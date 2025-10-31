-- ============================================================================
-- RECREATE TRIGGER WITH CORRECT OUTLET SCHEMA (SQL Workbench Version)
-- ============================================================================
-- Purpose: Drop and recreate trg_notify_new_transfer_for_supplier with 
--          correct outlet deleted_at check (= '0000-00-00 00:00:00')
-- 
-- NOTE: Run each statement separately in SQL Workbench
-- ============================================================================

USE icarex_test;

-- Step 1: Drop the old trigger
DROP TRIGGER IF EXISTS `trg_notify_new_transfer_for_supplier`;

-- Step 2: Create new trigger with correct outlet schema
-- IMPORTANT: Change delimiter first, then create trigger, then change back

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
                   (SELECT name FROM vend_outlets WHERE id = NEW.outlet_to AND deleted_at = '0000-00-00 00:00:00' LIMIT 1)),
            'transfer',
            NEW.id,
            NOW()
        );
    END IF;
END$$

DELIMITER ;

-- Step 3: Verify trigger was created
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    ACTION_TIMING,
    CREATED
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'icarex_test'
  AND TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

-- Step 4: Check if outlet schema is correct
SELECT 
    TRIGGER_NAME,
    CASE 
        WHEN ACTION_STATEMENT LIKE '%deleted_at = ''0000-00-00 00:00:00''%' THEN '✅ CORRECT - Uses deleted_at = 0000-00-00 00:00:00'
        WHEN ACTION_STATEMENT LIKE '%deleted_at IS NULL%' THEN '❌ WRONG - Still uses IS NULL'
        ELSE '⚠️ UNKNOWN - Check manually'
    END AS outlet_schema_check
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'icarex_test'
  AND TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';
