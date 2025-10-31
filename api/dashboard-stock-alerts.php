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

    // Get stores with low stock alerts based on sales velocity
    // Algorithm: current_stock < (avg_daily_sales * 14 days)
    $stmt = $pdo->prepare("
        SELECT
            o.id as outlet_id,
            o.name as outlet_name,
            COUNT(DISTINCT p.id) as products_below_threshold,
            SUM(CASE
                WHEN COALESCE(vi.count, 0) = 0 THEN 1
                ELSE 0
            END) as out_of_stock,
            SUM(CASE
                WHEN COALESCE(vi.count, 0) > 0
                AND COALESCE(vi.count, 0) < (
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                        AND sli.deleted_at IS NULL
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
                        AND sli.deleted_at IS NULL
                    ), 0) / 180 > 0
                    THEN ROUND(COALESCE(vi.count, 0) / (
                        (SELECT SUM(sli.quantity)
                         FROM vend_sales_line_items sli
                         JOIN vend_sales s ON sli.sale_id = s.id
                         WHERE sli.product_id = p.id
                         AND s.outlet_id = o.id
                         AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND s.deleted_at IS NULL
                         AND sli.deleted_at IS NULL
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

        -- Only products with sales in last 6 months
        AND EXISTS (
            SELECT 1
            FROM vend_sales_line_items sli
            JOIN vend_sales s ON sli.sale_id = s.id
            WHERE sli.product_id = p.id
            AND s.outlet_id = o.id
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND s.deleted_at IS NULL
            AND sli.deleted_at IS NULL
        )

        GROUP BY o.id, o.name
        HAVING products_below_threshold > 0
        ORDER BY out_of_stock DESC, days_until_stockout ASC, products_below_threshold DESC
        LIMIT 6
    ");

    $stmt->execute([$supplierID]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format severity
    foreach ($stores as &$store) {
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

    // Get top 4 most critical product alerts
    $stmt = $pdo->prepare("
        SELECT
            p.name as product_name,
            o.name as outlet,
            COALESCE(vi.count, 0) as current_stock,
            ROUND(
                COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                    AND sli.deleted_at IS NULL
                ), 0) / 180 * 14
            ) as recommended_min,
            CASE
                WHEN COALESCE(vi.count, 0) = 0 THEN 'out of stock'
                WHEN COALESCE((
                    SELECT SUM(sli.quantity)
                    FROM vend_sales_line_items sli
                    JOIN vend_sales s ON sli.sale_id = s.id
                    WHERE sli.product_id = p.id
                    AND s.outlet_id = o.id
                    AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND s.deleted_at IS NULL
                    AND sli.deleted_at IS NULL
                ), 0) / 180 > 0
                THEN CONCAT(
                    ROUND(COALESCE(vi.count, 0) / (
                        (SELECT SUM(sli.quantity)
                         FROM vend_sales_line_items sli
                         JOIN vend_sales s ON sli.sale_id = s.id
                         WHERE sli.product_id = p.id
                         AND s.outlet_id = o.id
                         AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND s.deleted_at IS NULL
                         AND sli.deleted_at IS NULL
                        ) / 180
                    )), ' days left'
                )
                ELSE 'low stock'
            END as message,
            CASE
                WHEN COALESCE(vi.count, 0) = 0 THEN 'critical'
                WHEN COALESCE(vi.count, 0) <
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                        AND sli.deleted_at IS NULL
                    ), 0) / 180 * 3
                THEN 'critical'
                WHEN COALESCE(vi.count, 0) <
                    COALESCE((
                        SELECT SUM(sli.quantity)
                        FROM vend_sales_line_items sli
                        JOIN vend_sales s ON sli.sale_id = s.id
                        WHERE sli.product_id = p.id
                        AND s.outlet_id = o.id
                        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        AND s.deleted_at IS NULL
                        AND sli.deleted_at IS NULL
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
                    AND sli.deleted_at IS NULL
                ), 0) / 180 > 0
                THEN COALESCE(vi.count, 0) / (
                    (SELECT SUM(sli.quantity)
                     FROM vend_sales_line_items sli
                     JOIN vend_sales s ON sli.sale_id = s.id
                     WHERE sli.product_id = p.id
                     AND s.outlet_id = o.id
                     AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     AND s.deleted_at IS NULL
                     AND sli.deleted_at IS NULL
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

        -- Only products with sales
        AND EXISTS (
            SELECT 1
            FROM vend_sales_line_items sli
            JOIN vend_sales s ON sli.sale_id = s.id
            WHERE sli.product_id = p.id
            AND s.outlet_id = o.id
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND s.deleted_at IS NULL
            AND sli.deleted_at IS NULL
        )

        -- Below threshold
        AND COALESCE(vi.count, 0) < (
            COALESCE((
                SELECT SUM(sli.quantity)
                FROM vend_sales_line_items sli
                JOIN vend_sales s ON sli.sale_id = s.id
                WHERE sli.product_id = p.id
                AND s.outlet_id = o.id
                AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND s.deleted_at IS NULL
                AND sli.deleted_at IS NULL
            ), 0) / 180 * 14
        )

        ORDER BY days_left_sort ASC
        LIMIT 4
    ");

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
