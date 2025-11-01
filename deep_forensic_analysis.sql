-- ============================================================================
-- DEEP FORENSIC ANALYSIS - Match Legacy to Queue
-- Purpose: Identify EXACTLY which queue records came from legacy vs new
-- ============================================================================

-- STEP 1: See what columns exist in both tables
SELECT '=== QUEUE COLUMNS ===' as info;
SHOW COLUMNS FROM queue_consignments;

SELECT '=== LEGACY BACKUP COLUMNS ===' as info;
SHOW COLUMNS FROM stock_transfers_backup_20251023;

-- STEP 2: Sample queue STOCK records to see data patterns
SELECT '=== QUEUE STOCK SAMPLES ===' as info;
SELECT 
  id as queue_id,
  transfer_category,
  vend_consignment_id,
  supplier_id,
  outlet_id,
  created_at,
  status
FROM queue_consignments
WHERE transfer_category='STOCK'
ORDER BY created_at DESC
LIMIT 5;

-- STEP 3: Sample legacy backup records
SELECT '=== LEGACY BACKUP SAMPLES ===' as info;
SELECT 
  id as legacy_id,
  from_outlet_id,
  to_outlet_id,
  status,
  notes
FROM stock_transfers_backup_20251023
LIMIT 5;

-- STEP 4: Date range comparison
SELECT '=== DATE RANGES ===' as info;
SELECT 
  'Queue STOCK' as source,
  MIN(created_at) as earliest,
  MAX(created_at) as latest,
  COUNT(*) as total
FROM queue_consignments
WHERE transfer_category='STOCK'
UNION ALL
SELECT
  'Legacy Backup' as source,
  'N/A (no created_at)' as earliest,
  'N/A (no created_at)' as latest,
  COUNT(*) as total
FROM stock_transfers_backup_20251023;

-- STEP 5: Check if there's a linking ID field
SELECT '=== CHECKING FOR ID LINKS ===' as info;
SELECT
  'Queue with supplier_id set' as check_type,
  COUNT(*) as count
FROM queue_consignments
WHERE transfer_category='STOCK' AND supplier_id IS NOT NULL
UNION ALL
SELECT
  'Queue with outlet_id set' as check_type,
  COUNT(*) as count
FROM queue_consignments
WHERE transfer_category='STOCK' AND outlet_id IS NOT NULL;

