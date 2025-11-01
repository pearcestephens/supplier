# Transfer Category Analysis - Complete System Breakdown

**Date:** November 1, 2025
**Scope:** All transfer types across all systems
**Purpose:** Understand the migration and data flow for ST, PO, IN, JT categories

---

## Transfer Category Acronyms

| Acronym | Full Name | System | Description |
|---------|-----------|--------|-------------|
| **ST** | STOCK | queue_consignments | Stock transfers (includes migrated Purchase Orders!) |
| **PO** | PURCHASE_ORDER | queue_consignments, vend_consignments | Purchase orders |
| **IN** | INTERNAL | queue_consignments | Internal transfers |
| **JT** | JUICE | queue_consignments | Juice transfers |
| - | SUPPLIER | vend_consignments | Lightspeed supplier consignments |
| - | OUTLET | vend_consignments | Lightspeed outlet transfers |
| - | RETURN | vend_consignments | Lightspeed returns |

---

## System-Wide Statistics

### queue_consignments (PRIMARY ACTIVE SYSTEM)

**Total Records:** 30,706 across 60 suppliers (Dec 2016 - Oct 2025)

| Category | Records | Suppliers | PO Linked | Native | Earliest | Latest | Status |
|----------|---------|-----------|-----------|--------|----------|--------|--------|
| **STOCK** | 23,523 | 60 | 11,532 (49%) | 11,991 (51%) | Dec 2016 | **Oct 31, 2025** | ✅ ACTIVE |
| **JUICE** | 3,716 | 0 | 0 | 3,716 (100%) | Apr 2019 | Oct 4, 2025 | ✅ ACTIVE |
| **INTERNAL** | 3,466 | 0 | 0 | 3,466 (100%) | Mar 2020 | Jul 4, 2025 | ✅ ACTIVE |
| **PURCHASE_ORDER** | 1 | 0 | 0 | 1 (100%) | Oct 23, 2025 | Oct 23, 2025 | 🧪 TEST |

**TOTAL:** 30,706 records

---

### vend_consignments (LIGHTSPEED SYSTEM)

**Total Records:** 12,812 across 26 suppliers (Dec 2016 - Oct 2025)

| Type | Records | Suppliers | Earliest | Latest | Primary Status |
|------|---------|-----------|----------|--------|----------------|
| **SUPPLIER** | 245 | 27 | Jul 2018 | **Oct 24, 2025** | PO from suppliers |
| **OUTLET** | 12,563 | 0 | Dec 2016 | **Oct 21, 2025** | Inter-outlet transfers |
| **RETURN** | 4 | 0 | Oct 2020 | Oct 2020 | Returns |

**TOTAL:** 12,812 records

---

### purchase_orders (LEGACY SYSTEM - SOURCE)

**Total Records:** 11,470 completed orders across 29 suppliers (Dec 2018 - Sep 2025)

- **No category field** (all are purchase orders by definition)
- Last activity: September 2, 2025
- **Migration to queue_consignments:** 11,469 of 11,470 = **99.99% complete**
- All migrated as `transfer_category='STOCK'` (NOT 'PURCHASE_ORDER')

---

## Test Supplier Breakdown (0a91b764-1c71-11eb-e0eb-d7bf46fa95c8)

### queue_consignments

| Category | Status | Count | Has PO Link | Earliest | Latest |
|----------|--------|-------|-------------|----------|--------|
| **STOCK** | RECEIVED | 1,258 | 1,224 (97%) | Aug 2021 | Oct 22, 2025 |
| **STOCK** | OPEN | 18 | 18 (100%) | Oct 6, 2025 | Oct 6, 2025 |

**Total:** 1,276 records (all STOCK category)

**Breakdown:**
- 1,242 migrated from purchase_orders (PO linked)
- 34 native stock transfers (no PO link)
- 0 JUICE transfers
- 0 INTERNAL transfers
- 0 PURCHASE_ORDER category records

### vend_consignments

| Type | Status | Count | Earliest | Latest |
|------|--------|-------|----------|--------|
| SUPPLIER | OPEN | 18 | Oct 15, 2025 | Oct 23, 2025 |

**Total:** 18 records (pilot/test data only)

### purchase_orders (Source)

- **1,224 completed orders** (Aug 2021 - Aug 2025)
- All migrated to queue_consignments as `transfer_category='STOCK'`

---

## Critical Discovery: Migration Strategy

### ✅ What Happened During Migration

**User performed migration "a little while ago":**

1. **Source:** `purchase_orders` table (11,470 completed POs)
2. **Target:** `queue_consignments` table
3. **Tracking:** `cis_purchase_order_id` field (FK to purchase_orders)
4. **Marker:** `vend_consignment_id = 'MIGRATED-PO-{purchase_order_id}'`
5. **Category Assignment:** ALL migrated POs → `transfer_category='STOCK'` ⚠️
6. **Status:** RECEIVED for completed, OPEN for recent/active
7. **Completeness:** 11,469 of 11,470 = 99.99% ✅

### ❌ Why Portal Shows Wrong Data

**orders.php Current Query:**
```php
FROM vend_consignments t
WHERE t.transfer_category = 'PURCHASE_ORDER'  // ❌ Wrong!
  AND t.supplier_id = ?
```

**Result:** Finds 0 records (or pilot data only)

**Why It Fails:**
1. Migrated POs are in `queue_consignments` (not `vend_consignments`)
2. Migrated POs have `transfer_category='STOCK'` (not 'PURCHASE_ORDER')
3. vend_consignments only has recent pilot/test data (Oct 15, 2025+)

---

## The Fix Strategy

### Phase 1: Display All Historical Purchase Orders ✅ READY

**Update orders.php to query:**
```php
FROM queue_consignments qc
WHERE qc.supplier_id = ?
  AND qc.transfer_category = 'STOCK'
  AND qc.cis_purchase_order_id IS NOT NULL  // Only purchase orders
ORDER BY qc.created_at DESC
```

**Expected Result:**
- Test supplier: 18 → 1,242 orders (6,900% increase)
- All suppliers: Proportional increases

### Phase 2: Separate Views by Transfer Type 🎯 RECOMMENDED

Create separate tabs/pages for each category:

1. **Purchase Orders (PO)** - Filter: `transfer_category='STOCK' AND cis_purchase_order_id IS NOT NULL`
2. **Stock Transfers (ST)** - Filter: `transfer_category='STOCK' AND cis_purchase_order_id IS NULL`
3. **Juice Transfers (JT)** - Filter: `transfer_category='JUICE'`
4. **Internal Transfers (IN)** - Filter: `transfer_category='INTERNAL'`

### Phase 3: Unify with Lightspeed Data (Future) 🔮

Eventually merge queue_consignments → vend_consignments:
- Sync historical data to Lightspeed
- Use vend_consignments as single source of truth
- Maintain queue_consignments for legacy reference

---

## Data Discrepancies Explained

### Why Test Supplier Has More Records in Queue Than PO Table

**purchase_orders:** 1,224 completed orders
**queue_consignments (PO linked):** 1,242 records (+18)
**queue_consignments (all STOCK):** 1,276 records (+52)

**Explanation:**
1. **+18 records:** Orders updated/reopened after completion
   - Same purchase_order_id appears multiple times in queue
   - Represents order lifecycle (created → updated → completed)
2. **+34 records:** Native stock transfers (no PO link)
   - Direct stock movements not originating from purchase orders
   - Pure inventory transfers between locations

### Why JUICE and INTERNAL Show 0 Suppliers

These categories don't have `supplier_id` populated:
- **JUICE:** Inter-outlet juice transfers (no supplier involved)
- **INTERNAL:** Internal stock movements (no supplier involved)
- These are purely internal logistics operations

---

## Acronym Context in Files

### Where You'll See These

**In Code:**
```php
// queue_consignments.transfer_category
'STOCK'          // ST - Stock transfers + migrated POs
'PURCHASE_ORDER' // PO - Direct purchase orders (rare, 1 record)
'JUICE'          // JT - Juice transfers
'INTERNAL'       // IN - Internal movements
```

**In vend_consignments:**
```php
// vend_consignments.type
'SUPPLIER'  // Supplier consignments (Lightspeed's version of PO)
'OUTLET'    // Inter-outlet transfers (Lightspeed's version of ST)
'RETURN'    // Product returns
```

---

## System Status Summary

### Active Systems (October 2025)

1. **queue_consignments** ✅ PRIMARY ACTIVE
   - Last activity: October 31, 2025 (YESTERDAY!)
   - Contains: All historical data + current operations
   - Categories: STOCK (23,523), JUICE (3,716), INTERNAL (3,466)
   - **This is where suppliers should see their data**

2. **vend_consignments** ✅ ACTIVE (Pilot)
   - Last activity: October 24, 2025
   - Contains: Recent pilot data (Oct 15, 2025+) + old outlet transfers
   - Types: SUPPLIER (245), OUTLET (12,563)
   - **Not fully integrated yet**

3. **purchase_orders** 📦 LEGACY
   - Last activity: September 2, 2025
   - Status: Migration complete, read-only reference
   - **Historical source only**

---

## Recommended Action Plan

### Immediate (This Session)

1. ✅ **COMPLETED:** Comprehensive analysis of all transfer categories
2. 🔥 **NEXT:** Update orders.php to query queue_consignments + STOCK + cis_purchase_order_id IS NOT NULL
3. 🔥 **VERIFY:** Test supplier sees 1,242 purchase orders (not 18)

### Short Term (Next Week)

4. 📊 Create separate views/tabs for each transfer type:
   - Purchase Orders (PO) - Historical + current supplier orders
   - Stock Transfers (ST) - Native stock movements
   - Juice Transfers (JT) - Juice logistics
   - Internal Transfers (IN) - Internal movements

5. 🎨 Update UI to show transfer category badges/icons

### Long Term (Future Sprints)

6. 🔄 Plan queue → vend sync strategy
7. 🧹 Consolidate duplicate systems
8. 📈 Create unified reporting across all categories

---

## Key Takeaways

1. ✅ **Migration successful:** 99.99% of purchase_orders → queue_consignments
2. ⚠️ **Naming confusion:** Migrated POs stored as 'STOCK' category (not 'PURCHASE_ORDER')
3. 🎯 **Portal fix needed:** Query queue_consignments, not vend_consignments
4. 📊 **Multiple categories exist:** ST, PO, IN, JT all actively used
5. 🔮 **Future state:** Eventually unify queue + vend systems

**User was correct:** Data IS "in queue but not vend consignment tables"!
