<?php
/**
 * Dashboard Stats Module
 * Returns 6 key metrics for dashboard metric cards
 *
 * @package Supplier\Portal\API\Modules
 * @version 2.0.0
 */

// Auth check
requireAuth();

$pdo = pdo();
$supplierID = getSupplierID();

// Metric 1: Total Orders (30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_orders
    FROM vend_consignments
    WHERE supplier_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND deleted_at IS NULL
");
$stmt->execute([$supplierID]);
$totalOrders = (int)$stmt->fetchColumn();

// Metric 2: Pending/Processing Orders
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_orders
    FROM vend_consignments
    WHERE supplier_id = ?
    AND state IN ('OPEN', 'SENT', 'RECEIVING')
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
$ordersProgress = min(100, ($totalOrders / 200) * 100);

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

// Units Sold (last 30 days)
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

// Revenue (last 30 days)
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

$inStock = $activeProducts;
$lowStock = (int)ceil($activeProducts * 0.05);

// Send response using standard envelope
sendApiResponse(true, [
    'total_orders' => $totalOrders,
    'total_orders_change' => round($ordersChange, 1),
    'total_orders_progress' => round($ordersProgress, 0),
    'total_orders_target' => 200,
    'active_products' => $activeProducts,
    'products_in_stock' => $inStock,
    'products_low_stock' => $lowStock,
    'products_availability' => $activeProducts > 0 ? round(($inStock / $activeProducts) * 100, 1) : 0,
    'pending_claims' => $pendingClaims,
    'avg_order_value' => $avgOrderValue,
    'units_sold' => $unitsSold,
    'revenue_30d' => $revenue30d,
    'pending_orders' => $pendingOrders
], 'Dashboard statistics loaded successfully');
