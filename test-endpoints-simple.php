#!/usr/bin/env php
<?php
/**
 * Simple API Endpoint Tester
 * Tests ALL API endpoints - Clean output
 *
 * Usage: php test-endpoints-simple.php
 */

declare(strict_types=1);

// Suppress all warnings and errors for clean output
error_reporting(0);
ini_set('display_errors', '0');

// Start session before bootstrap
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

// ANSI colors
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

// Get test supplier
$pdo = pdo();
$stmt = $pdo->query("SELECT id, name FROM vend_suppliers LIMIT 1");
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

// Set up authentication
$_SESSION['supplier_id'] = $supplier['id'];
$_SESSION['authenticated'] = true;
$_SESSION['supplier_name'] = $supplier['name'];

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  SUPPLIER PORTAL API ENDPOINT TESTS\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  Supplier: " . $supplier['name'] . "\n";
echo "  ID: " . $supplier['id'] . "\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Define test endpoints
$endpoints = [
    'dashboard-stats' => 'Dashboard statistics',
    'dashboard-charts' => 'Dashboard charts data',
    'dashboard-orders-table' => 'Recent orders table',
    'sidebar-stats' => 'Sidebar statistics',
    'add-order-note' => 'Add note to order',
    'update-tracking' => 'Update tracking info',
    'request-info' => 'Request information',
    'update-profile' => 'Update supplier profile',
];

$passed = 0;
$failed = 0;

echo str_pad("ENDPOINT", 30) . "  STATUS    RESPONSE TIME\n";
echo str_repeat("─", 60) . "\n";

foreach ($endpoints as $action => $description) {
    $start = microtime(true);

    // Simulate API request
    $_GET['action'] = $action;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    try {
        // Include the module directly
        $moduleFile = __DIR__ . '/api/modules/' . $action . '.php';

        if (!file_exists($moduleFile)) {
            throw new Exception("Module not found");
        }

        require $moduleFile;
        $output = ob_get_clean();

        // Parse JSON
        $json = json_decode($output, true);

        if ($json && isset($json['success']) && $json['success'] === true) {
            $time = round((microtime(true) - $start) * 1000, 2);
            echo str_pad($action, 30) . "  " . GREEN . "✓ PASS" . RESET . "    {$time}ms\n";
            $passed++;
        } else {
            $error = $json['error']['message'] ?? 'Unknown error';
            echo str_pad($action, 30) . "  " . RED . "✗ FAIL" . RESET . "    {$error}\n";
            $failed++;
        }

    } catch (Throwable $e) {
        ob_end_clean();
        echo str_pad($action, 30) . "  " . RED . "✗ FAIL" . RESET . "    " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo str_repeat("─", 60) . "\n";
echo "\nRESULTS: " . GREEN . "{$passed} passed" . RESET . ", " . ($failed > 0 ? RED : GREEN) . "{$failed} failed" . RESET . "\n";
echo "\n";

exit($failed > 0 ? 1 : 0);
