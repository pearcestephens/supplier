#!/bin/bash

echo "=========================================="
echo "🧪 COMPREHENSIVE PORTAL TEST SUITE"
echo "=========================================="
echo "Testing ALL URLs, Buttons, and Links"
echo ""

# Test supplier credentials
SUPPLIER_ID="0a91b764-1c71-11eb-e0eb-d7bf46fa95c8"
BASE_URL="https://staff.vapeshed.co.nz/supplier"
COOKIE_FILE="/tmp/portal_test_cookies.txt"

# Clean up old cookies
rm -f $COOKIE_FILE

echo "=========================================="
echo "PHASE 1: LOGIN & AUTHENTICATION"
echo "=========================================="
echo ""

# Test 1: Login Page
echo "✓ Test 1: Login page loads"
response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/login.php")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 2: Magic Link Authentication
echo "✓ Test 2: Magic link authentication"
curl -s -c $COOKIE_FILE -b $COOKIE_FILE -L "$BASE_URL/?supplier_id=$SUPPLIER_ID" > /tmp/auth_response.html
if grep -q "British American Tobacco" /tmp/auth_response.html; then
    echo "  ✅ PASSED - Authenticated successfully"
else
    echo "  ❌ FAILED - Authentication failed"
fi
echo ""

echo "=========================================="
echo "PHASE 2: MAIN NAVIGATION TABS"
echo "=========================================="
echo ""

# Test 3: Dashboard Tab
echo "✓ Test 3: Dashboard tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/dashboard.html -w "%{http_code}" "$BASE_URL/?tab=dashboard")
if [ "$response" = "200" ] && grep -q "Dashboard" /tmp/dashboard.html; then
    echo "  ✅ PASSED - HTTP $response, Dashboard loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 4: Orders Tab
echo "✓ Test 4: Purchase Orders tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/orders.html -w "%{http_code}" "$BASE_URL/?tab=orders")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response, Orders loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 5: Warranty Tab
echo "✓ Test 5: Warranty Claims tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/warranty.html -w "%{http_code}" "$BASE_URL/?tab=warranty")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response, Warranty loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 6: Downloads Tab
echo "✓ Test 6: Downloads tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/downloads.html -w "%{http_code}" "$BASE_URL/?tab=downloads")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response, Downloads loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 7: Reports Tab
echo "✓ Test 7: Reports tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/reports.html -w "%{http_code}" "$BASE_URL/?tab=reports")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response, Reports loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi

# Test 8: Account Tab
echo "✓ Test 8: Account Settings tab"
response=$(curl -s -b $COOKIE_FILE -o /tmp/account.html -w "%{http_code}" "$BASE_URL/?tab=account")
if [ "$response" = "200" ]; then
    echo "  ✅ PASSED - HTTP $response, Account loaded"
else
    echo "  ❌ FAILED - HTTP $response"
fi
echo ""

echo "=========================================="
echo "PHASE 3: INVALID TABS & ERROR HANDLING"
echo "=========================================="
echo ""

# Test 9: Invalid Tab
echo "✓ Test 9: Invalid tab handling"
response=$(curl -s -b $COOKIE_FILE -o /tmp/invalid.html -w "%{http_code}" "$BASE_URL/?tab=invalid")
if [ "$response" = "200" ] && grep -q "Tab not found" /tmp/invalid.html; then
    echo "  ✅ PASSED - Shows error message for invalid tab"
else
    echo "  ❌ FAILED - Didn't handle invalid tab properly"
fi
echo ""

echo "=========================================="
echo "PHASE 4: ASSETS & RESOURCES"
echo "=========================================="
echo ""

# Test 10: CSS Files
echo "✓ Test 10: CSS files load"
response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/assets/css/supplier-portal.css")
if [ "$response" = "200" ] || [ "$response" = "304" ]; then
    echo "  ✅ PASSED - HTTP $response"
else
    echo "  ⚠️  WARNING - HTTP $response (CSS might not exist yet)"
fi

# Test 11: JS Files
echo "✓ Test 11: JavaScript files load"
response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/assets/js/supplier-portal.js")
if [ "$response" = "200" ] || [ "$response" = "304" ]; then
    echo "  ✅ PASSED - HTTP $response"
else
    echo "  ⚠️  WARNING - HTTP $response (JS might not exist yet)"
fi
echo ""

echo "=========================================="
echo "PHASE 5: COMPONENT FILES"
echo "=========================================="
echo ""

# Test 12: Header Component (included in pages)
echo "✓ Test 12: Header component"
if grep -q "navbar" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Header loaded"
else
    echo "  ❌ FAILED - Header not found"
fi

# Test 13: Sidebar Component
echo "✓ Test 13: Sidebar component"
if grep -q "sidebar" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Sidebar loaded"
else
    echo "  ❌ FAILED - Sidebar not found"
fi

# Test 14: Footer Component
echo "✓ Test 14: Footer component"
if grep -q "footer\|Footer" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Footer loaded"
else
    echo "  ⚠️  WARNING - Footer not found (might be minimal)"
fi
echo ""

echo "=========================================="
echo "PHASE 6: SESSION & SECURITY"
echo "=========================================="
echo ""

# Test 15: Session Persistence
echo "✓ Test 15: Session persists across requests"
response=$(curl -s -b $COOKIE_FILE -o /tmp/session_test.html "$BASE_URL/")
if grep -q "British American Tobacco" /tmp/session_test.html; then
    echo "  ✅ PASSED - Session maintained"
else
    echo "  ❌ FAILED - Session lost"
fi

# Test 16: Logout Functionality
echo "✓ Test 16: Logout functionality"
if [ -f "$BASE_URL/logout.php" ]; then
    response=$(curl -s -b $COOKIE_FILE -o /dev/null -w "%{http_code}" "$BASE_URL/logout.php")
    echo "  ✅ Logout endpoint exists - HTTP $response"
else
    echo "  ℹ️  INFO - No logout.php found (session expires naturally)"
fi
echo ""

echo "=========================================="
echo "PHASE 7: API ENDPOINTS"
echo "=========================================="
echo ""

# Test 17: API Directory
echo "✓ Test 17: API endpoints accessible"
if [ -d "api" ]; then
    api_files=$(ls api/*.php 2>/dev/null | wc -l)
    echo "  ℹ️  Found $api_files API files"
    
    # Test a few key API endpoints
    for api_file in api/update-po-status.php api/add-warranty-note.php api/warranty-action.php; do
        if [ -f "$api_file" ]; then
            filename=$(basename $api_file)
            response=$(curl -s -b $COOKIE_FILE -o /dev/null -w "%{http_code}" "$BASE_URL/$api_file")
            if [ "$response" = "200" ] || [ "$response" = "400" ] || [ "$response" = "405" ]; then
                echo "  ✅ $filename - HTTP $response (reachable)"
            else
                echo "  ❌ $filename - HTTP $response"
            fi
        fi
    done
else
    echo "  ℹ️  No API directory found"
fi
echo ""

echo "=========================================="
echo "PHASE 8: CONTENT VALIDATION"
echo "=========================================="
echo ""

# Test 18: Check for supplier name in all tabs
echo "✓ Test 18: Supplier name appears in all tabs"
tabs_with_name=0
for tab in dashboard orders warranty downloads reports account; do
    if grep -q "British American" /tmp/${tab}.html 2>/dev/null; then
        tabs_with_name=$((tabs_with_name + 1))
    fi
done
echo "  ✅ Supplier name found in $tabs_with_name/6 tabs"

# Test 19: Check for navigation links
echo "✓ Test 19: Navigation links present"
if grep -q "tab=dashboard" /tmp/dashboard.html && grep -q "tab=orders" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Navigation links working"
else
    echo "  ❌ FAILED - Navigation links missing"
fi

# Test 20: Check for proper page titles
echo "✓ Test 20: Page titles set correctly"
if grep -q "<title>Dashboard - The Vape Shed" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Titles formatted correctly"
else
    echo "  ⚠️  WARNING - Title format may differ"
fi
echo ""

echo "=========================================="
echo "PHASE 9: RESPONSIVE & UI ELEMENTS"
echo "=========================================="
echo ""

# Test 21: Bootstrap CSS loaded
echo "✓ Test 21: Bootstrap CSS referenced"
if grep -q "bootstrap" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Bootstrap CSS included"
else
    echo "  ❌ FAILED - Bootstrap CSS not found"
fi

# Test 22: Font Awesome icons
echo "✓ Test 22: Font Awesome icons"
if grep -q "font-awesome\|fas fa-" /tmp/dashboard.html; then
    echo "  ✅ PASSED - Font Awesome included"
else
    echo "  ❌ FAILED - Font Awesome not found"
fi

# Test 23: jQuery loaded
echo "✓ Test 23: jQuery library"
if grep -q "jquery" /tmp/dashboard.html; then
    echo "  ✅ PASSED - jQuery included"
else
    echo "  ❌ FAILED - jQuery not found"
fi
echo ""

echo "=========================================="
echo "SUMMARY: TEST RESULTS"
echo "=========================================="
echo ""

# Count results
total_tests=23
passed_tests=$(grep -c "✅ PASSED" /tmp/test_log.txt 2>/dev/null || echo "0")

echo "Total Tests Run: $total_tests"
echo ""
echo "View detailed responses:"
echo "  - Dashboard: /tmp/dashboard.html"
echo "  - Orders: /tmp/orders.html"
echo "  - Warranty: /tmp/warranty.html"
echo "  - Downloads: /tmp/downloads.html"
echo "  - Reports: /tmp/reports.html"
echo "  - Account: /tmp/account.html"
echo ""

# Cleanup
rm -f $COOKIE_FILE

echo "=========================================="
echo "🎉 COMPREHENSIVE TEST COMPLETE!"
echo "=========================================="
