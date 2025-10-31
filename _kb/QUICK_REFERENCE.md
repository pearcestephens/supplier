# Supplier Portal - Quick Reference Guide
**Last Updated:** October 31, 2025

---

## 🚀 All Systems Operational

### Pages (9 Total)
```
✅ dashboard.php          → 200 OK | Performance metrics dashboard
✅ products.php           → 200 OK | Product analytics & KPIs
✅ orders.php             → 200 OK | Order management hub
✅ warranty.php           → 200 OK | Warranty & fault tracking
✅ account.php            → 200 OK | Supplier settings
✅ reports.php            → 200 OK | Report generation
✅ catalog.php            → 200 OK | Product catalog browser
✅ inventory-movements.php → 200 OK | Inventory movement log
⚠️ downloads.php          → 302 Redirect | Intentional feature
```

### APIs (5 Total)
```
✅ api/dashboard-stats.php    → 200 OK
✅ api/dashboard-charts.php   → 200 OK
✅ api/dashboard-insights.php → 200 OK
✅ api/export-orders.php      → 200 OK
✅ api/generate-report.php    → 200 OK
```

---

## 🔧 Critical Database Column Mappings

### MUST USE (Not alternatives)
```
vend_inventory:
  ✅ inventory_level     (NOT quantity)

vend_consignment_line_items:
  ✅ transfer_id         (NOT consignment_id)

faulty_products:
  ✅ time_created        (NOT created_at)

vend_products:
  ✅ price_including_tax (Retail price with GST)
  ✅ supply_price        (Cost/wholesale price)
```

---

## 💰 Price Fields in vend_products

```sql
SELECT
  supply_price,           -- Cost price
  price_including_tax,    -- Retail with GST
  price_excluding_tax     -- Retail without GST
FROM vend_products;
```

**Margin Calculation:**
```php
margin = ((price_including_tax - supply_price) / supply_price) * 100
```

---

## 📁 Component Includes - CORRECT

```php
// ✅ CORRECT
<?php include __DIR__ . '/components/sidebar-new.php'; ?>
<?php include __DIR__ . '/components/page-header.php'; ?>

// ❌ WRONG (do not use)
<?php include __DIR__ . '/components/sidebar.php'; ?>
<?php include __DIR__ . '/components/header.php'; ?>
```

---

## 🔑 Auth Class Methods

```php
Auth::getSupplierId()     // Get current supplier ID
Auth::getSupplierName()   // Get current supplier name
Auth::check()             // Check if authenticated
Auth::loginById($id)      // Login by supplier ID
Auth::logout()            // Logout
```

---

## 📊 File Modifications Summary

**Products.php:**
- Enhanced query with all analytics fields
- Added default values for missing calculations

**Catalog.php:**
- Fixed table names: products → vend_products, inventory → vend_inventory
- Added price columns: supply_price, price_including_tax
- Added margin calculation
- Fixed component includes

**Orders.php:**
- Fixed JOIN: consignment_id → transfer_id

**Warranty.php:**
- Fixed column names: created_at → time_created, issue_category → fault_desc

**Inventory-Movements.php:**
- Fixed component includes: sidebar → sidebar-new, header → page-header

**Dashboard APIs:**
- Fixed inventory column: vi.quantity → vi.inventory_level
- Fixed GROUP BY and JOIN clauses

---

## ✅ Testing Commands

```bash
# Test single page
curl -I https://staff.vapeshed.co.nz/supplier/products.php

# Test all pages
for p in dashboard products orders warranty account reports catalog downloads inventory-movements; do
  curl -s -w "%{http_code}" -o /dev/null "https://staff.vapeshed.co.nz/supplier/$p.php"
done

# Test all APIs
for api in dashboard-stats dashboard-charts dashboard-insights export-orders generate-report; do
  curl -s -w "%{http_code}" -o /dev/null "https://staff.vapeshed.co.nz/supplier/api/$api.php"
done
```

---

## 🐛 Common Errors & Fixes

### "Unknown column 'X'"
→ Check database column mapping table above

### "Failed to open 'components/header.php'"
→ Use `components/page-header.php` instead

### "Call to undefined method Auth::userId()"
→ Use `Auth::getSupplierId()` instead

### "SQLSTATE[42S02]: Base table not found"
→ Check table name: use `vend_products`, not `products`

---

## 📋 Deployment Checklist

- ✅ All 14 components tested and operational
- ✅ Database schema verified
- ✅ Component files in correct location
- ✅ Session management functional
- ✅ Authentication working
- ✅ No error logs
- ✅ API responses validated
- ✅ Performance metrics acceptable

---

## 📝 Error Log Location

```
/home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log
```

Monitor for:
- Fatal errors
- SQL errors
- Missing includes
- Undefined methods

---

## 🔄 Session Management

- Session name: `CIS_SUPPLIER_SESSION`
- Secure: ✅ Enabled
- HttpOnly: ✅ Enabled
- SameSite: Lax
- Max-Age: 30 days
- Auto-regeneration: On login

---

## 🎯 Next Steps

1. **Monitor** - Watch error logs for any issues
2. **Test** - Run comprehensive tests weekly
3. **Backup** - Regular database backups
4. **Update** - Keep PHP and dependencies current

---

## 📞 Support Resources

- Database schema: See SUPPLIER_PORTAL_COMPLETION_REPORT.md
- Code changes: Review git history or modification timestamps
- Performance: Check Apache logs and query times
- Status: Run testing commands above

---

**Status:** ✅ PRODUCTION READY
**Last Verified:** October 31, 2025
**All Systems:** OPERATIONAL
