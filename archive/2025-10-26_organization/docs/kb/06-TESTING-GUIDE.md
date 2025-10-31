# 06 - Testing Guide

**Testing commands, debugging procedures, and quality assurance**

---

## Table of Contents

1. [Quick Testing Commands](#quick-testing-commands)
2. [PHP Syntax Testing](#php-syntax-testing)
3. [API Testing](#api-testing)
4. [Database Testing](#database-testing)
5. [Frontend Testing](#frontend-testing)
6. [Error Log Monitoring](#error-log-monitoring)
7. [Test Scenarios](#test-scenarios)

---

## Quick Testing Commands

### Syntax Check All PHP Files

```bash
# Test syntax of all PHP files
cd /home/master/applications/jcepnzzkmj/public_html/supplier
php -l bootstrap.php && echo "✓ bootstrap.php"
php -l config.php && echo "✓ config.php"
php -l index.php && echo "✓ index.php"

# Test all lib files
for file in lib/*.php; do php -l "$file" && echo "✓ $file"; done

# Test all API handlers
for file in api/handlers/*.php; do php -l "$file" && echo "✓ $file"; done

# Test all tabs
for file in tabs/*.php; do php -l "$file" && echo "✓ $file"; done
```

### Batch Syntax Test Script

Create `test-syntax.sh`:

```bash
#!/bin/bash
# Test all PHP files for syntax errors

echo "Testing PHP syntax..."
errors=0

for file in $(find . -name "*.php" ! -path "./vendor/*" ! -path "./archive/*"); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ $file"
        php -l "$file"
        ((errors++))
    fi
done

if [ $errors -eq 0 ]; then
    echo "✅ All files passed syntax check"
    exit 0
else
    echo "❌ $errors file(s) failed syntax check"
    exit 1
fi
```

Run with:
```bash
chmod +x test-syntax.sh
./test-syntax.sh
```

---

## PHP Syntax Testing

### Test Individual File

```bash
php -l path/to/file.php
```

**Success output:**
```
No syntax errors detected in path/to/file.php
```

**Error output:**
```
PHP Parse error:  syntax error, unexpected '}' in path/to/file.php on line 42
Errors parsing path/to/file.php
```

### Test with Strict Types

```bash
php -d error_reporting=-1 -d display_errors=1 -l file.php
```

---

## API Testing

### Using cURL

#### Test Dashboard Stats

```bash
# Login first to get session cookie
curl -c cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"auth.login","params":{"email":"test@example.com","password":"test123"}}'

# Get dashboard stats (using saved cookie)
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"dashboard.getStats","params":{"date_range":30}}' | jq '.'
```

#### Test Orders API

```bash
# Get pending orders
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"orders.getPending","params":{"limit":5}}' | jq '.'

# Get order detail
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"orders.getOrderDetail","params":{"id":1234}}' | jq '.'
```

#### Test Warranty API

```bash
# Get warranty claims list
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"warranty.getList","params":{"page":1,"per_page":10,"status":"pending"}}' | jq '.'
```

### Batch API Test Script

Create `test-dashboard-apis.sh`:

```bash
#!/bin/bash
# Test all dashboard API endpoints

BASE_URL="https://staff.vapeshed.co.nz/supplier/api/endpoint.php"
COOKIES="cookies.txt"

# Login first
echo "Logging in..."
curl -s -c $COOKIES -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{"action":"auth.login","params":{"email":"test@example.com","password":"test123"}}' > /dev/null

# Test dashboard.getStats
echo "Testing dashboard.getStats..."
result=$(curl -s -b $COOKIES -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{"action":"dashboard.getStats","params":{}}')

if echo "$result" | jq -e '.success == true' > /dev/null; then
    echo "✅ dashboard.getStats passed"
else
    echo "❌ dashboard.getStats failed"
    echo "$result" | jq '.'
fi

# Test dashboard.getChartData
echo "Testing dashboard.getChartData..."
result=$(curl -s -b $COOKIES -X POST $BASE_URL \
  -H "Content-Type: application/json" \
  -d '{"action":"dashboard.getChartData","params":{"chart_type":"revenue"}}')

if echo "$result" | jq -e '.success == true' > /dev/null; then
    echo "✅ dashboard.getChartData passed"
else
    echo "❌ dashboard.getChartData failed"
fi

# Clean up
rm $COOKIES
```

### PHP Test Script

Create `tests/test-dashboard-api.php`:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

// Force supplier_id for testing
$_SESSION['supplier_id'] = 'test-supplier-uuid';
$_SESSION['authenticated'] = true;

// Test dashboard.getStats
echo "Testing dashboard.getStats...\n";
$_POST = json_encode([
    'action' => 'dashboard.getStats',
    'params' => ['date_range' => 30]
]);

ob_start();
include __DIR__ . '/../api/endpoint.php';
$output = ob_get_clean();

$result = json_decode($output, true);
if ($result['success']) {
    echo "✅ PASSED\n";
    print_r($result['data']);
} else {
    echo "❌ FAILED: {$result['error']}\n";
}

echo "\n";
```

Run with:
```bash
php tests/test-dashboard-api.php
```

---

## Database Testing

### Test Database Connection

```php
<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $db = db();
    echo "✅ MySQLi connection successful\n";
    
    $pdo = pdo();
    echo "✅ PDO connection successful\n";
    
    // Test query
    $result = $pdo->query("SELECT COUNT(*) as count FROM vend_suppliers");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Query test: Found {$row['count']} suppliers\n";
    
} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "\n";
}
```

### Test Supplier Data Access

```php
<?php
require_once __DIR__ . '/bootstrap.php';

$testSupplierId = 'your-supplier-uuid-here';

// Test supplier exists
$pdo = pdo();
$stmt = $pdo->prepare("
    SELECT id, name, email 
    FROM vend_suppliers 
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$testSupplierId]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($supplier) {
    echo "✅ Supplier found: {$supplier['name']}\n";
} else {
    echo "❌ Supplier not found\n";
}

// Test orders count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM vend_consignments
    WHERE supplier_id = ?
      AND transfer_category = 'PURCHASE_ORDER'
      AND deleted_at IS NULL
");
$stmt->execute([$testSupplierId]);
$count = $stmt->fetchColumn();
echo "✅ Found {$count} orders\n";
```

---

## Frontend Testing

### Browser Console Tests

Open browser console (F12) and run:

```javascript
// Test API call function
callAPI('dashboard.getStats', { date_range: 30 })
    .then(data => {
        console.log('✅ API call successful:', data);
    })
    .catch(error => {
        console.error('❌ API call failed:', error);
    });

// Test session status
fetch('/supplier/api/session-debug.php', {
    credentials: 'same-origin'
})
.then(r => r.json())
.then(data => {
    console.log('✅ Session data:', data);
});

// Test Chart.js loaded
if (typeof Chart !== 'undefined') {
    console.log('✅ Chart.js loaded');
} else {
    console.error('❌ Chart.js not loaded');
}

// Test jQuery loaded
if (typeof jQuery !== 'undefined') {
    console.log('✅ jQuery loaded:', jQuery.fn.jquery);
} else {
    console.error('❌ jQuery not loaded');
}
```

### Automated Frontend Tests (Optional)

Using Puppeteer for headless browser testing:

```javascript
// tests/frontend.test.js
const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    
    // Navigate to dashboard
    await page.goto('https://staff.vapeshed.co.nz/supplier/');
    
    // Check title
    const title = await page.title();
    console.log(title.includes('Dashboard') ? '✅ Title correct' : '❌ Title wrong');
    
    // Check for key elements
    const hasStats = await page.$('.stats-grid');
    console.log(hasStats ? '✅ Stats grid found' : '❌ Stats grid missing');
    
    const hasSidebar = await page.$('.sidebar');
    console.log(hasSidebar ? '✅ Sidebar found' : '❌ Sidebar missing');
    
    await browser.close();
})();
```

---

## Error Log Monitoring

### Tail Error Log

```bash
# Apache error log
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Last 100 lines
tail -100 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Search for specific error
grep "Fatal error" /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Count errors by type
grep "error" /home/master/applications/jcepnzzkmj/logs/apache_*.error.log | cut -d':' -f1 | sort | uniq -c
```

### PHP Error Test Page

Create `test-errors.php`:

```php
<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error Handler Test</title>
</head>
<body>
    <h1>Error Handler Test</h1>
    
    <h2>Test 1: Trigger Notice</h2>
    <?php
    echo $undefinedVariable; // Should trigger notice
    ?>
    
    <h2>Test 2: Trigger Warning</h2>
    <?php
    include('nonexistent-file.php'); // Should trigger warning
    ?>
    
    <h2>Test 3: Trigger Exception</h2>
    <?php
    try {
        throw new Exception('Test exception');
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage();
    }
    ?>
    
    <h2>Test 4: API Error Response</h2>
    <button onclick="testAPIError()">Test API Error</button>
    <pre id="api-result"></pre>
    
    <script>
    async function testAPIError() {
        try {
            const response = await fetch('/supplier/api/endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'invalid.method',
                    params: {}
                })
            });
            const data = await response.json();
            document.getElementById('api-result').textContent = JSON.stringify(data, null, 2);
        } catch (error) {
            document.getElementById('api-result').textContent = 'Error: ' + error.message;
        }
    }
    </script>
</body>
</html>
```

---

## Test Scenarios

### Scenario 1: Complete Authentication Flow

```bash
# 1. Fresh browser (clear cookies)
# 2. Visit https://staff.vapeshed.co.nz/supplier/
# Expected: Redirected to login page or shown login instructions

# 3. Visit magic link: ?supplier_id={valid-uuid}
# Expected: Logged in, redirected to dashboard

# 4. Refresh page
# Expected: Still logged in, dashboard loads

# 5. Visit /supplier/logout.php
# Expected: Logged out, redirected to login

# 6. Try visiting /supplier/tabs/tab-orders.php directly
# Expected: Redirected to login (requireAuth() working)
```

### Scenario 2: API Error Handling

```bash
# Test 1: Invalid action
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"invalid.action","params":{}}' | jq '.'
# Expected: {"success": false, "error": "Handler not found", "code": 404}

# Test 2: Missing parameters
curl -b cookies.txt -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"orders.getOrderDetail","params":{}}' | jq '.'
# Expected: {"success": false, "error": "Order ID required", "code": 400}

# Test 3: Unauthorized (no session)
curl -X POST \
  https://staff.vapeshed.co.nz/supplier/api/endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"dashboard.getStats","params":{}}' | jq '.'
# Expected: {"success": false, "error": "Authentication required", "code": 401}
```

### Scenario 3: Multi-tenancy Verification

```php
<?php
// Test that supplier A cannot see supplier B's data
require_once __DIR__ . '/bootstrap.php';

$supplierA = 'uuid-supplier-a';
$supplierB = 'uuid-supplier-b';

// Login as supplier A
$_SESSION['supplier_id'] = $supplierA;
$_SESSION['authenticated'] = true;

// Try to access supplier B's order
$pdo = pdo();
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM vend_consignments 
    WHERE supplier_id = ?
      AND transfer_category = 'PURCHASE_ORDER'
");
$stmt->execute([$supplierB]);
$countB = $stmt->fetchColumn();

// With proper filtering, should return 0 (no access)
echo $countB === 0 ? "✅ Multi-tenancy working" : "❌ SECURITY BREACH";
```

---

## Pre-Deployment Checklist

Before deploying to production:

- [ ] All PHP files pass syntax check (`php -l`)
- [ ] No PHP errors in logs (check last 24 hours)
- [ ] All API endpoints return valid JSON
- [ ] Authentication flow works (login → dashboard → logout)
- [ ] Multi-tenancy enforced (supplier A cannot see supplier B data)
- [ ] CSRF tokens present on all forms
- [ ] Session cookies secure (HTTPS-only, HttpOnly)
- [ ] Error handler catches exceptions properly
- [ ] Frontend loads without console errors
- [ ] Chart.js charts render correctly
- [ ] AJAX requests include credentials
- [ ] Mobile responsive (test at 375px, 768px, 1200px)

---

## Debugging Tips

### 1. Enable Detailed Error Reporting (Development Only)

Add to `config.php`:

```php
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
```

### 2. Add Debug Logging

```php
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    
    if ($data !== null) {
        $logLine .= print_r($data, true) . "\n";
    }
    
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

// Usage
debugLog('Supplier login attempt', ['supplier_id' => $supplierId]);
```

### 3. Var Dump with Exit

```php
// Quick debug (REMOVE BEFORE COMMIT)
var_dump($variable);
exit;
```

---

## Next Steps

- **Deployment:** [07-DEPLOYMENT.md](07-DEPLOYMENT.md)
- **Troubleshooting:** [08-TROUBLESHOOTING.md](08-TROUBLESHOOTING.md)

---

**Last Updated:** 2025-10-26  
**Related:** [01-ARCHITECTURE.md](01-ARCHITECTURE.md), [03-API-REFERENCE.md](03-API-REFERENCE.md)
