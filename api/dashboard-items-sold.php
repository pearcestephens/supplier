<?php
/**
 * Dashboard Items Sold Chart API
 * Returns monthly sales data for last 3 months
 *
 * Chart Data:
 * - Month-by-month aggregation
 * - Transactions count
 * - Units sold
 * - Revenue total
 * - Ready for Chart.js consumption
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

    $pdo = pdo();

    // Get monthly sales aggregation for last 3 months
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(s.sale_date, '%Y-%m') as month,
            DATE_FORMAT(s.sale_date, '%b %Y') as month_label,
            COUNT(DISTINCT s.id) as transactions,
            SUM(sli.quantity) as units_sold,
            SUM(sli.quantity * sli.price_total) as revenue,
            AVG(sli.quantity * sli.price_total) as avg_transaction_value
        FROM vend_sales s
        INNER JOIN vend_sales_line_items sli ON s.id = sli.sale_id
        INNER JOIN vend_products p ON sli.product_id = p.id
        WHERE p.supplier_id = ?
        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            AND (s.deleted_at IS NULL OR s.deleted_at = '0000-00-00 00:00:00')
            AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
        ORDER BY month ASC
    ");

    $stmt->execute([$supplierID]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for Chart.js
    $labels = [];
    $transactions = [];
    $unitsSold = [];
    $revenue = [];

    foreach ($monthlyData as $row) {
        $labels[] = $row['month_label'];
        $transactions[] = (int)$row['transactions'];
        $unitsSold[] = (int)$row['units_sold'];
        $revenue[] = round((float)$row['revenue'], 2);
    }

    // Calculate totals
    $totalTransactions = array_sum($transactions);
    $totalUnits = array_sum($unitsSold);
    $totalRevenue = array_sum($revenue);

    // Get comparison to previous 3 months
    $stmt = $pdo->prepare("
        SELECT
            SUM(sli.quantity) as previous_units,
            SUM(sli.quantity * sli.price_total) as previous_revenue
        FROM vend_sales s
        INNER JOIN vend_sales_line_items sli ON s.id = sli.sale_id
        INNER JOIN vend_products p ON sli.product_id = p.id
        WHERE p.supplier_id = ?
        AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND s.sale_date < DATE_SUB(NOW(), INTERVAL 3 MONTH)
            AND (s.deleted_at IS NULL OR s.deleted_at = '0000-00-00 00:00:00')
            AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
    ");

    $stmt->execute([$supplierID]);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC);

    $previousUnits = (int)($previous['previous_units'] ?? 0);
    $previousRevenue = (float)($previous['previous_revenue'] ?? 0);

    // Calculate percentage changes
    $unitsChange = $previousUnits > 0
        ? round((($totalUnits - $previousUnits) / $previousUnits) * 100, 1)
        : 0;

    $revenueChange = $previousRevenue > 0
        ? round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'chart_data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Units Sold',
                    'data' => $unitsSold,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Transactions',
                    'data' => $transactions,
                    'backgroundColor' => 'rgba(255, 206, 86, 0.5)',
                    'borderColor' => 'rgba(255, 206, 86, 1)',
                    'borderWidth' => 2
                ]
            ]
        ],
        'summary' => [
            'total_transactions' => $totalTransactions,
            'total_units' => $totalUnits,
            'total_revenue' => $totalRevenue,
            'avg_transaction_value' => $totalTransactions > 0
                ? round($totalRevenue / $totalTransactions, 2)
                : 0,
            'units_change' => $unitsChange,
            'revenue_change' => $revenueChange
        ],
        'raw_data' => $monthlyData,
        'period' => 'Last 3 months',
        'last_updated' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('Dashboard Items Sold API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
