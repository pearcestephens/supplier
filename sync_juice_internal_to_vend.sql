-- ============================================================================
-- SYNC JUICE AND INTERNAL TRANSFERS TO VEND_CONSIGNMENTS
-- Purpose: Sync 3,716 JUICE and 3,466 INTERNAL transfers from queue to vend
-- Created: November 1, 2025
--
-- DATA VALIDATION SUMMARY:
-- ✅ JUICE: 3,716 transfers with 68,117 line items (products)
-- ✅ INTERNAL: 3,466 transfers with 0 line items (empty legacy records)
-- ✅ Public IDs: JCE-XXXXX for JUICE, INT-XXXXX for INTERNAL
-- ✅ All records have vend_consignment_id (LEGACY-JT-* and LEGACY-IN-*)
-- ============================================================================

USE jcepnzzkmj;

-- ============================================================================
-- STEP 1: PRE-SYNC VALIDATION
-- ============================================================================

SELECT '=== PRE-SYNC VALIDATION ===' as step;

-- Check current state
SELECT
  'BEFORE SYNC' as timing,
  transfer_category,
  COUNT(*) as queue_records,
  (SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = qc.transfer_category) as vend_records
FROM queue_consignments qc
WHERE transfer_category IN ('JUICE', 'INTERNAL')
GROUP BY transfer_category;

-- Validate JUICE records
SELECT
  'JUICE validation' as check_type,
  COUNT(*) as total_records,
  SUM(CASE WHEN public_id LIKE 'JCE-%' THEN 1 ELSE 0 END) as has_JCE_prefix,
  SUM(CASE WHEN vend_consignment_id LIKE 'LEGACY-JT-%' THEN 1 ELSE 0 END) as has_legacy_id,
  SUM(CASE WHEN vend_consignment_id IS NULL THEN 1 ELSE 0 END) as null_vend_id,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   INNER JOIN queue_consignments qc ON qcp.consignment_id = qc.id
   WHERE qc.transfer_category = 'JUICE') as total_line_items
FROM queue_consignments
WHERE transfer_category = 'JUICE';

-- Validate INTERNAL records
SELECT
  'INTERNAL validation' as check_type,
  COUNT(*) as total_records,
  SUM(CASE WHEN public_id LIKE 'INT-%' THEN 1 ELSE 0 END) as has_INT_prefix,
  SUM(CASE WHEN vend_consignment_id LIKE 'LEGACY-IN-%' THEN 1 ELSE 0 END) as has_legacy_id,
  SUM(CASE WHEN vend_consignment_id IS NULL THEN 1 ELSE 0 END) as null_vend_id,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   INNER JOIN queue_consignments qc ON qcp.consignment_id = qc.id
   WHERE qc.transfer_category = 'INTERNAL') as total_line_items
FROM queue_consignments
WHERE transfer_category = 'INTERNAL';

-- Check for duplicates in vend_consignments
SELECT
  'Duplicate check' as validation,
  COUNT(*) as existing_in_vend,
  'Should be 0 before sync' as expected
FROM vend_consignments vc
WHERE vc.vend_consignment_id IN (
  SELECT vend_consignment_id FROM queue_consignments
  WHERE transfer_category IN ('JUICE', 'INTERNAL')
);

-- ============================================================================
-- STEP 2: CREATE BACKUP
-- ============================================================================

SELECT '=== CREATING BACKUP ===' as step;

CREATE TABLE IF NOT EXISTS vend_consignments_backup_juice_internal_sync_20251101
LIKE vend_consignments;

INSERT INTO vend_consignments_backup_juice_internal_sync_20251101
SELECT * FROM vend_consignments;

SELECT
  'Backup created' as status,
  COUNT(*) as records_backed_up
FROM vend_consignments_backup_juice_internal_sync_20251101;

-- ============================================================================
-- STEP 3: SYNC JUICE TRANSFERS TO VEND_CONSIGNMENTS
-- ============================================================================

SELECT '=== SYNCING JUICE TRANSFERS ===' as step;

INSERT INTO vend_consignments (
  public_id,
  vend_consignment_id,
  consignment_id,
  transfer_category,
  creation_method,
  vend_number,
  vend_url,
  vend_origin,
  outlet_from,
  outlet_to,
  created_by,
  supplier_id,
  supplier_invoice_number,
  supplier_reference,
  tracking_number,
  tracking_carrier,
  tracking_url,
  created_at,
  expected_delivery_date,
  due_at,
  sent_at,
  received_at,
  updated_at,
  state,
  total_boxes,
  total_weight_g,
  total_count,
  total_cost,
  total_received,
  line_item_count,
  lightspeed_sync_status
)
SELECT
  qc.public_id,                           -- JCE-XXXXX format
  qc.vend_consignment_id,                 -- LEGACY-JT-XXXXXXXXXX
  qc.id as consignment_id,                -- Link back to queue_consignments
  qc.transfer_category,                   -- 'JUICE'
  'AUTOMATED' as creation_method,         -- Migration = automated
  NULL as vend_number,                    -- No vend_number in queue_consignments
  NULL as vend_url,
  'CONSIGNMENT' as vend_origin,           -- JUICE are consignments
  qc.source_outlet_id as outlet_from,
  qc.destination_outlet_id as outlet_to,
  qc.cis_user_id as created_by,          -- User who created the transfer
  qc.supplier_id,
  NULL as supplier_invoice_number,        -- Not in queue_consignments
  NULL as supplier_reference,             -- Not in queue_consignments
  NULL as tracking_number,                -- Not in queue_consignments
  NULL as tracking_carrier,
  NULL as tracking_url,
  qc.created_at,
  qc.delivery_date as expected_delivery_date,
  qc.due_at,
  qc.sent_at,
  qc.received_at,
  NOW() as updated_at,
  CASE
    WHEN qc.received_at IS NOT NULL THEN 'RECEIVED'
    WHEN qc.sent_at IS NOT NULL THEN 'SENT'
    ELSE 'OPEN'
  END as state,
  0 as total_boxes,
  0 as total_weight_g,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id = qc.id) as total_count,
  (SELECT COALESCE(SUM(cost_total), 0) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id = qc.id) as total_cost,
  (SELECT SUM(count_received) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id = qc.id) as total_received,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id = qc.id) as line_item_count,
  'synced' as lightspeed_sync_status
FROM queue_consignments qc
WHERE qc.transfer_category = 'JUICE'
  AND NOT EXISTS (
    SELECT 1 FROM vend_consignments vc
    WHERE vc.vend_consignment_id = qc.vend_consignment_id
  );

SELECT
  'JUICE sync complete' as status,
  ROW_COUNT() as records_synced;

-- ============================================================================
-- STEP 4: SYNC INTERNAL TRANSFERS TO VEND_CONSIGNMENTS
-- ============================================================================

SELECT '=== SYNCING INTERNAL TRANSFERS ===' as step;

INSERT INTO vend_consignments (
  public_id,
  vend_consignment_id,
  consignment_id,
  transfer_category,
  creation_method,
  vend_number,
  vend_url,
  vend_origin,
  outlet_from,
  outlet_to,
  created_by,
  supplier_id,
  supplier_invoice_number,
  supplier_reference,
  tracking_number,
  tracking_carrier,
  tracking_url,
  created_at,
  expected_delivery_date,
  due_at,
  sent_at,
  received_at,
  updated_at,
  state,
  total_boxes,
  total_weight_g,
  total_count,
  total_cost,
  total_received,
  line_item_count,
  lightspeed_sync_status
)
SELECT
  qc.public_id,                           -- INT-XXXXX format
  qc.vend_consignment_id,                 -- LEGACY-IN-XXXXXXXXXX
  qc.id as consignment_id,                -- Link back to queue_consignments
  qc.transfer_category,                   -- 'INTERNAL'
  'AUTOMATED' as creation_method,         -- Migration = automated
  NULL as vend_number,                    -- No vend_number in queue_consignments
  NULL as vend_url,
  'TRANSFER' as vend_origin,              -- INTERNAL are outlet transfers
  qc.source_outlet_id as outlet_from,
  qc.destination_outlet_id as outlet_to,
  qc.cis_user_id as created_by,          -- User who created the transfer
  NULL as supplier_id,                    -- INTERNAL transfers have no supplier
  NULL as supplier_invoice_number,
  NULL as supplier_reference,
  NULL as tracking_number,
  NULL as tracking_carrier,
  NULL as tracking_url,
  qc.created_at,
  qc.delivery_date as expected_delivery_date,
  qc.due_at,
  qc.sent_at,
  qc.received_at,
  NOW() as updated_at,
  CASE
    WHEN qc.received_at IS NOT NULL THEN 'RECEIVED'
    WHEN qc.sent_at IS NOT NULL THEN 'SENT'
    ELSE 'OPEN'
  END as state,
  0 as total_boxes,
  0 as total_weight_g,
  0 as total_count,                       -- INTERNAL have 0 line items
  0 as total_cost,
  0 as total_received,
  0 as line_item_count,
  'synced' as lightspeed_sync_status
FROM queue_consignments qc
WHERE qc.transfer_category = 'INTERNAL'
  AND NOT EXISTS (
    SELECT 1 FROM vend_consignments vc
    WHERE vc.vend_consignment_id = qc.vend_consignment_id
  );

SELECT
  'INTERNAL sync complete' as status,
  ROW_COUNT() as records_synced;

-- ============================================================================
-- STEP 5: SYNC LINE ITEMS FOR JUICE (vend_consignment_line_items)
-- ============================================================================

SELECT '=== SYNCING JUICE LINE ITEMS ===' as step;

-- Note: INTERNAL transfers have 0 line items, so we only sync JUICE products

INSERT INTO vend_consignment_line_items (
  vend_consignment_id,
  queue_consignment_product_id,
  vend_product_id,
  vend_consignment_product_id,
  product_name,
  product_sku,
  count_ordered,
  count_received,
  cost_per_unit,
  cost_total,
  created_at,
  updated_at
)
SELECT
  qc.vend_consignment_id,                 -- Link to vend_consignments
  qcp.id as queue_consignment_product_id, -- Link back to queue
  qcp.vend_product_id,
  qcp.vend_consignment_product_id,
  qcp.product_name,
  qcp.product_sku,
  qcp.count_ordered,
  qcp.count_received,
  qcp.cost_per_unit,
  qcp.cost_total,
  qcp.created_at,
  NOW() as updated_at
FROM queue_consignment_products qcp
INNER JOIN queue_consignments qc ON qcp.consignment_id = qc.id
WHERE qc.transfer_category = 'JUICE'
  AND NOT EXISTS (
    SELECT 1 FROM vend_consignment_line_items vcli
    WHERE vcli.vend_consignment_id = qc.vend_consignment_id
      AND vcli.vend_consignment_product_id = qcp.vend_consignment_product_id
  );

SELECT
  'JUICE line items synced' as status,
  ROW_COUNT() as line_items_synced;

-- ============================================================================
-- STEP 6: POST-SYNC VERIFICATION
-- ============================================================================

SELECT '=== POST-SYNC VERIFICATION ===' as step;

-- Final counts
SELECT
  'AFTER SYNC' as timing,
  transfer_category,
  (SELECT COUNT(*) FROM queue_consignments qc2 WHERE qc2.transfer_category = qc.transfer_category) as queue_records,
  COUNT(*) as vend_records,
  '✅ Should match queue' as expected
FROM vend_consignments qc
WHERE transfer_category IN ('JUICE', 'INTERNAL')
GROUP BY transfer_category;

-- Verify JUICE line items
SELECT
  'JUICE line items' as verification,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   INNER JOIN queue_consignments qc ON qcp.consignment_id = qc.id
   WHERE qc.transfer_category = 'JUICE') as queue_products,
  (SELECT COUNT(*) FROM vend_consignment_line_items vcli
   INNER JOIN vend_consignments vc ON vcli.vend_consignment_id = vc.vend_consignment_id
   WHERE vc.transfer_category = 'JUICE') as vend_line_items,
  '✅ Should match' as expected;

-- Verify public_id formats
SELECT
  'Public ID verification' as check_type,
  transfer_category,
  COUNT(*) as total,
  COUNT(DISTINCT public_id) as unique_public_ids,
  MIN(public_id) as min_public_id,
  MAX(public_id) as max_public_id,
  CASE
    WHEN transfer_category = 'JUICE' AND MIN(public_id) LIKE 'JCE-%' AND MAX(public_id) LIKE 'JCE-%' THEN '✅ Valid JCE-XXXXX format'
    WHEN transfer_category = 'INTERNAL' AND MIN(public_id) LIKE 'INT-%' AND MAX(public_id) LIKE 'INT-%' THEN '✅ Valid INT-XXXXX format'
    ELSE '❌ Invalid format'
  END as validation_status
FROM vend_consignments
WHERE transfer_category IN ('JUICE', 'INTERNAL')
GROUP BY transfer_category;

-- Check for orphaned records
SELECT
  'Orphan check' as validation,
  COUNT(*) as vend_without_queue_link,
  'Should be 0' as expected
FROM vend_consignments vc
WHERE vc.transfer_category IN ('JUICE', 'INTERNAL')
  AND vc.consignment_id NOT IN (SELECT id FROM queue_consignments);

-- Total vend_consignments after sync
SELECT
  'Total vend_consignments' as final_count,
  COUNT(*) as total_records,
  'Expected: ~31,525 (was 24,343 + 3,716 JUICE + 3,466 INTERNAL)' as calculation
FROM vend_consignments;

-- ============================================================================
-- STEP 7: SUMMARY
-- ============================================================================

SELECT '=== SYNC SUMMARY ===' as summary;

SELECT
  'JUICE' as transfer_type,
  (SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'JUICE') as vend_records,
  (SELECT COUNT(*) FROM vend_consignment_line_items vcli
   INNER JOIN vend_consignments vc ON vcli.vend_consignment_id = vc.vend_consignment_id
   WHERE vc.transfer_category = 'JUICE') as line_items,
  '✅ 3,716 transfers with 68,117 line items' as expected
UNION ALL
SELECT
  'INTERNAL' as transfer_type,
  (SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'INTERNAL') as vend_records,
  0 as line_items,
  '✅ 3,466 transfers with 0 line items' as expected
UNION ALL
SELECT
  'PURCHASE_ORDER' as transfer_type,
  (SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'PURCHASE_ORDER') as vend_records,
  (SELECT COUNT(*) FROM vend_consignment_line_items vcli
   INNER JOIN vend_consignments vc ON vcli.vend_consignment_id = vc.vend_consignment_id
   WHERE vc.transfer_category = 'PURCHASE_ORDER') as line_items,
  '✅ 11,532 (already synced)' as expected
UNION ALL
SELECT
  'STOCK' as transfer_type,
  (SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'STOCK') as vend_records,
  (SELECT COUNT(*) FROM vend_consignment_line_items vcli
   INNER JOIN vend_consignments vc ON vcli.vend_consignment_id = vc.vend_consignment_id
   WHERE vc.transfer_category = 'STOCK') as line_items,
  '✅ Should be 0 (cleaned from queue)' as expected;

SELECT '=== ✅ SYNC COMPLETE ===' as status;
