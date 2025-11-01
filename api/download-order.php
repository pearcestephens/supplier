<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Download Single Order as CSV
 *
 * Generates CSV export for a single purchase order with line items
 *
 * @param int $order_id - Order ID to export
 * @return CSV file download
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
supplier_require_auth_bridge(true);

try {
    $db = db(); // Use MySQLi helper from bootstrap
    $supplierID = getSupplierID();

    // Get order ID from query parameter
    $orderID = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

    if ($orderID <= 0) {
        http_response_code(400);
        die('Invalid order ID');
    }

    // Verify this order belongs to this supplier
    $verifyQuery = "SELECT t.id, t.public_id, t.created_at, t.expected_delivery_date,
                           t.state, t.reference, o.name as outlet_name, o.outlet_code
                    FROM vend_consignments t
                    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
                    WHERE t.id = ? AND t.supplier_id = ? AND t.transfer_category = 'PURCHASE_ORDER'
                      AND t.deleted_at IS NULL
                    LIMIT 1";

    $stmt = $db->prepare($verifyQuery);
    $stmt->bind_param('is', $orderID, $supplierID);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        http_response_code(404);
        die('Order not found');
    }

    // Get line items
    $itemsQuery = "SELECT p.name as product_name, p.sku, p.supplier_code,
                          ti.quantity, ti.cost as unit_cost,
                          (ti.quantity * ti.cost) as line_total_ex_gst,
                          (ti.quantity * ti.cost * 1.15) as line_total_inc_gst
                   FROM vend_consignment_line_items ti
                   LEFT JOIN vend_products p ON ti.product_id = p.id
                   WHERE ti.transfer_id = ?
                   ORDER BY p.name ASC";

    $stmt = $db->prepare($itemsQuery);
    $stmt->bind_param('i', $orderID);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Set CSV headers
    $filename = 'order_' . $order['public_id'] . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write order header
    fputcsv($output, ['Order Information']);
    fputcsv($output, ['Order Number', $order['public_id']]);
    fputcsv($output, ['Order Date', date('j M Y', strtotime($order['created_at']))]);
    fputcsv($output, ['Status', ucfirst(strtolower($order['state']))]);
    fputcsv($output, ['Outlet', $order['outlet_name'] . ' (' . $order['outlet_code'] . ')']);
    fputcsv($output, ['Reference', $order['reference'] ?? 'N/A']);

    if (!empty($order['expected_delivery_date'])) {
        fputcsv($output, ['Expected Delivery', date('j M Y', strtotime($order['expected_delivery_date']))]);
    }

    fputcsv($output, []); // Blank line

    // Write line items header
    fputcsv($output, ['Line Items']);
    fputcsv($output, [
        'Product Name',
        'SKU',
        'Quantity',
        'Unit Cost (ex GST)',
        'Line Total (ex GST)',
        'Line Total (inc GST)'
    ]);

    // Calculate totals
    $totalQty = 0;
    $totalExGST = 0;
    $totalIncGST = 0;

    // Write line items
    foreach ($items as $item) {
        fputcsv($output, [
            $item['product_name'] ?? 'Unknown Product',
            $item['sku'] ?? 'N/A',
            $item['quantity'],
            '$' . number_format($item['unit_cost'], 2),
            '$' . number_format($item['line_total_ex_gst'], 2),
            '$' . number_format($item['line_total_inc_gst'], 2)
        ]);

        $totalQty += $item['quantity'];
        $totalExGST += $item['line_total_ex_gst'];
        $totalIncGST += $item['line_total_inc_gst'];
    }

    // Write totals
    fputcsv($output, []); // Blank line
    fputcsv($output, ['Totals']);
    fputcsv($output, ['Total Items', count($items)]);
    fputcsv($output, ['Total Units', $totalQty]);
    fputcsv($output, ['Total (ex GST)', '$' . number_format($totalExGST, 2)]);
    fputcsv($output, ['GST (15%)', '$' . number_format($totalIncGST - $totalExGST, 2)]);
    fputcsv($output, ['Total (inc GST)', '$' . number_format($totalIncGST, 2)]);

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log('Download Order Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating CSV export');
}
?>
