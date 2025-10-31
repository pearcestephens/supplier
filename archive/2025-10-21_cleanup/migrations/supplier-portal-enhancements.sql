-- ============================================================================
-- SUPPLIER PORTAL - ENHANCEMENT MIGRATIONS
-- ============================================================================
-- Purpose: Add recommended features from testing phase
-- Date: October 21, 2025
-- Status: OPTIONAL ENHANCEMENTS (Portal is production-ready without these)
-- ============================================================================

-- ============================================================================
-- MIGRATION 1: Add Expected Delivery Date to Transfers
-- ============================================================================
-- Priority: HIGH
-- Benefit: Allows tracking of when supplier expects to deliver PO
-- Impact: Enables overdue PO alerts and better planning
-- ============================================================================

-- Add expected_delivery_date column to transfers table
ALTER TABLE `transfers` 
ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL 
AFTER `created_at`,
ADD INDEX `idx_expected_delivery` (`expected_delivery_date`, `state`);

-- Add supplier action timestamps for tracking
ALTER TABLE `transfers`
ADD COLUMN `supplier_sent_at` TIMESTAMP NULL DEFAULT NULL 
  COMMENT 'Timestamp when supplier marked transfer as sent via portal'
  AFTER `expected_delivery_date`,
ADD COLUMN `supplier_cancelled_at` TIMESTAMP NULL DEFAULT NULL 
  COMMENT 'Timestamp when supplier cancelled transfer via portal'
  AFTER `supplier_sent_at`,
ADD INDEX `idx_supplier_actions` (`supplier_sent_at`, `supplier_cancelled_at`);

-- ============================================================================
-- MIGRATION 2: Auto-Populate Notifications System
-- ============================================================================
-- Priority: HIGH
-- Benefit: Keeps suppliers informed of important events
-- Impact: Proactive communication, reduced support queries
-- ============================================================================

-- Create notification generation trigger for new transfers
DELIMITER $$

CREATE TRIGGER `trg_notify_new_transfer_for_supplier` 
AFTER INSERT ON `transfers`
FOR EACH ROW
BEGIN
    -- Only create notification if transfer has a supplier and is PURCHASE_ORDER category
    IF NEW.supplier_id IS NOT NULL 
       AND NEW.transfer_category = 'PURCHASE_ORDER' 
       AND NEW.state IN ('OPEN', 'SENT') THEN
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            NEW.supplier_id,
            'new_purchase_order',
            'New Purchase Order Received',
            CONCAT('Purchase Order #', NEW.public_id, ' has been created for ', 
                   (SELECT name FROM vend_outlets WHERE id = NEW.outlet_to LIMIT 1)),
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Create notification trigger for transfer state changes
DELIMITER $$

CREATE TRIGGER `trg_notify_transfer_state_change` 
AFTER UPDATE ON `transfers`
FOR EACH ROW
BEGIN
    -- Only notify supplier if state changed and has supplier_id
    IF NEW.supplier_id IS NOT NULL 
       AND NEW.transfer_category = 'PURCHASE_ORDER'
       AND OLD.state != NEW.state THEN
        
        -- Determine notification type and message based on state
        SET @notif_type = CASE NEW.state
            WHEN 'RECEIVED' THEN 'transfer_received'
            WHEN 'CANCELLED' THEN 'transfer_cancelled'
            WHEN 'CLOSED' THEN 'transfer_completed'
            ELSE 'transfer_updated'
        END;
        
        SET @notif_title = CASE NEW.state
            WHEN 'RECEIVED' THEN 'Purchase Order Received'
            WHEN 'CANCELLED' THEN 'Purchase Order Cancelled'
            WHEN 'CLOSED' THEN 'Purchase Order Completed'
            ELSE 'Purchase Order Updated'
        END;
        
        SET @notif_message = CONCAT('PO #', NEW.public_id, 
                                   ' status changed from ', OLD.state, 
                                   ' to ', NEW.state);
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            NEW.supplier_id,
            @notif_type,
            @notif_title,
            @notif_message,
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Create notification trigger for faulty products (warranty claims)
DELIMITER $$

CREATE TRIGGER `trg_notify_new_faulty_product` 
AFTER INSERT ON `faulty_products`
FOR EACH ROW
BEGIN
    -- Create notification for supplier when new faulty product reported
    -- Extract supplier_id from product
    DECLARE v_supplier_id VARCHAR(100);
    
    SELECT supplier_id INTO v_supplier_id
    FROM vend_products
    WHERE id = NEW.product_id
    LIMIT 1;
    
    IF v_supplier_id IS NOT NULL THEN
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            v_supplier_id,
            'new_warranty_claim',
            'New Faulty Product Claim',
            CONCAT('Faulty product claim #', NEW.id, ' has been submitted from ', NEW.store_location),
            'faulty_product',
            NEW.id,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Create notification trigger for faulty product status changes
DELIMITER $$

CREATE TRIGGER `trg_notify_faulty_product_update` 
AFTER UPDATE ON `faulty_products`
FOR EACH ROW
BEGIN
    -- Notify supplier when internal status changes (store updates claim)
    DECLARE v_supplier_id VARCHAR(100);
    
    SELECT supplier_id INTO v_supplier_id
    FROM vend_products
    WHERE id = NEW.product_id
    LIMIT 1;
    
    IF v_supplier_id IS NOT NULL 
       AND OLD.status != NEW.status THEN
        
        SET @status_text = CASE NEW.status
            WHEN 0 THEN 'Pending Review'
            WHEN 1 THEN 'Resolved'
            WHEN 2 THEN 'Rejected'
            ELSE 'Updated'
        END;
        
        INSERT INTO `supplier_portal_notifications` (
            supplier_id,
            type,
            title,
            message,
            related_type,
            related_id,
            created_at
        ) VALUES (
            v_supplier_id,
            'warranty_claim_updated',
            'Faulty Product Claim Status Updated',
            CONCAT('Claim #', NEW.id, ' status: ', @status_text),
            'faulty_product',
            NEW.id,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- MIGRATION 3: Inventory Integration (OPTIONAL)
-- ============================================================================
-- Priority: MEDIUM (only if stock levels are needed)
-- Benefit: Show real-time stock levels to suppliers
-- Impact: Better inventory planning, reduced out-of-stock situations
-- ============================================================================

-- Note: vend_inventory table already exists in database
-- Check if stock level display is actually needed before integrating
-- Current implementation: Products page shows all products without stock levels
-- This works well and is performant

-- IF stock levels are required, uncomment the following:

-- Create view for supplier product inventory summary
/*
CREATE OR REPLACE VIEW `v_supplier_product_inventory` AS
SELECT 
    p.id as product_id,
    p.supplier_id,
    p.name as product_name,
    p.sku,
    p.price_including_tax as retail_price,
    p.supply_price,
    COALESCE(SUM(i.count), 0) as total_stock,
    COUNT(DISTINCT i.outlet_id) as outlets_stocked,
    CASE 
        WHEN COALESCE(SUM(i.count), 0) = 0 THEN 'OUT_OF_STOCK'
        WHEN COALESCE(SUM(i.count), 0) < 5 THEN 'LOW_STOCK'
        WHEN COALESCE(SUM(i.count), 0) < 20 THEN 'MEDIUM_STOCK'
        ELSE 'IN_STOCK'
    END as stock_status
FROM vend_products p
LEFT JOIN vend_inventory i ON p.id = i.product_id
WHERE p.deleted_at = '0000-00-00 00:00:00'
GROUP BY p.id, p.supplier_id, p.name, p.sku, p.price_including_tax, p.supply_price;

-- Add index for performance
CREATE INDEX idx_inventory_product_outlet ON vend_inventory(product_id, outlet_id);
*/

-- ============================================================================
-- MIGRATION 4: Populate Test/Demo Faulty Product (OPTIONAL)
-- ============================================================================
-- Priority: LOW (for testing purposes only)
-- Benefit: Allows full testing of faulty product/warranty claim functionality
-- Impact: Validates complete warranty flow
-- ============================================================================

-- Insert a test faulty product for British American Tobacco
/*
-- First, get a product ID from BAT
SET @test_product_id = (
    SELECT id FROM vend_products 
    WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'
    AND name LIKE '%VUSE%'
    LIMIT 1
);

-- Get a store location
SET @test_store = (
    SELECT name FROM vend_outlets 
    WHERE name LIKE '%Auckland%'
    LIMIT 1
);

INSERT INTO `faulty_products` (
    product_id,
    serial_number,
    fault_desc,
    staff_member,
    store_location,
    time_created,
    status,
    supplier_status,
    supplier_update_status
) VALUES (
    @test_product_id,
    CONCAT('TEST-SN-', UNIX_TIMESTAMP()),
    'Device not charging - LED indicator not working. Customer reports device was working fine for 2 weeks then stopped charging completely. Charging port appears clean, tried multiple cables.',
    'Test Staff',
    @test_store,
    NOW(),
    1,  -- Status: Pending (active)
    0,  -- Supplier Status: Pending Review
    0   -- Not yet updated
);
*/

-- ============================================================================
    0,  -- Supplier Status: Pending Review
    (SELECT id FROM vend_outlets WHERE name LIKE '%Auckland%' LIMIT 1),
    NOW()
);

-- Add a test note to the claim (if faulty_product_notes table exists)
/*
INSERT INTO `faulty_product_notes` (
    fault_id,
    note,
    added_by_supplier,
    created_at
) VALUES (
    LAST_INSERT_ID(),
    'Initial assessment: Device appears to have charging port issue. Requesting replacement unit.',
    1,  -- Added by supplier
    NOW()
);
*/

-- ============================================================================
-- ROLLBACK INSTRUCTIONS
-- ============================================================================

-- To rollback Migration 1 (Expected Delivery Date):
/*
ALTER TABLE `transfers` 
DROP INDEX `idx_expected_delivery`,
DROP INDEX `idx_supplier_actions`,
DROP COLUMN `expected_delivery_date`,
DROP COLUMN `supplier_sent_at`,
DROP COLUMN `supplier_cancelled_at`;
*/

-- To rollback Migration 2 (Notifications):
/*
DROP TRIGGER IF EXISTS `trg_notify_new_transfer_for_supplier`;
DROP TRIGGER IF EXISTS `trg_notify_transfer_state_change`;
DROP TRIGGER IF EXISTS `trg_notify_new_faulty_product`;
DROP TRIGGER IF EXISTS `trg_notify_faulty_product_update`;
*/

-- To rollback Migration 3 (Inventory):
/*
DROP VIEW IF EXISTS `v_supplier_product_inventory`;
DROP INDEX IF EXISTS `idx_inventory_product_outlet` ON vend_inventory;
*/

-- ============================================================================
-- DEPLOYMENT CHECKLIST
-- ============================================================================

-- [ ] Backup database before running migrations
-- [ ] Run migrations on staging environment first
-- [ ] Test all portal functionality after migrations
-- [ ] Verify triggers are firing correctly
-- [ ] Check notification generation
-- [ ] Update portal code to use new expected_delivery_date field
-- [ ] Update dashboard to show overdue POs
-- [ ] Test warranty claim notifications
-- [ ] Monitor database performance after triggers added
-- [ ] Document new fields in schema documentation

-- ============================================================================
-- PERFORMANCE NOTES
-- ============================================================================

-- Triggers Impact:
-- - New Transfer: +1 INSERT (notifications table)
-- - Transfer Update: +1 INSERT if state changes
-- - Warranty Claim: +2 INSERTs (claim + notification)
-- - Estimated overhead: < 10ms per operation
-- - Notifications table should have index on (supplier_id, created_at, read_at)

-- Inventory View Impact (if enabled):
-- - Complex JOIN with aggregation
-- - Use view only when needed, don't query on every page load
-- - Consider caching results for 5-15 minutes
-- - Alternative: Pre-calculate and store in separate summary table

-- ============================================================================
-- END OF MIGRATIONS
-- ============================================================================
