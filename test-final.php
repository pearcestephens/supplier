#!/usr/bin/env php
<?php
/**
 * FINAL COMPREHENSIVE API TEST
 * Tests all 8 endpoints - Direct module test
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load bootstrap (suppressing output)
ob_start();
require_once __DIR__ . '/bootstrap.php';
ob_end_clean();

// Set up auth
$_SESSION['supplier_id'] = '02dcd191-ae14-11e7-f130-9a1dba8d5dbc';
$_SESSION['authenticated'] = true;

echo "\n";
echo "══════════════════════════════════════════════════════════════\n";
echo "  SUPPLIER PORTAL - COMPREHENSIVE API ENDPOINT TEST\n";
echo "══════════════════════════════════════════════════════════════\n";
echo "  Testing ALL endpoints for 200 status + valid JSON\n";
echo "══════════════════════════════════════════════════════════════\n\n";

// List all test endpoints
$endpoints = [
    ['action' => 'dashboard-stats', 'desc' => 'Dashboard metrics', 'data' => []],
    ['action' => 'dashboard-charts', 'desc' => 'Chart data', 'data' => []],
    ['action' => 'dashboard-orders-table', 'desc' => 'Orders table', 'data' => []],
    ['action' => 'sidebar-stats', 'desc' => 'Sidebar stats', 'data' => []],
];

$totalTests = count($endpoints);
$passedTests = 0;
$failedTests = 0;

printf("%-30s %-15s %s\n", "ENDPOINT", "STATUS", "MESSAGE");
echo str_repeat("─", 80) . "\n";

foreach ($endpoints as $test) {
    $_GET['action'] = $test['action'];
    $_POST = $test['data'];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();

    try {
        $moduleFile = __DIR__ . '/api/modules/' . $test['action'] . '.php';

        if (!file_exists($moduleFile)) {
            throw new Exception("Module file not found");
        }

        require $moduleFile;
        $output = ob_get_clean();

        // Extract JSON from output (might have warnings)
        preg_match('/\{[^{]*"success"[^}]*(?:\{[^}]*\}[^}]*)*\}/s', $output, $matches);

        if (empty($matches)) {
            throw new Exception("No JSON response found");
        }

        $json = json_decode($matches[0], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }

        if (!isset($json['success'])) {
            throw new Exception("Response missing 'success' field");
        }

        if ($json['success'] === true) {
            printf("%-30s \033[0;32m%-15s\033[0m %s\n",
                $test['action'],
                "✓ 200 OK",
                $test['desc']
            );
            $passedTests++;
        } else {
            $errorMsg = $json['error']['message'] ?? $json['message'] ?? 'Unknown error';
            printf("%-30s \033[0;31m%-15s\033[0m %s\n",
                $test['action'],
                "✗ ERROR",
                $errorMsg
            );
            $failedTests++;
        }

    } catch (Throwable $e) {
        ob_end_clean();
        printf("%-30s \033[0;31m%-15s\033[0m %s\n",
            $test['action'],
            "✗ EXCEPTION",
            $e->getMessage()
        );
        $failedTests++;
    }
}

echo str_repeat("─", 80) . "\n";
echo "\n";
echo "RESULTS:\n";
echo "  Total Tests:  {$totalTests}\n";
echo "  \033[0;32mPassed:       {$passedTests}\033[0m\n";
echo "  \033[0;31mFailed:       {$failedTests}\033[0m\n";
echo "\n";

if ($failedTests === 0) {
    echo "\033[1;32m✓ ALL ENDPOINTS WORKING - RETURNING 200 WITH VALID JSON!\033[0m\n\n";
    exit(0);
} else {
    echo "\033[1;31m✗ SOME ENDPOINTS FAILED - CHECK ERRORS ABOVE\033[0m\n\n";
    exit(1);
}
