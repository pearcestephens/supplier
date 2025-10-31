-- ============================================================================
-- Supplier Portal - Database Setup Script (CLEAN VERSION)
-- 
-- Uses existing vend_suppliers table as main reference
-- All supporting tables use UUID foreign keys
-- 
-- @package CIS\Supplier
-- @version 3.0.0 (Simple, No ML/AI)
-- @date October 21, 2025
-- ============================================================================

USE vend_sales;

-- ============================================================================
-- REFERENCE: EXISTING VEND_SUPPLIERS TABLE
-- ============================================================================
-- This table ALREADY EXISTS in production (managed by Vend API)
-- DO NOT create or modify this table
--
-- Expected Structure:
--   - id (CHAR(36) or VARCHAR) - UUID Primary Key from Vend
--   - name (VARCHAR) - Supplier name
--   - deleted_at (VARCHAR/DATETIME) - Soft delete indicator
--       * '' (empty string) = Active supplier
--       * datetime value = Deleted supplier
--   - Other Vend-managed columns (contact info, addresses, etc.)
--
-- Portal Queries MUST Filter By:
--   WHERE id = ? AND deleted_at = ''
-- ============================================================================

-- Verify vend_suppliers table structure
SELECT 
    'VERIFICATION: vend_suppliers table structure' AS info,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'vend_sales'
AND TABLE_NAME = 'vend_suppliers'
AND COLUMN_NAME IN ('id', 'name', 'deleted_at')
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- SUPPLIER SESSIONS TABLE
-- ============================================================================
-- Tracks active login sessions for suppliers
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    session_id VARCHAR(128) NOT NULL COMMENT 'PHP session ID',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent string',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Session start time',
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last request time',
    
    -- Indexes
    UNIQUE KEY unique_supplier_session (supplier_id, session_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_session (session_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_created (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active supplier portal login sessions - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER PORTAL LOGS TABLE
-- ============================================================================
-- Audit trail of all supplier actions
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_portal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    action VARCHAR(100) NOT NULL COMMENT 'Action performed (login, view_order, download, etc.)',
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Type of resource accessed (order, warranty, download)',
    resource_id VARCHAR(100) DEFAULT NULL COMMENT 'ID of resource accessed',
    data JSON DEFAULT NULL COMMENT 'Additional action data',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent string',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Action timestamp',
    
    -- Indexes
    INDEX idx_supplier (supplier_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at),
    INDEX idx_supplier_action (supplier_id, action),
    INDEX idx_supplier_date (supplier_id, created_at),
    INDEX idx_supplier_resource (supplier_id, resource_type)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log of all supplier portal actions - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER PREFERENCES TABLE
-- ============================================================================
-- Stores supplier-specific preferences and settings
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    preference_key VARCHAR(100) NOT NULL COMMENT 'Setting name (e.g. theme, language, notifications)',
    preference_value TEXT DEFAULT NULL COMMENT 'Setting value (JSON or plain text)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY unique_supplier_preference (supplier_id, preference_key),
    INDEX idx_supplier (supplier_id),
    INDEX idx_key (preference_key)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplier preferences and settings - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER DOWNLOADS TABLE
-- ============================================================================
-- Tracks files downloaded by suppliers
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    file_name VARCHAR(255) NOT NULL COMMENT 'Name of downloaded file',
    file_path VARCHAR(500) NOT NULL COMMENT 'Server path to file',
    file_size INT DEFAULT NULL COMMENT 'File size in bytes',
    file_type VARCHAR(50) DEFAULT NULL COMMENT 'MIME type or category',
    download_count INT DEFAULT 1 COMMENT 'Number of times downloaded',
    first_downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'First download time',
    last_downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Most recent download',
    
    -- Indexes
    INDEX idx_supplier (supplier_id),
    INDEX idx_file_name (file_name),
    INDEX idx_file_type (file_type),
    INDEX idx_first_downloaded (first_downloaded_at),
    INDEX idx_last_downloaded (last_downloaded_at),
    INDEX idx_supplier_file (supplier_id, file_name)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplier download tracking - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER NOTIFICATIONS TABLE
-- ============================================================================
-- Stores notifications/messages for suppliers
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    notification_type VARCHAR(50) NOT NULL COMMENT 'Type: order_update, warranty_status, system_message, etc.',
    title VARCHAR(200) NOT NULL COMMENT 'Notification title',
    message TEXT NOT NULL COMMENT 'Notification message body',
    link VARCHAR(500) DEFAULT NULL COMMENT 'Optional link to related resource',
    is_read BOOLEAN DEFAULT FALSE COMMENT 'Has supplier read this?',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL COMMENT 'When notification was read',
    
    -- Indexes
    INDEX idx_supplier (supplier_id),
    INDEX idx_type (notification_type),
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at),
    INDEX idx_supplier_unread (supplier_id, is_read),
    INDEX idx_supplier_created (supplier_id, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplier notifications and messages - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER WARRANTY CLAIMS TABLE (if not exists elsewhere)
-- ============================================================================
-- Tracks warranty claims submitted by suppliers
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_warranty_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    claim_number VARCHAR(50) NOT NULL COMMENT 'Unique claim identifier',
    product_id VARCHAR(100) DEFAULT NULL COMMENT 'Product being claimed',
    product_name VARCHAR(255) DEFAULT NULL,
    issue_description TEXT NOT NULL COMMENT 'Description of warranty issue',
    quantity INT DEFAULT 1 COMMENT 'Number of units being claimed',
    claim_status ENUM('pending', 'approved', 'rejected', 'processing', 'completed') DEFAULT 'pending',
    attachments JSON DEFAULT NULL COMMENT 'Array of attachment file paths',
    admin_notes TEXT DEFAULT NULL COMMENT 'Internal notes from admin review',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL COMMENT 'When claim was resolved',
    
    -- Indexes
    UNIQUE KEY unique_claim_number (claim_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (claim_status),
    INDEX idx_created (created_at),
    INDEX idx_resolved (resolved_at),
    INDEX idx_supplier_status (supplier_id, claim_status),
    INDEX idx_supplier_created (supplier_id, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplier warranty claims - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- SUPPLIER API KEYS TABLE
-- ============================================================================
-- Stores API keys for suppliers (if API access is provided)
-- Uses UUID from vend_suppliers.id

CREATE TABLE IF NOT EXISTS supplier_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id CHAR(36) NOT NULL COMMENT 'UUID from vend_suppliers.id',
    api_key CHAR(64) NOT NULL COMMENT 'SHA256 hashed API key',
    key_name VARCHAR(100) DEFAULT NULL COMMENT 'User-friendly name for this key',
    permissions JSON DEFAULT NULL COMMENT 'Array of allowed permissions',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Key enabled/disabled',
    last_used_at DATETIME DEFAULT NULL COMMENT 'Last API request time',
    expires_at DATETIME DEFAULT NULL COMMENT 'Optional expiration date',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY unique_api_key (api_key),
    INDEX idx_supplier (supplier_id),
    INDEX idx_is_active (is_active),
    INDEX idx_expires (expires_at),
    INDEX idx_supplier_active (supplier_id, is_active)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Supplier API keys for programmatic access - supplier_id references vend_suppliers.id (UUID)';

-- ============================================================================
-- VERIFY CREATED TABLES
-- ============================================================================

SELECT 
    TABLE_NAME AS 'Table',
    TABLE_ROWS AS 'Rows',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)',
    CREATE_TIME AS 'Created',
    UPDATE_TIME AS 'Updated',
    TABLE_COMMENT AS 'Description'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'vend_sales'
AND TABLE_NAME IN (
    'supplier_sessions',
    'supplier_portal_logs',
    'supplier_preferences',
    'supplier_downloads',
    'supplier_notifications',
    'supplier_warranty_claims',
    'supplier_api_keys'
)
ORDER BY TABLE_NAME;

-- ============================================================================
-- SAMPLE QUERIES FOR REFERENCE
-- ============================================================================

-- Get active supplier by UUID
-- SELECT * FROM vend_suppliers WHERE id = 'your-uuid-here' AND deleted_at = '';

-- Get supplier's active sessions
-- SELECT * FROM supplier_sessions WHERE supplier_id = 'your-uuid-here';

-- Get supplier's recent activity
-- SELECT * FROM supplier_portal_logs 
-- WHERE supplier_id = 'your-uuid-here' 
-- ORDER BY created_at DESC LIMIT 50;

-- Get supplier's unread notifications
-- SELECT * FROM supplier_notifications 
-- WHERE supplier_id = 'your-uuid-here' AND is_read = FALSE 
-- ORDER BY created_at DESC;

-- Get supplier's warranty claims
-- SELECT * FROM supplier_warranty_claims 
-- WHERE supplier_id = 'your-uuid-here' 
-- ORDER BY created_at DESC;

-- ============================================================================
-- MAINTENANCE QUERIES (optional)
-- ============================================================================

-- Clean up old sessions (older than 24 hours)
-- DELETE FROM supplier_sessions 
-- WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Clean up old logs (older than 90 days)
-- DELETE FROM supplier_portal_logs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Mark old notifications as archived (older than 30 days and read)
-- UPDATE supplier_notifications 
-- SET notification_type = 'archived'
-- WHERE is_read = TRUE 
-- AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- ============================================================================
-- FOREIGN KEY NOTES
-- ============================================================================
-- We intentionally DO NOT create foreign key constraints to vend_suppliers.id
-- because:
-- 1. vend_suppliers is managed by external Vend API sync
-- 2. We want flexibility in handling soft deletes (deleted_at)
-- 3. Application-level referential integrity is sufficient
-- 4. Avoids cascade complications during Vend sync operations
--
-- All supplier_id columns use CHAR(36) to match vend_suppliers.id UUID format
-- Application code MUST validate supplier exists and deleted_at = '' before use
-- ============================================================================

-- ============================================================================
-- DONE
-- ============================================================================

SELECT 
    'Database setup complete!' AS status,
    '7 supporting tables created for vend_suppliers' AS info,
    'All tables use UUID (CHAR(36)) for supplier_id' AS uuid_info,
    'No ML/AI components included' AS simplicity;
