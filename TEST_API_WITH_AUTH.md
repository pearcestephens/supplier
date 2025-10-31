# 🧪 API Testing Guide - With Authentication

## 🔐 Method 1: Browser Cookie Export (EASIEST)

### Step 1: Login in Browser
1. Open browser and go to: `https://staff.vapeshed.co.nz/supplier/login.php`
2. Login with valid credentials
3. Open Developer Tools (F12)
4. Go to **Application** tab → **Cookies**
5. Find cookies for `staff.vapeshed.co.nz`

### Step 2: Copy Session Cookie
Look for cookie named: `PHPSESSID` or `supplier_session`
Copy the value: `abc123def456...`

### Step 3: Test with curl
```bash
# Replace YOUR_SESSION_ID with the cookie value
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats" \
  -H "Cookie: PHPSESSID=YOUR_SESSION_ID" \
  -H "Content-Type: application/json"
```

### Step 4: Save cookies.txt for reuse
```bash
# Export cookies from browser using extension or:
# In browser console:
document.cookie.split(';').forEach(c => console.log(c.trim()));

# Create cookies.txt file:
cat > cookies.txt << 'EOF'
# Netscape HTTP Cookie File
staff.vapeshed.co.nz	FALSE	/	TRUE	0	PHPSESSID	YOUR_SESSION_ID
EOF

# Now use in all requests:
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats" \
  -b cookies.txt \
  -H "Content-Type: application/json"
```

---

## 🔓 Method 2: Create Test Auth Bypass (DEVELOPMENT ONLY)

### Create: `/supplier/api/test-auth.php`
```php
<?php
/**
 * TEST AUTH BYPASS - DEVELOPMENT ONLY
 * DO NOT DEPLOY TO PRODUCTION
 *
 * Usage: Include this BEFORE bootstrap.php in test scripts
 */

// Set test supplier ID in session
if (!isset($_SESSION)) {
    session_start();
}

// REPLACE WITH REAL SUPPLIER ID FROM DATABASE
$_SESSION['supplier_id'] = '12345678-1234-1234-1234-123456789abc';
$_SESSION['authenticated'] = true;
$_SESSION['supplier_name'] = 'Test Supplier';
$_SESSION['supplier_email'] = 'test@example.com';

echo "✅ Test auth bypass activated - Supplier ID: {$_SESSION['supplier_id']}\n\n";
```

### Create: `/supplier/api/test-endpoint.php`
```php
<?php
/**
 * Test Endpoint with Auth Bypass
 *
 * Usage: php test-endpoint.php dashboard-stats
 */

// Enable test auth bypass
require_once __DIR__ . '/test-auth.php';

// Now load bootstrap (will see test session)
require_once dirname(__DIR__) . '/bootstrap.php';

// Get action from command line
$action = $argv[1] ?? 'health';

echo "Testing action: {$action}\n";
echo str_repeat('-', 50) . "\n\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = $action;

// Capture output
ob_start();
require __DIR__ . '/index.php';
$output = ob_get_clean();

echo $output;
echo "\n\n";
```

### Test from command line:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/api
php test-endpoint.php dashboard-stats
php test-endpoint.php sidebar-stats
php test-endpoint.php dashboard-charts
```

---

## 🎯 Method 3: Direct Session Manipulation

### Get Real Supplier ID from Database:
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT id, name, email FROM suppliers LIMIT 5;"
```

### Create Login Script: `/supplier/scripts/test-login.sh`
```bash
#!/bin/bash
# Test login and save session

SUPPLIER_EMAIL="test@example.com"
SUPPLIER_PASSWORD="password123"
BASE_URL="https://staff.vapeshed.co.nz/supplier"

echo "🔐 Logging in..."

# Login and save cookies
curl -c cookies.txt -X POST "${BASE_URL}/login.php" \
  -d "email=${SUPPLIER_EMAIL}" \
  -d "password=${SUPPLIER_PASSWORD}" \
  -L -v 2>&1 | grep -i "set-cookie"

echo ""
echo "✅ Cookies saved to cookies.txt"
echo ""
echo "Test API call:"
curl -b cookies.txt -X POST "${BASE_URL}/api/?action=dashboard-stats" \
  -H "Content-Type: application/json" | jq .

echo ""
echo "💾 Cookies file:"
cat cookies.txt
```

Make executable and run:
```bash
chmod +x scripts/test-login.sh
./scripts/test-login.sh
```

---

## 🧪 Method 4: Postman/Insomnia Setup

### Postman Collection:
1. **Create new request**: `POST https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats`
2. **Headers**:
   - `Content-Type: application/json`
   - `Cookie: PHPSESSID=YOUR_SESSION_ID`
3. **Body** (JSON):
   ```json
   {
     "action": "dashboard-stats"
   }
   ```

### Get Session ID:
- Login in browser first
- Copy cookie value from DevTools
- Paste into Postman Cookie header

### Save as Collection:
Create collection with all endpoints:
- Dashboard Stats
- Dashboard Charts
- Dashboard Orders Table
- Sidebar Stats
- Add Order Note
- Update Profile
- etc.

---

## 🔍 Method 5: Browser DevTools Testing

### Open Console in Logged-in Browser:
```javascript
// Test dashboard stats
fetch('/supplier/api/?action=dashboard-stats', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    credentials: 'same-origin'
})
.then(r => r.json())
.then(data => console.log(data));

// Test sidebar stats
fetch('/supplier/api/?action=sidebar-stats', {
    method: 'POST',
    credentials: 'same-origin'
})
.then(r => r.json())
.then(data => console.log(data));

// Test update profile
fetch('/supplier/api/?action=update-profile', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'update-profile',
        name: 'Test Name',
        email: 'test@example.com'
    }),
    credentials: 'same-origin'
})
.then(r => r.json())
.then(data => console.log(data));
```

---

## 📝 Method 6: Automated Test Suite

### Create: `/supplier/tests/api-test-suite.php`
```php
<?php
/**
 * API Test Suite with Auth
 *
 * Usage: php tests/api-test-suite.php
 */

// Setup test auth
require_once __DIR__ . '/../api/test-auth.php';
require_once dirname(__DIR__) . '/bootstrap.php';

class APITester {
    private $baseUrl = 'https://staff.vapeshed.co.nz/supplier/api/';
    private $cookieFile = '/tmp/api-test-cookies.txt';

    public function testEndpoint(string $action, array $data = []): array {
        $url = $this->baseUrl . '?action=' . urlencode($action);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(array_merge(['action' => $action], $data)),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: PHPSESSID=' . session_id()
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    public function runTests(): void {
        $tests = [
            'health' => [],
            'dashboard-stats' => [],
            'sidebar-stats' => [],
            'dashboard-charts' => [],
            'dashboard-orders-table' => [],
            'dashboard-stock-alerts' => []
        ];

        echo "🧪 Running API Test Suite\n";
        echo str_repeat('=', 60) . "\n\n";

        foreach ($tests as $action => $data) {
            echo "Testing: {$action}... ";

            $result = $this->testEndpoint($action, $data);

            if ($result['status'] === 200 && $result['response']['success'] === true) {
                echo "✅ PASS\n";
            } else {
                echo "❌ FAIL\n";
                echo "  Status: {$result['status']}\n";
                echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
            }
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "✅ Test suite complete\n";
    }
}

// Run tests
$tester = new APITester();
$tester->runTests();
```

Run:
```bash
php tests/api-test-suite.php
```

---

## 🎯 Quick Test Commands

### Test Health Check (No Auth Required):
```bash
curl "https://staff.vapeshed.co.nz/supplier/api/?action=health"
```

### Test with Session Cookie:
```bash
# Get session ID from browser, then:
curl -X POST "https://staff.vapeshed.co.nz/supplier/api/?action=dashboard-stats" \
  -H "Cookie: PHPSESSID=abc123def456" \
  -H "Content-Type: application/json"
```

### Test All Endpoints:
```bash
# Save as test-all.sh
#!/bin/bash
COOKIE="PHPSESSID=YOUR_SESSION_ID"
BASE="https://staff.vapeshed.co.nz/supplier/api/"

for action in dashboard-stats sidebar-stats dashboard-charts dashboard-orders-table; do
    echo "Testing: $action"
    curl -X POST "${BASE}?action=${action}" \
      -H "Cookie: ${COOKIE}" \
      -H "Content-Type: application/json" | jq .success
    echo ""
done
```

---

## 🔒 SECURITY WARNINGS

### ⚠️ NEVER DO IN PRODUCTION:
1. ❌ Don't commit `test-auth.php` to git
2. ❌ Don't leave auth bypass code in production
3. ❌ Don't share session cookies publicly
4. ❌ Don't disable auth checks in production code

### ✅ SAFE PRACTICES:
1. ✅ Use `.gitignore` for test files
2. ✅ Test in staging/dev environments
3. ✅ Rotate credentials after testing
4. ✅ Use environment-specific test accounts
5. ✅ Delete test files before deployment

### .gitignore additions:
```
# API Testing Files
test-auth.php
test-endpoint.php
cookies.txt
*.cookie
scripts/test-*.sh
```

---

## 📊 Expected Responses

### Success Response:
```json
{
  "success": true,
  "data": {
    "total_orders": 150,
    "active_products": 1250,
    "pending_claims": 5
  },
  "message": "Dashboard statistics loaded successfully",
  "timestamp": "2025-10-30T12:00:00+00:00",
  "request_id": "req_67432abc..."
}
```

### Auth Error:
```json
{
  "success": false,
  "message": "Authentication required",
  "error": {
    "code": "AUTH_REQUIRED",
    "message": "You must be logged in to access this resource",
    "details": "Your session may have expired. Please log in again.",
    "action": "redirect",
    "redirect_url": "/supplier/login.php"
  },
  "timestamp": "2025-10-30T12:00:00+00:00",
  "request_id": "req_67432xyz..."
}
```

---

## ✅ Recommended Testing Flow

1. **Login in browser** → Get session cookie
2. **Export cookie** → Save to `cookies.txt`
3. **Test health endpoint** (no auth) → Verify API working
4. **Test with cookie** → Verify auth working
5. **Run test suite** → Verify all endpoints
6. **Check error handling** → Test invalid actions
7. **Verify error modals** → Check frontend display

---

**Status**: 🧪 READY FOR TESTING
**Auth Methods**: 6 different approaches
**Test Tools**: curl, PHP CLI, Postman, Browser DevTools
**Date**: October 30, 2025
