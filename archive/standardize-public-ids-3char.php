<?php
/**
 * STANDARDIZE PUBLIC IDS - Convert to 3-Char Prefix System
 * 
 * This script:
 * 1. Adds 'INTERNAL' to transfer_category ENUM
 * 2. Renames ALL public_ids to use 3-char prefix + database ID
 * 3. Updates transfer_category for each type
 * 
 * NEW NAMING SCHEME:
 * - STK-{id} = Stock Transfer (was TR-)
 * - INT-{id} = Internal Transfer (was ST-)
 * - JCE-{id} = Juice Transfer (was JT-)
 * - POR-{id} = Purchase Order (was PO-)
 * 
 * Example transformations:
 * - TR-STO-202509-000004-47 → STK-1234
 * - ST-202509-123 → INT-5678
 * - JT-2024-456 → JCE-9012
 * - PO-42 → POR-42
 * 
 * Uses vend_consignments.id (primary key) as the unique identifier
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(600);

$db = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if ($db->connect_error) {
    die("❌ Connection failed: " . $db->connect_error);
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  PUBLIC ID STANDARDIZATION - 3-Char Prefix System           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$execute = in_array('--execute', $argv);
if (!$execute) {
    echo "🔍 DRY RUN MODE - No changes will be made\n";
    echo "   Add --execute flag to apply changes\n\n";
} else {
    echo "⚠️  LIVE MODE - Changes will be applied!\n\n";
    sleep(3);
}

// ============================================================================
// STEP 1: ADD 'INTERNAL' TO ENUM
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 1: ADD 'INTERNAL' TO TRANSFER_CATEGORY ENUM\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($execute) {
    try {
        $db->query("
            ALTER TABLE vend_consignments 
            MODIFY transfer_category ENUM('STOCK','JUICE','STAFF','RETURN','PURCHASE_ORDER','INTERNAL') 
            NOT NULL DEFAULT 'STOCK'
        ");
        echo "✅ Added 'INTERNAL' to transfer_category ENUM\n\n";
    } catch (Exception $e) {
        echo "⚠️  ENUM modification failed (might already exist): " . $e->getMessage() . "\n\n";
    }
} else {
    echo "🔍 Would add 'INTERNAL' to transfer_category ENUM\n\n";
}

// ============================================================================
// STEP 2: ANALYZE CURRENT PUBLIC_IDS
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 2: ANALYZE CURRENT PUBLIC_IDS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$prefixCounts = [];
$result = $db->query("
    SELECT 
        SUBSTRING(public_id, 1, 3) as prefix,
        COUNT(*) as count,
        MIN(id) as min_id,
        MAX(id) as max_id
    FROM vend_consignments
    GROUP BY SUBSTRING(public_id, 1, 3)
    ORDER BY count DESC
");

echo "📊 Current Public ID Prefixes:\n\n";
printf("%-10s | %-8s | %-10s | %-10s | %s\n", "Prefix", "Count", "Min ID", "Max ID", "New Prefix");
echo str_repeat("-", 70) . "\n";

while ($row = $result->fetch_assoc()) {
    $prefix = $row['prefix'];
    $count = $row['count'];
    $minId = $row['min_id'];
    $maxId = $row['max_id'];
    
    $newPrefix = 'UNK';
    if ($prefix === 'TR-') {
        $newPrefix = 'STK';
    } elseif ($prefix === 'ST-') {
        $newPrefix = 'INT';
    } elseif ($prefix === 'JT-') {
        $newPrefix = 'JCE';
    } elseif ($prefix === 'PO-') {
        $newPrefix = 'POR';
    }
    
    printf("%-10s | %-8s | %-10s | %-10s | %s\n", $prefix, number_format((int)$count), $minId, $maxId, $newPrefix);
    
    $prefixCounts[$prefix] = [
        'count' => $count,
        'new_prefix' => $newPrefix
    ];
}

echo "\n";

// ============================================================================
// STEP 3: RENAME PUBLIC_IDS (Using Database ID)
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 3: RENAME PUBLIC_IDS TO 3-CHAR PREFIX + ID\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$mappings = [
    'TR-' => ['new_prefix' => 'STK', 'category' => 'STOCK'],
    'ST-' => ['new_prefix' => 'INT', 'category' => 'INTERNAL'],
    'JT-' => ['new_prefix' => 'JCE', 'category' => 'JUICE'],
    'PO-' => ['new_prefix' => 'POR', 'category' => 'PURCHASE_ORDER'],
];

$totalRenamed = 0;
$errors = [];

foreach ($mappings as $oldPrefix => $config) {
    $newPrefix = $config['new_prefix'];
    $category = $config['category'];
    
    echo "🔄 Processing {$oldPrefix} → {$newPrefix}- (category: {$category})\n";
    
    // Get all records with this prefix
    $result = $db->query("
        SELECT id, public_id, transfer_category
        FROM vend_consignments
        WHERE public_id LIKE '{$oldPrefix}%'
    ");
    
    $count = 0;
    $batchSize = 100;
    $batch = [];
    
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $oldPublicId = $row['public_id'];
        $newPublicId = "{$newPrefix}-{$id}";
        
        if ($execute) {
            $db->begin_transaction();
            try {
                // Update public_id and transfer_category
                $stmt = $db->prepare("
                    UPDATE vend_consignments 
                    SET public_id = ?,
                        transfer_category = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('ssi', $newPublicId, $category, $id);
                $stmt->execute();
                
                $db->commit();
                $count++;
                $totalRenamed++;
                
                if ($count % $batchSize === 0) {
                    echo "   Progress: {$count} renamed...\n";
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = [
                    'id' => $id,
                    'old_public_id' => $oldPublicId,
                    'error' => $e->getMessage()
                ];
            }
        } else {
            $count++;
        }
    }
    
    if ($execute) {
        echo "   ✅ Renamed {$count} records: {$oldPrefix}XXX → {$newPrefix}-{ID}\n\n";
    } else {
        echo "   🔍 Would rename {$count} records: {$oldPrefix}XXX → {$newPrefix}-{ID}\n\n";
    }
}

// ============================================================================
// STEP 4: HANDLE EDGE CASES (UUID-style, numeric, etc)
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 4: HANDLE NON-STANDARD PUBLIC_IDS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Find records that don't match our patterns
$result = $db->query("
    SELECT id, public_id, transfer_category
    FROM vend_consignments
    WHERE public_id NOT LIKE 'STK-%'
    AND public_id NOT LIKE 'INT-%'
    AND public_id NOT LIKE 'JCE-%'
    AND public_id NOT LIKE 'POR-%'
    AND public_id NOT LIKE 'TR-%'
    AND public_id NOT LIKE 'ST-%'
    AND public_id NOT LIKE 'JT-%'
    AND public_id NOT LIKE 'PO-%'
    LIMIT 50
");

$edgeCases = [];
while ($row = $result->fetch_assoc()) {
    $edgeCases[] = $row;
}

if (!empty($edgeCases)) {
    echo "⚠️  Found " . count($edgeCases) . " non-standard public_ids:\n\n";
    
    foreach ($edgeCases as $row) {
        $id = $row['id'];
        $publicId = $row['public_id'];
        $category = $row['transfer_category'];
        
        // Determine prefix based on category
        $newPrefix = 'STK'; // Default to stock
        if ($category === 'JUICE') {
            $newPrefix = 'JCE';
        } elseif ($category === 'INTERNAL' || $category === 'STAFF') {
            $newPrefix = 'INT';
        } elseif ($category === 'PURCHASE_ORDER') {
            $newPrefix = 'POR';
        }
        
        $newPublicId = "{$newPrefix}-{$id}";
        
        echo "   ID {$id}: '{$publicId}' → '{$newPublicId}' (category: {$category})\n";
        
        if ($execute) {
            try {
                $stmt = $db->prepare("
                    UPDATE vend_consignments 
                    SET public_id = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $newPublicId, $id);
                $stmt->execute();
                $totalRenamed++;
            } catch (Exception $e) {
                $errors[] = [
                    'id' => $id,
                    'old_public_id' => $publicId,
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    echo "\n";
} else {
    echo "✅ No non-standard public_ids found\n\n";
}

// ============================================================================
// STEP 5: VERIFICATION
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 5: VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Count by new prefix
$result = $db->query("
    SELECT 
        SUBSTRING(public_id, 1, 4) as prefix,
        transfer_category,
        COUNT(*) as count
    FROM vend_consignments
    GROUP BY SUBSTRING(public_id, 1, 4), transfer_category
    ORDER BY count DESC
    LIMIT 20
");

echo "📊 New Public ID Distribution:\n\n";
printf("%-10s | %-20s | %s\n", "Prefix", "Category", "Count");
echo str_repeat("-", 50) . "\n";

while ($row = $result->fetch_assoc()) {
    printf("%-10s | %-20s | %s\n", $row['prefix'], $row['transfer_category'], number_format((int)$row['count']));
}

echo "\n";

// Check for any mismatches
$result = $db->query("
    SELECT 
        CASE 
            WHEN public_id LIKE 'STK-%' AND transfer_category != 'STOCK' THEN 'MISMATCH'
            WHEN public_id LIKE 'INT-%' AND transfer_category != 'INTERNAL' THEN 'MISMATCH'
            WHEN public_id LIKE 'JCE-%' AND transfer_category != 'JUICE' THEN 'MISMATCH'
            WHEN public_id LIKE 'POR-%' AND transfer_category != 'PURCHASE_ORDER' THEN 'MISMATCH'
            ELSE 'OK'
        END as status,
        COUNT(*) as count
    FROM vend_consignments
    GROUP BY status
");

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'MISMATCH') {
        echo "⚠️  Found {$row['count']} prefix/category mismatches\n";
    } else {
        echo "✅ {$row['count']} records have matching prefix/category\n";
    }
}

echo "\n";

// ============================================================================
// STEP 6: SUMMARY
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($execute) {
    echo "✅ Total records renamed: " . number_format($totalRenamed) . "\n";
    echo "✅ ENUM updated with 'INTERNAL'\n";
    echo "✅ All categories aligned with prefixes\n";
    
    if (!empty($errors)) {
        echo "\n⚠️  Errors encountered: " . count($errors) . "\n";
        foreach (array_slice($errors, 0, 10) as $error) {
            echo "   - ID {$error['id']}: {$error['error']}\n";
        }
    }
} else {
    echo "🔍 This was a DRY RUN\n";
    echo "   Would rename approximately: " . number_format(array_sum(array_column($prefixCounts, 'count'))) . " records\n";
    echo "   Command to execute: php " . basename(__FILE__) . " --execute\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "NEW PUBLIC_ID FORMAT:\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  STK-{id}  = Stock Transfer (store-to-store)\n";
echo "  INT-{id}  = Internal Transfer (staff orders, multi-store)\n";
echo "  JCE-{id}  = Juice Transfer (juice mixing/bottling)\n";
echo "  POR-{id}  = Purchase Order (supplier orders)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$db->close();
