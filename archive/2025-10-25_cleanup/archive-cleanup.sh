#!/bin/bash
# Archive Cleanup Script - October 25, 2025
# Run this script to complete the archival process

echo "=== Supplier Portal - Archive Cleanup Script ==="
echo "Date: October 25, 2025"
echo ""

# Define base paths
SUPPLIER_ROOT="/home/master/applications/jcepnzzkmj/public_html/supplier"
ARCHIVE_DIR="$SUPPLIER_ROOT/archive/2025-10-25_cleanup"

echo "Creating archive directories..."
mkdir -p "$ARCHIVE_DIR/api-debug"
mkdir -p "$ARCHIVE_DIR/root-debug"
mkdir -p "$ARCHIVE_DIR/test-files"
mkdir -p "$ARCHIVE_DIR/old-documentation"

echo ""
echo "=== Moving API Debug Files ==="
mv -v "$SUPPLIER_ROOT/api/session-debug.php" "$ARCHIVE_DIR/api-debug/" 2>/dev/null && echo "✓ session-debug.php archived" || echo "✗ session-debug.php not found"
mv -v "$SUPPLIER_ROOT/api/session-test.php" "$ARCHIVE_DIR/api-debug/" 2>/dev/null && echo "✓ session-test.php archived" || echo "✗ session-test.php not found"

echo ""
echo "=== Moving Root Debug Files ==="
mv -v "$SUPPLIER_ROOT/session-diagnostic.php" "$ARCHIVE_DIR/root-debug/" 2>/dev/null && echo "✓ session-diagnostic.php archived" || echo "✗ session-diagnostic.php not found"
mv -v "$SUPPLIER_ROOT/test-auth-flow.php" "$ARCHIVE_DIR/root-debug/" 2>/dev/null && echo "✓ test-auth-flow.php archived" || echo "✗ test-auth-flow.php not found"

echo ""
echo "=== Moving Test Files ==="
mv -v "$SUPPLIER_ROOT/tests/comprehensive-page-test.php" "$ARCHIVE_DIR/test-files/" 2>/dev/null && echo "✓ comprehensive-page-test.php archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/tests/quick-session-test.sh" "$ARCHIVE_DIR/test-files/" 2>/dev/null && echo "✓ quick-session-test.sh archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/tests/test-session-fix.sh" "$ARCHIVE_DIR/test-files/" 2>/dev/null && echo "✓ test-session-fix.sh archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/tests/test-session-protocol.sh" "$ARCHIVE_DIR/test-files/" 2>/dev/null && echo "✓ test-session-protocol.sh archived" || echo "✗ not found"

echo ""
echo "=== Moving Old Documentation ==="
mv -v "$SUPPLIER_ROOT/SESSION_FIX_COMPLETE.md" "$ARCHIVE_DIR/old-documentation/" 2>/dev/null && echo "✓ SESSION_FIX_COMPLETE.md archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/SESSION_PROTOCOL_FIX.md" "$ARCHIVE_DIR/old-documentation/" 2>/dev/null && echo "✓ SESSION_PROTOCOL_FIX.md archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/PHASE_3_ACTION_PLAN.md" "$ARCHIVE_DIR/old-documentation/" 2>/dev/null && echo "✓ PHASE_3_ACTION_PLAN.md archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/PHASE_3_COMPLETE.md" "$ARCHIVE_DIR/old-documentation/" 2>/dev/null && echo "✓ PHASE_3_COMPLETE.md archived" || echo "✗ not found"
mv -v "$SUPPLIER_ROOT/UPGRADE_COMPLETE_PHASES_1_2.md" "$ARCHIVE_DIR/old-documentation/" 2>/dev/null && echo "✓ UPGRADE_COMPLETE_PHASES_1_2.md archived" || echo "✗ not found"

echo ""
echo "=== Keeping Active Files ==="
echo "✓ test-errors.php - Active error testing suite"
echo "✓ ERROR_HANDLING_COMPLETE.md - Current documentation"
echo "✓ ERROR_HANDLING_SYSTEM.md - Active reference"
echo "✓ DEPLOYMENT_STATUS.md - Current tracking"
echo "✓ SUPPLIER_PORTAL_FEATURE_BLUEPRINT.md - Future planning"
echo "✓ SUPPLIER_PORTAL_DATA_ANALYSIS.md - Database reference"
echo "✓ /demo/ directory - UI prototypes"
echo "✓ /tests/APIEndpointTest.php - Unit tests for CI/CD"
echo "✓ /tests/DashboardAPITest.php - Unit tests for CI/CD"
echo "✓ /tests/DatabaseTest.php - Unit tests for CI/CD"
echo "✓ /tests/LibraryClassesTest.php - Unit tests for CI/CD"

echo ""
echo "=== Creating Archive Completion Report ==="
cat > "$ARCHIVE_DIR/ARCHIVE_COMPLETE.txt" << 'EOF'
Archive Cleanup Completed
Date: October 25, 2025
Time: $(date '+%Y-%m-%d %H:%M:%S')

Files Archived:
- API debug files (2 files)
- Root debug files (2 files)
- Test shell scripts (4 files)
- Old documentation (5 files)

Total: 13 files archived

Reason: Bootstrap error handling system supersedes individual debug scripts.
All functionality preserved in new error-handler.js and bootstrap.php.

See ARCHIVE_MANIFEST.md for complete details and restoration instructions.
EOF

echo ""
echo "=== Archive Cleanup Complete ==="
echo "Location: $ARCHIVE_DIR"
echo "Files archived: Check ARCHIVE_COMPLETE.txt for summary"
echo "Manifest: See ARCHIVE_MANIFEST.md for details"
echo ""
echo "Next Steps:"
echo "1. Test error handling: https://staff.vapeshed.co.nz/supplier/test-errors.php"
echo "2. Resume Phase 4: Frontend JS migration (~3 hours)"
echo ""
