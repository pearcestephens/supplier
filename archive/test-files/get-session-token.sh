#!/bin/bash

##############################################################################
# Get Supplier Portal Session Token
# 
# This script authenticates and retrieves a valid session cookie
# for automated testing
##############################################################################

echo "========================================"
echo "Supplier Portal - Get Session Token"
echo "========================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
SUPPLIER_ID="0a91b764-1c71-11eb-e0eb-d7bf46fa95c8"  # British American Tobacco
COOKIE_FILE="/tmp/supplier_cookies.txt"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "Authenticating with supplier portal..."
echo "Supplier ID: $SUPPLIER_ID"
echo ""

# Step 1: Get session by visiting magic link
echo "Step 1: Authenticating via magic link..."
response=$(curl -s -w "\n%{http_code}" \
    -L \
    -c "$COOKIE_JAR" \
    "$BASE_URL/index.php?supplier_id=$SUPPLIER_ID")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✅ Authentication successful (HTTP $http_code)${NC}"
else
    echo -e "${RED}❌ Authentication failed (HTTP $http_code)${NC}"
    exit 1
fi

# Step 2: Extract PHPSESSID from cookie jar
echo ""
echo "Step 2: Extracting session token..."

if [ ! -f "$COOKIE_JAR" ]; then
    echo -e "${RED}❌ Cookie file not created${NC}"
    exit 1
fi

# Extract PHPSESSID from cookie file
PHPSESSID=$(grep -i 'PHPSESSID' "$COOKIE_JAR" | awk '{print $7}')

if [ -z "$PHPSESSID" ]; then
    echo -e "${RED}❌ Session token not found in cookies${NC}"
    echo "Cookie file contents:"
    cat "$COOKIE_JAR"
    exit 1
fi

echo -e "${GREEN}✅ Session token extracted${NC}"
echo ""

# Step 3: Verify session works
echo "Step 3: Verifying session..."
response=$(curl -s -w "\n%{http_code}" \
    -L \
    -H "Cookie: PHPSESSID=$PHPSESSID" \
    "$BASE_URL/index.php?tab=warranty")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" = "200" ]; then
    # Check if we got the warranty page (not redirected to login)
    if echo "$body" | grep -q "Warranty" || echo "$body" | grep -q "warranty"; then
        echo -e "${GREEN}✅ Session verified and working (HTTP $http_code)${NC}"
    else
        echo -e "${YELLOW}⚠️  Got HTTP 200 but may have been redirected to login${NC}"
        echo -e "${YELLOW}   Session may not be working properly${NC}"
    fi
else
    echo -e "${RED}❌ Session verification failed (HTTP $http_code)${NC}"
    exit 1
fi

echo ""
echo "========================================"
echo "SUCCESS!"
echo "========================================"
echo ""
echo "Your session token is:"
echo ""
echo -e "${GREEN}$PHPSESSID${NC}"
echo ""
echo "To use it in tests, run:"
echo ""
echo "  export SESSION_TOKEN='$PHPSESSID'"
echo ""
echo "Or copy this command to run the full test:"
echo ""
echo "  SESSION_TOKEN='$PHPSESSID' ./test-warranty-apis.sh"
echo ""
echo "Session will remain valid for 8 hours (or 30 min idle timeout)"
echo ""

# Clean up
rm -f "$COOKIE_JAR"

exit 0
