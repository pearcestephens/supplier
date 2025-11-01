<?php
/**
 * Reports API - Export Handler
 * 
 * Handles report exports in multiple formats:
 * - CSV (comma-separated values)
 * - Excel (Microsoft Excel format)
 * - PDF (printable report)
 * 
 * @package SupplierPortal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/ReportGenerator.php';

// Security check
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();
$db = db();

try {
    // Get parameters
    $format = $_GET['format'] ?? 'csv'; // csv, excel, pdf
    $reportType = $_GET['type'] ?? 'sales_summary'; // sales_summary, product_performance, forecast
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Validate format
    if (!in_array($format, ['csv', 'excel', 'pdf'])) {
        throw new Exception('Invalid export format');
    }
    
    // Generate filename
    $dateStr = date('Y-m-d');
    $filename = "supplier_report_{$reportType}_{$dateStr}.{$format}";
    
    // Fetch data based on report type
    switch ($reportType) {
        case 'sales_summary':
            $data = fetchSalesSummary($db, $supplierID, $startDate, $endDate);
            $headers = ['Week Start', 'Orders', 'Units Sold', 'Revenue', 'Avg Order Value', 'Unique Products', 'Unique Stores'];
            $title = 'Sales Summary Report';
            break;
            
        case 'product_performance':
            $data = fetchProductPerformance($db, $supplierID, $startDate, $endDate);
            $headers = ['Product Name', 'SKU', 'Orders', 'Units Sold', 'Revenue', 'Velocity', 'Growth Rate', 'Lifecycle'];
            $title = 'Product Performance Report';
            break;
            
        case 'top_products':
            $data = fetchTopProducts($db, $supplierID, $startDate, $endDate);
            $headers = ['Rank', 'Product Name', 'SKU', 'Orders', 'Units Sold', 'Revenue', 'Avg Price'];
            $title = 'Top Products Report';
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    if (empty($data)) {
        throw new Exception('No data available for export');
    }
    
    // Export based on format
    switch ($format) {
        case 'csv':
            ReportGenerator::exportCSV($data, $headers, $filename);
            break;
            
        case 'excel':
            ReportGenerator::exportExcel($data, $headers, $filename);
            break;
            
        case 'pdf':
            $html = '<h1>' . htmlspecialchars($title) . '</h1>';
            $html .= '<p><strong>Supplier:</strong> ' . htmlspecialchars($supplierName) . '</p>';
            $html .= '<p><strong>Period:</strong> ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . '</p>';
            $html .= '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
            
            // Add summary stats
            $stats = [
                'Total Records' => count($data),
                'Report Type' => ucwords(str_replace('_', ' ', $reportType)),
                'Date Range' => "{$startDate} to {$endDate}"
            ];
            $html .= ReportGenerator::generateSummaryHTML($stats);
            
            // Add data table
            $html .= ReportGenerator::generateTableHTML($data, $headers);
            
            ReportGenerator::exportPDF($html, $filename, ['title' => $title]);
            break;
    }
    
} catch (Exception $e) {
    // If headers not sent yet, return JSON error
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Export failed',
            'message' => DEBUG_MODE ? $e->getMessage() : 'An error occurred during export'
        ]);
    } else {
        // Headers already sent, just log the error
        error_log('Export error: ' . $e->getMessage());
    }
}

/**
 * Fetch sales summary data
 */
function fetchSalesSummary($db, $supplierID, $startDate, $endDate): array
{
    $query = "
        SELECT 
            DATE(DATE_SUB(t.created_at, INTERVAL WEEKDAY(t.created_at) DAY)) as week_start,
            COUNT(DISTINCT t.id) as order_count,
            SUM(ti.quantity_sent) as total_units,
            SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
            AVG(ti.quantity_sent * ti.unit_cost) as avg_order_value,
            COUNT(DISTINCT ti.product_id) as unique_products,
            COUNT(DISTINCT t.outlet_to) as unique_stores
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.deleted_at IS NULL
          AND t.created_at BETWEEN ? AND ?
        GROUP BY week_start
        ORDER BY week_start ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('sss', $supplierID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            date('M d, Y', strtotime($row['week_start'])),
            $row['order_count'],
            $row['total_units'],
            '$' . number_format((float)$row['total_revenue'], 2),
            '$' . number_format((float)$row['avg_order_value'], 2),
            $row['unique_products'],
            $row['unique_stores']
        ];
    }
    
    $stmt->close();
    return $data;
}

/**
 * Fetch product performance data
 */
function fetchProductPerformance($db, $supplierID, $startDate, $endDate): array
{
    $query = "
        SELECT 
            p.name as product_name,
            p.sku,
            COUNT(DISTINCT ti.transfer_id) as order_count,
            SUM(ti.quantity_sent) as total_units,
            SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
            DATEDIFF(?, ?) as period_days
        FROM vend_consignment_line_items ti
        JOIN vend_consignments t ON ti.transfer_id = t.id
        JOIN vend_products p ON ti.product_id = p.id
        WHERE t.supplier_id = ?
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.deleted_at IS NULL
          AND t.created_at BETWEEN ? AND ?
        GROUP BY ti.product_id
        ORDER BY total_revenue DESC
        LIMIT 100
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('sssss', $endDate, $startDate, $supplierID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    require_once __DIR__ . '/../lib/Forecasting.php';
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $periodDays = max(1, (int)$row['period_days']);
        $velocity = Forecasting::salesVelocity((int)$row['total_units'], $periodDays);
        
        $data[] = [
            $row['product_name'],
            $row['sku'],
            $row['order_count'],
            $row['total_units'],
            '$' . number_format((float)$row['total_revenue'], 2),
            number_format($velocity, 2) . ' u/wk',
            'N/A', // Growth rate requires more complex calculation
            'Mature' // Simplified lifecycle
        ];
    }
    
    $stmt->close();
    return $data;
}

/**
 * Fetch top products data
 */
function fetchTopProducts($db, $supplierID, $startDate, $endDate): array
{
    $query = "
        SELECT 
            p.name as product_name,
            p.sku,
            COUNT(DISTINCT ti.transfer_id) as times_ordered,
            SUM(ti.quantity_sent) as total_quantity,
            SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
            AVG(ti.unit_cost) as avg_unit_price
        FROM vend_consignment_line_items ti
        JOIN vend_consignments t ON ti.transfer_id = t.id
        JOIN vend_products p ON ti.product_id = p.id
        WHERE t.supplier_id = ?
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.deleted_at IS NULL
          AND t.created_at BETWEEN ? AND ?
        GROUP BY ti.product_id
        ORDER BY total_revenue DESC
        LIMIT 50
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('sss', $supplierID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            '#' . $rank++,
            $row['product_name'],
            $row['sku'],
            $row['times_ordered'],
            $row['total_quantity'],
            '$' . number_format((float)$row['total_revenue'], 2),
            '$' . number_format((float)$row['avg_unit_price'], 2)
        ];
    }
    
    $stmt->close();
    return $data;
}
