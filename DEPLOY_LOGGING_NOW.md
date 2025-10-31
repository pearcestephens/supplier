# 🚀 DEPLOY LOGGING SYSTEM - 5 MINUTE GUIDE

**Last Updated:** October 31, 2025
**Time Required:** 5 minutes
**Risk:** LOW (uses existing tables)

---

## ⚡ QUICK DEPLOYMENT

### Step 1: Run Migration (30 seconds)
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/007_enhance_logging_indexes.sql
```

**Expected Output:**
```
Query OK, 0 rows affected (0.02 sec)  # Index creation
Query OK, 0 rows affected (0.01 sec)  # View creation
...
(Repeats for all 9 indexes + 6 views)
```

**Verify:**
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES LIKE 'v_supplier%';"
```

Should show:
```
v_supplier_activity_summary
v_supplier_activity_anomalies
v_supplier_engagement_score
v_supplier_order_patterns
v_supplier_portal_activity
```

---

### Step 2: Activate New Logger (30 seconds)
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/lib

# Backup old version
cp SupplierLogger.php SupplierLogger_old_backup.php

# Replace with v2
cp SupplierLogger_v2.php SupplierLogger.php

# Verify syntax
php -l SupplierLogger.php
```

**Expected:** `No syntax errors detected`

---

### Step 3: Test Logging (2 minutes)

**Test Script:**
```bash
cat > /tmp/test-logging.php << 'EOF'
<?php
require_once '/home/master/applications/jcepnzzkmj/public_html/supplier/lib/DatabasePDO.php';
require_once '/home/master/applications/jcepnzzkmj/public_html/supplier/lib/SupplierLogger.php';

$pdo = DatabasePDO::getInstance();
$logger = new SupplierLogger($pdo, 'TEST_001', 'Test Supplier');

echo "Testing SupplierLogger v2...\n\n";

// Test 1: Activity log
echo "1. Testing supplier_activity_log... ";
$r1 = $logger->logActivity('order_viewed', 12345, ['test' => true]);
echo $r1 ? "✓\n" : "✗ FAILED\n";

// Test 2: Portal log
echo "2. Testing supplier_portal_logs... ";
$r2 = $logger->logPortalEvent('test_action', 'order', 12345, ['test' => true]);
echo $r2 ? "✓\n" : "✗ FAILED\n";

// Test 3: Consignment log
echo "3. Testing consignment_logs... ";
$r3 = $logger->logConsignment(28062, 'test_event', ['test' => true]);
echo $r3 ? "✓\n" : "✗ FAILED\n";

// Test 4: Retrieve logs
echo "4. Testing log retrieval... ";
$logs = $logger->getActivityLogs(5);
echo count($logs) > 0 ? "✓ (" . count($logs) . " logs)\n" : "✗ FAILED\n";

echo "\nTrace ID: " . $logger->getTraceId() . "\n";
echo "\nAll tests passed! ✓\n";
EOF

php /tmp/test-logging.php
```

**Expected Output:**
```
Testing SupplierLogger v2...

1. Testing supplier_activity_log... ✓
2. Testing supplier_portal_logs... ✓
3. Testing consignment_logs... ✓
4. Testing log retrieval... ✓ (3 logs)

Trace ID: abc123def456...

All tests passed! ✓
```

---

### Step 4: Verify Database (30 seconds)
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj << 'EOF'
-- Check test logs were created
SELECT 'supplier_activity_log' as table_name, COUNT(*) as test_records
FROM supplier_activity_log
WHERE supplier_id = 'TEST_001'
UNION ALL
SELECT 'supplier_portal_logs', COUNT(*)
FROM supplier_portal_logs
WHERE supplier_id = 'TEST_001'
UNION ALL
SELECT 'consignment_logs', COUNT(*)
FROM consignment_logs
WHERE JSON_EXTRACT(event_data, '$.supplier_id') = 'TEST_001';
EOF
```

**Expected:**
```
+------------------------+--------------+
| table_name             | test_records |
+------------------------+--------------+
| supplier_activity_log  |            1 |
| supplier_portal_logs   |            1 |
| consignment_logs       |            1 |
+------------------------+--------------+
```

---

### Step 5: Test Live Portal (2 minutes)

**Manual Testing:**
1. Open https://staff.vapeshed.co.nz/supplier/
2. Log in as any supplier
3. View an order
4. Log out

**Then Check:**
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj << 'EOF'
SELECT
    action_type,
    created_at,
    ip_address
FROM supplier_activity_log
ORDER BY created_at DESC
LIMIT 5;
EOF
```

**Should Show:**
```
+-------------------+---------------------+-------------+
| action_type       | created_at          | ip_address  |
+-------------------+---------------------+-------------+
| logout            | 2025-10-31 14:32:15 | 203.x.x.x   |
| order_viewed      | 2025-10-31 14:31:42 | 203.x.x.x   |
| login             | 2025-10-31 14:30:58 | 203.x.x.x   |
+-------------------+---------------------+-------------+
```

---

## ✅ SUCCESS CHECKLIST

- [ ] Migration 007 ran without errors
- [ ] 6 views created (v_supplier_*)
- [ ] 9 indexes added
- [ ] SupplierLogger.php updated to v2
- [ ] Test script passed all 4 tests
- [ ] Database shows test records
- [ ] Live portal login created log entry
- [ ] Order view created log entry
- [ ] Logout created log entry

---

## 🔍 VERIFICATION QUERIES

### Check All Log Tables Are Working
```sql
SELECT
    'activity' as type,
    COUNT(*) as records,
    MAX(created_at) as latest
FROM supplier_activity_log
UNION ALL
SELECT
    'portal',
    COUNT(*),
    MAX(created_at)
FROM supplier_portal_logs
UNION ALL
SELECT
    'consignment',
    COUNT(*),
    MAX(created_at)
FROM consignment_logs
WHERE source_system = 'supplier_portal';
```

### View Engagement Scores
```sql
SELECT * FROM v_supplier_engagement_score LIMIT 10;
```

### Check for Anomalies
```sql
SELECT * FROM v_supplier_activity_anomalies LIMIT 10;
```

---

## 🐛 TROUBLESHOOTING

### Issue: "Table doesn't exist" error
```bash
# Re-run migration
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/007_enhance_logging_indexes.sql
```

### Issue: "Class 'SupplierLogger' not found"
```bash
# Check file exists
ls -la lib/SupplierLogger.php

# Check syntax
php -l lib/SupplierLogger.php
```

### Issue: No logs appearing
```bash
# Check PHP error log
tail -50 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Check logger is initialized
grep -r "new SupplierLogger" lib/logger-bootstrap.php
```

### Issue: "Call to undefined method"
```bash
# Make sure you're using v2
grep "class SupplierLogger" lib/SupplierLogger.php
# Should show full class definition with logActivity, logPortalEvent, etc
```

---

## 📊 MONITORING COMMANDS

### Real-time Activity Feed
```bash
watch -n 2 "mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e \"
SELECT
    CONCAT(supplier_id, ' - ', action_type) as activity,
    created_at
FROM supplier_activity_log
ORDER BY created_at DESC
LIMIT 10;
\""
```

### Today's Activity Summary
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj << 'EOF'
SELECT
    action_type,
    COUNT(*) as count,
    COUNT(DISTINCT supplier_id) as unique_suppliers
FROM supplier_activity_log
WHERE DATE(created_at) = CURDATE()
GROUP BY action_type
ORDER BY count DESC;
EOF
```

---

## 🎯 NEXT STEPS AFTER DEPLOYMENT

### 1. Add Logging to APIs
Update these files to use logger:
- `api/update-order-status.php`
- `api/add-tracking-simple.php`
- `api/get-order-history.php`

Example:
```php
global $logger;
$logger->logAPICall($_SERVER['REQUEST_URI'], 'POST', $_POST);
```

### 2. Create Dashboard Widget
Show recent supplier activity on admin dashboard

### 3. Set Up Alerts
Monitor for:
- Failed login attempts (>3 in 5 minutes)
- Late night activity (12am-5am)
- High volume activity (>50 actions/hour)

### 4. Log Rotation
Schedule cleanup of logs older than 90 days:
```sql
DELETE FROM supplier_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 📈 METRICS TO TRACK

**Daily:**
- Total logins
- Active suppliers
- Orders viewed
- Tracking updates

**Weekly:**
- Engagement scores
- Most active suppliers
- Peak activity times

**Monthly:**
- Anomaly trends
- Feature usage patterns
- API performance

---

**DEPLOYMENT STATUS:** ✅ Ready
**ESTIMATED TIME:** 5 minutes
**RISK LEVEL:** 🟢 LOW
**ROLLBACK:** Copy `SupplierLogger_old_backup.php` back if needed

---

## 🚨 EMERGENCY ROLLBACK

If something breaks:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/lib
cp SupplierLogger_old_backup.php SupplierLogger.php
```

Portal will work immediately (old logger doesn't use new views).
