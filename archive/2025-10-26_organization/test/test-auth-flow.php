<?php
/**
 * Authentication Flow Test Script
 * Tests the complete login flow: GET parameter → Session → Redirect
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';

echo "<pre>\n";
echo "==============================================\n";
echo "  SUPPLIER PORTAL AUTH FLOW TEST\n";
echo "==============================================\n\n";

// Test 1: Session Management
echo "TEST 1: Session Management\n";
echo "----------------------------\n";
Session::start();
echo "✓ Session started successfully\n";
echo "  Session ID: " . session_id() . "\n\n";

// Test 2: Database Connection
echo "TEST 2: Database Connection\n";
echo "----------------------------\n";
try {
    $db = Database::connect();
    echo "✓ Database connected successfully\n";
    
    // Test supplier lookup
    $testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'; // British American Tobacco
    $stmt = $db->prepare("SELECT id, name, email FROM vend_suppliers WHERE id = ? LIMIT 1");
    $stmt->bind_param('s', $testSupplierID);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if ($supplier) {
        echo "✓ Test supplier found:\n";
        echo "  ID: {$supplier['id']}\n";
        echo "  Name: {$supplier['name']}\n";
        echo "  Email: {$supplier['email']}\n\n";
    } else {
        echo "✗ Test supplier not found\n\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Auth Check (should be false initially)
echo "TEST 3: Initial Auth Check\n";
echo "----------------------------\n";
if (Auth::check()) {
    echo "✓ Already authenticated\n";
    echo "  Supplier ID: " . Auth::getSupplierId() . "\n";
    echo "  Supplier Name: " . Auth::getSupplierName() . "\n\n";
} else {
    echo "✓ Not authenticated (expected)\n\n";
}

// Test 4: Login by ID
echo "TEST 4: Login by ID (Magic Link Simulation)\n";
echo "----------------------------\n";
if (isset($supplier)) {
    if (Auth::loginById($supplier['id'])) {
        echo "✓ Login successful\n";
        echo "  Session created for: {$supplier['name']}\n\n";
    } else {
        echo "✗ Login failed\n\n";
    }
} else {
    echo "✗ No supplier to test login\n\n";
}

// Test 5: Verify Authentication
echo "TEST 5: Verify Authentication After Login\n";
echo "----------------------------\n";
if (Auth::check()) {
    echo "✓ Authentication verified\n";
    echo "  Supplier ID: " . Auth::getSupplierId() . "\n";
    echo "  Supplier Name: " . Auth::getSupplierName() . "\n\n";
} else {
    echo "✗ Authentication failed after login\n\n";
}

// Test 6: GET Parameter Test
echo "TEST 6: GET Parameter Handling\n";
echo "----------------------------\n";
if (isset($_GET['supplier_id'])) {
    echo "✓ supplier_id parameter detected: " . htmlspecialchars($_GET['supplier_id']) . "\n";
    echo "  Would trigger Auth::loginById() in index.php\n\n";
} else {
    echo "  No supplier_id parameter (test with ?supplier_id=UUID)\n\n";
}

// Test 7: Magic Link Generation
echo "TEST 7: Magic Link Generation\n";
echo "----------------------------\n";
if (isset($supplier)) {
    $magicLink = 'https://' . $_SERVER['HTTP_HOST'] . '/supplier/index.php?supplier_id=' . urlencode($supplier['id']);
    echo "✓ Magic link generated:\n";
    echo "  {$magicLink}\n\n";
}

// Test 8: Session Data
echo "TEST 8: Session Data\n";
echo "----------------------------\n";
if (isset($_SESSION['supplier'])) {
    echo "✓ Session data exists:\n";
    echo "  " . print_r($_SESSION['supplier'], true) . "\n";
} else {
    echo "  No session data (expected if not logged in)\n\n";
}

// Test 9: Logout Test
echo "TEST 9: Logout Test\n";
echo "----------------------------\n";
if (Auth::check()) {
    Session::destroy();
    echo "✓ Session destroyed\n";
    
    if (!Auth::check()) {
        echo "✓ Auth check returns false after logout\n\n";
    } else {
        echo "✗ Auth still returns true after logout\n\n";
    }
} else {
    echo "  Not logged in, skipping logout test\n\n";
}

echo "==============================================\n";
echo "  FLOW SIMULATION\n";
echo "==============================================\n\n";

echo "1. User requests login page:\n";
echo "   → https://staff.vapeshed.co.nz/supplier/login.php\n\n";

echo "2. User enters email:\n";
echo "   → System sends magic link to email\n\n";

echo "3. User clicks magic link:\n";
if (isset($supplier)) {
    echo "   → https://staff.vapeshed.co.nz/supplier/index.php?supplier_id={$supplier['id']}\n\n";
}

echo "4. index.php processes GET parameter:\n";
echo "   → Detects supplier_id in GET\n";
echo "   → Calls Auth::loginById()\n";
echo "   → Creates session\n";
echo "   → Loads dashboard\n\n";

echo "5. Subsequent requests use session:\n";
echo "   → Auth::check() validates session\n";
echo "   → No GET parameter needed\n\n";

echo "==============================================\n";
echo "  TEST URLS\n";
echo "==============================================\n\n";

echo "Login Page:\n";
echo "https://staff.vapeshed.co.nz/supplier/login.php\n\n";

if (isset($supplier)) {
    echo "Direct Login (Magic Link):\n";
    echo "https://staff.vapeshed.co.nz/supplier/index.php?supplier_id={$supplier['id']}\n\n";
}

echo "Dashboard (requires auth):\n";
echo "https://staff.vapeshed.co.nz/supplier/index.php\n\n";

echo "API Test (requires auth):\n";
echo "https://staff.vapeshed.co.nz/supplier/api/endpoint.php?handler=dashboard&method=getStats\n\n";

echo "==============================================\n";
echo "  ALL TESTS COMPLETE\n";
echo "==============================================\n";
echo "</pre>";
