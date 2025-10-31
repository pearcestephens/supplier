-- ============================================================================
-- SUPPLIER PORTAL SUPPORT TABLES
-- ============================================================================
-- Purpose: Support tables for supplier portal functionality
-- Database: jcepnzzkmj
-- Date: October 21, 2025
-- 
-- Dependencies: Requires vend_suppliers, faulty_products tables
-- ============================================================================

-- Drop tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS supplier_portal_notifications;
DROP TABLE IF EXISTS supplier_warranty_notes;
DROP TABLE IF EXISTS supplier_portal_logs;
DROP TABLE IF EXISTS supplier_portal_sessions;

-- ============================================================================
-- TABLE 1: Supplier Portal Sessions
-- ============================================================================
-- Purpose: Manage supplier authentication and active sessions
-- Security: Auto-expire sessions, track IP/user agent
-- ============================================================================

CREATE TABLE supplier_portal_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique session identifier',
    
    -- Supplier identification
    supplier_id VARCHAR(100) NOT NULL COMMENT 'Links to vend_suppliers.id (business code format)',
    
    -- Session security
    session_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Secure session token for authentication',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the session for security tracking',
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'Browser user agent for device identification',
    
    -- Session lifecycle
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When session was created',
    expires_at TIMESTAMP NOT NULL COMMENT 'When session expires (typically 24 hours)',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity timestamp for idle detection',
    
    -- Foreign keys
    FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_session_token (session_token) COMMENT 'Fast token lookup',
    INDEX idx_supplier_active (supplier_id, expires_at) COMMENT 'Find active sessions by supplier',
    INDEX idx_expires (expires_at) COMMENT 'Cleanup expired sessions'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Supplier portal authentication sessions with auto-expiry';


-- ============================================================================
-- TABLE 2: Supplier Portal Activity Logs
-- ============================================================================
-- Purpose: Audit trail of all supplier actions in the portal
-- Compliance: Track all data access and modifications
-- ============================================================================

CREATE TABLE supplier_portal_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique log entry identifier',
    
    -- Who did what
    supplier_id VARCHAR(100) NOT NULL COMMENT 'Supplier who performed the action',
    action VARCHAR(100) NOT NULL COMMENT 'Action type: login, view_po, update_warranty, etc.',
    
    -- What was accessed
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Resource type: purchase_order, warranty_claim, product, etc.',
    resource_id VARCHAR(100) DEFAULT NULL COMMENT 'ID of the resource accessed',
    
    -- Context
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of request',
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'Browser user agent',
    details TEXT DEFAULT NULL COMMENT 'Additional details about the action (JSON format)',
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When action occurred',
    
    -- Foreign keys
    FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_supplier_created (supplier_id, created_at DESC) COMMENT 'Activity history by supplier',
    INDEX idx_action (action) COMMENT 'Filter by action type',
    INDEX idx_resource (resource_type, resource_id) COMMENT 'Find all actions on a resource',
    INDEX idx_created (created_at DESC) COMMENT 'Recent activity across all suppliers'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Audit trail of supplier portal actions for compliance and security';


-- ============================================================================
-- TABLE 3: Supplier Warranty Notes
-- ============================================================================
-- Purpose: Allow suppliers to add notes/comments on warranty claims
-- Integration: Links to faulty_products table
-- ============================================================================

CREATE TABLE supplier_warranty_notes (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique note identifier',
    
    -- Links
    fault_id INT NOT NULL COMMENT 'Links to faulty_products.id',
    supplier_id VARCHAR(100) NOT NULL COMMENT 'Supplier who added the note',
    
    -- Note content
    note TEXT NOT NULL COMMENT 'Supplier note/comment on the warranty claim',
    action_taken VARCHAR(50) DEFAULT NULL COMMENT 'Action: ACCEPTED, DECLINED, MORE_INFO_NEEDED, RESOLVED',
    
    -- Internal reference
    internal_ref VARCHAR(100) DEFAULT NULL COMMENT 'Supplier internal reference number for tracking',
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When note was added',
    
    -- Foreign keys
    FOREIGN KEY (fault_id) REFERENCES faulty_products(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_fault (fault_id, created_at DESC) COMMENT 'All notes for a warranty claim',
    INDEX idx_supplier (supplier_id, created_at DESC) COMMENT 'All notes by a supplier',
    INDEX idx_action (action_taken) COMMENT 'Filter by action taken'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Supplier notes and responses on warranty claims';


-- ============================================================================
-- TABLE 4: Supplier Portal Notifications
-- ============================================================================
-- Purpose: In-app notifications for suppliers
-- Features: Unread count, notification history
-- ============================================================================

CREATE TABLE supplier_portal_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique notification identifier',
    
    -- Recipient
    supplier_id VARCHAR(100) NOT NULL COMMENT 'Supplier receiving the notification',
    
    -- Notification content
    type VARCHAR(50) NOT NULL COMMENT 'Type: new_po, warranty_claim, stock_alert, message',
    title VARCHAR(255) NOT NULL COMMENT 'Notification title/subject',
    message TEXT DEFAULT NULL COMMENT 'Notification message body',
    link VARCHAR(255) DEFAULT NULL COMMENT 'Deep link to related resource',
    
    -- Read status
    is_read TINYINT DEFAULT 0 COMMENT 'Read status: 0=unread, 1=read',
    read_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When notification was marked as read',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
    
    -- Foreign keys
    FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_supplier_unread (supplier_id, is_read, created_at DESC) COMMENT 'Unread notifications by supplier',
    INDEX idx_type (type) COMMENT 'Filter by notification type',
    INDEX idx_created (created_at DESC) COMMENT 'Recent notifications'
    
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='In-app notifications for supplier portal users';


-- ============================================================================
-- PERFORMANCE INDEXES ON EXISTING TABLES
-- ============================================================================
-- Note: Only create if they don't already exist
-- ============================================================================

-- Index for filtering transfers by supplier and category
-- Uncomment if index doesn't exist:
-- CREATE INDEX idx_transfers_supplier_category_state 
-- ON transfers(supplier_id, transfer_category, state, created_at DESC);

-- Index for warranty claims by supplier
-- Requires JOIN through vend_products - ensure vend_products has supplier_id index:
-- CREATE INDEX idx_vend_products_supplier_deleted 
-- ON vend_products(supplier_id, deleted_at);

-- Index for faulty products with supplier filtering
-- CREATE INDEX idx_faulty_products_product_status 
-- ON faulty_products(product_id, supplier_status, time_created DESC);


-- ============================================================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================================================

-- Sample session (will be created on login)
-- INSERT INTO supplier_portal_sessions (supplier_id, session_token, ip_address, expires_at)
-- VALUES ('ACEVAPE', SHA2(CONCAT('ACEVAPE', NOW(), RAND()), 256), '127.0.0.1', DATE_ADD(NOW(), INTERVAL 24 HOUR));

-- Sample notification
-- INSERT INTO supplier_portal_notifications (supplier_id, type, title, message, link)
-- VALUES ('ACEVAPE', 'new_po', 'New Purchase Order #PO-12345', 'You have received a new purchase order for 150 units.', '/pages/purchase-order-detail.php?id=12345');


-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check table creation
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'jcepnzzkmj' 
  AND TABLE_NAME LIKE 'supplier_%'
ORDER BY TABLE_NAME;

-- Check indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'jcepnzzkmj' 
  AND TABLE_NAME LIKE 'supplier_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================================================
-- CLEANUP (For complete reinstall only)
-- ============================================================================

-- To completely remove portal tables:
-- DROP TABLE IF EXISTS supplier_portal_notifications;
-- DROP TABLE IF EXISTS supplier_warranty_notes;
-- DROP TABLE IF EXISTS supplier_portal_logs;
-- DROP TABLE IF EXISTS supplier_portal_sessions;
