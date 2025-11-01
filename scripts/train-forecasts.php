<?php
/**
 * Daily ML Training Script
 *
 * Runs every night at 2 AM to:
 * - Train ML models for all active suppliers
 * - Generate 30-day forecasts (4 weeks)
 * - Store predictions in ml_predictions table
 * - Log results for monitoring
 *
 * Usage: php scripts/train-forecasts.php
 * Cron:  0 2 * * * cd /path/to/supplier && php scripts/train-forecasts.php >> logs/ml-training.log 2>&1
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Forecasting.php';

$startTime = microtime(true);
$logFile = __DIR__ . '/../logs/ml-training.log';

function log_message($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

log_message("=== ML Training Started ===");

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();

    // Get all active suppliers with historical data
    $result = $db->query("
        SELECT DISTINCT t.supplier_id,
               COUNT(DISTINCT t.id) as total_orders,
               MIN(t.created_at) as first_order_date,
               MAX(t.created_at) as last_order_date
        FROM vend_consignments t
        WHERE t.supplier_id IS NOT NULL
          AND t.supplier_id != ''
          AND t.transfer_category = 'PURCHASE_ORDER'
          AND t.deleted_at IS NULL
          AND t.created_at >= DATE_SUB(NOW(), INTERVAL 52 WEEK)
        GROUP BY t.supplier_id
        HAVING COUNT(DISTINCT t.id) >= 12
        ORDER BY t.supplier_id
    ");

    $suppliers = $result->fetch_all(MYSQLI_ASSOC);
    log_message("Found " . count($suppliers) . " suppliers with sufficient data (12+ orders)");

    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    foreach ($suppliers as $supplier) {
        $supplierID = $supplier['supplier_id'];
        $totalOrders = $supplier['total_orders'];

        try {
            log_message("Processing supplier: {$supplierID} ({$totalOrders} orders)");

            // Fetch 52 weeks of historical data
            $historicalQuery = "
                SELECT
                    YEARWEEK(t.created_at, 1) as year_week,
                    DATE(DATE_SUB(t.created_at, INTERVAL WEEKDAY(t.created_at) DAY)) as week_start,
                    SUM(ti.quantity_sent) as total_units,
                    SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
                    COUNT(DISTINCT t.id) as order_count
                FROM vend_consignments t
                LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
                WHERE t.supplier_id = ?
                  AND t.transfer_category = 'PURCHASE_ORDER'
                  AND t.deleted_at IS NULL
                  AND t.created_at >= DATE_SUB(NOW(), INTERVAL 52 WEEK)
                GROUP BY YEARWEEK(t.created_at, 1), week_start
                ORDER BY week_start ASC
            ";

            $stmt = $db->prepare($historicalQuery);
            $stmt->bind_param('s', $supplierID);
            $stmt->execute();
            $result = $stmt->get_result();
            $historicalData = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (count($historicalData) < 12) {
                log_message("  ⚠️  Skipping (insufficient weeks: " . count($historicalData) . ")");
                $skipCount++;
                continue;
            }

            // Extract data arrays
            $revenueData = array_map('floatval', array_column($historicalData, 'total_revenue'));
            $unitsData = array_map('floatval', array_column($historicalData, 'total_units'));
            $ordersData = array_map('floatval', array_column($historicalData, 'order_count'));

            // Train models and generate 4-week predictions
            $revenueForecast = Forecasting::linearRegression($revenueData, 4);
            $unitsForecast = Forecasting::linearRegression($unitsData, 4);
            $ordersForecast = Forecasting::linearRegression($ordersData, 4);

            // Calculate statistics for confidence intervals
            $revenueStdDev = Forecasting::calculateStandardDeviation($revenueData);
            $unitsStdDev = Forecasting::calculateStandardDeviation($unitsData);
            $ordersStdDev = Forecasting::calculateStandardDeviation($ordersData);

            $revenueMean = array_sum($revenueData) / count($revenueData);
            $unitsMean = array_sum($unitsData) / count($unitsData);
            $ordersMean = array_sum($ordersData) / count($ordersData);

            // Data quality score (based on completeness: weeks/52)
            $dataQuality = min(1.0, count($historicalData) / 52);

            // Clear old predictions for this supplier
            $deleteStmt = $db->prepare("DELETE FROM ml_predictions WHERE supplier_id = ? AND prediction_date > NOW()");
            $deleteStmt->bind_param('s', $supplierID);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Prepare insert statement
            $insertStmt = $db->prepare("
                INSERT INTO ml_predictions
                (supplier_id, prediction_date, metric_type, predicted_value,
                 confidence_lower, confidence_upper, confidence_score,
                 anomaly_threshold_high, anomaly_threshold_low, data_quality_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Store predictions for next 4 weeks
            for ($week = 1; $week <= 4; $week++) {
                $predictionDate = date('Y-m-d', strtotime("+{$week} weeks"));

                // Revenue prediction
                $revenueValue = $revenueForecast[$week - 1] ?? 0;
                $metricType = 'revenue';
                $insertStmt->bind_param(
                    'sssddddddd',
                    $supplierID,
                    $predictionDate,
                    $metricType,
                    $revenueValue,
                    $revenueValue - $revenueStdDev,
                    $revenueValue + $revenueStdDev,
                    $dataQuality,
                    $revenueMean + (2 * $revenueStdDev),
                    max(0, $revenueMean - (2 * $revenueStdDev)),
                    $dataQuality
                );
                $insertStmt->execute();

                // Units prediction
                $unitsValue = $unitsForecast[$week - 1] ?? 0;
                $metricType = 'units';
                $insertStmt->bind_param(
                    'sssddddddd',
                    $supplierID,
                    $predictionDate,
                    $metricType,
                    $unitsValue,
                    $unitsValue - $unitsStdDev,
                    $unitsValue + $unitsStdDev,
                    $dataQuality,
                    $unitsMean + (2 * $unitsStdDev),
                    max(0, $unitsMean - (2 * $unitsStdDev)),
                    $dataQuality
                );
                $insertStmt->execute();

                // Orders prediction
                $ordersValue = $ordersForecast[$week - 1] ?? 0;
                $metricType = 'orders';
                $insertStmt->bind_param(
                    'sssddddddd',
                    $supplierID,
                    $predictionDate,
                    $metricType,
                    $ordersValue,
                    $ordersValue - $ordersStdDev,
                    $ordersValue + $ordersStdDev,
                    $dataQuality,
                    $ordersMean + (2 * $ordersStdDev),
                    max(0, $ordersMean - (2 * $ordersStdDev)),
                    $dataQuality
                );
                $insertStmt->execute();
            }

            $insertStmt->close();
            log_message("  ✅ Predictions stored (12 rows: 4 weeks × 3 metrics)");
            $successCount++;

        } catch (Exception $e) {
            log_message("  ❌ Error for {$supplierID}: " . $e->getMessage());
            $errorCount++;
        }
    }

    $duration = round(microtime(true) - $startTime, 2);
    log_message("=== ML Training Completed in {$duration}s ===");
    log_message("Summary: {$successCount} successful, {$skipCount} skipped, {$errorCount} errors");

    exit(0);

} catch (Exception $e) {
    log_message("❌ FATAL ERROR: " . $e->getMessage());
    log_message($e->getTraceAsString());
    exit(1);
}
