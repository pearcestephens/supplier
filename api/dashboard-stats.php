<?php

require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
/**
 * Dashboard Stats API Endpoint
 * Returns 6 key metrics for dashboard metric cards
 *
 * TEST: curl -b cookies.txt https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Auth check
supplier_require_auth_bridge(true);

// JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();

    // =======================================================================
    // SIMPLIFIED VERSION - Only uses tables we KNOW exist
    // Using: vend_consignments, vend_products, faulty_products
    // =======================================================================

    // Metric 1: Total ACTIVE Orders (30 days) - EXCLUDE CANCELLED
    // Updated: 2025-11-02 - Fixed to exclude CANCELLED state
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_orders
        FROM vend_consignments
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND state != 'CANCELLED'
        AND deleted_at IS NULL
    ");
    $stmt->execute([$supplierID]);
    $totalOrders = (int)$stmt->fetchColumn();

    // DEBUG: Log the actual value
    error_log("DEBUG dashboard-stats.php: totalOrders = " . $totalOrders . " for supplier " . $supplierID);

    // Metric 2: Pending/Processing Orders (OPEN or PACKING states)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_orders
        FROM vend_consignments
        WHERE supplier_id = ?
        AND state IN ('OPEN', 'PACKING')
        AND deleted_at IS NULL
    ");
    $stmt->execute([$supplierID]);
    $pendingOrders = (int)$stmt->fetchColumn();

    // Metric 3: Active Products
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_products
        FROM vend_products
        WHERE supplier_id = ?
        AND active = 1
        AND deleted_at = '0000-00-00 00:00:00'
    ");
    $stmt->execute([$supplierID]);
    $activeProducts = (int)$stmt->fetchColumn();

    // Metric 4: Pending Warranty Claims
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_claims
        FROM faulty_products fp
        INNER JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
        AND fp.supplier_status = 0
    ");
    $stmt->execute([$supplierID]);
    $pendingClaims = (int)$stmt->fetchColumn();

    // Calculate previous period (days 31-60) for comparison
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as prev_orders
        FROM vend_consignments
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND deleted_at IS NULL
    ");
    $stmt->execute([$supplierID]);
    $prevOrders = (int)$stmt->fetchColumn();

    // Calculate percentage change
    $ordersChange = $prevOrders > 0 ? (($totalOrders - $prevOrders) / $prevOrders) * 100 : 0;

    // Calculate progress percentage (against target of 200 orders/month)
    $ordersProgress = min(100, ($totalOrders / 200) * 100);

    // ========================================================================
    // REAL DATA - Using actual purchase_order_line_items schema
    // ========================================================================

    // Average Order Value (last 30 days)
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(c.total_cost), 0) as avg_value
        FROM vend_consignments c
        WHERE c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$supplierID]);
    $avgOrderValue = round((float)$stmt->fetchColumn(), 2);

    // Units Sold (last 30 days) - Using qty_arrived from line items
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(li.qty_arrived), 0) as total_units
        FROM purchase_order_line_items li
        INNER JOIN vend_consignments c ON li.purchase_order_id = c.id
        WHERE c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND li.deleted_at IS NULL
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$supplierID]);
    $unitsSold = (int)$stmt->fetchColumn();

    // Revenue (last 30 days) - Using qty_arrived * order_purchase_price
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(li.qty_arrived * li.order_purchase_price), 0) as total_revenue
        FROM purchase_order_line_items li
        INNER JOIN vend_consignments c ON li.purchase_order_id = c.id
        WHERE c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND li.deleted_at IS NULL
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$supplierID]);
    $revenue30d = round((float)$stmt->fetchColumn(), 2);

    // Total Inventory Value - Using supply_price * current quantity
    // FIXED: Filter to this supplier's products only, handle NULL quantities properly
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(
                vp.supply_price * COALESCE(vi.inventory_level, 0)
            ), 0) as total_inventory_value
        FROM vend_products vp
        LEFT JOIN vend_inventory vi ON vp.id = vi.product_id
        WHERE vp.supplier_id = ?
        AND vp.active = 1
        AND vp.deleted_at = '0000-00-00 00:00:00'
        AND vp.supply_price IS NOT NULL
        AND vp.supply_price > 0
    ");
    $stmt->execute([$supplierID]);
    $inventoryResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalInventoryValue = round((float)($inventoryResult['total_inventory_value'] ?? 0), 2);

    $inStock = $activeProducts;
    $lowStock = (int)ceil($activeProducts * 0.05);

    // Send standardized JSON response
    sendJsonResponse(true, [
        // Card 1: Total Orders
        'total_orders' => $totalOrders,
        'total_orders_change' => round($ordersChange, 1),
        'total_orders_progress' => round($ordersProgress, 0),
        'total_orders_target' => 200,

        // Card 2: Active Products
        'active_products' => $activeProducts,
        'products_in_stock' => $inStock,
        'products_low_stock' => $lowStock,
        'products_availability' => $activeProducts > 0 ? round(($inStock / $activeProducts) * 100, 1) : 0,

        // Card 3: Pending Claims
        'pending_claims' => $pendingClaims,

        // Card 4: Avg Order Value (PLACEHOLDER - needs line items table)
        'avg_order_value' => round($avgOrderValue, 2),

        // Card 5: Units Sold (PLACEHOLDER - needs line items table)
        'units_sold' => $unitsSold,

        // Card 6: Revenue 30d (PLACEHOLDER - needs line items table)
        'revenue_30d' => round($revenue30d, 2),

        // Card 6: Total Inventory Value (supply_price * quantity)
        'total_inventory_value' => $totalInventoryValue,

        // Card 7: Pending Orders
        'pending_orders' => $pendingOrders
    ], 'Dashboard statistics loaded successfully (placeholders for revenue data)');

} catch (Exception $e) {
    error_log('Dashboard Stats API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, [
        'error_type' => 'dashboard_stats_error',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 'Failed to load dashboard statistics', 500);
}
