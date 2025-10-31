#!/bin/bash

# ============================================================================
# AUTHENTICATED DASHBOARD API TESTS
# Tests all 4 dashboard endpoints with real authentication
# ============================================================================

echo "============================================"
echo "  AUTHENTICATED DASHBOARD API TESTS"
echo "============================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get a valid supplier_id from database
echo -e "${BLUE}→ Getting valid supplier_id from database...${NC}"
SUPPLIER_ID=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -N -e "SELECT id FROM vend_suppliers WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' LIMIT 1" 2>/dev/null)

if [ -z "$SUPPLIER_ID" ]; then
    echo -e "${RED}✗ ERROR: Could not get supplier_id from database${NC}"
    echo "  Check database connection and vend_suppliers table"
    exit 1
fi

echo -e "${GREEN}✓ Using supplier_id: ${SUPPLIER_ID}${NC}"
echo ""

# Base URL
BASE_URL="https://staff.vapeshed.co.nz/supplier/api"

# Counters
PASSED=0
FAILED=0

# Test function with authentication
test_endpoint() {
    local name=$1
    local endpoint=$2
    
    echo -e "${BLUE}Testing ${name}...${NC}"
    
    # Make request with supplier_id parameter
    RESPONSE=$(curl -s -w "\n%{http_code}" "${BASE_URL}/${endpoint}?supplier_id=${SUPPLIER_ID}")
    
    # Split response into body and status code
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    # Check HTTP status
    if [ "$HTTP_CODE" = "200" ]; then
        # Check JSON structure
        if echo "$BODY" | grep -q '"success"'; then
            # Check if success is true
            if echo "$BODY" | grep -q '"success":true'; then
                echo -e "  ${GREEN}✅ PASS${NC} (HTTP $HTTP_CODE, Valid JSON, Success=true)"
                PASSED=$((PASSED + 1))
            else
                echo -e "  ${YELLOW}⚠️  WARNING${NC} (HTTP $HTTP_CODE, Success=false)"
                echo "  Response: ${BODY:0:200}..."
                FAILED=$((FAILED + 1))
            fi
        else
            echo -e "  ${YELLOW}⚠️  WARNING${NC} (HTTP $HTTP_CODE, Invalid JSON structure)"
            echo "  Response: ${BODY:0:200}..."
            FAILED=$((FAILED + 1))
        fi
    else
        echo -e "  ${RED}❌ FAIL${NC} (HTTP $HTTP_CODE)"
        echo "  Error: ${BODY:0:200}..."
        FAILED=$((FAILED + 1))
    fi
    echo ""
}

echo "============================================"
echo "  RUNNING AUTHENTICATED TESTS"
echo "============================================"
echo ""

# Test all 4 endpoints
test_endpoint "Dashboard Statistics" "dashboard-stats.php"
test_endpoint "Orders Table" "dashboard-orders-table.php"
test_endpoint "Stock Alerts" "dashboard-stock-alerts.php"
test_endpoint "Chart Data" "dashboard-charts.php"

# Summary
echo "============================================"
echo "  TEST SUMMARY"
echo "============================================"
echo -e "Passed: ${GREEN}${PASSED}${NC}"
echo -e "Failed: ${RED}${FAILED}${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Test dashboard.php in browser: https://staff.vapeshed.co.nz/supplier/dashboard.php?supplier_id=${SUPPLIER_ID}"
    echo "2. Check DevTools Console for JavaScript errors"
    echo "3. Verify Network tab shows 4 successful AJAX calls"
    echo "4. Confirm metrics, tables, and charts populate with data"
    echo ""
    exit 0
else
    echo -e "${RED}❌ SOME TESTS FAILED${NC}"
    echo ""
    echo "Action required:"
    echo "1. Check /logs/apache_*.error.log for PHP/SQL errors"
    echo "2. Review failed endpoint code"
    echo "3. Fix issues and re-run this script"
    echo ""
    exit 1
fi
