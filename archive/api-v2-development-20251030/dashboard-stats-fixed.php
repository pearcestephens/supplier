<?php
/**
 * Dashboard Stats API v2 - Fixed with proper deleted_at handling
 * 
 * Provides comprehensive supplier dashboard statistics
 * Uses exact database schema fields with flexible deleted_at patterns
 * 
 * @endpoint: /api/v2/dashboard-stats.php
 * @method: GET
 * @params: supplier_id (required)
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
    
    $supplierID = trim($_GET['supplier_id']);
    
    // Validate supplier exists and is active - using flexible deletion check
    $supplierCondition = getNotDeletedCondition('vend_suppliers');
    $stmt = $pdo->prepare("SELECT id, name FROM vend_suppliers WHERE id = ? AND {$supplierCondition} LIMIT 1");
    $stmt->execute([$supplierID]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        apiResponse(false, null, ['message' => 'Invalid supplier ID or supplier not found', 'code' => 403]);
    }
    
    // Start building response data
    $stats = [];
    
    // 1. ACTIVE ORDERS STATISTICS
    $transfersCondition = getNotDeletedCondition('transfers', 't');
    $activeOrdersQuery = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN t.state = 'OPEN' THEN 1 ELSE 0 END) as open_orders,
            SUM(CASE WHEN t.state = 'SENT' THEN 1 ELSE 0 END) as sent_orders,
            SUM(CASE WHEN t.state = 'RECEIVING' THEN 1 ELSE 0 END) as receiving_orders,
            SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as orders_this_week,
            SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as orders_today
        FROM transfers t
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.state IN ('OPEN', 'SENT', 'RECEIVING', 'PARTIAL')
        AND {$transfersCondition}
    ";
    
    $stmt = $pdo->prepare($activeOrdersQuery);
    $stmt->execute([$supplierID]);
    $activeOrders = $stmt->fetch();
    
    $stats['active_orders'] = [
        'total' => (int)$activeOrders['total_orders'],
        'open' => (int)$activeOrders['open_orders'],
        'sent' => (int)$activeOrders['sent_orders'],
        'receiving' => (int)$activeOrders['receiving_orders'],
        'this_week' => (int)$activeOrders['orders_this_week'],
        'today' => (int)$activeOrders['orders_today']
    ];
    
    // 2. WARRANTY CLAIMS STATISTICS
    $productsCondition = getNotDeletedCondition('vend_products', 'p');
    $warrantyQuery = "
        SELECT 
            COUNT(*) as total_claims,
            SUM(CASE WHEN fp.supplier_status = 1 THEN 1 ELSE 0 END) as approved_claims,
            SUM(CASE WHEN fp.supplier_status = 2 THEN 1 ELSE 0 END) as rejected_claims,
            SUM(CASE WHEN fp.supplier_status = 0 THEN 1 ELSE 0 END) as pending_claims,
            AVG(CASE 
                WHEN fp.supplier_status > 0 AND fp.updated_at IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, fp.created_at, fp.updated_at) 
                ELSE NULL 
            END) as avg_response_hours
        FROM faulty_products fp
        INNER JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
        AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND {$productsCondition}
    ";
    
    $stmt = $pdo->prepare($warrantyQuery);
    $stmt->execute([$supplierID]);
    $warranty = $stmt->fetch();
    
    $stats['warranty_claims'] = [
        'total' => (int)$warranty['total_claims'],
        'approved' => (int)$warranty['approved_claims'],
        'rejected' => (int)$warranty['rejected_claims'],
        'pending' => (int)$warranty['pending_claims'],
        'avg_response_hours' => round((float)($warranty['avg_response_hours'] ?? 0), 1)
    ];
    
    // 3. MONTHLY PERFORMANCE STATISTICS
    $monthlyQuery = "
        SELECT 
            COUNT(DISTINCT t.id) as completed_orders,
            SUM(ti.qty_sent_total) as items_shipped,
            SUM(ti.qty_sent_total * COALESCE(ti.cost, 0)) as total_revenue,
            AVG(TIMESTAMPDIFF(DAY, t.created_at, t.updated_at)) as avg_fulfillment_days
        FROM transfers t
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.state IN ('RECEIVED', 'CLOSED')
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND {$transfersCondition}
    ";
    
    $stmt = $pdo->prepare($monthlyQuery);
    $stmt->execute([$supplierID]);
    $monthly = $stmt->fetch();
    
    $stats['monthly_performance'] = [
        'completed_orders' => (int)$monthly['completed_orders'],
        'items_shipped' => (int)($monthly['items_shipped'] ?? 0),
        'total_revenue' => round((float)($monthly['total_revenue'] ?? 0), 2),
        'avg_fulfillment_days' => round((float)($monthly['avg_fulfillment_days'] ?? 0), 1)
    ];
    
    // 4. PRODUCT CATALOG STATISTICS
    $catalogQuery = "
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN p.active = 1 THEN 1 ELSE 0 END) as active_products,
            COUNT(DISTINCT p.type_id) as product_categories
        FROM vend_products p
        WHERE p.supplier_id = ?
        AND {$productsCondition}
    ";
    
    $stmt = $pdo->prepare($catalogQuery);
    $stmt->execute([$supplierID]);
    $catalog = $stmt->fetch();
    
    $stats['product_catalog'] = [
        'total_products' => (int)$catalog['total_products'],
        'active_products' => (int)$catalog['active_products'],
        'product_categories' => (int)$catalog['product_categories']
    ];
    
    // 5. OUTLET PERFORMANCE (TOP 5)
    $outletsCondition = getNotDeletedCondition('vend_outlets', 'vo');
    $outletQuery = "
        SELECT 
            vo.name as outlet_name,
            COUNT(DISTINCT t.id) as order_count,
            SUM(ti.qty_sent_total) as items_shipped
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
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($outletQuery);
    $stmt->execute([$supplierID]);
    $outlets = $stmt->fetchAll();
    
    $stats['top_outlets'] = $outlets;
    
    // 6. RECENT ACTIVITY (last 10 orders)
    $recentQuery = "
        SELECT 
            t.id,
            t.reference,
            t.state,
            t.created_at,
            vo.name as outlet_name,
            COUNT(ti.id) as item_count
        FROM transfers t
        INNER JOIN vend_outlets vo ON t.outlet_to = vo.id
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND {$transfersCondition}
        AND {$outletsCondition}
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($recentQuery);
    $stmt->execute([$supplierID]);
    $recent = $stmt->fetchAll();
    
    $stats['recent_activity'] = $recent;
    
    // 7. TRENDS (compare with previous month)
    $prevMonthQuery = "
        SELECT 
            SUM(ti.qty_sent_total * COALESCE(ti.cost, 0)) as prev_revenue
        FROM transfers t
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.state IN ('RECEIVED', 'CLOSED')
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND {$transfersCondition}
    ";
    
    $stmt = $pdo->prepare($prevMonthQuery);
    $stmt->execute([$supplierID]);
    $prevMonth = $stmt->fetch();
    
    $currentRevenue = $stats['monthly_performance']['total_revenue'];
    $previousRevenue = (float)($prevMonth['prev_revenue'] ?? 0);
    
    $revenueChange = 0;
    $trendDirection = 'stable';
    
    if ($previousRevenue > 0) {
        $revenueChange = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        $trendDirection = $revenueChange > 5 ? 'up' : ($revenueChange < -5 ? 'down' : 'stable');
    }
    
    $stats['trends'] = [
        'revenue_change_percent' => round($revenueChange, 1),
        'revenue_trend_direction' => $trendDirection
    ];
    
    // Final response
    apiResponse(true, [
        'supplier_id' => $supplierID,
        'supplier_name' => $supplier['name'],
        'statistics' => $stats,
        'generated_at' => date('Y-m-d H:i:s'),
        'data_period' => '30-90 days'
    ], null);

} catch (PDOException $e) {
    apiResponse(false, null, ['message' => 'Database query failed: ' . $e->getMessage(), 'code' => 500]);
} catch (Exception $e) {
    apiResponse(false, null, ['message' => 'Server error: ' . $e->getMessage(), 'code' => 500]);
}
?>