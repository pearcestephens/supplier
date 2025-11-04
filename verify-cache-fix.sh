#!/bin/bash

# Dashboard Cache Fix Verification Script
# Tests that all cache-busting measures are in place

echo "🔍 CACHE FIX VERIFICATION SCRIPT"
echo "================================="
echo ""

# Test 1: Dashboard.php has cache headers
echo "📄 Test 1: Dashboard.php cache headers..."
if grep -q "Cache-Control.*no-cache" dashboard.php; then
    echo "   ✅ PASS - Cache headers found"
else
    echo "   ❌ FAIL - No cache headers in dashboard.php"
fi
echo ""

# Test 2: API handler has cache-buster
echo "📄 Test 2: API handler cache-buster..."
if grep -q "_t.*Date.now()" assets/js/02-api-handler.js; then
    echo "   ✅ PASS - Cache-buster found in API handler"
else
    echo "   ❌ FAIL - No cache-buster in API handler"
fi
echo ""

# Test 3: Dashboard.js has cache-busters
echo "📄 Test 3: Dashboard.js cache-busters..."
CACHE_BUSTERS=$(grep -c "_t.*Date.now()" assets/js/dashboard.js)
if [ "$CACHE_BUSTERS" -ge 4 ]; then
    echo "   ✅ PASS - Found $CACHE_BUSTERS cache-busters in dashboard.js"
else
    echo "   ⚠️  WARNING - Only found $CACHE_BUSTERS cache-busters (expected 4+)"
fi
echo ""

# Test 4: API endpoints have cache headers
echo "📄 Test 4: API endpoint cache headers..."
API_COUNT=$(grep -l "Cache-Control.*no-cache" api/dashboard-*.php | wc -l)
echo "   ℹ️  Found $API_COUNT API files with cache headers"
echo ""

# Test 5: Test database state
echo "📄 Test 5: Database verification..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -N -e "
SELECT
    CONCAT('   Total orders: ', COUNT(*)) as stat
FROM vend_consignments
WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
UNION ALL
SELECT
    CONCAT('   OPEN/PACKING: ', COUNT(*))
FROM vend_consignments
WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'
    AND state IN ('OPEN', 'PACKING')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
UNION ALL
SELECT
    CONCAT('   CANCELLED: ', COUNT(*))
FROM vend_consignments
WHERE supplier_id = '0a91b764-1c71-11eb-e0eb-d7bf46fa95c8'
    AND state = 'CANCELLED'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
"
echo ""

# Summary
echo "================================="
echo "✅ VERIFICATION COMPLETE"
echo ""
echo "📝 Expected Results:"
echo "   • All tests PASS"
echo "   • OPEN/PACKING orders: 0"
echo "   • CANCELLED orders: 177"
echo ""
echo "🧪 Manual Test:"
echo "   Open: https://staff.vapeshed.co.nz/supplier/test-cache-fix.html"
echo "   Or press Ctrl+Shift+R on dashboard"
echo ""
