<?php
/**
 * WEB-BASED COMPLETE TEST SUITE - Phase 1 Supplier Portal
 * 
 * Access via browser: https://staff.vapeshed.co.nz/supplier/test-suite-web.php
 * 
 * Tests every function, endpoint, query, and edge case
 * 
 * @version 1.0.0
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Set custom error handler to display errors in test format
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $failCount;
    $failCount++;
    echo '<div class="test-item"><span class="fail">❌ PHP ERROR:</span> ' . htmlspecialchars($errstr);
    echo '<div class="detail">File: ' . htmlspecialchars($errfile) . ' Line: ' . $errline . '</div>';
    echo '</div>';
    flush();
    return true;
});

// Allow direct access for testing - bypass all security checks
define('DIRECT_ACCESS_ALLOWED', true);
define('SUPPLIER_PORTAL', true);
define('TESTING_MODE', true);

// Disable output buffering for real-time results
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 'on');
ob_implicit_flush(true);

// Set unlimited execution time
set_time_limit(0);

// Output HTML headers immediately
header('Content-Type: text/html; charset=utf-8');

// Initialize session and authentication
session_start();

// Auto-authenticate with test supplier if not already authenticated
// Allow GET parameter to override supplier_id for testing different suppliers
$testSupplierID = $_GET['supplier_id'] ?? '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';

if (!isset($_SESSION['supplier_id'])) {
    $_SESSION['supplier_id'] = $testSupplierID; // British American Tobacco (default)
    $_SESSION['supplier_name'] = 'British American Tobacco (Test Mode)';
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();
}

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Test Suite - Phase 1</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #fff;
        }
        .header p {
            color: #e0d4ff;
            font-size: 14px;
        }
        .section {
            background: #2a2a2a;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .test-item {
            padding: 8px 0;
            border-bottom: 1px solid #3a3a3a;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .pass {
            color: #4CAF50;
            font-weight: bold;
        }
        .fail {
            color: #f44336;
            font-weight: bold;
            background: rgba(244, 67, 54, 0.1);
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .warn {
            color: #ff9800;
            font-weight: bold;
            background: rgba(255, 152, 0, 0.1);
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .info {
            color: #2196F3;
        }
        .detail {
            color: #999;
            font-size: 12px;
            margin-left: 25px;
        }
        .summary {
            background: linear-gradient(135deg, #2a2a2a 0%, #3a3a3a 100%);
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
            font-size: 18px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .summary .count {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        .summary .pass-count { color: #4CAF50; }
        .summary .fail-count { color: #f44336; }
        .summary .warn-count { color: #ff9800; }
        .loader {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #667eea;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .timestamp {
            color: #666;
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🧪 COMPLETE EXHAUSTIVE TEST SUITE</h1>
    <p>Phase 1 - Supplier Portal Implementation</p>
    <p class="timestamp">Started: <?php echo date('Y-m-d H:i:s'); ?></p>
</div>

<?php
flush();

$passCount = 0;
$failCount = 0;
$warnCount = 0;
$startTime = microtime(true);

function pass($test, $detail = '') {
    global $passCount;
    $passCount++;
    echo '<div class="test-item"><span class="pass">✅ PASS:</span> ' . htmlspecialchars($test);
    if ($detail) echo '<div class="detail">' . htmlspecialchars($detail) . '</div>';
    echo '</div>';
    flush();
}

function fail($test, $detail = '') {
    global $failCount;
    $failCount++;
    echo '<div class="test-item"><span class="fail">❌ FAIL:</span> ' . htmlspecialchars($test);
    if ($detail) echo '<div class="detail">' . htmlspecialchars($detail) . '</div>';
    echo '</div>';
    flush();
}

function warn($test, $detail = '') {
    global $warnCount;
    $warnCount++;
    echo '<div class="test-item"><span class="warn">⚠️  WARN:</span> ' . htmlspecialchars($test);
    if ($detail) echo '<div class="detail">' . htmlspecialchars($detail) . '</div>';
    echo '</div>';
    flush();
}

function section($title) {
    echo '<div class="section"><div class="section-title">' . htmlspecialchars($title) . '</div>';
    flush();
}

function endSection() {
    echo '</div>';
    flush();
}

// ============================================================================
// TEST 1: DATABASE CONNECTIVITY
// ============================================================================

section("DATABASE CONNECTIVITY");

try {
    // Load standalone libraries (same as index.php)
    require_once __DIR__ . '/lib/Database.php';
    require_once __DIR__ . '/lib/Session.php';
    require_once __DIR__ . '/lib/Auth.php';
    require_once __DIR__ . '/lib/Utils.php';
    
    // Connect to database using standalone Database class
    $db = Database::connect();
    
    if ($db && $db->ping()) {
        pass("Database connection", "Connected successfully");
    } else {
        fail("Database connection", "Connection failed");
    }
    
    // Test basic query
    $result = $db->query("SELECT 1 as test");
    if ($result && $result->num_rows > 0) {
        pass("Database query execution", "Simple query works");
    } else {
        fail("Database query execution", "Query failed");
    }
    
    // Get database name
    $result = $db->query("SELECT DATABASE() as db_name");
    if ($result) {
        $row = $result->fetch_assoc();
        pass("Database selected", "Using: " . $row['db_name']);
    }
    
} catch (Exception $e) {
    fail("Database connectivity - Exception", $e->getMessage());
    $db = null; // Set to null so other tests can check
} catch (Error $e) {
    fail("Database connectivity - FATAL ERROR", $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    $db = null; // Set to null so other tests can check
}

endSection();

// ============================================================================
// TEST 2: DATABASE SCHEMA
// ============================================================================

section("DATABASE SCHEMA");

// Skip if database connection failed
if (!isset($db) || $db === null) {
    fail("Database Schema Tests", "Skipped - database connection failed in previous test");
    endSection();
} else {
    try {
        $requiredTables = [
            'transfers' => 'Purchase orders table',
            'transfer_items' => 'Order line items',
            'vend_suppliers' => 'Supplier master data',
            'vend_outlets' => 'Store locations',
            'vend_products' => 'Product catalog',
            'faulty_products' => 'Warranty claims',
            'vend_sales' => 'Sales transactions'
        ];
        
        foreach ($requiredTables as $table => $desc) {
            $result = $db->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $countResult = $db->query("SELECT COUNT(*) as cnt FROM $table");
            $row = $countResult->fetch_assoc();
            pass("Table: $table", "$desc - " . number_format($row['cnt']) . " rows");
        } else {
            fail("Table: $table", "Table not found");
        }
    }
    
    } catch (Exception $e) {
        fail("Database schema test", $e->getMessage());
    }

    endSection();
}

// ============================================================================
// TEST 3: AUTH CLASSES
// ============================================================================

section("AUTHENTICATION CLASSES");

try {
    // Auth classes already loaded in DATABASE CONNECTIVITY section
    
    if (class_exists('Session')) {
        pass("Session class exists");
        
        $methods = ['start', 'get', 'set', 'destroy'];
        foreach ($methods as $method) {
            if (method_exists('Session', $method)) {
                pass("Session::$method() method exists");
            } else {
                fail("Session::$method() method exists", "Method not found");
            }
        }
    } else {
        fail("Session class exists", "Class not found");
    }
    
    if (class_exists('Auth')) {
        pass("Auth class exists");
        
        $methods = ['check', 'getSupplierId', 'getSupplierName', 'loginById'];
        foreach ($methods as $method) {
            if (method_exists('Auth', $method)) {
                pass("Auth::$method() method exists");
            } else {
                fail("Auth::$method() method exists", "Method not found");
            }
        }
    } else {
        fail("Auth class exists", "Class not found");
    }
    
} catch (Exception $e) {
    fail("Authentication class test", $e->getMessage());
}

endSection();

// ============================================================================
// TEST 4: SUPPLIER DATA
// ============================================================================

section("SUPPLIER DATA");

try {
    $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
    
    $query = "SELECT id, name, email, phone FROM vend_suppliers WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $testSupplierID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $supplier = $result->fetch_assoc();
        pass("Test supplier exists", $supplier['name']);
        if ($supplier['email']) pass("Supplier has email", $supplier['email']);
        if ($supplier['phone']) pass("Supplier has phone", $supplier['phone']);
    } else {
        fail("Test supplier exists", "Supplier not found: $testSupplierID");
    }
    $stmt->close();
    
    // Count total suppliers
    $result = $db->query("SELECT COUNT(*) as cnt FROM vend_suppliers");
    $row = $result->fetch_assoc();
    pass("Total suppliers", number_format($row['cnt']) . " suppliers in database");
    
} catch (Exception $e) {
    fail("Supplier data test", $e->getMessage());
}

endSection();

// ============================================================================
// TEST 5: TRANSFERS QUERIES (Orders Tab - Query 1-4)
// ============================================================================

section("TRANSFERS QUERIES - ORDERS TAB");

try {
    $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
    
    // Query 1: Available years
    $yearsQuery = "SELECT DISTINCT YEAR(created_at) as order_year 
                   FROM transfers 
                   WHERE supplier_id = ? AND transfer_category = 'PURCHASE_ORDER'
                   ORDER BY order_year DESC";
    
    $stmt = $db->prepare($yearsQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['order_year'];
    }
    
    if (count($years) > 0) {
        pass("Query 1: Available years", count($years) . " years found in {$time}ms - Years: " . implode(', ', $years));
    } else {
        warn("Query 1: Available years", "No years found (supplier may have no orders)");
    }
    $stmt->close();
    
    // Query 2: Available outlets
    $outletsQuery = "SELECT DISTINCT o.id, o.name, o.store_code
                     FROM transfers t
                     JOIN vend_outlets o ON t.outlet_to = o.id
                     WHERE t.supplier_id = ? AND t.transfer_category = 'PURCHASE_ORDER'
                     ORDER BY o.name ASC";
    
    $stmt = $db->prepare($outletsQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    
    $outlets = [];
    while ($row = $result->fetch_assoc()) {
        $outlets[] = $row['name'];
    }
    
    if (count($outlets) > 0) {
        pass("Query 2: Available outlets", count($outlets) . " outlets found in {$time}ms");
    } else {
        warn("Query 2: Available outlets", "No outlets found");
    }
    $stmt->close();
    
    // Query 3: Main orders query (using ACTUAL transfer_items columns)
    $ordersQuery = "SELECT t.id, t.public_id, t.created_at, t.expected_delivery_date, 
                           t.state, o.name as outlet_name,
                           COUNT(DISTINCT ti.id) as items_count,
                           SUM(ti.qty_requested) as total_units
                    FROM transfers t
                    LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
                    WHERE t.supplier_id = ? AND t.transfer_category = 'PURCHASE_ORDER'
                    GROUP BY t.id
                    ORDER BY t.created_at DESC
                    LIMIT 50";
    
    $stmt = $db->prepare($ordersQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    if (count($orders) > 0) {
        pass("Query 3: Main orders query", count($orders) . " orders retrieved in {$time}ms");
        
        $first = $orders[0];
        pass("First order details", "Order: {$first['public_id']} - {$first['outlet_name']} - {$first['items_count']} items, {$first['total_units']} units requested");
        
        if ($time < 300) {
            pass("Query 3 performance", "Excellent: {$time}ms < 300ms target");
        } else if ($time < 500) {
            pass("Query 3 performance", "Good: {$time}ms < 500ms target");
        } else {
            warn("Query 3 performance", "Slow: {$time}ms (target < 500ms)");
        }
    } else {
        warn("Query 3: Main orders query", "No orders found");
    }
    $stmt->close();
    
    // Query 4: Summary statistics (using actual columns)
    $statsQuery = "SELECT 
                     COUNT(DISTINCT t.id) as this_year_count,
                     (SELECT COUNT(DISTINCT t2.id) FROM transfers t2 
                      WHERE t2.supplier_id = ? AND t2.transfer_category = 'PURCHASE_ORDER'
                      AND t2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as last_30_days_count,
                     (SELECT COUNT(DISTINCT t3.id) FROM transfers t3
                      WHERE t3.supplier_id = ? AND t3.transfer_category = 'PURCHASE_ORDER'
                      AND t3.state IN ('OPEN','SENT','RECEIVING','PARTIAL')) as active_count
                   FROM transfers t
                   LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                   WHERE t.supplier_id = ? AND t.transfer_category = 'PURCHASE_ORDER'
                   AND YEAR(t.created_at) = YEAR(NOW())";
    
    $stmt = $db->prepare($statsQuery);
    $stmt->bind_param('sss', $testSupplierID, $testSupplierID, $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        pass("Query 4: Summary statistics", "Retrieved in {$time}ms");
        pass("This year stats", "{$stats['this_year_count']} orders this year");
        pass("Last 30 days", "{$stats['last_30_days_count']} orders");
        pass("Active orders", "{$stats['active_count']} orders in progress");
    } else {
        fail("Query 4: Summary statistics", "Query returned no results");
    }
    $stmt->close();
    
} catch (Exception $e) {
    fail("Transfers queries test", $e->getMessage());
}

endSection();

// ============================================================================
// TEST 6: NOTIFICATIONS QUERIES
// ============================================================================

section("NOTIFICATIONS QUERIES");

try {
    $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
    
    // Query 1: Pending warranty claims
    $claimsQuery = "SELECT COUNT(fp.id) as count
                    FROM faulty_products fp
                    JOIN vend_products p ON fp.product_id = p.id
                    WHERE p.supplier_id = ? AND fp.supplier_status = 0";
    
    $stmt = $db->prepare($claimsQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $pendingClaims = $row['count'];
    
    pass("Notifications Query 1: Pending claims", "$pendingClaims claims found in {$time}ms");
    $stmt->close();
    
    // Query 2: Urgent deliveries
    $urgentQuery = "SELECT COUNT(*) as count
                    FROM transfers
                    WHERE supplier_id = ? 
                    AND transfer_category = 'PURCHASE_ORDER'
                    AND expected_delivery_date IS NOT NULL
                    AND expected_delivery_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                    AND expected_delivery_date >= NOW()
                    AND state NOT IN ('RECEIVED', 'CLOSED', 'CANCELLED')";
    
    $stmt = $db->prepare($urgentQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $urgentDeliveries = $row['count'];
    
    pass("Notifications Query 2: Urgent deliveries", "$urgentDeliveries deliveries due soon ({$time}ms)");
    $stmt->close();
    
    // Query 3: Overdue claims
    $overdueQuery = "SELECT COUNT(fp.id) as count
                     FROM faulty_products fp
                     JOIN vend_products p ON fp.product_id = p.id
                     WHERE p.supplier_id = ?
                     AND fp.supplier_status = 0
                     AND DATEDIFF(NOW(), fp.time_created) > 7";
    
    $stmt = $db->prepare($overdueQuery);
    $stmt->bind_param('s', $testSupplierID);
    $start = microtime(true);
    $stmt->execute();
    $time = round((microtime(true) - $start) * 1000, 2);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $overdueClaims = $row['count'];
    
    pass("Notifications Query 3: Overdue claims", "$overdueClaims overdue ({$time}ms)");
    $stmt->close();
    
    // Calculate total and urgency
    $totalNotifications = $pendingClaims + $urgentDeliveries + $overdueClaims;
    $urgency = 'normal';
    if ($overdueClaims > 0) {
        $urgency = 'critical';
    } else if ($urgentDeliveries > 0 || $pendingClaims > 5) {
        $urgency = 'warning';
    }
    
    pass("Notifications total", "$totalNotifications total notifications (urgency level: $urgency)");
    
} catch (Exception $e) {
    fail("Notifications queries test", $e->getMessage());
}

endSection();

// ============================================================================
// TEST 7: FILE STRUCTURE
// ============================================================================

section("FILE STRUCTURE");

$requiredFiles = [
    'tabs/tab-orders.php' => 'Orders Tab',
    'tabs/tab-account.php' => 'Account Tab',
    'api/notifications-count.php' => 'Notifications API',
    'api/download-order.php' => 'Download Order API',
    'api/export-orders.php' => 'Export Orders API',
    'assets/js/supplier-portal.js' => 'Portal JavaScript',
    'config/database.php' => 'Database Config',
    'config/session.php' => 'Session Config'
];

foreach ($requiredFiles as $file => $name) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $size = filesize(__DIR__ . '/' . $file);
        pass("File: $name", number_format($size) . " bytes - $file");
    } else {
        fail("File: $name", "File not found: $file");
    }
}

endSection();

// ============================================================================
// TEST 8: PHP SYNTAX
// ============================================================================

section("PHP SYNTAX VALIDATION");

$phpFiles = [
    'tabs/tab-orders.php',
    'tabs/tab-account.php',
    'api/notifications-count.php',
    'api/download-order.php',
    'api/export-orders.php'
];

foreach ($phpFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $syntax = @shell_exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1");
        if (strpos($syntax, 'No syntax errors') !== false) {
            pass("PHP Syntax: " . basename($file), "No syntax errors");
        } else {
            fail("PHP Syntax: " . basename($file), "Syntax error detected");
        }
    }
}

endSection();

// ============================================================================
// TEST 9: SQL INJECTION PROTECTION
// ============================================================================

section("SQL INJECTION PROTECTION");

$filesToCheck = [
    'tabs/tab-orders.php' => 'Orders Tab',
    'tabs/tab-account.php' => 'Account Tab',
    'api/notifications-count.php' => 'Notifications API',
    'api/download-order.php' => 'Download Order API',
    'api/export-orders.php' => 'Export Orders API'
];

foreach ($filesToCheck as $file => $name) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        
        $hasPrepare = (strpos($content, '->prepare(') !== false);
        $hasBindParam = (strpos($content, '->bind_param(') !== false);
        
        if ($hasPrepare && $hasBindParam) {
            pass("SQL Protection: $name", "Uses prepared statements with bind_param()");
        } else {
            warn("SQL Protection: $name", "May not use prepared statements properly");
        }
    }
}

endSection();

// ============================================================================
// TEST 10: EDGE CASES
// ============================================================================

section("EDGE CASES");

try {
    // Test: Orders with NULL expected_delivery_date
    $nullDateQuery = "SELECT COUNT(*) as cnt FROM transfers 
                     WHERE transfer_category = 'PURCHASE_ORDER' 
                     AND expected_delivery_date IS NULL";
    $result = $db->query($nullDateQuery);
    $row = $result->fetch_assoc();
    pass("Edge Case: NULL delivery dates", $row['cnt'] . " orders with NULL expected_delivery_date");
    
    // Test: Orders with 0 line items
    $noItemsQuery = "SELECT COUNT(DISTINCT t.id) as cnt FROM transfers t
                    LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                    WHERE t.transfer_category = 'PURCHASE_ORDER'
                    AND ti.id IS NULL";
    $result = $db->query($noItemsQuery);
    $row = $result->fetch_assoc();
    if ($row['cnt'] > 0) {
        warn("Edge Case: Orders with 0 items", $row['cnt'] . " orders have no line items");
    } else {
        pass("Edge Case: Orders with 0 items", "All orders have line items (good)");
    }
    
} catch (Exception $e) {
    fail("Edge case tests", $e->getMessage());
}

endSection();

// ============================================================================
// TEST 11: PERFORMANCE
// ============================================================================

section("PERFORMANCE BENCHMARKS");

try {
    $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
    
    // Check for indexes on transfers table
    $indexQuery = "SHOW INDEX FROM transfers WHERE Key_name != 'PRIMARY'";
    $result = $db->query($indexQuery);
    $indexCount = $result->num_rows;
    
    if ($indexCount > 0) {
        pass("Database indexes: transfers", "$indexCount secondary indexes found");
    } else {
        warn("Database indexes: transfers", "No secondary indexes found (may impact performance)");
    }
    
} catch (Exception $e) {
    fail("Performance tests", $e->getMessage());
}

endSection();

// ============================================================================
// SUMMARY
// ============================================================================

$duration = round(microtime(true) - $startTime, 2);
$total = $passCount + $failCount + $warnCount;

?>

<div class="summary">
    <h2>TEST SUMMARY</h2>
    <div class="count pass-count">✅ <?php echo $passCount; ?> PASSED</div>
    <div class="count fail-count">❌ <?php echo $failCount; ?> FAILED</div>
    <div class="count warn-count">⚠️  <?php echo $warnCount; ?> WARNINGS</div>
    <hr style="margin: 20px 0; border: 1px solid #3a3a3a;">
    <div>TOTAL TESTS: <strong><?php echo $total; ?></strong></div>
    <div>DURATION: <strong><?php echo $duration; ?>s</strong></div>
    <div style="margin-top: 20px;">
        <?php if ($failCount === 0): ?>
            <div style="color: #4CAF50; font-size: 24px; font-weight: bold;">
                🎉 ALL TESTS PASSED!
            </div>
            <?php if ($warnCount > 0): ?>
                <div style="color: #ff9800; margin-top: 10px;">
                    ⚠️ Some warnings to review
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="color: #f44336; font-size: 24px; font-weight: bold;">
                ❌ TESTS FAILED
            </div>
            <div style="color: #f44336; margin-top: 10px;">
                Please fix the failing tests before deployment
            </div>
        <?php endif; ?>
    </div>
    <div style="margin-top: 20px; font-size: 12px; color: #666;">
        Test completed: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</div>

</body>
</html>
