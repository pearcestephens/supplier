#!/usr/bin/env php
<?php
/**
 * Comprehensive API Endpoint Tester
 * Tests ALL API endpoints with proper authentication
 *
 * Usage: php test-all-endpoints.php
 */

declare(strict_types=1);

// Start session BEFORE loading bootstrap so we can set auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load bootstrap to get database access
require_once __DIR__ . '/bootstrap.php';

// ANSI colors for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

$results = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          SUPPLIER PORTAL API ENDPOINT TESTER                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Get test supplier ID and set up authentication
$testSupplierId = getTestSupplierID();
$_SESSION['supplier_id'] = $testSupplierId;
$_SESSION['authenticated'] = true;
$_SESSION['supplier_name'] = 'Test Supplier';
$_SESSION['supplier_email'] = 'test@supplier.com';

echo "Testing with Supplier ID: " . GREEN . $testSupplierId . RESET . "\n\n";

/**
 * Test an API endpoint
 */
function testEndpoint(string $action, array $postData = [], string $description = ''): array {
    global $totalTests, $passedTests, $failedTests;

    $totalTests++;

    // Set up test environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = $action;
    $_POST = $postData;

    // NOTE: Authentication is already set up in session at script start

    // Capture output
    ob_start();

    try {
        // Include the API index which will process the request
        // We need to reset some globals first
        unset($GLOBALS['response']);

        // Execute the module directly
        $moduleFile = __DIR__ . '/api/modules/' . $action . '.php';

        if (!file_exists($moduleFile)) {
            throw new Exception("Module file not found: {$moduleFile}");
        }

        require $moduleFile;

        $output = ob_get_clean();

        // If module didn't output anything, check for $response variable
        if (empty($output) && isset($response)) {
            $output = json_encode($response, JSON_PRETTY_PRINT);
        }

        // Parse JSON response
        $json = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        // Check if successful
        if (!isset($json['success'])) {
            throw new Exception("Response missing 'success' field");
        }

        if ($json['success'] === false) {
            throw new Exception("API returned error: " . ($json['error']['message'] ?? 'Unknown error'));
        }

        $passedTests++;

        return [
            'status' => 'PASS',
            'action' => $action,
            'description' => $description,
            'response' => $json,
            'data_keys' => isset($json['data']) ? array_keys((array)$json['data']) : []
        ];

    } catch (Exception $e) {
        ob_end_clean();
        $failedTests++;

        return [
            'status' => 'FAIL',
            'action' => $action,
            'description' => $description,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

/**
 * Get a test supplier ID from database
 */
function getTestSupplierID(): string {
    global $pdo;

    // Get first supplier UUID from vend_suppliers table
    // The id column is the UUID used for authentication
    $stmt = $pdo->query("SELECT id, name FROM vend_suppliers LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Return ID and name will be printed by caller
        return $result['id'];
    }

    // Fallback to a known test ID if table is empty
    return '02dcd191-ae14-11e7-f130-9a1dba8d5dbc';
}

/**
 * Print test result
 */
function printResult(array $result): void {
    $status = $result['status'];
    $action = $result['action'];
    $description = $result['description'];

    if ($status === 'PASS') {
        echo GREEN . "✓ PASS" . RESET . " │ ";
        echo BLUE . str_pad($action, 30) . RESET . " │ ";
        echo $description . "\n";

        if (!empty($result['data_keys'])) {
            echo "         │ " . YELLOW . "Data keys: " . RESET . implode(', ', $result['data_keys']) . "\n";
        }
    } else {
        echo RED . "✗ FAIL" . RESET . " │ ";
        echo BLUE . str_pad($action, 30) . RESET . " │ ";
        echo $description . "\n";
        echo RED . "         │ Error: " . $result['error'] . RESET . "\n";
        echo "         │ " . $result['file'] . ":" . $result['line'] . "\n";
    }

    echo "──────────┼────────────────────────────────┼" . str_repeat("─", 50) . "\n";
}

// ============================================================================
// START TESTING
// ============================================================================

echo "Testing with Supplier ID: " . GREEN . getTestSupplierID() . RESET . "\n\n";

echo "──────────┬────────────────────────────────┬" . str_repeat("─", 50) . "\n";
echo " Status   │ Endpoint                       │ Description\n";
echo "──────────┼────────────────────────────────┼" . str_repeat("─", 50) . "\n";

// Test 1: Dashboard Stats
$results[] = testEndpoint('dashboard-stats', [], 'Dashboard metrics and statistics');
printResult(end($results));

// Test 2: Dashboard Charts
$results[] = testEndpoint('dashboard-charts', [], 'Chart data for dashboard graphs');
printResult(end($results));

// Test 3: Dashboard Orders Table
$results[] = testEndpoint('dashboard-orders-table', [], 'Recent orders table data');
printResult(end($results));

// Test 4: Dashboard Stock Alerts
$results[] = testEndpoint('dashboard-stock-alerts', [], 'Low stock alerts');
printResult(end($results));

// Test 5: Sidebar Stats
$results[] = testEndpoint('sidebar-stats', [], 'Sidebar notification counts');
printResult(end($results));

// Test 6: Add Order Note
$results[] = testEndpoint('add-order-note', [
    'order_id' => 'test-order-001',
    'note' => 'Test note from automated test'
], 'Add note to order');
printResult(end($results));

// Test 7: Update Tracking
$results[] = testEndpoint('update-tracking', [
    'order_id' => 'test-order-001',
    'tracking_number' => 'TEST123456789'
], 'Update tracking number');
printResult(end($results));

// Test 8: Request Info
$results[] = testEndpoint('request-info', [
    'order_id' => 'test-order-001',
    'message' => 'Test info request'
], 'Request more information');
printResult(end($results));

// Test 9: Update Profile
$results[] = testEndpoint('update-profile', [
    'name' => 'Test Supplier Name',
    'email' => 'test@example.com',
    'phone' => '0211234567'
], 'Update supplier profile');
printResult(end($results));

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      TEST SUMMARY                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests:  " . BLUE . $totalTests . RESET . "\n";
echo "Passed:       " . GREEN . $passedTests . RESET . " (" . round(($passedTests / $totalTests) * 100) . "%)\n";
echo "Failed:       " . RED . $failedTests . RESET . " (" . round(($failedTests / $totalTests) * 100) . "%)\n";
echo "\n";

if ($failedTests > 0) {
    echo RED . "⚠ SOME TESTS FAILED - Review errors above" . RESET . "\n\n";
    exit(1);
} else {
    echo GREEN . "✓ ALL TESTS PASSED!" . RESET . "\n\n";
    exit(0);
}
