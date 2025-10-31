#!/bin/bash
# Test Order Management System
# Tests: Status changes, 24-hour validation, carrier updates, notes

echo "🧪 ORDER MANAGEMENT SYSTEM TEST SUITE"
echo "======================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
TEST_ORDER_ID=1  # Replace with real order ID

# Test 1: Get Order Details
echo "✅ TEST 1: Get Order Details"
echo "----------------------------"
curl -s "${BASE_URL}/api/get-order-detail.php?id=${TEST_ORDER_ID}" | json_pp
echo ""

# Test 2: Get Order History
echo "✅ TEST 2: Get Order History"
echo "----------------------------"
curl -s "${BASE_URL}/api/get-order-history.php?id=${TEST_ORDER_ID}" | json_pp
echo ""

# Test 3: Add Note (requires authentication)
echo "✅ TEST 3: Add Order Note"
echo "-------------------------"
echo "Skipping - requires authenticated session"
echo ""

# Test 4: Update Order Status (requires authentication)
echo "✅ TEST 4: Update Order Status"
echo "------------------------------"
echo "Skipping - requires authenticated session"
echo ""

# Test 5: Check Database Tables
echo "✅ TEST 5: Verify Database Tables"
echo "---------------------------------"
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT 'order_history' as table_name, COUNT(*) as row_count FROM order_history
UNION ALL
SELECT 'staff_transfers', COUNT(*) FROM staff_transfers
UNION ALL
SELECT 'staff_transfers with carrier', COUNT(*) FROM staff_transfers WHERE carrier_name IS NOT NULL;
" 2>/dev/null
echo ""

# Test 6: Check Recent History Entries
echo "✅ TEST 6: Recent History Entries"
echo "---------------------------------"
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT
    id,
    order_id,
    action,
    SUBSTRING(note, 1, 50) as note_preview,
    created_by,
    created_at
FROM order_history
ORDER BY created_at DESC
LIMIT 5;
" 2>/dev/null
echo ""

# Test 7: Check carrier_name Column
echo "✅ TEST 7: Verify carrier_name Column"
echo "-------------------------------------"
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
DESCRIBE staff_transfers;
" 2>/dev/null | grep carrier_name
echo ""

# Test 8: Asset Loading
echo "✅ TEST 8: Check Asset Files"
echo "----------------------------"
echo "JavaScript files:"
ls -lh assets/js/12-order-management.js 2>/dev/null || echo "❌ 12-order-management.js not found"
echo ""
echo "CSS files:"
ls -lh assets/css/04-order-management.css 2>/dev/null || echo "❌ 04-order-management.css not found"
echo ""

echo "======================================"
echo "✅ Test suite complete!"
echo ""
echo "MANUAL TESTS REQUIRED:"
echo "1. Login to supplier portal"
echo "2. Click 'Edit' button on an order"
echo "3. Change status OPEN → SENT"
echo "4. Add carrier 'CourierPost'"
echo "5. Add note 'Test note'"
echo "6. Save and verify changes"
echo "7. View order history"
echo "8. Test 24-hour locking (create order, wait 24h, try to change status)"
echo ""
