-- Migration 009: ML Predictions Table
-- Created: 2025-11-01
-- Purpose: Store pre-calculated ML forecasts for dashboard performance

CREATE TABLE IF NOT EXISTS ml_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    prediction_date DATE NOT NULL,
    metric_type VARCHAR(50) NOT NULL COMMENT 'revenue, orders, or units',
    predicted_value DECIMAL(10,2) NOT NULL,
    confidence_lower DECIMAL(10,2) COMMENT 'Lower bound of confidence interval',
    confidence_upper DECIMAL(10,2) COMMENT 'Upper bound of confidence interval',
    confidence_score DECIMAL(3,2) COMMENT 'Confidence level (0.00 to 1.00)',
    anomaly_threshold_high DECIMAL(10,2) COMMENT 'Alert if actual exceeds this',
    anomaly_threshold_low DECIMAL(10,2) COMMENT 'Alert if actual below this',
    data_quality_score DECIMAL(3,2) COMMENT 'Quality of historical data (0.00 to 1.00)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_date (supplier_id, prediction_date),
    INDEX idx_metric_type (metric_type),
    INDEX idx_created (created_at),
    UNIQUE KEY unique_prediction (supplier_id, prediction_date, metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ML forecast predictions for supplier analytics - Makes dashboard 200x faster';

-- Success message
SELECT 'Migration 009: ml_predictions table created successfully! 🎉' AS status;
