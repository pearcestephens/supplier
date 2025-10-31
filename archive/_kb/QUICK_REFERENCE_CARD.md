# 🚀 Quick Reference Card - Supplier Portal

## Start Here (First 60 Seconds)

### Essential Files
```
.github/copilot-instructions.md    ← READ THIS FIRST (5 min)
docs/kb/README.md                  ← KB overview
docs/kb/01-ARCHITECTURE.md         ← System design
docs/kb/02-DATABASE-SCHEMA.md      ← All tables & queries
```

### Bootstrap Pattern (USE IN EVERY FILE)
```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth(); // For protected pages/APIs
$supplierId = getSupplierID();
$pdo = pdo(); // PDO connection (preferred)
```

## Common Tasks

### 1. Create New API Endpoint
```php
// File: api/handlers/module.php
class Handler_Module {
    private PDO $db;
    private string $supplierId;
    
    public function __construct(PDO $db, string $supplierId) {
        $this->db = $db;
        $this->supplierId = $supplierId;
    }
    
    public function methodName(array $params): array {
        $stmt = $this->db->prepare("SELECT * FROM table WHERE supplier_id = ?");
        $stmt->execute([$this->supplierId]);
        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'message' => 'Success'
        ];
    }
}
```

### 2. Create New Page Tab
```php
// File: tabs/tab-name.php
<?php require_once dirname(__DIR__) . '/bootstrap.php'; requireAuth(); ?>
<!DOCTYPE html>
<html>
<head><?php include '../components/header-top.php'; ?></head>
<body>
    <?php include '../components/sidebar.php'; ?>
    <main>
        <?php include '../components/header-bottom.php'; ?>
        <!-- Your content -->
    </main>
    <script src="../assets/js/main.js"></script>
</body>
</html>
```

### 3. Database Query (PDO)
```php
// SELECT with parameters
$stmt = pdo()->prepare("
    SELECT * FROM vend_consignments 
    WHERE supplier_id = ? 
    AND state = ?
    AND deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->execute([$supplierId, 'OPEN', 25]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// INSERT
$stmt = pdo()->prepare("
    INSERT INTO table (col1, col2, created_at) 
    VALUES (?, ?, NOW())
");
$stmt->execute([$val1, $val2]);
$lastId = pdo()->lastInsertId();

// UPDATE
$stmt = pdo()->prepare("
    UPDATE table 
    SET col1 = ?, updated_at = NOW() 
    WHERE id = ? AND supplier_id = ?
");
$stmt->execute([$newVal, $id, $supplierId]);
```

### 4. Frontend AJAX Call
```javascript
fetch('/api/endpoint.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'module.method',
        params: {key: 'value'}
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        console.log(data.data);
    } else {
        alert(data.message);
    }
})
.catch(err => console.error(err));
```

## Critical Patterns

### ✅ ALWAYS Do
- ✅ Use `require_once bootstrap.php` first
- ✅ Use PDO for new code (not MySQLi)
- ✅ Filter by `supplier_id` in ALL queries
- ✅ Check `deleted_at IS NULL` for soft deletes
- ✅ Use prepared statements (never concatenate SQL)
- ✅ Call `requireAuth()` before accessing data
- ✅ Escape output with `e()` helper
- ✅ Return JSON from API: `sendJsonResponse(true, $data, 'message')`

### ❌ NEVER Do
- ❌ Manually start sessions (bootstrap does it)
- ❌ Use string concatenation in SQL
- ❌ Trust `$_GET`/`$_POST` without validation
- ❌ Expose other suppliers' data (multi-tenancy breach)
- ❌ Change demo CSS classes during migration
- ❌ Mix MySQLi and PDO in same transaction
- ❌ Return raw exceptions to frontend

## Database Quick Ref

### Core Tables
```
vend_suppliers         (id VARCHAR UUID, name, email)
vend_consignments      (id INT, public_id, supplier_id, state, tracking_number)
vend_products          (id VARCHAR UUID, supplier_id, name, sku, active)
vend_inventory         (product_id, outlet_id, current_amount)
faulty_products        (id INT, product_id, supplier_status 0/1)
vend_outlets           (id VARCHAR UUID, name, store_code)
```

### Common Filters
```sql
-- Active suppliers
WHERE deleted_at IS NULL

-- Active products
WHERE active = 1 AND is_active = 1 AND deleted_at = '0000-00-00 00:00:00'

-- Supplier's data only (ALWAYS)
WHERE supplier_id = ?

-- Last 30 days
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

-- Pending warranty claims
WHERE supplier_status = 0
```

## Testing Commands

```bash
# Syntax check
php -l api/endpoint.php

# Test DB connection
php -r "require 'bootstrap.php'; var_dump(pdo()->query('SELECT 1')->fetchColumn());"

# Run test suite
php tests/comprehensive-api-test.php

# Check logs
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
```

## Emergency Fixes

### Blank Page
```php
// Check: Is bootstrap loaded?
require_once dirname(__DIR__) . '/bootstrap.php';

// Check: Is session started?
// (bootstrap does this automatically)

// Check: Is auth working?
requireAuth(); // Should redirect or return 401
```

### 401 Unauthorized
```php
// Check: Did you call requireAuth()?
requireAuth();

// Check: Is session valid?
$supplierId = getSupplierID();
if (!$supplierId) {
    // Session expired, redirect to login
}
```

### SQL Error
```php
// Check: Using prepared statements?
$stmt = pdo()->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]); // ✅ CORRECT

// NOT this:
$sql = "SELECT * FROM table WHERE id = $id"; // ❌ WRONG
```

## File Locations

```
Production:   /home/master/applications/jcepnzzkmj/public_html/supplier/
Logs:         /home/master/applications/jcepnzzkmj/logs/apache_*.error.log
URL:          https://staff.vapeshed.co.nz/supplier/
Demo:         https://staff.vapeshed.co.nz/supplier/demo/index.html
KB Docs:      /supplier/docs/kb/
```

## Helper Functions (from bootstrap)

```php
pdo()                                  // Get PDO connection
db()                                   // Get MySQLi connection (legacy)
requireAuth()                          // Check auth, redirect/401 if not logged in
getSupplierID()                        // Get authenticated supplier UUID
e($string)                             // HTML escape: htmlspecialchars()
formatDate($timestamp, 'display')      // Format: "Oct 26, 2025"
sendJsonResponse($success, $data, $msg)// Send JSON API response
isJsonRequest()                        // Check if request expects JSON
logMessage($level, $message)           // Application logging
```

## Config Constants (from config.php)

```php
DB_HOST, DB_NAME, DB_USER, DB_PASS     // Database credentials
SESSION_LIFETIME                       // 86400 (24 hours)
PAGINATION_PER_PAGE                    // 25 rows per page
PO_PREFIX                              // 'JCE-PO-'
WARRANTY_RESPONSE_SLA_HOURS            // 48 hours
DEBUG_MODE                             // false in production
```

## Resources

- **Architecture:** `docs/kb/01-ARCHITECTURE.md`
- **Database:** `docs/kb/02-DATABASE-SCHEMA.md`
- **Full Guide:** `COMPLETE_IMPLEMENTATION_GUIDE.md`
- **Auth Flow:** `docs/AUTHENTICATION_FLOW.md`
- **UI Migration:** `DEMO_TO_PRODUCTION_MIGRATION_PLAN.md`

## Support

1. Search KB: `grep -r "topic" docs/kb/`
2. Check existing code for patterns
3. Review test files in `/tests/`
4. Check logs for errors
5. Verify bootstrap is loaded

---
**Print this card and keep it visible while coding!**
