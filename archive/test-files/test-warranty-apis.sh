#!/bin/bash

##############################################################################
# Warranty System - API Testing Script
# Tests all warranty-related API endpoints
##############################################################################

echo "========================================"
echo "Warranty System API Tests"
echo "========================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
SUPPLIER_ID="03f1b070-b0f8-11ec-a8dc-2d8b85195d82"  # British American Tobacco
TEST_FAULT_ID=3535

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Session token (you'll need to login first and get this)
if [ -z "$SESSION_TOKEN" ]; then
    echo -e "${YELLOW}⚠️  SESSION_TOKEN not set${NC}"
    echo "Please login to the portal and extract your session cookie:"
    echo ""
    echo "  1. Login at: $BASE_URL/login.php"
    echo "  2. Open browser dev tools (F12)"
    echo "  3. Go to Application/Storage > Cookies"
    echo "  4. Copy the 'supplier_session' cookie value"
    echo "  5. Export it: export SESSION_TOKEN='your_token_here'"
    echo ""
    echo "Then run this script again."
    echo ""
    exit 1
fi

echo "Using session token: ${SESSION_TOKEN:0:20}..."
echo ""

##############################################################################
# Test 1: View Warranty Tab
##############################################################################
echo "Test 1: View Warranty Tab"
echo "-------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    "$BASE_URL/supplier-dashboard.php?tab=warranty")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    if echo "$body" | grep -q "Fault ID $TEST_FAULT_ID" || echo "$body" | grep -q "pending"; then
        echo -e "${GREEN}✅ PASS${NC} - Warranty tab loaded (HTTP $http_code)"
    else
        echo -e "${YELLOW}⚠️  WARNING${NC} - Tab loaded but test claim not found (HTTP $http_code)"
        echo "   This may be normal if claim has been processed"
    fi
else
    echo -e "${RED}❌ FAIL${NC} - Failed to load tab (HTTP $http_code)"
fi
echo ""

##############################################################################
# Test 2: Accept Claim (will fail if already processed)
##############################################################################
echo "Test 2: Accept Warranty Claim"
echo "------------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    -d "{
        \"action\": \"accept\",
        \"fault_id\": $TEST_FAULT_ID,
        \"resolution\": \"Test acceptance - automated test at $(date)\"
    }" \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Claim accepted successfully (HTTP $http_code)"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
elif [ "$http_code" = "400" ]; then
    if echo "$body" | grep -q "already been"; then
        echo -e "${YELLOW}⚠️  EXPECTED${NC} - Claim already processed (HTTP $http_code)"
        echo "$body" | jq '.error' 2>/dev/null || echo "$body"
    else
        echo -e "${RED}❌ FAIL${NC} - Bad request (HTTP $http_code)"
        echo "$body"
    fi
else
    echo -e "${RED}❌ FAIL${NC} - Failed to accept claim (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Test 3: Decline Claim (will fail if already processed)
##############################################################################
echo "Test 3: Decline Warranty Claim"
echo "-------------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    -d "{
        \"action\": \"decline\",
        \"fault_id\": $TEST_FAULT_ID,
        \"reason\": \"Test decline - automated test at $(date)\"
    }" \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Claim declined successfully (HTTP $http_code)"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
elif [ "$http_code" = "400" ]; then
    if echo "$body" | grep -q "already been"; then
        echo -e "${YELLOW}⚠️  EXPECTED${NC} - Claim already processed (HTTP $http_code)"
        echo "$body" | jq '.error' 2>/dev/null || echo "$body"
    else
        echo -e "${RED}❌ FAIL${NC} - Bad request (HTTP $http_code)"
        echo "$body"
    fi
else
    echo -e "${RED}❌ FAIL${NC} - Failed to decline claim (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Test 4: Add Warranty Note
##############################################################################
echo "Test 4: Add Warranty Note"
echo "-------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    -d "{
        \"fault_id\": $TEST_FAULT_ID,
        \"note\": \"Test note added by automated test at $(date)\",
        \"action_taken\": \"test_note\"
    }" \
    "$BASE_URL/api/add-warranty-note.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Note added successfully (HTTP $http_code)"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
else
    echo -e "${RED}❌ FAIL${NC} - Failed to add note (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Test 5: Update Warranty Claim Status
##############################################################################
echo "Test 5: Update Warranty Status"
echo "-------------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    -d "{
        \"fault_id\": $TEST_FAULT_ID,
        \"status\": 1,
        \"action\": \"APPROVED\",
        \"note\": \"Test status update at $(date)\"
    }" \
    "$BASE_URL/api/update-warranty-claim.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Status updated successfully (HTTP $http_code)"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
else
    echo -e "${RED}❌ FAIL${NC} - Failed to update status (HTTP $http_code)"
    echo "$body"
fi
echo ""

##############################################################################
# Test 6: Download Media (if exists)
##############################################################################
echo "Test 6: Download Media File"
echo "---------------------------"

# First, check if any media exists for this claim
response=$(curl -s -w "\n%{http_code}" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    "$BASE_URL/api/download-media.php?id=1")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Media download works (HTTP $http_code)"
elif [ "$http_code" = "404" ]; then
    echo -e "${YELLOW}⚠️  EXPECTED${NC} - No media files found (HTTP $http_code)"
    echo "   This is normal if no media has been uploaded yet"
else
    echo -e "${RED}❌ FAIL${NC} - Media download error (HTTP $http_code)"
fi
echo ""

##############################################################################
# Test 7: Download ZIP Archive
##############################################################################
echo "Test 7: Download ZIP Archive"
echo "----------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    "$BASE_URL/api/download-media.php?fault_id=$TEST_FAULT_ID&type=zip")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC} - ZIP download works (HTTP $http_code)"
elif [ "$http_code" = "404" ]; then
    echo -e "${YELLOW}⚠️  EXPECTED${NC} - No media files to zip (HTTP $http_code)"
    echo "   This is normal if no media has been uploaded yet"
else
    echo -e "${RED}❌ FAIL${NC} - ZIP download error (HTTP $http_code)"
fi
echo ""

##############################################################################
# Test 8: Unauthorized Access (no session)
##############################################################################
echo "Test 8: Unauthorized Access Check"
echo "----------------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "{
        \"action\": \"accept\",
        \"fault_id\": $TEST_FAULT_ID,
        \"resolution\": \"This should fail\"
    }" \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)

if [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Unauthorized access properly blocked (HTTP $http_code)"
else
    echo -e "${RED}❌ FAIL${NC} - Security issue: unauthorized access not blocked (HTTP $http_code)"
fi
echo ""

##############################################################################
# Test 9: Invalid Fault ID
##############################################################################
echo "Test 9: Invalid Fault ID Handling"
echo "----------------------------------"

response=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Cookie: PHPSESSID=$SESSION_TOKEN" \
    -d "{
        \"action\": \"accept\",
        \"fault_id\": 999999999,
        \"resolution\": \"This fault does not exist\"
    }" \
    "$BASE_URL/api/warranty-action.php")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Invalid fault ID properly rejected (HTTP $http_code)"
elif [ "$http_code" = "400" ]; then
    echo -e "${GREEN}✅ PASS${NC} - Invalid fault ID properly rejected (HTTP $http_code)"
else
    echo -e "${RED}❌ FAIL${NC} - Invalid fault ID not handled (HTTP $http_code)"
fi
echo ""

##############################################################################
# Summary
##############################################################################
echo "========================================"
echo "Test Summary"
echo "========================================"
echo ""
echo "✅ = Test passed"
echo "⚠️  = Expected result (e.g., claim already processed)"
echo "❌ = Test failed (needs investigation)"
echo ""
echo "Notes:"
echo "- Some tests may show warnings if the test claim has already been processed"
echo "- Media download tests will show warnings if no media files exist yet"
echo "- All security tests (unauthorized access, invalid IDs) should PASS"
echo ""
echo "Next Steps:"
echo "1. Review any ❌ FAIL results above"
echo "2. Test manually in browser to verify UI behavior"
echo "3. Check database for claim status updates"
echo "4. Upload test media files to test download functionality"
echo ""
