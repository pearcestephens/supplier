# File Organization Instructions

## Quick Commands

```bash
# Make script executable
chmod +x organize-files.sh

# Run the organization script
./organize-files.sh

# Verify results
ls -la _kb/
ls -la archive/2025-10-26_organization/
```

## What This Does

### Creates Structure
- `_kb/` - All knowledge base documentation (35+ MD files)
- `archive/2025-10-26_organization/` - Archived files
  - `demo/` - All demo HTML files
  - `test/` - test-*.php, test-*.sh files
  - `debug/` - session-diagnostic.php, session-debug.php, etc.
  - `docs/` - Old docs folder content

### Moves to _kb/
- QUICK_REFERENCE_CARD.md
- DOCUMENTATION_INDEX.md
- KB_COMPLETION_SUMMARY.md
- KB_INTEGRATION_SUMMARY.md
- COMPLETE_IMPLEMENTATION_GUIDE.md
- STEP_BY_STEP_IMPLEMENTATION.md
- QUICK_START.md
- PHASE_*.md (all phase docs)
- MIGRATION_*.md (all migration docs)
- DEPLOYMENT_STATUS.md
- DASHBOARD_*.md (all dashboard docs)
- SUPPLIER_PORTAL_*.md (all portal docs)
- WIDGET_INVENTORY_VISUAL_GUIDE.md
- ERROR_HANDLING_*.md
- SESSION_*.md
- BUGFIX_*.md
- All docs/kb/ content

### Archives
- `demo/` folder → `archive/2025-10-26_organization/demo/`
- `test-*.php` → `archive/2025-10-26_organization/test/`
- `test-*.sh` → `archive/2025-10-26_organization/test/`
- `session-diagnostic.php` → `archive/2025-10-26_organization/debug/`
- `api/session-debug.php` → `archive/2025-10-26_organization/debug/`
- `api/session-test.php` → `archive/2025-10-26_organization/debug/`
- `EXPERIMENTAL_FILES_REPORT.md` → `archive/2025-10-26_organization/debug/`
- `README_TEST_NOW.md` → `archive/2025-10-26_organization/demo/`

### Creates _kb/README.md
Comprehensive index with:
- Quick start guides
- Navigation structure
- Links to all documents
- Statistics
- Archive location

## After Running

Your workspace will have:
```
/supplier/
├── _kb/                          ← All documentation (clean & organized)
│   ├── README.md                 ← Start here
│   ├── QUICK_REFERENCE_CARD.md
│   ├── DOCUMENTATION_INDEX.md
│   ├── 01-ARCHITECTURE.md
│   ├── 02-DATABASE-SCHEMA.md
│   ├── 03-API-REFERENCE.md
│   ├── 04-AUTHENTICATION.md
│   ├── 05-FRONTEND-PATTERNS.md
│   ├── 06-TESTING-GUIDE.md
│   ├── 07-DEPLOYMENT.md
│   ├── 08-TROUBLESHOOTING.md
│   ├── 09-CODE-SNIPPETS.md
│   └── ... (all other docs)
│
├── archive/                      ← Archived files
│   └── 2025-10-26_organization/
│       ├── demo/
│       ├── test/
│       ├── debug/
│       └── docs/
│
├── api/                          ← Production API (clean)
├── assets/                       ← Production assets
├── components/                   ← Production components
├── lib/                          ← Production libraries
├── tabs/                         ← Production tabs
├── .github/                      ← Copilot instructions
│   └── copilot-instructions.md
│
└── [production files only]
```

## Verify After Running

```bash
# Check _kb/ has all docs
ls -l _kb/ | wc -l
# Should show 35+ files

# Check archive created
ls -la archive/2025-10-26_organization/

# Verify no test files in root
ls -la test-*.php test-*.sh
# Should show "No such file"

# Verify no demo folder in root
ls -la demo/
# Should show "No such file"
```

## Optional: Delete Archive After Backup

```bash
# After confirming everything works:
# 1. Create backup first
tar -czf archive_backup_2025-10-26.tar.gz archive/

# 2. Move backup to safe location
mv archive_backup_2025-10-26.tar.gz ~/backups/

# 3. Delete archive folder (optional)
rm -rf archive/
```

## Rollback (If Needed)

If something goes wrong:
```bash
# Restore from archive
cp -r archive/2025-10-26_organization/demo ./
cp -r archive/2025-10-26_organization/test/* ./
cp -r archive/2025-10-26_organization/debug/* ./

# Or just keep archive folder (recommended)
```

---

**Ready to run!** Execute: `chmod +x organize-files.sh && ./organize-files.sh`
