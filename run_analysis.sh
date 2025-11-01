#!/bin/bash
# Comprehensive duplicate and sync analysis
# Run: bash run_analysis.sh

DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

echo "========================================"
echo "DUPLICATE & SYNC ANALYSIS"
echo "========================================"
echo ""

echo "1. STOCK TRANSFERS - Date Analysis"
echo "-----------------------------------"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
  'Legacy backup (Oct 23)' as source,
  COUNT(*) as total,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM stock_transfers_backup_20251023
UNION ALL
SELECT 
  'Queue STOCK (all)' as source,
  COUNT(*) as total,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM queue_consignments WHERE transfer_category='STOCK'
UNION ALL
SELECT 
  'Queue STOCK (since Oct 23)' as source,
  COUNT(*) as total,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM queue_consignments 
WHERE transfer_category='STOCK' 
  AND created_at >= '2025-10-23 00:00:00'
UNION ALL
SELECT 
  'Queue STOCK (before Oct 23)' as source,
  COUNT(*) as total,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM queue_consignments 
WHERE transfer_category='STOCK' 
  AND created_at < '2025-10-23 00:00:00';
"

echo ""
echo "2. DUPLICATE CHECK - Records in BOTH systems"
echo "--------------------------------------------"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
  qc.transfer_category,
  COUNT(*) as in_queue,
  SUM(CASE WHEN EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id) THEN 1 ELSE 0 END) as already_in_vend,
  SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id) THEN 1 ELSE 0 END) as missing_from_vend
FROM queue_consignments qc
GROUP BY qc.transfer_category;
"

echo ""
echo "3. STOCK RECORDS - Migration Marker Analysis"
echo "--------------------------------------------"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
  CASE 
    WHEN vend_consignment_id LIKE 'LEGACY-ST-%' THEN 'LEGACY-ST-*'
    WHEN vend_consignment_id LIKE 'MIGRATED-ST-%' THEN 'MIGRATED-ST-*'
    WHEN vend_consignment_id LIKE '%-%' THEN 'UUID format'
    ELSE 'Other'
  END as id_pattern,
  COUNT(*) as count,
  SUM(CASE WHEN EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id) THEN 1 ELSE 0 END) as in_vend,
  SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id) THEN 1 ELSE 0 END) as not_in_vend
FROM queue_consignments qc
WHERE transfer_category='STOCK'
GROUP BY id_pattern;
"

echo ""
echo "4. VEND OUTLET TYPE - What's already there?"
echo "-------------------------------------------"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
  CASE 
    WHEN vend_consignment_id LIKE 'LEGACY-ST-%' THEN 'LEGACY Stock Transfers'
    WHEN vend_consignment_id LIKE 'LEGACY-JT-%' THEN 'LEGACY Juice Transfers'
    WHEN vend_consignment_id LIKE 'LEGACY-IN-%' THEN 'LEGACY Internal'
    WHEN vend_consignment_id LIKE 'MIGRATED-%' THEN 'MIGRATED records'
    ELSE 'Live/Current'
  END as vend_type,
  COUNT(*) as count,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM vend_consignments
WHERE type='OUTLET'
GROUP BY vend_type;
"

echo ""
echo "5. SAMPLE STOCK RECORDS (newest 10)"
echo "-----------------------------------"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
  id,
  SUBSTRING(vend_consignment_id, 1, 25) as vend_id,
  created_at,
  status,
  CASE WHEN EXISTS(SELECT 1 FROM vend_consignments vc WHERE vc.vend_consignment_id = qc.vend_consignment_id) THEN 'YES' ELSE 'NO' END as in_vend
FROM queue_consignments qc
WHERE transfer_category='STOCK'
ORDER BY created_at DESC
LIMIT 10;
"

echo ""
echo "======================================"
echo "ANALYSIS COMPLETE"
echo "======================================"
