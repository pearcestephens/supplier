<?php
/**
 * Find Valid Suppliers
 * Gets a list of active suppliers for testing
 */

require_once __DIR__ . '/lib/Database.php';

echo "=== FIND VALID SUPPLIERS ===\n\n";

try {
    $suppliers = Database::query("
        SELECT id, name, email 
        FROM vend_suppliers 
        WHERE (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
        LIMIT 10
    ");
    
    if (empty($suppliers)) {
        echo "No suppliers found in database\n";
    } else {
        echo "Found " . count($suppliers) . " active suppliers:\n\n";
        foreach ($suppliers as $supplier) {
            echo "ID: " . $supplier['id'] . "\n";
            echo "Name: " . $supplier['name'] . "\n";
            echo "Email: " . ($supplier['email'] ?? 'N/A') . "\n";
            echo "Magic Link: https://staff.vapeshed.co.nz/supplier/?supplier_id=" . $supplier['id'] . "\n";
            echo str_repeat('-', 80) . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== SEARCH COMPLETE ===\n";
