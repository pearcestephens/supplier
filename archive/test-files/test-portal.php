<?php
// Portal functionality test
$supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';

echo "=== SUPPLIER PORTAL COMPREHENSIVE TEST ===\n\n";

// Database connection
$mysqli = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if ($mysqli->connect_error) {
    die("❌ Database connection failed: " . $mysqli->connect_error . "\n");
}
echo "✅ Database connected\n\n";

// Test 1: Include functions file
echo "[TEST 1] Loading functions-real.php...\n";
require_once 'includes/functions-real.php';
echo "✅ Functions loaded successfully\n\n";

// Test 2: Get supplier details
echo "[TEST 2] Fetching supplier details...\n";
$supplier = get_supplier($mysqli, $supplier_id);
if ($supplier) {
    echo "✅ Supplier found: " . $supplier['name'] . "\n";
    echo "   Email: " . ($supplier['email'] ?: 'N/A') . "\n";
    echo "   Contact: " . ($supplier['contact_name'] ?: 'N/A') . "\n\n";
} else {
    echo "❌ Supplier not found\n\n";
}

// Test 3: Create session
echo "[TEST 3] Creating session...\n";
$session_token = create_supplier_session($mysqli, $supplier_id, '127.0.0.1', 'Test Script');
if ($session_token) {
    echo "✅ Session created: " . substr($session_token, 0, 16) . "...\n\n";
} else {
    echo "❌ Session creation failed\n\n";
}

// Test 4: Get purchase orders
echo "[TEST 4] Fetching purchase orders...\n";
$pos = get_supplier_purchase_orders($mysqli, $supplier_id);
echo "✅ Found " . count($pos) . " purchase orders\n";
if (count($pos) > 0) {
    echo "   First PO: " . $pos[0]['public_id'] . " - " . $pos[0]['state'] . "\n\n";
} else {
    echo "   (No purchase orders found)\n\n";
}

// Test 5: Get warranty claims
echo "[TEST 5] Fetching warranty claims...\n";
$claims = get_supplier_warranty_claims($mysqli, $supplier_id);
echo "✅ Found " . count($claims) . " warranty claims\n";
if (count($claims) > 0) {
    echo "   First claim: Fault #" . $claims[0]['id'] . " - " . $claims[0]['product_name'] . "\n\n";
} else {
    echo "   (No warranty claims found)\n\n";
}

// Test 6: Get dashboard stats
echo "[TEST 6] Loading dashboard stats...\n";
$stats = get_dashboard_stats($mysqli, $supplier_id);
echo "✅ Dashboard stats:\n";
echo "   Active POs: " . $stats['active_pos'] . "\n";
echo "   Pending Claims: " . $stats['pending_claims'] . "\n";
echo "   Products: " . $stats['total_products'] . "\n";
echo "   Notifications: " . $stats['unread_notifications'] . "\n\n";

$mysqli->close();
echo "=== ALL TESTS COMPLETED ===\n";
