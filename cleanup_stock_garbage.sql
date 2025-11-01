-- ============================================================================
-- CLEANUP SCRIPT - Remove Garbage from queue_consignments STOCK
-- Created: November 1, 2025
-- Purpose: Delete 7,179 garbage records (3,466 MIGRATED + 3,713 orphaned UUID)
-- ============================================================================

-- SAFETY FIRST: Create backup table
CREATE TABLE IF NOT EXISTS queue_consignments_stock_garbage_backup_20251101 AS
SELECT * FROM queue_consignments WHERE 1=0;

-- Backup MIGRATED-STAFF-TRANSFER garbage (3,466 records)
INSERT INTO queue_consignments_stock_garbage_backup_20251101
SELECT * FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%';

-- Backup orphaned UUID records (3,713 records)
INSERT INTO queue_consignments_stock_garbage_backup_20251101
SELECT * FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc
                 WHERE vc.vend_consignment_id = qc.vend_consignment_id);

-- Verify backup count
SELECT
  'BACKUP VERIFICATION' as step,
  COUNT(*) as records_backed_up,
  'Expected: 7179 (3466 + 3713)' as expected
FROM queue_consignments_stock_garbage_backup_20251101;

-- ============================================================================
-- EXECUTE CLEANUP
-- ============================================================================

-- DELETE 1: Remove MIGRATED-STAFF-TRANSFER garbage
DELETE FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%';

SELECT
  'DELETE 1 COMPLETE' as step,
  ROW_COUNT() as records_deleted,
  'Expected: 3466' as expected;

-- DELETE 2: Remove orphaned UUID records
DELETE FROM queue_consignments
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc
                 WHERE vc.vend_consignment_id = vend_consignment_id);

SELECT
  'DELETE 2 COMPLETE' as step,
  ROW_COUNT() as records_deleted,
  'Expected: 3713' as expected;

-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT '=== POST-CLEANUP VERIFICATION ===' as status;

SELECT
  'Total queue STOCK remaining' as metric,
  COUNT(*) as count,
  'Expected: 4941 (4800 legacy + 141 new)' as expected
FROM queue_consignments
WHERE transfer_category='STOCK';

SELECT
  'Queue STOCK matched to legacy' as metric,
  COUNT(DISTINCT qc.id) as count,
  'Expected: 4800' as expected
FROM queue_consignments qc
INNER JOIN stock_transfers_backup_20251023 leg ON qc.cis_transfer_id = leg.transfer_id
WHERE qc.transfer_category='STOCK';

SELECT
  'Queue STOCK truly new (since Oct 23)' as metric,
  COUNT(*) as count,
  'Expected: 141' as expected
FROM queue_consignments qc
WHERE qc.transfer_category='STOCK'
  AND qc.created_at >= '2025-10-23 00:00:00'
  AND (qc.cis_transfer_id IS NULL
       OR NOT EXISTS(SELECT 1 FROM stock_transfers_backup_20251023 leg
                     WHERE leg.transfer_id = qc.cis_transfer_id));

SELECT
  'MIGRATED-STAFF-TRANSFER remaining' as metric,
  COUNT(*) as count,
  'Expected: 0' as expected
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%';

SELECT
  'Orphaned UUID remaining' as metric,
  COUNT(*) as count,
  'Expected: 0' as expected
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc
                 WHERE vc.vend_consignment_id = qc.vend_consignment_id);

-- ============================================================================
-- FINAL SUMMARY
-- ============================================================================

SELECT '=== CLEANUP SUMMARY ===' as status;

SELECT
  'Records before cleanup' as item,
  11991 as count
UNION ALL
SELECT
  'Garbage removed',
  (SELECT COUNT(*) FROM queue_consignments_stock_garbage_backup_20251101)
UNION ALL
SELECT
  'Records after cleanup',
  (SELECT COUNT(*) FROM queue_consignments WHERE transfer_category='STOCK')
UNION ALL
SELECT
  'Expected final count',
  4941
UNION ALL
SELECT
  'Difference from expected',
  (SELECT COUNT(*) FROM queue_consignments WHERE transfer_category='STOCK') - 4941;
