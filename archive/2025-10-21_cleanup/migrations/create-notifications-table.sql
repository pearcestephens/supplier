-- ============================================================================
-- CREATE/FIX supplier_portal_notifications TABLE
-- ============================================================================
-- Purpose: Ensure table exists with all required columns before running migration
-- Date: October 21, 2025
-- Issue: Triggers reference related_type and related_id columns that don't exist
-- ============================================================================

-- Check if table exists
SET @table_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supplier_portal_notifications'
);

-- ============================================================================
-- OPTION 1: Create table if it doesn't exist
-- ============================================================================

SET @create_table_sql = IF(
    @table_exists = 0,
    'CREATE TABLE `supplier_portal_notifications` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT ''Unique notification identifier'',
        `supplier_id` VARCHAR(100) NOT NULL COMMENT ''Supplier receiving the notification'',
        `type` VARCHAR(50) NOT NULL COMMENT ''Type: new_po, warranty_claim, stock_alert, message'',
        `title` VARCHAR(255) NOT NULL COMMENT ''Notification title/subject'',
        `message` TEXT DEFAULT NULL COMMENT ''Notification message body'',
        `link` VARCHAR(255) DEFAULT NULL COMMENT ''Deep link to related resource'',
        `related_type` VARCHAR(50) DEFAULT NULL COMMENT ''Entity type: transfer, product, faulty_product'',
        `related_id` VARCHAR(100) DEFAULT NULL COMMENT ''ID of related entity'',
        `is_read` TINYINT DEFAULT 0 COMMENT ''Read status: 0=unread, 1=read'',
        `read_at` TIMESTAMP NULL DEFAULT NULL COMMENT ''When notification was marked as read'',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT ''When notification was created'',
        INDEX `idx_supplier_unread` (`supplier_id`, `is_read`, `created_at` DESC),
        INDEX `idx_type` (`type`),
        INDEX `idx_created` (`created_at` DESC),
        INDEX `idx_related` (`related_type`, `related_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT ''Table supplier_portal_notifications already exists'' as status'
);

PREPARE stmt FROM @create_table_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- OPTION 2: Add missing columns if table exists
-- ============================================================================

-- Check if related_type column exists
SET @related_type_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supplier_portal_notifications'
      AND COLUMN_NAME = 'related_type'
);

-- Add related_type if missing
SET @add_related_type = IF(
    @table_exists = 1 AND @related_type_exists = 0,
    'ALTER TABLE `supplier_portal_notifications`
     ADD COLUMN `related_type` VARCHAR(50) DEFAULT NULL 
       COMMENT ''Entity type: transfer, product, faulty_product''
       AFTER `link`,
     ADD INDEX `idx_related_type` (`related_type`)',
    'SELECT ''Column related_type already exists or table not found'' as status'
);

PREPARE stmt FROM @add_related_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if related_id column exists
SET @related_id_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supplier_portal_notifications'
      AND COLUMN_NAME = 'related_id'
);

-- Add related_id if missing
SET @add_related_id = IF(
    @table_exists = 1 AND @related_id_exists = 0,
    'ALTER TABLE `supplier_portal_notifications`
     ADD COLUMN `related_id` VARCHAR(100) DEFAULT NULL 
       COMMENT ''ID of related entity''
       AFTER `related_type`,
     ADD INDEX `idx_related_id` (`related_id`)',
    'SELECT ''Column related_id already exists or table not found'' as status'
);

PREPARE stmt FROM @add_related_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT '✅ VERIFICATION: supplier_portal_notifications table' as status;

-- Check table exists
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ Table exists'
        ELSE '❌ Table NOT created'
    END as table_status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'supplier_portal_notifications';

-- Check all required columns exist
SELECT 
    '✅ Required Columns' as check_type,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'supplier_portal_notifications'
  AND COLUMN_NAME IN ('related_type', 'related_id', 'supplier_id', 'type', 'title')
ORDER BY 
    CASE COLUMN_NAME
        WHEN 'supplier_id' THEN 1
        WHEN 'type' THEN 2
        WHEN 'title' THEN 3
        WHEN 'related_type' THEN 4
        WHEN 'related_id' THEN 5
    END;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================

SELECT 
    '🎉 supplier_portal_notifications table ready!' as result,
    'You can now run fix-remaining-issues.sql safely' as next_step;

-- ============================================================================
-- END OF SCRIPT
-- ============================================================================
