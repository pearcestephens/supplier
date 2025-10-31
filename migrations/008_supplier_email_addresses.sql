-- Migration: Email Management System - Multiple Email Addresses per Supplier
-- Date: 2025-10-31
-- Description: Adds support for suppliers to manage multiple email addresses with verification

-- ============================================================================
-- 1. Create supplier_email_addresses table
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_email_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(64) NULL,
    verification_token_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token),
    INDEX idx_is_primary (is_primary),
    INDEX idx_verified (verified),
    FOREIGN KEY (supplier_id) REFERENCES vend_suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_email (supplier_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Populate table with existing supplier emails (set as primary + verified)
-- ============================================================================
INSERT INTO supplier_email_addresses (supplier_id, email, is_primary, verified)
SELECT 
    id as supplier_id,
    email,
    1 as is_primary,
    1 as verified
FROM vend_suppliers
WHERE email IS NOT NULL 
  AND email != '' 
  AND deleted_at IS NULL
ON DUPLICATE KEY UPDATE is_primary = 1, verified = 1;

-- ============================================================================
-- 3. Create email verification log table (optional - for auditing)
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_email_verification_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    action ENUM('added', 'verified', 'removed', 'primary_changed', 'verification_sent') NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_email (email),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. Create rate limiting table for email operations
-- ============================================================================
CREATE TABLE IF NOT EXISTS supplier_email_rate_limit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id VARCHAR(100) NOT NULL,
    action_type ENUM('add_email', 'resend_verification') NOT NULL,
    action_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_action (supplier_id, action_type),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Success message
-- ============================================================================
SELECT 'Migration 008 completed successfully! Email management system tables created.' as status;
