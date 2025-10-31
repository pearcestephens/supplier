-- Supplier Portal Logging Enhancement
-- Ensures proper indexes and structure for existing log tables
-- Does NOT create new tables - uses existing CIS tables

-- Verify and optimize supplier_activity_log
ALTER TABLE supplier_activity_log
ADD INDEX idx_created_at (created_at),
ADD INDEX idx_action_order (action_type, order_id),
ADD INDEX idx_supplier_action_date (supplier_id, action_type, created_at);

-- Verify and optimize supplier_portal_logs
ALTER TABLE supplier_portal_logs
ADD INDEX idx_supplier_action_date (supplier_id, action, created_at),
ADD INDEX idx_resource (resource_type, resource_id),
ADD INDEX idx_action_created (action, created_at);

-- Verify and optimize consignment_logs indexes
ALTER TABLE consignment_logs
ADD INDEX idx_transfer_event_date (transfer_id, event_type, created_at),
ADD INDEX idx_trace (trace_id),
ADD INDEX idx_severity_date (severity, created_at);

-- Create view for supplier activity summary (AI-ready)
CREATE OR REPLACE VIEW v_supplier_activity_summary AS
SELECT
    sal.supplier_id,
    sal.action_type,
    COUNT(*) as action_count,
    MAX(sal.created_at) as last_action,
    MIN(sal.created_at) as first_action,
    COUNT(DISTINCT sal.order_id) as unique_orders,
    COUNT(DISTINCT DATE(sal.created_at)) as active_days
FROM supplier_activity_log sal
GROUP BY sal.supplier_id, sal.action_type;

-- Create view for supplier portal activity summary
CREATE OR REPLACE VIEW v_supplier_portal_activity AS
SELECT
    spl.supplier_id,
    spl.action,
    spl.resource_type,
    COUNT(*) as event_count,
    MAX(spl.created_at) as last_event,
    COUNT(DISTINCT DATE(spl.created_at)) as active_days
FROM supplier_portal_logs spl
GROUP BY spl.supplier_id, spl.action, spl.resource_type;

-- Create view for consignment activity by supplier
CREATE OR REPLACE VIEW v_consignment_supplier_activity AS
SELECT
    JSON_UNQUOTE(JSON_EXTRACT(cl.event_data, '$.supplier_id')) as supplier_id,
    cl.transfer_id,
    cl.event_type,
    cl.severity,
    COUNT(*) as event_count,
    MAX(cl.created_at) as last_event
FROM consignment_logs cl
WHERE cl.source_system = 'supplier_portal'
  AND cl.actor_role = 'supplier'
  AND JSON_EXTRACT(cl.event_data, '$.supplier_id') IS NOT NULL
GROUP BY supplier_id, cl.transfer_id, cl.event_type, cl.severity;

-- AI Insights: Supplier engagement score
CREATE OR REPLACE VIEW v_supplier_engagement_score AS
SELECT
    supplier_id,
    COUNT(DISTINCT action_type) as action_variety_score,
    COUNT(*) as total_actions,
    COUNT(DISTINCT DATE(created_at)) as active_days,
    DATEDIFF(MAX(created_at), MIN(created_at)) + 1 as tenure_days,
    COUNT(*) / NULLIF(DATEDIFF(MAX(created_at), MIN(created_at)) + 1, 0) as avg_actions_per_day,
    CASE
        WHEN DATEDIFF(NOW(), MAX(created_at)) <= 7 THEN 'active'
        WHEN DATEDIFF(NOW(), MAX(created_at)) <= 30 THEN 'moderate'
        ELSE 'inactive'
    END as engagement_status
FROM supplier_activity_log
GROUP BY supplier_id;

-- AI Insights: Order interaction patterns
CREATE OR REPLACE VIEW v_supplier_order_patterns AS
SELECT
    sal.supplier_id,
    sal.order_id,
    COUNT(*) as interaction_count,
    MIN(sal.created_at) as first_interaction,
    MAX(sal.created_at) as last_interaction,
    TIMESTAMPDIFF(MINUTE, MIN(sal.created_at), MAX(sal.created_at)) as engagement_duration_minutes,
    GROUP_CONCAT(DISTINCT sal.action_type ORDER BY sal.created_at SEPARATOR ',') as action_sequence
FROM supplier_activity_log sal
WHERE sal.order_id IS NOT NULL
GROUP BY sal.supplier_id, sal.order_id;

-- AI Insights: Anomaly detection - unusual activity times
CREATE OR REPLACE VIEW v_supplier_activity_anomalies AS
SELECT
    supplier_id,
    DATE(created_at) as activity_date,
    HOUR(created_at) as activity_hour,
    COUNT(*) as action_count,
    CASE
        WHEN HOUR(created_at) BETWEEN 0 AND 5 THEN 'unusual_late_night'
        WHEN HOUR(created_at) BETWEEN 22 AND 23 THEN 'late_evening'
        ELSE 'normal'
    END as time_pattern
FROM supplier_activity_log
GROUP BY supplier_id, DATE(created_at), HOUR(created_at)
HAVING action_count > 50;  -- Flag high-volume activity

-- Success summary
SELECT
    'supplier_activity_log indexes' as table_name,
    COUNT(*) as record_count,
    MIN(created_at) as oldest_record,
    MAX(created_at) as newest_record
FROM supplier_activity_log

UNION ALL

SELECT
    'supplier_portal_logs indexes' as table_name,
    COUNT(*) as record_count,
    MIN(created_at) as oldest_record,
    MAX(created_at) as newest_record
FROM supplier_portal_logs

UNION ALL

SELECT
    'consignment_logs (supplier)' as table_name,
    COUNT(*) as record_count,
    MIN(created_at) as oldest_record,
    MAX(created_at) as newest_record
FROM consignment_logs
WHERE source_system = 'supplier_portal';
