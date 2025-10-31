# 🧪 PHASE 1 QUICK TESTING GUIDE

**Purpose:** Verify all 7 PHASE 1 fixes are working correctly
**Time Required:** ~15 minutes
**Prerequisites:** Access to supplier portal + test data

---

## 📌 QUICK TEST SUMMARY

```
PHASE 1.1: Products Page ..................... [ ] TEST
PHASE 1.2: Dashboard Inventory ............... [ ] TEST
PHASE 1.3: Warranty Security + Analytics .... [ ] TEST
PHASE 1.4: Orders Join ....................... [ ] TEST
PHASE 1.5: Reports Dates ..................... [ ] TEST
PHASE 1.6: Account Validation ............... [ ] TEST
PHASE 1.7: Warranty Pagination .............. [ ] TEST
```

---

## ✅ TEST 1.1: Products Page

**What to check:** Products page now shows performance metrics (was just a placeholder)

**Steps:**
1. Log in as supplier
2. Navigate to **Products** page
3. Look for:
   - ✅ **4 KPI Cards** at top (Total Products, Stock Value, Low Stock, Dead Stock)
   - ✅ **Search box** to find products by SKU or name
   - ✅ **Filter options**: Period (30/90/365 days), Sort options
   - ✅ **Data table** with 12+ columns including:
     - Product name
     - Current Stock quantity
     - Inventory Value ($ amount)
     - Velocity (Fast/Normal/Slow)
     - Sell-Through %
     - Defect Rate %
     - Days Since Sale
   - ✅ **Color-coded badges** (green/yellow/red for health)
   - ✅ **Pagination** showing 25 products per page

**Expected Result:** ✅ Page displays full product analytics dashboard (not a placeholder)

**If it fails:** Check `/supplier/products.php` file exists and is 450+ lines

---

## ✅ TEST 1.2: Dashboard Inventory Calculation

**What to check:** Dashboard shows correct inventory value (supplier-specific, not global)

**Steps:**
1. Log in as supplier
2. Go to **Dashboard**
3. Look for **Card 6: Inventory Value** (or similar)
4. Check:
   - ✅ Shows a dollar amount (e.g., $12,345.67)
   - ✅ NOT showing $0.00
   - ✅ NOT showing NULL or "undefined"
   - ✅ Number is reasonable for the supplier

**Expected Result:** ✅ Inventory Value card shows accurate supplier-specific value

**If it fails:**
- Could be NULL values in database
- Check `/supplier/api/dashboard-stats.php` has the fix

**Debug:**
```bash
# Check the query in dashboard-stats.php
grep -A5 "total_inventory_value" /supplier/api/dashboard-stats.php
# Should show: AND vp.supply_price IS NOT NULL AND vp.supply_price > 0
```

---

## ✅ TEST 1.3A: Warranty Security

**What to check:** Only supplier can update their own warranty claims (security fix)

**Steps:**
1. Log in as **Supplier A**
2. Get their warranty claim ID (e.g., from warranty page)
3. Try to update claim status using the API:

**Test 1 - Valid update (should WORK):**
```bash
curl -X POST https://staff.vapeshed.co.nz/supplier/api/warranty-update.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session" \
  -d '{
    "fault_id": 123,
    "status": 1,
    "notes": "Approved - customer will contact"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Warranty claim updated",
  "fault_id": 123,
  "status": 1,
  "timestamp": "2025-10-31 14:30:00"
}
```

**Test 2 - Try to update another supplier's claim (should FAIL):**
```bash
# This should return 403 Unauthorized
```

**Expected Result:** ✅ Only supplier can update their own claims

---

## ✅ TEST 1.3B: Warranty Defect Analytics

**What to check:** Warranty page now shows defect rates by product

**Steps:**
1. Log in as supplier
2. Go to **Warranty** page
3. Look for:
   - ✅ Product name (not just fault ID)
   - ✅ **Defect Rate %** column (e.g., "2.5%" or "0.1%")
   - ✅ **Total Claims** in time period
   - ✅ **Issue Types** (if tracked)

**Expected Result:** ✅ Can see which products have quality issues

---

## ✅ TEST 1.4: Orders Join

**What to check:** Orders display correct line item totals (JOIN was using wrong column)

**Steps:**
1. Log in as supplier
2. Go to **Orders** page
3. Select an order with line items
4. Check:
   - ✅ Line items display (not blank/empty)
   - ✅ **Line Item Count** shows correct number
   - ✅ **Total Amount** calculates correctly
   - ✅ Individual item prices show

**Expected Result:** ✅ Order details show complete line item information

**If it fails:**
- Could still have wrong JOIN column
- Check `/supplier/orders.php` line 100 has: `ti.consignment_id` (not `ti.transfer_id`)

---

## ✅ TEST 1.5: Reports Date Handling

**What to check:** Reports show selected dates and validate date ranges

**Steps:**
1. Log in as supplier
2. Go to **Reports** page
3. Check:
   - ✅ **Start Date input** shows a populated date (not empty)
   - ✅ **End Date input** shows a populated date (not empty)
   - ✅ These match what's in the URL/session

**Test invalid date range:**
1. Try to set Start Date = Dec 31, 2025
2. Try to set End Date = Jan 1, 2025 (before start)
3. Submit the form

**Expected Result:** ✅ Either auto-corrects to valid range OR shows error

---

## ✅ TEST 1.6: Account Validation

**What to check:** Account updates are validated on server (not just browser)

**Steps:**
1. Log in as supplier
2. Go to **Account Settings** page
3. Try these edge cases:

**Test 1 - Invalid email:**
```
- Change email to "notanemail"
- Click Save
- Expected: Error message "Invalid email format"
```

**Test 2 - Short name:**
```
- Change name to "AB" (too short)
- Click Save
- Expected: Error message "Name must be 3+ characters"
```

**Test 3 - Valid update:**
```
- Change name to "New Supplier Name 2025"
- Click Save
- Expected: Success message, page updates
```

**Expected Result:** ✅ Server-side validation prevents invalid data

**If it fails:**
- Check `/supplier/api/account-update.php` exists
- May need to clear browser cache (validation might have worked before)

---

## ✅ TEST 1.7: Warranty Pagination

**What to check:** Warranty page only shows up to 100 claims (not 1000+)

**Steps:**
1. Log in as supplier
2. Go to **Warranty** page
3. Look for:
   - ✅ **Pending Claims section**: Shows max 100 claims
   - ✅ **Approved Claims section**: Shows max 100 claims
   - ✅ **Declined Claims section**: Shows max 100 claims
   - ✅ Page loads quickly (doesn't hang)

**Expected Result:** ✅ Each section shows limited results (pagination working)

**Debug:**
```bash
# Check if LIMIT is set in warranty queries
grep -n "LIMIT" /supplier/warranty.php
# Should show: 3 results with "LIMIT 100" or similar
```

---

## 🔴 CRITICAL: If ANY Test Fails

**Step 1: Check the specific file**
```bash
# Example for Products page
ls -la /supplier/products.php
wc -l /supplier/products.php  # Should be 450+ lines
```

**Step 2: Check for PHP syntax errors**
```bash
php -l /supplier/products.php
php -l /supplier/api/dashboard-stats.php
php -l /supplier/api/warranty-update.php
php -l /supplier/api/account-update.php
```

**Step 3: Check Apache error log**
```bash
tail -100 /path/to/apache_error.log
```

**Step 4: Enable PHP error display (temporary)**
```bash
# In test environment only - add to top of page:
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

---

## 📊 TEST RESULTS CHECKLIST

Mark each test as you complete it:

| Phase | Test | Expected | Result | Issues |
|-------|------|----------|--------|--------|
| 1.1 | Products page loads | 450+ lines, 4 KPI cards | ✅ | |
| 1.2 | Dashboard inventory | Shows $ amount | ✅ | |
| 1.3A | Warranty security | 403 on unauthorized update | ✅ | |
| 1.3B | Warranty analytics | Shows defect rates | ✅ | |
| 1.4 | Orders JOIN | Shows line items | ✅ | |
| 1.5 | Reports dates | Shows current dates | ✅ | |
| 1.6 | Account validation | Rejects invalid data | ✅ | |
| 1.7 | Warranty pagination | Max 100 per section | ✅ | |

---

## 🎯 QUICK RESULTS SUMMARY

**If ALL tests pass:**
✅ PHASE 1 is complete and ready for production

**If SOME tests fail:**
- Identify which phase failed
- Check corresponding file (see test details above)
- Verify file was modified correctly
- Check for PHP/database errors

**Next Steps After Testing:**
1. Document any issues found
2. Decide: Deploy to production or fix first
3. Plan PHASE 2 analytics dashboards
4. Schedule user training if needed

---

**Date:** October 31, 2025
**Version:** 1.0.0
**Updated By:** AI Development Agent
