# 🚨 QUICK START: Historical Purchase Orders Migration

**Problem:** Only 94 orders visible, 11,472 missing
**Solution:** Ready to execute migration
**Time:** 30 minutes
**Risk:** LOW ✅

---

## 📊 The Numbers

| What | Count |
|------|-------|
| **Currently Visible** | 94 orders |
| **Missing (Hidden)** | 11,472 orders |
| **Will Be Visible** | 11,566 orders |
| **Line Items** | 259,227 |
| **Suppliers Affected** | 29 |

---

## ⚡ Quick Execute

### Option 1: Automated (RECOMMENDED)
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier
./migrations/run-migration.sh
```

**The script will:**
1. ✅ Create backups automatically
2. ✅ Migrate 10 test records
3. ⏸️ PAUSE for you to verify in supplier portal
4. ✅ Wait for your approval
5. ✅ Migrate all remaining records
6. ✅ Show verification report

---

### Option 2: Manual SQL
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/006_migrate_historical_purchase_orders.sql
```

---

## ✋ STOP Points (Manual Checks Required)

### Stop #1: After Test Migration
**When:** After 10 records migrated
**What to Check:**
1. Open supplier portal
2. Log in as a test supplier
3. Verify 10 old orders appear
4. Check line items display
5. Confirm dates are correct

**Then:** Approve to continue OR stop if issues

---

### Stop #2: After Full Migration
**When:** All 11,472 records migrated
**What to Check:**
1. Run verification queries (automatic)
2. Check counts match expected
3. Review final report

---

## 🔍 Quick Verification Queries

### Check Current State
```sql
-- How many orders currently visible?
SELECT COUNT(*) FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Should be: 94

-- How many to migrate?
SELECT COUNT(*) FROM purchase_orders
WHERE deleted_at IS NULL;
-- Should be: 11,472
```

### After Migration
```sql
-- Total orders now visible?
SELECT COUNT(*) FROM vend_consignments
WHERE transfer_category = 'PURCHASE_ORDER';
-- Should be: 11,566

-- All linked?
SELECT COUNT(*) FROM purchase_orders
WHERE vend_consignment_id IS NOT NULL;
-- Should be: 11,472
```

---

## 🆘 Rollback (If Needed)

**If something goes wrong:**
```sql
-- Delete migrated records
DELETE FROM vend_consignment_line_items
WHERE transfer_id IN (
    SELECT id FROM vend_consignments
    WHERE public_id LIKE 'PO-%'
);

DELETE FROM vend_consignments
WHERE public_id LIKE 'PO-%';

-- Reset links
UPDATE purchase_orders
SET vend_consignment_id = NULL;
```

**Or restore from backup:**
```sql
DROP TABLE vend_consignments;
CREATE TABLE vend_consignments
AS SELECT * FROM vend_consignments_backup_20251031;
```

---

## 📋 Pre-Flight Checklist

Before running migration:

- [ ] Read MIGRATION_SUMMARY.md
- [ ] Database backup space available (~500MB)
- [ ] No other users making changes
- [ ] Test supplier account ready
- [ ] 30 minutes of uninterrupted time

---

## 🎯 Success Looks Like

**In Supplier Portal:**
```
Total Orders: 11,566
Date Range: 2018-12-17 to 2025-10-15
Top Supplier: 3,453 orders
All line items visible
No errors
```

**In Database:**
```
vend_consignments: 11,566 PURCHASE_ORDER records ✓
purchase_orders: All have vend_consignment_id ✓
Line items: All migrated ✓
```

---

## 📞 Need Help?

**Files to Check:**
- Full analysis: `_kb/HISTORICAL_DATA_ANALYSIS.md`
- Summary: `_kb/MIGRATION_SUMMARY.md`
- SQL script: `migrations/006_migrate_historical_purchase_orders.sql`
- Bash script: `migrations/run-migration.sh`

**Logs:**
- Check terminal output during migration
- MySQL errors will be shown in red

---

## ⏱️ Timeline

```
00:00 - Start script
00:02 - Backups created
00:04 - Pre-verification complete
00:09 - Test migration done → YOU CHECK SUPPLIER PORTAL
00:14 - You approve → Full migration starts
00:19 - Orders migrated
00:27 - Line items migrated
00:30 - Final verification → DONE!
```

---

## 🚀 Ready?

**Command:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier
./migrations/run-migration.sh
```

**Watch for:**
- ✅ Green checkmarks = success
- ⏸️ Yellow prompts = you need to check/approve
- ❌ Red X = error (stop and review)

---

**READY TO EXECUTE** ✅
**ALL SCRIPTS PREPARED** ✅
**BACKUPS WILL BE AUTOMATIC** ✅
**ROLLBACK AVAILABLE** ✅
