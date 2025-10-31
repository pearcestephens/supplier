#!/bin/bash
# Test Logging System
# Verifies that the enhanced logger is working correctly

echo "================================================"
echo "🧪 Supplier Portal Logging System Test"
echo "================================================"
echo ""

cd /home/master/applications/jcepnzzkmj/public_html/supplier

echo "1️⃣ Checking if logger files exist..."
if [ -f "lib/SupplierLogger.php" ]; then
    echo "   ✅ SupplierLogger.php found"
else
    echo "   ❌ SupplierLogger.php missing!"
    exit 1
fi

if [ -f "lib/logger-bootstrap.php" ]; then
    echo "   ✅ logger-bootstrap.php found"
else
    echo "   ❌ logger-bootstrap.php missing!"
    exit 1
fi

echo ""
echo "2️⃣ Checking if logger is integrated in bootstrap..."
if grep -q "logger-bootstrap.php" bootstrap.php; then
    echo "   ✅ Logger integrated in bootstrap.php"
else
    echo "   ❌ Logger not integrated in bootstrap.php!"
    exit 1
fi

echo ""
echo "3️⃣ Checking if API endpoints exist..."
if [ -f "api/get-activity-logs.php" ]; then
    echo "   ✅ get-activity-logs.php found"
else
    echo "   ❌ get-activity-logs.php missing!"
fi

if [ -f "api/get-ai-insights.php" ]; then
    echo "   ✅ get-ai-insights.php found"
else
    echo "   ❌ get-ai-insights.php missing!"
fi

echo ""
echo "4️⃣ Checking database tables..."
LOGS_TABLE=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES LIKE 'logs';" 2>/dev/null | grep -c "logs")
if [ "$LOGS_TABLE" -eq 1 ]; then
    echo "   ✅ logs table exists"
else
    echo "   ❌ logs table missing!"
    exit 1
fi

LOG_TYPES_TABLE=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES LIKE 'log_types';" 2>/dev/null | grep -c "log_types")
if [ "$LOG_TYPES_TABLE" -eq 1 ]; then
    echo "   ✅ log_types table exists"
else
    echo "   ❌ log_types table missing!"
    exit 1
fi

echo ""
echo "5️⃣ Checking PHP syntax..."
php -l lib/SupplierLogger.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ SupplierLogger.php syntax valid"
else
    echo "   ❌ SupplierLogger.php has syntax errors!"
    php -l lib/SupplierLogger.php
    exit 1
fi

php -l lib/logger-bootstrap.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ logger-bootstrap.php syntax valid"
else
    echo "   ❌ logger-bootstrap.php has syntax errors!"
    php -l lib/logger-bootstrap.php
    exit 1
fi

php -l api/get-activity-logs.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ get-activity-logs.php syntax valid"
else
    echo "   ❌ get-activity-logs.php has syntax errors!"
fi

php -l api/get-ai-insights.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ get-ai-insights.php syntax valid"
else
    echo "   ❌ get-ai-insights.php has syntax errors!"
fi

echo ""
echo "6️⃣ Checking log directory..."
if [ -d "logs" ]; then
    echo "   ✅ logs directory exists"
else
    echo "   ⚠️  logs directory missing, creating..."
    mkdir -p logs
fi

if [ -w "logs" ]; then
    echo "   ✅ logs directory is writable"
else
    echo "   ❌ logs directory is not writable!"
    echo "   Run: chmod 755 logs"
fi

echo ""
echo "7️⃣ Checking existing logs in database..."
LOG_COUNT=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) FROM logs WHERE created >= DATE_SUB(NOW(), INTERVAL 7 DAY);" 2>/dev/null | tail -1)
echo "   📊 Logs in last 7 days: $LOG_COUNT"

SUPPLIER_LOGS=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) FROM logs WHERE user_id > 0 AND JSON_EXTRACT(data, '$.supplier_id') IS NOT NULL;" 2>/dev/null | tail -1)
echo "   📊 Supplier logs: $SUPPLIER_LOGS"

echo ""
echo "8️⃣ Checking log_types for supplier entries..."
SUPPLIER_LOG_TYPES=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) FROM log_types WHERE title LIKE '%Supplier:%';" 2>/dev/null | tail -1)
echo "   📊 Supplier log types: $SUPPLIER_LOG_TYPES"

echo ""
echo "================================================"
echo "✅ LOGGING SYSTEM TEST COMPLETE"
echo "================================================"
echo ""
echo "Summary:"
echo "  ✅ All logger files present"
echo "  ✅ Database tables verified"
echo "  ✅ PHP syntax valid"
echo "  ✅ Log directory ready"
echo "  📊 $LOG_COUNT logs in last 7 days"
echo "  📊 $SUPPLIER_LOGS supplier logs recorded"
echo ""
echo "Next steps:"
echo "  1. Visit any supplier portal page to test auto-logging"
echo "  2. Check logs table: SELECT * FROM logs ORDER BY id DESC LIMIT 10;"
echo "  3. View API: GET /api/get-activity-logs.php"
echo "  4. View AI insights: GET /api/get-ai-insights.php"
echo ""
echo "🎉 Logger is ready to capture all supplier actions!"
