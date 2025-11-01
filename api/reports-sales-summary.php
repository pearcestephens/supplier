<?php
/**
 * Reports API - Sales Summary by Week
 * 
 * Provides aggregated weekly sales data for reporting dashboard
 * Supports date range filtering and supplier isolation
 * 
 * @package SupplierPortal\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap.php';

// Security check
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supplierID = Auth::getSupplierId();
$db = db();

try {
    // Get parameters
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-365 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Query: Weekly sales aggregation
    $query = "
        SELECT 
            YEARWEEK(t.created_at, 1) as year_week,
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
        GROUP BY YEARWEEK(t.created_at, 1), week_start
        ORDER BY week_start ASC
    ";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $db->error);
    }
    
    $stmt->bind_param('sss', $supplierID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $weeklySales = [];
    while ($row = $result->fetch_assoc()) {
        $weeklySales[] = [
            'year_week' => $row['year_week'],
            'week_start' => $row['week_start'],
            'week_label' => date('M d, Y', strtotime($row['week_start'])),
            'order_count' => (int)$row['order_count'],
            'total_units' => (int)$row['total_units'],
            'total_revenue' => (float)$row['total_revenue'],
            'avg_order_value' => (float)$row['avg_order_value'],
            'unique_products' => (int)$row['unique_products'],
            'unique_stores' => (int)$row['unique_stores']
        ];
    }
    
    $stmt->close();
    
    // Calculate summary statistics
    $summary = [
        'total_weeks' => count($weeklySales),
        'total_revenue' => array_sum(array_column($weeklySales, 'total_revenue')),
        'total_units' => array_sum(array_column($weeklySales, 'total_units')),
        'total_orders' => array_sum(array_column($weeklySales, 'order_count')),
        'avg_weekly_revenue' => count($weeklySales) > 0 
            ? array_sum(array_column($weeklySales, 'total_revenue')) / count($weeklySales) 
            : 0,
        'avg_weekly_units' => count($weeklySales) > 0
            ? array_sum(array_column($weeklySales, 'total_units')) / count($weeklySales)
            : 0
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $weeklySales,
        'summary' => $summary,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
    ]);
}
