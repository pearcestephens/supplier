# 🚀 Enable Sidebar Quick Stats - Step-by-Step Guide

## Current Status
The sidebar widgets are **disabled** because of previous database errors. However, the API and functions exist and are ready to use.

---

## 🔧 How to Enable

### Step 1: Uncomment the Initialization

**File:** `/supplier/assets/js/sidebar-widgets.js`
**Lines:** 186-193

**Current (DISABLED):**
```javascript
// Auto-initialize when DOM is ready
// DISABLED: initSidebarWidgets() causing database errors every 2 minutes
// if (document.readyState === 'loading') {
//     document.addEventListener('DOMContentLoaded', initSidebarWidgets);
// } else {
//     initSidebarWidgets();
// }
```

**Change to (ENABLED):**
```javascript
// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarWidgets);
} else {
    initSidebarWidgets();
}
```

### Step 2: Test in Browser

1. **Open:** https://staff.vapeshed.co.nz/supplier/dashboard.php
2. **Open DevTools:** F12 → Console tab
3. **Look for:**
   - ✅ No error messages
   - ✅ "Sidebar widgets loaded" message (if added)
   - ✅ Numbers appear in Quick Stats section

4. **Check Sidebar:**
   ```
   Quick Stats
   ├─ Active Orders: [number] [progress bar]
   ├─ Orders This Week: [number] [progress bar]
   ├─ Completed This Week: [number] [progress bar]
   └─ Products Listed: [number] [progress bar]
   ```

### Step 3: If Errors Appear

**Check the API directly:**
```bash
curl -H "Cookie: PHPSESSID=your-session" \
  https://staff.vapeshed.co.nz/supplier/api/sidebar-stats.php
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "active_orders": {
            "count": 18,
            "percent": 80
        },
        "orders_this_week": {
            "count": 45
        },
        "completed_this_week": {
            "count": 32
        },
        "products_listed": {
            "count": 128
        },
        "recent_activity": [...]
    }
}
```

**If API returns errors:**
1. Check `/supplier/api/sidebar-stats.php` for database queries
2. Look for missing columns or wrong table names
3. Check error logs: `/logs/apache_*.error.log`

---

## 🔍 Troubleshooting Database Errors

### Common Issues:

#### 1. Missing Column Error
**Error:** `Unknown column 'deleted_at' in 'where clause'`

**Fix:** Check if `vend_consignments` table has `deleted_at` column:
```sql
SHOW COLUMNS FROM vend_consignments LIKE 'deleted_at';
```

If missing, either:
- Add column: `ALTER TABLE vend_consignments ADD COLUMN deleted_at DATETIME NULL;`
- Remove from query: Remove `AND deleted_at IS NULL` from all queries

#### 2. Wrong Table Name
**Error:** `Table 'database.vend_products' doesn't exist`

**Fix:** Check actual table name:
```sql
SHOW TABLES LIKE 'vend_%';
```

Update table name in API if different.

#### 3. Connection Timeout
**Error:** `Lost connection to MySQL server`

**Fix:** Optimize queries:
- Add indexes on frequently queried columns
- Use `LIMIT` on large result sets
- Check slow query log

---

## 📝 Alternative: Disable Auto-Refresh

If widgets work but the **auto-refresh** causes issues:

**File:** `/supplier/assets/js/sidebar-widgets.js`
**Lines:** 180-182

**Current:**
```javascript
// DISABLED: Refresh stats every 2 minutes
// setInterval(loadSidebarStats, 120000);
```

**Keep Disabled** - Only load on page load, not every 2 minutes.

---

## 🎯 What the Sidebar Should Show

### HTML Location:
Look for this in `/supplier/dashboard.php`:
```html
<div id="sidebar-active-orders">
    <strong>-</strong>
    <span>Active Orders</span>
    <div class="progress">
        <div class="progress-bar"></div>
    </div>
</div>
```

### After Loading:
```html
<div id="sidebar-active-orders">
    <strong>18</strong>  ← Updated from API
    <span>Active Orders</span>
    <div class="progress">
        <div class="progress-bar" style="width: 80%;"></div>  ← Animated
    </div>
</div>
```

---

## 🧪 Test Checklist

### ✅ Before Enabling:
- [ ] Check API exists: `/supplier/api/sidebar-stats.php`
- [ ] Test API response manually with curl
- [ ] Verify no database errors in API response
- [ ] Check sidebar HTML exists in dashboard.php

### ✅ After Enabling:
- [ ] Page loads without errors
- [ ] Console shows no JavaScript errors
- [ ] Sidebar shows numbers instead of "-"
- [ ] Progress bars animate to correct width
- [ ] Recent activity section loads (if enabled)

### ✅ After 2 Minutes (Auto-Refresh):
- [ ] Stats update automatically (if enabled)
- [ ] No 500 errors in network tab
- [ ] No database timeout errors

---

## 🚨 Emergency Rollback

If enabling causes issues:

**Quick Fix:**
```javascript
// In sidebar-widgets.js, line 175:
function initSidebarWidgets() {
    console.log('Sidebar widgets disabled - database schema fixes needed');
    return; // ← Add this line to disable

    // Rest of code below...
}
```

Or just re-comment the initialization:
```javascript
// if (document.readyState === 'loading') {
//     document.addEventListener('DOMContentLoaded', initSidebarWidgets);
// } else {
//     initSidebarWidgets();
// }
```

---

## 📊 Expected Behavior

### On Page Load:
1. Dashboard loads with skeleton loaders on cards
2. Sidebar shows "-" in all Quick Stats
3. JavaScript loads dashboard stats API
4. JavaScript loads sidebar stats API
5. Dashboard cards fade in with data
6. Sidebar updates with numbers and progress bars

### Total Load Time:
- Dashboard API: ~200-500ms
- Sidebar API: ~100-300ms
- **Total:** < 1 second for full page load

### Network Requests:
```
GET /supplier/api/modules/dashboard-stats.php  → 200 OK (dashboard cards)
GET /supplier/api/dashboard-stock-alerts.php   → 200 OK (stock alerts)
GET /supplier/api/sidebar-stats.php            → 200 OK (sidebar widgets)
```

---

## 💡 Pro Tips

### 1. Add Console Logging
Add to `loadSidebarStats()` in sidebar-widgets.js:
```javascript
function loadSidebarStats() {
    console.log('📊 Loading sidebar stats...');

    fetch('/supplier/api/sidebar-stats.php')
        .then(response => response.json())
        .then(data => {
            console.log('✅ Sidebar stats loaded:', data);
            if (data.success) {
                updateSidebarStats(data.data);
            }
        })
        .catch(error => {
            console.error('❌ Sidebar stats error:', error);
        });
}
```

### 2. Check Database Queries
Add to `/supplier/api/sidebar-stats.php`:
```php
// At the top after requireAuth()
error_log('📊 Sidebar Stats API called by supplier: ' . getSupplierID());

// Before each query
error_log('🔍 Running query: Active Orders');
```

### 3. Test with Different Suppliers
Make sure API works for suppliers with:
- 0 orders
- Hundreds of orders
- New suppliers (no data)
- Inactive suppliers

---

## 🎯 Success Criteria

✅ **Working Correctly When:**
- Page loads without errors
- Sidebar shows real numbers
- Progress bars animate smoothly
- No console errors
- No 500 errors in network tab
- Numbers update on page refresh

❌ **Something's Wrong If:**
- Sidebar still shows "-" after load
- Console shows database errors
- Network tab shows 500 errors on sidebar-stats.php
- Progress bars don't animate
- Page loads slower than 2 seconds

---

**Priority:** HIGH (User requested "THESE NEED TO BE HOOKED UP NOW")
**Difficulty:** EASY (Just uncomment 3 lines)
**Risk:** LOW (Can easily rollback if issues)
**Time:** 2 minutes to enable, 5 minutes to test
