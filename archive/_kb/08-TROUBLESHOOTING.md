# 08 - Troubleshooting Guide

**Common issues and their solutions**

---

## Quick Diagnostic Commands

```bash
# Check recent errors (last 100 lines)
tail -100 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Search for specific error
grep -i "fatal\|parse error" /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Check PHP syntax of all files
find /home/master/applications/jcepnzzkmj/public_html/supplier -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# Test database connection
php -r "require '/home/master/applications/jcepnzzkmj/public_html/supplier/bootstrap.php'; try { pdo()->query('SELECT 1'); echo 'DB OK\n'; } catch (Exception \$e) { echo 'DB FAIL: ' . \$e->getMessage() . '\n'; }"

# Check file permissions
ls -la /home/master/applications/jcepnzzkmj/public_html/supplier/

# Test homepage
curl -I https://staff.vapeshed.co.nz/supplier/
```

---

## Common Issues

### 1. Blank White Page

**Symptoms:**
- Browser shows blank white page
- No error message displayed
- No console errors

**Causes & Solutions:**

#### Cause A: PHP Fatal Error
```bash
# Check error log
tail -50 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Common issues:
# - "Cannot redeclare function X" → duplicate function definition
# - "Class not found" → missing include or namespace issue
# - "Memory exhausted" → increase memory_limit
```

**Fix:**
```php
// In config.php or bootstrap.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

#### Cause B: Session Already Started Error
```bash
# Error: "session_start(): Session cannot be started after headers have been sent"
```

**Fix:**
```php
// In bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for output BEFORE session_start()
// Look for echo, print, whitespace, or BOM before <?php
```

#### Cause C: Bootstrap Not Loaded
```bash
# Error: "Call to undefined function pdo()"
```

**Fix:**
```php
// Add at top of EVERY file
require_once __DIR__ . '/bootstrap.php';

// Or for files in subdirectories:
require_once dirname(__DIR__) . '/bootstrap.php';
```

---

### 2. 401 Unauthorized Error

**Symptoms:**
- API returns `{"success":false,"error":"Unauthorized"}`
- User redirected to login repeatedly
- Auth check fails even after login

**Causes & Solutions:**

#### Cause A: Session Cookie Not Set
```bash
# Check browser console → Application → Cookies
# Should see: CIS_SUPPLIER_SESSION cookie with HttpOnly flag
```

**Fix:**
```php
// In Session.php
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/supplier/', // MUST match URL path
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

#### Cause B: Session Expired
```bash
# Sessions expire after 24 hours
```

**Fix:**
```php
// In Session.php validateAge()
if (isset($_SESSION['created_at'])) {
    $age = time() - $_SESSION['created_at'];
    if ($age > SESSION_LIFETIME) {
        session_destroy();
        return false;
    }
}
```

#### Cause C: supplier_id Not in Session
```bash
# Check session contents
# Visit: /api/session-debug.php
```

**Fix:**
```php
// After successful login in Auth::loginById()
$_SESSION['supplier_id'] = $supplierId;
$_SESSION['authenticated'] = true;
$_SESSION['created_at'] = time();
Session::regenerateIdPeriodically();
```

#### Cause D: AJAX Missing Credentials
```javascript
// API calls must include credentials
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin', // ← REQUIRED
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({action: 'dashboard.getStats', params: {}})
});
```

---

### 3. SQL Errors

**Symptoms:**
- API returns database error
- Error log shows MySQL syntax error
- Query returns empty or wrong data

**Causes & Solutions:**

#### Cause A: Missing Multi-Tenancy Filter
```bash
# Error: "Supplier accessing data from another supplier"
```

**Fix:**
```php
// WRONG - returns ALL suppliers' data
$stmt = $pdo->query("SELECT * FROM vend_consignments");

// CORRECT - filters by authenticated supplier
$stmt = $pdo->prepare("
    SELECT * FROM vend_consignments 
    WHERE supplier_id = ? AND deleted_at IS NULL
");
$stmt->execute([$supplierId]);
```

#### Cause B: SQL Injection Risk
```php
// NEVER do this:
$query = "SELECT * FROM orders WHERE id = " . $_GET['id'];

// ALWAYS use prepared statements:
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$_GET['id']]);
```

#### Cause C: Wrong Database Connection
```bash
# Using legacy MySQLi instead of PDO
```

**Fix:**
```php
// OLD (avoid for new code)
$db = db(); // Returns MySQLi

// NEW (use this)
$pdo = pdo(); // Returns PDO
```

#### Cause D: Deleted Records Included
```php
// WRONG - includes soft-deleted records
$stmt = $pdo->prepare("SELECT * FROM vend_suppliers WHERE id = ?");

// CORRECT - excludes soft-deleted
$stmt = $pdo->prepare("
    SELECT * FROM vend_suppliers 
    WHERE id = ? AND deleted_at IS NULL
");
```

---

### 4. Chart Not Rendering

**Symptoms:**
- Dashboard shows empty widget
- Console error: "Chart is not defined"
- Canvas element exists but no chart

**Causes & Solutions:**

#### Cause A: Chart.js Not Loaded
```html
<!-- Check in header-bottom.php -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1"></script>
```

**Test in console:**
```javascript
typeof Chart // Should return "function"
```

#### Cause B: Canvas ID Mismatch
```javascript
// HTML
<canvas id="revenueChart"></canvas>

// JavaScript (must match exactly)
const ctx = document.getElementById('revenueChart'); // ✅ Correct
const ctx = document.getElementById('revenue-chart'); // ❌ Wrong
```

#### Cause C: Data Format Wrong
```javascript
// Chart.js expects specific format
{
    labels: ['Jan', 'Feb', 'Mar'], // Array of strings
    datasets: [{
        label: 'Revenue',
        data: [1000, 2000, 1500], // Array of numbers (not strings!)
        backgroundColor: '#3b82f6'
    }]
}
```

#### Cause D: API Returns Error
```javascript
// Check response in console
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: JSON.stringify({action: 'dashboard.getChartData', params: {type: 'revenue'}})
})
.then(r => r.json())
.then(data => {
    console.log('Chart data:', data); // Debug here
    if (!data.success) {
        console.error('API error:', data.error);
        return;
    }
    // Create chart...
});
```

---

### 5. Magic Link Not Working

**Symptoms:**
- User clicks link and sees login page
- URL has `?supplier_id=XXX` but no auto-login
- Error: "Invalid supplier ID"

**Causes & Solutions:**

#### Cause A: UUID Format Invalid
```php
// Test UUID validation
function isValidUUID(string $uuid): bool {
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid) === 1;
}

// Valid: "550e8400-e29b-41d4-a716-446655440000"
// Invalid: "abc123", "not-a-uuid", "00000000-0000-0000-0000"
```

#### Cause B: Supplier Not Found or Deleted
```php
// In Auth::loginById()
$stmt = $pdo->prepare("
    SELECT id, name, email 
    FROM vend_suppliers 
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$supplierId]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    return false; // Supplier doesn't exist or is deleted
}
```

#### Cause C: Session Not Persisting
```php
// After setting session, MUST NOT redirect before session_write_close()
$_SESSION['supplier_id'] = $supplierId;
session_write_close(); // Ensure session is saved
header('Location: /supplier/');
exit;
```

---

### 6. AJAX Request Fails

**Symptoms:**
- Network error in console
- CORS error
- Request returns HTML instead of JSON

**Causes & Solutions:**

#### Cause A: Wrong Content-Type
```javascript
// WRONG - sends as form data
fetch('/api/endpoint.php', {
    method: 'POST',
    body: JSON.stringify({action: 'orders.getOrders'})
});

// CORRECT - sets JSON header
fetch('/api/endpoint.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' }, // ← Add this
    body: JSON.stringify({action: 'orders.getOrders', params: {}})
});
```

#### Cause B: PHP Error Returns HTML
```bash
# API should return JSON, but returns HTML error page
```

**Fix:** Check error log and fix PHP error
```php
// In api/endpoint.php
header('Content-Type: application/json');

// Catch all errors
try {
    // Handle request...
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
```

#### Cause C: Missing Credentials
```javascript
// For same-origin requests (our case)
fetch('/api/endpoint.php', {
    credentials: 'same-origin' // ← MUST include
});

// Or with jQuery
$.ajax({
    url: '/api/endpoint.php',
    xhrFields: { withCredentials: true } // ← MUST include
});
```

---

### 7. Dashboard Stats Wrong

**Symptoms:**
- Numbers don't match database
- Trend percentages incorrect
- Showing other suppliers' data

**Causes & Solutions:**

#### Cause A: Multi-Tenancy Filter Missing
```sql
-- WRONG - counts all suppliers
SELECT COUNT(*) FROM vend_consignments WHERE state = 'OPEN'

-- CORRECT - counts only authenticated supplier
SELECT COUNT(*) FROM vend_consignments 
WHERE supplier_id = ? AND state = 'OPEN' AND deleted_at IS NULL
```

#### Cause B: Trend Calculation Wrong
```php
// Calculate trend percentage correctly
$current = 100;
$previous = 80;
$trend = ($previous > 0) ? (($current - $previous) / $previous) * 100 : 0;
// Result: 25% increase
```

#### Cause C: Date Range Not Applied
```php
// When filtering by period
$params = ['period' => '7days'];

$dateFilter = match($params['period']) {
    '7days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => ""
};

$query = "SELECT COUNT(*) FROM vend_consignments WHERE supplier_id = ? $dateFilter";
```

---

### 8. File Upload Issues

**Symptoms:**
- File upload returns error
- Large files fail
- Wrong file type rejected

**Causes & Solutions:**

#### Cause A: PHP Upload Limits
```bash
# Check current limits
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

**Fix in php.ini:**
```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 300
```

#### Cause B: Wrong Upload Directory
```php
// In config.php
define('UPLOAD_PATH', '/home/master/applications/jcepnzzkmj/public_html/supplier/uploads');

// Check directory exists and is writable
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_writable(UPLOAD_PATH)) {
    chmod(UPLOAD_PATH, 0755);
}
```

#### Cause C: File Type Validation
```php
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$fileType = $_FILES['file']['type'];

if (!in_array($fileType, $allowedTypes)) {
    throw new Exception("Invalid file type: $fileType");
}
```

---

### 9. Performance Issues

**Symptoms:**
- Slow page load (> 3 seconds)
- Dashboard takes long to render
- High CPU usage

**Causes & Solutions:**

#### Cause A: Missing Database Indexes
```sql
-- Check slow queries
SHOW FULL PROCESSLIST;

-- Add indexes on frequently queried columns
CREATE INDEX idx_supplier_state ON vend_consignments(supplier_id, state);
CREATE INDEX idx_supplier_created ON vend_consignments(supplier_id, created_at);
CREATE INDEX idx_supplier_status ON faulty_products(supplier_id, supplier_status);
```

#### Cause B: N+1 Query Problem
```php
// WRONG - N+1 queries
$orders = $pdo->query("SELECT * FROM vend_consignments")->fetchAll();
foreach ($orders as $order) {
    $items = $pdo->query("SELECT * FROM purchase_order_line_items WHERE order_id = {$order['id']}")->fetchAll();
}

// CORRECT - single join
$stmt = $pdo->query("
    SELECT c.*, poi.* 
    FROM vend_consignments c
    LEFT JOIN purchase_order_line_items poi ON c.id = poi.order_id
");
```

#### Cause C: No Query Result Caching
```php
// Cache expensive queries
$cacheKey = "dashboard_stats_{$supplierId}";
$cached = apcu_fetch($cacheKey);

if ($cached === false) {
    $stats = calculateStats($supplierId);
    apcu_store($cacheKey, $stats, 300); // Cache for 5 minutes
} else {
    $stats = $cached;
}
```

---

### 10. Session Lost on Redirect

**Symptoms:**
- User logged in, then redirected and logged out
- Session data disappears
- "Session not found" error

**Causes & Solutions:**

#### Cause A: Session Not Written Before Redirect
```php
// WRONG
$_SESSION['supplier_id'] = $supplierId;
header('Location: /supplier/'); // Session not saved yet!
exit;

// CORRECT
$_SESSION['supplier_id'] = $supplierId;
session_write_close(); // Force write to disk
header('Location: /supplier/');
exit;
```

#### Cause B: Cookie Path Mismatch
```php
// Check cookie path matches URL structure
session_set_cookie_params([
    'path' => '/supplier/', // MUST match your URL path
    // ...
]);

// If URL is https://example.com/supplier/
// Cookie path MUST be /supplier/
```

#### Cause C: Secure Flag on HTTP
```php
// If testing on HTTP (local dev), set secure to false
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'secure' => $isHttps, // Only set true on HTTPS
    // ...
]);
```

---

## Debugging Tools

### 1. Session Debugger

**Location:** `/api/session-debug.php`

```bash
# Visit in browser
https://staff.vapeshed.co.nz/supplier/api/session-debug.php
```

**Output:**
```json
{
    "session_status": "active",
    "session_id": "abc123...",
    "session_data": {
        "supplier_id": "550e8400-e29b-41d4-a716-446655440000",
        "authenticated": true,
        "created_at": 1729900000
    },
    "session_age": 3600,
    "cookies": {
        "CIS_SUPPLIER_SESSION": "abc123..."
    }
}
```

### 2. Error Log Viewer

```bash
# Watch errors in real-time
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Filter for specific errors
tail -f logs/apache_*.error.log | grep -i "fatal\|parse"

# Count errors by type
grep -i "fatal" logs/apache_*.error.log | wc -l
```

### 3. Database Query Profiler

```php
// In bootstrap.php (development only)
if (ENVIRONMENT === 'development') {
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['ProfilingPDOStatement']);
}

class ProfilingPDOStatement extends PDOStatement {
    public function execute($params = null) {
        $start = microtime(true);
        $result = parent::execute($params);
        $time = (microtime(true) - $start) * 1000;
        
        if ($time > 100) { // Log queries > 100ms
            error_log("Slow query ({$time}ms): {$this->queryString}");
        }
        
        return $result;
    }
}
```

### 4. API Request Logger

```php
// In api/endpoint.php (add at top)
$requestLog = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'action' => $requestData['action'] ?? 'none',
    'params' => $requestData['params'] ?? [],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

file_put_contents(
    __DIR__ . '/../logs/api-requests.log',
    json_encode($requestLog) . "\n",
    FILE_APPEND
);
```

---

## Emergency Procedures

### Total System Down

```bash
# 1. Check Apache is running
systemctl status apache2

# 2. Check PHP-FPM is running
systemctl status php-fpm

# 3. Restart services
sudo systemctl restart apache2
sudo systemctl restart php-fpm

# 4. Check database
mysql -u jcepnzzkmj -p'wprKh9Jq63' -e "SELECT 1"

# 5. Check disk space
df -h

# 6. Check error logs
tail -100 /var/log/apache2/error.log
```

### Database Locked

```sql
-- Check for locked tables
SHOW OPEN TABLES WHERE In_use > 0;

-- Check for long-running queries
SHOW FULL PROCESSLIST;

-- Kill problematic query (last resort)
KILL <process_id>;
```

### Session Table Full

```sql
-- Clean old sessions
DELETE FROM supplier_portal_sessions 
WHERE expires_at < NOW();

-- Optimize table
OPTIMIZE TABLE supplier_portal_sessions;
```

---

## Preventive Measures

### Daily Health Checks

```bash
#!/bin/bash
# daily-health-check.sh

echo "=== Daily Health Check $(date) ===" >> logs/health.log

# Check error count
ERRORS=$(grep -c "Fatal\|Parse error" logs/apache_*.error.log)
echo "Errors in last 24h: $ERRORS" >> logs/health.log

# Check disk space
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "WARNING: Disk usage is ${DISK_USAGE}%" >> logs/health.log
fi

# Check database connection
php -r "require 'bootstrap.php'; pdo()->query('SELECT 1');" && echo "Database: OK" >> logs/health.log || echo "Database: FAILED" >> logs/health.log
```

---

## Next Steps

- **Code Snippets:** [09-CODE-SNIPPETS.md](09-CODE-SNIPPETS.md)
- **Testing Guide:** [06-TESTING-GUIDE.md](06-TESTING-GUIDE.md)

---

**Last Updated:** 2025-10-26  
**Related:** [01-ARCHITECTURE.md](01-ARCHITECTURE.md), [04-AUTHENTICATION.md](04-AUTHENTICATION.md)
