-- Shipment Boxes/Parcels Tracking System
-- Allows tracking which items go in which box/parcel

-- Drop existing tables if needed (for clean migration)
DROP TABLE IF EXISTS `shipment_box_items`;
DROP TABLE IF EXISTS `shipment_boxes`;

-- Main boxes/parcels table
CREATE TABLE `shipment_boxes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `consignment_id` INT UNSIGNED NOT NULL,
    `box_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `tracking_number` VARCHAR(100) NOT NULL,
    `carrier_name` VARCHAR(50) NULL,
    `weight_kg` DECIMAL(8,2) NULL,
    `dimensions` VARCHAR(50) NULL COMMENT 'LxWxH in cm',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_consignment` (`consignment_id`),
    INDEX `idx_tracking` (`tracking_number`),
    UNIQUE KEY `unique_consignment_box` (`consignment_id`, `box_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items assigned to boxes
CREATE TABLE `shipment_box_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `box_id` INT UNSIGNED NOT NULL,
    `line_item_id` INT UNSIGNED NOT NULL COMMENT 'vend_consignment_line_items.id',
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'How many of this item in this box',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_box` (`box_id`),
    INDEX `idx_line_item` (`line_item_id`),
    UNIQUE KEY `unique_box_item` (`box_id`, `line_item_id`),

    FOREIGN KEY (`box_id`) REFERENCES `shipment_boxes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to existing table for reference
ALTER TABLE `vend_consignments`
COMMENT = 'Main consignment/order table. See shipment_boxes for parcel tracking.';

SELECT 'Shipment boxes tracking system created successfully!' as status;
