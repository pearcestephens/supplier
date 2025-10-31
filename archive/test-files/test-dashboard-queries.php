<?php
/**
 * Test Dashboard Queries
 */

// Load libraries
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';

Session::start();
$db = Database::connect();

// Test supplier ID
$supplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';

echo "<h1>Dashboard Query Tests</h1>";
echo "<pre>";

// Test 1: Active Orders
echo "\n1. Testing Active Orders Query...\n";
try {
    $activeOrdersCount = Database::queryOne("
        SELECT COUNT(DISTINCT t.id) as count
        FROM transfers t
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND t.state IN ('OPEN', 'SENT', 'RECEIVING', 'PARTIAL')
        AND t.deleted_at IS NULL
    ", [$supplierID]);
    print_r($activeOrdersCount);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Pending Claims
echo "\n2. Testing Pending Claims Query...\n";
try {
    $pendingClaimsCount = Database::queryOne("
        SELECT COUNT(fp.id) as count
        FROM faulty_products fp
        INNER JOIN vend_products p ON fp.product_id = p.id
        WHERE p.supplier_id = ?
        AND fp.supplier_status = 0
        AND fp.status IN ('pending', 'open', 'new')
    ", [$supplierID]);
    print_r($pendingClaimsCount);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 3: 30-Day Stats
echo "\n3. Testing 30-Day Stats Query...\n";
try {
    $revenueStats = Database::queryOne("
        SELECT 
            COUNT(DISTINCT t.id) as order_count,
            COUNT(ti.id) as total_items
        FROM transfers t
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND t.state IN ('RECEIVED', 'CLOSED')
        AND t.deleted_at IS NULL
    ", [$supplierID]);
    print_r($revenueStats);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Recent Orders
echo "\n4. Testing Recent Orders Query...\n";
try {
    $recentOrders = Database::queryAll("
        SELECT 
            t.id,
            t.public_id as po_id,
            t.created_at as date,
            vo.name as outlet,
            t.state as status,
            COUNT(ti.id) as item_count
        FROM transfers t
        LEFT JOIN vend_outlets vo ON t.outlet_to = vo.id
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        WHERE t.supplier_id = ?
        AND t.transfer_category = 'PURCHASE_ORDER'
        AND t.deleted_at IS NULL
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$supplierID]);
    echo "Found " . count($recentOrders) . " orders\n";
    if (!empty($recentOrders)) {
        print_r($recentOrders[0]);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n</pre>";
echo "<p><strong>All tests completed!</strong></p>";
