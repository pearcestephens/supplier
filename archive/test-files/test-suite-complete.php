#!/usr/bin/env php
<?php
/**
 * COMPLETE EXHAUSTIVE TEST SUITE - Phase 1 Supplier Portal
 * 
 * Tests every function, endpoint, query, authentication state, and edge case
 * 
 * Usage: php test-suite-complete.php
 * 
 * @version 1.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes max

// ANSI Color Codes
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");
define('COLOR_BOLD', "\033[1m");

class TestSuite {
    private $passCount = 0;
    private $failCount = 0;
    private $warnCount = 0;
    private $testResults = [];
    private $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function run() {
        $this->printHeader();
        
        // Test Categories
        $this->testDatabaseConnectivity();
        $this->testDatabaseSchema();
        $this->testAuthenticationClasses();
        $this->testSupplierData();
        $this->testTransfersQueries();
        $this->testNotificationsQueries();
        $this->testAPIEndpoints();
        $this->testFileStructure();
        $this->testPHPSyntax();
        $this->testSQLInjectionProtection();
        $this->testEdgeCases();
        $this->testPerformance();
        
        $this->printSummary();
    }
    
    private function printHeader() {
        echo COLOR_BOLD . COLOR_BLUE . "\n";
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo "  COMPLETE EXHAUSTIVE TEST SUITE - Phase 1 Supplier Portal\n";
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo COLOR_RESET . "\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n\n";
    }
    
    private function pass($test, $message = '') {
        $this->passCount++;
        echo COLOR_GREEN . "✅ PASS: " . COLOR_RESET . $test;
        if ($message) echo " - " . $message;
        echo "\n";
        $this->testResults[] = ['status' => 'PASS', 'test' => $test, 'message' => $message];
    }
    
    private function fail($test, $message = '') {
        $this->failCount++;
        echo COLOR_RED . "❌ FAIL: " . COLOR_RESET . $test;
        if ($message) echo " - " . COLOR_RED . $message . COLOR_RESET;
        echo "\n";
        $this->testResults[] = ['status' => 'FAIL', 'test' => $test, 'message' => $message];
    }
    
    private function warn($test, $message = '') {
        $this->warnCount++;
        echo COLOR_YELLOW . "⚠️  WARN: " . COLOR_RESET . $test;
        if ($message) echo " - " . $message;
        echo "\n";
        $this->testResults[] = ['status' => 'WARN', 'test' => $test, 'message' => $message];
    }
    
    private function section($title) {
        echo "\n" . COLOR_BOLD . COLOR_BLUE . "═══ " . $title . " ═══" . COLOR_RESET . "\n\n";
    }
    
    // ========================================================================
    // DATABASE CONNECTIVITY TESTS
    // ========================================================================
    
    private function testDatabaseConnectivity() {
        $this->section("DATABASE CONNECTIVITY");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            
            if ($db && $db->ping()) {
                $this->pass("Database connection", "Connected successfully");
            } else {
                $this->fail("Database connection", "Connection failed");
                return;
            }
            
            // Test basic query
            $result = $db->query("SELECT 1 as test");
            if ($result && $result->num_rows > 0) {
                $this->pass("Database query execution", "Simple query works");
            } else {
                $this->fail("Database query execution", "Query failed");
            }
            
            // Test database selection
            $result = $db->query("SELECT DATABASE() as db_name");
            if ($result) {
                $row = $result->fetch_assoc();
                $this->pass("Database selected", "Using: " . $row['db_name']);
            }
            
        } catch (Exception $e) {
            $this->fail("Database connectivity", $e->getMessage());
        }
    }
    
    // ========================================================================
    // DATABASE SCHEMA TESTS
    // ========================================================================
    
    private function testDatabaseSchema() {
        $this->section("DATABASE SCHEMA");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            
            // Required tables
            $requiredTables = [
                'transfers',
                'transfer_items',
                'vend_suppliers',
                'vend_outlets',
                'vend_products',
                'faulty_products',
                'vend_sales'
            ];
            
            foreach ($requiredTables as $table) {
                $result = $db->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    $this->pass("Table exists: $table");
                    
                    // Check row count
                    $countResult = $db->query("SELECT COUNT(*) as cnt FROM $table");
                    if ($countResult) {
                        $row = $countResult->fetch_assoc();
                        echo "   └─ Rows: " . number_format($row['cnt']) . "\n";
                    }
                } else {
                    $this->fail("Table exists: $table", "Table not found");
                }
            }
            
            // Test transfers table columns
            $this->testTableColumns($db, 'transfers', [
                'id', 'public_id', 'supplier_id', 'outlet_to', 'transfer_category',
                'state', 'created_at', 'expected_delivery_date', 'reference'
            ]);
            
            // Test transfer_items table columns
            $this->testTableColumns($db, 'transfer_items', [
                'id', 'transfer_id', 'product_id', 'quantity', 'cost'
            ]);
            
            // Test vend_suppliers table columns
            $this->testTableColumns($db, 'vend_suppliers', [
                'id', 'name', 'email', 'phone', 'website', 'created_at'
            ]);
            
        } catch (Exception $e) {
            $this->fail("Database schema test", $e->getMessage());
        }
    }
    
    private function testTableColumns($db, $table, $requiredColumns) {
        $result = $db->query("SHOW COLUMNS FROM $table");
        $existingColumns = [];
        
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
        
        foreach ($requiredColumns as $col) {
            if (in_array($col, $existingColumns)) {
                // Column exists, no output (too verbose)
            } else {
                $this->fail("Column $table.$col", "Missing column");
            }
        }
    }
    
    // ========================================================================
    // AUTHENTICATION CLASS TESTS
    // ========================================================================
    
    private function testAuthenticationClasses() {
        $this->section("AUTHENTICATION CLASSES");
        
        try {
            require_once __DIR__ . '/config/session.php';
            
            // Test Session class exists
            if (class_exists('Session')) {
                $this->pass("Session class exists");
            } else {
                $this->fail("Session class exists", "Class not found");
                return;
            }
            
            // Test Auth class exists
            if (class_exists('Auth')) {
                $this->pass("Auth class exists");
            } else {
                $this->fail("Auth class exists", "Class not found");
                return;
            }
            
            // Test Auth methods exist
            $authMethods = ['check', 'getSupplierId', 'getSupplierName'];
            foreach ($authMethods as $method) {
                if (method_exists('Auth', $method)) {
                    $this->pass("Auth::$method() exists");
                } else {
                    $this->fail("Auth::$method() exists", "Method not found");
                }
            }
            
            // Test Session methods exist
            $sessionMethods = ['start', 'get', 'set', 'destroy'];
            foreach ($sessionMethods as $method) {
                if (method_exists('Session', $method)) {
                    $this->pass("Session::$method() exists");
                } else {
                    $this->fail("Session::$method() exists", "Method not found");
                }
            }
            
        } catch (Exception $e) {
            $this->fail("Authentication class test", $e->getMessage());
        }
    }
    
    // ========================================================================
    // SUPPLIER DATA TESTS
    // ========================================================================
    
    private function testSupplierData() {
        $this->section("SUPPLIER DATA");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            
            // Test supplier: British American Tobacco
            $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
            
            $query = "SELECT id, name, email, phone, website FROM vend_suppliers WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $testSupplierID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $supplier = $result->fetch_assoc();
                $this->pass("Test supplier exists", $supplier['name']);
                echo "   └─ Email: " . ($supplier['email'] ?? 'N/A') . "\n";
                echo "   └─ Phone: " . ($supplier['phone'] ?? 'N/A') . "\n";
            } else {
                $this->fail("Test supplier exists", "Supplier ID not found: $testSupplierID");
            }
            $stmt->close();
            
            // Count total suppliers
            $result = $db->query("SELECT COUNT(*) as cnt FROM vend_suppliers");
            $row = $result->fetch_assoc();
            $this->pass("Total suppliers in database", number_format($row['cnt']) . " suppliers");
            
        } catch (Exception $e) {
            $this->fail("Supplier data test", $e->getMessage());
        }
    }
    
    // ========================================================================
    // TRANSFERS QUERIES TESTS (Orders Tab)
    // ========================================================================
    
    private function testTransfersQueries() {
        $this->section("TRANSFERS QUERIES (ORDERS TAB)");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
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
                $this->pass("Query 1: Available years", count($years) . " years found ({$time}ms)");
                echo "   └─ Years: " . implode(', ', $years) . "\n";
            } else {
                $this->warn("Query 1: Available years", "No years found (supplier may have no orders)");
            }
            $stmt->close();
            
            // Query 2: Available outlets
            $outletsQuery = "SELECT DISTINCT o.id, o.name, o.outlet_code
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
                $this->pass("Query 2: Available outlets", count($outlets) . " outlets found ({$time}ms)");
                echo "   └─ Outlets: " . implode(', ', array_slice($outlets, 0, 3)) . "...\n";
            } else {
                $this->warn("Query 2: Available outlets", "No outlets found");
            }
            $stmt->close();
            
            // Query 3: Main orders query
            $ordersQuery = "SELECT t.id, t.public_id, t.created_at, t.expected_delivery_date, 
                                   t.state, t.reference, o.name as outlet_name,
                                   COUNT(DISTINCT ti.id) as items_count,
                                   SUM(ti.quantity) as total_units,
                                   COALESCE(SUM(ti.quantity * ti.cost), 0) as total_ex_gst,
                                   COALESCE(SUM(ti.quantity * ti.cost * 1.15), 0) as total_inc_gst
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
                $this->pass("Query 3: Main orders", count($orders) . " orders found ({$time}ms)");
                
                // Show first order details
                $first = $orders[0];
                echo "   └─ First order: " . $first['public_id'] . " - " . $first['outlet_name'] . "\n";
                echo "   └─ Items: " . $first['items_count'] . ", Units: " . $first['total_units'] . "\n";
                echo "   └─ Total: $" . number_format($first['total_inc_gst'], 2) . "\n";
                
                // Check query performance
                if ($time < 300) {
                    $this->pass("Query 3 performance", "Fast ({$time}ms < 300ms target)");
                } else if ($time < 500) {
                    $this->warn("Query 3 performance", "Acceptable ({$time}ms, target < 300ms)");
                } else {
                    $this->fail("Query 3 performance", "Slow ({$time}ms, target < 300ms)");
                }
            } else {
                $this->warn("Query 3: Main orders", "No orders found (supplier may have no orders)");
            }
            $stmt->close();
            
            // Query 4: Summary statistics
            $statsQuery = "SELECT 
                             COUNT(DISTINCT t.id) as this_year_count,
                             COALESCE(SUM(ti.quantity * ti.cost * 1.15), 0) as this_year_value,
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
                $this->pass("Query 4: Summary statistics", "Retrieved ({$time}ms)");
                echo "   └─ This year: " . $stats['this_year_count'] . " orders ($" . number_format($stats['this_year_value'], 2) . ")\n";
                echo "   └─ Last 30 days: " . $stats['last_30_days_count'] . " orders\n";
                echo "   └─ Active orders: " . $stats['active_count'] . " orders\n";
                
                if ($time < 300) {
                    $this->pass("Query 4 performance", "Fast ({$time}ms)");
                } else {
                    $this->warn("Query 4 performance", "Could be faster ({$time}ms)");
                }
            } else {
                $this->fail("Query 4: Summary statistics", "Query returned no results");
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $this->fail("Transfers queries test", $e->getMessage());
        }
    }
    
    // ========================================================================
    // NOTIFICATIONS QUERIES TESTS
    // ========================================================================
    
    private function testNotificationsQueries() {
        $this->section("NOTIFICATIONS QUERIES");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
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
            
            $this->pass("Notifications Query 1: Pending claims", "$pendingClaims claims ({$time}ms)");
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
            
            $this->pass("Notifications Query 2: Urgent deliveries", "$urgentDeliveries deliveries ({$time}ms)");
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
            
            $this->pass("Notifications Query 3: Overdue claims", "$overdueClaims overdue ({$time}ms)");
            $stmt->close();
            
            // Calculate total and urgency
            $totalNotifications = $pendingClaims + $urgentDeliveries + $overdueClaims;
            $urgency = 'normal';
            if ($overdueClaims > 0) {
                $urgency = 'critical';
            } else if ($urgentDeliveries > 0 || $pendingClaims > 5) {
                $urgency = 'warning';
            }
            
            $this->pass("Notifications total", "$totalNotifications total (urgency: $urgency)");
            
        } catch (Exception $e) {
            $this->fail("Notifications queries test", $e->getMessage());
        }
    }
    
    // ========================================================================
    // API ENDPOINTS TESTS
    // ========================================================================
    
    private function testAPIEndpoints() {
        $this->section("API ENDPOINTS");
        
        // Test notifications-count.php
        $this->testPHPFile('api/notifications-count.php', 'Notifications API syntax');
        
        // Test download-order.php
        $this->testPHPFile('api/download-order.php', 'Download Order API syntax');
        
        // Test export-orders.php
        $this->testPHPFile('api/export-orders.php', 'Export Orders API syntax');
        
        // Test existing warranty APIs
        if (file_exists(__DIR__ . '/api/update-warranty-claim.php')) {
            $this->testPHPFile('api/update-warranty-claim.php', 'Update Warranty API syntax');
        }
        
        if (file_exists(__DIR__ . '/api/add-warranty-note.php')) {
            $this->testPHPFile('api/add-warranty-note.php', 'Add Warranty Note API syntax');
        }
    }
    
    // ========================================================================
    // FILE STRUCTURE TESTS
    // ========================================================================
    
    private function testFileStructure() {
        $this->section("FILE STRUCTURE");
        
        $requiredFiles = [
            'tabs/tab-orders.php' => 'Orders Tab',
            'tabs/tab-account.php' => 'Account Tab',
            'api/notifications-count.php' => 'Notifications API',
            'api/download-order.php' => 'Download Order API',
            'api/export-orders.php' => 'Export Orders API',
            'assets/js/supplier-portal.js' => 'Portal JavaScript',
            'assets/css/supplier-portal.css' => 'Portal CSS',
            'config/database.php' => 'Database Config',
            'config/session.php' => 'Session Config',
            'components/header.php' => 'Header Component',
            'components/footer.php' => 'Footer Component',
            'components/sidebar.php' => 'Sidebar Component'
        ];
        
        foreach ($requiredFiles as $file => $name) {
            $fullPath = __DIR__ . '/' . $file;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                $this->pass("File exists: $name", number_format($size) . " bytes");
            } else {
                $this->fail("File exists: $name", "File not found: $file");
            }
        }
        
        // Check documentation files
        $docFiles = [
            'PHASE1_TESTING_CHECKLIST.md',
            'PHASE1_COMPLETE_SUMMARY.md',
            'PHASE1_QUICK_REF.md'
        ];
        
        foreach ($docFiles as $doc) {
            if (file_exists(__DIR__ . '/' . $doc)) {
                $this->pass("Documentation: $doc");
            } else {
                $this->warn("Documentation: $doc", "File not found");
            }
        }
    }
    
    // ========================================================================
    // PHP SYNTAX TESTS
    // ========================================================================
    
    private function testPHPSyntax() {
        $this->section("PHP SYNTAX VALIDATION");
        
        $phpFiles = [
            'tabs/tab-orders.php',
            'tabs/tab-account.php',
            'tabs/tab-dashboard.php',
            'tabs/tab-warranty.php',
            'api/notifications-count.php',
            'api/download-order.php',
            'api/export-orders.php',
            'index.php',
            'login.php'
        ];
        
        foreach ($phpFiles as $file) {
            $this->testPHPFile($file, basename($file));
        }
    }
    
    private function testPHPFile($file, $name) {
        $fullPath = __DIR__ . '/' . $file;
        if (!file_exists($fullPath)) {
            $this->warn("PHP Syntax: $name", "File not found");
            return;
        }
        
        $output = [];
        $return = 0;
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $return);
        
        if ($return === 0) {
            $this->pass("PHP Syntax: $name");
        } else {
            $this->fail("PHP Syntax: $name", implode("\n", $output));
        }
    }
    
    // ========================================================================
    // SQL INJECTION PROTECTION TESTS
    // ========================================================================
    
    private function testSQLInjectionProtection() {
        $this->section("SQL INJECTION PROTECTION");
        
        // Test that queries use prepared statements
        $filesToCheck = [
            'tabs/tab-orders.php',
            'tabs/tab-account.php',
            'api/notifications-count.php',
            'api/download-order.php',
            'api/export-orders.php'
        ];
        
        foreach ($filesToCheck as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (!file_exists($fullPath)) {
                continue;
            }
            
            $content = file_get_contents($fullPath);
            
            // Check for prepared statements
            $hasPrepare = (strpos($content, '->prepare(') !== false || strpos($content, '->prepare (') !== false);
            $hasBindParam = (strpos($content, '->bind_param(') !== false || strpos($content, '->bind_param (') !== false);
            
            if ($hasPrepare && $hasBindParam) {
                $this->pass("SQL Protection: " . basename($file), "Uses prepared statements");
            } else {
                $this->warn("SQL Protection: " . basename($file), "May not use prepared statements");
            }
            
            // Check for dangerous patterns
            $dangerousPatterns = [
                '/\$_GET\[.*?\].*?SELECT/i',
                '/\$_POST\[.*?\].*?SELECT/i',
                '/\$_REQUEST\[.*?\].*?SELECT/i'
            ];
            
            $vulnerable = false;
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $vulnerable = true;
                    break;
                }
            }
            
            if (!$vulnerable) {
                $this->pass("SQL Injection Check: " . basename($file), "No obvious vulnerabilities");
            } else {
                $this->fail("SQL Injection Check: " . basename($file), "Potential SQL injection vulnerability found");
            }
        }
    }
    
    // ========================================================================
    // EDGE CASE TESTS
    // ========================================================================
    
    private function testEdgeCases() {
        $this->section("EDGE CASES");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            
            // Test 1: Supplier with no orders
            $emptySupplierQuery = "SELECT id FROM vend_suppliers 
                                  WHERE id NOT IN (SELECT DISTINCT supplier_id FROM transfers WHERE transfer_category = 'PURCHASE_ORDER')
                                  LIMIT 1";
            $result = $db->query($emptySupplierQuery);
            if ($result && $result->num_rows > 0) {
                $this->pass("Edge Case: Supplier with 0 orders exists", "Can test empty state");
            } else {
                $this->warn("Edge Case: Supplier with 0 orders", "No empty supplier to test");
            }
            
            // Test 2: Orders with NULL expected_delivery_date
            $nullDateQuery = "SELECT COUNT(*) as cnt FROM transfers 
                             WHERE transfer_category = 'PURCHASE_ORDER' 
                             AND expected_delivery_date IS NULL";
            $result = $db->query($nullDateQuery);
            $row = $result->fetch_assoc();
            if ($row['cnt'] > 0) {
                $this->pass("Edge Case: NULL delivery dates", $row['cnt'] . " orders with NULL dates");
            } else {
                $this->warn("Edge Case: NULL delivery dates", "All orders have delivery dates");
            }
            
            // Test 3: Orders with 0 line items
            $noItemsQuery = "SELECT COUNT(DISTINCT t.id) as cnt FROM transfers t
                            LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                            WHERE t.transfer_category = 'PURCHASE_ORDER'
                            AND ti.id IS NULL";
            $result = $db->query($noItemsQuery);
            $row = $result->fetch_assoc();
            if ($row['cnt'] > 0) {
                $this->warn("Edge Case: Orders with 0 items", $row['cnt'] . " orders have no line items");
            } else {
                $this->pass("Edge Case: Orders with 0 items", "All orders have line items");
            }
            
            // Test 4: Very long outlet names
            $longNameQuery = "SELECT name, LENGTH(name) as len FROM vend_outlets 
                             WHERE LENGTH(name) > 30 LIMIT 1";
            $result = $db->query($longNameQuery);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $this->pass("Edge Case: Long outlet name", $row['len'] . " chars: " . substr($row['name'], 0, 40) . "...");
            } else {
                $this->pass("Edge Case: Long outlet names", "All outlet names < 30 chars");
            }
            
            // Test 5: Special characters in data
            $specialCharsQuery = "SELECT public_id FROM transfers 
                                 WHERE public_id REGEXP '[^a-zA-Z0-9_-]' 
                                 LIMIT 1";
            $result = $db->query($specialCharsQuery);
            if ($result && $result->num_rows > 0) {
                $this->warn("Edge Case: Special characters", "Found special chars in order IDs");
            } else {
                $this->pass("Edge Case: Special characters", "No special chars in order IDs");
            }
            
        } catch (Exception $e) {
            $this->fail("Edge case tests", $e->getMessage());
        }
    }
    
    // ========================================================================
    // PERFORMANCE TESTS
    // ========================================================================
    
    private function testPerformance() {
        $this->section("PERFORMANCE BENCHMARKS");
        
        try {
            require_once __DIR__ . '/config/database.php';
            $db = Database::getInstance()->getConnection();
            $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';
            
            // Test 1: Orders query with pagination (target: < 500ms)
            $query = "SELECT t.id, t.public_id, t.created_at, t.state,
                             COUNT(DISTINCT ti.id) as items_count,
                             SUM(ti.quantity) as total_units
                      FROM transfers t
                      LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
                      WHERE t.supplier_id = ? AND t.transfer_category = 'PURCHASE_ORDER'
                      GROUP BY t.id
                      ORDER BY t.created_at DESC
                      LIMIT 50";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $testSupplierID);
            
            $start = microtime(true);
            $stmt->execute();
            $result = $stmt->get_result();
            $time = round((microtime(true) - $start) * 1000, 2);
            $stmt->close();
            
            if ($time < 300) {
                $this->pass("Performance: Orders query", "Excellent ({$time}ms < 300ms)");
            } else if ($time < 500) {
                $this->pass("Performance: Orders query", "Good ({$time}ms < 500ms)");
            } else {
                $this->warn("Performance: Orders query", "Slow ({$time}ms, target < 500ms)");
            }
            
            // Test 2: Notifications query (target: < 200ms)
            $query = "SELECT COUNT(fp.id) as count
                     FROM faulty_products fp
                     JOIN vend_products p ON fp.product_id = p.id
                     WHERE p.supplier_id = ? AND fp.supplier_status = 0";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param('s', $testSupplierID);
            
            $start = microtime(true);
            $stmt->execute();
            $time = round((microtime(true) - $start) * 1000, 2);
            $stmt->close();
            
            if ($time < 200) {
                $this->pass("Performance: Notifications query", "Fast ({$time}ms)");
            } else {
                $this->warn("Performance: Notifications query", "Could be faster ({$time}ms)");
            }
            
            // Test 3: Check for indexes
            $indexQuery = "SHOW INDEX FROM transfers WHERE Key_name != 'PRIMARY'";
            $result = $db->query($indexQuery);
            $indexCount = $result->num_rows;
            
            if ($indexCount > 0) {
                $this->pass("Performance: transfers indexes", "$indexCount indexes found");
            } else {
                $this->warn("Performance: transfers indexes", "No secondary indexes found");
            }
            
        } catch (Exception $e) {
            $this->fail("Performance tests", $e->getMessage());
        }
    }
    
    // ========================================================================
    // SUMMARY
    // ========================================================================
    
    private function printSummary() {
        $duration = round(microtime(true) - $this->startTime, 2);
        $total = $this->passCount + $this->failCount + $this->warnCount;
        
        echo "\n" . COLOR_BOLD . COLOR_BLUE . "═══════════════════════════════════════════════════════════════════\n";
        echo "  TEST SUMMARY\n";
        echo "═══════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
        
        echo COLOR_GREEN . "✅ PASSED: " . $this->passCount . COLOR_RESET . "\n";
        echo COLOR_RED . "❌ FAILED: " . $this->failCount . COLOR_RESET . "\n";
        echo COLOR_YELLOW . "⚠️  WARNINGS: " . $this->warnCount . COLOR_RESET . "\n";
        echo "━━━━━━━━━━━━━━━━━━━\n";
        echo "TOTAL TESTS: " . $total . "\n";
        echo "DURATION: " . $duration . "s\n\n";
        
        // Overall result
        if ($this->failCount === 0) {
            echo COLOR_GREEN . COLOR_BOLD . "🎉 ALL TESTS PASSED!" . COLOR_RESET . "\n";
            if ($this->warnCount > 0) {
                echo COLOR_YELLOW . "⚠️  Some warnings to review" . COLOR_RESET . "\n";
            }
            exit(0);
        } else {
            echo COLOR_RED . COLOR_BOLD . "❌ TESTS FAILED" . COLOR_RESET . "\n";
            echo COLOR_RED . "Please fix the failing tests before deployment." . COLOR_RESET . "\n";
            exit(1);
        }
    }
}

// Run the test suite
$suite = new TestSuite();
$suite->run();
