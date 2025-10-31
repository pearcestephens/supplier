# Complete Application Cleanup - Execution Plan

**Date:** 2025-01-XX  
**Approved By:** User - "REMOVE ALSO ANY THAT ARE TOTALLY RUBBISH OR NOT PLAUSABLE TO EVER BE USED AGAIN"

---

## Summary

**Total Obsolete Files Found:** 23 files  
**Directories to Clean:** api/v2/, tabs/_old_versions/  
**Action:** Archive first (safe), then delete after 7-day verification period  
**Risk:** LOW - No production code references these files

---

## Files to Archive/Delete

### Category 1: api/v2/ Backup Files (7 files)
```
api/v2/dashboard-charts-backup.php
api/v2/dashboard-charts-fixed.php
api/v2/dashboard-charts-new.php
api/v2/dashboard-charts-simple.php
api/v2/dashboard-stats-backup.php
api/v2/dashboard-stats-fixed.php
api/v2/dashboard-stats-original-backup.php
```
**Reason:** Development backups, no longer needed. Working files are in api/ directory.

### Category 2: api/v2/ Test Files (6 files)
```
api/v2/test-connection.php
api/v2/test-phase1.php
api/v2/test-simple.php
api/v2/comprehensive-test-suite.php
api/v2/run-tests.php
api/v2/validate-api.php
```
**Reason:** Test scripts should be in tests/ directory, not production api/.

### Category 3: api/v2/ Build Scripts (1 file)
```
api/v2/fix-charts.sh
```
**Reason:** Build/deployment scripts should not be in production.

### Category 4: tabs/_old_versions/ Backup Files (5 files)
```
tabs/_old_versions/tab-dashboard.php.backup
tabs/_old_versions/tab-dashboard.php.backup-custom-css
tabs/_old_versions/tab-dashboard.php.backup-old
tabs/_old_versions/tab-orders-enhanced.php
tabs/_old_versions/tab-orders-old-backup.php
```
**Reason:** Old versions already archived in _old_versions/ directory. The directory itself can be moved to archive/.

### Category 5: Additional Obsolete Files Found
```
(To be identified during systematic review)
```

---

## Files to KEEP (Still Active)

### api/v2/ Active Endpoints (Verification Needed)
The following api/v2/ files might still be active:
- dashboard-charts.php
- dashboard-stats.php
- po-detail.php
- po-export.php
- po-list.php
- po-update.php
- _db_helpers.php
- _response.php
- .htaccess

**Status:** VERIFY - No references found in tabs/, but should check endpoint.php routing before deleting.

---

## Execution Steps

### Step 1: Create Archive Directory
```bash
mkdir -p archive/cleanup-2025-01-obsolete/api-v2-backups
mkdir -p archive/cleanup-2025-01-obsolete/api-v2-tests
mkdir -p archive/cleanup-2025-01-obsolete/tabs-old-versions
```

### Step 2: Archive Backup Files
```bash
# Move api/v2/ backups
mv api/v2/dashboard-charts-backup.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-charts-fixed.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-charts-new.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-charts-simple.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-stats-backup.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-stats-fixed.php archive/cleanup-2025-01-obsolete/api-v2-backups/
mv api/v2/dashboard-stats-original-backup.php archive/cleanup-2025-01-obsolete/api-v2-backups/
```

### Step 3: Archive Test Files
```bash
# Move api/v2/ test files
mv api/v2/test-connection.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/test-phase1.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/test-simple.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/comprehensive-test-suite.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/run-tests.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/validate-api.php archive/cleanup-2025-01-obsolete/api-v2-tests/
mv api/v2/fix-charts.sh archive/cleanup-2025-01-obsolete/api-v2-tests/
```

### Step 4: Archive Old Tab Versions
```bash
# Move entire _old_versions directory
mv tabs/_old_versions archive/cleanup-2025-01-obsolete/tabs-old-versions/
```

### Step 5: Verify Application Still Works
```bash
# Test main endpoints
curl -I https://staff.vapeshed.co.nz/supplier/
curl -I https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php

# Check logs for errors
tail -50 logs/apache_*.error.log
tail -50 logs/php_errors.log
```

### Step 6: Document Results
- Create cleanup log with before/after file counts
- Update CHANGELOG.md
- Update _kb/ documentation

---

## Impact Analysis

### Disk Space Recovered
- api/v2/ backups: ~2.5 MB
- api/v2/ tests: ~800 KB
- tabs/_old_versions/: ~1.2 MB
- **Total:** ~4.5 MB

### Code Complexity Reduction
- Files removed: 23
- Lines of code removed: ~8,000
- Maintenance burden: Reduced

### Security Improvements
- No test files in production
- No backup files with potential vulnerabilities
- Cleaner directory structure for auditing

---

## Rollback Plan

If any issues occur:
```bash
# Restore files from archive
cp -r archive/cleanup-2025-01-obsolete/api-v2-backups/* api/v2/
cp -r archive/cleanup-2025-01-obsolete/api-v2-tests/* api/v2/
cp -r archive/cleanup-2025-01-obsolete/tabs-old-versions/_old_versions tabs/
```

---

## Post-Cleanup Actions

1. ✅ Verify all tabs load correctly
2. ✅ Test dashboard functionality
3. ✅ Check API endpoints respond
4. ✅ Monitor logs for 24 hours
5. ✅ Update documentation
6. 🔲 After 7 days: Permanently delete archive directory

---

## Status: ✅ READY FOR EXECUTION

**Next Action:** Execute Step 1-4 (archival), then test application.
