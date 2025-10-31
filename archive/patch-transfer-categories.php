<?php
/**
 * PATCH TRANSFER CATEGORIES - Fix Juice & Internal Transfers
 * 
 * This script:
 * 1. Updates transfer_category for JCE- (JUICE) and INT- (INTERNAL) transfers
 * 2. Adds missing queue_consignments entries (marked as COMPLETED)
 * 3. Adds missing consignment_shipments (with proper timestamps)
 * 4. Adds missing consignment_parcels (1 box per transfer)
 * 5. Matches outlet IDs and timestamps from vend_consignments
 * 
 * Issues Found:
 * - 3,716 JCE- transfers marked as STOCK (should be JUICE)
 *   - 542 missing from queue_consignments
 *   - 3,716 missing shipments
 *   - 3,716 missing parcels
 * 
 * - 3,466 INT- transfers marked as STOCK (should be INTERNAL)
 *   - 3,466 missing from queue_consignments (ALL!)
 *   - 3,466 missing shipments (ALL!)
 *   - 3,466 missing parcels (ALL!)
 * 
 * Total records to create: ~4,008 queue + ~7,182 shipments + ~7,182 parcels
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(600); // 10 minutes

// Database connection
$db = new mysqli('127.0.0.1', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if ($db->connect_error) {
    die("❌ Connection failed: " . $db->connect_error);
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  TRANSFER CATEGORY PATCHER - Juice & Internal Transfers     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check if --execute flag is provided
$execute = in_array('--execute', $argv);
if (!$execute) {
    echo "🔍 DRY RUN MODE - No changes will be made\n";
    echo "   Add --execute flag to apply changes\n\n";
} else {
    echo "⚠️  LIVE MODE - Changes will be applied!\n\n";
    sleep(2);
}

// ============================================================================
// STEP 1: PRE-FLIGHT CHECKS
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 1: PRE-FLIGHT CHECKS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Count JCE- transfers
$result = $db->query("
    SELECT COUNT(*) as count 
    FROM vend_consignments 
    WHERE public_id LIKE 'JCE-%'
");
$jceCount = $result->fetch_assoc()['count'];
echo "📦 Juice Transfers (JCE-): {$jceCount}\n";

// Count INT- transfers
$result = $db->query("
    SELECT COUNT(*) as count 
    FROM vend_consignments 
    WHERE public_id LIKE 'INT-%'
");
$intCount = $result->fetch_assoc()['count'];
echo "📦 Internal Transfers (INT-): {$intCount}\n\n";

// Check missing queue entries
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT qc.id) as missing
    FROM vend_consignments vc
    LEFT JOIN queue_consignments qc ON qc.vend_consignment_id = vc.vend_transfer_id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
while ($row = $result->fetch_assoc()) {
    echo "⚠️  {$row['prefix']} missing from queue: {$row['missing']}\n";
}

// Check missing shipments
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT ts.id) as missing
    FROM vend_consignments vc
    LEFT JOIN consignment_shipments ts ON ts.transfer_id = vc.id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
while ($row = $result->fetch_assoc()) {
    echo "⚠️  {$row['prefix']} missing shipments: {$row['missing']}\n";
}

// Check missing parcels
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT tp.id) as missing
    FROM vend_consignments vc
    LEFT JOIN consignment_shipments ts ON ts.transfer_id = vc.id
    LEFT JOIN consignment_parcels tp ON tp.shipment_id = ts.id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
while ($row = $result->fetch_assoc()) {
    echo "⚠️  {$row['prefix']} missing parcels: {$row['missing']}\n";
}

echo "\n";

// ============================================================================
// STEP 2: UPDATE TRANSFER CATEGORIES
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 2: UPDATE TRANSFER CATEGORIES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($execute) {
    // Update JCE- to JUICE
    $db->query("
        UPDATE vend_consignments 
        SET transfer_category = 'JUICE' 
        WHERE public_id LIKE 'JCE-%' 
        AND transfer_category != 'JUICE'
    ");
    $jceUpdated = $db->affected_rows;
    echo "✅ Updated {$jceUpdated} JCE- transfers to category JUICE\n";
    
    // Update INT- to INTERNAL
    $db->query("
        UPDATE vend_consignments 
        SET transfer_category = 'INTERNAL' 
        WHERE public_id LIKE 'INT-%' 
        AND transfer_category != 'INTERNAL'
    ");
    $intUpdated = $db->affected_rows;
    echo "✅ Updated {$intUpdated} INT- transfers to category INTERNAL\n\n";
} else {
    echo "🔍 Would update {$jceCount} JCE- transfers to JUICE\n";
    echo "🔍 Would update {$intCount} INT- transfers to INTERNAL\n\n";
}

// ============================================================================
// STEP 3: ADD MISSING QUEUE_CONSIGNMENTS
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 3: ADD MISSING QUEUE_CONSIGNMENTS (COMPLETED)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get transfers missing from queue
$result = $db->query("
    SELECT 
        vc.id,
        vc.vend_transfer_id,
        vc.public_id,
        vc.transfer_category,
        vc.supplier_id,
        vc.outlet_to as destination_outlet_id,
        vc.created_at,
        vc.received_at,
        vc.state,
        COUNT(vcli.id) as item_count,
        COALESCE(SUM(vcli.total_cost), 0) as total_cost,
        COALESCE(SUM(vcli.total_cost), 0) as total_value
    FROM vend_consignments vc
    LEFT JOIN queue_consignments qc ON qc.vend_consignment_id = vc.vend_transfer_id
    LEFT JOIN vend_consignment_line_items vcli ON vcli.transfer_id = vc.id
    WHERE (vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%')
    AND qc.id IS NULL
    GROUP BY vc.id
");

$queueInserted = 0;
$queueErrors = [];

while ($transfer = $result->fetch_assoc()) {
    if ($execute) {
        $db->begin_transaction();
        try {
            // Type: Both JCE- and INT- are outlet-to-outlet transfers
            $type = 'OUTLET';
            
            $stmt = $db->prepare("
                INSERT INTO queue_consignments (
                    vend_consignment_id,
                    type,
                    status,
                    supplier_id,
                    destination_outlet_id,
                    is_migrated,
                    sync_source,
                    total_value,
                    total_cost,
                    item_count,
                    created_at,
                    updated_at
                ) VALUES (?, ?, 'RECEIVED', ?, ?, 1, 'MIGRATION', ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                'ssssddiss',
                $transfer['vend_transfer_id'],
                $type,
                $transfer['supplier_id'],
                $transfer['destination_outlet_id'],
                $transfer['total_value'],
                $transfer['total_cost'],
                $transfer['item_count'],
                $transfer['created_at'],
                $transfer['received_at']
            );
            
            $stmt->execute();
            $queueInserted++;
            $db->commit();
            
            if ($queueInserted % 100 === 0) {
                echo "   Progress: {$queueInserted} queue entries created...\n";
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $queueErrors[] = [
                'id' => $transfer['id'],
                'public_id' => $transfer['public_id'],
                'error' => $e->getMessage()
            ];
        }
    }
}

if ($execute) {
    echo "✅ Added {$queueInserted} entries to queue_consignments (status: COMPLETED)\n";
    if (!empty($queueErrors)) {
        echo "⚠️  {" . count($queueErrors) . "} errors occurred\n";
    }
} else {
    $missingQueue = $result->num_rows;
    echo "🔍 Would add {$missingQueue} entries to queue_consignments\n";
}

echo "\n";

// ============================================================================
// STEP 4: ADD MISSING TRANSFER_SHIPMENTS
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 4: ADD MISSING TRANSFER_SHIPMENTS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Get transfers missing shipments
$result = $db->query("
    SELECT 
        vc.id,
        vc.public_id,
        vc.tracking_number,
        vc.tracking_carrier,
        vc.created_at,
        vc.received_at,
        vc.state
    FROM vend_consignments vc
    LEFT JOIN consignment_shipments ts ON ts.transfer_id = vc.id
    WHERE (vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%')
    AND ts.id IS NULL
");

$shipmentsInserted = 0;
$shipmentErrors = [];

while ($transfer = $result->fetch_assoc()) {
    if ($execute) {
        $db->begin_transaction();
        try {
            // Determine status from state
            $shipmentStatus = 'received'; // Default
            if ($transfer['state'] === 'SENT' || $transfer['state'] === 'OPEN') {
                $shipmentStatus = 'in_transit';
            } elseif ($transfer['state'] === 'CANCELLED') {
                $shipmentStatus = 'cancelled';
            }
            
            // Determine delivery mode
            $deliveryMode = (substr($transfer['public_id'], 0, 4) === 'INT-') ? 'internal_drive' : 'courier';
            
            // Use existing tracking or generate new
            $trackingNumber = $transfer['tracking_number'] ?: $transfer['public_id'] . '-MIGRATED';
            $carrier = $transfer['tracking_carrier'] ?: 'CourierPost';
            
            $stmt = $db->prepare("
                INSERT INTO consignment_shipments (
                    transfer_id,
                    delivery_mode,
                    status,
                    tracking_number,
                    carrier_name,
                    packed_at,
                    received_at,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                'isssssss',
                $transfer['id'],
                $deliveryMode,
                $shipmentStatus,
                $trackingNumber,
                $carrier,
                $transfer['created_at'],
                $transfer['received_at'],
                $transfer['created_at']
            );
            
            $stmt->execute();
            $shipmentId = $db->insert_id;
            $shipmentsInserted++;
            
            // ============================================================================
            // STEP 5: ADD TRANSFER_PARCEL FOR THIS SHIPMENT
            // ============================================================================
            
            $parcelStatus = ($shipmentStatus === 'received') ? 'received' : 'pending';
            
            $stmt2 = $db->prepare("
                INSERT INTO consignment_parcels (
                    shipment_id,
                    box_number,
                    tracking_number,
                    courier,
                    status,
                    parcel_number,
                    received_at,
                    created_at
                ) VALUES (?, 1, ?, ?, ?, 1, ?, ?)
            ");
            
            $stmt2->bind_param(
                'isssss',
                $shipmentId,
                $trackingNumber,
                $carrier,
                $parcelStatus,
                $transfer['received_at'],
                $transfer['created_at']
            );
            
            $stmt2->execute();
            
            $db->commit();
            
            if ($shipmentsInserted % 100 === 0) {
                echo "   Progress: {$shipmentsInserted} shipments + parcels created...\n";
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $shipmentErrors[] = [
                'id' => $transfer['id'],
                'public_id' => $transfer['public_id'],
                'error' => $e->getMessage()
            ];
        }
    }
}

if ($execute) {
    echo "✅ Added {$shipmentsInserted} shipments\n";
    echo "✅ Added {$shipmentsInserted} parcels (1 box per transfer)\n";
    if (!empty($shipmentErrors)) {
        echo "⚠️  {" . count($shipmentErrors) . "} errors occurred\n";
    }
} else {
    $missingShipments = $result->num_rows;
    echo "🔍 Would add {$missingShipments} shipments\n";
    echo "🔍 Would add {$missingShipments} parcels (1 box per transfer)\n";
}

echo "\n";

// ============================================================================
// STEP 6: POST-PATCH VALIDATION
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 6: POST-PATCH VALIDATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Count by category
$result = $db->query("
    SELECT transfer_category, COUNT(*) as count 
    FROM vend_consignments 
    GROUP BY transfer_category
");
echo "📊 Transfers by Category:\n";
while ($row = $result->fetch_assoc()) {
    echo "   {$row['transfer_category']}: {$row['count']}\n";
}
echo "\n";

// Check queue coverage
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) as total,
        COUNT(DISTINCT qc.id) as in_queue,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT qc.id) as missing
    FROM vend_consignments vc
    LEFT JOIN queue_consignments qc ON qc.vend_consignment_id = vc.vend_transfer_id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
echo "📊 Queue Coverage:\n";
while ($row = $result->fetch_assoc()) {
    $pct = ($row['total'] > 0) ? round(($row['in_queue'] / $row['total']) * 100, 1) : 0;
    echo "   {$row['prefix']}: {$row['in_queue']} / {$row['total']} ({$pct}%) | Missing: {$row['missing']}\n";
}
echo "\n";

// Check shipment coverage
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) as total,
        COUNT(DISTINCT ts.id) as has_shipments,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT ts.id) as missing
    FROM vend_consignments vc
    LEFT JOIN consignment_shipments ts ON ts.transfer_id = vc.id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
echo "📊 Shipment Coverage:\n";
while ($row = $result->fetch_assoc()) {
    $pct = ($row['total'] > 0) ? round(($row['has_shipments'] / $row['total']) * 100, 1) : 0;
    echo "   {$row['prefix']}: {$row['has_shipments']} / {$row['total']} ({$pct}%) | Missing: {$row['missing']}\n";
}
echo "\n";

// Check parcel coverage
$result = $db->query("
    SELECT 
        SUBSTRING(vc.public_id, 1, 4) as prefix,
        COUNT(DISTINCT vc.id) as total,
        COUNT(DISTINCT tp.id) as has_parcels,
        COUNT(DISTINCT vc.id) - COUNT(DISTINCT tp.id) as missing
    FROM vend_consignments vc
    LEFT JOIN consignment_shipments ts ON ts.transfer_id = vc.id
    LEFT JOIN consignment_parcels tp ON tp.shipment_id = ts.id
    WHERE vc.public_id LIKE 'JCE-%' OR vc.public_id LIKE 'INT-%'
    GROUP BY SUBSTRING(vc.public_id, 1, 4)
");
echo "📊 Parcel Coverage:\n";
while ($row = $result->fetch_assoc()) {
    $pct = ($row['total'] > 0) ? round(($row['has_parcels'] / $row['total']) * 100, 1) : 0;
    echo "   {$row['prefix']}: {$row['has_parcels']} / {$row['total']} ({$pct}%) | Missing: {$row['missing']}\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "PATCH COMPLETE!\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (!$execute) {
    echo "⚠️  This was a DRY RUN. Add --execute to apply changes.\n";
    echo "   Command: php patch-transfer-categories.php --execute\n\n";
}

$db->close();
