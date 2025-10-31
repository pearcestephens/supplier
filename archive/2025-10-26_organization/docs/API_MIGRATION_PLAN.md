# API Migration Plan - Complete Architectural Upgrade

**Version:** 4.0.0 - Enterprise Unified Architecture  
**Date:** 2025-01-XX  
**Status:** IN PROGRESS

---

## Executive Summary

**Goal:** Migrate from fragmented API architecture (11 legacy endpoints + unified system) to a **single unified API** with centralized bootstrap, PDO-only database access, and shared resources.

**Current State:**
- ✅ Unified API system exists (`endpoint.php` → handlers) using PDO
- ❌ 11 legacy endpoints with duplicate initialization code using MySQLi
- ❌ Tabs use MySQLi via global `$db`
- ❌ No consistent bootstrap across all API files

**Target State:**
- ✅ **ONE** API endpoint receiver (`api/endpoint.php`)
- ✅ ALL API logic through handlers (`api/handlers/*.php`)
- ✅ ALL files use single bootstrap (`require_once __DIR__ . '/bootstrap.php'`)
- ✅ PDO-only database access (remove MySQLi)
- ✅ Shared session, auth, database across entire application

---

## Legacy API Endpoints Analysis

### Group 1: Order Management (4 endpoints)

#### 1. `add-order-note.php` → `Handler_Orders::addNote()`
**Purpose:** Add supplier notes to purchase orders  
**Method:** POST  
**Auth:** Required (supplier must own order)  
**Input:**
```json
{
  "order_id": 12345,
  "note": "Shipment delayed due to weather"
}
```
**Database Operations:**
- Verify order ownership: `SELECT id, notes FROM vend_consignments WHERE id = ? AND supplier_id = ?`
- Append note with timestamp: `UPDATE vend_consignments SET notes = ? WHERE id = ?`

**Migration Notes:**
- Already partially exists in `handlers/orders.php`
- Needs to append formatted note with timestamp
- Should support note formatting: `[YYYY-MM-DD HH:MM:SS] Supplier Name:\nNote text`

---

#### 2. `update-po-status.php` → `Handler_Orders::updateStatus()`
**Purpose:** Update purchase order status (SENT, CANCELLED)  
**Method:** POST  
**Auth:** Required  
**Input:**
```json
{
  "transfer_id": 12345,
  "new_status": "SENT"
}
```
**Validation:**
- Only allows: `SENT`, `CANCELLED`
- Can only update from `OPEN` state (unless cancelling)

**Database Operations:**
- Verify ownership: `SELECT id, state FROM vend_consignments WHERE id = ? AND supplier_id = ?`
- Update state: `UPDATE vend_consignments SET state = ? WHERE id = ?`
- Log activity to `supplier_activity_log`

**Migration Notes:**
- Add to existing `Handler_Orders` class
- Implement state transition rules
- Log all status changes

---

#### 3. `update-tracking.php` → `Handler_Orders::updateTracking()`
**Purpose:** Add/update tracking number for shipment  
**Method:** POST  
**Auth:** Required  
**Input:**
```json
{
  "order_id": 12345,
  "tracking_number": "1Z999AA10123456784",
  "carrier": "UPS"
}
```
**Database Operations:**
- Verify ownership
- Update tracking fields + auto-transition `OPEN` → `SENT`
```sql
UPDATE vend_consignments 
SET tracking_number = ?, 
    tracking_carrier = ?,
    tracking_updated_at = NOW(),
    state = CASE WHEN state = 'OPEN' THEN 'SENT' ELSE state END
WHERE id = ?
```

**Migration Notes:**
- Add tracking validation (format, carrier list)
- Auto-transition logic needs testing
- Notify CIS staff when tracking added

---

#### 4. `download-order.php` → `Handler_Orders::downloadSingle()`
**Purpose:** Download single order as CSV with line items  
**Method:** GET  
**Auth:** Required  
**Input:** `?order_id=12345`  
**Output:** CSV file download

**CSV Structure:**
```
Order Information
Order Number,PO-1234
Order Date,15 Jan 2025
Status,Sent
...

Line Items
Product Name,SKU,Quantity,Unit Cost,Line Total
Product A,SKU-001,10,5.00,50.00
```

**Migration Notes:**
- **Special handling:** Binary file download, not JSON response
- Set headers: `Content-Type: text/csv`, `Content-Disposition: attachment`
- Use `fputcsv()` for proper CSV formatting
- Consider: Add to unified API or keep as separate download endpoint?

---

### Group 2: Warranty Claims (3 endpoints)

#### 5. `add-warranty-note.php` → `Handler_Warranty::addNote()`
**Purpose:** Add supplier notes to warranty claims  
**Method:** POST  
**Auth:** Required  
**Input:**
```json
{
  "fault_id": 456,
  "note": "Replacement unit shipped",
  "action": "replace",
  "internal_ref": "RMA-2025-001"
}
```
**Database Operations:**
- Verify claim ownership via product supplier
- Insert into `faulty_product_notes` table
- Update `faulty_products.supplier_update_status = 1`

**Migration Notes:**
- Table: `faulty_product_notes` with columns: `faulty_product_id`, `supplier_id`, `note`, `action`, `internal_ref`, `created_at`
- Auto-mark claim as "supplier updated"

---

#### 6. `warranty-action.php` → `Handler_Warranty::processAction()`
**Purpose:** Accept or decline warranty claims  
**Method:** POST  
**Auth:** Required  
**Input:**
```json
{
  "action": "accept",
  "fault_id": 456,
  "resolution": "Replace with new unit",
  "reason": "" // or decline reason
}
```
**Actions:**
- `accept`: Requires `resolution` notes
- `decline`: Requires `reason` notes

**Database Operations:**
- Transaction-based update
- Update `faulty_products.supplier_status` (0=pending, 1=accepted, 2=declined)
- Insert note into `faulty_product_notes`
- Set `supplier_update_status = 1`

**Migration Notes:**
- Critical: Use transactions (BEGIN/COMMIT/ROLLBACK)
- Validate state transitions (can't change already processed claims)
- Email notification to CIS staff on action

---

#### 7. `download-media.php` → `Handler_Warranty::downloadMedia()`
**Purpose:** Download warranty claim photos  
**Method:** GET  
**Auth:** Required  
**Input:** `?fault_id=456`  
**Output:** ZIP file with all claim photos

**Migration Notes:**
- **Special handling:** Binary file download
- Need to read file paths from database
- Create ZIP on-the-fly or pre-generated?
- Consider: Keep as separate endpoint for large file handling

---

### Group 3: Bulk Operations (1 endpoint)

#### 8. `export-orders.php` → `Handler_Orders::exportFiltered()`
**Purpose:** Export all orders matching filters as CSV  
**Method:** GET  
**Auth:** Required  
**Input:** Query params matching Orders tab filters
```
?year=2025
&quarter=1
&status=active
&outlet=uuid-here
&search=PO-1234
```
**Output:** CSV file with all matching orders

**CSV Structure:**
```
Order Number,Date,Status,Outlet,Items,Units,Total (ex GST),Total (inc GST)
PO-1234,15 Jan 2025,Sent,Store A,5,50,500.00,575.00
```

**Migration Notes:**
- **Special handling:** Binary CSV download
- Complex filter logic (year, quarter, status, outlet, search)
- Potentially large datasets (pagination? limit?)
- Consider: Async job for very large exports?

---

### Group 4: Dashboard Support (1 endpoint)

#### 9. `notifications-count.php` → `Handler_Dashboard::getNotificationCount()`
**Purpose:** Real-time count of pending items for badge  
**Method:** GET  
**Auth:** Required  
**Output:**
```json
{
  "count": 12,
  "urgency": "warning",
  "breakdown": {
    "pending_claims": 5,
    "urgent_deliveries": 3,
    "overdue_claims": 4
  }
}
```
**Calculations:**
- **Pending claims:** Claims in pending/open/new status
- **Urgent deliveries:** Expected within 7 days
- **Overdue claims:** Pending > 7 days

**Migration Notes:**
- Already implemented in `handlers/dashboard.php`
- Verify calculations match current logic
- Cache results (30-60 seconds) to reduce database load

---

### Group 5: Unanalyzed (3 endpoints)

#### 10. `request-info.php` → ???
**Status:** Need to read file  
**Purpose:** TBD

#### 11. `update-warranty-claim.php` → ???
**Status:** Need to read file  
**Purpose:** TBD (different from warranty-action.php?)

#### 12. `download-media.php` → ???
**Status:** Analyzed above  
**Purpose:** Download warranty claim photos

---

## Handler Architecture Design

### Existing Handlers (Keep & Enhance)

#### `handlers/auth.php` → `Handler_Auth`
**Current Methods:**
- `login` - Magic link authentication
- `logout` - Session destruction

**Status:** ✅ Keep as-is (working)

---

#### `handlers/dashboard.php` → `Handler_Dashboard`
**Current Methods:**
- `getStats` - Dashboard statistics
- `getCharts` - Chart data (orders by month, claims by status)

**Add Methods:**
- `getNotificationCount` - Migrate from `notifications-count.php` ✅

---

#### `handlers/orders.php` → `Handler_Orders`
**Current Methods:**
- `getList` - Get paginated orders list
- `getDetail` - Get single order with line items

**Add Methods:**
- `addNote` - Migrate from `add-order-note.php` ✅
- `updateStatus` - Migrate from `update-po-status.php` ✅
- `updateTracking` - Migrate from `update-tracking.php` ✅
- `downloadSingle` - Migrate from `download-order.php` ⚠️ Binary response
- `exportFiltered` - Migrate from `export-orders.php` ⚠️ Binary response

---

### New Handlers (Create)

#### `handlers/warranty.php` → `Handler_Warranty`
**New Handler** - Create from scratch

**Methods:**
- `getList` - Get warranty claims list (paginated)
- `getDetail` - Get single claim with notes/photos
- `addNote` - Migrate from `add-warranty-note.php` ✅
- `processAction` - Migrate from `warranty-action.php` ✅
- `downloadMedia` - Migrate from `download-media.php` ⚠️ Binary response
- `updateClaim` - Migrate from `update-warranty-claim.php` ✅ (TBD after reading file)

---

### New Handler Pattern

```php
<?php
/**
 * Warranty Claims Handler
 * 
 * Handles all warranty-related API operations
 * 
 * @package SupplierPortal\API\Handlers
 */

declare(strict_types=1);

class Handler_Warranty {
    private PDO $pdo;
    private string $supplierID;
    
    public function __construct(PDO $pdo, string $supplierID) {
        $this->pdo = $pdo;
        $this->supplierID = $supplierID;
    }
    
    /**
     * Handle incoming requests
     * 
     * @param string $method Method name (addNote, processAction, etc.)
     * @param array $params Request parameters
     * @return array Response data
     */
    public function handle(string $method, array $params): array {
        if (!method_exists($this, $method)) {
            throw new Exception("Method not found: {$method}");
        }
        
        return $this->$method($params);
    }
    
    /**
     * Add note to warranty claim
     */
    private function addNote(array $params): array {
        // Implementation
    }
    
    /**
     * Accept or decline warranty claim
     */
    private function processAction(array $params): array {
        // Implementation with transactions
    }
}
```

---

## Migration Steps (Phased Approach)

### Phase 1: Bootstrap Enhancement ✅ COMPLETE
**Duration:** 1 hour  
**Tasks:**
- ✅ Enhance `bootstrap.php` with MySQLi + PDO initialization
- ✅ Add helper functions (`db()`, `pdo()`, `requireAuth()`, etc.)
- ✅ Add error handlers (exception + error)
- ✅ JSON request detection (`isJsonRequest()`)
- ✅ Response helper (`sendJsonResponse()`)

**Testing:**
- ✅ Verify `index.php` loads correctly
- ✅ Verify tabs still work
- ✅ Test existing unified API

---

### Phase 2: Create Warranty Handler
**Duration:** 2 hours  
**Tasks:**
- [ ] Create `api/handlers/warranty.php`
- [ ] Implement `addNote()` method
- [ ] Implement `processAction()` method (with transactions)
- [ ] Implement `getList()` and `getDetail()` methods

**Testing:**
- [ ] Test add note via unified API
- [ ] Test accept/decline actions
- [ ] Verify transaction rollback on errors

---

### Phase 3: Enhance Orders Handler
**Duration:** 2 hours  
**Tasks:**
- [ ] Add `addNote()` to `Handler_Orders`
- [ ] Add `updateStatus()` to `Handler_Orders`
- [ ] Add `updateTracking()` to `Handler_Orders`

**Testing:**
- [ ] Test each new method via unified API
- [ ] Verify state transitions work correctly
- [ ] Test validation and error handling

---

### Phase 4: Enhance Dashboard Handler
**Duration:** 30 minutes  
**Tasks:**
- [ ] Add `getNotificationCount()` to `Handler_Dashboard`
- [ ] Implement caching (30-60 seconds)

**Testing:**
- [ ] Verify count calculations match legacy endpoint
- [ ] Test cache behavior

---

### Phase 5: Handle File Downloads (Special Case)
**Duration:** 2 hours  
**Decision:** Keep as separate endpoints OR modify unified API to support binary responses?

**Option A:** Separate download endpoints (simpler)
- Keep `api/download-order.php`
- Keep `api/download-media.php`  
- Keep `api/export-orders.php`
- Update them to use `require_once bootstrap.php`
- Update to use PDO

**Option B:** Unified API with binary response support
- Modify `endpoint.php` to detect download actions
- Return file stream instead of JSON for downloads
- More complex but cleaner architecture

**Recommendation:** **Option A** for Phase 5, **Option B** as future enhancement

---

### Phase 6: Update Download Endpoints to Bootstrap
**Duration:** 1 hour  
**Tasks:**
- [ ] Update `download-order.php` to use bootstrap
- [ ] Update `download-media.php` to use bootstrap
- [ ] Update `export-orders.php` to use bootstrap
- [ ] Convert all to use PDO instead of MySQLi

---

### Phase 7: Frontend Migration
**Duration:** 3 hours  
**Tasks:**
- [ ] Update all AJAX calls to use unified API format
- [ ] Change from individual endpoints to `endpoint.php` with action parameter
- [ ] Example: `$.post('/supplier/api/add-order-note.php', data)` becomes `$.post('/supplier/api/endpoint.php', {action: 'orders.addNote', params: data})`

**Files to update:**
- `assets/js/orders.js` - Order page interactions
- `assets/js/warranty.js` - Warranty page interactions
- `assets/js/dashboard.js` - Dashboard live updates

---

### Phase 8: Remove Legacy Endpoints
**Duration:** 30 minutes  
**Tasks:**
- [ ] Archive legacy endpoints to `archive/api-legacy/`
- [ ] Remove from production:
  - `add-order-note.php`
  - `add-warranty-note.php`
  - `update-po-status.php`
  - `update-tracking.php`
  - `warranty-action.php`
  - `notifications-count.php`
  - Any other legacy endpoints

---

### Phase 9: Convert Tabs to PDO
**Duration:** 4 hours  
**Tasks:**
- [ ] Update `tabs/tab-orders.php` to use PDO
- [ ] Update `tabs/tab-warranty.php` to use PDO
- [ ] Update `tabs/tab-reports.php` to use PDO
- [ ] Update `tabs/tab-account.php` to use PDO

**Migration Pattern:**
```php
// OLD (MySQLi):
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

// NEW (PDO):
$stmt = pdo()->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

### Phase 10: Remove MySQLi (Complete PDO Migration)
**Duration:** 1 hour  
**Tasks:**
- [ ] Remove `lib/Database.php` (MySQLi class)
- [ ] Update `bootstrap.php` to remove MySQLi initialization
- [ ] Remove `db()` helper function
- [ ] Rename `pdo()` to `db()` for consistency

---

## Testing Strategy

### Unit Tests
- Test each handler method individually
- Mock PDO connections
- Test validation logic

### Integration Tests
- Test full request/response cycle through `endpoint.php`
- Test authentication checks
- Test error handling and transactions

### Regression Tests
- Compare responses from old endpoints vs new handlers
- Verify all existing functionality still works
- Test edge cases and error conditions

### Load Tests
- Test notification count endpoint (called frequently)
- Test export with large datasets
- Verify database connection pooling

---

## Rollback Plan

### Before Each Phase
1. **Backup database**
2. **Git commit** all changes
3. **Tag release** with current state
4. **Document changes** in CHANGELOG

### If Issues Arise
1. **Revert git changes:** `git reset --hard <commit>`
2. **Restore database** if schema changed
3. **Clear caches** (session, browser, opcache)
4. **Test rollback** procedure before starting

---

## Success Criteria

### Technical Metrics
- ✅ Zero legacy API endpoints remaining (all migrated or removed)
- ✅ All files use single bootstrap include
- ✅ 100% PDO, 0% MySQLi
- ✅ All API calls go through `endpoint.php`
- ✅ Response times < 500ms for 95th percentile
- ✅ Zero session sharing issues
- ✅ Zero authentication failures

### Code Quality
- ✅ PSR-12 coding standards
- ✅ PHPDoc comments on all methods
- ✅ Type hints on all parameters and returns
- ✅ Exception handling on all database operations
- ✅ Transaction support for multi-step operations
- ✅ Input validation on all API endpoints
- ✅ SQL injection prevention (prepared statements)
- ✅ CSRF protection on all forms

### Documentation
- ✅ API documentation up-to-date
- ✅ Handler methods documented
- ✅ Database schema documented
- ✅ Migration notes for future reference

---

## Timeline Estimate

| Phase | Duration | Dependencies | Status |
|-------|----------|--------------|--------|
| Phase 1: Bootstrap Enhancement | 1 hour | None | ✅ COMPLETE |
| Phase 2: Warranty Handler | 2 hours | Phase 1 | 🔄 READY |
| Phase 3: Orders Handler Enhancement | 2 hours | Phase 1 | 🔄 READY |
| Phase 4: Dashboard Handler Enhancement | 30 min | Phase 1 | 🔄 READY |
| Phase 5: File Download Strategy | 2 hours | Phase 1 | 🔄 READY |
| Phase 6: Update Download Endpoints | 1 hour | Phase 5 | ⏳ WAITING |
| Phase 7: Frontend Migration | 3 hours | Phase 2-4 | ⏳ WAITING |
| Phase 8: Remove Legacy Endpoints | 30 min | Phase 7 | ⏳ WAITING |
| Phase 9: Convert Tabs to PDO | 4 hours | Phase 8 | ⏳ WAITING |
| Phase 10: Remove MySQLi | 1 hour | Phase 9 | ⏳ WAITING |
| **TOTAL** | **17 hours** | Sequential | **IN PROGRESS** |

**Estimated Completion:** 2-3 days of focused work

---

## Next Steps

### Immediate Actions (Now)
1. ✅ Bootstrap enhancement complete
2. 🔄 Create `Handler_Warranty` class
3. 🔄 Enhance `Handler_Orders` with new methods
4. 🔄 Test unified API with new handlers

### Short-term (Today)
5. Read remaining legacy endpoints (`request-info.php`, `update-warranty-claim.php`)
6. Decide on file download strategy (Option A vs B)
7. Begin frontend AJAX migration

### Medium-term (This Week)
8. Complete all handler migrations
9. Update frontend to use unified API
10. Remove legacy endpoints
11. Convert tabs to PDO

### Long-term (Next Week)
12. Remove MySQLi completely
13. Performance testing and optimization
14. Documentation updates
15. Deploy to production

---

**Document Status:** 📝 Living Document - Update after each phase completion  
**Last Updated:** 2025-01-XX  
**Next Review:** After Phase 2 completion
