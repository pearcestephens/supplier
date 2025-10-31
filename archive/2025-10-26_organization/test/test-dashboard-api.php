<?php
/**
 * Dashboard API Test Script (WITH AUTHENTICATION)
 * Tests all dashboard endpoints and validates responses
 * 
 * Usage: php test-dashboard-api.php
 */

declare(strict_types=1);

// Bootstrap with authentication
require_once __DIR__ . '/bootstrap.php';

// Simulate authenticated session (use test supplier ID)
$_SESSION['supplier_id'] = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'; // British American Tobacco
$_SESSION['authenticated'] = true;
$_SESSION['login_time'] = time();

echo "========================================\n";
echo "DASHBOARD API TEST SUITE (Authenticated)\n";
echo "========================================\n";
echo "Test Supplier: " . ($_SESSION['supplier_id'] ?? 'NONE') . "\n";
echo "========================================\n\n";

// Test configuration - use local file includes instead of HTTP
$baseDir = __DIR__ . '/api';
$endpoints = [
    'dashboard-stats' => [
        'file' => 'dashboard-stats.php',
        'required_fields' => ['success', 'data'],
        'data_fields' => ['total_orders', 'active_products', 'pending_claims', 'avg_order_value', 'units_sold']
    ],
    'dashboard-orders-table' => [
        'file' => 'dashboard-orders-table.php',
        'required_fields' => ['success', 'data'],
        'data_fields' => ['orders', 'total', 'showing']
    ],
    'dashboard-stock-alerts' => [
        'file' => 'dashboard-stock-alerts.php',
        'required_fields' => ['success', 'data'],
        'data_fields' => ['stores', 'alerts']
    ],
    'dashboard-charts' => [
        'file' => 'dashboard-charts.php',
        'required_fields' => ['success', 'data'],
        'data_fields' => ['items_sold', 'warranty_claims']
    ]
];

$passed = 0;
$failed = 0;

foreach ($endpoints as $name => $config) {
    echo "TEST: {$name}\n";
    echo str_repeat('-', 50) . "\n";
    
    $file = $baseDir . '/' . $config['file'];
    echo "File: {$config['file']}\n";
    
    // Capture output from API file
    ob_start();
    try {
        include $file;
        $response = ob_get_clean();
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ FAILED: Exception - " . $e->getMessage() . "\n\n";
        $failed++;
        continue;
    }
    
    // Parse JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ FAILED: Invalid JSON\n";
        echo "Error: " . json_last_error_msg() . "\n";
        echo "Response: " . substr($response, 0, 200) . "\n\n";
        $failed++;
        continue;
    }
    
    // Check required fields
    $missing = [];
    foreach ($config['required_fields'] as $field) {
        if (!isset($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        echo "❌ FAILED: Missing required fields: " . implode(', ', $missing) . "\n\n";
        $failed++;
        continue;
    }
    
    // Check success flag
    if (!$data['success']) {
        echo "❌ FAILED: success = false\n";
        echo "Error: " . ($data['error'] ?? 'Unknown error') . "\n\n";
        $failed++;
        continue;
    }
    
    // Check data fields
    if (isset($config['data_fields'])) {
        $missingData = [];
        foreach ($config['data_fields'] as $field) {
            if (!isset($data['data'][$field])) {
                $missingData[] = $field;
            }
        }
        
        if (!empty($missingData)) {
            echo "⚠️  WARNING: Missing data fields: " . implode(', ', $missingData) . "\n";
        }
    }
    
    // Success!
    echo "✅ PASSED\n";
    echo "Sample data:\n";
    if (isset($data['data'])) {
        foreach (array_slice($data['data'], 0, 5) as $key => $value) {
            $displayValue = is_numeric($value) ? $value : json_encode($value);
            echo "  - {$key}: {$displayValue}\n";
        }
    }
    echo "\n";
    $passed++;
}

echo "========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
