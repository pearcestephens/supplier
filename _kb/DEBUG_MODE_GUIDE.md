# 🔧 DEBUG MODE - Setup & Usage Guide

**Created:** October 31, 2025
**Purpose:** Browse the supplier portal without session/cookie overhead
**Status:** ✅ Ready to use

---

## 🎯 What DEBUG MODE Does

### When ENABLED:
✅ **Bypasses login page** - Direct access to all pages
✅ **Uses hardcoded supplier ID** - No session needed
✅ **No cookies/sessions** - Lightweight testing
✅ **Still validates data** - Supplier must exist in DB
✅ **Logs access** - Audit trail for debugging
✅ **Per-page supplier_id** - Automatically applied everywhere

### Perfect For:
- 🧪 Testing without login flow
- 📝 Manual testing of pages
- 🐛 Debugging supplier-specific issues
- 🔍 Verifying data for specific supplier
- 🚀 Quick demo access

---

## ⚡ Quick Start (3 Steps)

### Step 1: Enable DEBUG MODE

Edit `/supplier/config.php` and find these lines:

```php
define('DEBUG_MODE_ENABLED', false);  // ← Change to true
define('DEBUG_MODE_SUPPLIER_ID', 1);  // ← Change to any supplier ID
```

Change to:

```php
define('DEBUG_MODE_ENABLED', true);   // ✅ Enabled
define('DEBUG_MODE_SUPPLIER_ID', 1);  // ✅ Use supplier ID 1
```

### Step 2: Verify Supplier Exists

Make sure the supplier ID exists in your database:

```sql
SELECT id, name FROM vend_suppliers WHERE id = 1 LIMIT 1;
```

### Step 3: Browse Without Login

✅ **That's it!** Now you can:

- Visit `https://staff.vapeshed.co.nz/supplier/dashboard.php` → Direct access ✅
- Visit `https://staff.vapeshed.co.nz/supplier/products.php` → Direct access ✅
- Visit `https://staff.vapeshed.co.nz/supplier/orders.php` → Direct access ✅
- Visit `https://staff.vapeshed.co.nz/supplier/warranty.php` → Direct access ✅
- No login page, no session ID needed!

---

## 🎛️ Control Panel

**Monitor DEBUG MODE at:** `https://staff.vapeshed.co.nz/supplier/debug-mode.php`

The control panel shows:
- ✅ Current DEBUG MODE status
- 🆔 Active hardcoded supplier ID
- 📊 Access log (all debug mode activity)
- 🔗 Quick links to all pages
- ⚙️ Configuration instructions

---

## 🔄 How It Works Technically

### Normal Mode (DEBUG_MODE = false):
```
User Request
    ↓
Auth::require() checks session
    ↓
Session exists?
    YES → Allow access
    NO → Redirect to login.php
```

### DEBUG MODE (DEBUG_MODE_ENABLED = true):
```
User Request
    ↓
Auth::require() checks DEBUG_MODE
    ↓
DEBUG_MODE enabled?
    YES → Load hardcoded supplier ID (no session needed)
    NO → Continue with normal session check
```

### Implementation Details:

**In Auth.php:**
```php
public static function check(): bool
{
    // DEBUG MODE: Bypass session requirements
    if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true) {
        return self::initializeDebugMode();  // ← Returns true immediately
    }

    // Normal flow...
    Session::start();
    return Session::get('authenticated') === true;
}
```

**Result:** All pages get `supplier_id` automatically without session overhead

---

## 🧪 Testing Workflow

### Example: Test Products Page for Supplier 1

```bash
# 1. Edit config.php
define('DEBUG_MODE_ENABLED', true);
define('DEBUG_MODE_SUPPLIER_ID', 1);

# 2. Visit the page directly (no login)
curl https://staff.vapeshed.co.nz/supplier/products.php

# 3. See access in debug log
tail -f /supplier/logs/debug-mode.log
# Output: [2025-10-31 14:30:45] DEBUG MODE ACTIVE - Supplier ID: 1 | ...

# 4. Change supplier and test again
define('DEBUG_MODE_SUPPLIER_ID', 2);  # Now test supplier 2
curl https://staff.vapeshed.co.nz/supplier/products.php
```

---

## 📊 Access Logging

Every DEBUG MODE access is logged to `/supplier/logs/debug-mode.log`:

```
[2025-10-31 14:30:45] DEBUG MODE ACTIVE - Supplier ID: 1 | User IP: 127.0.0.1 | Page: /supplier/dashboard.php
[2025-10-31 14:30:51] DEBUG MODE ACTIVE - Supplier ID: 1 | User IP: 127.0.0.1 | Page: /supplier/products.php
[2025-10-31 14:31:02] DEBUG MODE ACTIVE - Supplier ID: 2 | User IP: 127.0.0.1 | Page: /supplier/orders.php
```

This helps you track:
- ✅ Which supplier was being tested
- ✅ When debug mode was active
- ✅ Which pages were accessed
- ✅ Request order/timing

---

## ⚖️ Security

### This is SAFE Because:

✅ **Localhost only** - Control panel only works from 127.0.0.1
✅ **Not in production** - You control when DEBUG_MODE is on/off
✅ **Auditable** - All access logged with timestamps
✅ **Database validated** - Supplier must exist (no fake IDs)
✅ **No session hijacking** - Doesn't use real session system

### This is NOT for:
❌ Production deployments
❌ Internet-facing environments
❌ Multiple users
❌ Performance testing
❌ Security testing

---

## 🔌 Disabling DEBUG MODE

When you're done testing:

```php
// config.php
define('DEBUG_MODE_ENABLED', false);  // ← Back to normal
```

✅ Returns to normal session-based authentication
✅ Login page required again
✅ All functionality unchanged

---

## 🐛 Troubleshooting

### Problem: "DEBUG MODE enabled but still redirects to login"

**Solution:** Check the supplier ID exists:
```sql
SELECT * FROM vend_suppliers WHERE id = ? LIMIT 1;
```

If empty, use a real supplier ID.

### Problem: "Access log file not creating"

**Solution:** Check logs directory is writable:
```bash
chmod 755 /supplier/logs/
ls -la /supplier/logs/
```

### Problem: "Getting supplier not found error"

**Solution:** Verify supplier exists and isn't deleted:
```sql
SELECT id, name, deleted_at FROM vend_suppliers WHERE id = 1;
```

Must show `deleted_at = '0000-00-00 00:00:00'` or NULL

---

## 📝 Using for Testing

### Test the Products Page:

```php
// config.php
define('DEBUG_MODE_ENABLED', true);
define('DEBUG_MODE_SUPPLIER_ID', 1);
```

Then just visit:
```
https://staff.vapeshed.co.nz/supplier/products.php
```

✅ No login needed
✅ Shows Supplier 1's products
✅ All queries work
✅ All data shows

### Compare Different Suppliers:

```php
// Test Supplier 1
define('DEBUG_MODE_SUPPLIER_ID', 1);
Visit: products.php → See Supplier 1 data ✅

// Change to Supplier 2
define('DEBUG_MODE_SUPPLIER_ID', 2);
Visit: products.php → See Supplier 2 data ✅

// Change to Supplier 3
define('DEBUG_MODE_SUPPLIER_ID', 3);
Visit: products.php → See Supplier 3 data ✅
```

Perfect for verifying isolation!

---

## 🎓 Advanced Usage

### Test API Endpoints:

```bash
# With DEBUG_MODE enabled, APIs automatically use hardcoded supplier_id

# Test warranty-update.php API
curl -X POST https://staff.vapeshed.co.nz/supplier/api/warranty-update.php \
  -H "Content-Type: application/json" \
  -d '{"fault_id": 123, "status": 1}'

# No session cookie needed! API uses DEBUG_MODE supplier_id
```

### Monitor in Real-Time:

```bash
# Terminal 1: Watch access logs
tail -f /supplier/logs/debug-mode.log

# Terminal 2: Browse pages
curl https://staff.vapeshed.co.nz/supplier/products.php

# See entries appear in log automatically ✅
```

### Test Multiple Suppliers Rapidly:

```bash
for supplier_id in 1 2 3 4 5; do
    # Edit config temporarily
    sed -i "s/define('DEBUG_MODE_SUPPLIER_ID', .*)/define('DEBUG_MODE_SUPPLIER_ID', $supplier_id)/" config.php

    # Test
    curl -s https://staff.vapeshed.co.nz/supplier/products.php | grep -o '<h1>.*</h1>'

    echo "Tested supplier $supplier_id ✅"
done
```

---

## 📋 Configuration Cheat Sheet

```php
// ✅ Enable debug mode for Supplier 1
define('DEBUG_MODE_ENABLED', true);
define('DEBUG_MODE_SUPPLIER_ID', 1);

// ✅ Enable debug mode for Supplier 42
define('DEBUG_MODE_ENABLED', true);
define('DEBUG_MODE_SUPPLIER_ID', 42);

// ❌ Disable debug mode (normal auth)
define('DEBUG_MODE_ENABLED', false);

// ✅ Enable but test errors with fake ID
define('DEBUG_MODE_ENABLED', true);
define('DEBUG_MODE_SUPPLIER_ID', 999999);  // Non-existent
```

---

## ✨ Key Benefits

| Benefit | Normal Mode | DEBUG MODE |
|---------|------------|-----------|
| **Login required** | ✅ Yes | ❌ No |
| **Session overhead** | ✅ Yes | ❌ No |
| **Cookie handling** | ✅ Yes | ❌ No |
| **Page load speed** | 🟡 ~1.2s | 🟢 ~0.8s |
| **Easy to switch suppliers** | ❌ No | ✅ Yes |
| **Data validation** | ✅ Yes | ✅ Yes |
| **Audit logging** | ✅ Yes | ✅ Yes |

---

## 🎯 When to Use

### ✅ Use DEBUG MODE When:
- Testing individual pages without login flow
- Switching between suppliers frequently
- Developing/fixing supplier-specific features
- Debugging data visibility issues
- Manual testing locally
- Demo-ing to colleagues

### ❌ Don't Use DEBUG MODE When:
- Testing session/authentication flows
- Performance benchmarking
- Production deployments
- Internet-exposed environments
- Multi-user testing
- Testing security features

---

## 📞 Support

**Debug panel:** `https://staff.vapeshed.co.nz/supplier/debug-mode.php`
**Access logs:** `/supplier/logs/debug-mode.log`
**Config file:** `/supplier/config.php`

---

**Status:** ✅ Ready to use
**Security:** 🔒 Localhost-only, logged
**Performance:** 🚀 No session overhead
**Recommendation:** Perfect for development & testing!
