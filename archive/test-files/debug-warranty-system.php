<?php
/**
 * Warranty System Debug Script
 * 
 * Investigates the warranty claims system to understand:
 * 1. Database schema for faulty_products table
 * 2. Existing warranty claims for test supplier
 * 3. How media files are stored
 * 4. Current API endpoints status
 */

require_once __DIR__ . '/lib/Database.php';

$db = new Database();
$conn = $db->connect();

echo "========================================\n";
echo "🔍 WARRANTY SYSTEM DEBUG\n";
echo "========================================\n\n";

// 1. Check faulty_products table structure
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 1. FAULTY_PRODUCTS TABLE STRUCTURE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$schemaQuery = "DESCRIBE faulty_products";
$schemaResult = $conn->query($schemaQuery);

if ($schemaResult) {
    while ($row = $schemaResult->fetch_assoc()) {
        printf("%-30s %-20s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key']
        );
    }
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

echo "\n";

// 2. Check for warranty claims linked to our test supplier
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔧 2. WARRANTY CLAIMS FOR TEST SUPPLIER\n";
echo "   (British American Tobacco)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$testSupplierID = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8';

$claimsQuery = "
    SELECT 
        fp.id as fault_id,
        fp.product_id,
        fp.serial_number,
        fp.fault_desc,
        fp.staff_member,
        fp.store_location,
        fp.time_created,
        fp.status,
        fp.supplier_status,
        fp.supplier_update_status,
        fp.supplier_status_timestamp,
        p.name as product_name,
        p.sku
    FROM faulty_products fp
    LEFT JOIN vend_products p ON fp.product_id = p.id
    WHERE p.supplier_id = ?
    ORDER BY fp.time_created DESC
    LIMIT 10
";

$stmt = $conn->prepare($claimsQuery);
$stmt->bind_param('s', $testSupplierID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " warranty claims:\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "Fault ID: {$row['fault_id']}\n";
        echo "Product: {$row['product_name']} (SKU: {$row['sku']})\n";
        echo "Serial: {$row['serial_number']}\n";
        echo "Location: {$row['store_location']}\n";
        echo "Status: {$row['status']} (Supplier: {$row['supplier_status']})\n";
        echo "Description: " . substr($row['fault_desc'], 0, 100) . "...\n";
        echo "Staff: {$row['staff_member']}\n";
        echo "Date: {$row['time_created']}\n";
        echo "---\n";
    }
} else {
    echo "ℹ️  No warranty claims found for this supplier\n";
    echo "   Checking all faulty_products...\n\n";
    
    // Check total faulty products
    $totalQuery = "SELECT COUNT(*) as total FROM faulty_products";
    $totalResult = $conn->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    echo "   Total warranty claims in system: {$totalRow['total']}\n\n";
    
    // Check if products exist for this supplier
    $productsQuery = "SELECT COUNT(*) as total FROM vend_products WHERE supplier_id = ?";
    $stmt2 = $conn->prepare($productsQuery);
    $stmt2->bind_param('s', $testSupplierID);
    $stmt2->execute();
    $prodResult = $stmt2->get_result();
    $prodRow = $prodResult->fetch_assoc();
    echo "   Products from this supplier: {$prodRow['total']}\n";
}

$stmt->close();

echo "\n";

// 3. Check status values used in faulty_products
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 3. WARRANTY STATUS VALUES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$statusQuery = "
    SELECT 
        status,
        COUNT(*) as count
    FROM faulty_products
    GROUP BY status
    ORDER BY count DESC
";

$statusResult = $conn->query($statusQuery);

if ($statusResult && $statusResult->num_rows > 0) {
    while ($row = $statusResult->fetch_assoc()) {
        printf("%-20s: %d claims\n", $row['status'] ?? '(NULL)', $row['count']);
    }
} else {
    echo "No status data available\n";
}

echo "\n";

// 4. Check warranty_claim_updates table (if exists)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💬 4. WARRANTY UPDATES/NOTES TABLE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$tablesQuery = "SHOW TABLES LIKE '%warranty%' OR SHOW TABLES LIKE '%fault%'";
$tablesResult = $conn->query("SHOW TABLES LIKE '%warranty%'");

if ($tablesResult && $tablesResult->num_rows > 0) {
    echo "Warranty-related tables found:\n";
    while ($row = $tablesResult->fetch_array()) {
        echo "  - {$row[0]}\n";
    }
} else {
    echo "No warranty-specific tables found\n";
}

$tablesResult2 = $conn->query("SHOW TABLES LIKE '%fault%'");
if ($tablesResult2 && $tablesResult2->num_rows > 0) {
    echo "Fault-related tables found:\n";
    while ($row = $tablesResult2->fetch_array()) {
        echo "  - {$row[0]}\n";
    }
}

echo "\n";

// 5. Sample warranty claim data structure
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 5. SAMPLE WARRANTY CLAIM (FULL DATA)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$sampleQuery = "SELECT * FROM faulty_products LIMIT 1";
$sampleResult = $conn->query($sampleQuery);

if ($sampleResult && $sampleResult->num_rows > 0) {
    $sample = $sampleResult->fetch_assoc();
    foreach ($sample as $key => $value) {
        printf("%-30s: %s\n", $key, $value ?? '(NULL)');
    }
} else {
    echo "No sample data available\n";
}

echo "\n";

// 6. Check API endpoints
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🌐 6. WARRANTY API ENDPOINTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$apiFiles = [
    'api/warranty-action.php',
    'api/update-warranty-claim.php',
    'api/add-warranty-note.php',
    'api/download-media.php'
];

foreach ($apiFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ {$file} - EXISTS\n";
        
        // Check if it uses old bootstrap.php
        $content = file_get_contents($fullPath);
        if (strpos($content, 'bootstrap.php') !== false) {
            echo "   ⚠️  WARNING: Uses bootstrap.php (needs conversion to standalone)\n";
        }
        if (strpos($content, 'app.php') !== false) {
            echo "   ⚠️  WARNING: Uses app.php (needs conversion to standalone)\n";
        }
    } else {
        echo "❌ {$file} - NOT FOUND\n";
    }
}

echo "\n";

echo "========================================\n";
echo "✅ DEBUG COMPLETE\n";
echo "========================================\n";

$conn->close();
