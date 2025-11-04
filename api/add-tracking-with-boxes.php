<?php
/**
 * Add Tracking with Box/Parcel Assignment
 *
 * Creates shipment boxes and assigns items to them
 * Updates order status to SENT
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

$supplierID = getSupplierID();
$pdo = pdo();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$orderId = (int)($input['order_id'] ?? 0);
$carrier = trim($input['carrier'] ?? '');
$boxes = $input['boxes'] ?? [];

if (!$orderId || empty($boxes)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Verify order belongs to supplier
$stmt = $pdo->prepare("
    SELECT id, state FROM vend_consignments
    WHERE id = ? AND supplier_id = ?
");
$stmt->execute([$orderId, $supplierID]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

try {
    $pdo->beginTransaction();

    // First, create or get shipment record
    $stmt = $pdo->prepare("
        SELECT id FROM consignment_shipments
        WHERE consignment_id = ?
    ");
    $stmt->execute([$orderId]);
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shipment) {
        // Create shipment record
        $stmt = $pdo->prepare("
            INSERT INTO consignment_shipments
            (consignment_id, created_at)
            VALUES (?, NOW())
        ");
        $stmt->execute([$orderId]);
        $shipmentId = $pdo->lastInsertId();
    } else {
        $shipmentId = $shipment['id'];

        // Delete existing parcels if re-submitting
        $stmt = $pdo->prepare("DELETE FROM consignment_parcels WHERE shipment_id = ?");
        $stmt->execute([$shipmentId]);
    }

    // Create boxes and assign items
    foreach ($boxes as $index => $box) {
        $boxNumber = $index + 1;
        $trackingNumber = trim($box['tracking']);

        if (empty($trackingNumber)) {
            throw new Exception("Box #{$boxNumber} missing tracking number");
        }

        // Insert parcel (box)
        $stmt = $pdo->prepare("
            INSERT INTO consignment_parcels
            (shipment_id, parcel_number, box_number, tracking_number, courier)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$shipmentId, $boxNumber, $boxNumber, $trackingNumber, $carrier]);
        $parcelId = $pdo->lastInsertId();

        // Assign items to this parcel
        if (!empty($box['items'])) {
            foreach ($box['items'] as $item) {
                $lineItemId = (int)$item['id'];
                $quantity = (int)$item['qty'];

                if ($quantity > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO consignment_parcel_items
                        (parcel_id, item_id, qty)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$parcelId, $lineItemId, $quantity]);
                }
            }
        }
    }

    // Update order status to SENT
    $stmt = $pdo->prepare("
        UPDATE vend_consignments
        SET state = 'SENT',
            tracking_carrier = ?,
            tracking_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$carrier, $orderId]);

    // Log to order history
    $stmt = $pdo->prepare("
        INSERT INTO order_history
        (order_id, action, note, created_by)
        VALUES (?, 'Tracking added', ?, ?)
    ");
    $note = count($boxes) . " box(es) created with tracking numbers";
    $stmt->execute([$orderId, $note, 'Supplier']);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Shipment created successfully',
        'boxes_created' => count($boxes)
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
