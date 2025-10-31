<?php
/**
 * Test DEBUG MODE - Verify supplier portal works without cookies
 */

require_once __DIR__ . '/bootstrap.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "DEBUG MODE TEST\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "1. DEBUG MODE STATUS\n";
echo "   DEBUG_MODE_ENABLED: " . (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED ? 'TRUE ✅' : 'FALSE ❌') . "\n";
echo "   DEBUG_MODE_SUPPLIER_ID: " . (defined('DEBUG_MODE_SUPPLIER_ID') ? DEBUG_MODE_SUPPLIER_ID : 'NOT SET') . "\n\n";

echo "2. AUTH CHECK\n";
$isAuth = Auth::check();
echo "   Auth::check(): " . ($isAuth ? 'TRUE ✅' : 'FALSE ❌') . "\n\n";

if ($isAuth) {
    echo "3. SUPPLIER DETAILS\n";
    $supplierId = Auth::getSupplierId();
    $supplierName = Auth::getSupplierName();
    echo "   Supplier ID: $supplierId\n";
    echo "   Supplier Name: $supplierName\n\n";

    echo "4. SESSION DATA\n";
    echo "   Session vars: " . json_encode($_SESSION) . "\n\n";

    echo "✅ SUCCESS - Portal is operational without cookies!\n";
    echo "   You can now browse: https://staff.vapeshed.co.nz/supplier/dashboard.php\n";
} else {
    echo "❌ FAILED - Auth check returned false\n";
    echo "   Debugging info: " . json_encode($_SESSION) . "\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
?>
