-- Find the JOIN key between queue and legacy backup

-- Check queue_consignments columns for STOCK category
SELECT
  'Queue fields sample' as info,
  id,
  cis_stock_transfer_id,
  vend_consignment_id,
  created_at
FROM queue_consignments
WHERE transfer_category='STOCK'
LIMIT 3;

-- Check legacy backup columns
SELECT
  'Legacy fields sample' as info,
  id as legacy_id,
  from_outlet_id,
  to_outlet_id,
  status
FROM stock_transfers_backup_20251023
LIMIT 3;

-- Show queue STOCK columns
SHOW COLUMNS FROM queue_consignments;

-- Show legacy backup columns
SHOW COLUMNS FROM stock_transfers_backup_20251023;
