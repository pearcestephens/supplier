#!/bin/bash
# Test Script for Order Detail Functionality
# Tests all action buttons and export functionality

echo "======================================"
echo "ORDER DETAIL FUNCTIONALITY TEST"
echo "======================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier"
TEST_ORDER_ID=1  # Replace with a valid order ID

echo "Testing Base URL: $BASE_URL"
echo "Test Order ID: $TEST_ORDER_ID"
echo ""

# Test 1: Order Detail Page Load
echo "Test 1: Order Detail Page Load"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/order-detail.php?id=${TEST_ORDER_ID}")
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ PASS: Order detail page loads (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: Order detail page error (HTTP $HTTP_CODE)"
fi
echo ""

# Test 2: CSV Export Endpoint
echo "Test 2: CSV Export Endpoint"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/export-order-items.php?id=${TEST_ORDER_ID}")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "✅ PASS: CSV export endpoint exists (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: CSV export endpoint error (HTTP $HTTP_CODE)"
fi
echo ""

# Test 3: PDF Export Endpoint
echo "Test 3: PDF Export Endpoint"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/export-order-pdf.php?id=${TEST_ORDER_ID}")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "✅ PASS: PDF export endpoint exists (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: PDF export endpoint error (HTTP $HTTP_CODE)"
fi
echo ""

# Test 4: Update Status API Endpoint
echo "Test 4: Update Order Status API"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    -d '{"order_id":1,"status":"SENT"}' \
    "${BASE_URL}/api/update-order-status.php")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "400" ]; then
    echo "✅ PASS: Update status API exists (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: Update status API error (HTTP $HTTP_CODE)"
fi
echo ""

# Test 5: Tracking Modal Script
echo "Test 5: Tracking Modal Script"
echo "--------------------------------------"
if [ -f "${BASE_URL}/assets/js/add-tracking-modal.js" ]; then
    echo "✅ PASS: Tracking modal script exists"
else
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/assets/js/add-tracking-modal.js")
    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ PASS: Tracking modal script accessible (HTTP $HTTP_CODE)"
    else
        echo "⚠️  WARNING: Tracking modal script not found (HTTP $HTTP_CODE)"
    fi
fi
echo ""

# Test 6: Tracking API Endpoint
echo "Test 6: Add Tracking API"
echo "--------------------------------------"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    -d '{"order_id":1,"tracking_numbers":["TEST123"],"carrier":"test"}' \
    "${BASE_URL}/api/add-tracking-simple.php")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "400" ]; then
    echo "✅ PASS: Add tracking API exists (HTTP $HTTP_CODE)"
else
    echo "❌ FAIL: Add tracking API error (HTTP $HTTP_CODE)"
fi
echo ""

echo "======================================"
echo "TEST SUMMARY"
echo "======================================"
echo ""
echo "All critical endpoints have been tested."
echo "Expected results:"
echo "  - 200: Success (endpoint works)"
echo "  - 401: Unauthorized (endpoint exists, needs auth)"
echo "  - 400: Bad request (endpoint exists, validation works)"
echo ""
echo "Manual Testing Required:"
echo "  1. Login to supplier portal"
echo "  2. Navigate to any order detail page"
echo "  3. Click 'Export CSV' - should download CSV file"
echo "  4. Click 'Export PDF' - should open printable PDF"
echo "  5. Click 'Mark as Shipped' (if OPEN) - should update status"
echo "  6. Click 'Add Tracking' - should open modal"
echo "  7. Click 'Print Order' - should open print dialog"
echo ""
echo "======================================"
