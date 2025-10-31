-- ============================================================================
-- RECREATE TRIGGER WITH CORRECT OUTLET SCHEMA
-- ============================================================================
-- Purpose: Drop and recreate trg_notify_new_transfer_for_supplier with 
--          correct outlet deleted_at check (= '0000-00-00 00:00:00')
-- ============================================================================

USE icarex_test;

-- Drop the old trigger (with incorrect IS NULL check)
DROP TRIGGER IF EXISTS `trg_notify_new_transfer_for_supplier`;

-- Recreate with correct outlet deleted_at check
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

-- ============================================================================
-- VERIFICATION
-- ============================================================================

-- Check trigger exists
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    ACTION_TIMING,
    CREATED
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'icarex_test'
  AND TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

-- Verify the outlet deleted_at check is correct in the trigger definition
SELECT 
    TRIGGER_NAME,
    CASE 
        WHEN ACTION_STATEMENT LIKE '%deleted_at = ''0000-00-00 00:00:00''%' THEN '✅ CORRECT - Uses deleted_at = 0000-00-00 00:00:00'
        WHEN ACTION_STATEMENT LIKE '%deleted_at IS NULL%' THEN '❌ WRONG - Still uses IS NULL'
        ELSE '⚠️ UNKNOWN - Check manually'
    END AS outlet_schema_check,
    LEFT(ACTION_STATEMENT, 200) AS trigger_preview
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'icarex_test'
  AND TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

SELECT '✅ Trigger recreated successfully!' AS Status;
