#!/usr/bin/env php
<?php
/**
 * Quick Endpoint Status Check
 * Simple check that all endpoints return 200 with valid JSON
 */

// Start session FIRST
session_start();

// Load bootstrap with output buffering to catch warnings
ob_start();
require_once __DIR__ . '/bootstrap.php';
ob_end_clean();

// Set up test authentication
$testSupplierId = '02dcd191-ae14-11e7-f130-9a1dba8d5dbc';
$_SESSION['supplier_id'] = $testSupplierId;
$_SESSION['authenticated'] = true;

// Test each endpoint
$endpoints = [
    'dashboard-stats',
    'dashboard-charts',
    'dashboard-orders-table',
    'sidebar-stats',
];

$passed = 0;
$failed = 0;
$results = [];

foreach ($endpoints as $action) {
    // Set up mock request
    $_GET['action'] = $action;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    // Capture endpoint output
    ob_start();

    try {
        $moduleFile = __DIR__ . '/api/modules/' . $action . '.php';
        include $moduleFile;

        $output = ob_get_contents();
        ob_end_clean();

        // Try to find JSON in the output (it might have warnings before it)
        if (preg_match('/\{.*"success".*\}/s', $output, $matches)) {
            $json = json_decode($matches[0], true);

            if ($json && $json['success'] === true) {
                $results[$action] = '✓ PASS';
                $passed++;
            } else {
                $results[$action] = '✗ FAIL: ' . ($json['error']['message'] ?? 'Unknown error');
                $failed++;
            }
        } else {
            $results[$action] = '✗ FAIL: No valid JSON found';
            $failed++;
        }

    } catch (Throwable $e) {
        ob_end_clean();
        $results[$action] = '✗ FAIL: ' . $e->getMessage();
        $failed++;
    }
}

// Print results cleanly
echo "\n════════════════════════════════════════\n";
echo "  API ENDPOINT STATUS CHECK\n";
echo "════════════════════════════════════════\n\n";

foreach ($results as $endpoint => $result) {
    $status = strpos($result, '✓') !== false ? "\033[0;32m" : "\033[0;31m";
    echo str_pad($endpoint, 30) . " " . $status . $result . "\033[0m\n";
}

echo "\n────────────────────────────────────────\n";
echo "TOTAL: {$passed} passed, {$failed} failed\n";
echo "════════════════════════════════════════\n\n";

exit($failed > 0 ? 1 : 0);
