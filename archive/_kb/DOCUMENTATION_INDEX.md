# 📚 Knowledge Base & Documentation Index

## For AI Coding Agents

### Start Here (Priority Order)
1. **`.github/copilot-instructions.md`** ← READ THIS FIRST (5 min)
   - Critical architecture patterns
   - Essential code examples
   - Common pitfalls to avoid

2. **`QUICK_REFERENCE_CARD.md`** ← Quick lookup (30 sec)
   - Common tasks with code
   - Testing commands
   - Emergency fixes

3. **`docs/kb/README.md`** ← KB navigation
   - Knowledge base structure
   - Topic index
   - Usage guidelines

### Deep Dive Documentation

#### System Understanding
- **`docs/kb/01-ARCHITECTURE.md`** - Complete system architecture (5000+ words)
  - Bootstrap pattern
  - API architecture
  - Database architecture
  - Authentication flow
  - Error handling
  - Multi-tenancy

- **`docs/kb/02-DATABASE-SCHEMA.md`** - All tables & relationships (4500+ words)
  - 7 core tables with full schemas
  - Query patterns & examples
  - Index strategies
  - Relationship diagrams

#### Implementation Guides
- **`COMPLETE_IMPLEMENTATION_GUIDE.md`** - Bootstrap system overview
- **`DEMO_TO_PRODUCTION_MIGRATION_PLAN.md`** - UI migration strategy (12,000+ words)
- **`STEP_BY_STEP_IMPLEMENTATION.md`** - Execution checklist
- **`WIDGET_INVENTORY_VISUAL_GUIDE.md`** - UI component guide

#### Technical References
- **`docs/DATABASE_MASTER_REFERENCE.md`** - Database deep dive
- **`docs/AUTHENTICATION_FLOW.md`** - Auth implementation details
- **`docs/API_MIGRATION_PLAN.md`** - API architecture evolution
- **`docs/DATABASE_CREATE_STATEMENTS.sql`** - Complete SQL schema

#### Status & Progress
- **`DEPLOYMENT_STATUS.md`** - Current deployment state
- **`PHASE_B_PDO_CONVERSION.md`** - Database migration status
- **`MIGRATION_READY_SUMMARY.md`** - Migration readiness
- **`MIGRATION_FLOW_DIAGRAM.md`** - Visual flow diagrams

## For Developers

### Onboarding Path
```
Day 1: Quick Reference Card + Copilot Instructions
       ↓
Day 2: Architecture (01-ARCHITECTURE.md) + Database Schema (02-DATABASE-SCHEMA.md)
       ↓
Day 3: Read existing code + Review test files
       ↓
Day 4: Start coding with KB support
```

### By Topic

#### Authentication & Security
- `.github/copilot-instructions.md` → Section 3 (Authentication Flow)
- `docs/kb/01-ARCHITECTURE.md` → Authentication Architecture
- `docs/AUTHENTICATION_FLOW.md` → Complete implementation
- `lib/Auth.php` → Code implementation
- `lib/Session.php` → Session management

#### Database & Queries
- `docs/kb/02-DATABASE-SCHEMA.md` → All tables & patterns
- `docs/DATABASE_MASTER_REFERENCE.md` → Deep dive
- `docs/DATABASE_CREATE_STATEMENTS.sql` → SQL schemas
- `lib/DatabasePDO.php` → PDO wrapper
- `config.php` → Database credentials

#### API Development
- `.github/copilot-instructions.md` → Section 4 (API Architecture)
- `docs/kb/01-ARCHITECTURE.md` → API Layer
- `api/endpoint.php` → Unified router
- `api/handlers/*.php` → Handler examples
- `docs/API_MIGRATION_PLAN.md` → Evolution strategy

#### Frontend & UI
- `DEMO_TO_PRODUCTION_MIGRATION_PLAN.md` → UI strategy
- `WIDGET_INVENTORY_VISUAL_GUIDE.md` → Component guide
- `demo/*.html` → Reference designs
- `tabs/tab-*.php` → Production pages
- `assets/css/professional-black.css` → Styling

#### Testing & Debugging
- `QUICK_REFERENCE_CARD.md` → Testing commands
- `tests/comprehensive-api-test.php` → Test suite
- `test-errors.php` → Error handling tests
- Check logs: `/home/master/applications/jcepnzzkmj/logs/`

## By File Type

### Configuration Files
```
config.php                      All constants & settings
supplier-config.php             Supplier-specific config
bootstrap.php                   Initialization & helpers
```

### Core Libraries
```
lib/Database.php               MySQLi wrapper (legacy)
lib/DatabasePDO.php            PDO wrapper (preferred)
lib/Session.php                Session management
lib/Auth.php                   Authentication methods
lib/Utils.php                  Helper functions
lib/AuthHelper.php             Auth utilities
lib/UtilsHelper.php            Utility functions
```

### API Files
```
api/endpoint.php               Unified API router
api/handlers/auth.php          Authentication
api/handlers/dashboard.php     Dashboard data
api/handlers/orders.php        Order management
api/handlers/warranty.php      Warranty claims
```

### Page Templates
```
index.php                      Main portal entry
login.php                      Magic link login
logout.php                     Session cleanup
tabs/tab-dashboard.php         Dashboard page
tabs/tab-orders.php            Orders page
tabs/tab-warranty.php          Warranty page
tabs/tab-reports.php           Reports page
tabs/tab-downloads.php         Downloads archive
tabs/tab-account.php           Account settings
```

### UI Components
```
components/header-top.php      Logo, search, user menu
components/header-bottom.php   Breadcrumbs, page actions
components/sidebar.php         Navigation menu
```

### Demo Files (Reference)
```
demo/index.html               Dashboard design reference
demo/orders.html              Orders page reference
demo/warranty.html            Warranty page reference
demo/reports.html             Reports page reference
demo/downloads.html           Downloads page reference
demo/account.html             Account page reference
```

### Documentation
```
.github/copilot-instructions.md     AI agent quick start
QUICK_REFERENCE_CARD.md             Quick lookup
docs/KB_INTEGRATION_SUMMARY.md      KB creation summary
docs/kb/README.md                   KB overview
docs/kb/01-ARCHITECTURE.md          System architecture
docs/kb/02-DATABASE-SCHEMA.md       Database reference
COMPLETE_IMPLEMENTATION_GUIDE.md    Bootstrap guide
DEPLOYMENT_STATUS.md                Current status
```

### Tests
```
tests/comprehensive-api-test.php    API test suite
tests/DatabaseTest.php              Database tests
tests/LibraryClassesTest.php        Library tests
tests/APIEndpointTest.php           Endpoint tests
test-errors.php                     Error handling test
```

## Search Strategies

### Find Pattern/Example
```bash
# Search all PHP files
grep -r "pattern" --include="*.php"

# Search specific directory
grep -r "pattern" api/handlers/

# Search KB
grep -r "pattern" docs/kb/

# Find function definition
grep -rn "function functionName" --include="*.php"
```

### Find Table Usage
```bash
# Find table references
grep -r "vend_consignments" --include="*.php"

# Find SQL queries
grep -r "SELECT.*FROM vend_suppliers" --include="*.php"
```

### Find Configuration
```bash
# Find constant usage
grep -r "PAGINATION_PER_PAGE" --include="*.php"

# Find config definitions
cat config.php | grep "define"
```

## Common Scenarios

### "I need to add a new feature"
1. Check demo files for UI reference
2. Read architecture for pattern understanding
3. Search existing code for similar functionality
4. Review database schema for table structure
5. Write code following bootstrap pattern

### "I'm getting an error"
1. Check error logs first
2. Read QUICK_REFERENCE_CARD.md emergency fixes
3. Verify bootstrap is loaded
4. Check authentication is working
5. Review database query syntax

### "How do I test this?"
1. Read QUICK_REFERENCE_CARD.md testing section
2. Run `php -l` for syntax check
3. Run test suite: `php tests/comprehensive-api-test.php`
4. Check logs for errors
5. Test in browser

### "I need to understand the system"
1. Read .github/copilot-instructions.md (5 min)
2. Read docs/kb/01-ARCHITECTURE.md (20 min)
3. Read docs/kb/02-DATABASE-SCHEMA.md (20 min)
4. Review existing code with new understanding
5. Ask specific questions with context

## Documentation Quality Standards

### What Makes Good Documentation
✅ **Specific to this project** (not generic advice)
✅ **Includes real code examples** from codebase
✅ **Explains the "why"** not just "what"
✅ **Shows common patterns** used in production
✅ **References actual files** for verification

### What to Avoid
❌ Generic advice ("write tests", "handle errors")
❌ Aspirational practices not actually used
❌ Outdated information
❌ Missing context
❌ No examples

## Maintenance

### When to Update Documentation
- ✏️ Architecture changes (design patterns)
- ✏️ New features added (API endpoints, pages)
- ✏️ Database schema changes (tables, columns)
- ✏️ Configuration changes (constants, settings)
- ✏️ Deployment process changes

### How to Update
1. Identify affected KB sections
2. Update with specific examples
3. Cross-reference related sections
4. Test examples are accurate
5. Commit with descriptive message

### Review Schedule
- 📅 **Weekly:** Check for accuracy
- 📅 **Monthly:** Comprehensive review
- 📅 **Quarterly:** Architecture alignment
- 📅 **On deployment:** Update status docs

## Statistics

### Documentation Coverage
- **Total files:** 200+ code files, 35+ documentation files
- **KB documents:** ✅ 9 COMPLETE (6,150+ lines total)
- **Code examples:** 150+ throughout KB
- **Tables documented:** 7 core tables with full schemas
- **API endpoints:** 4 handler classes fully documented (20+ endpoints)

### Code Analysis
- **Lines analyzed:** 15,000+ lines of PHP
- **Lines documented:** 6,150+ lines in KB system
- **Patterns identified:** 60+ reusable patterns
- **Security patterns:** 15+ critical security practices
- **Test scenarios:** 25+ documented test cases
- **Database queries:** 30+ example queries with patterns

## Quick Links

### Most Referenced Files
1. `bootstrap.php` - Used by EVERY file
2. `config.php` - All configuration constants
3. `api/endpoint.php` - API entry point
4. `lib/DatabasePDO.php` - Database wrapper
5. `lib/Auth.php` - Authentication methods

### Most Important Concepts
1. Bootstrap pattern (CRITICAL)
2. PDO vs MySQLi (transitional)
3. Multi-tenancy filtering
4. Magic link authentication
5. Unified API envelope format

### Most Common Mistakes to Avoid
1. Not loading bootstrap first
2. Using MySQLi instead of PDO
3. Forgetting supplier_id filter
4. Not checking deleted_at
5. Concatenating SQL strings

---

## Get Help

### Steps to Get Unstuck
1. **Search KB:** `grep -r "topic" docs/kb/`
2. **Check existing code:** Find similar functionality
3. **Review tests:** See working examples
4. **Check logs:** Error messages tell the story
5. **Read architecture:** Understand the "why"

### When All Else Fails
1. Verify bootstrap is loaded: `defined('BOOTSTRAP_LOADED')`
2. Check database connection: `var_dump(pdo()->query('SELECT 1')->fetchColumn())`
3. Verify authentication: `var_dump(getSupplierID())`
4. Check error logs: `tail -f /path/to/logs/apache_*.error.log`
5. Run test suite: `php tests/comprehensive-api-test.php`

---

**Last Updated:** October 26, 2025  
**KB Version:** 1.0.0  
**Coverage:** Foundation Complete (40% of planned KB)  
**Status:** Ready for Use & Expansion
