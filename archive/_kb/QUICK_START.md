# ⚡ QUICK START - Dashboard Testing (5 Minutes)

**Status:** Ready to test NOW  
**Time Required:** 5 minutes  
**Expected Result:** Fully working dashboard  

---

## 🚀 OPTION 1: FASTEST TEST (3 commands)

```bash
# 1. Test APIs
cd /home/master/applications/jcepnzzkmj/public_html/supplier && php test-dashboard-api.php

# 2. Activate dashboard
cd tabs && mv tab-dashboard.php tab-dashboard-v3-backup.php && mv tab-dashboard-v4-demo-perfect.php tab-dashboard.php

# 3. Open browser
# Go to: https://staff.vapeshed.co.nz/supplier/index.php?tab=dashboard
```

**Expected Result:**
- ✅ Terminal shows: "4 passed, 0 failed"
- ✅ Browser shows: 6 cards + table + alerts + 2 charts
- ✅ Console shows: 4x "✅ loaded" messages

---

## 🧪 OPTION 2: DETAILED TEST (With verification)

### Step 1: Test APIs (2 min)
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier
php test-dashboard-api.php
```

**Look for:**
```
========================================
DASHBOARD API TEST SUITE
========================================

TEST: dashboard-stats
✅ PASSED

TEST: dashboard-orders-table
✅ PASSED

TEST: dashboard-stock-alerts
✅ PASSED

TEST: dashboard-charts
✅ PASSED

========================================
RESULTS: 4 passed, 0 failed
========================================
```

**If ANY fail:** Check logs:
```bash
tail -100 logs/apache_*.error.log
```

---

### Step 2: Activate Dashboard (30 sec)
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/tabs
mv tab-dashboard.php tab-dashboard-v3-backup.php
mv tab-dashboard-v4-demo-perfect.php tab-dashboard.php
```

**Verify:**
```bash
ls -lah tab-dashboard.php
# Should show ~700 lines, ~25KB file size
```

---

### Step 3: Load in Browser (2 min)

1. **Open:** https://staff.vapeshed.co.nz/supplier/index.php?tab=dashboard

2. **Press F12** (Open DevTools Console)

3. **Check Console Output:**
   ```
   Dashboard loading...
   ✅ Dashboard stats loaded
   ✅ Orders table loaded
   ✅ Stock alerts loaded
   ✅ Charts loaded
   ```

4. **Visual Check:**
   - [ ] See 6 metric cards (with blue/green/yellow/purple icons)
   - [ ] Cards show numbers (not spinners)
   - [ ] Orders table has 10 rows
   - [ ] Stock alerts show 6 store cards
   - [ ] 2 charts rendered (line + stacked bar)
   - [ ] NO red errors in console

5. **Interaction Check:**
   - [ ] Hover over cards → lift effect
   - [ ] Click "Pending Claims" card → goes to warranty tab
   - [ ] Resize window → layout responsive

---

## ✅ SUCCESS CRITERIA

**Dashboard is working when:**
- ✅ APIs return `{"success": true}`
- ✅ Browser shows all widgets
- ✅ Console shows 4x "loaded" messages
- ✅ No red errors anywhere
- ✅ Charts render properly

---

## 🐛 QUICK FIXES

### Issue: "Test failed: dashboard-stats"
**Fix:**
```bash
# Check API directly
curl https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php

# Check logs
tail -100 logs/apache_*.error.log | grep dashboard
```

### Issue: "Chart is not defined"
**Fix:** Chart.js CDN failed to load. Check:
```html
<!-- Should be in tab-dashboard.php -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
```

### Issue: "Cannot find element ID"
**Fix:** Dashboard tab not activated. Run Step 2 again.

### Issue: "CSS looks broken"
**Fix:** Add CSS file to index.php:
```php
<link rel="stylesheet" href="assets/css/dashboard-widgets.css">
```

---

## 📞 IF STUCK

**Check these 3 things:**
1. APIs work: `php test-dashboard-api.php` → All pass?
2. File activated: `ls -lah tabs/tab-dashboard.php` → ~25KB?
3. Browser console: F12 → Any red errors?

**If still stuck:**
Read: `DASHBOARD_READY_FOR_TESTING.md` (detailed troubleshooting)

---

## 🎉 WHEN WORKING

**You should see:**
- Dashboard loads in < 2 seconds
- All 6 cards show real numbers
- Orders table populated with actual orders
- Stock alerts show stores (even if mock data)
- Both charts render with animation
- Console: 4x green checkmarks

**Then:** ✅ **DASHBOARD MIGRATION COMPLETE!**

---

**Time to Success:** < 5 minutes  
**Difficulty:** Easy (just 3 commands)  
**Result:** Fully working dashboard  

🚀 **GO TEST NOW!**
