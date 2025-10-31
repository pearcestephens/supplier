#!/bin/bash
# Comprehensive API Testing Script
# Tests all 5 new API endpoints with various scenarios

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     COMPREHENSIVE API ENDPOINT TESTING - ALL SCENARIOS         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

BASE_URL="https://staff.vapeshed.co.nz/supplier/api"
PASS_COUNT=0
FAIL_COUNT=0

# Function to test endpoint
test_endpoint() {
    local name="$1"
    local url="$2"
    local expected_code="$3"
    
    echo -n "Testing $name... "
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>&1)
    
    if [ "$response" = "$expected_code" ]; then
        echo "✅ PASS (HTTP $response)"
        ((PASS_COUNT++))
    else
        echo "❌ FAIL (Expected $expected_code, got $response)"
        ((FAIL_COUNT++))
    fi
}

# Function to test JSON response
test_json_response() {
    local name="$1"
    local url="$2"
    
    echo -n "Testing $name... "
    
    response=$(curl -s "$url" 2>&1)
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>&1)
    
    # Check if response contains "success" field
    if echo "$response" | grep -q '"success"'; then
        echo "✅ PASS (HTTP $http_code, Valid JSON)"
        echo "   Response preview: $(echo "$response" | head -c 100)..."
        ((PASS_COUNT++))
    else
        echo "❌ FAIL (HTTP $http_code, Invalid/Missing JSON)"
        echo "   Response: $response"
        ((FAIL_COUNT++))
    fi
    echo ""
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TEST SUITE 1: API Endpoint Accessibility"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

test_endpoint "search-orders.php exists" \
    "${BASE_URL}/search-orders.php?q=test" "200"

test_endpoint "get-order-detail.php exists" \
    "${BASE_URL}/get-order-detail.php?id=1" "200"

test_endpoint "get-warranty-detail.php exists" \
    "${BASE_URL}/get-warranty-detail.php?id=1" "200"

test_endpoint "update-account.php exists" \
    "${BASE_URL}/update-account.php" "405"

test_endpoint "search-products.php exists" \
    "${BASE_URL}/search-products.php?q=test" "200"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TEST SUITE 2: JSON Response Format"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

test_json_response "search-orders.php JSON" \
    "${BASE_URL}/search-orders.php?q=PO"

test_json_response "search-products.php JSON" \
    "${BASE_URL}/search-products.php?q=vape"

test_json_response "get-order-detail.php JSON" \
    "${BASE_URL}/get-order-detail.php?id=1"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TEST SUITE 3: Query Validation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Short query (should return empty results)
test_json_response "search-orders.php (short query)" \
    "${BASE_URL}/search-orders.php?q=a"

# Missing query
test_json_response "search-orders.php (no query)" \
    "${BASE_URL}/search-orders.php"

# Invalid ID
test_json_response "get-order-detail.php (invalid ID)" \
    "${BASE_URL}/get-order-detail.php?id=abc"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TEST SUITE 4: Method Validation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# POST to GET endpoint (should fail gracefully)
echo -n "Testing search-orders.php (wrong method)... "
response=$(curl -s -X POST "${BASE_URL}/search-orders.php?q=test" 2>&1)
if echo "$response" | grep -q '"success".*false'; then
    echo "✅ PASS (Properly rejects POST method)"
    ((PASS_COUNT++))
else
    echo "❌ FAIL (Should reject POST method)"
    ((FAIL_COUNT++))
fi

# GET to POST endpoint (should return 405)
test_endpoint "update-account.php (wrong method)" \
    "${BASE_URL}/update-account.php" "405"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TEST SUITE 5: Response Time"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

for endpoint in "search-orders.php?q=test" "search-products.php?q=test" "get-order-detail.php?id=1"; do
    echo -n "Testing ${endpoint} response time... "
    time_total=$(curl -s -o /dev/null -w "%{time_total}" "${BASE_URL}/${endpoint}" 2>&1)
    time_ms=$(echo "$time_total * 1000" | bc)
    
    if (( $(echo "$time_total < 2.0" | bc -l) )); then
        echo "✅ PASS (${time_ms}ms < 2000ms)"
        ((PASS_COUNT++))
    else
        echo "⚠️  SLOW (${time_ms}ms > 2000ms)"
        ((FAIL_COUNT++))
    fi
done

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                      TEST RESULTS SUMMARY                      ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Total Tests Run:     $((PASS_COUNT + FAIL_COUNT))"
echo "✅ Passed:           $PASS_COUNT"
echo "❌ Failed:           $FAIL_COUNT"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo "🎉 ALL TESTS PASSED! API endpoints are working correctly!"
    exit 0
else
    echo "⚠️  Some tests failed. Review the output above for details."
    exit 1
fi
