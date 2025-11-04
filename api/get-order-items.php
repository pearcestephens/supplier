<?php
/**
 * Get Order Items for Box Assignment
 *
 * Returns all line items for an order so suppliers can assign them to boxes
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

$orderId = (int)($_GET['id'] ?? 0);
$supplierID = getSupplierID();
$pdo = pdo();

if (!$orderId) {
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

// Verify order belongs to supplier
$stmt = $pdo->prepare("
    SELECT id FROM vend_consignments
    WHERE id = ? AND supplier_id = ?
");
$stmt->execute([$orderId, $supplierID]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Get line items
$stmt = $pdo->prepare("
    SELECT
        id,
        sku,
        product_name,
        quantity,
        quantity_sent,
        unit_cost
    FROM vend_consignment_line_items
    WHERE transfer_id = ?
    ORDER BY product_name
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'items' => $items
]);
