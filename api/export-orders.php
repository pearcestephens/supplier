<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Export All Filtered Orders as CSV
 *
 * Generates CSV export for all orders matching current filters
 *
 * @return CSV file download with orders list
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
supplier_require_auth_bridge(true);

try {
    $db = db(); // Use MySQLi helper from bootstrap
    $supplierID = getSupplierID();

    // Get filter parameters (same as Orders Tab)
    $filterYear = isset($_GET['year']) && !empty($_GET['year']) ? intval($_GET['year']) : null;
    $filterQuarter = isset($_GET['quarter']) && $_GET['quarter'] !== 'all' ? intval($_GET['quarter']) : null;
    $filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
    $filterOutlet = isset($_GET['outlet']) && !empty($_GET['outlet']) ? $_GET['outlet'] : null;
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build WHERE clause dynamically
    $whereConditions = [
        "t.supplier_id = ?",
        "t.transfer_category = 'PURCHASE_ORDER'",
        "t.deleted_at IS NULL"
    ];

    $bindTypes = 's'; // supplier_id (string)
    $bindParams = [$supplierID];

    // Year filter
    if ($filterYear !== null) {
        $whereConditions[] = "YEAR(t.created_at) = ?";
        $bindTypes .= 'i';
        $bindParams[] = $filterYear;
    }

    // Quarter filter
    if ($filterQuarter !== null) {
        $whereConditions[] = "QUARTER(t.created_at) = ?";
        $bindTypes .= 'i';
        $bindParams[] = $filterQuarter;
    }

    // Status filter
    if ($filterStatus !== 'all') {
        switch ($filterStatus) {
            case 'active':
                $whereConditions[] = "t.state IN ('OPEN', 'PACKING')";
                break;
            case 'completed':
                $whereConditions[] = "t.state IN ('RECEIVED', 'CLOSED')";
                break;
            case 'cancelled':
                $whereConditions[] = "t.state = 'CANCELLED'";
                break;
        }
    }

    // Outlet filter
    if ($filterOutlet !== null) {
        $whereConditions[] = "t.outlet_to = ?";
        $bindTypes .= 's';
        $bindParams[] = $filterOutlet;
    }

    // Search filter
    if (!empty($searchTerm)) {
        $whereConditions[] = "(t.public_id LIKE ? OR t.vend_number LIKE ? OR o.name LIKE ?)";
        $searchPattern = '%' . $searchTerm . '%';
        $bindTypes .= 'sss';
        $bindParams[] = $searchPattern;
        $bindParams[] = $searchPattern;
        $bindParams[] = $searchPattern;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Query all matching orders
    $ordersQuery = "SELECT t.id, t.public_id, t.created_at, t.expected_delivery_date,
                           t.state, t.vend_number, o.name as outlet_name, o.store_code as outlet_code,
                           COUNT(DISTINCT ti.id) as items_count,
                           SUM(ti.quantity_sent) as total_units,
                           COALESCE(SUM(ti.quantity_sent * ti.unit_cost), 0) as total_ex_gst,
                           COALESCE(SUM(ti.quantity_sent * ti.unit_cost * 1.15), 0) as total_inc_gst
                    FROM vend_consignments t
                    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
                    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
                    WHERE {$whereClause}
                    GROUP BY t.id
                    ORDER BY t.created_at DESC";

    $stmt = $db->prepare($ordersQuery);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Set CSV headers
    $filename = 'orders_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Get supplier name from session
    $supplierName = $_SESSION['supplier_name'] ?? 'Supplier';

    // Write export information
    fputcsv($output, ['Orders Export']);
    fputcsv($output, ['Exported By', $supplierName]);
    fputcsv($output, ['Export Date', date('j M Y, g:ia')]);
    fputcsv($output, ['Total Orders', count($orders)]);

    // Applied filters
    $appliedFilters = [];
    if ($filterYear) $appliedFilters[] = "Year: $filterYear";
    if ($filterQuarter) $appliedFilters[] = "Quarter: Q$filterQuarter";
    if ($filterStatus !== 'all') $appliedFilters[] = "Status: " . ucfirst($filterStatus);
    if ($filterOutlet) $appliedFilters[] = "Outlet: $filterOutlet";
    if (!empty($searchTerm)) $appliedFilters[] = "Search: $searchTerm";

    if (!empty($appliedFilters)) {
        fputcsv($output, ['Filters Applied', implode(', ', $appliedFilters)]);
    }

    fputcsv($output, []); // Blank line

    // Write orders header
    fputcsv($output, [
        'Order Number',
        'Order Date',
        'Outlet',
        'Outlet Code',
        'Status',
        'Items',
        'Total Units',
        'Total (ex GST)',
        'Total (inc GST)',
        'Expected Delivery',
        'Reference'
    ]);

    // Calculate grand totals
    $grandTotalOrders = count($orders);
    $grandTotalItems = 0;
    $grandTotalUnits = 0;
    $grandTotalExGST = 0;
    $grandTotalIncGST = 0;

    // Write orders
    foreach ($orders as $order) {
        $status = ucfirst(strtolower($order['state']));

        fputcsv($output, [
            $order['public_id'],
            date('j M Y', strtotime($order['created_at'])),
            $order['outlet_name'] ?? 'Unknown',
            $order['outlet_code'] ?? 'N/A',
            $status,
            $order['items_count'],
            $order['total_units'],
            '$' . number_format($order['total_ex_gst'], 2),
            '$' . number_format($order['total_inc_gst'], 2),
            !empty($order['expected_delivery_date']) ? date('j M Y', strtotime($order['expected_delivery_date'])) : 'Not set',
            $order['reference'] ?? 'N/A'
        ]);

        $grandTotalItems += $order['items_count'];
        $grandTotalUnits += $order['total_units'];
        $grandTotalExGST += $order['total_ex_gst'];
        $grandTotalIncGST += $order['total_inc_gst'];
    }

    // Write grand totals
    fputcsv($output, []); // Blank line
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Orders', $grandTotalOrders]);
    fputcsv($output, ['Total Items', $grandTotalItems]);
    fputcsv($output, ['Total Units', $grandTotalUnits]);
    fputcsv($output, ['Total (ex GST)', '$' . number_format($grandTotalExGST, 2)]);
    fputcsv($output, ['GST (15%)', '$' . number_format($grandTotalIncGST - $grandTotalExGST, 2)]);
    fputcsv($output, ['Total (inc GST)', '$' . number_format($grandTotalIncGST, 2)]);

    if ($grandTotalOrders > 0) {
        fputcsv($output, ['Average Order Value', '$' . number_format($grandTotalIncGST / $grandTotalOrders, 2)]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log('Export Orders Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating CSV export');
}
?>
