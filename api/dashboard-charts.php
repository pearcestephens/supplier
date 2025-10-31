<?php
/**
 * Dashboard Charts API
 * Returns data for Chart.js visualizations
 * 
 * TEST: curl https://staff.vapeshed.co.nz/supplier/api/dashboard-charts.php
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
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    // =======================================================================
    // =======================================================================
    // REAL DATA - Using actual purchase_order_line_items schema
    // =======================================================================
    
    // CHART 1: Items Sold (Last 3 Months) - REAL DATA
    $months = [];
    $unitsSold = [];
    
    for ($i = 2; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-{$i} months");
        $months[] = $date->format('F Y');
        
        $startDate = $date->format('Y-m-01');
        $endDate = $date->format('Y-m-t');
        
        // Get actual units from line items using qty_arrived
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(li.qty_arrived), 0) as units
            FROM purchase_order_line_items li
            INNER JOIN vend_consignments c ON li.purchase_order_id = c.id
            WHERE c.supplier_id = ?
            AND c.created_at >= ?
            AND c.created_at <= ?
            AND c.deleted_at IS NULL
            AND li.deleted_at IS NULL
        ");
        $stmt->execute([$supplierID, $startDate, $endDate . ' 23:59:59']);
        $unitsSold[] = (int)$stmt->fetchColumn();
    }
    
    // CHART 2: Warranty Claims (Last 6 Months)
    $warrantyMonths = [];
    $pending = [];
    $approved = [];
    $rejected = [];
    $resolved = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-{$i} months");
        $warrantyMonths[] = $date->format('M');
        
        $startDate = $date->format('Y-m-01');
        $endDate = $date->format('Y-m-t');
        
        // Pending
        $stmt = $pdo->prepare("
            SELECT COUNT(fp.id) 
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.supplier_status = 0
            AND fp.time_created >= ?
            AND fp.time_created <= ?
        ");
        $stmt->execute([$supplierID, $startDate, $endDate . ' 23:59:59']);
        $pending[] = (int)$stmt->fetchColumn();
        
        // Approved (supplier responded positively)
        $stmt = $pdo->prepare("
            SELECT COUNT(fp.id) 
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.supplier_status = 1
            AND fp.supplier_status_timestamp >= ?
            AND fp.supplier_status_timestamp <= ?
        ");
        $stmt->execute([$supplierID, $startDate, $endDate . ' 23:59:59']);
        $approved[] = (int)$stmt->fetchColumn();
        
        // Rejected (use status field)
        $stmt = $pdo->prepare("
            SELECT COUNT(fp.id) 
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.status = 'rejected'
            AND fp.time_created >= ?
            AND fp.time_created <= ?
        ");
        $stmt->execute([$supplierID, $startDate, $endDate . ' 23:59:59']);
        $rejected[] = (int)$stmt->fetchColumn();
        
        // Resolved
        $stmt = $pdo->prepare("
            SELECT COUNT(fp.id) 
            FROM faulty_products fp
            INNER JOIN vend_products p ON fp.product_id = p.id
            WHERE p.supplier_id = ?
            AND fp.status IN ('resolved', 'completed')
            AND fp.time_created >= ?
            AND fp.time_created <= ?
        ");
        $stmt->execute([$supplierID, $startDate, $endDate . ' 23:59:59']);
        $resolved[] = (int)$stmt->fetchColumn();
    }
    
    sendJsonResponse(true, [
        'items_sold' => [
            'labels' => $months,
            'data' => $unitsSold
        ],
        'warranty_claims' => [
            'labels' => $warrantyMonths,
            'datasets' => [
                ['label' => 'Pending', 'data' => $pending, 'color' => '#fbbf24'],
                ['label' => 'Approved', 'data' => $approved, 'color' => '#10b981'],
                ['label' => 'Rejected', 'data' => $rejected, 'color' => '#ef4444'],
                ['label' => 'Resolved', 'data' => $resolved, 'color' => '#6b7280']
            ]
        ]
    ], 'Chart data retrieved successfully', 200, [
        'supplier_id' => $supplierID,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard Charts API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error_type' => 'chart_data_error',
        'message' => $e->getMessage()
    ], 'Failed to load chart data', 500);
}
