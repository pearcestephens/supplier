<?php
/**
 * Get Order Parcels & Tracking
 * Returns all parcels/boxes and their contents for an order
 */
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

// Verify supplier access
$stmt = $db->prepare("SELECT id FROM vend_consignments WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL");
$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$stmt->close();

// Get shipments & parcels
$parcels = [];
$stmt = $db->prepare("
    SELECT
        s.id as shipment_id,
        s.created_at as shipment_created,
        p.id as parcel_id,
        p.parcel_number,
        p.box_number,
        p.tracking_number,
        p.courier,
        p.weight_grams,
        p.weight_kg,
        p.length_mm,
        p.width_mm,
        p.height_mm,
        p.status as parcel_status,
        p.label_url,
        p.created_at as parcel_created
    FROM consignment_shipments s
    LEFT JOIN consignment_parcels p ON s.id = p.shipment_id
    WHERE s.consignment_id = ?
    ORDER BY p.box_number ASC, p.parcel_number ASC
");

$stmt->bind_param('i', $orderId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['parcel_id']) {
        $itemStmt = $db->prepare("
            SELECT
                pi.id,
                pi.item_id,
                pi.qty,
                pi.qty_received,
                li.product_id,
                p.name as product_name,
                p.sku
            FROM consignment_parcel_items pi
            LEFT JOIN vend_consignment_line_items li ON pi.item_id = li.id
            LEFT JOIN vend_products p ON li.product_id = p.id
            WHERE pi.parcel_id = ?
        ");
        $itemStmt->bind_param('i', $row['parcel_id']);
        $itemStmt->execute();
        $row['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();

        $parcels[] = $row;
    }
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $parcels,
    'count' => count($parcels)
]);
