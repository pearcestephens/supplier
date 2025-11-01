-- ============================================================================
-- FORENSIC MATCHING ANALYSIS - Queue vs Legacy
-- Created: November 1, 2025
-- Purpose: Match queue_consignments.cis_transfer_id to stock_transfers_backup_20251023.transfer_id
--          to identify EXACTLY what's legacy vs new vs garbage
-- ============================================================================

-- KEY DISCOVERY:
-- queue_consignments.cis_transfer_id = stock_transfers_backup_20251023.transfer_id
-- This is our JOIN key!

-- ============================================================================
-- SECTION 1: MATCHING ANALYSIS
-- ============================================================================

SELECT '=== MATCHING QUEUE TO LEGACY ===' as analysis, '' as details, NULL as count
UNION ALL
SELECT '---', '---', NULL

UNION ALL
SELECT
  'Total queue STOCK',
  '',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'

UNION ALL
SELECT
  'Queue STOCK with cis_transfer_id',
  '(has potential legacy link)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND cis_transfer_id IS NOT NULL

UNION ALL
SELECT
  'Queue STOCK WITHOUT cis_transfer_id',
  '(cannot match to legacy)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND cis_transfer_id IS NULL

UNION ALL
SELECT
  'Queue STOCK matched to legacy',
  '(cis_transfer_id exists in backup)',
  COUNT(DISTINCT qc.id)
FROM queue_consignments qc
INNER JOIN stock_transfers_backup_20251023 leg ON qc.cis_transfer_id = leg.transfer_id
WHERE qc.transfer_category='STOCK'

UNION ALL
SELECT
  'Queue STOCK NOT in legacy',
  '(cis_transfer_id but not in backup)',
  COUNT(*)
FROM queue_consignments qc
WHERE qc.transfer_category='STOCK'
  AND qc.cis_transfer_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM stock_transfers_backup_20251023 leg
    WHERE leg.transfer_id = qc.cis_transfer_id
  )

UNION ALL
SELECT
  'Legacy records',
  'Total in backup (Oct 23)',
  COUNT(*)
FROM stock_transfers_backup_20251023

UNION ALL
SELECT
  'Legacy records NOT in queue',
  '(missing from migration)',
  COUNT(*)
FROM stock_transfers_backup_20251023 leg
WHERE NOT EXISTS (
  SELECT 1 FROM queue_consignments qc
  WHERE qc.cis_transfer_id = leg.transfer_id
  AND qc.transfer_category='STOCK'
);

-- ============================================================================
-- SECTION 2: DUPLICATE ANALYSIS - Same legacy ID multiple times in queue
-- ============================================================================

SELECT '' as spacer, '', NULL
UNION ALL
SELECT '=== DUPLICATE DETECTION ===' as analysis, '', NULL
UNION ALL
SELECT '---', '---', NULL

UNION ALL
SELECT
  'Queue records with duplicate cis_transfer_id',
  '(same legacy ID appears multiple times)',
  COUNT(*)
FROM (
  SELECT cis_transfer_id, COUNT(*) as dup_count
  FROM queue_consignments
  WHERE transfer_category='STOCK'
    AND cis_transfer_id IS NOT NULL
  GROUP BY cis_transfer_id
  HAVING COUNT(*) > 1
) dups

UNION ALL
SELECT
  'Total duplicate queue records',
  '(extra copies beyond first)',
  SUM(dup_count - 1)
FROM (
  SELECT cis_transfer_id, COUNT(*) as dup_count
  FROM queue_consignments
  WHERE transfer_category='STOCK'
    AND cis_transfer_id IS NOT NULL
  GROUP BY cis_transfer_id
  HAVING COUNT(*) > 1
) dups;

-- ============================================================================
-- SECTION 3: GARBAGE IDENTIFICATION
-- ============================================================================

SELECT '' as spacer, '', NULL
UNION ALL
SELECT '=== GARBAGE BREAKDOWN ===' as analysis, '', NULL
UNION ALL
SELECT '---', '---', NULL

UNION ALL
SELECT
  'MIGRATED-STAFF-TRANSFER markers',
  '(old staff transfer garbage)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%'

UNION ALL
SELECT
  'UUID format orphans',
  '(not in vend_consignments)',
  COUNT(*)
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id)

UNION ALL
SELECT
  'Queue STOCK with NULL cis_transfer_id',
  '(cannot verify origin)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND cis_transfer_id IS NULL;

-- ============================================================================
-- SECTION 4: DATE/TIME CORRELATION ANALYSIS
-- ============================================================================

SELECT '' as spacer, '', NULL
UNION ALL
SELECT '=== DATE CORRELATION ===' as analysis, '', NULL
UNION ALL
SELECT '---', '---', NULL

UNION ALL
SELECT
  'Queue STOCK created AFTER Oct 23',
  '(potential new records)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at >= '2025-10-23 00:00:00'

UNION ALL
SELECT
  'Queue STOCK created AFTER Oct 23 AND matched to legacy',
  '(suspicious - created after backup but has legacy ID)',
  COUNT(*)
FROM queue_consignments qc
INNER JOIN stock_transfers_backup_20251023 leg ON qc.cis_transfer_id = leg.transfer_id
WHERE qc.transfer_category='STOCK'
  AND qc.created_at >= '2025-10-23 00:00:00'

UNION ALL
SELECT
  'Queue STOCK created AFTER Oct 23 AND NOT in legacy',
  '(TRULY NEW records)',
  COUNT(*)
FROM queue_consignments qc
WHERE qc.transfer_category='STOCK'
  AND qc.created_at >= '2025-10-23 00:00:00'
  AND (qc.cis_transfer_id IS NULL
       OR NOT EXISTS(SELECT 1 FROM stock_transfers_backup_20251023 leg
                     WHERE leg.transfer_id = qc.cis_transfer_id));

-- ============================================================================
-- SECTION 5: SAMPLES OF EACH CATEGORY
-- ============================================================================

SELECT '' as spacer, '', NULL
UNION ALL
SELECT '=== SAMPLE: TRULY NEW (created after Oct 23, not in legacy) ===' as analysis, '', NULL;

SELECT
  id as queue_id,
  vend_consignment_id,
  cis_transfer_id,
  created_at,
  status,
  'TRULY NEW' as category
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at >= '2025-10-23 00:00:00'
  AND (cis_transfer_id IS NULL
       OR NOT EXISTS(SELECT 1 FROM stock_transfers_backup_20251023 leg
                     WHERE leg.transfer_id = cis_transfer_id))
LIMIT 5;

SELECT '=== SAMPLE: MATCHED TO LEGACY ===' as analysis, '' as details, NULL as count;

SELECT
  qc.id as queue_id,
  qc.vend_consignment_id,
  qc.cis_transfer_id,
  qc.created_at as queue_created,
  leg.date_created as legacy_created,
  'MATCHED TO LEGACY' as category
FROM queue_consignments qc
INNER JOIN stock_transfers_backup_20251023 leg ON qc.cis_transfer_id = leg.transfer_id
WHERE qc.transfer_category='STOCK'
LIMIT 5;

SELECT '=== SAMPLE: MIGRATED GARBAGE ===' as analysis, '' as details, NULL as count;

SELECT
  id as queue_id,
  vend_consignment_id,
  cis_transfer_id,
  created_at,
  'MIGRATED GARBAGE' as category
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%'
LIMIT 5;

SELECT '=== SAMPLE: ORPHANED UUID ===' as analysis, '' as details, NULL as count;

SELECT
  id as queue_id,
  vend_consignment_id,
  cis_transfer_id,
  created_at,
  'ORPHANED UUID' as category
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id)
LIMIT 5;

-- ============================================================================
-- SECTION 6: FINAL VERDICT
-- ============================================================================

SELECT '' as spacer, '', NULL
UNION ALL
SELECT '=== FINAL VERDICT ===' as analysis, '', NULL
UNION ALL
SELECT '---', '---', NULL

UNION ALL
SELECT
  'Total queue STOCK',
  '',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'

UNION ALL
SELECT
  '  - Matched to legacy (keep)',
  'Valid migration',
  COUNT(DISTINCT qc.id)
FROM queue_consignments qc
INNER JOIN stock_transfers_backup_20251023 leg ON qc.cis_transfer_id = leg.transfer_id
WHERE qc.transfer_category='STOCK'

UNION ALL
SELECT
  '  - Truly new (keep)',
  'Created after Oct 23, not in legacy',
  COUNT(*)
FROM queue_consignments qc
WHERE qc.transfer_category='STOCK'
  AND qc.created_at >= '2025-10-23 00:00:00'
  AND (qc.cis_transfer_id IS NULL
       OR NOT EXISTS(SELECT 1 FROM stock_transfers_backup_20251023 leg
                     WHERE leg.transfer_id = qc.cis_transfer_id))

UNION ALL
SELECT
  '  - MIGRATED garbage (DELETE)',
  'Old staff transfers',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%'

UNION ALL
SELECT
  '  - Orphaned UUID (DELETE)',
  'Not in vend_consignments',
  COUNT(*)
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id)
  AND vend_consignment_id NOT LIKE 'MIGRATED-%'

UNION ALL
SELECT
  '  - Suspicious (investigate)',
  'Has cis_transfer_id but not in legacy, created before Oct 23',
  COUNT(*)
FROM queue_consignments qc
WHERE qc.transfer_category='STOCK'
  AND qc.cis_transfer_id IS NOT NULL
  AND NOT EXISTS(SELECT 1 FROM stock_transfers_backup_20251023 leg
                 WHERE leg.transfer_id = qc.cis_transfer_id)
  AND qc.created_at < '2025-10-23 00:00:00'
  AND vend_consignment_id NOT LIKE 'MIGRATED-%'
  AND NOT (LENGTH(vend_consignment_id) = 36
           AND NOT EXISTS(SELECT 1 FROM vend_consignments vc
                          WHERE vc.vend_consignment_id = qc.vend_consignment_id));
