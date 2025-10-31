#!/bin/bash
echo "=========================================="
echo "🧪 SUPPLIER PORTAL - FINAL TEST SUITE"
echo "=========================================="
echo ""

# Test 1: Login Page
echo "Test 1: Login Page Loads"
response=$(curl -s -o /dev/null -w "%{http_code}" "https://staff.vapeshed.co.nz/supplier/login.php")
if [ "$response" = "200" ]; then
    echo "✅ PASSED - HTTP $response"
else
    echo "❌ FAILED - HTTP $response"
fi
echo ""

# Test 2: Unauthenticated Redirect
echo "Test 2: Unauthenticated Access Redirects to Login"
location=$(curl -s -o /dev/null -w "%{redirect_url}" "https://staff.vapeshed.co.nz/supplier/")
if [[ "$location" == *"login.php"* ]]; then
    echo "✅ PASSED - Redirects to login.php"
else
    echo "❌ FAILED - No redirect or wrong destination"
fi
echo ""

# Test 3: Magic Link Authentication
echo "Test 3: Magic Link Creates Session"
headers=$(curl -sI "https://staff.vapeshed.co.nz/supplier/?supplier_id=0a91b764-1c71-11eb-e0eb-d7bf46fa95c8")
if echo "$headers" | grep -q "Set-Cookie.*PHPSESSID" && echo "$headers" | grep -q "302"; then
    echo "✅ PASSED - Session created, HTTP 302 redirect"
else
    echo "❌ FAILED - No session or no redirect"
fi
echo ""

# Test 4: Authenticated Dashboard
echo "Test 4: Dashboard Shows Supplier Name"
content=$(curl -s -c /tmp/test_cookies.txt -b /tmp/test_cookies.txt -L "https://staff.vapeshed.co.nz/supplier/?supplier_id=0a91b764-1c71-11eb-e0eb-d7bf46fa95c8")
if echo "$content" | grep -q "British American Tobacco"; then
    echo "✅ PASSED - Supplier name displayed"
else
    echo "❌ FAILED - Supplier name not found"
fi
echo ""

# Test 5: Session Persistence
echo "Test 5: Session Persists Without supplier_id"
content=$(curl -s -b /tmp/test_cookies.txt "https://staff.vapeshed.co.nz/supplier/")
if echo "$content" | grep -q "Dashboard" && echo "$content" | grep -q "British American"; then
    echo "✅ PASSED - Session maintained"
else
    echo "❌ FAILED - Session not maintained"
fi
echo ""

# Cleanup
rm -f /tmp/test_cookies.txt

echo "=========================================="
echo "🎉 TEST SUITE COMPLETE"
echo "=========================================="
