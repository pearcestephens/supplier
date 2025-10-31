# Phase 3 Action Plan - Complete Remaining Legacy Endpoints

## Summary of Remaining Work

### Legacy Endpoints Still to Migrate (3 files)

#### 1. `request-info.php` → `Handler_Orders::requestInfo()`
**Purpose:** Request additional information from Vape Shed staff about an order  
**Method:** POST  
**Input:**
```json
{
  "order_id": 456,
  "message": "Need clarification on delivery address"
}
```
**Database:** Creates ticket in `supplier_info_requests` table  
**Action:** Add to `Handler_Orders` as new method

---

#### 2. `update-warranty-claim.php` → Already covered by `warranty.processAction()`
**Purpose:** Update warranty claim status (accept/decline)  
**Status:** ✅ **ALREADY IMPLEMENTED** in `warranty.processAction()`  
**Note:** This file is **duplicate functionality** - can be removed after frontend migration

---

#### 3. File Downloads (Keep as separate endpoints)
**Decision:** Keep these as separate endpoints but update to use bootstrap + PDO

**Files:**
- `download-order.php` - Single order CSV
- `download-media.php` - Warranty photos ZIP
- `export-orders.php` - Filtered orders CSV

**Reason:** Binary file responses work better as separate endpoints

---

## Quick Implementation Plan

### Step 1: Add `requestInfo()` to Orders Handler (15 minutes)

**Add to `/api/handlers/orders.php`:**
```php
/**
 * Request additional information from Vape Shed staff
 * 
 * @param array $params {
 *     @type int $order_id Order ID
 *     @type string $message Request message
 * }
 * @return array Success response with request ID
 */
public function requestInfo(array $params): array {
    $orderId = (int)($params['order_id'] ?? 0);
    $message = trim($params['message'] ?? '');
    
    if ($orderId <= 0 || empty($message)) {
        throw new Exception('order_id and message are required', 400);
    }
    
    // Verify ownership...
    // Insert into supplier_info_requests...
    // Return success
}
```

---

### Step 2: Update Download Endpoints to Use Bootstrap (30 minutes)

**For each file (`download-order.php`, `download-media.php`, `export-orders.php`):**

1. Replace initialization:
```php
// OLD:
require_once '../lib/Database.php';
require_once '../lib/Session.php';
$db = Database::connect();

// NEW:
require_once __DIR__ . '/../bootstrap.php';
$db = db(); // or pdo() if converting to PDO
```

2. Keep binary response logic as-is (CSV headers, ZIP generation, etc.)

---

### Step 3: Frontend Migration (~2 hours)

**Update JavaScript files:**
- `assets/js/orders.js`
- `assets/js/warranty.js`
- `assets/js/dashboard.js`

**Pattern:**
```javascript
// OLD:
$.post('/supplier/api/add-order-note.php', data, callback);

// NEW:
$.post('/supplier/api/endpoint.php', {
    action: 'orders.addNote',
    params: data
}, callback);
```

---

### Step 4: Remove Legacy Endpoints (10 minutes)

**After frontend working, archive these:**
```bash
mkdir -p archive/api-legacy-oct2025
mv api/add-order-note.php archive/api-legacy-oct2025/
mv api/add-warranty-note.php archive/api-legacy-oct2025/
mv api/update-po-status.php archive/api-legacy-oct2025/
mv api/update-tracking.php archive/api-legacy-oct2025/
mv api/warranty-action.php archive/api-legacy-oct2025/
mv api/notifications-count.php archive/api-legacy-oct2025/
mv api/update-warranty-claim.php archive/api-legacy-oct2025/
# Keep: request-info.php until requestInfo() method added
# Keep: download-*.php, export-*.php (binary responses)
```

---

## Estimated Timeline

| Task | Time | Priority |
|------|------|----------|
| Fix typo in warranty.php | ✅ DONE | Critical |
| Add requestInfo() method | 15 min | High |
| Update download endpoints | 30 min | Medium |
| Frontend migration | 2 hours | High |
| Remove legacy endpoints | 10 min | Low |
| **TOTAL** | **~3 hours** | - |

---

## Testing After Each Step

### Test requestInfo()
```bash
curl -X POST https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -H "Cookie: SUPPLIER_PORTAL_SESSION=xxx" \
  -d '{"action":"orders.requestInfo","params":{"order_id":1,"message":"Test request"}}'
```

### Test Download Endpoints
```bash
# Should still work after bootstrap update
curl -O https://staff.vapeshed.co.nz/supplier/api/download-order.php?order_id=1 \
  -H "Cookie: SUPPLIER_PORTAL_SESSION=xxx"
```

### Test Frontend
1. Open supplier portal in browser
2. Login with magic link
3. Test each feature that was migrated
4. Check browser console for errors
5. Verify API calls go to unified endpoint

---

## Success Criteria

- ✅ All API functionality works through unified endpoint
- ✅ File downloads still work (CSV, ZIP)
- ✅ Frontend uses unified API format
- ✅ Legacy endpoints archived
- ✅ No JavaScript console errors
- ✅ All features tested and working

---

## MOVE FORWARD NOW

**Priority Actions:**
1. ✅ Typo fixed in warranty.php
2. Add `requestInfo()` method to orders handler
3. Update download endpoints to use bootstrap
4. Begin frontend migration
5. Archive legacy endpoints

**Next File to Edit:** `/supplier/api/handlers/orders.php` (add requestInfo method)
