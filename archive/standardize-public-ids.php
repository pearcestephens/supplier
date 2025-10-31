<?php
/**
 * PUBLIC_ID STANDARDIZATION & CLEANUP
 * 
 * Cleans up all public_ids to use clean format:
 * - ST-{id} for stock transfers
 * - JT-{id} for juice transfers  
 * - IT-{id} for staff/internal transfers
 * - PO-{id} for purchase orders
 * 
 * SAFE: Dry run first, then --execute
 */

$db = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');

$dryRun = !in_array('--execute', $argv);

if ($dryRun) {
    echo "🔒 DRY RUN - No changes will be made\n";
    echo "   Use: php standardize-public-ids.php --execute\n\n";
} else {
    echo "⚠️  EXECUTE MODE - Changes will be made!\n\n";
}

echo "PUBLIC_ID STANDARDIZATION\n";
echo "=========================\n\n";

$stats = [
    'stock' => ['total' => 0, 'updated' => 0],
    'juice' => ['total' => 0, 'updated' => 0],
    'staff' => ['total' => 0, 'updated' => 0],
    'po' => ['total' => 0, 'updated' => 0],
];

// 1. STOCK TRANSFERS → ST-{id}
echo "STOCK TRANSFERS (ST-XXXXX):\n";
echo "----------------------------\n";
$result = $db->query("
    SELECT id, public_id, transfer_category 
    FROM vend_consignments 
    WHERE transfer_category = 'STOCK'
      AND deleted_at IS NULL
");

$stats['stock']['total'] = $result->num_rows;
$stockUpdates = [];

while ($row = $result->fetch_assoc()) {
    $newPublicId = "ST-" . $row['id'];
    
    if ($row['public_id'] !== $newPublicId) {
        $stockUpdates[] = [
            'id' => $row['id'],
            'old' => $row['public_id'],
            'new' => $newPublicId
        ];
    }
}

$stats['stock']['updated'] = count($stockUpdates);
echo "  Total: {$stats['stock']['total']}\n";
echo "  Need update: {$stats['stock']['updated']}\n";

if (!$dryRun && !empty($stockUpdates)) {
    foreach ($stockUpdates as $update) {
        $db->query("UPDATE vend_consignments SET public_id = '{$update['new']}' WHERE id = {$update['id']}");
    }
    echo "  ✅ Updated!\n";
}
echo "\n";

// 2. JUICE TRANSFERS → JT-{id}
echo "JUICE TRANSFERS (JT-XXXXX):\n";
echo "----------------------------\n";
$result = $db->query("
    SELECT id, public_id, transfer_category 
    FROM vend_consignments 
    WHERE transfer_category = 'JUICE'
      AND deleted_at IS NULL
");

$stats['juice']['total'] = $result->num_rows;
$juiceUpdates = [];

while ($row = $result->fetch_assoc()) {
    $newPublicId = "JT-" . $row['id'];
    
    if ($row['public_id'] !== $newPublicId) {
        $juiceUpdates[] = [
            'id' => $row['id'],
            'old' => $row['public_id'],
            'new' => $newPublicId
        ];
    }
}

$stats['juice']['updated'] = count($juiceUpdates);
echo "  Total: {$stats['juice']['total']}\n";
echo "  Need update: {$stats['juice']['updated']}\n";

if (!$dryRun && !empty($juiceUpdates)) {
    foreach ($juiceUpdates as $update) {
        $db->query("UPDATE vend_consignments SET public_id = '{$update['new']}' WHERE id = {$update['id']}");
    }
    echo "  ✅ Updated!\n";
}
echo "\n";

// 3. STAFF TRANSFERS → IT-{id}
echo "STAFF/INTERNAL TRANSFERS (IT-XXXXX):\n";
echo "-------------------------------------\n";
$result = $db->query("
    SELECT id, public_id, transfer_category 
    FROM vend_consignments 
    WHERE transfer_category = 'STAFF'
      AND deleted_at IS NULL
");

$stats['staff']['total'] = $result->num_rows;
$staffUpdates = [];

while ($row = $result->fetch_assoc()) {
    $newPublicId = "IT-" . $row['id'];
    
    if ($row['public_id'] !== $newPublicId) {
        $staffUpdates[] = [
            'id' => $row['id'],
            'old' => $row['public_id'],
            'new' => $newPublicId
        ];
    }
}

$stats['staff']['updated'] = count($staffUpdates);
echo "  Total: {$stats['staff']['total']}\n";
echo "  Need update: {$stats['staff']['updated']}\n";

if (!$dryRun && !empty($staffUpdates)) {
    foreach ($staffUpdates as $update) {
        $db->query("UPDATE vend_consignments SET public_id = '{$update['new']}' WHERE id = {$update['id']}");
    }
    echo "  ✅ Updated!\n";
}
echo "\n";

// 4. PURCHASE ORDERS → PO-{id}
echo "PURCHASE ORDERS (PO-XXXXX):\n";
echo "----------------------------\n";
$result = $db->query("
    SELECT id, public_id, transfer_category 
    FROM vend_consignments 
    WHERE transfer_category = 'PURCHASE_ORDER'
      AND deleted_at IS NULL
");

$stats['po']['total'] = $result->num_rows;
$poUpdates = [];

while ($row = $result->fetch_assoc()) {
    $newPublicId = "PO-" . $row['id'];
    
    if ($row['public_id'] !== $newPublicId) {
        $poUpdates[] = [
            'id' => $row['id'],
            'old' => $row['public_id'],
            'new' => $newPublicId
        ];
    }
}

$stats['po']['updated'] = count($poUpdates);
echo "  Total: {$stats['po']['total']}\n";
echo "  Need update: {$stats['po']['updated']}\n";

if (!$dryRun && !empty($poUpdates)) {
    foreach ($poUpdates as $update) {
        $db->query("UPDATE vend_consignments SET public_id = '{$update['new']}' WHERE id = {$update['id']}");
    }
    echo "  ✅ Updated!\n";
}
echo "\n";

// Summary
echo "SUMMARY:\n";
echo "--------\n";
$totalRecords = $stats['stock']['total'] + $stats['juice']['total'] + $stats['staff']['total'] + $stats['po']['total'];
$totalUpdates = $stats['stock']['updated'] + $stats['juice']['updated'] + $stats['staff']['updated'] + $stats['po']['updated'];

echo "Total records: {$totalRecords}\n";
echo "Need updates: {$totalUpdates}\n";

if ($dryRun) {
    echo "\n🔒 DRY RUN complete. Use --execute to apply changes.\n";
} else {
    echo "\n✅ Updates complete!\n";
}

// Show samples
if ($dryRun && $totalUpdates > 0) {
    echo "\nSample changes (first 5):\n";
    $allUpdates = array_merge($stockUpdates, $juiceUpdates, $staffUpdates, $poUpdates);
    foreach (array_slice($allUpdates, 0, 5) as $update) {
        echo "  ID {$update['id']}: '{$update['old']}' → '{$update['new']}'\n";
    }
}

$db->close();
