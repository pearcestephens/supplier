<?php
/**
 * Dashboard Stock Alerts API
 * Returns stores with low stock warnings
 * 
 * TEST: curl https://staff.vapeshed.co.nz/supplier/api/dashboard-stock-alerts.php
 * 
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    // Get real low stock alerts per outlet
    $stmt = $pdo->prepare("
        SELECT 
            o.name as outlet_name,
            COUNT(CASE WHEN i.current_amount > 0 AND i.current_amount < i.reorder_point THEN 1 END) as low_stock,
            COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) as out_of_stock,
            CASE 
                WHEN COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) > 50 THEN 'critical'
                WHEN COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) > 20 THEN 'high'
                ELSE 'medium'
            END as severity
        FROM vend_inventory i
        INNER JOIN vend_products p ON i.product_id = p.id
        INNER JOIN vend_outlets o ON i.outlet_id = o.id
        WHERE p.supplier_id = ?
        AND p.active = 1
        AND p.deleted_at = '0000-00-00 00:00:00'
        AND i.deleted_at IS NULL
        AND (i.current_amount < i.reorder_point OR i.current_amount = 0)
        GROUP BY o.id, o.name
        HAVING (low_stock + out_of_stock) > 0
        ORDER BY out_of_stock DESC, low_stock DESC
        LIMIT 6
    ");
    $stmt->execute([$supplierID]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get critical alerts (top 4 most urgent)
    $stmt = $pdo->prepare("
        SELECT 
            o.name as outlet,
            COUNT(i.product_id) as count,
            CASE 
                WHEN COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) > 0 THEN 'critical'
                WHEN AVG(i.current_amount) < 5 THEN 'low'
                ELSE 'warning'
            END as severity,
            CASE 
                WHEN COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) > 0 THEN 'out of stock'
                WHEN AVG(i.current_amount) < 5 THEN 'critically low'
                ELSE 'low stock'
            END as message
        FROM vend_inventory i
        INNER JOIN vend_products p ON i.product_id = p.id
        INNER JOIN vend_outlets o ON i.outlet_id = o.id
        WHERE p.supplier_id = ?
        AND p.active = 1
        AND p.deleted_at = '0000-00-00 00:00:00'
        AND i.deleted_at IS NULL
        AND i.current_amount < i.reorder_point
        GROUP BY o.id, o.name
        HAVING count > 0
        ORDER BY 
            COUNT(CASE WHEN i.current_amount = 0 THEN 1 END) DESC,
            AVG(i.current_amount) ASC
        LIMIT 4
    ");
    $stmt->execute([$supplierID]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total stores count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM vend_outlets o
        INNER JOIN vend_inventory i ON o.id = i.outlet_id
        INNER JOIN vend_products p ON i.product_id = p.id
        WHERE p.supplier_id = ?
        AND i.deleted_at IS NULL
    ");
    $stmt->execute([$supplierID]);
    $totalStores = (int)$stmt->fetchColumn();
    
    sendJsonResponse(true, [
        'stores' => $stores,
        'alerts' => $alerts,
        'total_stores' => $totalStores,
        'last_updated' => date('Y-m-d H:i:s')
    ], 'Stock alerts retrieved successfully', 200, [
        'supplier_id' => $supplierID,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard Stock Alerts API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error_type' => 'stock_alerts_error',
        'message' => $e->getMessage()
    ], 'Failed to load stock alerts', 500);
}
