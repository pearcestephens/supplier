#!/bin/bash

# Historical Purchase Orders Migration Script
# Date: 2025-10-31
# Purpose: Migrate 11,472 purchase orders to vend_consignments

# Database credentials
DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "Historical Purchase Orders Migration"
echo "=========================================="
echo ""

# Function to run SQL and check result
run_sql() {
    local sql="$1"
    local description="$2"

    echo -e "${YELLOW}Running: $description${NC}"
    mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "$sql" 2>&1

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Success${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ Failed${NC}"
        echo ""
        return 1
    fi
}

# Phase 1: Backup
echo "=========================================="
echo "PHASE 1: Creating Backups"
echo "=========================================="

run_sql "CREATE TABLE IF NOT EXISTS vend_consignments_backup_20251031 AS SELECT * FROM vend_consignments;" "Backup vend_consignments"
run_sql "CREATE TABLE IF NOT EXISTS purchase_orders_backup_20251031 AS SELECT * FROM purchase_orders;" "Backup purchase_orders"

# Phase 2: Pre-migration verification
echo "=========================================="
echo "PHASE 2: Pre-Migration Verification"
echo "=========================================="

echo "Source records to migrate:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT COUNT(*) as source_records
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NULL;
" 2> /dev/null

echo ""
echo "Current PURCHASE_ORDER records in vend_consignments:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT COUNT(*) as current_records
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
" 2> /dev/null

echo ""
read -p "Continue with test migration (10 records)? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Migration cancelled."
    exit 1
fi

# Phase 3: Test migration
echo ""
echo "=========================================="
echo "PHASE 3: Test Migration (10 records)"
echo "=========================================="

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME << 'EOF' 2>&1

-- Migrate 10 test records
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
AND po.vend_consignment_id IS NULL
ORDER BY po.date_created ASC
LIMIT 10;

-- Link back to purchase_orders
UPDATE purchase_orders po
INNER JOIN vend_consignments vc ON vc.public_id = CONCAT('PO-', po.purchase_order_id)
SET po.vend_consignment_id = vc.id
WHERE po.vend_consignment_id IS NULL
AND vc.public_id LIKE 'PO-%'
LIMIT 10;

SELECT 'Test migration completed' as status;
EOF

if [ $? -ne 0 ]; then
    echo -e "${RED}Test migration failed!${NC}"
    exit 1
fi

echo -e "${GREEN}Test migration successful!${NC}"
echo ""

# Phase 4: Migrate test line items
echo "=========================================="
echo "PHASE 4: Migrate Test Line Items"
echo "=========================================="

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME << 'EOF' 2>&1

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
WHERE po.vend_consignment_id IS NOT NULL
AND vc.public_id LIKE 'PO-%'
LIMIT 1000;

SELECT 'Test line items migrated' as status;
EOF

echo -e "${GREEN}Test line items migrated!${NC}"
echo ""

# Verification
echo "=========================================="
echo "TEST VERIFICATION"
echo "=========================================="

echo "Migrated test records:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT
    po.purchase_order_id,
    vc.public_id,
    vc.supplier_id,
    vc.state,
    vc.created_at,
    COUNT(vcli.id) as line_items
FROM purchase_orders po
INNER JOIN vend_consignments vc ON po.vend_consignment_id = vc.id
LEFT JOIN vend_consignment_line_items vcli ON vc.id = vcli.transfer_id
WHERE vc.public_id LIKE 'PO-%'
GROUP BY po.purchase_order_id
ORDER BY po.date_created ASC
LIMIT 10;
" 2> /dev/null

echo ""
echo -e "${YELLOW}=========================================="
echo "IMPORTANT: VERIFY IN SUPPLIER PORTAL"
echo "==========================================${NC}"
echo ""
echo "Please verify the following:"
echo "1. Log into supplier portal"
echo "2. Check that 10 historical orders appear"
echo "3. Verify line items display correctly"
echo "4. Check that dates are correct"
echo "5. Confirm suppliers can only see their orders"
echo ""

read -p "Verification complete? Continue with FULL migration? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Migration paused. Test records remain in database."
    echo "To rollback test, run: mysql ... < rollback-test.sql"
    exit 0
fi

# Phase 5: Full migration
echo ""
echo "=========================================="
echo "PHASE 5: FULL MIGRATION"
echo "=========================================="
echo -e "${YELLOW}Migrating all remaining records...${NC}"
echo ""

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME << 'EOF' 2>&1

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

-- Update purchase_orders
UPDATE purchase_orders po
INNER JOIN vend_consignments vc ON vc.public_id = CONCAT('PO-', po.purchase_order_id)
SET po.vend_consignment_id = vc.id
WHERE po.vend_consignment_id IS NULL
AND vc.public_id LIKE 'PO-%';

SELECT 'Full migration completed' as status;
EOF

if [ $? -ne 0 ]; then
    echo -e "${RED}Full migration failed!${NC}"
    echo "Database has been partially migrated. Contact support."
    exit 1
fi

echo -e "${GREEN}Full migration successful!${NC}"
echo ""

# Phase 6: Migrate all line items
echo "=========================================="
echo "PHASE 6: Migrate All Line Items"
echo "=========================================="
echo -e "${YELLOW}Migrating line items (this may take a few minutes)...${NC}"
echo ""

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME << 'EOF' 2>&1

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
WHERE po.deleted_at IS NULL
AND vc.public_id LIKE 'PO-%';

SELECT 'All line items migrated' as status;
EOF

echo -e "${GREEN}Line items migrated!${NC}"
echo ""

# Phase 7: Final verification
echo "=========================================="
echo "PHASE 7: Final Verification"
echo "=========================================="

echo ""
echo "Total purchase orders migrated:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT COUNT(*) as migrated_orders
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NOT NULL;
" 2> /dev/null

echo ""
echo "Total PURCHASE_ORDER records in vend_consignments:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT COUNT(*) as total_purchase_orders
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
" 2> /dev/null

echo ""
echo "Total line items migrated:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT COUNT(*) as total_line_items
FROM vend_consignment_line_items vcli
INNER JOIN vend_consignments vc ON vcli.transfer_id = vc.id
WHERE vc.transfer_category = 'PURCHASE_ORDER'
AND vc.public_id LIKE 'PO-%';
" 2> /dev/null

echo ""
echo "Date range:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT
    MIN(created_at) as oldest_order,
    MAX(created_at) as newest_order
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
" 2> /dev/null

echo ""
echo "Top 10 suppliers by order count:"
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT
    supplier_id,
    COUNT(*) as order_count
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER'
GROUP BY supplier_id
ORDER BY order_count DESC
LIMIT 10;
" 2> /dev/null

echo ""
echo -e "${GREEN}=========================================="
echo "MIGRATION COMPLETED SUCCESSFULLY!"
echo "==========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Test supplier portal thoroughly"
echo "2. Verify all suppliers can see their historical orders"
echo "3. Check that line items display correctly"
echo "4. Monitor performance"
echo ""
echo "Backup tables created:"
echo "- vend_consignments_backup_20251031"
echo "- purchase_orders_backup_20251031"
echo ""
echo "These can be dropped after 30 days if no issues."
