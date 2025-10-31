-- ============================================================================
-- Supplier Portal - Database Setup Script
-- 
-- Run this script to create all required tables for the supplier portal
-- 
-- @package CIS\Supplier
-- @version 2.0.0
-- ============================================================================

-- Use the correct database
USE vend_sales;

-- ============================================================================
-- SUPPLIER SESSIONS TABLE
-- ============================================================================
-- Note: Uses UUID from existing suppliers.id column

CREATE TABLE IF NOT EXISTS supplier_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from suppliers.id',
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    last_activity DATETIME NOT NULL,
    
    UNIQUE KEY unique_supplier_session (supplier_id, session_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_session (session_id),
    INDEX idx_last_activity (last_activity)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores active supplier portal sessions - supplier_id is UUID from suppliers.id';

-- ============================================================================
-- SUPPLIER PORTAL LOGS TABLE
-- ============================================================================
-- Note: Uses UUID from existing suppliers.id column

CREATE TABLE IF NOT EXISTS supplier_portal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from suppliers.id',
    action VARCHAR(100) NOT NULL,
    data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_supplier (supplier_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_supplier_action (supplier_id, action),
    INDEX idx_supplier_date (supplier_id, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs all supplier portal actions and events - supplier_id is UUID from suppliers.id';

-- ============================================================================
-- SUPPLIERS TABLE - ALREADY EXISTS (managed by Vend API)
-- ============================================================================
-- Note: This table already exists in production with the following structure:
--   - id (CHAR(36), PRIMARY KEY) - UUID from Vend
--   - name (VARCHAR) - Supplier name
--   - deleted_at (VARCHAR or DATETIME) - Soft delete ('' = active, datetime = deleted)
--   - Other Vend-managed columns
-- 
-- We DO NOT create or modify this table - it's managed by the main CIS system
-- Portal queries must filter by: deleted_at = '' for active suppliers only
-- ============================================================================

-- Verify suppliers table exists (informational only)
SELECT 
    'suppliers table must exist with id (UUID) and deleted_at columns' AS requirement,
    'Managed by Vend API - do not modify' AS note;

-- ============================================================================
-- MACHINE LEARNING PERFORMANCE TRACKING TABLES
-- ============================================================================

-- Performance metrics collection (raw data)
CREATE TABLE IF NOT EXISTS supplier_portal_performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL COMMENT 'e.g. dashboard, orders, warranty',
    query_hash CHAR(32) NOT NULL COMMENT 'MD5 hash of normalized query',
    execution_time_ms INT NOT NULL COMMENT 'Query execution time in milliseconds',
    query_complexity INT DEFAULT 1 COMMENT 'Complexity score (joins, WHERE clauses)',
    result_count INT DEFAULT 0 COMMENT 'Number of rows returned',
    server_load_avg DECIMAL(4,2) DEFAULT NULL COMMENT 'Server load average at query time',
    memory_usage_mb INT DEFAULT NULL COMMENT 'Memory usage in MB',
    created_at DATETIME NOT NULL,
    
    INDEX idx_endpoint (endpoint),
    INDEX idx_query_hash (query_hash),
    INDEX idx_execution_time (execution_time_ms),
    INDEX idx_created (created_at),
    INDEX idx_endpoint_hash (endpoint, query_hash)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Collects performance metrics for ML analysis';

-- Learned performance baselines (aggregated intelligence)
CREATE TABLE IF NOT EXISTS supplier_portal_performance_baselines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    query_hash CHAR(32) NOT NULL,
    avg_execution_time_ms INT NOT NULL COMMENT 'Rolling average execution time',
    p95_execution_time_ms INT NOT NULL COMMENT '95th percentile',
    p99_execution_time_ms INT NOT NULL COMMENT '99th percentile',
    min_execution_time_ms INT NOT NULL COMMENT 'Best ever performance',
    max_execution_time_ms INT NOT NULL COMMENT 'Worst performance',
    sample_count INT DEFAULT 0 COMMENT 'Number of samples used',
    stddev_ms DECIMAL(10,2) DEFAULT NULL COMMENT 'Standard deviation',
    last_updated DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    
    UNIQUE KEY unique_baseline (endpoint, query_hash),
    INDEX idx_endpoint (endpoint),
    INDEX idx_last_updated (last_updated)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Learned performance baselines for adaptive optimization';

-- Adaptive cache settings (dynamic TTL management)
CREATE TABLE IF NOT EXISTS supplier_portal_adaptive_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    ttl_seconds INT NOT NULL COMMENT 'Current TTL (auto-adjusted)',
    base_ttl_seconds INT NOT NULL COMMENT 'Original TTL before adjustment',
    hit_count INT DEFAULT 0,
    miss_count INT DEFAULT 0,
    avg_load_time_ms INT DEFAULT NULL,
    adjustment_factor DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Multiplier for TTL (0.5-3.0)',
    last_accessed DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_cache_key (cache_key),
    INDEX idx_endpoint (endpoint),
    INDEX idx_last_accessed (last_accessed),
    INDEX idx_adjustment_factor (adjustment_factor)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Adaptive cache configuration with ML-driven TTL adjustment';

-- Performance recommendations (AI-generated insights)
CREATE TABLE IF NOT EXISTS supplier_portal_performance_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recommendation_type VARCHAR(50) NOT NULL COMMENT 'add_index, increase_cache, reduce_complexity, etc.',
    endpoint VARCHAR(100) DEFAULT NULL,
    query_hash CHAR(32) DEFAULT NULL,
    description TEXT NOT NULL,
    sql_suggestion TEXT DEFAULT NULL COMMENT 'Suggested SQL improvement',
    impact_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estimated_improvement_ms INT DEFAULT NULL COMMENT 'Expected performance gain',
    auto_applied BOOLEAN DEFAULT FALSE COMMENT 'Was this auto-applied?',
    applied_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_type (recommendation_type),
    INDEX idx_endpoint (endpoint),
    INDEX idx_impact (impact_level),
    INDEX idx_created (created_at),
    INDEX idx_auto_applied (auto_applied)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ML-generated performance optimization recommendations';

-- Load forecasting data (predictive analytics)
CREATE TABLE IF NOT EXISTS supplier_portal_load_forecast (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forecast_hour INT NOT NULL COMMENT 'Hour of day (0-23)',
    forecast_day INT NOT NULL COMMENT 'Day of week (0-6, 0=Sunday)',
    avg_requests_per_hour INT NOT NULL,
    avg_query_time_ms INT NOT NULL,
    peak_concurrent_users INT NOT NULL,
    sample_weeks INT DEFAULT 1 COMMENT 'Number of weeks in sample',
    confidence_score DECIMAL(3,2) DEFAULT NULL COMMENT '0.00-1.00',
    last_updated DATETIME NOT NULL,
    
    UNIQUE KEY unique_forecast (forecast_hour, forecast_day),
    INDEX idx_hour (forecast_hour),
    INDEX idx_day (forecast_day)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Load forecasting for predictive resource optimization';

-- ============================================================================
-- VERIFY TABLES
-- ============================================================================

-- Show created tables
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    UPDATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'vend_sales'
AND TABLE_NAME IN (
    'supplier_sessions', 
    'supplier_portal_logs', 
    'supplier_portal_performance_metrics',
    'supplier_portal_performance_baselines',
    'supplier_portal_adaptive_cache',
    'supplier_portal_performance_recommendations',
    'supplier_portal_load_forecast'
)
ORDER BY TABLE_NAME;

-- ============================================================================
-- CLEANUP OLD SESSIONS (optional maintenance query)
-- ============================================================================

-- Delete sessions older than 24 hours
-- Uncomment to run:
-- DELETE FROM supplier_sessions 
-- WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- ============================================================================
-- CLEANUP OLD LOGS (optional maintenance query)
-- ============================================================================

-- Delete logs older than 90 days
-- Uncomment to run:
-- DELETE FROM supplier_portal_logs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 'Database setup complete!' AS status;
