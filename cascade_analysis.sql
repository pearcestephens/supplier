-- ============================================================================
-- COMPREHENSIVE CASCADE ANALYSIS
-- Purpose: Analyze ALL child records for the 7,179 garbage queue_consignments
--          to understand what needs cascading deletion
-- Created: November 1, 2025
-- ============================================================================

-- Get the IDs from the backup table (the 7,179 garbage records we wanted to delete)
CREATE TEMPORARY TABLE temp_garbage_ids AS
SELECT id, vend_consignment_id, public_id
FROM queue_consignments_stock_garbage_backup_20251101;

SELECT CONCAT('Found ', COUNT(*), ' garbage consignment IDs to analyze') as status
FROM temp_garbage_ids;

-- ============================================================================
-- ANALYZE CHILD RECORDS IN EACH TABLE
-- ============================================================================

SELECT '=== CHILD RECORDS BY TABLE ===' as analysis;

-- 1. queue_consignment_products (line items!)
SELECT
  'queue_consignment_products' as table_name,
  COUNT(*) as child_records,
  'Line items - products in consignments' as description
FROM queue_consignment_products qcp
INNER JOIN temp_garbage_ids g ON qcp.consignment_id = g.id;

-- 2. queue_consignment_notes
SELECT
  'queue_consignment_notes' as table_name,
  COUNT(*) as child_records,
  'Notes attached to consignments' as description
FROM queue_consignment_notes qcn
WHERE qcn.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 3. queue_consignment_actions
SELECT
  'queue_consignment_actions' as table_name,
  COUNT(*) as child_records,
  'Action history' as description
FROM queue_consignment_actions qca
WHERE qca.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 4. queue_consignment_state_transitions
SELECT
  'queue_consignment_state_transitions' as table_name,
  COUNT(*) as child_records,
  'Status change history' as description
FROM queue_consignment_state_transitions qcst
WHERE qcst.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 5. consignment_parcels
SELECT
  'consignment_parcels' as table_name,
  COUNT(*) as child_records,
  'Parcels/boxes in consignments' as description
FROM consignment_parcels cp
WHERE cp.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 6. consignment_parcel_items
SELECT
  'consignment_parcel_items' as table_name,
  COUNT(*) as child_records,
  'Items within parcels' as description
FROM consignment_parcel_items cpi
WHERE cpi.parcel_id IN (
  SELECT cp.id FROM consignment_parcels cp
  WHERE cp.consignment_id IN (SELECT id FROM temp_garbage_ids)
);

-- 7. consignment_logs
SELECT
  'consignment_logs' as table_name,
  COUNT(*) as child_records,
  'Log entries' as description
FROM consignment_logs cl
WHERE cl.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 8. consignment_notes
SELECT
  'consignment_notes' as table_name,
  COUNT(*) as child_records,
  'Notes (different from queue notes)' as description
FROM consignment_notes cn
WHERE cn.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 9. consignment_audit_log
SELECT
  'consignment_audit_log' as table_name,
  COUNT(*) as child_records,
  'Audit trail' as description
FROM consignment_audit_log cal
WHERE cal.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- 10. consignment_tracking_events
SELECT
  'consignment_tracking_events' as table_name,
  COUNT(*) as child_records,
  'Tracking/shipping events' as description
FROM consignment_tracking_events cte
WHERE cte.consignment_id IN (SELECT id FROM temp_garbage_ids);

-- ============================================================================
-- CHECK VEND_CONSIGNMENT_ID MATCHES
-- ============================================================================

SELECT '=== VEND SIDE CHILD RECORDS ===' as analysis;

-- Check vend_consignment_line_items (by vend_consignment_id, not queue id!)
SELECT
  'vend_consignment_line_items' as table_name,
  COUNT(*) as child_records,
  'Line items in vend_consignments table' as description
FROM vend_consignment_line_items vcli
WHERE vcli.vend_consignment_id IN (SELECT vend_consignment_id FROM temp_garbage_ids);

-- ============================================================================
-- DETAILED BREAKDOWN BY ID PATTERN
-- ============================================================================

SELECT '=== CHILD RECORDS BY ID PATTERN ===' as analysis;

SELECT
  'MIGRATED-STAFF-TRANSFER' as pattern,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   INNER JOIN temp_garbage_ids g ON qcp.consignment_id = g.id
   WHERE g.vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%') as products_count,
  (SELECT COUNT(*) FROM consignment_parcels cp
   INNER JOIN temp_garbage_ids g ON cp.consignment_id = g.id
   WHERE g.vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%') as parcels_count,
  (SELECT COUNT(*) FROM consignment_logs cl
   INNER JOIN temp_garbage_ids g ON cl.consignment_id = g.id
   WHERE g.vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%') as logs_count
UNION ALL
SELECT
  'Orphaned UUID (36 char)' as pattern,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   INNER JOIN temp_garbage_ids g ON qcp.consignment_id = g.id
   WHERE LENGTH(g.vend_consignment_id) = 36) as products_count,
  (SELECT COUNT(*) FROM consignment_parcels cp
   INNER JOIN temp_garbage_ids g ON cp.consignment_id = g.id
   WHERE LENGTH(g.vend_consignment_id) = 36) as parcels_count,
  (SELECT COUNT(*) FROM consignment_logs cl
   INNER JOIN temp_garbage_ids g ON cl.consignment_id = g.id
   WHERE LENGTH(g.vend_consignment_id) = 36) as logs_count;

-- ============================================================================
-- CHECK IF ANY UUID GARBAGE ACTUALLY EXISTS IN VEND_CONSIGNMENTS
-- ============================================================================

SELECT '=== CRITICAL CHECK: UUID GARBAGE IN VEND ===' as analysis;

SELECT
  COUNT(*) as count,
  'UUIDs from garbage that EXIST in vend_consignments' as description
FROM temp_garbage_ids g
WHERE LENGTH(g.vend_consignment_id) = 36
  AND EXISTS(SELECT 1 FROM vend_consignments vc
             WHERE vc.vend_consignment_id = g.vend_consignment_id);

SELECT
  COUNT(*) as count,
  'UUIDs from garbage that DO NOT exist in vend_consignments' as description
FROM temp_garbage_ids g
WHERE LENGTH(g.vend_consignment_id) = 36
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc
                 WHERE vc.vend_consignment_id = g.vend_consignment_id);

-- ============================================================================
-- SAMPLE RECORDS TO UNDERSTAND STRUCTURE
-- ============================================================================

SELECT '=== SAMPLE GARBAGE RECORDS WITH CHILDREN ===' as analysis;

SELECT
  g.id as queue_id,
  g.vend_consignment_id,
  g.public_id,
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id = g.id) as products,
  (SELECT COUNT(*) FROM consignment_parcels cp
   WHERE cp.consignment_id = g.id) as parcels,
  (SELECT COUNT(*) FROM consignment_logs cl
   WHERE cl.consignment_id = g.id) as logs,
  EXISTS(SELECT 1 FROM vend_consignments vc
         WHERE vc.vend_consignment_id = g.vend_consignment_id) as in_vend
FROM temp_garbage_ids g
ORDER BY (SELECT COUNT(*) FROM queue_consignment_products qcp
          WHERE qcp.consignment_id = g.id) DESC
LIMIT 10;

DROP TEMPORARY TABLE temp_garbage_ids;
