-- ============================================================================
-- FIX REMAINING MIGRATION ISSUES
-- ============================================================================
-- Purpose: Fix the 2 remaining issues from full migration
-- Date: October 21, 2025
-- Issues to Fix:
--   1. Columns already exist (skip if duplicate error)
--   2. Test faulty product failed (store_location was NULL)
-- ============================================================================

-- ============================================================================
-- ISSUE 1: Skip if columns already exist (use ALTER TABLE IF NOT EXISTS)
-- ============================================================================
-- Note: MariaDB doesn't support IF NOT EXISTS for columns, so we'll use a workaround

-- Check if columns exist before adding
SET @expected_delivery_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transfers'
      AND COLUMN_NAME = 'expected_delivery_date'
);

-- Only add if doesn't exist
SET @sql_add_expected_delivery = IF(
    @expected_delivery_exists = 0,
    'ALTER TABLE `transfers` 
     ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL 
       COMMENT "Expected delivery date set by supplier via portal"
       AFTER `created_at`,
     ADD INDEX `idx_expected_delivery` (`expected_delivery_date`, `state`)',
    'SELECT "Column expected_delivery_date already exists - skipping" as status'
);

PREPARE stmt FROM @sql_add_expected_delivery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ISSUE 2: Fix Test Faulty Product Insert
-- ============================================================================
-- Problem: @test_store variable was NULL because store name didn't match
-- Solution: Use proper store location value or skip if no match

-- Get a valid Auckland store with proper NULL check
SET @test_store_id = (
    SELECT id FROM vend_outlets 
    WHERE (name LIKE '%Auckland%' OR physical_city LIKE '%Auckland%')
    AND deleted_at = '0000-00-00 00:00:00'
    LIMIT 1
);

-- Get the store name
SET @test_store_name = (
    SELECT name FROM vend_outlets 
    WHERE id = @test_store_id
    LIMIT 1
);

-- Get a VUSE product from British American Tobacco
SET @test_product_id = (
    SELECT id FROM vend_products 
    WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'
    AND (name LIKE '%VUSE%' OR name LIKE '%Vuse%')
    AND deleted_at = '0000-00-00 00:00:00'
    LIMIT 1
);

-- Only insert if we have both valid product and store
-- NOTE: faulty_products table does NOT have related_type/related_id columns
-- Those columns are only in supplier_portal_notifications table
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
)
SELECT
    @test_product_id,
    CONCAT('TEST-WARRANTY-', UNIX_TIMESTAMP()),
    'TEST CLAIM: Device not charging - LED indicator not working. Customer reports device was working fine for 2 weeks then stopped charging completely. Charging port appears clean, tried multiple cables. This is a test warranty claim to validate the supplier portal notification system.',
    'Test Staff (Portal Setup)',
    COALESCE(@test_store_name, 'Test Store'),  -- Fallback to 'Test Store' if no Auckland store found
    NOW(),
    1,  -- Status: Active/Pending
    0,  -- Supplier Status: Pending Review
    0   -- Not yet updated
WHERE @test_product_id IS NOT NULL
  AND @test_store_name IS NOT NULL
  -- Don't insert if test claim already exists
  AND NOT EXISTS (
      SELECT 1 FROM faulty_products 
      WHERE serial_number LIKE 'TEST-WARRANTY-%'
  );

-- ============================================================================
-- VERIFICATION - Check what we just fixed
-- ============================================================================

SELECT '✅ MIGRATION STATUS CHECK' as status;

-- Check if expected_delivery_date column exists now
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ expected_delivery_date column EXISTS'
        ELSE '❌ expected_delivery_date column MISSING'
    END as column_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'transfers'
  AND COLUMN_NAME = 'expected_delivery_date';

-- Check if test faulty product was created
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN CONCAT('✅ Test faulty product created (ID: ', id, ')')
        ELSE '⚠️ Test faulty product not created (product or store not found)'
    END as test_claim_status,
    CASE 
        WHEN COUNT(*) > 0 THEN serial_number
        ELSE NULL
    END as serial_number
FROM faulty_products
WHERE serial_number LIKE 'TEST-WARRANTY-%'
LIMIT 1;

-- Show all columns added to transfers table
SELECT 
    '✅ ALL SUPPLIER COLUMNS IN TRANSFERS' as status,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'transfers'
  AND (COLUMN_NAME LIKE '%supplier%' OR COLUMN_NAME LIKE '%expected%')
ORDER BY ORDINAL_POSITION;

-- Show all triggers created
SELECT 
    '✅ ALL TRIGGERS CREATED' as status,
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND (TRIGGER_NAME LIKE '%supplier%' OR TRIGGER_NAME LIKE '%faulty%')
ORDER BY TRIGGER_NAME;

-- Show all views created
SELECT 
    '✅ ALL VIEWS CREATED' as status,
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'v_supplier%'
ORDER BY TABLE_NAME;

-- ============================================================================
-- BONUS: Test the new views with real data
-- ============================================================================

-- Test inventory view (show top 5 products with stock info)
SELECT 
    '📊 SAMPLE INVENTORY DATA' as test,
    product_name,
    sku,
    total_stock,
    outlets_stocked,
    stock_status
FROM v_supplier_product_inventory
WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'  -- British American Tobacco
LIMIT 5;

-- Test sales analytics view (show top 5 selling products)
SELECT 
    '📈 SAMPLE SALES DATA (Last 30 Days)' as test,
    product_name,
    outlet_name,
    units_sold,
    ROUND(gross_revenue, 2) as revenue,
    units_returned
FROM v_supplier_product_sales
WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'  -- British American Tobacco
ORDER BY units_sold DESC
LIMIT 5;

-- ============================================================================
-- FINAL SUMMARY
-- ============================================================================

SELECT 
    '🎉 MIGRATION COMPLETE!' as summary,
    CONCAT(
        (SELECT COUNT(*) FROM information_schema.TRIGGERS 
         WHERE TRIGGER_SCHEMA = DATABASE() 
         AND (TRIGGER_NAME LIKE '%supplier%' OR TRIGGER_NAME LIKE '%faulty%')),
        ' triggers'
    ) as triggers_created,
    CONCAT(
        (SELECT COUNT(*) FROM information_schema.TABLES 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME LIKE 'v_supplier%'),
        ' views'
    ) as views_created,
    CONCAT(
        (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'transfers'
         AND (COLUMN_NAME LIKE '%supplier%' OR COLUMN_NAME LIKE '%expected%')),
        ' columns'
    ) as columns_added,
    CONCAT(
        (SELECT COUNT(*) FROM information_schema.ROUTINES
         WHERE ROUTINE_SCHEMA = DATABASE()
         AND ROUTINE_NAME = 'sp_check_low_stock_and_notify'),
        ' procedure'
    ) as procedures_created;

-- ============================================================================
-- NEXT STEPS FOR YOU
-- ============================================================================

SELECT '📋 NEXT STEPS' as action_required;

SELECT '1️⃣ Run low stock check manually:' as step_1;
-- CALL sp_check_low_stock_and_notify();

SELECT '2️⃣ Test notification trigger by updating a transfer:' as step_2;
-- UPDATE transfers SET state = 'SENT' WHERE id = [some_transfer_id] LIMIT 1;

SELECT '3️⃣ Query inventory view for your supplier:' as step_3;
-- SELECT * FROM v_supplier_product_inventory WHERE supplier_id = 'YOUR_SUPPLIER_ID';

SELECT '4️⃣ Query sales view for performance data:' as step_4;
-- SELECT * FROM v_supplier_product_sales WHERE supplier_id = 'YOUR_SUPPLIER_ID';

SELECT '5️⃣ Update portal code to use new features!' as step_5;

-- ============================================================================
-- END OF FIX SCRIPT
-- ============================================================================
