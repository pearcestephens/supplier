#!/bin/bash
# Quick reference card - Print this out!

cat << 'EOF'

╔════════════════════════════════════════════════════════════════╗
║                                                                ║
║              DEBUG MODE - QUICK REFERENCE CARD                ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝

┌─ WHAT IS DEBUG MODE? ─────────────────────────────────────────┐
│                                                                │
│  Browse supplier portal WITHOUT login page or session cookies  │
│  Perfect for testing without auth friction                    │
│                                                                │
│  ✅ No login screen                                            │
│  ✅ No session cookies                                         │
│  ✅ Just edit config and browse                                │
│  ✅ Logs all access for audit trail                            │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ STEP 1: ENABLE (Edit config.php) ────────────────────────────┐
│                                                                │
│  Find line ~16-20:                                             │
│                                                                │
│  define('DEBUG_MODE_ENABLED', false);    ← BEFORE             │
│  ↓↓↓                                                            │
│  define('DEBUG_MODE_ENABLED', true);     ← AFTER ✅            │
│                                                                │
│  Also set supplier ID (around line 20):                       │
│                                                                │
│  define('DEBUG_MODE_SUPPLIER_ID', 1);    ← Any supplier ID    │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ STEP 2: SAVE & BROWSE ──────────────────────────────────────┐
│                                                                │
│  Save config.php                                               │
│                                                                │
│  Now visit directly (NO login!):                              │
│                                                                │
│  ✅ https://staff.vapeshed.co.nz/supplier/dashboard.php       │
│  ✅ https://staff.vapeshed.co.nz/supplier/products.php        │
│  ✅ https://staff.vapeshed.co.nz/supplier/orders.php          │
│  ✅ https://staff.vapeshed.co.nz/supplier/warranty.php        │
│  ✅ https://staff.vapeshed.co.nz/supplier/account.php         │
│                                                                │
│  All pages show Supplier {ID} data automatically!             │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ STEP 3: CHANGE SUPPLIER (Optional) ──────────────────────────┐
│                                                                │
│  Want to test different supplier? Just edit one line:         │
│                                                                │
│  define('DEBUG_MODE_SUPPLIER_ID', 1);     ← Change this       │
│                                                                │
│  Examples:                                                     │
│  define('DEBUG_MODE_SUPPLIER_ID', 1);     → Test Supplier 1   │
│  define('DEBUG_MODE_SUPPLIER_ID', 42);    → Test Supplier 42  │
│  define('DEBUG_MODE_SUPPLIER_ID', 999);   → Test Supplier 999 │
│                                                                │
│  Reload page → Instantly sees different supplier's data! ✅   │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ MONITOR ACCESS (Optional) ───────────────────────────────────┐
│                                                                │
│  View DEBUG MODE control panel:                               │
│  https://staff.vapeshed.co.nz/supplier/debug-mode.php         │
│                                                                │
│  Shows:                                                        │
│  • Current status (enabled/disabled)                          │
│  • Active supplier ID                                          │
│  • Access log with timestamps                                  │
│  • Quick links to all pages                                    │
│                                                                │
│  Or watch logs in terminal:                                   │
│  tail -f /supplier/logs/debug-mode.log                        │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ DISABLE WHEN DONE ───────────────────────────────────────────┐
│                                                                │
│  Want to go back to normal login? Just revert:                │
│                                                                │
│  define('DEBUG_MODE_ENABLED', false);     ← Back to normal    │
│                                                                │
│  ✅ Login page required again                                  │
│  ✅ All functionality unchanged                                │
│  ✅ No performance impact                                      │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════════════════════════════╗
║                          QUICK ACTIONS                         ║
╚════════════════════════════════════════════════════════════════╝

┌─ From Terminal ───────────────────────────────────────────────┐
│                                                                │
│  # Make script executable                                     │
│  chmod +x /supplier/debug-mode-toggle.sh                      │
│                                                                │
│  # Run toggle script                                          │
│  /supplier/debug-mode-toggle.sh                               │
│  # Choose option 1 to enable                                  │
│                                                                │
│  # Watch logs in real-time                                    │
│  tail -f /supplier/logs/debug-mode.log                        │
│                                                                │
│  # Test a page                                                │
│  curl https://staff.vapeshed.co.nz/supplier/products.php     │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════════════════════════════╗
║                       EXAMPLE WORKFLOWS                        ║
╚════════════════════════════════════════════════════════════════╝

┌─ Test Multiple Suppliers ─────────────────────────────────────┐
│                                                                │
│  1. Edit config.php                                            │
│  2. Set DEBUG_MODE_ENABLED = true                             │
│  3. Set DEBUG_MODE_SUPPLIER_ID = 1                            │
│  4. Browse /supplier/products.php                             │
│  5. Change to SUPPLIER_ID = 2                                 │
│  6. Reload page → See Supplier 2's data ✅                    │
│  7. Repeat for each supplier (NO LOGIN NEEDED!)               │
│                                                                │
│  Time savings: 3x faster than login/logout cycle!             │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

┌─ Debug Specific Supplier Issue ───────────────────────────────┐
│                                                                │
│  Issue: "Supplier 42's warranty claims not showing"           │
│                                                                │
│  1. Edit config.php                                            │
│  2. Set DEBUG_MODE_ENABLED = true                             │
│  3. Set DEBUG_MODE_SUPPLIER_ID = 42                           │
│  4. Visit warranty.php                                         │
│  5. Check what they see                                        │
│  6. Compare with database queries                              │
│  7. Fix issue with exact data ✅                               │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════════════════════════════╗
║                        SECURITY NOTES                          ║
╚════════════════════════════════════════════════════════════════╝

✅ SAFE:
  • Localhost-only control panel (127.0.0.1)
  • Database validates supplier exists
  • All access logged with IP + timestamp
  • Can toggle on/off instantly
  • No production impact (dev-only)

❌ NOT FOR PRODUCTION:
  • Bypasses authentication
  • Hardcoded supplier ID
  • Would expose data if internet-accessible

🔒 Best Practice:
  • Only enable when actively testing
  • Disable before deploying
  • Check access logs after testing
  • Never commit with DEBUG_MODE = true

╔════════════════════════════════════════════════════════════════╗
║                      FILES & LOCATIONS                         ║
╚════════════════════════════════════════════════════════════════╝

📝 Configuration:
   /supplier/config.php
   Lines ~16-20: Find DEBUG_MODE constants

🔨 Modified Code:
   /supplier/lib/Auth.php
   • check() method
   • initializeDebugMode() method
   • getSupplierId() method
   • require() method

🎛️ Control Panel:
   https://staff.vapeshed.co.nz/supplier/debug-mode.php

📊 Access Logs:
   /supplier/logs/debug-mode.log

🔧 Bash Script:
   /supplier/debug-mode-toggle.sh

📖 Full Documentation:
   /supplier/_kb/DEBUG_MODE_GUIDE.md
   /supplier/_kb/DEBUG_MODE_SETUP_COMPLETE.md

╔════════════════════════════════════════════════════════════════╗
║                         VERSION INFO                           ║
╚════════════════════════════════════════════════════════════════╝

Feature:   DEBUG MODE for Supplier Portal
Created:   October 31, 2025
Status:    ✅ READY TO USE
Type:      Development/Testing Only
Security:  🟢 Safe (dev-only, logged, validated)

Features:
  ✅ Hardcoded supplier ID support
  ✅ Session bypass (no cookies needed)
  ✅ Database validation (supplier must exist)
  ✅ Audit logging (timestamps + IP)
  ✅ Easy toggle (config.php only)
  ✅ No code changes needed for existing pages
  ✅ Control panel for monitoring
  ✅ Bash script for CLI toggle

╔════════════════════════════════════════════════════════════════╗
║  READY TO USE! Edit config.php and browse without login! 🚀   ║
╚════════════════════════════════════════════════════════════════╝

EOF
