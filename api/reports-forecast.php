<?php
/**
 * Reports API - ML Sales Forecast
 * 
 * Provides ML-powered sales forecasting using:
 * - Linear regression
 * - Confidence intervals (±1σ, ±2σ)
 * - Anomaly detection
 * - 4-8 week predictions
 * 
 * @package SupplierPortal\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/Forecasting.php';

// Security check
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supplierID = Auth::getSupplierId();
$db = db();

try {
    // Get parameters
    $forecastWeeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 8;
    $forecastWeeks = max(4, min(12, $forecastWeeks)); // Clamp between 4-12 weeks
    $productId = $_GET['product_id'] ?? null;
    
    // Build query based on whether we're forecasting for a specific product
    if ($productId) {
        // Product-specific forecast
        $query = "
            SELECT 
                YEARWEEK(t.created_at, 1) as year_week,
                DATE(DATE_SUB(t.created_at, INTERVAL WEEKDAY(t.created_at) DAY)) as week_start,
                SUM(ti.quantity_sent) as total_units,
                SUM(ti.quantity_sent * ti.unit_cost) as total_revenue
            FROM vend_consignments t
            JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
            WHERE t.supplier_id = ?
              AND ti.product_id = ?
              AND t.transfer_category = 'PURCHASE_ORDER'
              AND t.deleted_at IS NULL
              AND t.created_at >= DATE_SUB(NOW(), INTERVAL 52 WEEK)
            GROUP BY YEARWEEK(t.created_at, 1), week_start
            ORDER BY week_start ASC
        ";
        $stmt = $db->prepare($query);
        $stmt->bind_param('ss', $supplierID, $productId);
    } else {
        // Overall supplier forecast
        $query = "
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
        $stmt = $db->prepare($query);
        $stmt->bind_param('s', $supplierID);
    }
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $db->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historicalData = [];
    $revenueData = [];
    $unitsData = [];
    $weekLabels = [];
    
    while ($row = $result->fetch_assoc()) {
        $weekLabels[] = $row['week_start'];
        $revenueData[] = (float)$row['total_revenue'];
        $unitsData[] = (int)$row['total_units'];
    }
    
    $stmt->close();
    
    // Need at least 8 weeks of data for meaningful forecast
    if (count($revenueData) < 8) {
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient historical data',
            'message' => 'At least 8 weeks of historical data required for forecasting',
            'weeks_available' => count($revenueData)
        ]);
        exit;
    }
    
    // Generate forecasts for revenue
    $revenueForecast = Forecasting::generateForecast($revenueData, $forecastWeeks);
    
    // Generate forecasts for units
    $unitsForecast = Forecasting::generateForecast($unitsData, $forecastWeeks);
    
    // Generate future week labels
    $lastWeekDate = end($weekLabels);
    $futureWeeks = [];
    for ($i = 1; $i <= $forecastWeeks; $i++) {
        $futureWeeks[] = date('Y-m-d', strtotime($lastWeekDate . " +{$i} week"));
    }
    
    // Calculate forecast accuracy using last N weeks
    $testSize = min(8, (int)(count($revenueData) * 0.2)); // Use 20% for testing
    $trainSize = count($revenueData) - $testSize;
    
    if ($trainSize > 8) {
        $trainData = array_slice($revenueData, 0, $trainSize);
        $testData = array_slice($revenueData, $trainSize);
        
        $testForecast = Forecasting::linearRegression($trainData, $testSize);
        $mape = Forecasting::calculateMAPE($testData, $testForecast['predictions']);
        $accuracy = max(0, 100 - $mape);
    } else {
        $mape = 0;
        $accuracy = 0;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'forecast_weeks' => $forecastWeeks,
        'historical_weeks' => count($revenueData),
        'product_id' => $productId,
        
        'revenue' => [
            'historical' => $revenueData,
            'predictions' => $revenueForecast['predictions'],
            'confidence_1sigma' => $revenueForecast['confidence_1sigma'],
            'confidence_2sigma' => $revenueForecast['confidence_2sigma'],
            'quality' => $revenueForecast['quality'],
            'anomalies' => $revenueForecast['anomalies']
        ],
        
        'units' => [
            'historical' => $unitsData,
            'predictions' => $unitsForecast['predictions'],
            'confidence_1sigma' => $unitsForecast['confidence_1sigma'],
            'confidence_2sigma' => $unitsForecast['confidence_2sigma']
        ],
        
        'weeks' => [
            'historical' => $weekLabels,
            'future' => $futureWeeks
        ],
        
        'accuracy' => [
            'mape' => round($mape, 2),
            'accuracy_percent' => round($accuracy, 2),
            'r_squared' => round($revenueForecast['quality']['r_squared'], 4),
            'trend' => $revenueForecast['quality']['trend'],
            'test_size' => $testSize ?? 0
        ],
        
        'summary' => [
            'avg_historical_revenue' => count($revenueData) > 0 
                ? array_sum($revenueData) / count($revenueData) 
                : 0,
            'avg_forecast_revenue' => count($revenueForecast['predictions']) > 0
                ? array_sum($revenueForecast['predictions']) / count($revenueForecast['predictions'])
                : 0,
            'forecast_total' => array_sum($revenueForecast['predictions']),
            'std_dev' => $revenueForecast['std_dev'] ?? 0
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Forecast generation failed',
        'message' => DEBUG_MODE ? $e->getMessage() : 'An error occurred'
    ]);
}
