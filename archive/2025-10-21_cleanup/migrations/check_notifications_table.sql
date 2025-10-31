-- Check if table exists
SELECT COUNT(*) as table_exists
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME = 'supplier_portal_notifications';

-- Check columns if exists
SELECT COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME = 'supplier_portal_notifications'
ORDER BY ORDINAL_POSITION;
