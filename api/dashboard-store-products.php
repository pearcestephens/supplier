<?php
/**
 * Dashboard Store Products API
 * Returns low-stock products for a specific store
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

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
            p.id as product_id,
            p.name as product_name,
            p.sku,
            ($qtyExpr) as current_stock,
            ROUND(
                COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = ?
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 * 14
            ) as recommended_min,
            CASE
                WHEN COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = ?
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 > 0
                THEN ROUND(($qtyExpr) / (
                    (SELECT SUM(sli.quantity)
                     FROM vend_sales_line_items sli
                     JOIN vend_sales s ON sli.sale_id = s.id
                     WHERE sli.product_id = p.id
                     AND s.outlet_id = ?
                     AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     AND s.deleted_at IS NULL
                    ) / 180
                ))
                ELSE 999
            END as days_left
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
        ORDER BY current_stock ASC, days_left ASC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outletId, $outletId, $outletId, $outletId, $supplierID, $outletId, $outletId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'outlet_id' => $outletId,
        'total' => count($products)
    ]);

} catch (Exception $e) {
    error_log('Dashboard Store Products API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
