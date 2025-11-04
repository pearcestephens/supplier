<?php
/**
 * Dashboard Stock Alerts API
 * Smart inventory alerts based on 6-month sales velocity
 *
 * Calculates threshold per product per store based on:
 * - Average daily sales over past 6 months
 * - Lead time estimate (14 days default - 2 weeks buffer)
 * - Only alerts on actively selling products
 *
 * @package SupplierPortal
 * @version 2.0.0
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

    $pdo = pdo();

    // Detect inventory quantity column(s) for vend_inventory table
    // Build a safe SQL expression using only columns that actually exist
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
        // Fallback to zero if we cannot detect a quantity column (prevents SQL errors)
        $qtyExpr = '0';
    } else {
        // Build COALESCE(vi.`col1`, vi.`col2`, ... , 0)
        $parts = array_map(static function ($col) { return "vi.`{$col}`"; }, $presentQtyCols);
        $qtyExpr = 'COALESCE(' . implode(', ', $parts) . ', 0)';
    }

    // Get stores with low stock alerts based on sales velocity
    // Algorithm: Show stores where ANY product current_stock < (avg_daily_sales * 14 days)
    // Only look at products with actual sales history (actively selling products)
    $sqlStores = "
        SELECT
            o.id as outlet_id,
            o.name as outlet_name,
            COUNT(DISTINCT p.id) as products_below_threshold,
            SUM(CASE
                WHEN ($qtyExpr) = 0 THEN 1
                ELSE 0
            END) as out_of_stock,
            SUM(CASE
                WHEN ($qtyExpr) > 0
                AND ($qtyExpr) < (
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                    ), 0) / 180 * 14
                )
                THEN 1
                ELSE 0
            END) as low_stock,
            MIN(
                CASE
                    WHEN COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                    ), 0) / 180 > 0
                    THEN ROUND(($qtyExpr) / (
                        (SELECT SUM(sli.quantity)
                         FROM vend_sales_line_items sli
                         JOIN vend_sales s ON sli.sale_id = s.id
                         WHERE sli.product_id = p.id
                         AND s.outlet_id = o.id
                         AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND s.deleted_at IS NULL
                        ) / 180
                    ))
                    ELSE 999
                END
            ) as days_until_stockout

        FROM vend_products p
        CROSS JOIN vend_outlets o
        LEFT JOIN vend_inventory vi ON vi.product_id = p.id AND vi.outlet_id = o.id
        WHERE p.supplier_id = ?
        AND p.deleted_at IS NULL
        AND o.deleted_at IS NULL
        AND p.active = 1
        AND EXISTS (
            SELECT 1
            FROM vend_sales_line_items sli
            JOIN vend_sales s ON sli.sale_id = s.id
            WHERE sli.product_id = p.id
            AND s.outlet_id = o.id
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND s.deleted_at IS NULL
            HAVING SUM(sli.quantity) > 0
        )

        GROUP BY o.id, o.name
        HAVING products_below_threshold > 0
        ORDER BY out_of_stock DESC, days_until_stockout ASC, products_below_threshold DESC
        LIMIT 6
    ";
    $stmt = $pdo->prepare($sqlStores);

    $stmt->execute([$supplierID]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format severity (if not already set by demo data)
    foreach ($stores as &$store) {
        if (empty($store['severity'])) {
            $outOfStock = (int)$store['out_of_stock'];
            $daysLeft = (int)$store['days_until_stockout'];

            if ($outOfStock > 10 || $daysLeft <= 3) {
                $store['severity'] = 'critical';
            } elseif ($outOfStock > 5 || $daysLeft <= 7) {
                $store['severity'] = 'high';
            } else {
                $store['severity'] = 'medium';
            }
        }
    }

    // Get top 4 most critical product alerts
    $sqlAlerts = "
        SELECT
            p.name as product_name,
            o.name as outlet,
            ($qtyExpr) as current_stock,
            ROUND(
                COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 * 14
            ) as recommended_min,
            CASE
                WHEN ($qtyExpr) = 0 THEN 'out of stock'
                WHEN COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 > 0
                THEN CONCAT(
                    ROUND(({$qtyExpr}) / (
                        (SELECT SUM(sli.quantity)
                         FROM vend_sales_line_items sli
                         JOIN vend_sales s ON sli.sale_id = s.id
                         WHERE sli.product_id = p.id
                         AND s.outlet_id = o.id
                         AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND s.deleted_at IS NULL
                        ) / 180
                    )), ' days left'
                )
                ELSE 'low stock'
            END as message,
            CASE
                WHEN ({$qtyExpr}) = 0 THEN 'critical'
                WHEN ({$qtyExpr}) <
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                    ), 0) / 180 * 3
                THEN 'critical'
                WHEN ({$qtyExpr}) <
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                    ), 0) / 180 * 7
                THEN 'low'
                ELSE 'warning'
            END as severity,
            CASE
                WHEN COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                ), 0) / 180 > 0
                THEN ({$qtyExpr}) / (
                    (SELECT SUM(sli.quantity)
                     FROM vend_sales_line_items sli
                     JOIN vend_sales s ON sli.sale_id = s.id
                     WHERE sli.product_id = p.id
                     AND s.outlet_id = o.id
                     AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     AND s.deleted_at IS NULL
                    ) / 180
                )
                ELSE 999
            END as days_left_sort

        FROM vend_products p
        CROSS JOIN vend_outlets o
        LEFT JOIN vend_inventory vi ON vi.product_id = p.id AND vi.outlet_id = o.id
        WHERE p.supplier_id = ?
        AND p.deleted_at IS NULL
        AND o.deleted_at IS NULL
        AND p.active = 1

        AND (
            -- Include products with sales history that are below velocity threshold
            (
                EXISTS (
                    SELECT 1
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                )
                AND ({$qtyExpr}) < (
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                    ), 0) / 180 * 14
                )
            )
            -- OR include any product with <= 10 units (absolute low stock)
            OR ($qtyExpr) <= 10
        )

        ORDER BY days_left_sort ASC
        LIMIT 4
    ";
    $stmt = $pdo->prepare($sqlAlerts);

    $stmt->execute([$supplierID]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total stores count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM vend_outlets o
        WHERE o.deleted_at IS NULL
    ");
    $stmt->execute();
    $totalStores = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'stores' => $stores,
        'alerts' => $alerts,
        'total_stores' => $totalStores,
        'last_updated' => date('Y-m-d H:i:s'),
        'algorithm' => 'Sales velocity (6mo avg) * 14 days buffer'
    ]);

} catch (Exception $e) {
    error_log('Dashboard Stock Alerts API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
