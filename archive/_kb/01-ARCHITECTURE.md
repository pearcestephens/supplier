# 🏗️ System Architecture

## Overview
The Vape Shed Supplier Portal is a **multi-tenant B2B application** for managing purchase orders, warranty claims, and inventory between The Vape Shed and its suppliers.

## Architectural Layers

### 1. Entry Layer
```
┌─────────────────────────────────────┐
│  Entry Points                       │
├─────────────────────────────────────┤
│  index.php        Main portal entry │
│  login.php        Magic link login  │
│  logout.php       Session cleanup   │
│  api/endpoint.php Unified API       │
└─────────────────────────────────────┘
```

### 2. Bootstrap Layer
```
┌─────────────────────────────────────┐
│  bootstrap.php (ALWAYS REQUIRED)    │
├─────────────────────────────────────┤
│  • Loads config.php                 │
│  • Initializes databases (MySQLi+PDO)│
│  • Starts session management        │
│  • Registers error handlers         │
│  • Provides helper functions        │
└─────────────────────────────────────┘
```

**Critical Pattern:**
```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
// Now you have: pdo(), db(), requireAuth(), e(), formatDate(), etc.
```

### 3. Core Library Layer
```
/lib/
├── Database.php       MySQLi wrapper (legacy, being phased out)
├── DatabasePDO.php    PDO wrapper (preferred for new code)
├── Session.php        Secure session management
├── Auth.php           Authentication static methods
└── Utils.php          Helper functions
```

**Dependencies:** These classes have NO external dependencies except config.php

### 4. API Layer
```
/api/
├── endpoint.php              Single unified entry point
├── handlers/
│   ├── auth.php             Authentication (login/logout)
│   ├── dashboard.php        Dashboard stats & charts
│   ├── orders.php           Order/PO management
│   └── warranty.php         Warranty claim handling
└── [legacy endpoints]       Being migrated to handlers
```

**Request Flow:**
```
1. POST /api/endpoint.php
   Body: {"action": "dashboard.getStats", "params": {}}

2. endpoint.php parses envelope:
   - Extracts module="dashboard", method="getStats"
   - Loads /api/handlers/dashboard.php
   - Instantiates Handler_Dashboard($pdo, $supplierId)

3. Calls $handler->getStats($params)

4. Returns JSON:
   {
     "success": true,
     "data": {...},
     "message": "Stats retrieved",
     "meta": {}
   }
```

### 5. Presentation Layer
```
/tabs/                      Server-rendered pages
├── tab-dashboard.php      Dashboard with KPIs & charts
├── tab-orders.php         Purchase order list/details
├── tab-warranty.php       Warranty claim management
├── tab-reports.php        30-day performance reports
├── tab-downloads.php      Invoice/document archive
└── tab-account.php        Supplier account settings

/components/               Reusable UI fragments
├── header-top.php         Logo, search, notifications
├── header-bottom.php      Breadcrumbs, page actions
└── sidebar.php            Navigation menu

/demo/                     Static HTML reference designs
├── index.html            Exact UI we're implementing
├── orders.html
└── ... (6 pages total)
```

**Migration Pattern:** Demo HTML → Production PHP (1:1 structure)

## Database Architecture

### Current State: Dual Database (Transitional)
```
┌──────────────┐     ┌──────────────┐
│   MySQLi     │     │     PDO      │
│ (Legacy)     │     │ (Preferred)  │
├──────────────┤     ├──────────────┤
│ Used by:     │     │ Used by:     │
│ - Old tabs   │     │ - Handlers   │
│ - Legacy APIs│     │ - New APIs   │
│              │     │ - New tabs   │
└──────────────┘     └──────────────┘
       │                    │
       └────────┬───────────┘
                │
         ┌──────▼──────┐
         │  MySQL DB   │
         │ jcepnzzkmj  │
         └─────────────┘
```

**Migration Strategy:** All new code uses PDO. MySQLi being gradually removed.

### Core Tables & Relationships
```
vend_suppliers (UUID)
    ↓ (1:many)
vend_products (UUID, supplier_id)
    ↓ (many:1)
vend_inventory (product_id, outlet_id, current_amount)

vend_suppliers (UUID)
    ↓ (1:many)
vend_consignments (id INT, supplier_id, state, public_id)
    ↓ (1:many)
purchase_order_line_items (purchase_order_id, product_id)

vend_products (UUID)
    ↓ (1:many)
faulty_products (id INT, product_id, supplier_status 0/1)
```

## Authentication Architecture

### Magic Link Flow
```
1. Email sent with: ?supplier_id=0a91b764-1c71-11eb-e0eb-d7bf46fa95c8

2. index.php receives GET param:
   - Validates UUID exists in vend_suppliers
   - Calls Auth::loginById($supplierId)

3. Auth::loginById():
   - Starts session (Session::start())
   - Sets $_SESSION['supplier_id']
   - Sets $_SESSION['authenticated'] = true
   - Redirects to dashboard

4. All subsequent requests:
   - requireAuth() checks session
   - getSupplierID() returns authenticated UUID
   - Multi-tenancy: Filter all queries by supplier_id
```

**Session Storage:**
- Cookie name: `CIS_SUPPLIER_SESSION`
- Path: `/supplier/`
- Secure: HTTPS only
- Lifetime: 24 hours
- Database tracking: `supplier_portal_sessions` table

## Error Handling Architecture

### Global Error Handlers (bootstrap.php)
```php
set_exception_handler(function($e) {
    if (isJsonRequest()) {
        // AJAX/API request
        sendJsonResponse(false, null, $e->getMessage(), 500);
    } else {
        // Page request - show HTML error page with:
        // - Error message
        // - Stack trace (if DEBUG_MODE)
        // - Copy to clipboard button
        // - Download error report button
    }
});

set_error_handler(function($severity, $message, $file, $line) {
    // Convert PHP errors to exceptions
    throw new ErrorException($message, 0, $severity, $file, $line);
});
```

### Exception Pattern in Code
```php
try {
    $result = riskyOperation();
    sendJsonResponse(true, $result, 'Success');
} catch (Exception $e) {
    error_log("Error in operation: " . $e->getMessage());
    sendJsonResponse(false, null, 'Operation failed', 500);
}
```

## Multi-Tenancy Architecture

### Data Isolation Pattern
Every query MUST filter by authenticated supplier:

```php
$supplierId = getSupplierID(); // From session

// CORRECT - Filtered by supplier
$stmt = pdo()->prepare("
    SELECT * FROM vend_consignments 
    WHERE supplier_id = ? 
    AND state = ?
    AND deleted_at IS NULL
");
$stmt->execute([$supplierId, $state]);

// WRONG - No supplier filter (security breach!)
$stmt = pdo()->prepare("
    SELECT * FROM vend_consignments 
    WHERE state = ?
");
```

### Soft Delete Pattern
```php
// All queries must exclude soft-deleted records
WHERE deleted_at IS NULL
// OR
WHERE deleted_at = '0000-00-00 00:00:00'
```

## Frontend Architecture

### Design System
- **Framework:** Bootstrap 5.3
- **Theme:** Professional Black
  - Sidebar: `#0a0a0a` (pure black)
  - Accent: `#3b82f6` (blue)
  - Text: `#a0a0a0` (light gray)
- **Fonts:** Inter (Google Fonts)
- **Icons:** Font Awesome 6.0

### JavaScript Libraries
- **jQuery 3.6:** DOM manipulation, AJAX
- **Chart.js 3.9.1:** Dashboard charts
- **Bootstrap 5.3:** UI components, modals

### AJAX Pattern
```javascript
fetch('/api/endpoint.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'dashboard.getStats',
        params: {date_range: 30}
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        updateUI(data.data);
    } else {
        showError(data.message);
    }
});
```

## Configuration Management

### config.php Constants
```php
// Database
DB_HOST, DB_NAME, DB_USER, DB_PASS

// Session
SESSION_LIFETIME (86400 = 24hrs)
SESSION_COOKIE_NAME ('CIS_SUPPLIER_SESSION')

// Business Logic
PO_PREFIX ('JCE-PO-')
WARRANTY_RESPONSE_SLA_HOURS (48)
PAGINATION_PER_PAGE (25)

// File Uploads
UPLOAD_MAX_SIZE (10MB)
UPLOAD_ALLOWED_TYPES (images + PDF)
```

### Environment Detection
```php
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production'); // development, staging, production
```

## Performance Considerations

### Database Connection Pooling
- Bootstrap creates ONE PDO connection per request
- Stored in `$GLOBALS['pdo']`
- Reused via `pdo()` helper
- Auto-cleanup at script end

### Query Optimization
- All foreign keys indexed
- Compound indexes on (supplier_id, state, created_at)
- Use EXPLAIN for slow queries
- Limit result sets with PAGINATION_PER_PAGE

### Caching Strategy
```php
// Session-level caching for supplier data
if (!isset($_SESSION['supplier_data_cache'])) {
    $_SESSION['supplier_data_cache'] = fetchSupplierData();
}
```

## Security Architecture

### Input Validation
```php
// ALWAYS validate input
$supplierId = filter_var($_POST['supplier_id'], FILTER_SANITIZE_STRING);
if (!preg_match('/^[a-f0-9-]{36}$/i', $supplierId)) {
    throw new Exception('Invalid supplier ID format');
}
```

### Output Escaping
```php
// HTML context
echo e($userInput); // htmlspecialchars()

// JSON context (automatic)
sendJsonResponse(true, $data); // json_encode with flags
```

### SQL Injection Prevention
```php
// ALWAYS use prepared statements
$stmt = pdo()->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);

// NEVER concatenate
$sql = "SELECT * FROM table WHERE id = " . $id; // ❌ WRONG!
```

## File Organization Principles

1. **Separation of Concerns:** API logic (handlers) separate from presentation (tabs)
2. **DRY:** Shared code in `/lib/`, reusable components in `/components/`
3. **Convention over Configuration:** File naming: `tab-{name}.php`, `Handler_{Module}`
4. **Progressive Enhancement:** Demo HTML → Production PHP
5. **Documentation Co-location:** KB in `/docs/kb/`, related docs in `/docs/`

## Deployment Architecture

### File Paths
```
Production: /home/master/applications/jcepnzzkmj/public_html/supplier/
Logs: /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
URL: https://staff.vapeshed.co.nz/supplier/
```

### Zero-Downtime Deployment
1. Backup current files
2. Deploy new files
3. Run database migrations (if any)
4. Test critical paths
5. Monitor error logs

---
**Related:**
- Database: `02-DATABASE-SCHEMA.md`
- API: `03-API-REFERENCE.md`
- Auth: `04-AUTHENTICATION.md`
