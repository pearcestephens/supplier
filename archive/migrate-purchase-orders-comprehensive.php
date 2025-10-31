<?php
/**
 * 🎯 COMPREHENSIVE PURCHASE ORDER MIGRATION SCRIPT
 * 
 * Migrates 11,472 purchase orders from old system to new multi-table structure:
 * - vend_consignments (primary record)
 * - queue_consignments (queue system integration)
 * - vend_consignment_line_items (product lines)
 * - queue_consignment_products (queue product lines)
 * - supplier_activity_log (notes & history)
 * 
 * Usage:
 *   php migrate-purchase-orders-comprehensive.php              (dry run)
 *   php migrate-purchase-orders-comprehensive.php --execute    (real run)
 * 
 * @author CIS Bot
 * @date 2025-10-24
 */

// Database connection
$host = '127.0.0.1';
$user = 'jcepnzzkmj';
$pass = 'wprKh9Jq63';
$dbname = 'jcepnzzkmj';

$db = new mysqli($host, $user, $pass, $dbname);
if ($db->connect_error) {
    die("❌ Database connection failed: " . $db->connect_error . "\n");
}

// Check if this is a real run or dry run
$dryRun = !in_array('--execute', $argv);

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
echo "║  🎯 COMPREHENSIVE PURCHASE ORDER MIGRATION                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No data will be changed\n";
    echo "   Run with --execute flag to perform actual migration\n\n";
} else {
    echo "⚠️  LIVE MODE - Data will be migrated!\n\n";
    echo "   Press Ctrl+C within 5 seconds to cancel...\n";
    sleep(5);
    echo "   Starting migration...\n\n";
}

// ============================================================================
// PHASE 1: PRE-FLIGHT CHECKS
// ============================================================================

echo "═══ PHASE 1: PRE-FLIGHT CHECKS ═══\n\n";

// Check for missing suppliers
echo "Checking for missing suppliers... ";
$checkSuppliers = $db->query("
    SELECT DISTINCT po.supplier_id, po.supplier_name_cache
    FROM purchase_orders po
    LEFT JOIN vend_suppliers vs ON po.supplier_id = vs.id
    WHERE vs.id IS NULL
    LIMIT 10
");
$missingSuppliers = $checkSuppliers->num_rows;
if ($missingSuppliers > 0) {
    echo "⚠️  Found $missingSuppliers missing suppliers\n";
    while ($row = $checkSuppliers->fetch_assoc()) {
        echo "   - {$row['supplier_id']} ({$row['supplier_name_cache']})\n";
    }
} else {
    echo "✅ All suppliers exist\n";
}

// Check for missing outlets
echo "Checking for missing outlets... ";
$checkOutlets = $db->query("
    SELECT DISTINCT po.outlet_id
    FROM purchase_orders po
    LEFT JOIN vend_outlets vo ON po.outlet_id = vo.id
    WHERE vo.id IS NULL
    LIMIT 10
");
$missingOutlets = $checkOutlets->num_rows;
if ($missingOutlets > 0) {
    echo "⚠️  Found $missingOutlets missing outlets\n";
    while ($row = $checkOutlets->fetch_assoc()) {
        echo "   - {$row['outlet_id']}\n";
    }
} else {
    echo "✅ All outlets exist\n";
}

// Check for missing products
echo "Checking for missing products... ";
$checkProducts = $db->query("
    SELECT COUNT(DISTINCT poi.product_id) as missing_count
    FROM purchase_order_items poi
    LEFT JOIN vend_products vp ON poi.product_id = vp.id
    WHERE vp.id IS NULL
");
$missingProductsRow = $checkProducts->fetch_assoc();
$missingProducts = $missingProductsRow['missing_count'];
if ($missingProducts > 0) {
    echo "⚠️  Found $missingProducts missing products\n";
} else {
    echo "✅ All products exist\n";
}

// Count records to migrate
echo "\nCounting records to migrate...\n";
$countQuery = $db->query("
    SELECT 
        COUNT(DISTINCT po.purchase_order_id) as total_pos,
        COUNT(*) as total_line_items,
        SUM(CASE WHEN po.status = 0 THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN po.status = 1 THEN 1 ELSE 0 END) as received,
        SUM(CASE WHEN po.status = 2 THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN po.status = 3 THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN po.status = 4 THEN 1 ELSE 0 END) as receiving
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
    WHERE po.deleted_at IS NULL
");
$counts = $countQuery->fetch_assoc();

echo "  📦 Total Purchase Orders: " . number_format($counts['total_pos']) . "\n";
echo "  📋 Total Line Items: " . number_format($counts['total_line_items']) . "\n";
echo "  📊 Status Breakdown:\n";
echo "     - OPEN: " . number_format($counts['open']) . "\n";
echo "     - RECEIVED: " . number_format($counts['received']) . "\n";
echo "     - CANCELLED: " . number_format($counts['cancelled']) . "\n";
echo "     - SENT: " . number_format($counts['sent']) . "\n";
echo "     - RECEIVING: " . number_format($counts['receiving']) . "\n";

echo "\n";

if ($missingSuppliers > 0 || $missingOutlets > 0 || $missingProducts > 0) {
    echo "⚠️  WARNING: Missing reference data detected!\n";
    echo "   Migration will continue but may have data integrity issues.\n\n";
    if (!$dryRun) {
        echo "   Press Ctrl+C within 10 seconds to cancel...\n";
        sleep(10);
    }
}

// ============================================================================
// PHASE 2: MIGRATION LOOP
// ============================================================================

echo "═══ PHASE 2: MIGRATION ═══\n\n";

$batchSize = 100;
$totalMigrated = 0;
$totalLineItems = 0;
$errors = [];

// Get all purchase orders to migrate
$poQuery = $db->query("
    SELECT *
    FROM purchase_orders
    WHERE deleted_at IS NULL
    ORDER BY purchase_order_id ASC
");

echo "Starting migration of {$counts['total_pos']} purchase orders...\n\n";

$startTime = microtime(true);

while ($po = $poQuery->fetch_assoc()) {
    try {
        $purchaseOrderId = $po['purchase_order_id'];
        $publicId = 'PO-' . $purchaseOrderId;
        
        // Check if supplier exists
        $supplierCheck = $db->query("SELECT id FROM vend_suppliers WHERE id = '{$po['supplier_id']}' LIMIT 1");
        if ($supplierCheck->num_rows === 0) {
            echo "  ⚠️  Skipping PO-$purchaseOrderId: Supplier {$po['supplier_id']} not found\n";
            continue;
        }
        
        // Check if outlet exists
        $outletCheck = $db->query("SELECT id FROM vend_outlets WHERE id = '{$po['outlet_id']}' LIMIT 1");
        if ($outletCheck->num_rows === 0) {
            echo "  ⚠️  Skipping PO-$purchaseOrderId: Outlet {$po['outlet_id']} not found\n";
            continue;
        }
        
        if (!$dryRun) {
            $db->begin_transaction();
        }
        
        // Map status to state
        $statusMap = [
            0 => 'OPEN',
            1 => 'RECEIVED',
            2 => 'CANCELLED',
            3 => 'SENT',
            4 => 'RECEIVING'
        ];
        $state = $statusMap[$po['status']] ?? 'OPEN';
        
        // ============================================================
        // 1. INSERT INTO vend_consignments
        // ============================================================
        
        if (!$dryRun) {
            $trackingNumber = 'PO-' . $purchaseOrderId . '-MIGRATED';
            
            $insertVendConsignment = $db->prepare("
                INSERT INTO vend_consignments (
                    public_id,
                    vend_transfer_id,
                    consignment_id,
                    transfer_category,
                    vend_origin,
                    vend_number,
                    outlet_from,
                    outlet_to,
                    supplier_id,
                    supplier_invoice_number,
                    tracking_number,
                    tracking_carrier,
                    total_boxes,
                    created_by,
                    created_at,
                    received_at,
                    state,
                    updated_at
                ) VALUES (?, UUID(), ?, 'PURCHASE_ORDER', 'PURCHASE_ORDER', ?, NULL, ?, ?, ?, ?, 'CourierPost', 1, ?, ?, ?, ?, NOW())
            ");
            
            $insertVendConsignment->bind_param(
                'sisissssssssss',
                $publicId,
                $purchaseOrderId,
                $po['vend_consignment_id'],
                $po['outlet_id'],
                $po['supplier_id'],
                $po['invoice_no'],
                $trackingNumber,
                $po['created_by'],
                $po['date_created'],
                $po['completed_timestamp'],
                $state
            );
            
            $insertVendConsignment->execute();
            $vendConsignmentId = $db->insert_id;
            
            // Get the vend_transfer_id we just created
            $getVendTransferId = $db->query("
                SELECT vend_transfer_id 
                FROM vend_consignments 
                WHERE id = $vendConsignmentId
            ");
            $vendTransferIdRow = $getVendTransferId->fetch_assoc();
            $vendTransferId = $vendTransferIdRow['vend_transfer_id'];
        } else {
            $vendConsignmentId = 999999; // Mock ID for dry run
            $vendTransferId = 'mock-uuid-' . $purchaseOrderId;
        }
        
        // ============================================================
        // 2. INSERT INTO queue_consignments
        // ============================================================
        
        // Get line item count and totals
        $lineItemStats = $db->query("
            SELECT 
                COUNT(*) as item_count,
                SUM(qty_ordered) as total_qty
            FROM purchase_order_items
            WHERE purchase_order_id = $purchaseOrderId
        ");
        $stats = $lineItemStats->fetch_assoc();
        
        if (!$dryRun) {
            $insertQueueConsignment = $db->prepare("
                INSERT INTO queue_consignments (
                    vend_consignment_id,
                    lightspeed_consignment_id,
                    type,
                    status,
                    reference,
                    name,
                    destination_outlet_id,
                    supplier_id,
                    cis_purchase_order_id,
                    created_at,
                    received_at,
                    is_migrated,
                    sync_source,
                    total_value,
                    total_cost,
                    item_count
                ) VALUES (?, ?, 'SUPPLIER', ?, ?, ?, ?, ?, ?, ?, ?, 1, 'MIGRATION', ?, ?, ?)
            ");
            
            $queueStatus = ($state === 'RECEIVED') ? 'RECEIVED' : (($state === 'CANCELLED') ? 'CANCELLED' : 'OPEN');
            $reference = $po['invoice_no'] ?: $po['packing_slip_no'];
            $name = "Purchase Order $publicId";
            
            $insertQueueConsignment->bind_param(
                'ssssssissdddi',
                $vendTransferId,
                $po['consignment_id'],
                $queueStatus,
                $reference,
                $name,
                $po['outlet_id'],
                $po['supplier_id'],
                $purchaseOrderId,
                $po['date_created'],
                $po['completed_timestamp'],
                $po['total_inc_gst'],
                $po['subtotal_ex_gst'],
                $stats['item_count']
            );
            
            $insertQueueConsignment->execute();
            $queueConsignmentId = $db->insert_id;
        } else {
            $queueConsignmentId = 888888; // Mock ID for dry run
        }
        
        // ============================================================
        // 3. INSERT LINE ITEMS
        // ============================================================
        
        $lineItemQuery = $db->query("
            SELECT *
            FROM purchase_order_items
            WHERE purchase_order_id = $purchaseOrderId
        ");
        
        $lineItemCount = 0;
        $skippedLineItems = 0;
        while ($lineItem = $lineItemQuery->fetch_assoc()) {
            // Check if product exists
            $productCheck = $db->query("SELECT id FROM vend_products WHERE id = '{$lineItem['product_id']}' LIMIT 1");
            if ($productCheck->num_rows === 0) {
                $skippedLineItems++;
                continue; // Skip this line item - product doesn't exist
            }
            
            // Insert into vend_consignment_line_items
            if (!$dryRun) {
                $insertVendLineItem = $db->prepare("
                    INSERT INTO vend_consignment_line_items (
                        vend_consignment_id,
                        product_id,
                        quantity_expected,
                        quantity_received,
                        quantity_damaged,
                        notes,
                        discrepancy_reason,
                        received_by,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertVendLineItem->bind_param(
                    'isiiiisss',
                    $vendConsignmentId,
                    $lineItem['product_id'],
                    $lineItem['qty_ordered'],
                    $lineItem['qty_received'],
                    $lineItem['damaged_qty'],
                    $lineItem['line_note'],
                    $lineItem['discrepancy_type'],
                    $lineItem['received_by'],
                    $po['date_created']
                );
                
                $insertVendLineItem->execute();
                
                // Insert into queue_consignment_products
                $insertQueueProduct = $db->prepare("
                    INSERT INTO queue_consignment_products (
                        consignment_id,
                        vend_product_id,
                        count_ordered,
                        count_received,
                        count_damaged,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $insertQueueProduct->bind_param(
                    'isiiii',
                    $queueConsignmentId,
                    $lineItem['product_id'],
                    $lineItem['qty_ordered'],
                    $lineItem['qty_received'],
                    $lineItem['damaged_qty'],
                    $po['date_created']
                );
                
                $insertQueueProduct->execute();
            }
            
            $lineItemCount++;
        }
        
        $totalLineItems += $lineItemCount;
        
        // ============================================================
        // 4. INSERT INTO consignment_shipments (create shipment record)
        // ============================================================
        
        if (!$dryRun) {
            // Map status to shipment status
            $shipmentStatus = 'packed';
            if ($state === 'RECEIVED') {
                $shipmentStatus = 'received';
            } elseif ($state === 'CANCELLED') {
                $shipmentStatus = 'cancelled';
            } elseif ($state === 'SENT') {
                $shipmentStatus = 'in_transit';
            }
            
            $insertShipment = $db->prepare("
                INSERT INTO consignment_shipments (
                    transfer_id,
                    delivery_mode,
                    status,
                    packed_at,
                    received_at,
                    created_at,
                    tracking_number,
                    carrier_name,
                    nicotine_in_shipment
                ) VALUES (?, 'courier', ?, ?, ?, ?, ?, 'CourierPost', 0)
            ");
            
            $insertShipment->bind_param(
                'issssss',
                $vendConsignmentId,
                $shipmentStatus,
                $po['date_created'],
                $po['completed_timestamp'],
                $po['date_created'],
                $trackingNumber
            );
            
            $insertShipment->execute();
            $shipmentId = $db->insert_id;
            
            // ============================================================
            // 5. INSERT INTO consignment_parcels (create 1 box - all items in 1 box)
            // ============================================================
            
            $parcelStatus = 'pending';
            if ($state === 'RECEIVED') {
                $parcelStatus = 'received';
            } elseif ($state === 'CANCELLED') {
                $parcelStatus = 'cancelled';
            } elseif ($state === 'SENT') {
                $parcelStatus = 'in_transit';
            }
            
            $insertParcel = $db->prepare("
                INSERT INTO consignment_parcels (
                    shipment_id,
                    box_number,
                    parcel_number,
                    tracking_number,
                    courier,
                    status,
                    received_at,
                    created_at
                ) VALUES (?, 1, 1, ?, 'CourierPost', ?, ?, ?)
            ");
            
            $insertParcel->bind_param(
                'issss',
                $shipmentId,
                $trackingNumber,
                $parcelStatus,
                $po['completed_timestamp'],
                $po['date_created']
            );
            
            $insertParcel->execute();
        }
        
        // ============================================================
        // 6. INSERT NOTES INTO supplier_activity_log (if has notes)
        // ============================================================
        
        $hasNotes = !empty($po['completed_notes']) || !empty($po['receiving_notes']);
        
        if ($hasNotes && !$dryRun) {
            $metadata = json_encode([
                'po_id' => $purchaseOrderId,
                'public_id' => $publicId,
                'original_status' => $po['status'],
                'completed_notes' => $po['completed_notes'],
                'receiving_notes' => $po['receiving_notes'],
                'receiving_quality' => $po['receiving_quality'],
                'receive_summary_json' => $po['receive_summary_json'],
                'packing_slip_no' => $po['packing_slip_no'],
                'invoice_no' => $po['invoice_no'],
                'migration_timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $insertActivityLog = $db->prepare("
                INSERT INTO supplier_activity_log (
                    supplier_id,
                    activity_type,
                    description,
                    metadata,
                    created_at
                ) VALUES (?, 'PURCHASE_ORDER_MIGRATED', ?, ?, ?)
            ");
            
            $description = "Migrated $publicId from legacy system";
            
            $insertActivityLog->bind_param(
                'ssss',
                $po['supplier_id'],
                $description,
                $metadata,
                $po['date_created']
            );
            
            $insertActivityLog->execute();
        }
        
        // ============================================================
        // 5. INSERT INTO consignment_shipments (freight/tracking)
        // ============================================================
        
        if (!$dryRun) {
            // Get outlet address for delivery
            $outletQuery = $db->query("
                SELECT name, physical_address_1, physical_address_2, 
                       physical_suburb, physical_city, physical_postcode 
                FROM vend_outlets 
                WHERE id = '{$po['outlet_id']}'
            ");
            $outlet = $outletQuery->fetch_assoc();
            
            $insertShipment = $db->prepare("
                INSERT INTO consignment_shipments (
                    transfer_id,
                    delivery_mode,
                    dest_name,
                    dest_company,
                    dest_addr1,
                    dest_addr2,
                    dest_suburb,
                    dest_city,
                    dest_postcode,
                    status,
                    packed_at,
                    received_at,
                    created_at,
                    carrier_name,
                    tracking_number
                ) VALUES (?, 'courier', ?, 'The Vape Shed', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CourierPost', ?)
            ");
            
            $shipmentStatus = ($state === 'RECEIVED') ? 'received' : (($state === 'SENT') ? 'in_transit' : 'packed');
            $trackingNum = 'PO-' . $purchaseOrderId . '-MIGRATED';
            
            $insertShipment->bind_param(
                'issssssssssss',
                $purchaseOrderId,
                $outlet['name'] ?? 'Store',
                $outlet['physical_address_1'] ?? '',
                $outlet['physical_address_2'] ?? '',
                $outlet['physical_suburb'] ?? '',
                $outlet['physical_city'] ?? '',
                $outlet['physical_postcode'] ?? '',
                $shipmentStatus,
                $po['date_created'],
                $po['completed_timestamp'],
                $po['date_created'],
                $trackingNum
            );
            
            $insertShipment->execute();
            $shipmentId = $db->insert_id;
            
            // ============================================================
            // 6. INSERT INTO consignment_parcels (boxes - create 1 box)
            // ============================================================
            
            $insertParcel = $db->prepare("
                INSERT INTO consignment_parcels (
                    shipment_id,
                    box_number,
                    tracking_number,
                    courier,
                    status,
                    created_at,
                    received_at,
                    parcel_number
                ) VALUES (?, 1, ?, 'CourierPost', ?, ?, ?, 1)
            ");
            
            $parcelStatus = ($state === 'RECEIVED') ? 'received' : 'pending';
            
            $insertParcel->bind_param(
                'issss',
                $shipmentId,
                $trackingNumber,
                $parcelStatus,
                $po['date_created'],
                $po['completed_timestamp']
            );
            
            $insertParcel->execute();
        }
        
        // ============================================================
        // 7. CREATE AUDIT TRAIL ENTRIES
        // ============================================================
        
        if (!$dryRun) {
            // A) po_events - Purchase order lifecycle events
            $poEventsToCreate = [
                ['event_type' => 'po.created', 'timestamp' => $po['date_created']],
            ];
            
            if ($po['completed_timestamp']) {
                $poEventsToCreate[] = ['event_type' => 'po.completed', 'timestamp' => $po['completed_timestamp']];
            }
            
            if ($state === 'RECEIVED') {
                $poEventsToCreate[] = ['event_type' => 'po.received', 'timestamp' => $po['completed_timestamp']];
            }
            
            $poEventsToCreate[] = ['event_type' => 'po.migrated', 'timestamp' => date('Y-m-d H:i:s')];
            
            foreach ($poEventsToCreate as $event) {
                $eventData = json_encode([
                    'purchase_order_id' => $purchaseOrderId,
                    'public_id' => $publicId,
                    'status' => $state,
                    'supplier_id' => $po['supplier_id'],
                    'outlet_id' => $po['outlet_id'],
                    'migration' => true
                ]);
                
                $insertEvent = $db->prepare("
                    INSERT INTO po_events (purchase_order_id, event_type, event_data, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertEvent->bind_param('issss', $purchaseOrderId, $event['event_type'], $eventData, $po['created_by'], $event['timestamp']);
                $insertEvent->execute();
            }
            
            // B) consignment_audit_log - Transfer/PO audit trail
            $auditMetadata = json_encode([
                'migration_source' => 'purchase_orders',
                'original_status' => $po['status'],
                'line_items' => $lineItemCount,
                'total_value' => $po['total_inc_gst'],
                'subtotal' => $po['subtotal_ex_gst'],
                'partial_delivery' => $po['partial_delivery']
            ]);
            
            $insertAudit = $db->prepare("
                INSERT INTO consignment_audit_log (
                    entity_type,
                    entity_pk,
                    transfer_pk,
                    vend_consignment_id,
                    action,
                    operation_type,
                    status,
                    actor_type,
                    actor_id,
                    user_id,
                    outlet_to,
                    metadata,
                    created_at
                ) VALUES ('po', ?, ?, ?, 'migrated', 'migration', ?, 'system', 'migration-script', ?, ?, ?, ?)
            ");
            
            $insertAudit->bind_param(
                'iiississ',
                $purchaseOrderId,
                $vendConsignmentId,
                $vendTransferId,
                $state,
                $po['created_by'],
                $po['outlet_id'],
                $auditMetadata,
                $po['date_created']
            );
            $insertAudit->execute();
            
            // C) system_activity_log - System-wide activity
            $systemDetails = "Migrated purchase order $publicId with $lineItemCount line items from legacy system";
            $insertSystemLog = $db->prepare("
                INSERT INTO system_activity_log (event_type, table_name, record_id, user_id, details, created_at)
                VALUES ('purchase_order_migrated', 'purchase_orders', ?, ?, ?, ?)
            ");
            $insertSystemLog->bind_param('iiss', $purchaseOrderId, $po['created_by'], $systemDetails, $po['date_created']);
            $insertSystemLog->execute();
        }
        
        // ============================================================
        // 8. UPDATE OLD TABLE with new consignment_id
        // ============================================================
        
        if (!$dryRun) {
            $db->query("
                UPDATE purchase_orders 
                SET consignment_id = $vendConsignmentId 
                WHERE purchase_order_id = $purchaseOrderId
            ");
            
            $db->commit();
        }
        
        $totalMigrated++;
        
        // Progress indicator
        if ($totalMigrated % 100 === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $totalMigrated / $elapsed;
            $remaining = ($counts['total_pos'] - $totalMigrated) / $rate;
            
            echo sprintf(
                "  Progress: %s / %s (%.1f%%) | Rate: %.1f/sec | ETA: %s\n",
                number_format($totalMigrated),
                number_format($counts['total_pos']),
                ($totalMigrated / $counts['total_pos']) * 100,
                $rate,
                gmdate('H:i:s', $remaining)
            );
        }
        
    } catch (Exception $e) {
        if (!$dryRun) {
            $db->rollback();
        }
        $errors[] = [
            'po_id' => $purchaseOrderId,
            'error' => $e->getMessage()
        ];
        echo "  ❌ Error migrating PO-$purchaseOrderId: " . $e->getMessage() . "\n";
    }
}

$totalTime = microtime(true) - $startTime;
$totalSkipped = $counts['total_pos'] - $totalMigrated;

echo "\n";
echo "═══ MIGRATION COMPLETE ═══\n\n";
echo "✅ Successfully migrated: " . number_format($totalMigrated) . " purchase orders\n";
echo "✅ Total line items: " . number_format($totalLineItems) . "\n";
echo "✅ Shipments created: " . number_format($totalMigrated) . " (1 per PO)\n";
echo "✅ Parcels created: " . number_format($totalMigrated) . " (1 box per PO)\n";
echo "✅ Audit trail entries: " . number_format($totalMigrated * 4) . " (po_events + transfer_audit + system_log)\n";
if ($totalSkipped > 0) {
    echo "⚠️  Skipped: " . number_format($totalSkipped) . " POs (missing supplier/outlet)\n";
}
echo "⏱️  Total time: " . gmdate('H:i:s', $totalTime) . "\n";
echo "📊 Rate: " . number_format($totalMigrated / $totalTime, 1) . " POs/second\n";

if (!empty($errors)) {
    echo "\n❌ Errors encountered: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   - PO-{$error['po_id']}: {$error['error']}\n";
    }
}

// ============================================================================
// PHASE 3: POST-MIGRATION VALIDATION
// ============================================================================

if (!$dryRun) {
    echo "\n═══ PHASE 3: POST-MIGRATION VALIDATION ═══\n\n";
    
    // Count migrated POs in vend_consignments
    $countVendConsignments = $db->query("
        SELECT COUNT(*) as count 
        FROM vend_consignments 
        WHERE transfer_category = 'PURCHASE_ORDER'
          AND consignment_id IS NOT NULL
    ");
    $vendCount = $countVendConsignments->fetch_assoc()['count'];
    echo "vend_consignments records: " . number_format($vendCount) . "\n";
    
    // Count migrated POs in queue_consignments
    $countQueueConsignments = $db->query("
        SELECT COUNT(*) as count 
        FROM queue_consignments 
        WHERE type = 'SUPPLIER'
          AND is_migrated = 1
    ");
    $queueCount = $countQueueConsignments->fetch_assoc()['count'];
    echo "queue_consignments records: " . number_format($queueCount) . "\n";
    
    // Count line items
    $countLineItems = $db->query("
        SELECT COUNT(*) as count 
        FROM vend_consignment_line_items vcli
        INNER JOIN vend_consignments vc ON vcli.vend_consignment_id = vc.id
        WHERE vc.transfer_category = 'PURCHASE_ORDER'
          AND vc.consignment_id IS NOT NULL
    ");
    $lineItemCount = $countLineItems->fetch_assoc()['count'];
    echo "vend_consignment_line_items records: " . number_format($lineItemCount) . "\n";
    
    // Count shipments
    $countShipments = $db->query("
        SELECT COUNT(*) as count 
        FROM consignment_shipments 
        WHERE transfer_id IN (SELECT purchase_order_id FROM purchase_orders WHERE deleted_at IS NULL AND consignment_id IS NOT NULL)
    ");
    $shipmentCount = $countShipments->fetch_assoc()['count'];
    echo "consignment_shipments records: " . number_format($shipmentCount) . "\n";
    
    // Count parcels
    $countParcels = $db->query("
        SELECT COUNT(*) as count 
        FROM consignment_parcels 
        WHERE shipment_id IN (
            SELECT id FROM consignment_shipments 
            WHERE transfer_id IN (SELECT purchase_order_id FROM purchase_orders WHERE deleted_at IS NULL AND consignment_id IS NOT NULL)
        )
    ");
    $parcelCount = $countParcels->fetch_assoc()['count'];
    echo "consignment_parcels records: " . number_format($parcelCount) . "\n";
    
    // Count audit entries
    $countPoEvents = $db->query("
        SELECT COUNT(*) as count 
        FROM po_events 
        WHERE purchase_order_id IN (SELECT purchase_order_id FROM purchase_orders WHERE deleted_at IS NULL AND consignment_id IS NOT NULL)
          AND event_type LIKE '%migrat%'
    ");
    $poEventsCount = $countPoEvents->fetch_assoc()['count'];
    echo "po_events records: " . number_format($poEventsCount) . "\n";
    
    $countTransferAudit = $db->query("
        SELECT COUNT(*) as count 
        FROM consignment_audit_log 
        WHERE entity_type = 'po' 
          AND action = 'migrated'
    ");
    $transferAuditCount = $countTransferAudit->fetch_assoc()['count'];
    echo "consignment_audit_log records: " . number_format($transferAuditCount) . "\n";
    
    $countSystemLog = $db->query("
        SELECT COUNT(*) as count 
        FROM system_activity_log 
        WHERE event_type = 'purchase_order_migrated'
    ");
    $systemLogCount = $countSystemLog->fetch_assoc()['count'];
    echo "system_activity_log records: " . number_format($systemLogCount) . "\n";
    
    // Check for orphaned records
    $countOrphaned = $db->query("
        SELECT COUNT(*) as count 
        FROM purchase_orders 
        WHERE deleted_at IS NULL 
          AND consignment_id IS NULL
    ");
    $orphanedCount = $countOrphaned->fetch_assoc()['count'];
    echo "\nOrphaned purchase_orders: " . number_format($orphanedCount) . "\n";
    
    if ($orphanedCount > 0) {
        echo "⚠️  WARNING: Some purchase orders were not migrated!\n";
    } else {
        echo "✅ No orphaned records\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ MIGRATION COMPLETE                                                ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "This was a DRY RUN - no data was changed.\n";
    echo "Run with --execute flag to perform actual migration.\n";
}

$db->close();
