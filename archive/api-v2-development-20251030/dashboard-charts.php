<?php
/**
 * Dashboard Charts API v2 - Fixed with proper deleted_at handling
 * 
 * Provides Chart.js compatible data for supplier dashboard visualizations
 * Uses exact database schema fields with flexible deleted_at patterns
 * 
 * @endpoint: /api/v2/dashboard-charts.php
 * @method: GET
 * @params: supplier_id (required), chart_type (required)
 * @auth: Session-based supplier authentication
 * @version: 2.1.0 - Fixed deleted_at handling
 */

declare(strict_types=1);

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Get the correct "not deleted" condition for each table
 * Vend tables use different patterns for deleted_at
 */
function getNotDeletedCondition(string $table, string $alias = ''): string {
    $field = $alias ? "{$alias}.deleted_at" : "deleted_at";
    
    switch ($table) {
        case 'vend_suppliers':
        case 'vend_outlets':
        case 'vend_products':
            // Vend tables typically use NULL, empty string, or zero date for active records
            return "({$field} IS NULL OR {$field} = '' OR {$field} = '0000-00-00 00:00:00' OR {$field} = '0000-00-00')";
            
        case 'transfers':
        case 'transfer_items':
        case 'faulty_products':
            // Internal tables may use different patterns - start with most common
            return "({$field} IS NULL OR {$field} = '' OR {$field} = '0000-00-00 00:00:00')";
            
        default:
            // Default to most permissive
            return "({$field} IS NULL OR {$field} = '' OR {$field} = '0000-00-00 00:00:00' OR {$field} = '0000-00-00')";
    }
}

/**
 * Send API response
 */
function apiResponse(bool $success, mixed $data = null, mixed $error = null): void {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => uniqid('req_', true),
        'api_version' => '2.1'
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
    
    // Validate supplier exists and is active - using flexible deletion check
    $supplierCondition = getNotDeletedCondition('vend_suppliers');
    $stmt = $pdo->prepare("SELECT id FROM vend_suppliers WHERE id = ? AND {$supplierCondition} LIMIT 1");
    $stmt->execute([$supplierID]);
    if (!$stmt->fetch()) {
        apiResponse(false, null, ['message' => 'Invalid supplier ID or supplier not found', 'code' => 403]);
    }
    
    // Performance timing
    $startTime = microtime(true);
    
    // Chart data based on requested type
    $chartData = [];
    
    switch ($chartType) {
        case 'orders_trend':
            // Orders trend over last 30 days (daily breakdown)
            // Using exact schema: transfers.transfer_category='PURCHASE_ORDER', transfers.created_at
            $transfersCondition = getNotDeletedCondition('transfers', 't');
            $ordersTrendQuery = "
                SELECT 
                    DATE(t.created_at) as order_date,
                    COUNT(*) as order_count,
                    SUM(CASE WHEN t.state IN ('RECEIVED', 'CLOSED') THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN t.state = 'OPEN' THEN 1 ELSE 0 END) as open_count,
                    COUNT(DISTINCT ti.product_id) as unique_products,
                    SUM(ti.qty_sent_total) as items_shipped
                FROM transfers t
                LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND {$transfersCondition}
                GROUP BY DATE(t.created_at)
                ORDER BY order_date ASC
            ";
            
            $stmt = $pdo->prepare($ordersTrendQuery);
            $stmt->execute([$supplierID]);
            $trendData = $stmt->fetchAll();
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => array_map(function($row) {
                        return date('M j', strtotime($row['order_date']));
                    }, $trendData),
                    'datasets' => [
                        [
                            'label' => 'Total Orders',
                            'data' => array_map(function($row) {
                                return (int)$row['order_count'];
                            }, $trendData),
                            'borderColor' => 'rgb(79, 70, 229)',
                            'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                            'fill' => true,
                            'tension' => 0.4
                        ],
                        [
                            'label' => 'Completed Orders',
                            'data' => array_map(function($row) {
                                return (int)$row['completed_count'];
                            }, $trendData),
                            'borderColor' => 'rgb(16, 185, 129)',
                            'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                            'fill' => false,
                            'tension' => 0.4
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => [
                                'precision' => 0
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ]
                    ]
                ]
            ];
            break;
            
        case 'top_products':
            // Top 10 products by shipped quantity (last 30 days)
            // Using exact schema: vend_products.name, transfer_items.qty_sent_total
            $transfersCondition = getNotDeletedCondition('transfers', 't');
            $productsCondition = getNotDeletedCondition('vend_products', 'p');
            $topProductsQuery = "
                SELECT 
                    p.name,
                    p.sku,
                    SUM(ti.qty_sent_total) as total_shipped,
                    COUNT(DISTINCT ti.transfer_id) as order_frequency,
                    AVG(ti.cost) as avg_cost
                FROM transfer_items ti
                INNER JOIN transfers t ON ti.transfer_id = t.id
                INNER JOIN vend_products p ON ti.product_id = p.id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND {$transfersCondition}
                AND {$productsCondition}
                GROUP BY p.id
                ORDER BY total_shipped DESC
                LIMIT 10
            ";
            
            $stmt = $pdo->prepare($topProductsQuery);
            $stmt->execute([$supplierID]);
            $productsData = $stmt->fetchAll();
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => array_map(function($row) {
                        return strlen($row['name']) > 20 ? substr($row['name'], 0, 20) . '...' : $row['name'];
                    }, $productsData),
                    'datasets' => [
                        [
                            'label' => 'Units Shipped',
                            'data' => array_map(function($row) {
                                return (int)$row['total_shipped'];
                            }, $productsData),
                            'backgroundColor' => [
                                'rgba(79, 70, 229, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(251, 146, 60, 0.8)',
                                'rgba(168, 85, 247, 0.8)'
                            ],
                            'borderColor' => [
                                'rgb(79, 70, 229)',
                                'rgb(16, 185, 129)',
                                'rgb(245, 158, 11)',
                                'rgb(239, 68, 68)',
                                'rgb(139, 92, 246)',
                                'rgb(59, 130, 246)',
                                'rgb(236, 72, 153)',
                                'rgb(34, 197, 94)',
                                'rgb(251, 146, 60)',
                                'rgb(168, 85, 247)'
                            ],
                            'borderWidth' => 1
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => [
                                'precision' => 0
                            ]
                        ],
                        'x' => [
                            'ticks' => [
                                'maxRotation' => 45,
                                'minRotation' => 0
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => false
                        ]
                    ]
                ]
            ];
            break;
            
        case 'outlet_performance':
            // Outlet performance by order count and value (last 30 days)
            // Using exact schema: vend_outlets.name, transfers.outlet_to
            $transfersCondition = getNotDeletedCondition('transfers', 't');
            $outletsCondition = getNotDeletedCondition('vend_outlets', 'vo');
            $outletPerformanceQuery = "
                SELECT 
                    vo.name as outlet_name,
                    COUNT(DISTINCT t.id) as order_count,
                    SUM(ti.qty_sent_total) as total_items,
                    SUM(ti.qty_sent_total * COALESCE(ti.cost, 0)) as total_value,
                    AVG(TIMESTAMPDIFF(DAY, t.created_at, COALESCE(t.updated_at, NOW()))) as avg_processing_days
                FROM transfers t
                INNER JOIN vend_outlets vo ON t.outlet_to = vo.id
                LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND {$transfersCondition}
                AND {$outletsCondition}
                GROUP BY vo.id
                ORDER BY order_count DESC
                LIMIT 8
            ";
            
            $stmt = $pdo->prepare($outletPerformanceQuery);
            $stmt->execute([$supplierID]);
            $outletsData = $stmt->fetchAll();
            
            $chartData = [
                'type' => 'doughnut',
                'data' => [
                    'labels' => array_map(function($row) {
                        return strlen($row['outlet_name']) > 15 ? substr($row['outlet_name'], 0, 15) . '...' : $row['outlet_name'];
                    }, $outletsData),
                    'datasets' => [
                        [
                            'label' => 'Order Count',
                            'data' => array_map(function($row) {
                                return (int)$row['order_count'];
                            }, $outletsData),
                            'backgroundColor' => [
                                'rgba(79, 70, 229, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(34, 197, 94, 0.8)'
                            ],
                            'borderWidth' => 2,
                            'borderColor' => '#ffffff'
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'right'
                        ]
                    ]
                ]
            ];
            break;
            
        case 'warranty_trends':
            // Warranty claims trend over last 60 days (weekly breakdown)
            // Using exact schema: faulty_products.supplier_status, faulty_products.created_at
            $productsCondition = getNotDeletedCondition('vend_products', 'p');
            $warrantyTrendsQuery = "
                SELECT 
                    YEARWEEK(fp.created_at) as week_year,
                    DATE(DATE_SUB(fp.created_at, INTERVAL WEEKDAY(fp.created_at) DAY)) as week_start,
                    COUNT(*) as total_claims,
                    SUM(CASE WHEN fp.supplier_status = 0 THEN 1 ELSE 0 END) as pending_claims,
                    SUM(CASE WHEN fp.supplier_status = 1 THEN 1 ELSE 0 END) as approved_claims,
                    SUM(CASE WHEN fp.supplier_status = 2 THEN 1 ELSE 0 END) as rejected_claims
                FROM faulty_products fp
                INNER JOIN vend_products p ON fp.product_id = p.id
                WHERE p.supplier_id = ?
                AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND {$productsCondition}
                GROUP BY YEARWEEK(fp.created_at)
                ORDER BY week_start ASC
            ";
            
            $stmt = $pdo->prepare($warrantyTrendsQuery);
            $stmt->execute([$supplierID]);
            $warrantyData = $stmt->fetchAll();
            
            $chartData = [
                'type' => 'line',
                'data' => [
                    'labels' => array_map(function($row) {
                        return date('M j', strtotime($row['week_start']));
                    }, $warrantyData),
                    'datasets' => [
                        [
                            'label' => 'New Claims',
                            'data' => array_map(function($row) {
                                return (int)$row['total_claims'];
                            }, $warrantyData),
                            'borderColor' => 'rgb(245, 158, 11)',
                            'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                            'fill' => true,
                            'tension' => 0.4
                        ],
                        [
                            'label' => 'Approved',
                            'data' => array_map(function($row) {
                                return (int)$row['approved_claims'];
                            }, $warrantyData),
                            'borderColor' => 'rgb(16, 185, 129)',
                            'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                            'fill' => false,
                            'tension' => 0.4
                        ],
                        [
                            'label' => 'Rejected',
                            'data' => array_map(function($row) {
                                return (int)$row['rejected_claims'];
                            }, $warrantyData),
                            'borderColor' => 'rgb(239, 68, 68)',
                            'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                            'fill' => false,
                            'tension' => 0.4
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => [
                                'precision' => 0
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ]
                    ]
                ]
            ];
            break;
            
        case 'revenue_analysis':
            // Revenue analysis by month (last 12 months)
            // Using exact schema: transfer_items.qty_sent_total, transfer_items.cost
            $transfersCondition = getNotDeletedCondition('transfers', 't');
            $revenueAnalysisQuery = "
                SELECT 
                    DATE_FORMAT(t.created_at, '%Y-%m') as revenue_month,
                    DATE_FORMAT(t.created_at, '%M %Y') as month_label,
                    COUNT(DISTINCT t.id) as order_count,
                    SUM(ti.qty_sent_total) as items_shipped,
                    SUM(ti.qty_sent_total * COALESCE(ti.cost, 0)) as total_revenue,
                    AVG(ti.qty_sent_total * COALESCE(ti.cost, 0)) as avg_order_value
                FROM transfers t
                LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                WHERE t.supplier_id = ?
                AND t.transfer_category = 'PURCHASE_ORDER'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND t.state IN ('RECEIVED', 'CLOSED')
                AND {$transfersCondition}
                GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
                ORDER BY revenue_month ASC
            ";
            
            $stmt = $pdo->prepare($revenueAnalysisQuery);
            $stmt->execute([$supplierID]);
            $revenueData = $stmt->fetchAll();
            
            $chartData = [
                'type' => 'bar',
                'data' => [
                    'labels' => array_map(function($row) {
                        return $row['month_label'];
                    }, $revenueData),
                    'datasets' => [
                        [
                            'label' => 'Revenue ($)',
                            'data' => array_map(function($row) {
                                return round((float)$row['total_revenue'], 2);
                            }, $revenueData),
                            'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                            'borderColor' => 'rgb(16, 185, 129)',
                            'borderWidth' => 1,
                            'yAxisID' => 'y'
                        ],
                        [
                            'label' => 'Order Count',
                            'data' => array_map(function($row) {
                                return (int)$row['order_count'];
                            }, $revenueData),
                            'type' => 'line',
                            'borderColor' => 'rgb(79, 70, 229)',
                            'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                            'fill' => false,
                            'tension' => 0.4,
                            'yAxisID' => 'y1'
                        ]
                    ]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => [
                        'y' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'left',
                            'beginAtZero' => true,
                            'ticks' => [
                                'callback' => 'function(value) { return "$" + value.toLocaleString(); }'
                            ]
                        ],
                        'y1' => [
                            'type' => 'linear',
                            'display' => true,
                            'position' => 'right',
                            'beginAtZero' => true,
                            'grid' => [
                                'drawOnChartArea' => false
                            ],
                            'ticks' => [
                                'precision' => 0
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ]
                    ]
                ]
            ];
            break;
            
        default:
            $response->error('Chart type not implemented', 400);
    }
    
    // Performance metrics
    $endTime = microtime(true);
    $queryTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
    
    // Success response with chart data
    $response->success($chartData, [
        'query_time_ms' => $queryTime,
        'supplier_id' => $supplierID,
        'chart_type' => $chartType,
        'timestamp' => date('Y-m-d H:i:s'),
        'cache_ttl' => 600 // 10 minutes
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard Charts API Error: " . $e->getMessage());
    $response->error('Internal server error occurred while generating chart data', 500);
}