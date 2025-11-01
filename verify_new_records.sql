-- ============================================================================
-- VERIFY WHICH RECORDS ARE TRULY NEW
-- Created: November 1, 2025
-- Purpose: Cross-check queue vs legacy backup to find REAL new records
-- ============================================================================

-- QUESTION: How many queue STOCK records DON'T exist in legacy backup?
-- This tells us what's TRULY new (not just created_at date)

SELECT '=== VERIFICATION OF NEW RECORDS ===' as analysis, '' as details, NULL as count
UNION ALL
SELECT '---', '---', NULL
UNION ALL

-- 1. Total queue STOCK
SELECT
  'Total queue STOCK',
  '',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'

UNION ALL

-- 2. Queue STOCK with created_at AFTER Oct 23
SELECT
  'Queue STOCK with created_at >= Oct 23',
  '(141 records based on timestamp)',
  COUNT(*)
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at >= '2025-10-23 00:00:00'

UNION ALL

-- 3. Check what fields exist in BOTH tables to match on
SELECT
  'Legacy backup columns check',
  'Need to see what we can JOIN on',
  NULL

UNION ALL

-- 4. CRITICAL: Show sample of "new" records to see what fields they have
SELECT
  'Sample NEW record fields',
  CONCAT('id=', id, ' vend_id=', SUBSTRING(vend_consignment_id, 1, 20), ' cis_po=', COALESCE(cis_purchase_order_id, 'NULL')),
  NULL
FROM queue_consignments
WHERE transfer_category='STOCK'
  AND created_at >= '2025-10-23 00:00:00'
LIMIT 3

UNION ALL

-- 5. Show sample of legacy backup to see its structure
SELECT
  'Sample LEGACY record fields',
  CONCAT('id=', id, ' from=', from_outlet_id, ' to=', to_outlet_id),
  NULL
FROM stock_transfers_backup_20251023
LIMIT 3;

-- ============================================================================
-- NOW THE KEY QUESTION: What's the JOIN key between these tables?
-- ============================================================================

-- We need to know:
-- - Does legacy have an "id" field that matches queue's something?
-- - Does legacy have stock_transfer_id that matches queue's cis_stock_transfer_id?
-- - What's the PRIMARY KEY relationship?

-- Let's check schema
DESCRIBE stock_transfers_backup_20251023;
