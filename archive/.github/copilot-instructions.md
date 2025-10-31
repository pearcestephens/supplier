# Supplier Portal - AI Coding Agent Instructions

## Project Overview
**The Vape Shed Supplier Portal** - Production PHP application for supplier order/warranty management. Uses **magic link authentication** (no passwords), **Bootstrap 5 UI**, and **dual database architecture** (MySQLi + PDO in transition).

## Critical Architecture Knowledge

### 1. Bootstrap Pattern (ALWAYS USE THIS)
Every PHP file MUST start with:
```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php'; // For API files
// OR
require_once __DIR__ . '/bootstrap.php'; // For root files
```

**Why:** `bootstrap.php` provides unified initialization:
- Database connections (`$db` MySQLi + `$pdo` PDO)
- Session management via `Session::start()`
- Auth helpers: `requireAuth()`, `getSupplierID()`
- Error handlers (JSON for AJAX, HTML for pages)
- 10+ helper functions (`e()`, `formatDate()`, `sendJsonResponse()`)

**Never** manually require `lib/` files or start sessions - bootstrap does it correctly.

### 2. Database Architecture (In Transition)
**Current State:** Dual-database system during PDO migration:
- **MySQLi** (`lib/Database.php`): Legacy tabs, old APIs - accessed via `db()` helper or `$GLOBALS['db']`
- **PDO** (`lib/DatabasePDO.php`): New unified API handlers - accessed via `pdo()` helper or `$GLOBALS['pdo']`

**Writing New Code:** ALWAYS use PDO with prepared statements:
```php
$pdo = pdo();
$stmt = $pdo->prepare("SELECT * FROM vend_consignments WHERE supplier_id = ? AND state = ?");
$stmt->execute([$supplierId, $state]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

**Never:**
- Use string concatenation in SQL
- Use MySQLi for new features
- Mix MySQLi and PDO in same transaction

### 3. Authentication Flow
**Magic Link Login:** No passwords - users receive email with `?supplier_id={UUID}` parameter.

**Flow:**
1. `index.php` checks `$_GET['supplier_id']`
2. Calls `Auth::loginById($supplierId)`
3. Sets session: `$_SESSION['supplier_id']`, `$_SESSION['authenticated']`
4. All API requests require valid session

**Auth Checks:**
```php
requireAuth(); // Bootstrap helper - auto-redirects or returns 401 JSON
$supplierId = getSupplierID(); // Get authenticated supplier UUID
```

**Critical:** Session must be started BEFORE auth checks (bootstrap does this).

### 4. API Architecture (Unified Envelope Pattern)
**Single endpoint:** `/api/endpoint.php` routes to handlers via `module.method` format.

**Request format:**
```json
{
  "action": "dashboard.getStats",
  "params": {"date_range": 30}
}
```

**Routing:** `endpoint.php` loads `/api/handlers/{module}.php` and calls `Handler_{Module}::{method}()`

**Creating new API methods:**
1. Add method to handler class: `public function getStats(array $params): array`
2. Return `['data' => $result, 'message' => '...', 'meta' => []]`
3. Frontend calls: `fetch('/api/endpoint.php', {method: 'POST', body: JSON.stringify({action: 'dashboard.getStats', params: {}})})`

**Error handling:** Throw exceptions - endpoint.php catches and formats as JSON.

### 5. Database Schema - Core Tables
**Suppliers:** `vend_suppliers` (id=VARCHAR UUID, name, email, deleted_at)
**Orders/POs:** `vend_consignments` (id=INT auto, public_id, supplier_id, state ENUM, tracking_number, created_at)
**Products:** `vend_products` (id=VARCHAR UUID, supplier_id, name, sku, active)
**Warranties:** `faulty_products` (id=INT, product_id, supplier_status=0/1, supplier_status_timestamp)
**Inventory:** `vend_inventory` (product_id, outlet_id, current_amount)
**Sessions:** `supplier_portal_sessions` (supplier_id, session_token, expires_at)

**Key patterns:**
- `deleted_at` soft deletes (check `!= '0000-00-00 00:00:00'` or `IS NULL`)
- UUIDs are VARCHAR(100) not binary
- Always filter by `supplier_id` for multi-tenancy
- Use `public_id` (3-char prefix like 'JCE-PO-12345') for display, not DB `id`

### 6. Frontend Structure (Demo → Production Migration Active)
**Demo files:** `/demo/*.html` - static HTML with exact UI/styling we want
**Production tabs:** `/tabs/tab-{name}.php` - server-side rendered pages with same HTML structure

**Migration pattern:**
1. Copy HTML structure from `/demo/*.html`
2. Convert to PHP in `/tabs/tab-{name}.php`
3. Replace static data with API calls
4. Keep exact CSS classes and widget structure (user requirement: "1:1 match")

**CSS:** `assets/css/professional-black.css` (black sidebar #0a0a0a, blue accent #3b82f6)
**JS:** `assets/js/` - Bootstrap 5, Chart.js 3.9.1, jQuery 3.6
**Components:** `components/header-top.php`, `header-bottom.php`, `sidebar.php`

### 7. Configuration
**Database:** Credentials in `config.php` (DB_HOST, DB_NAME, DB_USER, DB_PASS)
**Session:** 24-hour lifetime, HTTPS-only cookies, path `/supplier/`
**Business logic:** Constants in `config.php` (PO_PREFIX='JCE-PO-', WARRANTY_SLA=48hrs, etc.)

### 8. Error Handling Pattern
**Bootstrap provides global handlers:**
- AJAX requests (Content-Type: application/json) → JSON error response
- Page requests → HTML error page with copy/download buttons
- All errors logged to PHP error_log

**In your code:**
```php
try {
    $result = someOperation();
    sendJsonResponse(true, $result, 'Success');
} catch (Exception $e) {
    // For AJAX
    sendJsonResponse(false, null, $e->getMessage(), 500);
    // For pages - bootstrap handles if you throw
}
```

### 9. Testing Commands
```bash
# Check PHP syntax
php -l api/endpoint.php

# Test database connection
php -r "require 'bootstrap.php'; echo db()->ping() ? 'OK' : 'FAIL';"

# Run unit tests
php tests/comprehensive-api-test.php

# Check error logs
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
```

### 10. Common Patterns

**Date formatting:**
```php
formatDate($timestamp, 'display'); // "Oct 26, 2025"
formatDate($timestamp, 'datetime'); // "Oct 26, 2025 3:45 PM"
```

**HTML escaping:**
```php
echo e($userInput); // Same as htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8')
```

**Pagination:**
```php
$page = (int)($_GET['page'] ?? 1);
$perPage = PAGINATION_PER_PAGE; // 25 from config.php
$offset = ($page - 1) * $perPage;
```

**Supplier filtering (multi-tenancy):**
```php
// ALWAYS filter by authenticated supplier
WHERE supplier_id = ? AND deleted_at IS NULL
```

## Development Workflow

1. **Starting new feature:** Check `/demo/` for UI reference first
2. **Database queries:** Search existing tabs for similar patterns before writing new SQL
3. **API changes:** Update handler class method, test via endpoint.php
4. **UI changes:** Maintain exact class structure from demo files (user requirement)
5. **Before commit:** Run `php -l` on changed files, check error logs

## Documentation References
- `COMPLETE_IMPLEMENTATION_GUIDE.md` - Bootstrap architecture details
- `docs/DATABASE_MASTER_REFERENCE.md` - Full schema with indexes
- `docs/AUTHENTICATION_FLOW.md` - Magic link implementation
- `DEMO_TO_PRODUCTION_MIGRATION_PLAN.md` - UI migration strategy
- `demo/README.md` - UI design system (colors, fonts, components)

## Critical Don'ts
- ❌ Never bypass `requireAuth()` in API endpoints
- ❌ Never use raw SQL without prepared statements
- ❌ Never manually start sessions (use bootstrap)
- ❌ Never mix MySQLi and PDO in same transaction
- ❌ Never expose `supplier_id` from other suppliers (multi-tenancy breach)
- ❌ Never trust `$_GET`/`$_POST` without validation
- ❌ Never return raw exceptions to frontend (sanitize first)


## When Stuck
1. Search codebase for similar functionality: `grep -r "similar_pattern" --include="*.php"`
2. Check existing handler methods for parameter/return patterns
3. Review test files in `/tests/` for working examples
4. Check documentation in `/docs/` and root `.md` files
5. Verify bootstrap is loaded: `defined('BOOTSTRAP_LOADED')` should be true
