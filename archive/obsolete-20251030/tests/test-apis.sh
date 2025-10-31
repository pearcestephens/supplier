#!/bin/bash
# API Testing Script for Supplier Portal
# Tests all dashboard API endpoints

echo "🧪 Supplier Portal API Testing"
echo "=============================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="https://staff.vapeshed.co.nz/supplier/api"

# Session cookie (replace with real session token)
COOKIE="session_token=YOUR_SESSION_TOKEN_HERE"

# Test counter
PASS=0
FAIL=0

# Function to test endpoint
test_endpoint() {
    local endpoint=$1
    local name=$2
    
    echo -n "Testing ${name}... "
    
    response=$(curl -s -b "$COOKIE" -w "\n%{http_code}" "${BASE_URL}/${endpoint}")
    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" = "200" ]; then
        # Check if JSON is valid
        if echo "$body" | jq empty 2>/dev/null; then
            # Check if success field is true
            success=$(echo "$body" | jq -r '.success' 2>/dev/null)
            if [ "$success" = "true" ]; then
                echo -e "${GREEN}✅ PASS${NC} (HTTP 200, Valid JSON, success=true)"
                ((PASS++))
            else
                echo -e "${YELLOW}⚠️  WARN${NC} (HTTP 200, Valid JSON, but success=false)"
                echo "   Error: $(echo "$body" | jq -r '.message' 2>/dev/null)"
                ((FAIL++))
            fi
        else
            echo -e "${RED}❌ FAIL${NC} (HTTP 200 but invalid JSON)"
            echo "   Response: ${body:0:100}..."
            ((FAIL++))
        fi
    else
        echo -e "${RED}❌ FAIL${NC} (HTTP ${http_code})"
        echo "   Response: ${body:0:100}..."
        ((FAIL++))
    fi
}

echo "📊 Dashboard APIs"
echo "─────────────────"
test_endpoint "dashboard-stats.php" "Dashboard Stats"
test_endpoint "dashboard-orders-table.php?limit=10" "Orders Table"
test_endpoint "dashboard-stock-alerts.php" "Stock Alerts"
test_endpoint "dashboard-charts.php" "Charts Data"

echo ""
echo "📦 Purchase Order APIs"
echo "──────────────────────"
test_endpoint "po-list.php?status=all&page=1" "PO List"
# Note: PO detail needs a real order ID
# test_endpoint "po-detail.php?id=12345" "PO Detail"

echo ""
echo "📈 Results Summary"
echo "══════════════════"
echo -e "Passed: ${GREEN}${PASS}${NC}"
echo -e "Failed: ${RED}${FAIL}${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed. Check the output above.${NC}"
    exit 1
fi
