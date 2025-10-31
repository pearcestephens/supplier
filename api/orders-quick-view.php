<?php
/**
 * Orders Quick View API
 * Returns detailed information for selected orders
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
    // Get order IDs from POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['order_ids']) || !is_array($input['order_ids'])) {
        throw new Exception('Invalid request: order_ids required');
    }

    $orderIds = array_map('intval', $input['order_ids']);

    if (empty($orderIds)) {
        throw new Exception('No orders selected');
    }

    $pdo = pdo();
    $supplierID = getSupplierID();

    // Build placeholders for IN clause
    $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';

    // Query for order details
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.public_id as po_number,
            c.state as status,
            c.total_cost as total_amount,
            c.created_at,
            c.expected_delivery_date as due_date,
            o.name as outlet_name,
            o.physical_address_1,
            o.physical_address_2,
            o.physical_suburb,
            o.physical_city,
            o.physical_postcode,
            COUNT(DISTINCT li.product_id) as items_count,
            SUM(li.quantity_sent) as units_count
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        LEFT JOIN vend_consignment_line_items li ON c.id = li.transfer_id AND li.deleted_at IS NULL
        WHERE c.id IN ($placeholders)
        AND c.supplier_id = ?
        AND c.deleted_at IS NULL
        GROUP BY c.id, c.public_id, c.state, c.total_cost, c.created_at, c.expected_delivery_date,
                 o.name, o.physical_address_1, o.physical_address_2, o.physical_suburb, o.physical_city, o.physical_postcode
        ORDER BY c.created_at DESC
    ");

    $params = array_merge($orderIds, [$supplierID]);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        throw new Exception('No orders found');
    }

    // Get line items for each order
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT
                li.product_id,
                li.name as product_name,
                li.sku,
                li.quantity_sent as quantity,
                li.unit_cost as price,
                (li.quantity_sent * li.unit_cost) as line_total
            FROM vend_consignment_line_items li
            WHERE li.transfer_id = ?
            AND li.deleted_at IS NULL
            ORDER BY li.name ASC
        ");

        $stmt->execute([$order['id']]);
        $order['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format address
        $addressParts = array_filter([
            $order['physical_address_1'],
            $order['physical_address_2'],
            $order['physical_suburb'],
            $order['physical_city'],
            $order['physical_postcode']
        ]);
        $order['full_address'] = implode(', ', $addressParts);
    }

    echo json_encode([
        'success' => true,
        'data' => $orders,
        'meta' => [
            'count' => count($orders),
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Orders Quick View API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
