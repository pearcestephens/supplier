<?php
/**
 * ML Forecasting Engine
 * 
 * Advanced forecasting algorithms for supplier sales predictions:
 * - Simple Moving Average (SMA)
 * - Exponential Moving Average (EMA)
 * - Weighted Moving Average (WMA)
 * - Linear Regression with trend analysis
 * - Seasonal decomposition
 * - Confidence intervals (±1σ, ±2σ)
 * - Anomaly detection (Z-score method)
 * 
 * @package SupplierPortal\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

class Forecasting
{
    /**
     * Calculate Simple Moving Average
     * 
     * @param array $data Historical data points [value1, value2, ...]
     * @param int $period Window size for averaging
     * @return array Smoothed values
     */
    public static function simpleMovingAverage(array $data, int $period = 4): array
    {
        if (empty($data) || $period <= 0 || $period > count($data)) {
            return [];
        }

        $sma = [];
        for ($i = $period - 1; $i < count($data); $i++) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $sum += $data[$i - $j];
            }
            $sma[] = $sum / $period;
        }
        
        return $sma;
    }

    /**
     * Calculate Exponential Moving Average
     * 
     * @param array $data Historical data points
     * @param float $alpha Smoothing factor (0-1, default 0.3)
     * @return array Smoothed values
     */
    public static function exponentialMovingAverage(array $data, float $alpha = 0.3): array
    {
        if (empty($data) || $alpha <= 0 || $alpha > 1) {
            return [];
        }

        $ema = [$data[0]];
        for ($i = 1; $i < count($data); $i++) {
            $ema[] = $alpha * $data[$i] + (1 - $alpha) * $ema[$i - 1];
        }
        
        return $ema;
    }

    /**
     * Calculate Weighted Moving Average
     * 
     * @param array $data Historical data points
     * @param int $period Window size
     * @return array Smoothed values
     */
    public static function weightedMovingAverage(array $data, int $period = 4): array
    {
        if (empty($data) || $period <= 0 || $period > count($data)) {
            return [];
        }

        $wma = [];
        $denominator = ($period * ($period + 1)) / 2;
        
        for ($i = $period - 1; $i < count($data); $i++) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $weight = $period - $j;
                $sum += $data[$i - $j] * $weight;
            }
            $wma[] = $sum / $denominator;
        }
        
        return $wma;
    }

    /**
     * Linear Regression Forecast
     * 
     * @param array $data Historical data points
     * @param int $forecastPeriods Number of periods to forecast
     * @return array ['slope' => float, 'intercept' => float, 'predictions' => array]
     */
    public static function linearRegression(array $data, int $forecastPeriods = 8): array
    {
        $n = count($data);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => 0, 'predictions' => []];
        }

        // Calculate means
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        // Calculate slope and intercept
        $numerator = $sumXY - ($n * $meanX * $meanY);
        $denominator = $sumX2 - ($n * $meanX * $meanX);
        
        $slope = ($denominator != 0) ? $numerator / $denominator : 0;
        $intercept = $meanY - ($slope * $meanX);

        // Generate predictions
        $predictions = [];
        for ($i = $n + 1; $i <= $n + $forecastPeriods; $i++) {
            $predictions[] = max(0, $slope * $i + $intercept); // Ensure non-negative
        }

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'predictions' => $predictions,
            'r_squared' => self::calculateRSquared($data, $slope, $intercept)
        ];
    }

    /**
     * Calculate R-squared for regression quality
     * 
     * @param array $data Historical data
     * @param float $slope Regression slope
     * @param float $intercept Regression intercept
     * @return float R-squared value (0-1)
     */
    private static function calculateRSquared(array $data, float $slope, float $intercept): float
    {
        $n = count($data);
        if ($n < 2) return 0;

        $meanY = array_sum($data) / $n;
        $ssTotal = 0;
        $ssResidual = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $yPredicted = $slope * $x + $intercept;
            $ssTotal += pow($data[$i] - $meanY, 2);
            $ssResidual += pow($data[$i] - $yPredicted, 2);
        }

        return ($ssTotal > 0) ? 1 - ($ssResidual / $ssTotal) : 0;
    }

    /**
     * Seasonal Decomposition (Additive Model)
     * 
     * @param array $data Historical data
     * @param int $seasonLength Length of one season (e.g., 4 for quarters, 52 for weeks)
     * @return array ['trend' => array, 'seasonal' => array, 'residual' => array]
     */
    public static function seasonalDecomposition(array $data, int $seasonLength = 4): array
    {
        $n = count($data);
        if ($n < $seasonLength * 2) {
            return ['trend' => [], 'seasonal' => [], 'residual' => []];
        }

        // Calculate trend using centered moving average
        $trend = self::centeredMovingAverage($data, $seasonLength);
        
        // Calculate seasonal indices
        $detrended = [];
        for ($i = 0; $i < $n; $i++) {
            if (isset($trend[$i]) && $trend[$i] !== null) {
                $detrended[$i] = $data[$i] - $trend[$i];
            }
        }

        // Average seasonal component for each period
        $seasonal = array_fill(0, $n, 0);
        $seasonalAverages = array_fill(0, $seasonLength, []);
        
        foreach ($detrended as $i => $value) {
            $seasonIndex = $i % $seasonLength;
            $seasonalAverages[$seasonIndex][] = $value;
        }

        $seasonalPattern = [];
        foreach ($seasonalAverages as $values) {
            $seasonalPattern[] = !empty($values) ? array_sum($values) / count($values) : 0;
        }

        // Apply seasonal pattern
        for ($i = 0; $i < $n; $i++) {
            $seasonal[$i] = $seasonalPattern[$i % $seasonLength];
        }

        // Calculate residuals
        $residual = [];
        for ($i = 0; $i < $n; $i++) {
            if (isset($trend[$i]) && $trend[$i] !== null) {
                $residual[$i] = $data[$i] - $trend[$i] - $seasonal[$i];
            }
        }

        return [
            'trend' => $trend,
            'seasonal' => $seasonal,
            'residual' => $residual,
            'pattern' => $seasonalPattern
        ];
    }

    /**
     * Centered Moving Average for trend calculation
     * 
     * @param array $data Historical data
     * @param int $period Window size
     * @return array Trend values (with nulls at edges)
     */
    private static function centeredMovingAverage(array $data, int $period): array
    {
        $n = count($data);
        $trend = array_fill(0, $n, null);
        $halfPeriod = floor($period / 2);

        for ($i = $halfPeriod; $i < $n - $halfPeriod; $i++) {
            $sum = 0;
            for ($j = -$halfPeriod; $j <= $halfPeriod; $j++) {
                $sum += $data[$i + $j];
            }
            $trend[$i] = $sum / $period;
        }

        return $trend;
    }

    /**
     * Calculate Confidence Intervals
     * 
     * @param array $predictions Forecasted values
     * @param array $historicalData Historical data for variance calculation
     * @param float $zScore Z-score for confidence level (1=68%, 2=95%, 3=99.7%)
     * @return array ['lower' => array, 'upper' => array, 'std_dev' => float]
     */
    public static function confidenceIntervals(array $predictions, array $historicalData, float $zScore = 1.0): array
    {
        if (empty($predictions) || empty($historicalData)) {
            return ['lower' => [], 'upper' => [], 'std_dev' => 0];
        }

        // Calculate standard deviation of historical data
        $mean = array_sum($historicalData) / count($historicalData);
        $variance = 0;
        foreach ($historicalData as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / count($historicalData));

        // Calculate confidence intervals
        $lower = [];
        $upper = [];
        foreach ($predictions as $pred) {
            $lower[] = max(0, $pred - ($zScore * $stdDev)); // Non-negative
            $upper[] = $pred + ($zScore * $stdDev);
        }

        return [
            'lower' => $lower,
            'upper' => $upper,
            'std_dev' => $stdDev
        ];
    }

    /**
     * Detect Anomalies using Z-score method
     * 
     * @param array $data Data points to analyze
     * @param float $threshold Z-score threshold (default 2.0 = 95% confidence)
     * @return array Indices of anomalies and their z-scores
     */
    public static function detectAnomalies(array $data, float $threshold = 2.0): array
    {
        if (count($data) < 3) {
            return [];
        }

        $mean = array_sum($data) / count($data);
        $variance = 0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / count($data));

        $anomalies = [];
        if ($stdDev > 0) {
            foreach ($data as $index => $value) {
                $zScore = abs(($value - $mean) / $stdDev);
                if ($zScore > $threshold) {
                    $anomalies[] = [
                        'index' => $index,
                        'value' => $value,
                        'z_score' => $zScore,
                        'severity' => $zScore > 3 ? 'high' : 'medium'
                    ];
                }
            }
        }

        return $anomalies;
    }

    /**
     * Calculate Sales Velocity (units per week)
     * 
     * @param int $totalUnits Total units sold
     * @param int $totalDays Total days in period
     * @return float Units per week
     */
    public static function salesVelocity(int $totalUnits, int $totalDays): float
    {
        if ($totalDays <= 0) return 0;
        return ($totalUnits / $totalDays) * 7; // Convert to per week
    }

    /**
     * Calculate Growth Rate
     * 
     * @param float $currentValue Current period value
     * @param float $previousValue Previous period value
     * @return float Growth rate as percentage
     */
    public static function growthRate(float $currentValue, float $previousValue): float
    {
        if ($previousValue == 0) return 0;
        return (($currentValue - $previousValue) / $previousValue) * 100;
    }

    /**
     * Classify Product Lifecycle Stage
     * 
     * @param array $weeklyData Weekly sales data (recent weeks)
     * @return string Stage: 'growth', 'mature', 'decline', 'new'
     */
    public static function classifyLifecycle(array $weeklyData): string
    {
        $n = count($weeklyData);
        if ($n < 4) return 'new';

        // Calculate trend
        $regression = self::linearRegression($weeklyData, 0);
        $slope = $regression['slope'];
        $avgSales = array_sum($weeklyData) / $n;

        // Recent weeks (last 25%)
        $recentWeeks = array_slice($weeklyData, -max(1, (int)($n * 0.25)));
        $recentAvg = array_sum($recentWeeks) / count($recentWeeks);

        // Classification logic
        if ($avgSales < 1) {
            return 'new';
        } elseif ($slope > ($avgSales * 0.1)) {
            return 'growth';
        } elseif ($slope < -($avgSales * 0.1)) {
            return 'decline';
        } else {
            return 'mature';
        }
    }

    /**
     * Calculate MAPE (Mean Absolute Percentage Error)
     * For measuring forecast accuracy
     * 
     * @param array $actual Actual values
     * @param array $predicted Predicted values
     * @return float MAPE percentage (lower is better)
     */
    public static function calculateMAPE(array $actual, array $predicted): float
    {
        if (count($actual) !== count($predicted) || empty($actual)) {
            return 0;
        }

        $sum = 0;
        $count = 0;
        for ($i = 0; $i < count($actual); $i++) {
            if ($actual[$i] != 0) {
                $sum += abs(($actual[$i] - $predicted[$i]) / $actual[$i]);
                $count++;
            }
        }

        return $count > 0 ? ($sum / $count) * 100 : 0;
    }

    /**
     * Generate Combined Forecast using multiple methods
     * 
     * @param array $historicalData Historical data points
     * @param int $forecastPeriods Number of periods to forecast
     * @return array Complete forecast with multiple methods and confidence intervals
     */
    public static function generateForecast(array $historicalData, int $forecastPeriods = 8): array
    {
        if (empty($historicalData)) {
            return [
                'method' => 'none',
                'predictions' => [],
                'confidence_1sigma' => ['lower' => [], 'upper' => []],
                'confidence_2sigma' => ['lower' => [], 'upper' => []]
            ];
        }

        // Use linear regression as primary method
        $regression = self::linearRegression($historicalData, $forecastPeriods);
        $predictions = $regression['predictions'];

        // Calculate confidence intervals
        $ci1 = self::confidenceIntervals($predictions, $historicalData, 1.0);
        $ci2 = self::confidenceIntervals($predictions, $historicalData, 2.0);

        // Detect anomalies in historical data
        $anomalies = self::detectAnomalies($historicalData);

        return [
            'method' => 'linear_regression',
            'predictions' => $predictions,
            'confidence_1sigma' => [
                'lower' => $ci1['lower'],
                'upper' => $ci1['upper']
            ],
            'confidence_2sigma' => [
                'lower' => $ci2['lower'],
                'upper' => $ci2['upper']
            ],
            'quality' => [
                'r_squared' => $regression['r_squared'],
                'slope' => $regression['slope'],
                'trend' => $regression['slope'] > 0 ? 'increasing' : ($regression['slope'] < 0 ? 'decreasing' : 'stable')
            ],
            'anomalies' => $anomalies,
            'std_dev' => $ci1['std_dev']
        ];
    }
}
