<?php
/**
 * Supplier Portal Configuration
 *
 * Central configuration file for all system settings
 *
 * @package SupplierPortal
 * @version 3.0.0
 */

declare(strict_types=1);

// ============================================================================
// ENVIRONMENT SETTINGS
// ============================================================================

define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production'); // development, staging, production

// ============================================================================
// DEBUG MODE - HARDCODED SUPPLIER FOR TESTING
// ============================================================================
// Set to true to bypass session/cookie requirements and use hardcoded supplier_id
// WARNING: ONLY FOR DEVELOPMENT/TESTING - NEVER in production!

define('DEBUG_MODE_ENABLED', false);   // Toggle this to enable/disable (must be false in production)
// When DEBUG_MODE_ENABLED is true, you can pass ?supplier_id=... in the URL to set the debug supplier dynamically.
// This constant is a fallback only; keep it empty in repo to avoid hardcoding any real IDs.
define('DEBUG_MODE_SUPPLIER_ID', '');  // Optional fallback for local testing

// Debug mode automatically:
// ✅ Skips session requirements
// ✅ Skips login page
// ✅ Uses hardcoded supplier_id on ALL pages
// ✅ Still validates that supplier exists in DB
// ✅ Can be toggled without code changes
// ✅ Logs all debug mode access for audit trail

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jcepnzzkmj');
define('DB_USER', 'jcepnzzkmj');
define('DB_PASS', 'wprKh9Jq63');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

define('SESSION_LIFETIME', 86400); // 24 hours in seconds
define('SESSION_COOKIE_NAME', 'CIS_SUPPLIER_SESSION');
define('SESSION_SECURE', true); // HTTPS only
define('SESSION_HTTPONLY', true); // No JavaScript access

// ============================================================================
// API CONFIGURATION
// ============================================================================

define('API_VERSION', '3.0.0');
define('API_RATE_LIMIT', 100); // requests per minute per IP
define('API_TIMEOUT', 30); // seconds

// Additional rate-limit controls (per IP)
define('RATE_LIMIT_API_PER_MIN', API_RATE_LIMIT); // alias, per-minute API limit
define('RATE_LIMIT_LOGIN_PER_MIN', 10); // POSTs to login per minute per IP

// Magic link TTL (one-time access window)
define('MAGIC_LINK_TTL_SECONDS', 86400); // 24 hours

// ============================================================================
// FILE UPLOAD CONFIGURATION
// ============================================================================

define('UPLOAD_MAX_SIZE', 10485760); // 10MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/supplier/uploads/');

// ============================================================================
// PAGINATION DEFAULTS
// ============================================================================

define('PAGINATION_PER_PAGE', 25);
define('PAGINATION_MAX_PER_PAGE', 100);

// ============================================================================
// DATE & TIME CONFIGURATION
// ============================================================================

define('TIMEZONE', 'Pacific/Auckland');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M j, Y');
define('DISPLAY_DATETIME_FORMAT', 'M j, Y g:i A');

// ============================================================================
// BUSINESS LOGIC SETTINGS
// ============================================================================

// Purchase Order Settings
define('PO_PREFIX', 'JCE-PO-');
define('PO_DUE_DAYS_DEFAULT', 5); // Default days until PO is due
define('PO_URGENT_THRESHOLD_DAYS', 2); // Mark as urgent if due within X days
define('PO_STATUSES', ['OPEN', 'SENT', 'RECEIVING', 'RECEIVED', 'CLOSED']);
define('PO_EDITABLE_STATUSES', ['OPEN', 'SENT']); // Statuses that allow editing

// Warranty Claim Settings
define('WARRANTY_CLAIM_STATUSES', ['pending', 'open', 'resolved', 'rejected']);
define('WARRANTY_RESPONSE_SLA_HOURS', 48); // Expected response time
define('WARRANTY_PHOTO_MAX_COUNT', 10); // Max photos per claim
define('WARRANTY_PHOTO_MAX_SIZE', 5242880); // 5MB per photo

// Inventory Settings
define('STOCK_HEALTH_THRESHOLD_HIGH', 50); // >= 50 units = healthy
define('STOCK_HEALTH_THRESHOLD_MEDIUM', 10); // >= 10 units = medium
define('STOCK_HEALTH_THRESHOLD_LOW', 1); // >= 1 unit = low
// < 1 unit = out of stock

// Performance Ratings
define('RATING_MIN', 1);
define('RATING_MAX', 5);
define('RATING_DECIMALS', 1);

// ============================================================================
// FEATURE FLAGS
// ============================================================================

define('FEATURE_DOWNLOADS_ENABLED', false); // Enable/disable downloads section

// ============================================================================
// NOTIFICATION SETTINGS
// ============================================================================

define('NOTIFICATION_EMAIL_FROM', 'noreply@vapeshed.co.nz');
define('NOTIFICATION_EMAIL_NAME', 'The Vape Shed Supplier Portal');
define('NOTIFICATION_TYPES', [
    'new_order',
    'warranty_claim',
    'order_due_soon',
    'order_overdue',
    'payment_received',
    'weekly_report'
]);

// ============================================================================
// ANALYTICS & REPORTING
// ============================================================================

define('ANALYTICS_DEFAULT_PERIOD_DAYS', 30);
define('ANALYTICS_MAX_PERIOD_DAYS', 365);
define('REPORT_CACHE_LIFETIME', 3600); // 1 hour

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// ============================================================================
// UI/UX SETTINGS
// ============================================================================

define('UI_ITEMS_PER_PAGE_OPTIONS', [10, 25, 50, 100]);
define('UI_DATE_RANGES', [
    '7' => 'Last 7 Days',
    '30' => 'Last 30 Days',
    '60' => 'Last 60 Days',
    '90' => 'Last 90 Days',
    '365' => 'Last Year'
]);

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

define('CACHE_ENABLED', true);
define('CACHE_PREFIX', 'supplier_portal_');
define('CACHE_DEFAULT_TTL', 900); // 15 minutes

// ============================================================================
// ERROR LOGGING
// ============================================================================

define('ERROR_LOG_PATH', $_SERVER['DOCUMENT_ROOT'] . '/supplier/logs/');
define('ERROR_LOG_MAX_SIZE', 10485760); // 10MB
define('ERROR_LOG_RETENTION_DAYS', 30);

// ============================================================================
// SYSTEM INFO
// ============================================================================

define('SYSTEM_NAME', 'The Vape Shed Supplier Portal');
define('SYSTEM_VERSION', '3.0.0');
define('SYSTEM_BUILD_DATE', '2025-10-25');
define('COMPANY_NAME', 'The Vape Shed');
define('COMPANY_WEBSITE', 'https://vapeshed.co.nz');
define('SUPPORT_EMAIL', 'suppliers@vapeshed.co.nz');

// ============================================================================
// BRANDING
// ============================================================================

define('LOGO_PATH', '/supplier/assets/images/logo.jpg');
define('LOGO_WIDTH', 180);
define('LOGO_HEIGHT', 50);

define('PRIMARY_COLOR', '#000000'); // Black
define('SECONDARY_COLOR', '#fbbf24'); // Yellow
define('SUCCESS_COLOR', '#10b981'); // Green
define('WARNING_COLOR', '#f59e0b'); // Orange
define('DANGER_COLOR', '#ef4444'); // Red
define('INFO_COLOR', '#3b82f6'); // Blue

// ============================================================================
// FEATURE FLAGS
// ============================================================================

define('FEATURE_MULTI_USER', true); // Enable multi-user accounts
define('FEATURE_API_ACCESS', true); // Enable API key generation
define('FEATURE_ADVANCED_ANALYTICS', true);
define('FEATURE_BULK_ACTIONS', true);
define('FEATURE_EXPORT_CSV', true);
define('FEATURE_EXPORT_PDF', true);
define('FEATURE_NOTIFICATIONS', true);
define('FEATURE_REAL_TIME_UPDATES', false); // WebSocket updates (future)
define('FEATURE_MOBILE_APP_API', false); // Mobile app endpoints (future)

// ============================================================================
// PATHS
// ============================================================================

define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/supplier/');
define('LIB_PATH', BASE_PATH . 'lib/');
define('API_PATH', BASE_PATH . 'api/');
define('ASSETS_PATH', BASE_PATH . 'assets/');
define('LOGS_PATH', BASE_PATH . 'logs/');
define('CACHE_PATH', BASE_PATH . 'cache/');

// ============================================================================
// URLS
// ============================================================================

define('BASE_URL', '/supplier/');
define('SITE_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'vapeshed.co.nz')); // Define full site URL
define('API_URL', BASE_URL . 'api/endpoint.php');
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOADS_URL', BASE_URL . 'uploads/');

// ============================================================================
// INITIALIZE TIMEZONE
// ============================================================================

date_default_timezone_set(TIMEZONE);

// ============================================================================
// ERROR REPORTING
// ============================================================================

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Don't show ugly errors on screen
    ini_set('log_errors', '1');
    ini_set('error_log', ERROR_LOG_PATH . 'php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ERROR_LOG_PATH . 'php_errors.log');
}

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
ini_set('session.use_strict_mode', '1');
session_name(SESSION_COOKIE_NAME);
