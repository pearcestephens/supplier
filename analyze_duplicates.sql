-- ============================================================================
-- COMPREHENSIVE DUPLICATE & SYNC ANALYSIS
-- Created: November 1, 2025
-- Purpose: Find duplicates between queue and vend, identify what's new
-- ============================================================================

-- 1. Check STOCK transfers date ranges
SELECT
  'STOCK TRANSFERS - Date Analysis' as analysis,
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
  CONCAT('Date range: ', MIN(created_at), ' to ', MAX(created_at))
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

-- 4. Check for records with MIGRATED-* markers (should already be in vend)
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== MIGRATION MARKERS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'STOCK with LEGACY markers',
  'LEGACY-ST-*',
  COUNT(*),
  'Should be old migrated data'
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'LEGACY-ST-%'
UNION ALL
SELECT
  'STOCK already in vend',
  'Found in vend_consignments',
  COUNT(*),
  'Already synced'
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'LEGACY-ST-%'
  AND EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id)
UNION ALL
SELECT
  'STOCK NOT in vend',
  'Missing from vend',
  COUNT(*),
  'NEED TO SYNC'
FROM queue_consignments qc
WHERE transfer_category='STOCK'
  AND vend_consignment_id LIKE 'LEGACY-ST-%'
  AND NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id);

-- ============================================================================

-- 5. Sample the STOCK records to understand the pattern
SELECT '' as spacer, '', NULL, '' UNION ALL
SELECT '=== SAMPLE STOCK RECORDS ===' as analysis, '', NULL, '' UNION ALL
SELECT '---', '---', NULL, '---'
UNION ALL
SELECT
  'Sample',
  CONCAT('ID: ', id, ', vend_id: ', SUBSTRING(vend_consignment_id, 1, 20)),
  NULL,
  CONCAT('Created: ', created_at, ', Status: ', status)
FROM queue_consignments
WHERE transfer_category='STOCK'
ORDER BY created_at DESC
LIMIT 5;
