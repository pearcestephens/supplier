#!/bin/bash
# Test API endpoints for Reports system

echo "🧪 Testing Reports API Endpoints..."
echo ""

BASE_URL="https://staff.vapeshed.co.nz/supplier"

# Test 1: Sales Summary API
echo "1️⃣ Testing reports-sales-summary.php..."
curl -s -o /dev/null -w "Status: %{http_code}\n" "${BASE_URL}/api/reports-sales-summary.php?start_date=2025-01-01&end_date=2025-11-01"
echo ""

# Test 2: Product Performance API
echo "2️⃣ Testing reports-product-performance.php..."
curl -s -o /dev/null -w "Status: %{http_code}\n" "${BASE_URL}/api/reports-product-performance.php?limit=10"
echo ""

# Test 3: Forecast API
echo "3️⃣ Testing reports-forecast.php..."
curl -s -o /dev/null -w "Status: %{http_code}\n" "${BASE_URL}/api/reports-forecast.php?weeks=8"
echo ""

# Test 4: Reports main page
echo "4️⃣ Testing reports.php main page..."
curl -s -o /dev/null -w "Status: %{http_code}\n" "${BASE_URL}/reports.php"
echo ""

echo "✅ Test complete!"
echo ""
echo "Expected results:"
echo "  200 = Success"
echo "  302 = Redirect (might need login)"
echo "  404 = File not found"
echo "  500 = Server error"
