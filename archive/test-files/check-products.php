<?php
$mysqli = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');

echo "=== PRODUCTS INVESTIGATION (FIXED) ===\n\n";

// Check total products with correct deleted_at
$result = $mysqli->query("SELECT COUNT(*) as total FROM vend_products WHERE deleted_at='0000-00-00 00:00:00'");
echo "Total Active Products: " . $result->fetch_assoc()['total'] . "\n\n";

// Check suppliers with most products
echo "Top 5 Suppliers by Product Count:\n";
echo "=====================================\n";
$result = $mysqli->query("
    SELECT COUNT(*) as total, supplier_id, 
           (SELECT name FROM vend_suppliers WHERE id=vend_products.supplier_id LIMIT 1) as supplier_name
    FROM vend_products 
    WHERE deleted_at='0000-00-00 00:00:00'
    GROUP BY supplier_id 
    ORDER BY total DESC 
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    echo $row['total'] . " products | " . $row['supplier_id'] . " | " . ($row['supplier_name'] ?: 'N/A') . "\n";
}

echo "\n";

// Check test supplier
echo "Checking test supplier 0a91b764-1c71-11eb-e0eb-d7bf46fa95c8:\n";
$result = $mysqli->query("SELECT COUNT(*) as total FROM vend_products WHERE supplier_id='0a91b764-1c71-11eb-e0eb-d7bf46fa95c8' AND deleted_at='0000-00-00 00:00:00'");
echo "Products: " . $result->fetch_assoc()['total'] . "\n";

$mysqli->close();
