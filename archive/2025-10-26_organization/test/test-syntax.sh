#!/bin/bash
# Quick syntax check for all dashboard APIs

echo "=========================================="
echo "SYNTAX CHECK - Dashboard APIs"
echo "=========================================="
echo ""

all_ok=true

for file in dashboard-stats.php dashboard-orders-table.php dashboard-stock-alerts.php dashboard-charts.php; do
    echo "Checking: api/$file"
    result=$(php -l "api/$file" 2>&1)
    if echo "$result" | grep -q "No syntax errors"; then
        echo "✅ Syntax OK"
    else
        echo "❌ Syntax Error!"
        echo "$result"
        all_ok=false
    fi
    echo ""
done

echo "Checking: bootstrap.php"
result=$(php -l bootstrap.php 2>&1)
if echo "$result" | grep -q "No syntax errors"; then
    echo "✅ Syntax OK"
else
    echo "❌ Syntax Error!"
    echo "$result"
    all_ok=false
fi
echo ""

echo "=========================================="
if [ "$all_ok" = true ]; then
    echo "✅ All syntax checks passed!"
    echo "Ready to run: php test-dashboard-api.php"
else
    echo "❌ Syntax errors found - fix before testing"
fi
echo "=========================================="

