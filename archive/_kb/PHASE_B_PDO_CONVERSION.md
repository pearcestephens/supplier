# Phase B: Complete PDO Conversion Plan

**Date:** October 25, 2025  
**Status:** In Progress  
**Goal:** Eliminate MySQLi completely, use PDO only

---

## 📊 Conversion Scope

### Files Using MySQLi (db() helper):
1. ✅ api/notifications-count.php
2. ✅ api/add-order-note.php
3. ✅ api/add-warranty-note.php
4. ✅ api/request-info.php
5. ✅ api/update-po-status.php
6. ✅ api/update-tracking.php
7. ✅ api/update-warranty-claim.php
8. ✅ api/warranty-action.php
9. ✅ index.php

### Files Using MySQLi Methods (MYSQLI_ASSOC, bind_param, etc.):
10. ⏳ tabs/tab-orders.php (4 queries)
11. ⏳ tabs/tab-warranty.php (5 queries)
12. ⏳ tabs/tab-reports.php (4 queries)
13. ⏳ tabs/tab-dashboard.php
14. ⏳ tabs/tab-downloads.php
15. ⏳ tabs/tab-account.php

### Bootstrap Changes:
16. ⏳ bootstrap.php - Remove MySQLi initialization
17. ⏳ Remove lib/Database.php from loading (keep for archive)
18. ⏳ Update helpers to only use pdo()

---

## 🔄 Conversion Pattern

### Old MySQLi Pattern:
```php
$db = db(); // MySQLi connection
$stmt = $db->prepare($sql);
$stmt->bind_param('si', $string, $int);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
```

### New PDO Pattern:
```php
$pdo = pdo(); // PDO connection
$stmt = $pdo->prepare($sql);
$stmt->execute([$string, $int]); // Direct array binding
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
// No close needed - auto-cleanup
```

### Benefits:
- ✅ Simpler syntax (no bind_param type strings)
- ✅ Named parameters support: `:name` instead of `?`
- ✅ Automatic cleanup (no manual close)
- ✅ Better error handling
- ✅ Half the database connections per request
- ✅ More modern, better maintained

---

## 📝 Conversion Steps

### Step 1: Convert API Files (9 files)
- Replace `$db = db()` with `$pdo = pdo()`
- Replace `$conn = db()` with `$pdo = pdo()`
- Convert all queries from MySQLi to PDO syntax

### Step 2: Convert Tab Files (6 files)
- Convert all MYSQLI_ASSOC to PDO::FETCH_ASSOC
- Convert bind_param to execute with array
- Convert get_result()->fetch_all() to fetchAll()

### Step 3: Update Bootstrap
- Remove MySQLi initialization
- Remove Database.php loading
- Keep only PDO connection
- Update db() helper to return PDO (or remove it)

### Step 4: Test Everything
- Run comprehensive test suite
- Verify all APIs work
- Verify all tabs work
- Check error handling

---

## 🎯 Expected Results

**Before:**
- 2 database connections per request (MySQLi + PDO)
- Inconsistent query syntax
- More verbose code
- Manual cleanup required

**After:**
- 1 database connection per request (PDO only)
- Consistent modern syntax
- Cleaner code
- Automatic cleanup
- 50% reduction in database load

---

## ⏱️ Timeline

- Step 1 (API Files): 30 minutes
- Step 2 (Tab Files): 45 minutes
- Step 3 (Bootstrap): 15 minutes
- Step 4 (Testing): 30 minutes
- **Total: ~2 hours**

---

## 🚀 Let's Begin!
