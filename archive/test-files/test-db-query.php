<?php
/**
 * Test Database Query
 * Tests if the supplier exists in the database
 */

require_once __DIR__ . '/lib/Database.php';

$supplierID = '03f1b070-b0f8-11ec-a8dc-2d8b85195d82';

echo "=== DATABASE QUERY TEST ===\n\n";

echo "Testing supplier ID: $supplierID\n\n";

try {
    $supplier = Database::queryOne("
        SELECT id, name, email 
        FROM vend_suppliers 
        WHERE id = ? 
        AND (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
        LIMIT 1
    ", [$supplierID]);
    
    if ($supplier) {
        echo "✓ Supplier found!\n";
        echo "ID: " . $supplier['id'] . "\n";
        echo "Name: " . $supplier['name'] . "\n";
        echo "Email: " . ($supplier['email'] ?? 'N/A') . "\n";
    } else {
        echo "✗ Supplier NOT found\n";
        echo "This supplier_id does not exist in vend_suppliers table\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
