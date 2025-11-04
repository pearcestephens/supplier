<?php
// Stream CSV for a single order
require_once dirname(__DIR__) . '/bootstrap.php';
enforceApiRateLimit();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Missing id');
}

$supplierID = getSupplierID();
$db = db();

// Verify supplier owns this order
$stmt = $db->prepare("SELECT public_id FROM vend_consignments WHERE id = ? AND supplier_id = ? AND deleted_at IS NULL");
$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(403);
    exit('Forbidden');
}
$publicId = $row['public_id'];

// Fetch items
$stmt = $db->prepare("SELECT li.id, p.sku, p.name, li.quantity, li.quantity_sent, li.unit_cost FROM vend_consignment_line_items li LEFT JOIN vend_products p ON p.id = li.product_id WHERE li.transfer_id = ? AND li.deleted_at IS NULL ORDER BY p.name ASC");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$res = $stmt->get_result();

$filename = sprintf('PO_%s.csv', preg_replace('/[^A-Za-z0-9_-]/','', (string)$publicId));
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
fputcsv($out, ['SKU','Product','Ordered','Sent','Unit Cost','Line Total']);
while ($item = $res->fetch_assoc()) {
    $ordered = (int)$item['quantity'];
    $sent = (int)$item['quantity_sent'];
    $unit = (float)$item['unit_cost'];
    $line = $ordered * $unit;
    fputcsv($out, [
        (string)$item['sku'],
        (string)$item['name'],
        $ordered,
        $sent,
        number_format($unit, 2, '.', ''),
        number_format($line, 2, '.', '')
    ]);
}
fclose($out);
exit;
