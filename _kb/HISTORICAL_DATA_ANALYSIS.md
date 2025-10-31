# Historical Purchase Orders Data Analysis

**Date:** October 31, 2025
**Issue:** Only 94 purchase orders showing in supplier portal when thousands expected

---

## Data Discovery

### Current State

**vend_consignments (PURCHASE_ORDER):**
- Total records: **94**
- All created: October 15, 2025
- All have supplier_id populated
- Date range: 2025-10-15 only

**purchase_orders (old table):**
- Total records: **11,472**
- Date range: 2018-12-17 to 2025-10-06
- Unique suppliers: **29**
- All have supplier_id populated

---

## Problem Analysis

### Root Cause
The historical purchase orders are stored in the `purchase_orders` table but have NOT been migrated or linked to the `vend_consignments` table. The supplier portal queries:

```sql
WHERE t.supplier_id = ?
AND t.transfer_category = 'PURCHASE_ORDER'
```

This only finds the 94 new records created on October 15, 2025.

### Data Mapping

**purchase_orders table:**
```
- purchase_order_id (PK)
- vend_consignment_id (NULL for all historical)
- consignment_id (only 2 populated)
- supplier_id ✓ (populated)
- outlet_id ✓ (populated)
- status
- created_at
- completed_at
- receiving_notes
```

**vend_consignments table:**
```
- id (PK)
- public_id
- supplier_id ✓ (needed for filtering)
- outlet_from
- outlet_to
- transfer_category = 'PURCHASE_ORDER'
- state
- created_at
```

---

## Top Suppliers (Historical Data)

| Supplier ID | Order Count | First Order | Last Order |
|-------------|-------------|-------------|------------|
| 02dcd191-ae71-11e8-ed44-615d5261c7a4 | 3,453 | 2018-12-21 | 2025-07-07 |
| 02dcd191-ae71-11e8-ed44-7f72b3357fbe | 2,408 | 2018-12-17 | 2025-10-06 |
| 9b4cf690-9173-4be1-b235-374697a1061c | 1,333 | 2023-07-03 | 2025-08-25 |
| 0a91b764-1c71-11eb-e0eb-d7bf46fa95c8 | 1,224 | 2021-08-17 | 2025-08-18 |
| 1c4c9afc-6295-4e91-994e-43edccb0e3e5 | 1,103 | 2024-01-16 | 2025-08-19 |

**Total across top 5 suppliers: 9,521 orders**

---

## Migration Strategy

### Option 1: Create vend_consignments Records (RECOMMENDED)

**Approach:** Create new records in vend_consignments for each historical purchase_order

**Pros:**
- Unified system - all orders in one place
- Supplier portal works immediately
- No complex joins needed
- Preserves all historical data

**Cons:**
- Adds 11,472 records to vend_consignments
- Need to handle line items migration

**SQL:**
```sql
INSERT INTO vend_consignments (
    public_id,
    supplier_id,
    outlet_from,
    outlet_to,
    transfer_category,
    state,
    created_at,
    sent_at,
    received_at,
    notes
)
SELECT
    CONCAT('PO-', purchase_order_id),
    supplier_id,
    supplier_id as outlet_from,
    outlet_id as outlet_to,
    'PURCHASE_ORDER',
    CASE
        WHEN status = 0 THEN 'OPEN'
        WHEN status = 1 THEN 'RECEIVED'
        ELSE 'CLOSED'
    END,
    date_created,
    completed_timestamp,
    last_received_at,
    receiving_notes
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NULL;
```

---

### Option 2: Update Supplier Portal Query

**Approach:** Modify supplier portal to query both tables

**Pros:**
- No data migration needed
- Preserves original table structure

**Cons:**
- Complex UNION queries
- Two sources of truth
- Performance issues with joins
- Harder to maintain

**Not Recommended**

---

## Recommended Migration Plan

### Phase 1: Backup
```sql
CREATE TABLE vend_consignments_backup_20251031
AS SELECT * FROM vend_consignments;

CREATE TABLE purchase_orders_backup_20251031
AS SELECT * FROM purchase_orders;
```

### Phase 2: Test Migration (10 records)
```sql
INSERT INTO vend_consignments (...)
SELECT ... FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NULL
LIMIT 10;
```

### Phase 3: Verify Test Data
- Check supplier portal shows 10 new orders
- Verify dates match
- Confirm supplier filtering works
- Test line items display

### Phase 4: Full Migration
```sql
-- Run full migration (11,472 records)
-- Update purchase_orders.vend_consignment_id
-- Migrate line items if needed
```

### Phase 5: Verification
```sql
SELECT
    COUNT(*) as total_po_in_vend_consignments
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 11,566 (94 existing + 11,472 migrated)
```

---

## Data Integrity Checks

### Before Migration
```sql
-- Count source records
SELECT COUNT(*) FROM purchase_orders WHERE deleted_at IS NULL;
-- Expected: 11,472

-- Count target records
SELECT COUNT(*) FROM vend_consignments WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 94
```

### After Migration
```sql
-- Verify all migrated
SELECT COUNT(*)
FROM purchase_orders
WHERE deleted_at IS NULL
AND vend_consignment_id IS NOT NULL;
-- Expected: 11,472

-- Verify total in vend_consignments
SELECT COUNT(*)
FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Expected: 11,566
```

---

## Line Items Consideration

Need to check if line items also need migration:

```sql
-- Check if purchase_order_items table exists
SHOW TABLES LIKE '%purchase_order%';

-- Check vend_consignment_line_items
SELECT COUNT(*)
FROM vend_consignment_line_items
WHERE transfer_id IN (
    SELECT id FROM vend_consignments
    WHERE transfer_category = 'PURCHASE_ORDER'
);
```

---

## Risk Assessment

**Low Risk:**
- Migration is additive (no data deletion)
- Backups will be created first
- Test migration on 10 records first
- Can be rolled back if needed

**Medium Risk:**
- Need to handle line items correctly
- Need to map status values properly
- Date fields need correct mapping

**High Risk:**
- None identified

---

## Timeline

**Total Estimated Time: 30-45 minutes**

1. Create backups (2 minutes)
2. Test migration (10 records) (5 minutes)
3. Verify test results (5 minutes)
4. Full migration (5-10 minutes)
5. Verification queries (5 minutes)
6. Supplier portal testing (10 minutes)

---

## Success Criteria

- [ ] All 11,472 historical orders visible in supplier portal
- [ ] Suppliers can only see their own orders
- [ ] Dates display correctly
- [ ] Line items show correctly (if applicable)
- [ ] No duplicate orders
- [ ] Original purchase_orders table unchanged (only vend_consignment_id updated)
- [ ] Performance remains acceptable

---

## Next Steps

1. Get approval for migration approach
2. Create migration script with proper status mapping
3. Test on 10 records
4. Review test results
5. Execute full migration
6. Verify in supplier portal
