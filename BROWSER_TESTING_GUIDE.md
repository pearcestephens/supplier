# 🌐 Browser Testing Guide

## Quick Browser Verification (5 Minutes)

### Step 1: Open Supplier Portal
1. Navigate to: `https://staff.vapeshed.co.nz/supplier/`
2. Log in with supplier credentials

### Step 2: Open DevTools
- **Chrome/Edge:** Press `F12` or `Ctrl+Shift+I`
- **Firefox:** Press `F12` or `Ctrl+Shift+I`
- Click the **Network** tab

### Step 3: Watch API Calls
1. Click on the **Dashboard** menu item (if not already there)
2. The page will load
3. In Network tab, filter by "XHR" or "Fetch"
4. You should see requests to: `/supplier/api/?action=...`

### Step 4: Inspect Responses
Click on any API request in the Network tab:
- **Status:** Should be `200 OK`
- **Response tab:** Click to see JSON
- **Preview tab:** See formatted JSON structure

### Example - dashboard-stats Response
```json
{
  "success": true,
  "message": "Dashboard statistics loaded successfully",
  "data": {
    "total_orders": 150,
    "active_products": 42,
    "pending_claims": 3,
    ...
  }
}
```

---

## What To Look For

### ✅ Good Response (Expected)
- Status: `200 OK`
- Content-Type: `application/json`
- Response has `"success": true`
- Data object is populated
- No error messages

### ❌ Bad Response (If Something's Wrong)
- Status: `401 Unauthorized` → Session expired, need to log in again
- Status: `500 Internal Server Error` → Check browser console for error details
- Response has `"success": false` → Read `error.message` for details

---

## Testing Specific Endpoints

### Test 1: Dashboard Stats
**What:** Load dashboard page
**Watches For:** `/api/?action=dashboard-stats`
**Expected:** Total orders, products, revenue metrics

### Test 2: Dashboard Charts
**What:** Wait 1 second after dashboard loads
**Watches For:** `/api/?action=dashboard-charts`
**Expected:** Chart data arrays for graphs

### Test 3: Orders Table
**What:** Scroll down on dashboard
**Watches For:** `/api/?action=dashboard-orders-table`
**Expected:** Recent orders list

### Test 4: Sidebar Stats
**What:** Page load (sidebar loads automatically)
**Watches For:** `/api/?action=sidebar-stats`
**Expected:** Notification counts, alerts

---

## Testing Error Handling

### Test Error Modal
1. Open DevTools → Console
2. Run: `API.call('non-existent-action')`
3. **Expected:** Red error modal pops up showing:
   - Error code
   - Error message
   - Request ID
   - "Reload Page" button

### Test Success Toast
1. Console: `API.showSuccess('Test successful!')`
2. **Expected:** Green toast notification appears top-right

---

## Common Issues & Solutions

### Issue: "Authentication Required" Error
**Cause:** Session expired
**Solution:** Refresh page and log in again

### Issue: Network request shows "Failed"
**Cause:** Server connection issue
**Solution:** Check internet connection, verify server is running

### Issue: Response is HTML instead of JSON
**Cause:** PHP error occurred
**Solution:** Check `/logs/apache_*.error.log` for PHP errors

---

## Advanced Testing (Optional)

### Test with curl
```bash
# Get session cookie from browser (DevTools → Application → Cookies)
# Copy PHPSESSID value

curl -X POST 'https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats' \
  -H 'Cookie: PHPSESSID=your-session-id-here' \
  -H 'Content-Type: application/json'
```

### Test with Postman
1. Create new request
2. Method: POST
3. URL: `https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats`
4. Headers: Add `Cookie: PHPSESSID=...` from browser
5. Send
6. Inspect JSON response

---

## Performance Check

In Network tab, check:
- **Time:** Most endpoints should respond < 500ms
- **Size:** Response size reasonable (< 100KB for most)
- **Waterfall:** No blocking/slow queries

If any endpoint takes > 1 second:
1. Check database slow query log
2. Review SQL queries in module file
3. Add indexes if needed

---

## ✅ Success Criteria

You can consider testing PASSED when:
- ✅ All API calls return 200 status
- ✅ All responses are valid JSON
- ✅ All responses have `success: true`
- ✅ Data is displayed correctly on page
- ✅ No console errors
- ✅ No red error modals
- ✅ Page loads in < 3 seconds

---

## 🎯 Final Verification Checklist

- [ ] Logged into supplier portal
- [ ] Opened DevTools Network tab
- [ ] Loaded dashboard page
- [ ] Saw API requests complete
- [ ] Clicked on request, verified 200 status
- [ ] Checked Response tab, saw valid JSON
- [ ] Checked Preview tab, saw `success: true`
- [ ] Verified data displays on page
- [ ] No errors in Console tab
- [ ] Tested navigation between pages
- [ ] All pages load data correctly

**If all checkboxes ✅ → API IS FULLY FUNCTIONAL** 🎉

---

**Ready for Production:** Yes
**Browser Testing:** Recommended
**Time Required:** 5 minutes
**Difficulty:** Easy
