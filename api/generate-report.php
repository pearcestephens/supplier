<?php
require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Generate Reports - Period-based reporting
 * 
 * Generates comprehensive reports for specified periods
 * Supports CSV and PDF formats
 * 
 * @return File download (CSV or PDF)
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Check authentication
supplier_require_auth_bridge(true);

try {
    $db = db();
    $supplierID = getSupplierID();
    
    // Get parameters
    $period = $_GET['period'] ?? 'this_month';
    $format = $_GET['format'] ?? 'csv';
    $customStart = $_GET['start_date'] ?? null;
    $customEnd = $_GET['end_date'] ?? null;
    
    // Calculate date range based on period
    switch ($period) {
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $periodLabel = date('F Y');
            break;
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('-1 month'));
            $endDate = date('Y-m-t', strtotime('-1 month'));
            $periodLabel = date('F Y', strtotime('-1 month'));
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-m-d');
            $periodLabel = 'Year to Date ' . date('Y');
            break;
        case 'custom':
            $startDate = $customStart ?? date('Y-m-01');
            $endDate = $customEnd ?? date('Y-m-d');
            $periodLabel = date('j M Y', strtotime($startDate)) . ' to ' . date('j M Y', strtotime($endDate));
            break;
        default:
            $startDate = $customStart ?? date('Y-m-01');
            $endDate = $customEnd ?? date('Y-m-d');
            $periodLabel = date('j M Y', strtotime($startDate)) . ' to ' . date('j M Y', strtotime($endDate));
    }
    
    // Query orders for the period
    $ordersQuery = "
        SELECT 
            t.id,
            t.public_id,
            t.created_at,
            t.expected_delivery_date,
            t.state,
            t.vend_number,
            o.name as outlet_name,
            o.store_code as outlet_code,
            COUNT(DISTINCT ti.id) as items_count,
            SUM(ti.quantity_sent) as total_units,
            COALESCE(SUM(ti.quantity_sent * ti.unit_cost), 0) as total_ex_gst
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.created_at BETWEEN ? AND ?
        AND t.deleted_at IS NULL
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $db->prepare($ordersQuery);
    $stmt->bind_param('sss', $supplierID, $startDate, $endDate);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate summary statistics
    $totalOrders = count($orders);
    $totalUnits = 0;
    $totalValue = 0;
    $statusBreakdown = [
        'OPEN' => 0,
        'SENT' => 0,
        'RECEIVING' => 0,
        'RECEIVED' => 0,
        'PARTIAL' => 0,
        'CANCELLED' => 0
    ];
    
    foreach ($orders as $order) {
        $totalUnits += $order['total_units'];
        $totalValue += $order['total_ex_gst'];
        if (isset($statusBreakdown[$order['state']])) {
            $statusBreakdown[$order['state']]++;
        }
    }
    
    $totalValueIncGST = $totalValue * 1.15;
    
    if ($format === 'csv') {
        // Generate CSV
        $filename = 'report_' . strtolower(str_replace(' ', '_', $periodLabel)) . '_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Report header
        fputcsv($output, ['Performance Report']);
        fputcsv($output, ['Period', $periodLabel]);
        fputcsv($output, ['Generated', date('j M Y, g:ia')]);
        fputcsv($output, ['Supplier', $_SESSION['supplier_name'] ?? 'Supplier']);
        fputcsv($output, []);
        
        // Summary statistics
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total Orders', $totalOrders]);
        fputcsv($output, ['Total Units', number_format($totalUnits)]);
        fputcsv($output, ['Total Value (ex GST)', '$' . number_format($totalValue, 2)]);
        fputcsv($output, ['Total Value (inc GST)', '$' . number_format($totalValueIncGST, 2)]);
        fputcsv($output, ['Average Order Value', $totalOrders > 0 ? '$' . number_format($totalValueIncGST / $totalOrders, 2) : '$0.00']);
        fputcsv($output, []);
        
        // Status breakdown
        fputcsv($output, ['Status Breakdown']);
        foreach ($statusBreakdown as $status => $count) {
            if ($count > 0) {
                fputcsv($output, [ucfirst(strtolower($status)), $count]);
            }
        }
        fputcsv($output, []);
        
        // Orders detail
        fputcsv($output, ['Order Details']);
        fputcsv($output, [
            'Order Number',
            'Date',
            'Outlet',
            'Status',
            'Items',
            'Units',
            'Value (ex GST)',
            'Value (inc GST)',
            'Expected Delivery'
        ]);
        
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['public_id'],
                date('j M Y', strtotime($order['created_at'])),
                $order['outlet_name'] ?? 'Unknown',
                ucfirst(strtolower($order['state'])),
                $order['items_count'],
                $order['total_units'],
                '$' . number_format($order['total_ex_gst'], 2),
                '$' . number_format($order['total_ex_gst'] * 1.15, 2),
                !empty($order['expected_delivery_date']) ? date('j M Y', strtotime($order['expected_delivery_date'])) : 'Not set'
            ]);
        }
        
        fclose($output);
        exit;
        
    } else {
        // PDF format - for now, redirect to CSV with message
        // TODO: Implement PDF generation with proper library
        header('Location: /supplier/?tab=downloads&error=pdf_not_implemented');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Generate Report Error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating report');
}
