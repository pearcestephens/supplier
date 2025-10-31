-- Migration: Add supplier tracking and activity logging capabilities
-- Date: 2025-10-23
-- Description: Adds tracking information fields and activity logging tables

-- ============================================================================
-- 1. Add tracking columns to vend_consignments
-- ============================================================================
ALTER TABLE vend_consignments 
ADD COLUMN tracking_number VARCHAR(100) NULL AFTER supplier_reference,
ADD COLUMN tracking_carrier VARCHAR(50) NULL AFTER tracking_number,
ADD COLUMN tracking_url VARCHAR(255) NULL AFTER tracking_carrier,
ADD COLUMN tracking_updated_at TIMESTAMP NULL AFTER tracking_url,
ADD INDEX idx_tracking_number (tracking_number),
ADD INDEX idx_tracking_updated_at (tracking_updated_at);

-- ============================================================================
-- 2. Create supplier activity log table
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    order_id INT(11) NULL,
    action_type ENUM('login', 'logout', 'tracking_updated', 'note_added', 'info_requested', 'order_viewed', 'report_generated', 'csv_exported') NOT NULL,
    action_details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_order_id (order_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (order_id) REFERENCES vend_consignments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Create supplier information requests table
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_info_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    order_id INT(11) NOT NULL,
    request_message TEXT NOT NULL,
    response_message TEXT NULL,
    status ENUM('pending', 'answered', 'closed') DEFAULT 'pending',
    responded_by INT(11) NULL COMMENT 'Staff user ID who responded',
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (order_id) REFERENCES vend_consignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. Create supplier notification preferences table
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_notification_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL UNIQUE,
    email_addresses TEXT NULL COMMENT 'JSON array of email addresses',
    notify_new_orders BOOLEAN DEFAULT TRUE,
    notify_order_updates BOOLEAN DEFAULT TRUE,
    notify_info_requests BOOLEAN DEFAULT TRUE,
    notify_daily_summary BOOLEAN DEFAULT FALSE,
    notify_weekly_report BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. Sample data for testing (optional)
-- ============================================================================
-- Add some sample notification preferences
-- INSERT INTO supplier_notification_preferences (supplier_id, email_addresses, notify_daily_summary) 
-- VALUES ('SUPPLIER_001', '["orders@supplier.com", "manager@supplier.com"]', TRUE);

-- ============================================================================
-- 6. Create view for supplier order summary
-- ============================================================================
CREATE OR REPLACE VIEW vw_supplier_order_summary AS
SELECT 
    t.id,
    t.public_id,
    t.supplier_id,
    t.state,
    t.created_at,
    t.expected_delivery_date,
    t.tracking_number,
    t.tracking_carrier,
    t.tracking_updated_at,
    o.name as outlet_name,
    o.outlet_code,
    COUNT(DISTINCT ti.id) as item_count,
    SUM(ti.quantity_sent) as total_quantity,
    SUM(ti.quantity_sent * ti.unit_cost) as total_value,
    CASE 
        WHEN t.expected_delivery_date < CURDATE() AND t.state IN ('OPEN', 'SENT', 'RECEIVING') THEN 'overdue'
        WHEN t.expected_delivery_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND t.state IN ('OPEN', 'SENT', 'RECEIVING') THEN 'due_soon'
        ELSE 'on_track'
    END as delivery_status
FROM vend_consignments t
LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
LEFT JOIN vend_outlets o ON t.outlet_to = o.id
WHERE t.transfer_category = 'PURCHASE_ORDER'
  AND t.deleted_at IS NULL
GROUP BY t.id;

-- ============================================================================
-- Success message
-- ============================================================================
SELECT 'Migration completed successfully! Supplier tracking and activity logging enabled.' as status;
