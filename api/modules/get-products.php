<?php
/**
 * API: Get Products with Stock Information
 *
 * Returns all products for supplier with store-level stock data
 */

declare(strict_types=1);

$supplierId = Auth::getSupplierId();
if (!$supplierId) {
    sendApiResponse(false, null, 'Authentication required', ['code' => 'AUTH_REQUIRED'], 401);
}

try {
    // Get all products for this supplier
    $stmt = $db->prepare("
        SELECT DISTINCT
            p.id,
            p.name,
            p.sku,
            p.description,
            p.image_url,
            p.brand,
            p.supply_price,
            p.retail_price,
            p.variant_option_one_value as variant1,
            p.variant_option_two_value as variant2,
            p.variant_option_three_value as variant3
        FROM vend_products p
        WHERE p.supplier_id = ?
        AND p.deleted_at IS NULL
        ORDER BY p.name ASC
    ");
    $stmt->bind_param('s', $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'description' => $row['description'],
            'image_url' => $row['image_url'],
            'brand' => $row['brand'],
            'supply_price' => (float)$row['supply_price'],
            'retail_price' => (float)$row['retail_price'],
            'variant1' => $row['variant1'],
            'variant2' => $row['variant2'],
            'variant3' => $row['variant3'],
            'total_stock' => 0,
            'store_count' => 0,
            'store_stocks' => [],
            'reorder_point' => 10
        ];
    }

    if (empty($products)) {
        sendApiResponse(true, [
            'products' => [],
            'total' => 0
        ]);
    }

    $productIds = array_keys($products);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // Get inventory data across all stores
    $stmt = $db->prepare("
        SELECT
            i.product_id,
            i.outlet_id,
            o.name as outlet_name,
            COALESCE(i.inventory_count, 0) as count,
            COALESCE(i.reorder_point, 10) as reorder_point
        FROM vend_inventory i
        LEFT JOIN vend_outlets o ON o.id = i.outlet_id
        WHERE i.product_id IN ($placeholders)
        AND o.deleted_at IS NULL
    ");

    $types = str_repeat('s', count($productIds));
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $inventoryResult = $stmt->get_result();

    while ($inv = $inventoryResult->fetch_assoc()) {
        $productId = $inv['product_id'];
        if (isset($products[$productId])) {
            $count = (int)$inv['count'];
            $products[$productId]['total_stock'] += $count;
            $products[$productId]['store_count']++;
            $products[$productId]['store_stocks'][] = [
                'outlet_id' => $inv['outlet_id'],
                'outlet_name' => $inv['outlet_name'],
                'count' => $count,
                'reorder_point' => (int)$inv['reorder_point']
            ];

            // Use highest reorder point across stores
            if ((int)$inv['reorder_point'] > $products[$productId]['reorder_point']) {
                $products[$productId]['reorder_point'] = (int)$inv['reorder_point'];
            }
        }
    }

    sendApiResponse(true, [
        'products' => array_values($products),
        'total' => count($products),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('Get products error: ' . $e->getMessage());
    sendApiResponse(false, null, 'Failed to retrieve products', [
        'code' => 'QUERY_ERROR',
        'details' => $e->getMessage()
    ], 500);
}
