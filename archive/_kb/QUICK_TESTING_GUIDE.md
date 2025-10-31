# 🧪 QUICK TESTING GUIDE - Supplier Portal

**Test all features in 15 minutes**

---

## ✅ TEST SEQUENCE

### 1. LOGIN (1 min)
```
URL: https://staff.vapeshed.co.nz/supplier/?supplier_id={YOUR_UUID}
Expected: Redirects to dashboard, shows supplier name in header
```

---

### 2. SIDEBAR (2 min)
**Check:**
- [ ] Logo displays in top left
- [ ] Badge on "Warranty" link shows count (red if > 0)
- [ ] Badge on "Orders" link shows count (yellow if > 0)
- [ ] Recent Activity widget shows last 4 orders
- [ ] Quick Stats widget shows 3 metrics with progress bars
- [ ] All links work (Dashboard, Orders, Warranty, Reports, Downloads, Account)

**Test Auto-Refresh:**
Wait 2 minutes → Recent Activity should update

---

### 3. DASHBOARD TAB (2 min)
**Check:**
- [ ] 4 stat cards show numbers (Total Orders, Pending, Received, Total Value)
- [ ] Orders Trend chart displays (line chart)
- [ ] Orders by Outlet chart displays (bar chart)
- [ ] Top Products chart displays (horizontal bar)
- [ ] Low Stock Alerts widget shows products (or "No alerts")
- [ ] Recent Orders table shows orders
- [ ] "Download All CSV" button downloads CSV file
- [ ] "Print Dashboard" button opens print dialog

**Test:**
```javascript
// Open browser console
console.log('Charts loaded:', Chart.instances.length); // Should be 3
```

---

### 4. ORDERS TAB (3 min)
**Check:**
- [ ] Orders table displays
- [ ] Status badges colored correctly (green=RECEIVED, blue=SENT, etc.)
- [ ] Filter by status works
- [ ] Date range picker works
- [ ] Search box filters results
- [ ] Pagination works (if > 25 orders)

**Test Order Details:**
- [ ] Click "View Details" on any order
- [ ] Modal opens with full order information
- [ ] Items table shows products, quantities, costs
- [ ] Tracking number field editable
- [ ] "Update Tracking" button saves (check for success toast)

**Test Bulk Tracking:**
- [ ] Click "Bulk Update Tracking" button
- [ ] Paste CSV: `JCE-PO-12345,TRACK123` (format shown in prompt)
- [ ] See toast: "1 tracking number ready to update"

**Test Export:**
- [ ] Click "Export Filtered" button
- [ ] CSV file downloads with current filter applied

---

### 5. WARRANTY TAB (2 min)
**Check:**
- [ ] Warranty claims table displays
- [ ] Status badges: Yellow=Pending, Green=Accepted, Red=Declined
- [ ] Filter by status works
- [ ] Search works

**Test Claim Action:**
- [ ] Click "View Details" on pending claim
- [ ] Modal shows product info, issue description, photos (if any)
- [ ] Click "Accept Claim" or "Decline Claim"
- [ ] Enter response notes
- [ ] Submit → Check for success toast
- [ ] Close modal → Status badge should update

**Test Export:**
- [ ] Click "Export to CSV" button
- [ ] CSV downloads with all claims
- [ ] Open CSV → Verify has summary section at bottom

---

### 6. REPORTS TAB (2 min)
**Check:**
- [ ] Period selector shows: 7 days, 30 days, 90 days, Custom
- [ ] Select "Last 30 days"
- [ ] Summary stats display (Orders, Units, Value, with trend arrows)
- [ ] Orders by Status chart (donut) displays
- [ ] Orders by Outlet chart (bar) displays
- [ ] Detailed orders table displays

**Test Custom Range:**
- [ ] Click "Custom Date Range"
- [ ] Select start date and end date
- [ ] Click "Apply"
- [ ] Report refreshes with new data

**Test Export:**
- [ ] Click "Export Report"
- [ ] CSV downloads with filtered data

---

### 7. DOWNLOADS TAB (2 min)
**Check:**
- [ ] Stats show: Total orders count, Total warranties count

**Test Quick Downloads:**
- [ ] Click "All Orders (CSV)" → Downloads all orders CSV
- [ ] Click "Warranty Claims (CSV)" → Downloads all warranties CSV
- [ ] Click "Monthly Report (PDF)" → Shows message (PDF not implemented yet - OK)

**Test Period Reports:**
- [ ] Click "This Month" → Generates CSV report for current month
- [ ] Click "Last Month" → Generates CSV for previous month
- [ ] Click "Year to Date" → Generates CSV from Jan 1 to today

**Test Custom Period:**
- [ ] Select start date and end date
- [ ] Select format: CSV
- [ ] Click "Generate Report"
- [ ] CSV downloads with custom date range

---

### 8. ACCOUNT TAB (1 min)
**Check:**
- [ ] Profile shows: Company name, Email (with verified badge), Phone, Website, Member since
- [ ] Account stats show: Total Orders, Warranty Claims, Active Products
- [ ] Session info shows: Status (Active), Login time, Last activity, Session duration
- [ ] Recent Activity list shows last 10 activities (or "No recent activity")

**Test Profile Edit:**
- [ ] Click "Edit Profile" button
- [ ] Edit form appears with current values
- [ ] Change phone number
- [ ] Click "Save Changes"
- [ ] See success toast
- [ ] Form switches back to view mode
- [ ] Verify phone number updated in view mode

**Test Validation:**
- [ ] Click "Edit Profile"
- [ ] Clear email field
- [ ] Try to save → Should show "Email address is required"
- [ ] Enter invalid email (e.g., "notanemail")
- [ ] Try to save → Should show "Invalid email address format"

**Test Logout:**
- [ ] Click "Logout" button
- [ ] Redirects to login page
- [ ] Try to access dashboard without login → Redirects back to login

---

## 🔒 SECURITY TESTS

### Multi-tenancy Check:
```sql
-- In database, get two different supplier_id values
SELECT id, name FROM vend_suppliers LIMIT 2;
```

1. Login as Supplier A
2. Note their order count
3. Logout
4. Login as Supplier B
5. Verify different order count (should NOT see Supplier A's data)

### SQL Injection Test:
In search box, try:
```
' OR '1'='1
```
Expected: No results or error (query is parameterized, so injection fails)

### XSS Test:
In order notes, try:
```html
<script>alert('XSS')</script>
```
Expected: Saved as text, displays as text (not executed)

---

## 📊 PERFORMANCE TESTS

### Load Time:
```javascript
// Open browser console on dashboard
performance.timing.loadEventEnd - performance.timing.navigationStart
// Should be < 2000ms (2 seconds)
```

### AJAX Speed:
```javascript
// Monitor Network tab while sidebar loads
// sidebar-stats.php should complete < 500ms
```

### CSV Export:
- Export 100+ orders
- Should complete < 3 seconds
- File size should be reasonable (< 1MB for 100 orders)

---

## 🐛 COMMON ISSUES & FIXES

### Issue: Sidebar widgets not loading
**Fix:** Check browser console for errors. Verify `/api/sidebar-stats.php` returns JSON.

### Issue: Charts not displaying
**Fix:** Verify Chart.js loaded. Check console for "Chart is not defined" error.

### Issue: CSV downloads empty
**Fix:** Check supplier has data. Verify database queries in PHP error log.

### Issue: Profile edit doesn't save
**Fix:** Check `/api/update-profile.php` exists. Verify JSON response in Network tab.

### Issue: Session expires too quickly
**Fix:** Check `php.ini` session.gc_maxlifetime is 86400 (24 hours).

---

## ✅ PASS CRITERIA

**Portal is PRODUCTION READY if:**
- [ ] All 8 tabs load without errors
- [ ] All CSV exports download successfully
- [ ] Profile editing saves correctly
- [ ] Sidebar widgets display data
- [ ] Charts render on dashboard
- [ ] Filters work on all tabs
- [ ] No "Coming Soon" messages visible
- [ ] No PHP errors in error log
- [ ] No JavaScript errors in console
- [ ] Multi-tenancy verified (suppliers see only their data)

---

## 🎯 QUICK TEST SCRIPT

**Run this in browser console on dashboard:**

```javascript
// Comprehensive Portal Health Check
(async function() {
    console.log('🧪 Starting Portal Health Check...\n');
    
    const tests = {
        'Sidebar Widgets': () => document.getElementById('sidebar-active-orders') !== null,
        'Charts Loaded': () => typeof Chart !== 'undefined' && Chart.instances.length > 0,
        'Recent Orders Table': () => document.querySelector('.table tbody tr') !== null,
        'Notification Badges': () => document.querySelectorAll('.navbar-nav .badge').length > 0,
        'Session Active': () => document.querySelector('.badge.bg-success') !== null
    };
    
    let passed = 0, failed = 0;
    
    for (const [name, test] of Object.entries(tests)) {
        try {
            if (test()) {
                console.log(`✅ ${name}`);
                passed++;
            } else {
                console.log(`❌ ${name}`);
                failed++;
            }
        } catch(e) {
            console.log(`❌ ${name} - Error: ${e.message}`);
            failed++;
        }
    }
    
    console.log(`\n📊 Results: ${passed} passed, ${failed} failed`);
    console.log(failed === 0 ? '🎉 Portal is HEALTHY!' : '⚠️ Some issues detected');
})();
```

**Expected Output:**
```
🧪 Starting Portal Health Check...

✅ Sidebar Widgets
✅ Charts Loaded
✅ Recent Orders Table
✅ Notification Badges
✅ Session Active

📊 Results: 5 passed, 0 failed
🎉 Portal is HEALTHY!
```

---

## 📞 SUPPORT

**If any test fails:**
1. Check browser console for errors
2. Check PHP error log: `/logs/apache_*.error.log`
3. Verify database connection in `config.php`
4. Verify all API files exist in `/api/` directory
5. Check file permissions (PHP files should be 755)

**All tests passing?**
✅ **PORTAL IS PRODUCTION READY!**

---

**Testing Time:** 15 minutes  
**Pass Rate Required:** 100% for production deployment  
**Last Updated:** January 25, 2025
