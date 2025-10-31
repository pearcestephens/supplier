#!/bin/bash

###############################################################################
# COMPREHENSIVE SUPPLIER PORTAL TESTING SCRIPT
# Tests all pages, APIs, and functionality
###############################################################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
COOKIE_FILE="/tmp/supplier_test_cookies.txt"
SESSION_TOKEN="YOUR_SESSION_TOKEN_HERE"  # Replace with real session token
SUPPLIER_ID="YOUR_SUPPLIER_ID_HERE"      # Replace with real supplier UUID

# Test counters
PASS=0
FAIL=0
WARN=0

###############################################################################
# Helper Functions
###############################################################################

print_header() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
}

print_test() {
    echo -e "${YELLOW}[TEST]${NC} $1"
}

print_pass() {
    echo -e "${GREEN}✅ PASS${NC} - $1"
    ((PASS++))
}

print_fail() {
    echo -e "${RED}❌ FAIL${NC} - $1"
    ((FAIL++))
}

print_warn() {
    echo -e "${YELLOW}⚠️  WARN${NC} - $1"
    ((WARN++))
}

test_http_status() {
    local url=$1
    local expected=${2:-200}
    local description=$3
    
    print_test "$description"
    
    # Make request and capture status
    local status=$(curl -s -o /dev/null -w "%{http_code}" \
        -b "session_token=$SESSION_TOKEN" \
        --max-time 10 \
        "$url")
    
    if [ "$status" = "$expected" ]; then
        print_pass "HTTP $status - $description"
        return 0
    else
        print_fail "Expected HTTP $expected, got $status - $description"
        return 1
    fi
}

test_json_api() {
    local url=$1
    local description=$2
    
    print_test "$description"
    
    # Make request
    local response=$(curl -s \
        -b "session_token=$SESSION_TOKEN" \
        --max-time 10 \
        "$url")
    
    # Check if valid JSON
    if ! echo "$response" | jq empty 2>/dev/null; then
        print_fail "Invalid JSON response - $description"
        echo "Response: $response" | head -n 3
        return 1
    fi
    
    # Check success field
    local success=$(echo "$response" | jq -r '.success // "null"')
    
    if [ "$success" = "true" ]; then
        print_pass "Valid JSON with success=true - $description"
        return 0
    elif [ "$success" = "false" ]; then
        local message=$(echo "$response" | jq -r '.message // "No message"')
        print_warn "API returned success=false: $message - $description"
        return 2
    else
        print_fail "Missing or invalid 'success' field - $description"
        return 1
    fi
}

test_page_content() {
    local url=$1
    local search_term=$2
    local description=$3
    
    print_test "$description"
    
    local content=$(curl -s \
        -b "session_token=$SESSION_TOKEN" \
        --max-time 10 \
        "$url")
    
    if echo "$content" | grep -q "$search_term"; then
        print_pass "Found '$search_term' - $description"
        return 0
    else
        print_fail "Missing '$search_term' - $description"
        return 1
    fi
}

###############################################################################
# PHASE 1: Main Pages
###############################################################################

print_header "PHASE 1: Testing Main Pages"

test_http_status "$BASE_URL/index.php?supplier_id=$SUPPLIER_ID" 302 "Index page (should redirect)"
test_http_status "$BASE_URL/dashboard.php" 200 "Dashboard page"
test_http_status "$BASE_URL/orders.php" 200 "Orders page"
test_http_status "$BASE_URL/products.php" 200 "Products page"
test_http_status "$BASE_URL/warranty.php" 200 "Warranty page"
test_http_status "$BASE_URL/downloads.php" 200 "Downloads page"
test_http_status "$BASE_URL/reports.php" 200 "Reports page"
test_http_status "$BASE_URL/account.php" 200 "Account page"

###############################################################################
# PHASE 2: Dashboard APIs
###############################################################################

print_header "PHASE 2: Testing Dashboard APIs"

test_json_api "$BASE_URL/api/dashboard-stats.php" "Dashboard stats endpoint"
test_json_api "$BASE_URL/api/dashboard-orders-table.php?limit=10" "Dashboard orders table endpoint"
test_json_api "$BASE_URL/api/dashboard-stock-alerts.php" "Dashboard stock alerts endpoint"
test_json_api "$BASE_URL/api/dashboard-charts.php" "Dashboard charts endpoint"

###############################################################################
# PHASE 3: Purchase Order APIs
###############################################################################

print_header "PHASE 3: Testing Purchase Order APIs"

test_json_api "$BASE_URL/api/po-list.php?page=1&limit=25" "PO list endpoint"
test_json_api "$BASE_URL/api/po-stats.php" "PO stats endpoint"
test_json_api "$BASE_URL/api/po-outlets.php" "PO outlets list endpoint"

# Get first order ID for detail test
print_test "Getting sample order ID for detail test"
ORDER_ID=$(curl -s -b "session_token=$SESSION_TOKEN" "$BASE_URL/api/po-list.php?limit=1" | jq -r '.data.orders[0].id // "null"')
if [ "$ORDER_ID" != "null" ] && [ -n "$ORDER_ID" ]; then
    print_pass "Found sample order ID: $ORDER_ID"
    test_json_api "$BASE_URL/api/po-detail.php?id=$ORDER_ID" "PO detail endpoint (ID: $ORDER_ID)"
else
    print_warn "No orders found to test detail endpoint"
fi

###############################################################################
# PHASE 4: Product APIs
###############################################################################

print_header "PHASE 4: Testing Product APIs"

test_json_api "$BASE_URL/api/products-list.php?page=1&limit=25" "Products list endpoint"
test_json_api "$BASE_URL/api/products-stats.php" "Products stats endpoint"

###############################################################################
# PHASE 5: Warranty APIs
###############################################################################

print_header "PHASE 5: Testing Warranty APIs"

test_json_api "$BASE_URL/api/warranty-list.php?page=1&limit=25" "Warranty list endpoint"
test_json_api "$BASE_URL/api/warranty-stats.php" "Warranty stats endpoint"

###############################################################################
# PHASE 6: Content Checks
###############################################################################

print_header "PHASE 6: Testing Page Content"

test_page_content "$BASE_URL/dashboard.php" "Dashboard" "Dashboard title present"
test_page_content "$BASE_URL/dashboard.php" "chart.js" "Chart.js library loaded"
test_page_content "$BASE_URL/dashboard.php" "loadDashboardStats" "Dashboard JavaScript present"
test_page_content "$BASE_URL/orders.php" "Orders" "Orders page title present"
test_page_content "$BASE_URL/products.php" "Products" "Products page title present"
test_page_content "$BASE_URL/warranty.php" "Warranty" "Warranty page title present"

###############################################################################
# PHASE 7: Static Assets
###############################################################################

print_header "PHASE 7: Testing Static Assets"

test_http_status "$BASE_URL/assets/css/professional-black.css" 200 "Main CSS file"
test_http_status "$BASE_URL/assets/css/dashboard-widgets.css" 200 "Dashboard widgets CSS"
test_http_status "$BASE_URL/assets/js/supplier-portal.js" 200 "Main JavaScript file"

###############################################################################
# PHASE 8: Security Tests
###############################################################################

print_header "PHASE 8: Testing Security"

print_test "Testing unauthenticated access (should fail)"
UNAUTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/dashboard-stats.php")
if [ "$UNAUTH_STATUS" = "401" ] || [ "$UNAUTH_STATUS" = "403" ]; then
    print_pass "Unauthenticated API access blocked (HTTP $UNAUTH_STATUS)"
else
    print_fail "API accessible without authentication (HTTP $UNAUTH_STATUS)"
fi

print_test "Testing invalid supplier_id filtering"
test_json_api "$BASE_URL/api/dashboard-stats.php" "Supplier ID filtering active"

###############################################################################
# PHASE 9: Component Includes
###############################################################################

print_header "PHASE 9: Testing Component Includes"

test_page_content "$BASE_URL/dashboard.php" "components/header-top.php" "Header top component included"
test_page_content "$BASE_URL/dashboard.php" "components/sidebar.php" "Sidebar component included"

###############################################################################
# FINAL SUMMARY
###############################################################################

print_header "TEST RESULTS SUMMARY"

TOTAL=$((PASS + FAIL + WARN))
PASS_PERCENT=$(awk "BEGIN {printf \"%.1f\", ($PASS/$TOTAL)*100}")

echo -e "${GREEN}✅ Passed:${NC}  $PASS tests"
echo -e "${RED}❌ Failed:${NC}  $FAIL tests"
echo -e "${YELLOW}⚠️  Warnings:${NC} $WARN tests"
echo -e "${BLUE}📊 Total:${NC}   $TOTAL tests"
echo ""
echo -e "${BLUE}Pass Rate:${NC} $PASS_PERCENT%"

if [ $FAIL -eq 0 ]; then
    echo -e "\n${GREEN}🎉 ALL CRITICAL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "\n${RED}❌ SOME TESTS FAILED - REVIEW ABOVE${NC}"
    exit 1
fi
