-- Create table for supplier notes on warranty claims
CREATE TABLE IF NOT EXISTS `faulty_product_notes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `faulty_product_id` INT(11) NOT NULL COMMENT 'FK to faulty_products.id',
  `supplier_id` VARCHAR(36) NOT NULL COMMENT 'FK to vend_suppliers.id',
  `note` TEXT NOT NULL COMMENT 'Supplier note/comment',
  `action` VARCHAR(50) DEFAULT NULL COMMENT 'Action type: investigating, accepted, declined, etc',
  `internal_ref` VARCHAR(100) DEFAULT NULL COMMENT 'Supplier internal reference number',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100) DEFAULT NULL COMMENT 'User who created the note',
  PRIMARY KEY (`id`),
  KEY `idx_faulty_product_id` (`faulty_product_id`),
  KEY `idx_supplier_id` (`supplier_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_fpn_faulty_product_id` FOREIGN KEY (`faulty_product_id`) REFERENCES `faulty_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fpn_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `vend_suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Supplier notes and communications for warranty claims';

-- Create indexes for performance
CREATE INDEX `idx_fault_supplier` ON `faulty_product_notes` (`faulty_product_id`, `supplier_id`);
CREATE INDEX `idx_action` ON `faulty_product_notes` (`action`);
