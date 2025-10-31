<?php
/**
 * Test Auth System
 * Tests if Auth::loginById() and Auth::check() work correctly
 */

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';

// Test supplier ID
$supplierID = '03f1b070-b0f8-11ec-a8dc-2d8b85195d82';

echo "=== AUTH SYSTEM TEST ===\n\n";

// Step 1: Try to login
echo "Step 1: Calling Auth::loginById('$supplierID')...\n";
$loginResult = Auth::loginById($supplierID);
echo "Result: " . ($loginResult ? "SUCCESS" : "FAILED") . "\n\n";

// Step 2: Check session data immediately after login
echo "Step 2: Checking session data after login...\n";
echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);
echo "\n";

// Step 3: Check if Auth::check() works
echo "Step 3: Calling Auth::check()...\n";
$checkResult = Auth::check();
echo "Result: " . ($checkResult ? "AUTHENTICATED" : "NOT AUTHENTICATED") . "\n\n";

// Step 4: Get current supplier
if ($checkResult) {
    echo "Step 4: Getting current supplier...\n";
    $supplier = Auth::getCurrentSupplier();
    print_r($supplier);
} else {
    echo "Step 4: SKIPPED (not authenticated)\n";
}

echo "\n=== TEST COMPLETE ===\n";
