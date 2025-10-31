#!/bin/bash
# ============================================================================
# MASTER FIX SCRIPT - RUN THIS ONE!
# ============================================================================
# Purpose: Runs all fixes in correct order
# Date: October 21, 2025
# Issue: supplier_portal_notifications table missing or incomplete
# ============================================================================

echo "=========================================="
echo "🔧 Master Fix Script"
echo "=========================================="
echo ""
echo "This will run 2 scripts in order:"
echo "  1. create-notifications-table.sql (create/fix notifications table)"
echo "  2. fix-remaining-issues.sql (fix migration issues)"
echo ""
echo "Press ENTER to continue, or CTRL+C to cancel..."
read

echo ""
echo "Step 1/2: Creating/fixing supplier_portal_notifications table..."
echo "=========================================="

mysql -u master -p'JrCDHB3bvBsq2y' jcepnzzkmj < create-notifications-table.sql 2>&1

if [ $? -eq 0 ]; then
    echo "✅ Step 1 complete!"
else
    echo "❌ Step 1 failed! Stopping here."
    exit 1
fi

echo ""
echo "Step 2/2: Running remaining migration fixes..."
echo "=========================================="

mysql -u master -p'JrCDHB3bvBsq2y' jcepnzzkmj < fix-remaining-issues.sql 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo "=========================================="
    echo "🎉 ALL FIXES COMPLETE!"
    echo "=========================================="
    echo ""
    echo "What was fixed:"
    echo "  ✅ supplier_portal_notifications table created/updated"
    echo "  ✅ related_type and related_id columns added"
    echo "  ✅ expected_delivery_date column handled"
    echo "  ✅ Test warranty claim created"
    echo ""
    echo "Next steps:"
    echo "  1. Test supplier portal warranty claims tab"
    echo "  2. Check notifications appear correctly"
    echo "  3. Done! 🎊"
else
    echo ""
    echo "❌ Step 2 had issues. Check output above."
    exit 1
fi
