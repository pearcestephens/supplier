-- Order History and Notes Table
-- Tracks all status changes, notes, and actions on orders

CREATE TABLE IF NOT EXISTS `order_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `note` TEXT NULL,
    `created_by` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add carrier_name column to staff_transfers if not exists
SET @dbname = DATABASE();
SET @tablename = 'staff_transfers';
SET @columnname = 'carrier_name';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename) AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(50) NULL;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
