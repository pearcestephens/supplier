# 🧪 HOW TO RUN YOUR EXISTING TEST TOOLS

You already have **excellent PHP-based testing tools** in `/supplier/tests/`!

---

## ✅ Your Existing Test Tools

### 1. **comprehensive-api-test.php** - Complete API Testing Suite
- Tests all API endpoints
- Tests login authentication
- Tests page loads
- Tests error handling
- Colored terminal output
- Success/fail summary

### 2. **comprehensive-page-test.php** - Page Load Testing
- Tests all tabs (dashboard, orders, warranty, etc.)
- Checks for SQL errors
- Validates runtime issues
- Uses real database connections

### 3. **sql-validator.php** - SQL Query Validation
- Validates SQL syntax
- Checks for common errors

### 4. **Test Shell Scripts:**
- `quick-session-test.sh`
- `test-session-fix.sh`
- `test-session-protocol.sh`
- `test-sidebar-stats.sh`

---

## 🚀 HOW TO RUN THEM

### Option 1: Run Comprehensive API Test (RECOMMENDED)

```bash
# SSH into your server
ssh your-server

# Navigate to supplier directory
cd /home/master/applications/jcepnzzkmj/public_html/supplier

# Run the comprehensive API test
php tests/comprehensive-api-test.php
```

**Expected Output:**
```
==========================================
   Supplier Portal API Test Suite
   Phase A + Phase B Validation
==========================================

Testing: Login Authentication... ✅ PASS
Testing: Notifications Count API... ✅ PASS
Testing: Add Order Note API... ✅ PASS
Testing: Dashboard Tab Load... ✅ PASS
[... more tests ...]

==========================================
           TEST SUMMARY
==========================================
Total Tests: 20
Passed: 20
Failed: 0
Success Rate: 100.0%
==========================================

🎉 ALL TESTS PASSED! System is ready for production.
```

---

### Option 2: Run Page Load Test

```bash
php tests/comprehensive-page-test.php
```

This will:
- ✅ Test each tab loads without errors
- ✅ Check for SQL syntax errors
- ✅ Verify no PHP fatal errors
- ✅ Test with real supplier ID

---

### Option 3: Run Shell Script Tests

```bash
# Make executable (if not already)
chmod +x tests/*.sh

# Run session test
./tests/quick-session-test.sh

# Run sidebar stats test
./tests/test-sidebar-stats.sh
```

---

## ⚙️ BEFORE RUNNING - CONFIGURE

### Edit comprehensive-api-test.php:

```bash
nano tests/comprehensive-api-test.php
```

**Update these lines (around line 14-16):**
```php
define('BASE_URL', 'https://staff.vapeshed.co.nz/supplier');
define('TEST_SUPPLIER_ID', 'YOUR_REAL_UUID_HERE'); // ⚠️ Change this!
```

**Update login credentials (around line 93-94):**
```php
'username' => 'your_test_username', // ⚠️ Change this!
'password' => 'your_test_password', // ⚠️ Change this!
```

---

### Edit comprehensive-page-test.php:

```bash
nano tests/comprehensive-page-test.php
```

**Update line 17:**
```php
$testSupplierID = 'YOUR_REAL_SUPPLIER_UUID_HERE'; // ⚠️ Change this!
```

---

## 🎯 WHAT GETS TESTED

### API Endpoints Tested:
- ✅ `/api/notifications-count.php`
- ✅ `/api/add-order-note.php`
- ✅ `/api/add-warranty-note.php`
- ✅ `/api/request-info.php`
- ✅ `/api/update-po-status.php`
- ✅ `/api/update-tracking.php`
- ✅ `/api/update-warranty-claim.php`
- ✅ `/api/warranty-action.php`

### Pages Tested:
- ✅ Index page (login redirect)
- ✅ Dashboard tab
- ✅ Orders tab
- ✅ Warranty tab
- ✅ Reports tab
- ✅ Downloads tab
- ✅ Account tab

### Security Tested:
- ✅ Authentication required
- ✅ Session validation
- ✅ Unauthenticated access blocked (401/403)

### Error Handling Tested:
- ✅ Bad requests return proper errors
- ✅ Missing fields handled gracefully
- ✅ Invalid data rejected

---

## 📊 INTERPRET RESULTS

### ✅ All Tests Pass:
```
🎉 ALL TESTS PASSED! System is ready for production.
```
**Meaning:** Everything works! Deploy with confidence.

### ❌ Some Tests Fail:
```
⚠️  Some tests failed. Review above for details.

Failed Tests:
  ❌ Add Order Note API: Missing required field: order_id
  ❌ Dashboard Tab Load: HTTP 500
```

**Action Required:**
1. Check error logs: `tail -f logs/apache_*.error.log`
2. Fix the reported issues
3. Re-run tests
4. Repeat until all pass

---

## 🔍 DETAILED DEBUGGING

### If API Tests Fail:

```bash
# Test specific API manually
curl -X POST https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php \
  -H "Content-Type: application/json" \
  -b "session_token=YOUR_TOKEN" \
  | jq .

# Expected output:
{
  "success": true,
  "data": {
    "po_count": 123,
    "items_count": 456,
    "total_value": 78900.50
  }
}
```

### If Page Tests Fail:

```bash
# Check PHP syntax
php -l tabs/tab-dashboard.php

# Check for SQL errors in logs
grep -i "SQL" logs/apache_*.error.log | tail -20

# Check for fatal errors
grep -i "fatal" logs/apache_*.error.log | tail -20
```

---

## 💡 PRO TIPS

### Run Tests After Every Change:
```bash
# Quick test loop
while true; do
  clear
  php tests/comprehensive-api-test.php
  echo ""
  echo "Press Ctrl+C to stop, or wait 30s for next run..."
  sleep 30
done
```

### Save Test Results:
```bash
# Save with timestamp
php tests/comprehensive-api-test.php > test_results/api_test_$(date +%Y%m%d_%H%M%S).log 2>&1

# Review later
cat test_results/api_test_*.log
```

### Run Before Every Deployment:
```bash
# Add to deployment script
php tests/comprehensive-api-test.php
if [ $? -ne 0 ]; then
  echo "❌ Tests failed - deployment aborted"
  exit 1
fi
echo "✅ Tests passed - proceeding with deployment"
```

---

## 🎉 QUICK START (30 Seconds)

```bash
# 1. Navigate to supplier directory
cd /home/master/applications/jcepnzzkmj/public_html/supplier

# 2. Update test supplier ID
sed -i "s/define('TEST_SUPPLIER_ID', '1')/define('TEST_SUPPLIER_ID', 'YOUR_UUID_HERE')/" tests/comprehensive-api-test.php

# 3. Run tests
php tests/comprehensive-api-test.php

# 4. Check results
# If all pass: 🎉 Deploy!
# If some fail: Check logs and fix
```

---

## 📝 COMPARISON: Your Tools vs My Scripts

| Feature | Your `comprehensive-api-test.php` | My `test-comprehensive.sh` |
|---------|----------------------------------|----------------------------|
| **Language** | PHP (native to project) | Bash (shell script) |
| **Execution** | Direct PHP execution | Requires curl/jq tools |
| **Auth** | Built-in session handling | Manual cookie management |
| **Database** | Can test SQL directly | Cannot test DB |
| **Page Loads** | Full PHP includes tested | Only HTTP responses |
| **Error Detail** | Can catch PHP errors | Only HTTP status |
| **Setup** | Change 2 lines | Change session token |
| **Best For** | Development testing | CI/CD pipelines |

**VERDICT:** 🏆 **Your PHP tools are BETTER for this project!**

---

## ✅ RECOMMENDATION

**Use your existing tools!** They're:
- ✅ Already integrated with your codebase
- ✅ Test actual PHP execution (not just HTTP)
- ✅ Can catch SQL errors directly
- ✅ Include session management
- ✅ Have detailed error reporting
- ✅ Colored terminal output

**Just run:**
```bash
php tests/comprehensive-api-test.php
php tests/comprehensive-page-test.php
```

**That's it!** No need for my bash scripts - your PHP tools are superior! 🚀
