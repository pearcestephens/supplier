-- ============================================================================
-- SIMPLE CASCADE ANALYSIS
-- Purpose: Check if the 7,179 deleted garbage records have ANY child data
-- Created: November 1, 2025
-- ============================================================================

USE jcepnzzkmj;

SELECT '=== CHECKING FOR CHILD RECORDS ===' as step;

-- Check queue_consignment_products (line items by consignment_id)
SELECT
  'queue_consignment_products' as table_name,
  COUNT(*) as child_records_found
FROM queue_consignment_products qcp
WHERE qcp.consignment_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

-- Check consignment_shipments (shipments by transfer_id = queue_consignments.id)
SELECT
  'consignment_shipments' as table_name,
  COUNT(*) as child_records_found
FROM consignment_shipments cs
WHERE cs.transfer_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

-- Check consignment_parcels (via shipment_id chain)
SELECT
  'consignment_parcels' as table_name,
  COUNT(*) as child_records_found
FROM consignment_parcels cp
WHERE cp.shipment_id IN (
  SELECT cs.id FROM consignment_shipments cs
  WHERE cs.transfer_id IN (
    SELECT id FROM queue_consignments_stock_garbage_backup_20251101
  )
);

-- Check queue_consignment_notes
SELECT
  'queue_consignment_notes' as table_name,
  COUNT(*) as child_records_found
FROM queue_consignment_notes qcn
WHERE qcn.consignment_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

-- Check queue_consignment_actions
SELECT
  'queue_consignment_actions' as table_name,
  COUNT(*) as child_records_found
FROM queue_consignment_actions qca
WHERE qca.consignment_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

-- Check queue_consignment_state_transitions
SELECT
  'queue_consignment_state_transitions' as table_name,
  COUNT(*) as child_records_found
FROM queue_consignment_state_transitions qcst
WHERE qcst.consignment_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

-- Check consignment_logs
SELECT
  'consignment_logs' as table_name,
  COUNT(*) as child_records_found
FROM consignment_logs cl
WHERE cl.consignment_id IN (
  SELECT id FROM queue_consignments_stock_garbage_backup_20251101
);

SELECT '=== SUMMARY ===' as step;

SELECT
  (SELECT COUNT(*) FROM queue_consignment_products qcp
   WHERE qcp.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) +
  (SELECT COUNT(*) FROM consignment_shipments cs
   WHERE cs.transfer_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) +
  (SELECT COUNT(*) FROM consignment_parcels cp
   WHERE cp.shipment_id IN (SELECT cs.id FROM consignment_shipments cs
                            WHERE cs.transfer_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101))) +
  (SELECT COUNT(*) FROM queue_consignment_notes qcn
   WHERE qcn.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) +
  (SELECT COUNT(*) FROM queue_consignment_actions qca
   WHERE qca.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) +
  (SELECT COUNT(*) FROM queue_consignment_state_transitions qcst
   WHERE qcst.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) +
  (SELECT COUNT(*) FROM consignment_logs cl
   WHERE cl.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101))
  as total_orphaned_child_records;

SELECT '=== GOOD NEWS CHECK ===' as step;
SELECT
  CASE
    WHEN (SELECT COUNT(*) FROM queue_consignment_products qcp
          WHERE qcp.consignment_id IN (SELECT id FROM queue_consignments_stock_garbage_backup_20251101)) = 0
    THEN '✅ NO ORPHANED LINE ITEMS! The garbage records had no product data.'
    ELSE '⚠️ WARNING: Orphaned line items exist!'
  END as verdict;
