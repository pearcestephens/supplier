#!/bin/bash
# Complete Dashboard Test Suite Runner
# Tests APIs → Activates Dashboard → Provides verification steps

echo "=========================================="
echo "🚀 DASHBOARD MIGRATION - COMPLETE TEST"
echo "=========================================="
echo ""

# Step 1: API Tests
echo "📋 STEP 1: Testing Dashboard APIs..."
echo "------------------------------------------"
php test-dashboard-api.php
api_result=$?

echo ""
if [ $api_result -eq 0 ]; then
    echo "✅ API Tests: ALL PASSED"
else
    echo "❌ API Tests: FAILED"
    echo ""
    echo "Fix API errors before continuing."
    echo "Check logs: tail -100 logs/apache*.error.log"
    exit 1
fi

echo ""
echo "=========================================="
echo "📦 STEP 2: Dashboard Activation"
echo "=========================================="
echo ""

# Check if new dashboard file exists
if [ ! -f "tabs/tab-dashboard-v4-demo-perfect.php" ]; then
    echo "❌ Error: tab-dashboard-v4-demo-perfect.php not found"
    exit 1
fi

echo "Current dashboard file status:"
ls -lh tabs/tab-dashboard*.php 2>/dev/null || echo "No dashboard files found"
echo ""

# Ask user to proceed
echo "Ready to activate new dashboard?"
echo "This will:"
echo "  1. Backup existing: tab-dashboard.php → tab-dashboard-v3-backup.php"
echo "  2. Activate new: tab-dashboard-v4-demo-perfect.php → tab-dashboard.php"
echo ""
read -p "Proceed? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Activation cancelled by user."
    exit 0
fi

# Backup and activate
cd tabs
if [ -f "tab-dashboard.php" ]; then
    echo "Backing up existing dashboard..."
    mv tab-dashboard.php tab-dashboard-v3-backup.php
    echo "✅ Backup created: tab-dashboard-v3-backup.php"
fi

echo "Activating new dashboard..."
mv tab-dashboard-v4-demo-perfect.php tab-dashboard.php
echo "✅ Dashboard activated: tab-dashboard.php"

cd ..

echo ""
echo "=========================================="
echo "🎯 STEP 3: Include CSS in index.php"
echo "=========================================="
echo ""

# Check if CSS already included
if grep -q "dashboard-widgets.css" index.php; then
    echo "✅ CSS already included in index.php"
else
    echo "⚠️  CSS not yet included"
    echo ""
    echo "Add this line to index.php <head> section:"
    echo '  <link rel="stylesheet" href="assets/css/dashboard-widgets.css">'
    echo ""
    echo "After this line:"
    echo '  <link rel="stylesheet" href="assets/css/professional-black.css">'
fi

echo ""
echo "=========================================="
echo "✅ DEPLOYMENT COMPLETE"
echo "=========================================="
echo ""
echo "📊 Next Steps:"
echo ""
echo "1. Open browser:"
echo "   https://staff.vapeshed.co.nz/supplier/index.php?tab=dashboard"
echo ""
echo "2. Press F12 (DevTools Console)"
echo ""
echo "3. Check for these messages:"
echo "   ✅ Dashboard stats loaded"
echo "   ✅ Orders table loaded"
echo "   ✅ Stock alerts loaded"
echo "   ✅ Charts loaded"
echo ""
echo "4. Visual check:"
echo "   ✅ 6 metric cards with data (no spinners)"
echo "   ✅ Orders table with rows"
echo "   ✅ Stock alerts showing 6 stores"
echo "   ✅ 2 charts rendered"
echo ""
echo "=========================================="
echo "🎉 Dashboard Migration Ready for Testing!"
echo "=========================================="
