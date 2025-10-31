-- Migration: Historical Purchase Orders to vend_consignments
-- Date: 2025-10-31
-- Purpose: Migrate 11,472 historical purchase orders from purchase_orders table to vend_consignments
-- Author: System Migration
-- Estimated Time: 5-10 minutes

-- ============================================================================
-- PHASE 1: BACKUP
-- ============================================================================

-- Backup vend_consignments
CREATE TABLE IF NOT EXISTS vend_consignments_backup_20251031
AS SELECT * FROM vend_consignments;

-- Backup purchase_orders
CREATE TABLE IF NOT EXISTS purchase_orders_backup_20251031
AS SELECT * FROM purchase_orders;

SELECT 'Backups created successfully' as status;

-- ============================================================================
-- PHASE 2: PRE-MIGRATION VERIFICATION
-- ============================================================================

-- Count source records
SELECT
    'Source Records' as check_type,
    COUNT(*) as count
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NULL;
-- Expected: 11,472

-- Count current target records
SELECT
    'Current Target Records' as check_type,
    COUNT(*) as count
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 94

-- ============================================================================
-- PHASE 3: TEST MIGRATION (10 RECORDS)
-- ============================================================================

-- Test with 10 oldest records first
INSERT INTO vend_consignments (
    public_id,
    supplier_id,
    outlet_from,
    outlet_to,
    transfer_category,
    state,
    created_at,
    updated_at,
    sent_at,
    received_at,
    notes
)
SELECT
    CONCAT('PO-', po.purchase_order_id) as public_id,
    po.supplier_id,
    po.supplier_id as outlet_from,  -- Supplier is the source
    po.outlet_id as outlet_to,       -- Store is the destination
    'PURCHASE_ORDER' as transfer_category,
    CASE
        WHEN po.status = 0 THEN 'OPEN'
        WHEN po.status = 1 AND po.completed_at IS NOT NULL THEN 'RECEIVED'
        WHEN po.status = 1 AND po.last_received_at IS NOT NULL THEN 'RECEIVED'
        WHEN po.last_received_at IS NOT NULL THEN 'RECEIVING'
        ELSE 'CLOSED'
    END as state,
    po.date_created as created_at,
    po.updated_at,
    po.completed_timestamp as sent_at,
    po.last_received_at as received_at,
    po.receiving_notes as notes
FROM purchase_orders po
WHERE po.deleted_at IS NULL
AND po.vend_consignment_id IS NULL
ORDER BY po.date_created ASC
LIMIT 10;

-- Get the IDs of newly created records
SET @last_insert_id = LAST_INSERT_ID();

-- Update purchase_orders with the new vend_consignment_id
UPDATE purchase_orders po
INNER JOIN vend_consignments vc ON vc.public_id = CONCAT('PO-', po.purchase_order_id)
SET po.vend_consignment_id = vc.id
WHERE po.purchase_order_id IN (
    SELECT purchase_order_id
    FROM (
        SELECT purchase_order_id
        FROM purchase_orders
        WHERE deleted_at IS NULL
        AND vend_consignment_id IS NOT NULL
        ORDER BY date_created ASC
        LIMIT 10
    ) as subquery
);

SELECT 'Test migration completed - 10 records' as status;

-- Verify test migration
SELECT
    'Test Verification' as check_type,
    COUNT(*) as migrated_count
FROM vend_consignments vc
INNER JOIN purchase_orders po ON vc.id = po.vend_consignment_id
WHERE po.purchase_order_id IN (
    SELECT purchase_order_id
    FROM (
        SELECT purchase_order_id
        FROM purchase_orders
        WHERE vend_consignment_id IS NOT NULL
        ORDER BY date_created ASC
        LIMIT 10
    ) as subquery
);
-- Expected: 10

-- ============================================================================
-- PHASE 4: MIGRATE LINE ITEMS FOR TEST RECORDS
-- ============================================================================

-- Migrate line items for the 10 test records
INSERT INTO vend_consignment_line_items (
    transfer_id,
    product_id,
    quantity,
    unit_cost,
    notes
)
SELECT
    vc.id as transfer_id,
    poli.product_id,
    poli.order_qty as quantity,
    poli.unit_cost_ex_gst as unit_cost,
    poli.line_note as notes
FROM purchase_order_line_items poli
INNER JOIN purchase_orders po ON poli.purchase_order_id = po.purchase_order_id
INNER JOIN vend_consignments vc ON po.vend_consignment_id = vc.id
WHERE po.purchase_order_id IN (
    SELECT purchase_order_id
    FROM (
        SELECT purchase_order_id
        FROM purchase_orders
        WHERE vend_consignment_id IS NOT NULL
        ORDER BY date_created ASC
        LIMIT 10
    ) as subquery
);

SELECT 'Test line items migrated' as status;

-- ============================================================================
-- VERIFICATION CHECKPOINT
-- ============================================================================
-- STOP HERE AND VERIFY IN SUPPLIER PORTAL BEFORE CONTINUING
-- Check that:
-- 1. 10 orders appear in supplier portal
-- 2. Line items display correctly
-- 3. Dates are correct
-- 4. Suppliers can only see their orders
-- ============================================================================

-- ============================================================================
-- PHASE 5: FULL MIGRATION (RUN ONLY AFTER TEST VERIFICATION)
-- ============================================================================

-- Migrate all remaining records
INSERT INTO vend_consignments (
    public_id,
    supplier_id,
    outlet_from,
    outlet_to,
    transfer_category,
    state,
    created_at,
    updated_at,
    sent_at,
    received_at,
    notes
)
SELECT
    CONCAT('PO-', po.purchase_order_id) as public_id,
    po.supplier_id,
    po.supplier_id as outlet_from,
    po.outlet_id as outlet_to,
    'PURCHASE_ORDER' as transfer_category,
    CASE
        WHEN po.status = 0 THEN 'OPEN'
        WHEN po.status = 1 AND po.completed_at IS NOT NULL THEN 'RECEIVED'
        WHEN po.status = 1 AND po.last_received_at IS NOT NULL THEN 'RECEIVED'
        WHEN po.last_received_at IS NOT NULL THEN 'RECEIVING'
        ELSE 'CLOSED'
    END as state,
    po.date_created as created_at,
    po.updated_at,
    po.completed_timestamp as sent_at,
    po.last_received_at as received_at,
    po.receiving_notes as notes
FROM purchase_orders po
WHERE po.deleted_at IS NULL
AND po.vend_consignment_id IS NULL;

-- Update purchase_orders with new vend_consignment_id
UPDATE purchase_orders po
INNER JOIN vend_consignments vc ON vc.public_id = CONCAT('PO-', po.purchase_order_id)
SET po.vend_consignment_id = vc.id
WHERE po.deleted_at IS NULL
AND po.vend_consignment_id IS NULL;

SELECT 'Full migration completed' as status;

-- ============================================================================
-- PHASE 6: MIGRATE ALL LINE ITEMS
-- ============================================================================

-- Migrate all line items
INSERT INTO vend_consignment_line_items (
    transfer_id,
    product_id,
    quantity,
    unit_cost,
    notes
)
SELECT
    vc.id as transfer_id,
    poli.product_id,
    poli.order_qty as quantity,
    poli.unit_cost_ex_gst as unit_cost,
    poli.line_note as notes
FROM purchase_order_line_items poli
INNER JOIN purchase_orders po ON poli.purchase_order_id = po.purchase_order_id
INNER JOIN vend_consignments vc ON po.vend_consignment_id = vc.id
WHERE po.deleted_at IS NULL;

SELECT 'All line items migrated' as status;

-- ============================================================================
-- PHASE 7: POST-MIGRATION VERIFICATION
-- ============================================================================

-- Verify all purchase orders migrated
SELECT
    'Migrated Purchase Orders' as check_type,
    COUNT(*) as count
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NOT NULL;
-- Expected: 11,472

-- Verify total in vend_consignments
SELECT
    'Total PURCHASE_ORDER in vend_consignments' as check_type,
    COUNT(*) as count
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 11,566 (94 existing + 11,472 migrated)

-- Verify line items
SELECT
    'Total Line Items Migrated' as check_type,
    COUNT(*) as count
FROM vend_consignment_line_items vcli
INNER JOIN vend_consignments vc ON vcli.transfer_id = vc.id
WHERE vc.transfer_category = 'PURCHASE_ORDER'
AND vc.public_id LIKE 'PO-%';
-- Expected: ~259,227 (matching purchase_order_line_items count)

-- Verify supplier distribution
SELECT
    supplier_id,
    COUNT(*) as order_count
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER'
AND public_id LIKE 'PO-%'
GROUP BY supplier_id
ORDER BY order_count DESC
LIMIT 10;

-- Verify date ranges
SELECT
    'Date Range' as check_type,
    MIN(created_at) as oldest_order,
    MAX(created_at) as newest_order
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 2018-12-17 to 2025-10-15

-- ============================================================================
-- ROLLBACK SCRIPT (USE ONLY IF MIGRATION FAILS)
-- ============================================================================

-- To rollback (DO NOT RUN unless there's a problem):
--
-- DELETE FROM vend_consignment_line_items
-- WHERE transfer_id IN (
--     SELECT id FROM vend_consignments
--     WHERE public_id LIKE 'PO-%'
-- );
--
-- DELETE FROM vend_consignments
-- WHERE public_id LIKE 'PO-%';
--
-- UPDATE purchase_orders
-- SET vend_consignment_id = NULL
-- WHERE vend_consignment_id IS NOT NULL;
--
-- -- Restore from backup if needed:
-- -- DROP TABLE vend_consignments;
-- -- CREATE TABLE vend_consignments AS SELECT * FROM vend_consignments_backup_20251031;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

SELECT 'Migration completed successfully!' as status;
SELECT 'Please verify in supplier portal that all historical orders are visible' as next_step;
