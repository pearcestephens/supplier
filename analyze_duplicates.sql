-- ============================================================================
-- COMPREHENSIVE DUPLICATE & SYNC ANALYSIS
-- Created: November 1, 2025
-- Purpose: Find duplicates between queue and vend, identify what's new
-- ============================================================================

-- 1. Check STOCK transfers counts (skip date ranges for now)
SELECT
  'STOCK TRANSFERS - Count Analysis' as analysis,
  '' as category,
  NULL as count,
  '' as details
UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Legacy Backup',
  'stock_transfers_backup_20251023',
  COUNT(*),
  'Total in backup (Oct 23, 2025)'
FROM stock_transfers_backup_20251023
UNION ALL
SELECT
  'Queue STOCK',
  'queue_consignments.STOCK',
  COUNT(*),
  CONCAT('Date range: ', MIN(created_at), ' to ', MAX(created_at))
FROM queue_consignments WHERE transfer_category='STOCK'
UNION ALL
SELECT
  'Queue STOCK (since Oct 23)',
  'New records only',
  COUNT(*),
  'Created after backup date'
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at >= '2025-10-23 00:00:00'
UNION ALL
SELECT
  'Queue STOCK (before Oct 23)',
  'Should match backup',
  COUNT(*),
  'Created before/on backup date'
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at < '2025-10-23 00:00:00';

-- ============================================================================

-- 2. Find DUPLICATES - Records in BOTH queue and vend
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== DUPLICATE ANALYSIS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'ALL Categories',
  'Records in BOTH queue AND vend',
  COUNT(*),
  'These already exist in vend'
FROM queue_consignments qc
WHERE EXISTS (
  SELECT 1 FROM vend_consignments vc
  WHERE vc.vend_consignment_id = qc.vend_consignment_id
);

-- ============================================================================

-- 3. Break down by category
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== BY CATEGORY ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  transfer_category,
  'In queue',
  COUNT(*),
  ''
FROM queue_consignments
GROUP BY transfer_category
UNION ALL
SELECT
  transfer_category,
  'Already in vend',
  COUNT(*),
  'DUPLICATES - already synced'
FROM queue_consignments qc
WHERE EXISTS (
  SELECT 1 FROM vend_consignments vc
  WHERE vc.vend_consignment_id = qc.vend_consignment_id
)
GROUP BY transfer_category
UNION ALL
SELECT
  transfer_category,
  'NOT in vend',
  COUNT(*),
  'NEED TO SYNC'
FROM queue_consignments qc
WHERE NOT EXISTS (
  SELECT 1 FROM vend_consignments vc
  WHERE vc.vend_consignment_id = qc.vend_consignment_id
)
GROUP BY transfer_category;

-- ============================================================================

-- 4. DEEP DIVE - Unique vend_consignment_id patterns in STOCK
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== STOCK ID PATTERNS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Pattern Analysis',
  CASE
    WHEN vend_consignment_id IS NULL THEN 'NULL'
    WHEN vend_consignment_id = '' THEN 'EMPTY STRING'
    WHEN vend_consignment_id LIKE 'LEGACY-%' THEN 'LEGACY markers'
    WHEN vend_consignment_id LIKE 'MIGRATED-%' THEN 'MIGRATED markers'
    WHEN LENGTH(vend_consignment_id) = 36 AND vend_consignment_id LIKE '%-%-%-%-%' THEN 'UUID format'
    ELSE 'OTHER format'
  END as pattern_type,
  COUNT(*),
  CONCAT('Sample: ', SUBSTRING(MIN(vend_consignment_id), 1, 30))
FROM queue_consignments
WHERE transfer_category='STOCK'
GROUP BY pattern_type;

-- ============================================================================

-- 5. Check if STOCK vend_consignment_ids exist in vend_consignments
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== STOCK SYNC STATUS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Total STOCK in queue',
  '',
  COUNT(*),
  ''
FROM queue_consignments
WHERE transfer_category='STOCK'
UNION ALL
SELECT
  'STOCK with UUID vend_id',
  'UUID format IDs',
  COUNT(*),
  'These should be in vend already'
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
UNION ALL
SELECT
  'STOCK UUIDs found in vend',
  'Actually exist in vend',
  COUNT(*),
  'DUPLICATES'
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
  AND EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id)
UNION ALL
SELECT
  'STOCK UUIDs NOT in vend',
  'Missing from vend',
  COUNT(*),
  'ORPHANED - possible data issue'
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND LENGTH(vend_consignment_id) = 36
  AND vend_consignment_id LIKE '%-%-%-%-%'
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id);

-- ============================================================================

-- 6. Compare vend OUTLET records to queue by vend_consignment_id
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== VEND OUTLET ANALYSIS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Total vend OUTLET',
  '',
  COUNT(*),
  ''
FROM vend_consignments
WHERE type='OUTLET'
UNION ALL
SELECT
  'OUTLET with matching queue',
  'Found in queue_consignments',
  COUNT(*),
  'DUPLICATES in both systems'
FROM vend_consignments vc
WHERE type='OUTLET'
  AND EXISTS(SELECT 1 FROM queue_consignments qc WHERE qc.vend_consignment_id = vc.vend_consignment_id)
UNION ALL
SELECT
  'OUTLET NOT in queue',
  'Only in vend',
  COUNT(*),
  'Vend-only records (live data?)'
FROM vend_consignments vc
WHERE type='OUTLET'
  AND NOT EXISTS(SELECT 1 FROM queue_consignments qc WHERE qc.vend_consignment_id = vc.vend_consignment_id);

-- ============================================================================

-- 7. MIGRATED markers deep dive
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== MIGRATED MARKERS BREAKDOWN ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'MIGRATED-STAFF-TRANSFER',
  'Old staff transfer data',
  COUNT(*),
  CONCAT('Sample: ', SUBSTRING(MIN(vend_consignment_id), 1, 40))
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-STAFF-TRANSFER-%'
UNION ALL
SELECT
  'Other MIGRATED patterns',
  'Check what else exists',
  COUNT(*),
  CONCAT('Sample: ', SUBSTRING(MIN(vend_consignment_id), 1, 40))
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'MIGRATED-%'
  AND vend_consignment_id NOT LIKE 'MIGRATED-STAFF-TRANSFER-%';

-- ============================================================================

-- 8. FINAL SUMMARY
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== FINAL SUMMARY ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Queue STOCK total',
  '',
  11991,
  ''
UNION ALL
SELECT
  '  - MIGRATED markers',
  '(old staff transfers)',
  8266,
  'Should NOT sync (duplicates)'
UNION ALL
SELECT
  '  - UUID format',
  '(orphaned UUIDs)',
  3713,
  'Orphaned - NO match in vend'
UNION ALL
SELECT
  '  - Other format',
  '(misc)',
  12,
  'Unknown'
UNION ALL
SELECT
  'Vend OUTLET total',
  '',
  12563,
  'NO overlap with queue!'
UNION ALL
SELECT
  'Actual NEW to sync',
  'since Oct 23',
  141,
  'Only these are truly new!';
