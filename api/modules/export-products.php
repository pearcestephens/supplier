<?php
/**
 * API: Export Products to CSV
 *
 * Exports product list with stock data to downloadable CSV
 */

declare(strict_types=1);

$supplierId = Auth::getSupplierId();
if (!$supplierId) {
    sendApiResponse(false, null, 'Authentication required', ['code' => 'AUTH_REQUIRED'], 401);
}

$productIds = $_POST['product_ids'] ?? [];
if (empty($productIds) || !is_array($productIds)) {
    sendApiResponse(false, null, 'No products specified', ['code' => 'INVALID_INPUT'], 400);
}

try {
    // Sanitize product IDs
    $productIds = array_map('strval', $productIds);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // Get products with stock data
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.brand,
            p.supply_price,
            p.retail_price,
            SUM(COALESCE(i.inventory_count, 0)) as total_stock,
            COUNT(DISTINCT i.outlet_id) as store_count
        FROM vend_products p
        LEFT JOIN vend_inventory i ON i.product_id = p.id
        WHERE p.id IN ($placeholders)
        AND p.supplier_id = ?
        AND p.deleted_at IS NULL
        GROUP BY p.id
        ORDER BY p.name ASC
    ");

    $types = str_repeat('s', count($productIds)) . 's';
    $params = array_merge($productIds, [$supplierId]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Create CSV in memory
    $filename = 'products_export_' . date('Ymd_His') . '.csv';
    $filepath = '/tmp/' . $filename;
    $handle = fopen($filepath, 'w');

    // CSV Headers
    fputcsv($handle, [
        'Product ID',
        'Product Name',
        'SKU',
        'Brand',
        'Supply Price',
        'Retail Price',
        'Total Stock',
        'Stores Stocked',
        'Export Date'
    ]);

    // CSV Rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($handle, [
            $row['id'],
            $row['name'],
            $row['sku'] ?? '',
            $row['brand'] ?? '',
            '$' . number_format($row['supply_price'], 2),
            '$' . number_format($row['retail_price'], 2),
            $row['total_stock'],
            $row['store_count'],
            date('Y-m-d H:i:s')
        ]);
    }

    fclose($handle);

    // Move to downloads directory
    $downloadsDir = __DIR__ . '/../../downloads';
    if (!is_dir($downloadsDir)) {
        mkdir($downloadsDir, 0755, true);
    }

    $finalPath = $downloadsDir . '/' . $filename;
    rename($filepath, $finalPath);

    sendApiResponse(true, [
        'filename' => $filename,
        'download_url' => '/supplier/downloads/' . $filename,
        'record_count' => $result->num_rows
    ]);

} catch (Exception $e) {
    error_log('Export products error: ' . $e->getMessage());
    sendApiResponse(false, null, 'Export failed', [
        'code' => 'EXPORT_ERROR',
        'details' => $e->getMessage()
    ], 500);
}
