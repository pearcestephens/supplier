<?php
/**
 * API Module: dashboard-orders
 * Returns recent orders for dashboard display
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
$limit = isset($_POST['limit']) ? min(50, max(1, (int)$_POST['limit'])) : 10;

try {
    $pdo = pdo();

    // Query recent orders for this supplier
    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.public_id as po_number,
            t.vend_number,
            t.state as status,
            t.outlet_to,
            t.created_at,
            t.expected_delivery_date as due_date,
            o.name as outlet,
            COALESCE(SUM(ti.quantity * ti.unit_cost), 0) as total_amount,
            COUNT(DISTINCT ti.id) as items_count,
            COALESCE(SUM(ti.quantity), 0) as units_count
        FROM vend_consignments t
        LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
        LEFT JOIN vend_outlets o ON t.outlet_to = o.id
        WHERE t.supplier_id = :supplier_id
            AND t.transfer_category = 'PURCHASE_ORDER'
            AND t.deleted_at IS NULL
            AND t.state IN ('OPEN', 'PACKING', 'PACKED', 'SENT', 'RECEIVING')
        GROUP BY t.id, t.public_id, t.vend_number, t.state, t.outlet_to, t.created_at, t.expected_delivery_date, o.name
        ORDER BY t.created_at DESC
        LIMIT :limit
    ");

    $stmt->bindValue(':supplier_id', $supplierID, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($orders as &$order) {
        if ($order['created_at']) {
            $order['created_at'] = date('d M Y', strtotime($order['created_at']));
        }
        if ($order['due_date'] && $order['due_date'] !== '0000-00-00' && $order['due_date'] !== '0000-00-00 00:00:00') {
            $order['due_date'] = date('d M Y', strtotime($order['due_date']));
        } else {
            $order['due_date'] = null;
        }
    }

    sendApiResponse(true, [
        'orders' => $orders,
        'total' => count($orders)
    ], 'Orders loaded successfully');

} catch (Exception $e) {
    error_log("Dashboard orders error: " . $e->getMessage());
    sendApiResponse(false, null, 'Failed to load orders', [
        'code' => 'QUERY_ERROR',
        'message' => 'Could not retrieve orders',
        'details' => $e->getMessage()
    ], 500);
}
