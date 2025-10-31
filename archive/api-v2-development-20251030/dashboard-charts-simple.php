<?php
/**
 * Dashboard Charts API v2 - Simplified Working Version
 * 
 * Provides Chart.js compatible data for supplier dashboard visualizations
 * 
 * @endpoint: /api/v2/dashboard-charts.php
 * @method: GET
 * @params: supplier_id (required), chart_type (required)
 * @version: 2.0.0
 */

declare(strict_types=1);

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

/**
 * Send API response
 */
function apiResponse(bool $success, mixed $data = null, mixed $error = null): void {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => uniqid('req_', true),
        'api_version' => '2.0'
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error;
        if (isset($error['code'])) {
            http_response_code($error['code']);
        } else {
            http_response_code(500);
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4", "jcepnzzkmj", "wprKh9Jq63", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    apiResponse(false, null, ['message' => 'Database connection failed', 'code' => 500]);
}

try {
    // Validate parameters
    if (!isset($_GET['supplier_id']) || empty(trim($_GET['supplier_id']))) {
        apiResponse(false, null, ['message' => 'Missing required parameter: supplier_id', 'code' => 400]);
    }
    
    if (!isset($_GET['chart_type']) || empty(trim($_GET['chart_type']))) {
        apiResponse(false, null, ['message' => 'Missing required parameter: chart_type', 'code' => 400]);
    }
    
    $supplierID = trim($_GET['supplier_id']);
    $chartType = trim($_GET['chart_type']);
    
    // Validate chart type
    $validChartTypes = ['orders_trend', 'top_products', 'outlet_performance', 'warranty_trends', 'revenue_analysis'];
    if (!in_array($chartType, $validChartTypes)) {
        apiResponse(false, null, ['message' => 'Invalid chart_type. Valid options: ' . implode(', ', $validChartTypes), 'code' => 400]);
    }
    
    // Validate supplier
    $stmt = $pdo->prepare("SELECT id FROM vend_suppliers WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$supplierID]);
    if (!$stmt->fetch()) {
        apiResponse(false, null, ['message' => 'Invalid supplier ID', 'code' => 403]);
    }
    
    $chartData = [];
    
    // Generate chart data based on type
    switch ($chartType) {
        case 'orders_trend':
            // 30-day orders trend
            $query = "
                SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as order_count
                FROM transfers t
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND t.deleted_at IS NULL
                GROUP BY DATE(t.created_at)
                ORDER BY date ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$supplierID]);
            $data = $stmt->fetchAll();
            
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $labels[] = date('M j', strtotime($row['date']));
                $values[] = (int)$row['order_count'];
            }
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Orders per Day',
                        'data' => $values,
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'tension' => 0.1
                    }]
                ],
                'options' => [
                    'responsive' => true,
                    'scales' => [
                        'y' => ['beginAtZero' => true]
                    ]
                ]
            ];
            break;
            
        case 'top_products':
            // Top 10 products by units shipped
            $query = "
                SELECT 
                    p.name,
                    SUM(ti.qty_sent_total) as total_shipped
                FROM transfer_items ti
                INNER JOIN transfers t ON ti.transfer_id = t.id
                INNER JOIN vend_products p ON ti.product_id = p.id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND t.deleted_at IS NULL
                AND p.deleted_at IS NULL
                GROUP BY p.id
                ORDER BY total_shipped DESC
                LIMIT 10
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$supplierID]);
            $data = $stmt->fetchAll();
            
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $labels[] = substr($row['name'], 0, 20) . (strlen($row['name']) > 20 ? '...' : '');
                $values[] = (int)$row['total_shipped'];
            }
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Units Shipped',
                        'data' => $values,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.8)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1
                    }]
                ],
                'options' => [
                    'responsive' => true,
                    'scales' => [
                        'y' => ['beginAtZero' => true]
                    ]
                ]
            ];
            break;
            
        case 'outlet_performance':
            // Orders by outlet
            $query = "
                SELECT 
                    vo.name as outlet_name,
                    COUNT(*) as order_count
                FROM transfers t
                INNER JOIN vend_outlets vo ON t.outlet_to = vo.id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND t.deleted_at IS NULL
                AND vo.deleted_at IS NULL
                GROUP BY vo.id
                ORDER BY order_count DESC
                LIMIT 8
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$supplierID]);
            $data = $stmt->fetchAll();
            
            $labels = [];
            $values = [];
            $colors = [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(199, 199, 199, 0.8)',
                'rgba(83, 102, 255, 0.8)'
            ];
            
            foreach ($data as $i => $row) {
                $labels[] = $row['outlet_name'];
                $values[] = (int)$row['order_count'];
            }
            
            $chartData = [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Orders',
                        'data' => $values,
                        'backgroundColor' => array_slice($colors, 0, count($values))
                    }]
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => [
                        'legend' => ['position' => 'bottom']
                    ]
                ]
            ];
            break;
            
        case 'warranty_trends':
            // Weekly warranty claims
            $query = "
                SELECT 
                    WEEK(fp.created_at) as week_num,
                    YEAR(fp.created_at) as year_num,
                    COUNT(*) as claim_count
                FROM faulty_products fp
                INNER JOIN vend_products p ON fp.product_id = p.id
                WHERE p.supplier_id = ?
                AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                AND p.deleted_at IS NULL
                GROUP BY YEAR(fp.created_at), WEEK(fp.created_at)
                ORDER BY year_num ASC, week_num ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$supplierID]);
            $data = $stmt->fetchAll();
            
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $labels[] = 'Week ' . $row['week_num'];
                $values[] = (int)$row['claim_count'];
            }
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Warranty Claims',
                        'data' => $values,
                        'borderColor' => 'rgb(255, 99, 132)',
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'tension' => 0.1
                    }]
                ],
                'options' => [
                    'responsive' => true,
                    'scales' => [
                        'y' => ['beginAtZero' => true]
                    ]
                ]
            ];
            break;
            
        case 'revenue_analysis':
            // Monthly revenue for last 12 months
            $query = "
                SELECT 
                    YEAR(t.created_at) as year_num,
                    MONTH(t.created_at) as month_num,
                    SUM(ti.qty_sent_total * COALESCE(ti.cost, 0)) as revenue
                FROM transfers t
                LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND t.state IN ('RECEIVED', 'CLOSED')
                AND t.deleted_at IS NULL
                GROUP BY YEAR(t.created_at), MONTH(t.created_at)
                ORDER BY year_num ASC, month_num ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$supplierID]);
            $data = $stmt->fetchAll();
            
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $monthName = date('M Y', mktime(0, 0, 0, $row['month_num'], 1, $row['year_num']));
                $labels[] = $monthName;
                $values[] = round((float)$row['revenue'], 2);
            }
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Revenue ($)',
                        'data' => $values,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.8)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'borderWidth' => 1
                    }]
                ],
                'options' => [
                    'responsive' => true,
                    'scales' => [
                        'y' => ['beginAtZero' => true]
                    ]
                ]
            ];
            break;
            
        default:
            apiResponse(false, null, ['message' => 'Chart type not implemented', 'code' => 400]);
    }
    
    apiResponse(true, $chartData, null);

} catch (PDOException $e) {
    apiResponse(false, null, ['message' => 'Database query failed: ' . $e->getMessage(), 'code' => 500]);
} catch (Exception $e) {
    apiResponse(false, null, ['message' => 'Server error: ' . $e->getMessage(), 'code' => 500]);
}
?>