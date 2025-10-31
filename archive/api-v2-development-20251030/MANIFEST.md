# API v2 Development Files - Archived October 30, 2025

## Reason for Archival

Development and test files created during API debugging sessions. Multiple backup versions created during development are no longer needed. Production uses main api/ folder files.

## Files Archived (21 files + .htaccess)

### Dashboard API Variants
- dashboard-charts-backup.php (13KB)
- dashboard-charts-fixed.php (15KB)
- dashboard-charts-new.php (13KB)
- dashboard-charts-simple.php (13KB)
- dashboard-charts.php (24KB) - v2 version
- dashboard-stats-backup.php (12KB)
- dashboard-stats-fixed.php (12KB)
- dashboard-stats-original-backup.php (548 bytes)
- dashboard-stats.php (12KB) - v2 version

### PO/Orders API Variants
- po-detail.php (12KB) - v2 version
- po-export.php (17KB) - v2 version
- po-list.php (15KB) - v2 version
- po-update.php (15KB) - v2 version

### Helper Files (Duplicates)
- _db_helpers.php (18KB) - Database helpers (duplicate of lib/Database.php)
- _response.php (5.7KB) - Response helpers (duplicate of lib/Utils.php)

### Test Files
- comprehensive-test-suite.php (33KB) - Complete API test suite
- run-tests.php (1KB) - Test runner
- test-connection.php (9.9KB) - Connection test
- test-phase1.php (11KB) - Phase 1 testing
- test-simple.php (1.3KB) - Simple test
- validate-api.php (14KB) - API validation

### Scripts
- fix-charts.sh - Chart fixing script
- .htaccess - Security rules

**Total:** 22 files (~250KB)

## Why api/v2/ Existed

Created during development to:
1. Test new features without breaking production
2. Debug API issues with multiple variants
3. Create test suites for validation
4. Try different approaches (-backup, -fixed, -new versions)

## Production Status

**None of these files are referenced in production code.**

Verified by:
```bash
grep -r "api/v2/" *.php components/*.php assets/js/*.js
# No matches found
```

## To Restore Individual Files

If a specific development file is needed:

```bash
cp archive/api-v2-development-20251030/filename.php api/v2/
```

## Development History

### Multiple Versions Indicate:
- **-backup.php** - Original version before changes
- **-fixed.php** - Bug fix attempt
- **-new.php** - New approach
- **-simple.php** - Simplified version
- **-original-backup.php** - Very first version

### Why Multiple Versions Were Created:
1. Dashboard charts had rendering issues
2. Dashboard stats had performance problems
3. Multiple attempts to fix without breaking production
4. Eventually fixed in main api/ folder files

## Current Production Equivalents

| api/v2/ File | Production File | Status |
|--------------|-----------------|--------|
| dashboard-charts.php | api/dashboard-charts.php | ✅ Working |
| dashboard-stats.php | api/dashboard-stats.php | ✅ Working |
| po-detail.php | api/po-detail.php | ✅ Working |
| po-list.php | api/po-list.php | ✅ Working |
| po-update.php | api/po-update.php | ✅ Working |
| po-export.php | api/export-orders.php | ✅ Working |

## Helper Files Analysis

### _db_helpers.php (18KB)
- Database connection and query helpers
- **Duplicate of:** lib/Database.php
- **Reason for duplication:** Development isolation
- **Status:** Not needed (use lib/Database.php)

### _response.php (5.7KB)
- JSON response formatting helpers
- **Duplicate of:** Functions in lib/Utils.php
- **Reason for duplication:** Development isolation
- **Status:** Not needed (use lib/Utils.php)

## Test Suite Information

### comprehensive-test-suite.php (33KB)
Large test suite covering:
- Dashboard APIs
- Orders/PO APIs
- Warranty APIs
- Authentication
- Error handling

**Could be useful for:** Future regression testing if extracted to tests/ folder

### Other Test Files
- Simple connectivity tests
- Phase-based testing
- API validation

**Status:** Useful for reference but not in active use

## Recommendation

**Keep archived for 90 days** in case any debugging insights are needed. After January 28, 2026, can be safely deleted as all fixes have been merged into production api/ files.

---

**Archived:** October 30, 2025  
**Files:** 22 files (~250KB)  
**Safe to Delete After:** January 28, 2026  
**Restoration:** Only if specific development file needed for reference
