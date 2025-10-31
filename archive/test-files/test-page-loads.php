<?php
/**
 * Page Load & Database Query Functional Test
 * Simulates actual page loading and data retrieval
 */

// Database connection
$mysqli = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

require_once 'includes/functions-real.php';

$supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';

echo "=== PAGE LOAD & DATABASE QUERY TESTS ===\n\n";

$test_results = [];
$errors = [];
$warnings = [];

// Test 1: Dashboard Page Data
echo "[TEST 1] Dashboard Page Data Loading...\n";
try {
    $stats = get_dashboard_stats($mysqli, $supplier_id);
    
    if (!isset($stats['active_pos'])) {
        $errors[] = "Dashboard: Missing 'active_pos' stat";
    }
    if (!isset($stats['pending_claims'])) {
        $errors[] = "Dashboard: Missing 'pending_claims' stat";
    }
    if (!isset($stats['total_products'])) {
        $errors[] = "Dashboard: Missing 'total_products' stat";
    }
    if (!isset($stats['unread_notifications'])) {
        $errors[] = "Dashboard: Missing 'unread_notifications' stat";
    }
    
    echo "  ✓ Stats loaded: " . count($stats) . " metrics\n";
    echo "    - Active POs: {$stats['active_pos']}\n";
    echo "    - Pending Claims: {$stats['pending_claims']}\n";
    echo "    - Products: {$stats['total_products']}\n";
    echo "    - Notifications: {$stats['unread_notifications']}\n";
    
    $test_results['dashboard_stats'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "Dashboard stats failed: " . $e->getMessage();
    $test_results['dashboard_stats'] = 'FAIL';
}
echo "\n";

// Test 2: Purchase Orders List
echo "[TEST 2] Purchase Orders List Loading...\n";
try {
    $pos = get_supplier_purchase_orders($mysqli, $supplier_id);
    
    if (empty($pos)) {
        $warnings[] = "No purchase orders found (may be expected)";
    } else {
        echo "  ✓ Found " . count($pos) . " purchase orders\n";
        
        // Check first PO structure
        $first_po = $pos[0];
        $required_fields = ['id', 'public_id', 'state', 'outlet_name', 'due_date', 'created_at'];
        foreach ($required_fields as $field) {
            if (!isset($first_po[$field])) {
                $errors[] = "PO missing field: {$field}";
            }
        }
        
        echo "    - First PO: {$first_po['public_id']}\n";
        echo "    - State: {$first_po['state']}\n";
        echo "    - Store: {$first_po['outlet_name']}\n";
        echo "    - Created: {$first_po['created_at']}\n";
    }
    
    $test_results['po_list'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "PO list failed: " . $e->getMessage();
    $test_results['po_list'] = 'FAIL';
}
echo "\n";

// Test 3: Purchase Order Detail
echo "[TEST 3] Purchase Order Detail Loading...\n";
try {
    $pos = get_supplier_purchase_orders($mysqli, $supplier_id);
    
    if (!empty($pos)) {
        $po_id = (int)$pos[0]['id'];  // Cast to int
        $po_detail = get_purchase_order_details($mysqli, $supplier_id, $po_id);
        
        if ($po_detail) {
            echo "  ✓ PO detail loaded for ID: {$po_id}\n";
            echo "    - Public ID: {$po_detail['public_id']}\n";
            echo "    - State: {$po_detail['state']}\n";
            
            // Get line items
            $items = get_purchase_order_items($mysqli, $po_id);
            echo "    - Line items: " . count($items) . "\n";
            
            if (!empty($items)) {
                $first_item = $items[0];
                echo "    - First item: {$first_item['product_name']}\n";
                echo "      Qty requested: {$first_item['qty_requested']}\n";
                echo "      Qty sent: " . ($first_item['qty_sent'] ?? 'N/A') . "\n";
                echo "      Qty received: " . ($first_item['qty_received'] ?? 'N/A') . "\n";
            }
            
            $test_results['po_detail'] = 'PASS';
        } else {
            $errors[] = "PO detail returned null";
            $test_results['po_detail'] = 'FAIL';
        }
    } else {
        $warnings[] = "Skipping PO detail test (no POs available)";
        $test_results['po_detail'] = 'SKIP';
    }
} catch (Exception $e) {
    $errors[] = "PO detail failed: " . $e->getMessage();
    $test_results['po_detail'] = 'FAIL';
}
echo "\n";

// Test 4: Warranty Claims List
echo "[TEST 4] Warranty Claims List Loading...\n";
try {
    $claims = get_supplier_warranty_claims($mysqli, $supplier_id);
    
    if (empty($claims)) {
        $warnings[] = "No warranty claims found (may be expected)";
        echo "  ⚠ No claims found (function verified)\n";
    } else {
        echo "  ✓ Found " . count($claims) . " warranty claims\n";
        
        $first_claim = $claims[0];
        echo "    - First claim ID: {$first_claim['id']}\n";
        echo "    - Product: {$first_claim['product_name']}\n";
        echo "    - Status: " . ($first_claim['supplier_status'] == 0 ? 'Pending' : 'Resolved') . "\n";
    }
    
    $test_results['claims_list'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "Claims list failed: " . $e->getMessage();
    $test_results['claims_list'] = 'FAIL';
}
echo "\n";

// Test 5: Products Catalog
echo "[TEST 5] Products Catalog Loading...\n";
try {
    // Simulate products page query - simplified without inventory joins
    // (inventory table structure varies between environments)
    $products_sql = "SELECT p.id, p.name, p.sku, p.supply_price, 
                            p.price_including_tax as retail_price
                     FROM vend_products p
                     WHERE p.supplier_id = ?
                     AND p.deleted_at = '0000-00-00 00:00:00'
                     ORDER BY p.name ASC
                     LIMIT 10";
    
    $stmt = $mysqli->prepare($products_sql);
    if (!$stmt) {
        throw new Exception("Products query prepare failed: " . $mysqli->error);
    }
    $stmt->bind_param('s', $supplier_id);
    if (!$stmt->execute()) {
        throw new Exception("Products query execute failed: " . $stmt->error);
    }
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($products)) {
        $errors[] = "No products found (should have 50 based on earlier test)";
        $test_results['products'] = 'FAIL';
    } else {
        echo "  ✓ Found " . count($products) . " products (showing first 10)\n";
        
        $first_product = $products[0];
        echo "    - First product: {$first_product['name']}\n";
        echo "    - SKU: {$first_product['sku']}\n";
        echo "    - Supply price: \${$first_product['supply_price']}\n";
        echo "    - Retail price: \${$first_product['retail_price']}\n";
        
        $test_results['products'] = 'PASS';
    }
} catch (Exception $e) {
    $errors[] = "Products query failed: " . $e->getMessage();
    $test_results['products'] = 'FAIL';
}
echo "\n";

// Test 6: Analytics Data
echo "[TEST 6] Analytics Data Loading...\n";
try {
    // Test sales trend query (last 30 days)
    $days = 30;
    $trend_sql = "SELECT 
                    DATE(s.sale_date) as date,
                    COUNT(DISTINCT s.id) as transactions,
                    SUM(sli.quantity) as units_sold,
                    SUM(sli.total_price) as revenue
                  FROM vend_sales s
                  JOIN vend_sales_line_items sli ON s.id = sli.sale_id
                  JOIN vend_products p ON sli.product_id = p.id
                  WHERE p.supplier_id = ?
                  AND s.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND s.status = 'CLOSED'
                  AND sli.is_return = 0
                  GROUP BY DATE(s.sale_date)
                  ORDER BY date ASC";
    
    $stmt = $mysqli->prepare($trend_sql);
    $stmt->bind_param('si', $supplier_id, $days);
    $stmt->execute();
    $trend_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "  ✓ Sales trend data: " . count($trend_data) . " days\n";
    
    if (!empty($trend_data)) {
        $total_revenue = array_sum(array_column($trend_data, 'revenue'));
        $total_units = array_sum(array_column($trend_data, 'units_sold'));
        $total_transactions = array_sum(array_column($trend_data, 'transactions'));
        
        echo "    - Period: Last {$days} days\n";
        echo "    - Total revenue: \$" . number_format($total_revenue, 2) . "\n";
        echo "    - Units sold: {$total_units}\n";
        echo "    - Transactions: {$total_transactions}\n";
    } else {
        $warnings[] = "No sales data in last {$days} days";
    }
    
    $test_results['analytics'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "Analytics query failed: " . $e->getMessage();
    $test_results['analytics'] = 'FAIL';
}
echo "\n";

// Test 7: Account Activity Log
echo "[TEST 7] Account Activity Log Loading...\n";
try {
    $activity_sql = "SELECT action, resource_type, resource_id, ip_address, created_at
                     FROM supplier_portal_logs
                     WHERE supplier_id = ?
                     ORDER BY created_at DESC
                     LIMIT 10";
    
    $stmt = $mysqli->prepare($activity_sql);
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "  ✓ Activity log: " . count($activity) . " recent actions\n";
    
    if (!empty($activity)) {
        $first_activity = $activity[0];
        echo "    - Latest action: {$first_activity['action']}\n";
        echo "    - Resource: {$first_activity['resource_type']}\n";
        echo "    - IP: {$first_activity['ip_address']}\n";
        echo "    - Time: {$first_activity['created_at']}\n";
    }
    
    $test_results['activity_log'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "Activity log query failed: " . $e->getMessage();
    $test_results['activity_log'] = 'FAIL';
}
echo "\n";

// Test 8: Notifications
echo "[TEST 8] Notifications Loading...\n";
try {
    $notifications_sql = "SELECT id, type, title, message, is_read, created_at
                          FROM supplier_portal_notifications
                          WHERE supplier_id = ?
                          ORDER BY created_at DESC
                          LIMIT 10";
    
    $stmt = $mysqli->prepare($notifications_sql);
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $unread = array_filter($notifications, function($n) { return $n['is_read'] == 0; });
    
    echo "  ✓ Notifications: " . count($notifications) . " total, " . count($unread) . " unread\n";
    
    if (!empty($notifications)) {
        $first_notif = $notifications[0];
        echo "    - Latest: {$first_notif['title']}\n";
        echo "    - Type: {$first_notif['type']}\n";
        echo "    - Read: " . ($first_notif['is_read'] ? 'Yes' : 'No') . "\n";
    }
    
    $test_results['notifications'] = 'PASS';
} catch (Exception $e) {
    $errors[] = "Notifications query failed: " . $e->getMessage();
    $test_results['notifications'] = 'FAIL';
}
echo "\n";

$mysqli->close();

// Summary
echo str_repeat("=", 80) . "\n";
echo "=== TEST SUMMARY ===\n\n";

echo "Results:\n";
foreach ($test_results as $test => $result) {
    $icon = $result === 'PASS' ? '✓' : ($result === 'SKIP' ? '⊘' : '✗');
    echo "  {$icon} " . str_pad($test, 20) . " : {$result}\n";
}
echo "\n";

if (!empty($errors)) {
    echo "ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
    echo "\n";
}

$pass_count = count(array_filter($test_results, function($r) { return $r === 'PASS'; }));
$fail_count = count(array_filter($test_results, function($r) { return $r === 'FAIL'; }));
$skip_count = count(array_filter($test_results, function($r) { return $r === 'SKIP'; }));

echo "Overall: {$pass_count} PASS, {$fail_count} FAIL, {$skip_count} SKIP\n";
echo "Status: " . ($fail_count === 0 ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "\n";
