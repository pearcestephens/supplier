#!/bin/bash
# API Endpoint Testing Script
# Tests all 5 newly created API endpoints

echo "=========================================="
echo "🧪 API ENDPOINT TESTING"
echo "=========================================="
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/supplier/api"
SESSION_COOKIE="" # Will need actual session cookie

echo "Testing 5 API Endpoints..."
echo ""

# Test 1: Search Orders
echo "1️⃣ Testing search-orders.php..."
echo "   URL: ${BASE_URL}/search-orders.php?q=PO"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" "${BASE_URL}/search-orders.php?q=PO"
echo ""

# Test 2: Get Order Detail
echo "2️⃣ Testing get-order-detail.php..."
echo "   URL: ${BASE_URL}/get-order-detail.php?id=1"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" "${BASE_URL}/get-order-detail.php?id=1"
echo ""

# Test 3: Get Warranty Detail
echo "3️⃣ Testing get-warranty-detail.php..."
echo "   URL: ${BASE_URL}/get-warranty-detail.php?id=1"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" "${BASE_URL}/get-warranty-detail.php?id=1"
echo ""

# Test 4: Update Account (POST)
echo "4️⃣ Testing update-account.php..."
echo "   URL: ${BASE_URL}/update-account.php"
echo "   (Skipping POST test - requires CSRF token)"
echo ""

# Test 5: Search Products
echo "5️⃣ Testing search-products.php..."
echo "   URL: ${BASE_URL}/search-products.php?q=vape"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" "${BASE_URL}/search-products.php?q=vape"
echo ""

echo "=========================================="
echo "✅ API Endpoint Test Complete"
echo "=========================================="
echo ""
echo "⚠️  Note: Full testing requires authenticated session"
echo "📝 To test manually:"
echo "   1. Log in to supplier portal"
echo "   2. Open browser console (F12)"
echo "   3. Run fetch() calls to each endpoint"
echo ""
