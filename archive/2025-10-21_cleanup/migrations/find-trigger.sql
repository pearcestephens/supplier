-- ============================================================================
-- FIND AND VERIFY TRIGGER LOCATION
-- ============================================================================

-- Check what database we're currently using
SELECT DATABASE() AS current_database;

-- Find the trigger in ALL databases
SELECT 
    TRIGGER_SCHEMA AS database_name,
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    ACTION_TIMING,
    EVENT_OBJECT_TABLE,
    CREATED
FROM information_schema.TRIGGERS
WHERE TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

-- Check if trigger exists in icarex_test specifically
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    ACTION_TIMING,
    CREATED,
    'Found in icarex_test!' AS status
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'icarex_test'
  AND TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

-- Verify the outlet schema check
SELECT 
    TRIGGER_SCHEMA,
    TRIGGER_NAME,
    CASE 
        WHEN ACTION_STATEMENT LIKE '%deleted_at = ''0000-00-00 00:00:00''%' THEN '✅ CORRECT - Uses deleted_at = 0000-00-00 00:00:00'
        WHEN ACTION_STATEMENT LIKE '%deleted_at IS NULL%' THEN '❌ WRONG - Still uses IS NULL'
        ELSE '⚠️ UNKNOWN - Check manually'
    END AS outlet_schema_check
FROM information_schema.TRIGGERS
WHERE TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';

-- Show first 500 chars of trigger definition to verify outlet query
SELECT 
    TRIGGER_SCHEMA,
    TRIGGER_NAME,
    LEFT(ACTION_STATEMENT, 500) AS trigger_code_preview
FROM information_schema.TRIGGERS
WHERE TRIGGER_NAME = 'trg_notify_new_transfer_for_supplier';
