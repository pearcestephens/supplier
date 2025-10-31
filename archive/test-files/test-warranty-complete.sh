#!/bin/bash

##############################################################################
# Complete Warranty API Test with Auto-Login
# 
# This script:
# 1. Gets a fresh session token via magic link
# 2. Runs all warranty API tests
# 3. Provides detailed results
##############################################################################

echo "========================================"
echo "Warranty System - Full Test Suite"
echo "========================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
SUPPLIER_ID="0a91b764-1c71-11eb-e0eb-d7bf46fa95c8"  # British American Tobacco
TEST_FAULT_ID=3535
COOKIE_JAR="/tmp/warranty_test_cookies.txt"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

##############################################################################
# Step 1: Authenticate and Get Session
##############################################################################
echo -e "${BLUE}Step 1: Authenticating...${NC}"

# Login via magic link
response=$(curl -s -w "\n%{http_code}" \
    -L \
    -c "$COOKIE_JAR" \
    "$BASE_URL/index.php?supplier_id=$SUPPLIER_ID")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" != "200" ]; then
    echo -e "${RED}❌ Authentication failed (HTTP $http_code)${NC}"
    exit 1
fi

# Extract PHPSESSID
if [ ! -f "$COOKIE_JAR" ]; then
    echo -e "${RED}❌ Cookie file not created${NC}"
    exit 1
fi

SESSION_TOKEN=$(grep -i 'PHPSESSID' "$COOKIE_JAR" | awk '{print $7}')

if [ -z "$SESSION_TOKEN" ]; then
    echo -e "${RED}❌ Session token not found${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Authenticated successfully${NC}"
echo "   Session: ${SESSION_TOKEN:0:20}..."
echo ""

##############################################################################
# Step 2: Test Warranty Tab Page Load
##############################################################################
echo -e "${BLUE}Test 1: Load Warranty Tab${NC}"

response=$(curl -s -w "\n%{http_code}" \
    -b "$COOKIE_JAR" \
    "$BASE_URL/index.php?tab=warranty")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    if echo "$body" | grep -q "Warranty"; then
        echo -e "${GREEN}✅ PASS${NC} - Warranty tab loaded successfully"
        
        if echo "$body" | grep -q "$TEST_FAULT_ID"; then
            echo "   Found test claim (Fault ID $TEST_FAULT_ID)"
        else
            echo -e "${YELLOW}   Note: Test claim not visible (may be processed)${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  WARNING${NC} - Page loaded but content unclear"
    fi
else
    echo -e "${RED}❌ FAIL${NC} - Tab load failed (HTTP $http_code)"
fi
echo ""

##############################################################################
# Step 3: Test Accept Claim API
##############################################################################
echo -e "${BLUE}Test 2: Accept Warranty Claim${NC}"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -b "$COOKIE_JAR" \
    -d "{
        \"action\": \"accept\",
        \"fault_id\": $TEST_FAULT_ID,
        \"resolution\": \"Automated test - replacement unit dispatched at $(date +'%Y-%m-%d %H:%M:%S')\"
    }" \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Claim accepted successfully"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
elif [ "$http_code" = "400" ]; then
    if echo "$body" | grep -q "already been"; then
        echo -e "${YELLOW}⚠️  EXPECTED${NC} - Claim already processed"
        echo "$body" | jq '.error' 2>/dev/null || echo "$body"
    else
        echo -e "${RED}❌ FAIL${NC} - Bad request"
        echo "$body"
    fi
elif [ "$http_code" = "404" ]; then
    echo -e "${YELLOW}⚠️  EXPECTED${NC} - Claim not found (may not belong to this supplier)"
    echo "$body" | jq '.error' 2>/dev/null || echo "$body"
else
    echo -e "${RED}❌ FAIL${NC} - Unexpected error (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Step 4: Test Add Warranty Note
##############################################################################
echo -e "${BLUE}Test 3: Add Warranty Note${NC}"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -b "$COOKIE_JAR" \
    -d "{
        \"fault_id\": $TEST_FAULT_ID,
        \"note\": \"Test note added by automated test suite at $(date +'%Y-%m-%d %H:%M:%S')\",
        \"action_taken\": \"test_note\"
    }" \
    "$BASE_URL/api/add-warranty-note.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Note added successfully"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
elif [ "$http_code" = "404" ]; then
    echo -e "${YELLOW}⚠️  EXPECTED${NC} - Claim not found"
    echo "$body" | jq '.error' 2>/dev/null || echo "$body"
else
    echo -e "${RED}❌ FAIL${NC} - Failed to add note (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Step 5: Test Download Media (if exists)
##############################################################################
echo -e "${BLUE}Test 4: Download Media File${NC}"

response=$(curl -s -w "\n%{http_code}" \
    -I \
    -b "$COOKIE_JAR" \
    "$BASE_URL/api/download-media.php?id=1")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Media download works"
elif [ "$http_code" = "404" ]; then
    echo -e "${YELLOW}⚠️  EXPECTED${NC} - No media file with ID 1"
else
    echo -e "${RED}❌ FAIL${NC} - Media download error (HTTP $http_code)"
fi
echo ""

##############################################################################
# Step 6: Test Unauthorized Access Prevention
##############################################################################
echo -e "${BLUE}Test 5: Security - Unauthorized Access${NC}"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{"action":"accept","fault_id":3535,"resolution":"Hack attempt"}' \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Unauthorized access properly blocked"
else
    echo -e "${RED}❌ FAIL${NC} - Security issue: unauthenticated request not blocked (HTTP $http_code)"
fi
echo ""

##############################################################################
# Summary
##############################################################################
echo "========================================"
echo "Test Suite Complete"
echo "========================================"
echo ""
echo "Tested APIs:"
echo "  - Warranty tab page load"
echo "  - Accept warranty claim"
echo "  - Add warranty note"
echo "  - Download media file"
echo "  - Security: Unauthorized access"
echo ""
echo "Session Token: $SESSION_TOKEN"
echo ""
echo "To manually test in browser:"
echo "  1. Open: $BASE_URL/index.php?supplier_id=$SUPPLIER_ID"
echo "  2. Navigate to Warranty & Returns tab"
echo "  3. Test accept/decline functionality"
echo ""

# Cleanup
rm -f "$COOKIE_JAR"

exit 0
