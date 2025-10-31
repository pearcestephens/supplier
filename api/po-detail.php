<?php
/**
 * Purchase Order Detail API
 * Returns full order details including line items
 * 
 * @package CIS\Supplier\API
 * @version 2.0.0
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    requireAuth();
    $supplierId = getSupplierID();
    $orderId = $_GET['id'] ?? null;
    
    if (!$orderId) {
        sendJsonResponse(false, null, 'Order ID required', 400);
        exit;
    }
    
    $pdo = pdo();
    
    // Get order header
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.public_id,
            c.state as status,
            c.created_at,
            c.expected_delivery_date,
            c.tracking_number,
            c.total_cost as total_value,
            c.outlet_to,
            o.name as outlet_name,
            o.physical_address_1,
            o.physical_city,
            o.physical_postcode,
            o.physical_phone_number
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        WHERE c.id = ? 
          AND c.supplier_id = ? 
          AND c.deleted_at IS NULL
    ");
    $stmt->execute([$orderId, $supplierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        sendJsonResponse(false, null, 'Order not found or access denied', 404);
        exit;
    }
    
    // Get line items with product details
    $stmt = $pdo->prepare("
        SELECT 
            li.product_id,
            li.order_qty as quantity,
            li.qty_arrived,
            li.order_purchase_price as unit_cost,
            (li.qty_arrived * li.order_purchase_price) as line_total,
            p.name as product_name,
            p.sku,
            p.variant_name,
            p.image_url,
            p.active as product_active
        FROM purchase_order_line_items li
        LEFT JOIN vend_products p ON li.product_id = p.id
        WHERE li.purchase_order_id = ? AND li.deleted_at IS NULL
        ORDER BY p.name ASC
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add items to order
    $order['items'] = $items;
    $order['items_count'] = count($items);
    
    // Calculate totals
    $order['subtotal'] = array_sum(array_column($items, 'line_total'));
    $order['total_quantity'] = array_sum(array_column($items, 'quantity'));
    
    // Get status history (if table exists - optional)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                status_from,
                status_to,
                changed_at,
                changed_by,
                notes
            FROM consignment_status_history
            WHERE consignment_id = ?
            ORDER BY changed_at DESC
            LIMIT 10
        ");
        $stmt->execute([$orderId]);
        $order['status_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist - that's okay
        $order['status_history'] = [];
    }
    
    sendJsonResponse(true, $order, 'Order details retrieved successfully');
    
} catch (Exception $e) {
    error_log("PO Detail API Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Failed to load order details: ' . $e->getMessage(), 500);
}
