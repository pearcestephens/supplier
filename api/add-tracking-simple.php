<?php
/**
 * Simple Tracking Number Addition
 *
 * When supplier enters tracking numbers, automatically create boxes:
 * - 1 tracking number = 1 box
 * - No product assignment at this stage
 * - Just tracking + box count
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

try {
    // Verify supplier is logged in
    if (!isset($_SESSION['supplier_id'])) {
        throw new Exception('Not authenticated');
    }

    $supplier_id = $_SESSION['supplier_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $order_id = filter_var($input['order_id'] ?? '', FILTER_VALIDATE_INT);
    $tracking_numbers = $input['tracking_numbers'] ?? []; // Array of tracking numbers
    $carrier = trim($input['carrier'] ?? 'CourierPost');

    if (!$order_id) {
        throw new Exception('Invalid order ID');
    }

    if (empty($tracking_numbers) || !is_array($tracking_numbers)) {
        throw new Exception('Please provide at least one tracking number');
    }

    // Verify order belongs to this supplier
    $stmt = $db->prepare("
        SELECT id, public_id
        FROM vend_consignments
        WHERE id = ?
          AND supplier_id = ?
          AND deleted_at IS NULL
    ");
    $stmt->bind_param('is', $order_id, $supplier_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Order not found or access denied');
    }

    // Start transaction
    $db->begin_transaction();

    try {
        // Create ONE shipment for all boxes
        $stmt = $db->prepare("
            INSERT INTO consignment_shipments (
                transfer_id,
                delivery_mode,
                carrier_name,
                status,
                created_at
            ) VALUES (?, 'courier', ?, 'in_transit', NOW())
        ");
        $stmt->bind_param('is', $order_id, $carrier);
        $stmt->execute();
        $shipment_id = $db->insert_id;

        // Create one parcel (box) per tracking number
        $box_number = 1;
        $parcel_ids = [];

        foreach ($tracking_numbers as $tracking) {
            $tracking = trim($tracking);

            if (empty($tracking)) {
                continue; // Skip empty tracking numbers
            }

            $stmt = $db->prepare("
                INSERT INTO consignment_parcels (
                    shipment_id,
                    box_number,
                    parcel_number,
                    tracking_number,
                    courier,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'in_transit', NOW())
            ");

            $parcel_number = sprintf('BOX-%03d', $box_number);
            $stmt->bind_param('iisss',
                $shipment_id,
                $box_number,
                $parcel_number,
                $tracking,
                $carrier
            );
            $stmt->execute();

            $parcel_ids[] = [
                'id' => $db->insert_id,
                'box_number' => $box_number,
                'tracking' => $tracking
            ];

            $box_number++;
        }

        // Update main consignment status
        $stmt = $db->prepare("
            UPDATE vend_consignments
            SET state = 'SENT',
                tracking_number = ?,
                tracking_carrier = ?,
                tracking_updated_at = NOW()
            WHERE id = ?
        ");
        // Use first tracking number for legacy field
        $first_tracking = $tracking_numbers[0];
        $stmt->bind_param('ssi', $first_tracking, $carrier, $order_id);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        // Return success with created data
        echo json_encode([
            'success' => true,
            'message' => sprintf('%d box%s added with tracking numbers',
                count($parcel_ids),
                count($parcel_ids) > 1 ? 'es' : ''
            ),
            'data' => [
                'shipment_id' => $shipment_id,
                'parcels' => $parcel_ids,
                'total_boxes' => count($parcel_ids)
            ]
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
