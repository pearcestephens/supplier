# 🧪 Dashboard Widgets - Quick Testing Guide

## ⚡ Fast Test (2 minutes)

### 1. Load Dashboard Page
```
URL: https://staff.vapeshed.co.nz/supplier/dashboard.php
```

### 2. Open Browser Console (F12)
Look for these console messages:
```
✅ Dashboard JS loaded
✅ Dashboard stats loaded
✅ Stock alerts loaded (sales velocity-based)
✅ Items Sold chart loaded
✅ Warranty Claims chart loaded
```

### 3. Visual Check
- [ ] **Stock Alerts Widget** - Shows stores with low inventory (or "All stores well-stocked!")
- [ ] **Items Sold Chart** - Line chart with 3 months of data
- [ ] **Warranty Claims Chart** - Stacked bar chart with 6 months of data

---

## 🔍 Detailed API Testing

### Test 1: Stock Alerts API
```bash
curl -X GET "https://staff.vapeshed.co.nz/supplier/api/dashboard-stock-alerts.php" \
  -H "Cookie: PHPSESSID=your_session_id" \
  | jq
```

**Expected Output:**
```json
{
  "success": true,
  "stores": [
    {
      "outlet_name": "Auckland Central",
      "products_below_threshold": 45,
      "out_of_stock": 12,
      "low_stock": 33,
      "days_until_stockout": 3,
      "severity": "critical"
    }
  ],
  "alerts": [...],
  "total_stores": 17,
  "algorithm": "Sales velocity (6mo avg) * 14 days buffer"
}
```

**Check:**
- ✅ `success: true`
- ✅ `stores` array populated (or empty if well-stocked)
- ✅ `days_until_stockout` is a number
- ✅ `severity` is "critical", "high", or "medium"
- ✅ `algorithm` shows the calculation method

---

### Test 2: Items Sold API
```bash
curl -X GET "https://staff.vapeshed.co.nz/supplier/api/dashboard-items-sold.php" \
  -H "Cookie: PHPSESSID=your_session_id" \
  | jq
```

**Expected Output:**
```json
{
  "success": true,
  "chart_data": {
    "labels": ["Oct 2024", "Nov 2024", "Dec 2024"],
    "datasets": [
      {
        "label": "Units Sold",
        "data": [1250, 1420, 1680]
      },
      {
        "label": "Transactions",
        "data": [320, 365, 425]
      }
    ]
  },
  "summary": {
    "total_units": 4350,
    "total_revenue": 45250.50,
    "units_change": 12.5
  }
}
```

**Check:**
- ✅ `success: true`
- ✅ `chart_data.labels` has 3 entries (last 3 months)
- ✅ `datasets` has 2 arrays (Units Sold + Transactions)
- ✅ `summary.units_change` shows percentage vs previous period

---

### Test 3: Warranty Claims API
```bash
curl -X GET "https://staff.vapeshed.co.nz/supplier/api/dashboard-warranty-claims.php" \
  -H "Cookie: PHPSESSID=your_session_id" \
  | jq
```

**Expected Output:**
```json
{
  "success": true,
  "chart_data": {
    "labels": ["Jul 24", "Aug 24", "Sep 24", "Oct 24", "Nov 24", "Dec 24"],
    "datasets": [
      {
        "label": "Approved",
        "data": [12, 15, 18, 20, 17, 19]
      },
      {
        "label": "Rejected",
        "data": [3, 2, 4, 3, 2, 1]
      },
      {
        "label": "Pending",
        "data": [5, 6, 4, 7, 8, 6]
      }
    ]
  },
  "summary": {
    "overall_approval_rate": 67.2,
    "approval_rate_change": 3.8
  },
  "top_reasons": [...]
}
```

**Check:**
- ✅ `success: true`
- ✅ `chart_data.labels` has 6 entries (last 6 months)
- ✅ `datasets` has 3 arrays (Approved/Rejected/Pending)
- ✅ `top_reasons` shows most common claim reasons

---

## 🎯 Performance Testing

### 1. Page Load Speed
```javascript
// Open browser console
performance.timing.loadEventEnd - performance.timing.navigationStart
```
**Target:** < 1000ms (1 second)

### 2. Widget Load Speed
Watch console timestamps:
```
[12:00:00.100] ✅ Dashboard JS loaded
[12:00:00.250] ✅ Stock alerts loaded (sales velocity-based)
[12:00:00.280] ✅ Items Sold chart loaded
[12:00:00.310] ✅ Warranty Claims chart loaded
```
**Target:** All widgets < 500ms each

### 3. Parallel Loading
All 3 APIs should load simultaneously (not sequentially).

**Check Network Tab:**
- Stock alerts, Items Sold, and Warranty Claims requests should have **similar start times**
- Not waterfall (one after another)

---

## ❌ Error Scenarios

### Test 1: Invalid Session (401)
```bash
curl "https://staff.vapeshed.co.nz/supplier/api/dashboard-stock-alerts.php"
```
**Expected:** Redirect to login or 401 error

### Test 2: Database Connection Failure
Simulate by temporarily breaking `bootstrap.php`.

**Expected:**
- Widget shows: "Error loading stock alerts"
- Console: `❌ Stock alerts error: [error message]`
- Other widgets still load (failure is isolated)

### Test 3: No Data
Test with supplier that has no sales.

**Expected:**
- Stock Alerts: "All stores well-stocked!"
- Items Sold: Empty chart or "No data"
- Warranty Claims: Empty chart or "No claims"

---

## 🔧 Debugging Tips

### Console Not Showing Logs?
```javascript
// Force reload without cache
Ctrl + Shift + R (Windows/Linux)
Cmd + Shift + R (Mac)
```

### Chart Not Rendering?
1. Check if Chart.js is loaded:
   ```javascript
   typeof Chart !== 'undefined'
   ```
2. Inspect canvas element:
   ```javascript
   document.getElementById('itemsSoldChart')
   ```
3. Check for JavaScript errors in console

### API Returns 500 Error?
1. Check PHP error log:
   ```bash
   tail -50 /path/to/supplier/logs/php_errors.log
   ```
2. Check database connection:
   ```php
   try {
       $pdo = pdo();
       echo "Connected!";
   } catch (Exception $e) {
       echo "Error: " . $e->getMessage();
   }
   ```

---

## ✅ Success Checklist

### Visual Checks:
- [ ] Stock Alerts widget populated (or shows "well-stocked")
- [ ] Items Sold chart has 3 data points (months)
- [ ] Warranty Claims chart has 6 data points (months)
- [ ] No JavaScript errors in console
- [ ] No blank/broken widgets
- [ ] Loading spinners disappear after load

### Functional Checks:
- [ ] Stock Alerts shows "days until stockout"
- [ ] Stock Alerts uses sales velocity (not reorder_point)
- [ ] Items Sold shows both Units and Transactions
- [ ] Warranty Claims shows Approved/Rejected/Pending breakdown
- [ ] Widgets load independently (one failure doesn't break others)

### Performance Checks:
- [ ] Page loads in < 1 second
- [ ] Widgets load in parallel (check Network tab)
- [ ] No blocking queries
- [ ] Console shows load times

---

## 🚀 Quick Fix Commands

### Refresh Dashboard Cache
```bash
# Clear browser cache
Ctrl + Shift + Delete

# Force JS reload
?v=<?php echo time(); ?>
```

### Check Database Performance
```sql
-- Check if sales query is slow
EXPLAIN SELECT
    SUM(sli.quantity)
FROM vend_sale_line_items sli
JOIN vend_sales s ON sli.sale_id = s.id
WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### Restart PHP-FPM (if needed)
```bash
sudo systemctl restart php-fpm
```

---

## 📞 Support

### If Stock Alerts Show No Data:
- Check if supplier has sales in last 6 months
- Check if products are marked `active = 1`
- Check if `deleted_at IS NULL` in query

### If Charts Don't Render:
- Verify Chart.js is loaded: `<script src="...chart.js"></script>`
- Check canvas IDs match: `itemsSoldChart`, `warrantyChart`
- Check for conflicting CSS that hides canvas

### If API Returns 500:
- Check PHP error log
- Verify database credentials in `bootstrap.php`
- Test database connection manually
- Check required extensions (PDO, mysqlnd)

---

**Last Updated:** December 2024
**Status:** Production-Ready ✅
