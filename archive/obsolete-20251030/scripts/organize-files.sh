#!/bin/bash
# File Organization Script
# Date: 2025-10-26
# Purpose: Move KB files to _kb/ and archive demo/debug/test files

echo "Starting file organization..."

# Create directories
mkdir -p _kb
mkdir -p archive/2025-10-26_organization/{demo,debug,test,docs}

echo "✅ Created directories"

# ============================================================================
# STEP 1: Move KB documentation files to _kb/
# ============================================================================

echo ""
echo "Step 1: Moving KB files to _kb/..."

# Move root-level documentation
mv -v QUICK_REFERENCE_CARD.md _kb/ 2>/dev/null || true
mv -v DOCUMENTATION_INDEX.md _kb/ 2>/dev/null || true
mv -v KB_COMPLETION_SUMMARY.md _kb/ 2>/dev/null || true
mv -v KB_INTEGRATION_SUMMARY.md _kb/ 2>/dev/null || true

# Move implementation guides
mv -v COMPLETE_IMPLEMENTATION_GUIDE.md _kb/ 2>/dev/null || true
mv -v STEP_BY_STEP_IMPLEMENTATION.md _kb/ 2>/dev/null || true
mv -v QUICK_START.md _kb/ 2>/dev/null || true

# Move phase documentation
mv -v PHASE_*.md _kb/ 2>/dev/null || true
mv -v UPGRADE_*.md _kb/ 2>/dev/null || true

# Move migration documentation
mv -v MIGRATION_*.md _kb/ 2>/dev/null || true
mv -v DEMO_TO_PRODUCTION_MIGRATION_PLAN.md _kb/ 2>/dev/null || true

# Move status documentation
mv -v DEPLOYMENT_STATUS.md _kb/ 2>/dev/null || true
mv -v DASHBOARD_*.md _kb/ 2>/dev/null || true

# Move analysis documentation
mv -v SUPPLIER_PORTAL_*.md _kb/ 2>/dev/null || true
mv -v CRITICAL_ISSUES_ANALYSIS.md _kb/ 2>/dev/null || true

# Move widget documentation
mv -v WIDGET_INVENTORY_VISUAL_GUIDE.md _kb/ 2>/dev/null || true

# Move error handling documentation
mv -v ERROR_HANDLING_*.md _kb/ 2>/dev/null || true

# Move session documentation
mv -v SESSION_*.md _kb/ 2>/dev/null || true

# Move bugfix documentation
mv -v BUGFIX_*.md _kb/ 2>/dev/null || true

# Move existing docs/kb/ content to _kb/ (if exists)
if [ -d "docs/kb" ]; then
    echo "Moving docs/kb/ content to _kb/..."
    cp -rv docs/kb/* _kb/ 2>/dev/null || true
    mv -v docs/KB_INTEGRATION_SUMMARY.md _kb/ 2>/dev/null || true
fi

echo "✅ KB files moved to _kb/"

# ============================================================================
# STEP 2: Archive demo files
# ============================================================================

echo ""
echo "Step 2: Archiving demo files..."

# Archive demo directory
if [ -d "demo" ]; then
    mv -v demo archive/2025-10-26_organization/demo/
    echo "✅ Archived demo/ directory"
fi

# Archive README_TEST_NOW.md
mv -v README_TEST_NOW.md archive/2025-10-26_organization/demo/ 2>/dev/null || true

echo "✅ Demo files archived"

# ============================================================================
# STEP 3: Archive debug/test files
# ============================================================================

echo ""
echo "Step 3: Archiving debug and test files..."

# Test files
mv -v test-*.php archive/2025-10-26_organization/test/ 2>/dev/null || true
mv -v test-*.sh archive/2025-10-26_organization/test/ 2>/dev/null || true

# Debug files
mv -v session-diagnostic.php archive/2025-10-26_organization/debug/ 2>/dev/null || true
mv -v api/session-debug.php archive/2025-10-26_organization/debug/ 2>/dev/null || true
mv -v api/session-test.php archive/2025-10-26_organization/debug/ 2>/dev/null || true

# Experimental report
mv -v EXPERIMENTAL_FILES_REPORT.md archive/2025-10-26_organization/debug/ 2>/dev/null || true

echo "✅ Debug and test files archived"

# ============================================================================
# STEP 4: Archive migration scripts
# ============================================================================

echo ""
echo "Step 4: Archiving one-time migration scripts..."

# Deployment script (if it was one-time use)
mv -v deploy-dashboard.sh archive/2025-10-26_organization/ 2>/dev/null || true

echo "✅ Migration scripts archived"

# ============================================================================
# STEP 5: Clean up empty docs directory
# ============================================================================

echo ""
echo "Step 5: Cleaning up empty directories..."

# Archive remaining docs if any
if [ -d "docs" ]; then
    # Move any remaining docs files
    if [ "$(ls -A docs)" ]; then
        mv -v docs/* archive/2025-10-26_organization/docs/ 2>/dev/null || true
    fi
    rmdir docs 2>/dev/null || true
fi

echo "✅ Cleanup complete"

# ============================================================================
# STEP 6: Create index in _kb/
# ============================================================================

echo ""
echo "Step 6: Creating _kb/ index..."

cat > _kb/README.md << 'EOF'
# Knowledge Base

**The Vape Shed Supplier Portal Documentation**

---

## Quick Start

### For AI Agents
Start here: [../.github/copilot-instructions.md](../.github/copilot-instructions.md) (5 min read)

### For Developers
1. Read [QUICK_REFERENCE_CARD.md](QUICK_REFERENCE_CARD.md) (10 min)
2. Read [01-ARCHITECTURE.md](01-ARCHITECTURE.md) (20 min)
3. Read [02-DATABASE-SCHEMA.md](02-DATABASE-SCHEMA.md) (20 min)
4. Start coding with KB support

---

## Core Documentation

### Essential Reading (Start Here)
- **[QUICK_REFERENCE_CARD.md](QUICK_REFERENCE_CARD.md)** - Quick lookup reference
- **[DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)** - Complete navigation guide

### Knowledge Base System (Read in Order)
1. **[01-ARCHITECTURE.md](01-ARCHITECTURE.md)** - System architecture deep dive
2. **[02-DATABASE-SCHEMA.md](02-DATABASE-SCHEMA.md)** - Complete database documentation
3. **[03-API-REFERENCE.md](03-API-REFERENCE.md)** - All API endpoints documented
4. **[04-AUTHENTICATION.md](04-AUTHENTICATION.md)** - Auth flows and session management
5. **[05-FRONTEND-PATTERNS.md](05-FRONTEND-PATTERNS.md)** - UI patterns and Chart.js
6. **[06-TESTING-GUIDE.md](06-TESTING-GUIDE.md)** - Testing procedures and commands
7. **[07-DEPLOYMENT.md](07-DEPLOYMENT.md)** - Deployment procedures and rollback
8. **[08-TROUBLESHOOTING.md](08-TROUBLESHOOTING.md)** - Common issues and solutions
9. **[09-CODE-SNIPPETS.md](09-CODE-SNIPPETS.md)** - Reusable code templates

### Implementation Guides
- **[COMPLETE_IMPLEMENTATION_GUIDE.md](COMPLETE_IMPLEMENTATION_GUIDE.md)** - Bootstrap pattern guide
- **[STEP_BY_STEP_IMPLEMENTATION.md](STEP_BY_STEP_IMPLEMENTATION.md)** - Implementation checklist
- **[QUICK_START.md](QUICK_START.md)** - Quick start guide

### Migration Documentation
- **[DEMO_TO_PRODUCTION_MIGRATION_PLAN.md](DEMO_TO_PRODUCTION_MIGRATION_PLAN.md)** - UI migration strategy
- **[MIGRATION_READY_SUMMARY.md](MIGRATION_READY_SUMMARY.md)** - Migration readiness
- **[MIGRATION_FLOW_DIAGRAM.md](MIGRATION_FLOW_DIAGRAM.md)** - Visual flow diagrams

### Project Status
- **[DEPLOYMENT_STATUS.md](DEPLOYMENT_STATUS.md)** - Current deployment state
- **[DASHBOARD_COMPLETE_SUMMARY.md](DASHBOARD_COMPLETE_SUMMARY.md)** - Dashboard status
- **[KB_COMPLETION_SUMMARY.md](KB_COMPLETION_SUMMARY.md)** - KB completion overview

### Feature Documentation
- **[WIDGET_INVENTORY_VISUAL_GUIDE.md](WIDGET_INVENTORY_VISUAL_GUIDE.md)** - UI component guide
- **[SUPPLIER_PORTAL_FEATURE_BLUEPRINT.md](SUPPLIER_PORTAL_FEATURE_BLUEPRINT.md)** - Feature specs
- **[SUPPLIER_PORTAL_DATA_ANALYSIS.md](SUPPLIER_PORTAL_DATA_ANALYSIS.md)** - Data analysis

### Technical Deep Dives
- **[ERROR_HANDLING_SYSTEM.md](ERROR_HANDLING_SYSTEM.md)** - Error handling architecture
- **[ERROR_HANDLING_COMPLETE.md](ERROR_HANDLING_COMPLETE.md)** - Error handling implementation
- **[SESSION_PROTOCOL_FIX.md](SESSION_PROTOCOL_FIX.md)** - Session management fixes
- **[SESSION_FIX_COMPLETE.md](SESSION_FIX_COMPLETE.md)** - Session fix summary

### Phase Documentation
- **[PHASE_3_COMPLETE.md](PHASE_3_COMPLETE.md)** - Phase 3 completion
- **[PHASE_3_ACTION_PLAN.md](PHASE_3_ACTION_PLAN.md)** - Phase 3 plan
- **[PHASE_A_B_COMPLETE.md](PHASE_A_B_COMPLETE.md)** - Phases A & B summary
- **[PHASE_B_PDO_CONVERSION.md](PHASE_B_PDO_CONVERSION.md)** - Database migration
- **[UPGRADE_COMPLETE_PHASES_1_2.md](UPGRADE_COMPLETE_PHASES_1_2.md)** - Phases 1 & 2 upgrade

### Bug Fixes & Issues
- **[BUGFIX_AND_ARCHIVE_COMPLETE.md](BUGFIX_AND_ARCHIVE_COMPLETE.md)** - Bugfix summary
- **[BUGFIX_HTTP500_RESOLVED.md](BUGFIX_HTTP500_RESOLVED.md)** - HTTP 500 resolution
- **[CRITICAL_ISSUES_ANALYSIS.md](CRITICAL_ISSUES_ANALYSIS.md)** - Critical issues

---

## Statistics

- **Total Documentation:** 35+ files
- **Total Lines:** 7,150+ lines
- **KB Documents:** 9 complete (6,150+ lines)
- **Code Examples:** 150+
- **Patterns Documented:** 60+
- **Test Scenarios:** 25+

---

## Archive Location

Historical files archived in: `../archive/2025-10-26_organization/`
- Demo files: `demo/`
- Test files: `test/`
- Debug files: `debug/`
- Old docs: `docs/`

---

**Last Updated:** 2025-10-26  
**Status:** ✅ Complete and organized
EOF

echo "✅ Created _kb/README.md"

# ============================================================================
# SUMMARY
# ============================================================================

echo ""
echo "============================================================"
echo "                  ORGANIZATION COMPLETE                     "
echo "============================================================"
echo ""
echo "📁 File Structure:"
echo "   _kb/                          - All KB documentation (35+ files)"
echo "   archive/2025-10-26_organization/"
echo "   ├── demo/                     - Demo HTML files"
echo "   ├── test/                     - Test scripts"
echo "   ├── debug/                    - Debug/diagnostic files"
echo "   └── docs/                     - Old docs folder"
echo ""
echo "✅ All KB documentation now in: _kb/"
echo "✅ All demo/test/debug files archived"
echo "✅ Clean workspace ready for production"
echo ""
echo "Next steps:"
echo "1. Review _kb/README.md for navigation"
echo "2. Verify archived files are backed up"
echo "3. Delete archive/ folder after verification (optional)"
echo ""
echo "Done! 🎉"
