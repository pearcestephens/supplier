<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Export Order Items to CSV
 *
 * Exports line items from a single order to CSV format
 */

require_once __DIR__ . '/../bootstrap.php';

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$supplierID = $_SESSION['supplier_id'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    http_response_code(400);
    die('Invalid order ID');
}

// Verify order belongs to supplier
$stmt = $db->prepare("
    SELECT
        t.id,
        t.public_id,
        t.state,
        o.name as outlet_name
    FROM vend_consignments t
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.id = ?
    AND t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.deleted_at IS NULL
");

$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die('Order not found or access denied');
}

// Get line items
$stmt = $db->prepare("
    SELECT
        p.sku,
        p.name as product_name,
        ti.quantity as qty_ordered,
        ti.quantity_sent as qty_received,
        ti.unit_cost,
        (ti.quantity * ti.unit_cost) as line_total
    FROM vend_consignment_line_items ti
    LEFT JOIN vend_products p ON ti.product_id = p.id
    WHERE ti.transfer_id = ?
    AND ti.deleted_at IS NULL
    ORDER BY p.name ASC
");

$stmt->bind_param('i', $orderId);
$stmt->execute();
$lineItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Generate CSV filename
$filename = sprintf(
    'order_%s_%s.csv',
    $order['public_id'] ?? $orderId,
    date('Y-m-d')
);

// Set headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write order summary at top
fputcsv($output, ['Order Export Summary']);
fputcsv($output, ['Order ID', $order['public_id'] ?? $orderId]);
fputcsv($output, ['Outlet', $order['outlet_name'] ?? 'Unknown']);
fputcsv($output, ['Status', $order['state'] ?? 'Unknown']);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row for spacing

// Write header row
fputcsv($output, [
    'Order ID',
    'Outlet',
    'Status',
    'SKU',
    'Product Name',
    'Qty Ordered',
    'Qty Received',
    'Unit Cost',
    'Line Total'
]);

// Write data rows
if (empty($lineItems)) {
    // If no items, write a message row
    fputcsv($output, [
        $order['public_id'] ?? $orderId,
        $order['outlet_name'],
        $order['state'],
        '',
        'No items in this order',
        0,
        0,
        '$0.00',
        '$0.00'
    ]);
    $totalOrdered = 0;
    $totalReceived = 0;
    $grandTotal = 0;
} else {
    foreach ($lineItems as $item) {
        fputcsv($output, [
            $order['public_id'] ?? $orderId,
            $order['outlet_name'] ?? 'Unknown',
            $order['state'] ?? 'Unknown',
            $item['sku'] ?? '',
            $item['product_name'] ?? 'Unknown Product',
            (int)($item['qty_ordered'] ?? 0),
            (int)($item['qty_received'] ?? 0),
            '$' . number_format((float)($item['unit_cost'] ?? 0), 2),
            '$' . number_format((float)($item['line_total'] ?? 0), 2)
        ]);
    }

    // Calculate totals
    $totalOrdered = array_sum(array_column($lineItems, 'qty_ordered'));
    $totalReceived = array_sum(array_column($lineItems, 'qty_received'));
    $grandTotal = array_sum(array_column($lineItems, 'line_total'));
}

fputcsv($output, [
    '', '', '', '', 'TOTALS',
    $totalOrdered,
    $totalReceived,
    '',
    '$' . number_format($grandTotal, 2)
]);

fclose($output);
exit;
