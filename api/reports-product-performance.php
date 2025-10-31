<?php
/**
 * Reports API - Product Performance Analytics
 * 
 * Provides detailed product analytics including:
 * - Sales velocity (units/week)
 * - Revenue trending (30/60/90 days)
 * - Growth rate calculations
 * - Product lifecycle classification
 * - Top/bottom performers
 * 
 * @package SupplierPortal\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Forecasting.php';

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
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $sortBy = $_GET['sort_by'] ?? 'revenue'; // revenue, velocity, growth
    
    // Main query: Product performance metrics
    $query = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            p.sku,
            p.description,
            COUNT(DISTINCT ti.transfer_id) as order_count,
            SUM(ti.quantity_sent) as total_units,
            SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
            AVG(ti.unit_cost) as avg_unit_price,
            MIN(t.created_at) as first_sale_date,
            MAX(t.created_at) as last_sale_date,
            DATEDIFF(?, ?) as period_days,
            
            -- Last 30 days
            SUM(CASE 
                WHEN t.created_at >= DATE_SUB(?, INTERVAL 30 DAY) 
                THEN ti.quantity_sent * ti.unit_cost 
                ELSE 0 
            END) as revenue_30d,
            
            -- Last 60 days
            SUM(CASE 
                WHEN t.created_at >= DATE_SUB(?, INTERVAL 60 DAY) 
                THEN ti.quantity_sent * ti.unit_cost 
                ELSE 0 
            END) as revenue_60d,
            
            -- Last 90 days
            SUM(CASE 
                WHEN t.created_at >= DATE_SUB(?, INTERVAL 90 DAY) 
                THEN ti.quantity_sent * ti.unit_cost 
                ELSE 0 
            END) as revenue_90d
            
        FROM vend_consignment_line_items ti
        JOIN vend_consignments t ON ti.transfer_id = t.id
        JOIN vend_products p ON ti.product_id = p.id
        WHERE t.supplier_id = ?
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.deleted_at IS NULL
          AND t.created_at BETWEEN ? AND ?
        GROUP BY ti.product_id
        HAVING total_units > 0
        ORDER BY total_revenue DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $db->error);
    }
    
    $stmt->bind_param(
        'ssssssssi',
        $endDate, $startDate,  // for DATEDIFF
        $endDate,              // revenue_30d
        $endDate,              // revenue_60d
        $endDate,              // revenue_90d
        $supplierID,
        $startDate, $endDate,
        $limit
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $periodDays = max(1, (int)$row['period_days']);
        $totalUnits = (int)$row['total_units'];
        
        // Calculate velocity (units per week)
        $velocity = Forecasting::salesVelocity($totalUnits, $periodDays);
        
        // Calculate growth rates
        $revenue30d = (float)$row['revenue_30d'];
        $revenue60d = (float)$row['revenue_60d'];
        $revenue90d = (float)$row['revenue_90d'];
        
        $revenue30to60 = $revenue60d - $revenue30d;
        $growthRate = Forecasting::growthRate($revenue30d, $revenue30to60);
        
        // Classify lifecycle (simplified - would need weekly data for full analysis)
        $lifecycle = 'mature';
        if ($periodDays < 30) {
            $lifecycle = 'new';
        } elseif ($growthRate > 20) {
            $lifecycle = 'growth';
        } elseif ($growthRate < -20) {
            $lifecycle = 'decline';
        }
        
        // Calculate performance score (0-100)
        $velocityScore = min(100, $velocity * 10);
        $revenueScore = min(100, ($row['total_revenue'] / 1000) * 10);
        $performanceScore = ($velocityScore * 0.4) + ($revenueScore * 0.6);
        
        $products[] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'sku' => $row['sku'],
            'order_count' => (int)$row['order_count'],
            'total_units' => $totalUnits,
            'total_revenue' => (float)$row['total_revenue'],
            'avg_unit_price' => (float)$row['avg_unit_price'],
            'velocity' => round($velocity, 2),
            'revenue_trending' => [
                '30d' => $revenue30d,
                '60d' => $revenue60d,
                '90d' => $revenue90d
            ],
            'growth_rate' => round($growthRate, 2),
            'lifecycle' => $lifecycle,
            'performance_score' => round($performanceScore, 1),
            'first_sale_date' => $row['first_sale_date'],
            'last_sale_date' => $row['last_sale_date'],
            'period_days' => $periodDays
        ];
    }
    
    $stmt->close();
    
    // Sort by requested field
    usort($products, function($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'velocity':
                return $b['velocity'] <=> $a['velocity'];
            case 'growth':
                return $b['growth_rate'] <=> $a['growth_rate'];
            case 'score':
                return $b['performance_score'] <=> $a['performance_score'];
            case 'revenue':
            default:
                return $b['total_revenue'] <=> $a['total_revenue'];
        }
    });
    
    // Identify top and bottom performers
    $topPerformers = array_slice($products, 0, 10);
    $bottomPerformers = array_slice(array_reverse($products), 0, 10);
    
    // Calculate summary
    $summary = [
        'total_products' => count($products),
        'total_revenue' => array_sum(array_column($products, 'total_revenue')),
        'avg_velocity' => count($products) > 0 
            ? array_sum(array_column($products, 'velocity')) / count($products) 
            : 0,
        'lifecycle_distribution' => [
            'new' => count(array_filter($products, fn($p) => $p['lifecycle'] === 'new')),
            'growth' => count(array_filter($products, fn($p) => $p['lifecycle'] === 'growth')),
            'mature' => count(array_filter($products, fn($p) => $p['lifecycle'] === 'mature')),
            'decline' => count(array_filter($products, fn($p) => $p['lifecycle'] === 'decline'))
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'top_performers' => $topPerformers,
        'bottom_performers' => $bottomPerformers,
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
