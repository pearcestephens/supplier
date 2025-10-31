# Obsolete Files Cleanup Report

**Date:** 2025-01-XX  
**Purpose:** Remove backup, test, and obsolete files from the application  
**Approved By:** User directive - "REMOVE ALSO ANY THAT ARE TOTALLY RUBBISH OR NOT PLAUSABLE TO EVER BE USED AGAIN"

---

## Files to be DELETED from api/v2/

### Backup Files (Created During Development, No Longer Needed)
1. ✅ **dashboard-charts-backup.php** - Old backup before fixes
2. ✅ **dashboard-charts-fixed.php** - Intermediate fix attempt
3. ✅ **dashboard-charts-new.php** - Alternative implementation
4. ✅ **dashboard-charts-simple.php** - Simplified version for testing
5. ✅ **dashboard-stats-backup.php** - Old backup
6. ✅ **dashboard-stats-fixed.php** - Intermediate fix
7. ✅ **dashboard-stats-original-backup.php** - Original version backup

**Reason:** These are development artifacts. The working versions are in api/ directory (not v2/).

### Test Files (Development/QA Only, Not Production)
8. ✅ **test-connection.php** - Database connection test
9. ✅ **test-phase1.php** - Phase 1 testing script
10. ✅ **test-simple.php** - Simple test script
11. ✅ **comprehensive-test-suite.php** - Full test suite
12. ✅ **run-tests.php** - Test runner
13. ✅ **validate-api.php** - API validation script

**Reason:** Test files should not be in production. Move to tests/ directory if needed.

### Build/Utility Scripts
14. ✅ **fix-charts.sh** - Shell script for fixing charts

**Reason:** Build scripts should not be in production API directory.

---

## Files to KEEP in api/v2/ (Active Features)

These files appear to be part of a v2 API implementation:

1. ✅ **dashboard-charts.php** - Active chart data endpoint
2. ✅ **dashboard-stats.php** - Active stats endpoint
3. ✅ **po-detail.php** - Purchase order detail endpoint
4. ✅ **po-export.php** - Purchase order export
5. ✅ **po-list.php** - Purchase order listing
6. ✅ **po-update.php** - Purchase order updates
7. ✅ **_db_helpers.php** - Database utility functions
8. ✅ **_response.php** - Response formatting helpers
9. ✅ **.htaccess** - Web server configuration

**Note:** Need to verify if v2/ endpoints are actually used in production or if they duplicate api/ endpoints.

---

## Analysis: api/v2/ vs api/ Duplication

### Files that exist in BOTH locations:
- **dashboard-charts.php** - Exists in api/ and api/v2/
- **dashboard-stats.php** - Exists in api/ and api/v2/
- **po-detail.php** - Exists in api/ and api/v2/
- **po-list.php** - Exists in api/ and api/v2/
- **po-update.php** - Exists in api/ and api/v2/

**Question:** Are tabs using api/v2/* or api/* versions?

**Answer from grep search:** Tabs are using `/supplier/api/*` paths, NOT `/supplier/api/v2/*`

**Conclusion:** The api/v2/ directory appears to be an abandoned v2 API attempt. Since tabs use api/ endpoints, the entire v2/ directory can likely be archived or deleted.

---

## Recommended Action Plan

### Phase 1: Delete Obvious Obsolete Files (NOW)
Delete all backup and test files from api/v2/:
- dashboard-charts-backup.php
- dashboard-charts-fixed.php
- dashboard-charts-new.php
- dashboard-charts-simple.php
- dashboard-stats-backup.php
- dashboard-stats-fixed.php
- dashboard-stats-original-backup.php
- test-connection.php
- test-phase1.php
- test-simple.php
- comprehensive-test-suite.php
- run-tests.php
- validate-api.php
- fix-charts.sh

**Total:** 14 files to delete

### Phase 2: Verify api/v2/ Usage (NEXT)
1. Search all tabs/* and assets/js/* for references to `/api/v2/`
2. Search for `endpoint.php` handlers that might route to v2/
3. If NO references found → archive entire api/v2/ directory

### Phase 3: Move Valid Tests to tests/ (OPTIONAL)
If any test files are valuable:
- Move comprehensive-test-suite.php to tests/api-suite.php
- Move validate-api.php to tests/api-validation.php
- Update paths and document usage

---

## Impact Assessment

### Risk Level: LOW
- Backup files have no functional code dependencies
- Test files are not referenced in production code
- All active endpoints are in api/ directory

### Testing Required:
- None for backup files (they're not used)
- Verify application still works after deletion (smoke test)

### Rollback Plan:
- Files moved to archive/ directory first
- Can restore if any issues found within 30 days

---

## Execution Log

```bash
# Create archive directory
mkdir -p /home/master/applications/jcepnzzkmj/public_html/supplier/archive/api-v2-cleanup-2025-01

# Move files to archive (not delete yet)
mv api/v2/dashboard-charts-backup.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-charts-fixed.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-charts-new.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-charts-simple.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-stats-backup.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-stats-fixed.php archive/api-v2-cleanup-2025-01/
mv api/v2/dashboard-stats-original-backup.php archive/api-v2-cleanup-2025-01/
mv api/v2/test-connection.php archive/api-v2-cleanup-2025-01/
mv api/v2/test-phase1.php archive/api-v2-cleanup-2025-01/
mv api/v2/test-simple.php archive/api-v2-cleanup-2025-01/
mv api/v2/comprehensive-test-suite.php archive/api-v2-cleanup-2025-01/
mv api/v2/run-tests.php archive/api-v2-cleanup-2025-01/
mv api/v2/validate-api.php archive/api-v2-cleanup-2025-01/
mv api/v2/fix-charts.sh archive/api-v2-cleanup-2025-01/

# Test application
# If all good after 7 days, permanently delete archive directory
```

---

## Status: ✅ READY FOR EXECUTION

All 14 obsolete files identified and ready for archival.
User approval received: "REMOVE ALSO ANY THAT ARE TOTALLY RUBBISH"

**Next:** Execute file moves, then continue with API standardization.
