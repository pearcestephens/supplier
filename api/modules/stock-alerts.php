<?php
/**
 * API Module: stock-alerts
 * Returns low stock alerts grouped by store
 */

declare(strict_types=1);

// Require authentication
if (!Auth::check()) {
    sendApiResponse(false, null, 'Unauthorized', [
        'code' => 'UNAUTHORIZED',
        'message' => 'You must be logged in to access this resource',
        'details' => 'Session expired or invalid'
    ], 401);
}

$supplierID = Auth::getSupplierId();

try {
    $pdo = pdo();

    // Query to get low stock products grouped by outlet
    $stmt = $pdo->prepare("
        SELECT
            o.id as outlet_id,
            o.name as outlet_name,
            COUNT(*) as total_alerts,
            SUM(CASE WHEN vi.inventory_count = 0 THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN vi.inventory_count > 0 AND vi.inventory_count <= COALESCE(p.reorder_point, 5) THEN 1 ELSE 0 END) as low_count
        FROM vend_inventory vi
        INNER JOIN vend_products p ON vi.product_id = p.id
        INNER JOIN vend_outlets o ON vi.outlet_id = o.id
        WHERE p.supplier_id = :supplier_id
            AND p.deleted_at IS NULL
            AND o.deleted_at IS NULL
            AND vi.deleted_at IS NULL
            AND (
                vi.inventory_count = 0
                OR vi.inventory_count <= COALESCE(p.reorder_point, 5)
            )
        GROUP BY o.id, o.name
        HAVING total_alerts > 0
        ORDER BY critical_count DESC, total_alerts DESC
    ");

    $stmt->execute(['supplier_id' => $supplierID]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalAlerts = 0;
    $storesWithAlerts = count($stores);

    foreach ($stores as $store) {
        $totalAlerts += (int)$store['total_alerts'];
    }

    sendApiResponse(true, [
        'stores' => $stores,
        'total_alerts' => $totalAlerts,
        'stores_with_alerts' => $storesWithAlerts
    ], 'Stock alerts loaded successfully');

} catch (Exception $e) {
    error_log("Stock alerts error: " . $e->getMessage());
    sendApiResponse(false, null, 'Failed to load stock alerts', [
        'code' => 'QUERY_ERROR',
        'message' => 'Could not retrieve stock alerts',
        'details' => $e->getMessage()
    ], 500);
}
