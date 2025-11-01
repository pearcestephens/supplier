#!/bin/bash
# Quick Setup Script for ML Cron Job
# Run this when you're ready to enable daily ML training at 2 AM

echo "🚀 ML Cron Job Setup Script"
echo "==========================="
echo ""

# Step 1: Create ml_predictions table
echo "📊 Step 1: Creating ml_predictions table..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < migrations/009_ml_predictions_table.sql

if [ $? -eq 0 ]; then
    echo "✅ Table created successfully!"
else
    echo "❌ Error creating table. Check error above."
    exit 1
fi

echo ""

# Step 2: Test script manually
echo "🧪 Step 2: Testing training script manually..."
php scripts/train-forecasts.php

if [ $? -eq 0 ]; then
    echo "✅ Training script works!"
else
    echo "❌ Error running script. Check logs/ml-training.log"
    exit 1
fi

echo ""

# Step 3: Add to crontab
echo "⏰ Step 3: Adding to crontab (runs at 2 AM daily)..."
CRON_JOB="0 2 * * * cd $(pwd) && php scripts/train-forecasts.php >> logs/ml-training.log 2>&1"

# Check if cron job already exists
(crontab -l 2>/dev/null | grep -q "train-forecasts.php") && {
    echo "⚠️  Cron job already exists. Skipping..."
} || {
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job added!"
}

echo ""

# Step 4: Verify
echo "🔍 Step 4: Verifying setup..."
echo ""
echo "Crontab entry:"
crontab -l | grep "train-forecasts"
echo ""

echo "Database check:"
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) as total_predictions FROM ml_predictions;" 2>/dev/null
echo ""

echo "=========================================="
echo "✅ Setup Complete!"
echo "=========================================="
echo ""
echo "📊 Predictions stored: Check above count"
echo "⏰ Next run: Tomorrow at 2:00 AM"
echo "📝 Logs: logs/ml-training.log"
echo ""
echo "To test again manually:"
echo "  php scripts/train-forecasts.php"
echo ""
echo "To view cron jobs:"
echo "  crontab -l"
echo ""
echo "To monitor logs:"
echo "  tail -f logs/ml-training.log"
echo ""
