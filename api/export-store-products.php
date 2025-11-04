<?php
/**
 * Export Store Products to CSV
 * Simple 3-column export: Product Name, SKU, Qty In Stock
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

try {
    $supplierID = getSupplierID();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found');
    }

    $outletId = $_GET['outlet_id'] ?? null;
    if (!$outletId) {
        throw new Exception('Outlet ID required');
    }

    $pdo = pdo();

    // Get outlet name
    $outletStmt = $pdo->prepare("SELECT name FROM vend_outlets WHERE id = ?");
    $outletStmt->execute([$outletId]);
    $outletName = $outletStmt->fetchColumn() ?: "Store_{$outletId}";

    // Detect inventory quantity column(s)
    $qtyCandidates = [
        'count', 'on_hand', 'onhand', 'quantity', 'qty', 'stock',
        'inventory_count', 'stock_on_hand', 'qty_on_hand', 'quantity_on_hand',
        'available', 'available_qty', 'available_quantity',
        'in_stock', 'in_stock_qty', 'current_stock', 'current_qty'
    ];

    $colsStmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vend_inventory'"
    );
    $colsStmt->execute();
    $existingCols = array_map(static function ($r) { return $r['COLUMN_NAME']; }, $colsStmt->fetchAll(PDO::FETCH_ASSOC));

    $presentQtyCols = [];
    foreach ($qtyCandidates as $cand) {
        if (in_array($cand, $existingCols, true)) {
            $presentQtyCols[] = $cand;
        }
    }

    if (empty($presentQtyCols)) {
        $qtyExpr = '0';
    } else {
        $parts = array_map(static function ($col) { return "vi.`{$col}`"; }, $presentQtyCols);
        $qtyExpr = 'COALESCE(' . implode(', ', $parts) . ', 0)';
    }

    // Get low stock products for this store
    $sql = "
        SELECT
            p.name as product_name,
            p.sku,
            ($qtyExpr) as current_stock
        FROM vend_products p
        LEFT JOIN vend_inventory vi ON vi.product_id = p.id AND vi.outlet_id = ?
        WHERE p.supplier_id = ?
        AND p.deleted_at IS NULL
        AND p.active = 1
        AND EXISTS (
            SELECT 1
            FROM vend_sales_line_items sli
            JOIN vend_sales s ON sli.sale_id = s.id
            WHERE sli.product_id = p.id
            AND s.outlet_id = ?
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND s.deleted_at IS NULL
            HAVING SUM(sli.quantity) > 0
        )
        AND (
            ($qtyExpr) = 0
            OR ($qtyExpr) < (
                COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = ?
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 * 14
            )
        )
        ORDER BY current_stock ASC
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outletId, $supplierID, $outletId, $outletId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set CSV headers
    $filename = 'low_stock_' . preg_replace('/[^a-z0-9_-]/i', '_', $outletName) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write header row
    fputcsv($output, ['Product Name', 'SKU', 'Qty In Stock']);

    // Write data rows
    foreach ($products as $product) {
        fputcsv($output, [
            $product['product_name'] ?? '',
            $product['sku'] ?? '',
            $product['current_stock'] ?? 0
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log('Export Store Products Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
