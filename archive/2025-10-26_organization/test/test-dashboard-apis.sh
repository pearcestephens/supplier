#!/bin/bash
# Dashboard API Test Suite
# Run this to test all dashboard endpoints as they're built

echo "==================================="
echo "DASHBOARD API TEST SUITE"
echo "==================================="
echo ""

# Test 1: Dashboard Stats
echo "TEST 1: Dashboard Stats API"
echo "URL: /supplier/api/dashboard-stats.php"
echo "-----------------------------------"
curl -s "https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php" | head -20
echo ""
echo "Expected: JSON with total_orders, active_products, pending_claims, etc."
echo "Status: Check if success=true and data object exists"
echo ""
echo "==================================="
