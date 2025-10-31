<?php
/**
 * Supplier Portal - ML Performance Tracking System
 * 
 * Adaptive machine learning system that learns query performance patterns
 * and automatically optimizes cache, pagination, and resource allocation
 * 
 * @package CIS\Supplier\Functions
 * @version 3.0.0
 * @author The Vape Shed
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// ML PERFORMANCE MONITORING
// ============================================================================

/**
 * Track query performance metrics
 * 
 * @param string $endpoint Current endpoint (dashboard, orders, etc)
 * @param string $query SQL query executed
 * @param int $executionTimeMs Execution time in milliseconds
 * @param int $resultCount Number of rows returned
 * @return void
 */
function ml_track_performance(string $endpoint, string $query, int $executionTimeMs, int $resultCount = 0): void
{
    if (!ML_ENABLED) {
        return;
    }
    
    // Sample rate check (to reduce overhead)
    if (ML_SAMPLE_RATE < 1.0 && (mt_rand() / mt_getrandmax()) > ML_SAMPLE_RATE) {
        return;
    }
    
    // Normalize query (remove values, keep structure)
    $queryHash = md5(normalize_query($query));
    
    // Calculate query complexity
    $complexity = calculate_query_complexity($query);
    
    // Get server metrics
    $serverLoad = sys_getloadavg()[0] ?? null;
    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024); // MB
    
    // Insert metrics
    $insertQuery = "INSERT INTO " . TABLE_PERFORMANCE_METRICS . " 
                    (endpoint, query_hash, execution_time_ms, query_complexity, result_count, 
                     server_load_avg, memory_usage_mb, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    db_execute($insertQuery, [
        $endpoint,
        $queryHash,
        $executionTimeMs,
        $complexity,
        $resultCount,
        $serverLoad,
        $memoryUsage
    ], 'ssiiidd');
    
    // Analyze and adapt (asynchronous if possible)
    ml_analyze_and_adapt($endpoint, $queryHash, $executionTimeMs);
}

/**
 * Normalize SQL query for pattern matching
 * Removes values, keeps structure
 * 
 * @param string $query Raw SQL query
 * @return string Normalized query
 */
function normalize_query(string $query): string
{
    // Remove whitespace variations
    $normalized = preg_replace('/\s+/', ' ', trim($query));
    
    // Replace string literals with placeholder
    $normalized = preg_replace("/'[^']*'/", "'?'", $normalized);
    
    // Replace numbers with placeholder
    $normalized = preg_replace('/\b\d+\b/', '?', $normalized);
    
    // Replace UUIDs with placeholder
    $normalized = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '?', $normalized);
    
    return strtoupper($normalized);
}

/**
 * Calculate query complexity score
 * Higher score = more complex query
 * 
 * @param string $query SQL query
 * @return int Complexity score (1-100)
 */
function calculate_query_complexity(string $query): int
{
    $score = 1;
    
    // Count JOINs
    $score += substr_count(strtoupper($query), 'JOIN') * 5;
    
    // Count subqueries
    $score += substr_count($query, 'SELECT', 1) * 10; // -1 for main SELECT
    
    // Count WHERE clauses
    $score += substr_count(strtoupper($query), 'WHERE') * 2;
    
    // Count OR conditions
    $score += substr_count(strtoupper($query), ' OR ') * 3;
    
    // Count aggregate functions
    $aggregates = ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'GROUP BY'];
    foreach ($aggregates as $func) {
        $score += substr_count(strtoupper($query), $func) * 2;
    }
    
    // Count DISTINCT
    $score += substr_count(strtoupper($query), 'DISTINCT') * 3;
    
    // Count ORDER BY
    $score += substr_count(strtoupper($query), 'ORDER BY') * 2;
    
    return min($score, 100); // Cap at 100
}

/**
 * Analyze performance and apply adaptive optimizations
 * 
 * @param string $endpoint Current endpoint
 * @param string $queryHash Query hash identifier
 * @param int $executionTimeMs Actual execution time
 * @return void
 */
function ml_analyze_and_adapt(string $endpoint, string $queryHash, int $executionTimeMs): void
{
    // Get current baseline
    $baseline = ml_get_baseline($endpoint, $queryHash);
    
    if (!$baseline) {
        // Not enough samples yet, create initial baseline
        ml_update_baseline($endpoint, $queryHash, $executionTimeMs);
        return;
    }
    
    // Calculate deviation from baseline
    $avgTime = $baseline['avg_execution_time_ms'];
    $deviation = ($executionTimeMs - $avgTime) / $avgTime;
    
    // Detect anomaly (performance degradation)
    if ($deviation > ML_ANOMALY_THRESHOLD) {
        ml_handle_anomaly($endpoint, $queryHash, $executionTimeMs, $avgTime, $deviation);
    }
    
    // Check for improvement
    if ($deviation < -0.2) {
        // 20%+ faster - can reduce caching overhead
        ml_reduce_cache_ttl($endpoint, 0.8);
    }
    
    // Update baseline (rolling average)
    ml_update_baseline($endpoint, $queryHash, $executionTimeMs);
}

/**
 * Get performance baseline for endpoint/query
 * 
 * @param string $endpoint Endpoint name
 * @param string $queryHash Query hash
 * @return array|null Baseline data or null
 */
function ml_get_baseline(string $endpoint, string $queryHash): ?array
{
    $query = "SELECT * FROM " . TABLE_PERFORMANCE_BASELINES . " 
              WHERE endpoint = ? AND query_hash = ? 
              LIMIT 1";
    
    return db_fetch_one($query, [$endpoint, $queryHash], 'ss');
}

/**
 * Update performance baseline (rolling average)
 * 
 * @param string $endpoint Endpoint name
 * @param string $queryHash Query hash
 * @param int $executionTimeMs New execution time
 * @return void
 */
function ml_update_baseline(string $endpoint, string $queryHash, int $executionTimeMs): void
{
    // Get recent metrics for this query
    $metricsQuery = "SELECT 
                        AVG(execution_time_ms) as avg_time,
                        MIN(execution_time_ms) as min_time,
                        MAX(execution_time_ms) as max_time,
                        STDDEV(execution_time_ms) as stddev,
                        COUNT(*) as sample_count
                     FROM " . TABLE_PERFORMANCE_METRICS . " 
                     WHERE endpoint = ? AND query_hash = ?
                     AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $metrics = db_fetch_one($metricsQuery, [$endpoint, $queryHash], 'ss');
    
    if (!$metrics || $metrics['sample_count'] < ML_BASELINE_MIN_SAMPLES) {
        // Not enough samples yet
        return;
    }
    
    // Calculate percentiles (simplified - would use proper percentile calculation in production)
    $p95 = round($metrics['avg_time'] + ($metrics['stddev'] ?? 0) * 1.645);
    $p99 = round($metrics['avg_time'] + ($metrics['stddev'] ?? 0) * 2.326);
    
    // Upsert baseline
    $upsertQuery = "INSERT INTO " . TABLE_PERFORMANCE_BASELINES . " 
                    (endpoint, query_hash, avg_execution_time_ms, p95_execution_time_ms, 
                     p99_execution_time_ms, min_execution_time_ms, max_execution_time_ms, 
                     sample_count, stddev_ms, last_updated, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        avg_execution_time_ms = VALUES(avg_execution_time_ms),
                        p95_execution_time_ms = VALUES(p95_execution_time_ms),
                        p99_execution_time_ms = VALUES(p99_execution_time_ms),
                        min_execution_time_ms = VALUES(min_execution_time_ms),
                        max_execution_time_ms = VALUES(max_execution_time_ms),
                        sample_count = VALUES(sample_count),
                        stddev_ms = VALUES(stddev_ms),
                        last_updated = NOW()";
    
    db_execute($upsertQuery, [
        $endpoint,
        $queryHash,
        round($metrics['avg_time']),
        $p95,
        $p99,
        $metrics['min_time'],
        $metrics['max_time'],
        $metrics['sample_count'],
        round($metrics['stddev'] ?? 0, 2)
    ], 'ssiiiiidi');
}

/**
 * Handle performance anomaly (degradation detected)
 * 
 * @param string $endpoint Endpoint name
 * @param string $queryHash Query hash
 * @param int $actualTime Actual execution time
 * @param int $baselineTime Expected execution time
 * @param float $deviation Deviation ratio
 * @return void
 */
function ml_handle_anomaly(string $endpoint, string $queryHash, int $actualTime, int $baselineTime, float $deviation): void
{
    $impactLevel = 'medium';
    $adjustmentFactor = 1.5;
    
    // Determine severity
    if ($deviation > 1.0) {
        // 100%+ slower - critical
        $impactLevel = 'critical';
        $adjustmentFactor = 2.0;
    } elseif ($deviation > 0.75) {
        // 75-100% slower - high
        $impactLevel = 'high';
        $adjustmentFactor = 1.75;
    }
    
    // Log anomaly
    log_security_event('performance_anomaly', [
        'endpoint' => $endpoint,
        'query_hash' => $queryHash,
        'actual_ms' => $actualTime,
        'baseline_ms' => $baselineTime,
        'deviation' => round($deviation * 100, 2) . '%',
        'impact' => $impactLevel
    ]);
    
    // Adaptive responses
    if (ML_AUTO_OPTIMIZE) {
        // Increase cache TTL
        ml_increase_cache_ttl($endpoint, $adjustmentFactor);
        
        // Create recommendation for index
        if ($deviation > 0.75) {
            ml_create_recommendation('add_index', $endpoint, $queryHash, 
                "Query is {$deviation}x slower than baseline. Consider adding index.", 
                $impactLevel);
        }
    }
}

/**
 * Increase cache TTL for endpoint (adaptive response to slow queries)
 * 
 * @param string $endpoint Endpoint name
 * @param float $factor Multiplier (1.5 = 50% increase)
 * @return void
 */
function ml_increase_cache_ttl(string $endpoint, float $factor): void
{
    $cacheKey = "endpoint_cache_{$endpoint}";
    
    // Get current cache settings
    $currentCache = db_fetch_one(
        "SELECT * FROM " . TABLE_ADAPTIVE_CACHE . " WHERE cache_key = ?",
        [$cacheKey],
        's'
    );
    
    if (!$currentCache) {
        // Create initial cache entry
        $baseTtl = 300; // 5 minutes default
        $newTtl = round($baseTtl * $factor);
        
        db_execute(
            "INSERT INTO " . TABLE_ADAPTIVE_CACHE . " 
             (cache_key, endpoint, ttl_seconds, base_ttl_seconds, adjustment_factor, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$cacheKey, $endpoint, $newTtl, $baseTtl, $factor],
            'ssiid'
        );
    } else {
        // Update existing cache
        $newFactor = min($currentCache['adjustment_factor'] * $factor, ML_CACHE_ADJUST_MAX);
        $newTtl = round($currentCache['base_ttl_seconds'] * $newFactor);
        
        db_execute(
            "UPDATE " . TABLE_ADAPTIVE_CACHE . " 
             SET ttl_seconds = ?, adjustment_factor = ?, updated_at = NOW()
             WHERE cache_key = ?",
            [$newTtl, $newFactor, $cacheKey],
            'ids'
        );
    }
}

/**
 * Reduce cache TTL (when performance improves)
 * 
 * @param string $endpoint Endpoint name
 * @param float $factor Multiplier (0.8 = 20% reduction)
 * @return void
 */
function ml_reduce_cache_ttl(string $endpoint, float $factor): void
{
    $cacheKey = "endpoint_cache_{$endpoint}";
    
    $currentCache = db_fetch_one(
        "SELECT * FROM " . TABLE_ADAPTIVE_CACHE . " WHERE cache_key = ?",
        [$cacheKey],
        's'
    );
    
    if ($currentCache) {
        $newFactor = max($currentCache['adjustment_factor'] * $factor, ML_CACHE_ADJUST_MIN);
        $newTtl = round($currentCache['base_ttl_seconds'] * $newFactor);
        
        db_execute(
            "UPDATE " . TABLE_ADAPTIVE_CACHE . " 
             SET ttl_seconds = ?, adjustment_factor = ?, updated_at = NOW()
             WHERE cache_key = ?",
            [$newTtl, $newFactor, $cacheKey],
            'ids'
        );
    }
}

/**
 * Create performance recommendation
 * 
 * @param string $type Recommendation type
 * @param string $endpoint Endpoint name
 * @param string $queryHash Query hash
 * @param string $description Recommendation description
 * @param string $impactLevel Impact level
 * @return void
 */
function ml_create_recommendation(
    string $type, 
    string $endpoint, 
    string $queryHash, 
    string $description, 
    string $impactLevel = 'medium'
): void {
    $insertQuery = "INSERT INTO " . TABLE_PERFORMANCE_RECOMMENDATIONS . " 
                    (recommendation_type, endpoint, query_hash, description, 
                     impact_level, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
    
    db_execute($insertQuery, [
        $type,
        $endpoint,
        $queryHash,
        $description,
        $impactLevel
    ], 'sssss');
}

/**
 * Get adaptive cache TTL for endpoint
 * 
 * @param string $endpoint Endpoint name
 * @return int TTL in seconds
 */
function ml_get_cache_ttl(string $endpoint): int
{
    $cacheKey = "endpoint_cache_{$endpoint}";
    
    $cache = db_fetch_one(
        "SELECT ttl_seconds FROM " . TABLE_ADAPTIVE_CACHE . " WHERE cache_key = ?",
        [$cacheKey],
        's'
    );
    
    return $cache ? (int)$cache['ttl_seconds'] : 300; // Default 5 minutes
}

// ============================================================================
// LOAD FORECASTING (Predictive Analytics)
// ============================================================================

/**
 * Update load forecast based on current traffic
 * Run this periodically (e.g., every hour)
 * 
 * @return void
 */
function ml_update_load_forecast(): void
{
    if (!ML_FORECAST_ENABLED) {
        return;
    }
    
    $currentHour = (int)date('G'); // 0-23
    $currentDay = (int)date('w');  // 0-6 (0=Sunday)
    
    // Get metrics for this hour/day combo from last 4 weeks
    $query = "SELECT 
                COUNT(DISTINCT DATE(created_at)) as sample_weeks,
                COUNT(*) / COUNT(DISTINCT DATE(created_at)) as avg_requests_per_hour,
                AVG(execution_time_ms) as avg_query_time_ms
              FROM " . TABLE_PERFORMANCE_METRICS . " 
              WHERE HOUR(created_at) = ?
              AND DAYOFWEEK(created_at) = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL 4 WEEK)";
    
    $metrics = db_fetch_one($query, [$currentHour, $currentDay + 1], 'ii'); // MySQL DAYOFWEEK is 1-7
    
    if (!$metrics || $metrics['sample_weeks'] < 2) {
        return; // Need at least 2 weeks of data
    }
    
    // Upsert forecast
    $upsertQuery = "INSERT INTO " . TABLE_LOAD_FORECAST . " 
                    (forecast_hour, forecast_day, avg_requests_per_hour, 
                     avg_query_time_ms, sample_weeks, confidence_score, last_updated)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        avg_requests_per_hour = VALUES(avg_requests_per_hour),
                        avg_query_time_ms = VALUES(avg_query_time_ms),
                        sample_weeks = VALUES(sample_weeks),
                        confidence_score = VALUES(confidence_score),
                        last_updated = NOW()";
    
    $confidence = min($metrics['sample_weeks'] / 4, 1.0); // 100% confidence at 4+ weeks
    
    db_execute($upsertQuery, [
        $currentHour,
        $currentDay,
        round($metrics['avg_requests_per_hour']),
        round($metrics['avg_query_time_ms']),
        $metrics['sample_weeks'],
        round($confidence, 2),
        $peakConcurrent ?? 0
    ], 'iiiidi');
}

/**
 * Get predicted load for next hour
 * Use this to pre-warm caches or scale resources
 * 
 * @return array|null Forecast data or null
 */
function ml_get_predicted_load(): ?array
{
    $nextHour = (int)date('G', strtotime('+1 hour'));
    $nextDay = (int)date('w', strtotime('+1 hour'));
    
    return db_fetch_one(
        "SELECT * FROM " . TABLE_LOAD_FORECAST . " 
         WHERE forecast_hour = ? AND forecast_day = ?",
        [$nextHour, $nextDay],
        'ii'
    );
}
