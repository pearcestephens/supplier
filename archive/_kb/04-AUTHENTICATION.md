# 04 - Authentication & Session Management

**Complete authentication flow, magic link system, and session security**

---

## Table of Contents

1. [Overview](#overview)
2. [Magic Link Authentication](#magic-link-authentication)
3. [Session Lifecycle](#session-lifecycle)
4. [Multi-tenancy & Security](#multi-tenancy--security)
5. [Helper Functions](#helper-functions)
6. [Session Debugging](#session-debugging)
7. [Common Issues](#common-issues)

---

## Overview

The Supplier Portal uses **passwordless magic link authentication** where suppliers receive an email with a unique link containing their `supplier_id` UUID. This eliminates password management complexity and provides a secure, user-friendly experience.

### Authentication Flow Diagram

```
┌──────────────┐
│ Supplier     │
│ clicks email │
│ magic link   │
└──────┬───────┘
       │
       ▼
┌──────────────────────────────────────┐
│ index.php?supplier_id={UUID}         │
└──────┬───────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────┐
│ Bootstrap.php validates UUID format  │
│ - Must be valid UUID string          │
│ - Must exist in vend_suppliers       │
│ - Must not be soft-deleted           │
└──────┬───────────────────────────────┘
       │
       ▼ Valid
┌──────────────────────────────────────┐
│ Auth::loginById($supplierId)         │
│ - Sets $_SESSION['supplier_id']      │
│ - Sets $_SESSION['authenticated']    │
│ - Logs login activity                │
└──────┬───────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────┐
│ Redirect to Dashboard                │
│ Session valid for 24 hours           │
└──────────────────────────────────────┘
```

---

## Magic Link Authentication

### How Magic Links Work

**Email Format:**
```
https://staff.vapeshed.co.nz/supplier/?supplier_id=abc-123-def-456-789
```

**Security Features:**
1. **UUID Validation** - Only valid UUIDs accepted
2. **Database Verification** - Supplier must exist and not be deleted
3. **Single-Use** - No tokens stored (stateless design)
4. **Time-Limited** - Links expire after 24 hours (configurable)

### Login Code (`index.php`)

```php
<?php
require_once __DIR__ . '/bootstrap.php';

// Check for magic link parameter
if (isset($_GET['supplier_id'])) {
    $supplierId = $_GET['supplier_id'];
    
    // Validate UUID format
    if (!isValidUUID($supplierId)) {
        die('Invalid supplier ID format');
    }
    
    // Attempt login
    try {
        Auth::loginById($supplierId);
        
        // Redirect to clean URL (remove ?supplier_id)
        header('Location: /supplier/');
        exit;
        
    } catch (Exception $e) {
        logMessage("Login failed for supplier: {$supplierId}", 'ERROR', [
            'error' => $e->getMessage()
        ]);
        die('Login failed. Please contact support.');
    }
}

// Check if already authenticated
if (!Auth::check()) {
    // Show login instructions page
    include __DIR__ . '/login.php';
    exit;
}

// User is authenticated - show dashboard
include __DIR__ . '/tabs/tab-dashboard.php';
```

### Auth Class Methods (`lib/Auth.php`)

```php
<?php
declare(strict_types=1);

class Auth
{
    /**
     * Authenticate supplier by ID
     * 
     * @param string $supplierId Supplier UUID
     * @throws Exception If supplier not found or deleted
     */
    public static function loginById(string $supplierId): void
    {
        $db = db();
        
        // Verify supplier exists
        $sql = "
            SELECT id, name, email 
            FROM vend_suppliers 
            WHERE id = ? 
              AND deleted_at IS NULL
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $supplierId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $supplier = $result->fetch_assoc();
        
        if (!$supplier) {
            throw new Exception('Supplier not found or account disabled');
        }
        
        // Set session
        Session::start();
        $_SESSION['supplier_id'] = $supplier['id'];
        $_SESSION['supplier_name'] = $supplier['name'];
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Log activity
        self::logActivity($supplier['id'], 'login', 'Supplier logged in via magic link');
    }
    
    /**
     * Check if supplier is authenticated
     * 
     * @return bool
     */
    public static function check(): bool
    {
        Session::start();
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    /**
     * Get authenticated supplier ID
     * 
     * @return string|null
     */
    public static function getSupplierId(): ?string
    {
        Session::start();
        return $_SESSION['supplier_id'] ?? null;
    }
    
    /**
     * Get authenticated supplier name
     * 
     * @return string|null
     */
    public static function getSupplierName(): ?string
    {
        Session::start();
        return $_SESSION['supplier_name'] ?? null;
    }
    
    /**
     * Logout supplier and destroy session
     */
    public static function logout(): void
    {
        Session::start();
        
        $supplierId = self::getSupplierId();
        
        if ($supplierId) {
            self::logActivity($supplierId, 'logout', 'Supplier logged out');
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Log authentication activity
     */
    private static function logActivity(string $supplierId, string $action, string $description): void
    {
        $db = db();
        
        $sql = "
            INSERT INTO supplier_activity_log 
            (supplier_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $db->prepare($sql);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param('sssss', $supplierId, $action, $description, $ipAddress, $userAgent);
        $stmt->execute();
    }
}
```

---

## Session Lifecycle

### Session Configuration (`config.php`)

```php
// Session settings
define('SESSION_LIFETIME', 86400);  // 24 hours in seconds
define('SESSION_NAME', 'CIS_SUPPLIER_SESSION');
define('SESSION_COOKIE_PATH', '/supplier/');
define('SESSION_COOKIE_SECURE', true);  // HTTPS only
define('SESSION_COOKIE_HTTPONLY', true); // No JavaScript access
define('SESSION_COOKIE_SAMESITE', 'Lax'); // CSRF protection
```

### Session Management (`lib/Session.php`)

```php
<?php
declare(strict_types=1);

class Session
{
    private static bool $started = false;
    
    /**
     * Start session with security settings
     */
    public static function start(): void
    {
        if (self::$started) {
            return; // Already started
        }
        
        // Configure session parameters
        ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);
        ini_set('session.cookie_path', SESSION_COOKIE_PATH);
        ini_set('session.cookie_secure', SESSION_COOKIE_SECURE ? '1' : '0');
        ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY ? '1' : '0');
        ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        
        session_name(SESSION_NAME);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$started = true;
            
            // Validate session age
            self::validateAge();
            
            // Regenerate ID periodically
            self::regenerateIdPeriodically();
        }
    }
    
    /**
     * Validate session hasn't expired
     */
    private static function validateAge(): void
    {
        if (!isset($_SESSION['login_time'])) {
            $_SESSION['login_time'] = time();
            return;
        }
        
        $age = time() - $_SESSION['login_time'];
        
        if ($age > SESSION_LIFETIME) {
            // Session expired
            self::destroy();
            
            // Redirect to login
            header('Location: /supplier/login.php?expired=1');
            exit;
        }
    }
    
    /**
     * Regenerate session ID every 30 minutes
     */
    private static function regenerateIdPeriodically(): void
    {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            return;
        }
        
        $timeSinceRegeneration = time() - $_SESSION['last_regeneration'];
        
        // Regenerate every 30 minutes
        if ($timeSinceRegeneration > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
        self::$started = false;
    }
    
    /**
     * Get session data for debugging
     */
    public static function debug(): array
    {
        self::start();
        
        return [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_status' => session_status(),
            'cookie_params' => session_get_cookie_params(),
            'session_data' => $_SESSION,
            'age_seconds' => isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : null,
            'remaining_seconds' => isset($_SESSION['login_time']) ? SESSION_LIFETIME - (time() - $_SESSION['login_time']) : null
        ];
    }
}
```

### Session Flow Diagram

```
┌─────────────────┐
│ User visits     │
│ any page        │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ bootstrap.php           │
│ Session::start()        │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│ Check session status    │
└────────┬────────────────┘
         │
         ├─ No session? ───────────► Show login page
         │
         ├─ Session exists but expired? ──► Destroy & redirect to login
         │
         └─ Valid session? ────────► Continue to requested page
                                      │
                                      ▼
                               requireAuth() passes
```

---

## Multi-tenancy & Security

### Supplier ID Filtering

**CRITICAL:** Every database query must filter by authenticated supplier ID.

#### Bad (Security Breach)

```php
// ❌ WRONG - Returns ALL suppliers' data
$sql = "SELECT * FROM vend_consignments WHERE status = 'OPEN'";
$result = $db->query($sql);
```

#### Good (Secure)

```php
// ✅ CORRECT - Only returns current supplier's data
$supplierId = getSupplierID();
$sql = "
    SELECT * FROM vend_consignments 
    WHERE supplier_id = ? 
      AND status = 'OPEN'
      AND deleted_at IS NULL
";
$stmt = $db->prepare($sql);
$stmt->bind_param('s', $supplierId);
$stmt->execute();
```

### Soft Delete Filtering

Always exclude soft-deleted records:

```sql
WHERE deleted_at IS NULL
-- OR
WHERE deleted_at = '0000-00-00 00:00:00'
```

### CSRF Protection

**Bootstrap automatically sets CSRF token:**

```php
// In bootstrap.php
Session::start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

**In forms:**

```html
<form method="POST" action="/api/endpoint.php">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <!-- form fields -->
</form>
```

**Validation in API:**

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        sendJsonResponse(false, null, 'CSRF validation failed', 403);
        exit;
    }
}
```

---

## Helper Functions

### requireAuth()

**Usage:** Call at the top of every protected page/API endpoint.

```php
<?php
require_once __DIR__ . '/bootstrap.php';
requireAuth(); // Exits if not authenticated

// Protected code here
```

**Implementation:**

```php
function requireAuth(): void
{
    if (!Auth::check()) {
        // Determine response type
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
                  && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        $isJsonRequest = isset($_SERVER['CONTENT_TYPE']) 
                         && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
        
        if ($isAjax || $isJsonRequest) {
            // Return JSON for API requests
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 401
            ]);
        } else {
            // Redirect to login for page requests
            header('Location: /supplier/login.php');
        }
        
        exit;
    }
}
```

### getSupplierID()

**Usage:** Get authenticated supplier UUID.

```php
$supplierId = getSupplierID();
if (!$supplierId) {
    die('Not authenticated');
}

// Use in queries
$sql = "SELECT * FROM vend_products WHERE supplier_id = ?";
```

**Implementation:**

```php
function getSupplierID(): ?string
{
    return Auth::getSupplierId();
}
```

### isValidUUID()

**Usage:** Validate UUID format before database lookup.

```php
if (!isValidUUID($supplierId)) {
    throw new Exception('Invalid supplier ID format');
}
```

**Implementation:**

```php
function isValidUUID(string $uuid): bool
{
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid) === 1;
}
```

---

## Session Debugging

### Debug Endpoint (`api/session-debug.php`)

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
requireAuth();

header('Content-Type: application/json');

$debug = [
    'session' => Session::debug(),
    'auth' => [
        'is_authenticated' => Auth::check(),
        'supplier_id' => Auth::getSupplierId(),
        'supplier_name' => Auth::getSupplierName()
    ],
    'server' => [
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ],
    'config' => [
        'SESSION_LIFETIME' => SESSION_LIFETIME,
        'SESSION_NAME' => SESSION_NAME,
        'SESSION_COOKIE_PATH' => SESSION_COOKIE_PATH,
        'SESSION_COOKIE_SECURE' => SESSION_COOKIE_SECURE,
        'SESSION_COOKIE_HTTPONLY' => SESSION_COOKIE_HTTPONLY,
        'SESSION_COOKIE_SAMESITE' => SESSION_COOKIE_SAMESITE
    ]
];

echo json_encode($debug, JSON_PRETTY_PRINT);
```

**Usage:**
```bash
curl https://staff.vapeshed.co.nz/supplier/api/session-debug.php \
  -H "Cookie: CIS_SUPPLIER_SESSION=abc123..."
```

### Session Test Page (`session-diagnostic.php`)

Located at root for quick access:

```php
<?php
require_once __DIR__ . '/bootstrap.php';

// Don't require auth for diagnostic
Session::start();

$data = Session::debug();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Diagnostic</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; }
        .pass { color: green; }
        .fail { color: red; }
    </style>
</head>
<body>
    <h1>Session Diagnostic</h1>
    
    <h2>Session Status</h2>
    <ul>
        <li>Session Started: <span class="<?= session_status() === PHP_SESSION_ACTIVE ? 'pass' : 'fail' ?>">
            <?= session_status() === PHP_SESSION_ACTIVE ? 'YES' : 'NO' ?>
        </span></li>
        <li>Authenticated: <span class="<?= Auth::check() ? 'pass' : 'fail' ?>">
            <?= Auth::check() ? 'YES' : 'NO' ?>
        </span></li>
        <li>Supplier ID: <?= e(Auth::getSupplierId() ?? 'Not set') ?></li>
        <li>Session Age: <?= $data['age_seconds'] ?? 0 ?> seconds</li>
        <li>Remaining: <?= $data['remaining_seconds'] ?? 0 ?> seconds</li>
    </ul>
    
    <h2>Session Data</h2>
    <pre><?= e(json_encode($data, JSON_PRETTY_PRINT)) ?></pre>
    
    <h2>Actions</h2>
    <a href="/supplier/logout.php">Logout</a> |
    <a href="/supplier/">Go to Dashboard</a>
</body>
</html>
```

---

## Common Issues

### Issue 1: Session Lost on Redirect

**Symptom:** User logs in successfully but gets logged out immediately.

**Cause:** Session cookies not being sent on redirect.

**Solution:**
```php
// Use absolute URL in redirect
header('Location: https://staff.vapeshed.co.nz/supplier/');

// OR use relative path starting with /
header('Location: /supplier/');

// NOT relative without /
// header('Location: supplier/'); // ❌ WRONG
```

### Issue 2: "Session Already Started" Warning

**Symptom:** `Warning: session_start(): A session had already been started`

**Cause:** Multiple calls to `session_start()` or `Session::start()`.

**Solution:** Bootstrap already starts session. Never call it directly:

```php
// ❌ WRONG
session_start();
require_once __DIR__ . '/bootstrap.php';

// ✅ CORRECT
require_once __DIR__ . '/bootstrap.php';
// Session already started by bootstrap
```

### Issue 3: Session Expired Message Loop

**Symptom:** Redirects to login with `?expired=1` repeatedly.

**Cause:** Cookie settings preventing session persistence.

**Solution:** Check `SESSION_COOKIE_SECURE` matches your environment:
- Development (HTTP): `define('SESSION_COOKIE_SECURE', false);`
- Production (HTTPS): `define('SESSION_COOKIE_SECURE', true);`

### Issue 4: 401 Errors on API Calls

**Symptom:** AJAX requests return `{"success": false, "error": "Authentication required", "code": 401}`

**Cause:** Session cookie not being sent with fetch requests.

**Solution:**

```javascript
// ✅ CORRECT - Include credentials
fetch('/api/endpoint.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    credentials: 'same-origin', // Include cookies
    body: JSON.stringify({ action: 'dashboard.getStats' })
});

// ❌ WRONG - No credentials
fetch('/api/endpoint.php', {
    method: 'POST',
    body: JSON.stringify({ action: 'dashboard.getStats' })
});
```

### Issue 5: Magic Link Not Working

**Symptom:** Clicking email link shows "Invalid supplier ID format"

**Cause:** UUID malformed or supplier deleted.

**Debug:**
```php
// Add to index.php temporarily
if (isset($_GET['supplier_id'])) {
    $supplierId = $_GET['supplier_id'];
    var_dump([
        'raw' => $supplierId,
        'is_valid_uuid' => isValidUUID($supplierId),
        'exists' => checkSupplierExists($supplierId)
    ]);
    exit;
}

function checkSupplierExists(string $id): bool {
    $db = db();
    $sql = "SELECT COUNT(*) FROM vend_suppliers WHERE id = ? AND deleted_at IS NULL";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_row()[0] > 0;
}
```

---

## Security Checklist

- ✅ **Session cookies HTTPS-only** (SESSION_COOKIE_SECURE = true in production)
- ✅ **Session cookies HttpOnly** (prevents JavaScript access)
- ✅ **Session cookies SameSite=Lax** (CSRF protection)
- ✅ **Session ID regeneration** every 30 minutes
- ✅ **Session expiry** after 24 hours
- ✅ **Multi-tenancy filtering** on ALL queries
- ✅ **Soft delete filtering** with deleted_at checks
- ✅ **CSRF tokens** on POST forms
- ✅ **UUID validation** before database lookups
- ✅ **Activity logging** for login/logout

---

## Next Steps

For frontend integration patterns and AJAX authentication, see:
- [05-FRONTEND-PATTERNS.md](05-FRONTEND-PATTERNS.md)

For testing authentication flows:
- [06-TESTING-GUIDE.md](06-TESTING-GUIDE.md)

---

**Last Updated:** 2025-10-26  
**Related:** [01-ARCHITECTURE.md](01-ARCHITECTURE.md), [03-API-REFERENCE.md](03-API-REFERENCE.md)
