# 📚 Supplier Portal Knowledge Base

## Purpose
Centralized knowledge repository for AI coding agents and developers working on The Vape Shed Supplier Portal.

## Structure

```
docs/kb/
├── README.md                          # This file - KB overview
├── 01-ARCHITECTURE.md                 # System architecture & design patterns
├── 02-DATABASE-SCHEMA.md              # Complete database schema & relationships
├── 03-API-REFERENCE.md                # API endpoints & handler methods
├── 04-AUTHENTICATION.md               # Auth flow & session management
├── 05-FRONTEND-PATTERNS.md            # UI components & JavaScript patterns
├── 06-TESTING-GUIDE.md                # Testing commands & procedures
├── 07-DEPLOYMENT.md                   # Deployment procedures & checklist
├── 08-TROUBLESHOOTING.md              # Common issues & solutions
└── 09-CODE-SNIPPETS.md                # Reusable code patterns
```

## Quick Reference

### Essential Files
- **Bootstrap:** `/bootstrap.php` - Always require this first
- **Config:** `/config.php` - All constants & settings
- **Auth:** `/lib/Auth.php` - Authentication methods
- **Session:** `/lib/Session.php` - Session management
- **Database:** `/lib/DatabasePDO.php` - PDO wrapper (preferred)

### Key Directories
- `/api/handlers/` - API business logic
- `/tabs/` - Page templates
- `/demo/` - UI reference designs
- `/components/` - Reusable UI components
- `/tests/` - Test suites
- `/docs/` - Documentation

### Quick Commands
```bash
# Test syntax
php -l <file.php>

# Run tests
php tests/comprehensive-api-test.php

# Check logs
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# Database check
php -r "require 'bootstrap.php'; var_dump(pdo()->query('SELECT 1')->fetchColumn());"
```

## Knowledge Base Usage

### For AI Agents
1. **Starting new task:** Read relevant KB sections first
2. **Code patterns:** Check `09-CODE-SNIPPETS.md` for templates
3. **Database queries:** Reference `02-DATABASE-SCHEMA.md` for exact table/column names
4. **API changes:** Follow patterns in `03-API-REFERENCE.md`
5. **Troubleshooting:** Check `08-TROUBLESHOOTING.md` for known issues

### For Developers
- KB supplements inline code documentation
- All KB files are markdown for easy reading/editing
- Update KB when architectural changes are made
- Link to KB sections in code comments when appropriate

## Maintenance
- **Update frequency:** After major changes or new patterns
- **Owner:** Lead developer / System architect
- **Version control:** Committed with code
- **Review:** Monthly architecture review

## Related Documentation
- Main: `../COMPLETE_IMPLEMENTATION_GUIDE.md`
- Database: `../DATABASE_MASTER_REFERENCE.md`
- Auth: `../AUTHENTICATION_FLOW.md`
- Migration: `../../DEMO_TO_PRODUCTION_MIGRATION_PLAN.md`

---
**Last Updated:** October 26, 2025
**KB Version:** 1.0.0
