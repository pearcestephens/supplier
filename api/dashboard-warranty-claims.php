<?php
/**
 * Dashboard Warranty Claims Chart API
 * Returns monthly warranty claim trends for last 6 months
 *
 * Chart Data:
 * - Month-by-month claim counts
 * - Breakdown by status (approved, rejected, pending)
 * - Approval rate trends
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

    // Get monthly warranty claims for last 6 months
        $stmt = $pdo->prepare("
            SELECT
                DATE_FORMAT(fp.time_created, '%Y-%m') as month,
                DATE_FORMAT(fp.time_created, '%b %Y') as month_label,
                COUNT(*) as total_claims,
                SUM(CASE WHEN fp.supplier_status = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN fp.supplier_status = 2 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN fp.supplier_status = 0 THEN 1 ELSE 0 END) as pending,
                ROUND((SUM(CASE WHEN fp.supplier_status = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as approval_rate
            FROM faulty_products fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.time_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(fp.time_created, '%Y-%m')
            ORDER BY month ASC
        ");

    $stmt->execute([$supplierID]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for Chart.js
    $labels = [];
    $totalClaims = [];
    $approved = [];
    $rejected = [];
    $pending = [];
    $approvalRates = [];

    foreach ($monthlyData as $row) {
        $labels[] = $row['month_label'];
        $totalClaims[] = (int)$row['total_claims'];
        $approved[] = (int)$row['approved'];
        $rejected[] = (int)$row['rejected'];
        $pending[] = (int)$row['pending'];
        $approvalRates[] = (float)$row['approval_rate'];
    }

    // Calculate totals and overall approval rate
    $grandTotal = array_sum($totalClaims);
    $grandApproved = array_sum($approved);
    $grandRejected = array_sum($rejected);
    $grandPending = array_sum($pending);

    $overallApprovalRate = $grandTotal > 0
        ? round(($grandApproved / $grandTotal) * 100, 1)
        : 0;

    // Get most common claim reasons
    $stmt = $pdo->prepare("
            SELECT
                fp.fault_desc as reason,
                COUNT(*) as count,
                ROUND((COUNT(*) / (
                    SELECT COUNT(*)
                    FROM faulty_products fp2
                    LEFT JOIN vend_products p2 ON fp2.product_id = p2.id
                    WHERE p2.supplier_id = ?
                    AND fp2.time_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                )) * 100, 1) as percentage
            FROM faulty_products fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.time_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND fp.fault_desc IS NOT NULL
            AND fp.fault_desc != ''
            GROUP BY fp.fault_desc
            ORDER BY count DESC
            LIMIT 5
        ");

    $stmt->execute([$supplierID, $supplierID]);
    $topReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get comparison to previous 6 months
    $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as previous_total,
                SUM(CASE WHEN fp.supplier_status = 1 THEN 1 ELSE 0 END) as previous_approved
            FROM faulty_products fp
            LEFT JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.time_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND fp.time_created < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");

    $stmt->execute([$supplierID]);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC);

    $previousTotal = (int)($previous['previous_total'] ?? 0);
    $previousApproved = (int)($previous['previous_approved'] ?? 0);

    $claimsChange = $previousTotal > 0
        ? round((($grandTotal - $previousTotal) / $previousTotal) * 100, 1)
        : 0;

    $previousApprovalRate = $previousTotal > 0
        ? round(($previousApproved / $previousTotal) * 100, 1)
        : 0;

    $approvalRateChange = $previousApprovalRate > 0
        ? round($overallApprovalRate - $previousApprovalRate, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'chart_data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Approved',
                    'data' => $approved,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Rejected',
                    'data' => $rejected,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Pending',
                    'data' => $pending,
                    'backgroundColor' => 'rgba(255, 206, 86, 0.6)',
                    'borderColor' => 'rgba(255, 206, 86, 1)',
                    'borderWidth' => 2
                ]
            ]
        ],
        'approval_rate_chart' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Approval Rate (%)',
                    'data' => $approvalRates,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4
                ]
            ]
        ],
        'summary' => [
            'total_claims' => $grandTotal,
            'approved' => $grandApproved,
            'rejected' => $grandRejected,
            'pending' => $grandPending,
            'overall_approval_rate' => $overallApprovalRate,
            'claims_change' => $claimsChange,
            'approval_rate_change' => $approvalRateChange
        ],
        'top_reasons' => $topReasons,
        'raw_data' => $monthlyData,
        'period' => 'Last 6 months',
        'last_updated' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('Dashboard Warranty Claims API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
