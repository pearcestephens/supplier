<?php
/**
 * Supplier Portal - Main Configuration
 * 
 * Central configuration file for all supplier portal settings
 * 
 * @package CIS\Supplier\Config
 * @version 3.0.0 - Updated for UUID suppliers and ML performance tracking
 * @author The Vape Shed
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// PORTAL SETTINGS
// ============================================================================

define('PORTAL_VERSION', '3.0.0');
define('PORTAL_NAME', 'The Vape Shed Supplier Portal');
define('PORTAL_TIMEZONE', 'Pacific/Auckland');

// Set timezone
date_default_timezone_set(PORTAL_TIMEZONE);

// ============================================================================
// SUPPLIER ID SETTINGS (UUID FORMAT)
// ============================================================================

// Suppliers use UUID format from Vend API
define('SUPPLIER_ID_FORMAT', 'UUID'); // UUIDs are 36 characters (with hyphens)
define('SUPPLIER_ID_REGEX', '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');

// ============================================================================
// SESSION SETTINGS
// ============================================================================

define('SESSION_TIMEOUT', 3600);        // 1 hour in seconds
define('SESSION_NAME', 'SUPPLIER_PORTAL_SESSION');
define('SESSION_COOKIE_LIFETIME', 0);   // Browser session
define('SESSION_SECURE', true);          // HTTPS only (set false for dev)
define('SESSION_HTTPONLY', true);        // No JavaScript access
define('SESSION_SAMESITE', 'Strict');    // CSRF protection

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

define('ENABLE_IP_WHITELIST', false);    // Set true to enable IP restrictions
define('ALLOWED_IPS', ['125.236.217.224', '127.0.0.1']);

define('ENABLE_RATE_LIMITING', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900);        // 15 minutes

define('CSRF_TOKEN_NAME', '_csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// ============================================================================
// DATABASE SETTINGS
// ============================================================================

// Using main CIS database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'vend_sales');
define('DB_CHARSET', 'utf8mb4');

// Database tables
define('TABLE_SUPPLIERS', 'suppliers'); // UUID id column, deleted_at for soft delete
define('TABLE_PURCHASE_ORDERS', 'purchase_orders');
define('TABLE_WARRANTY_CLAIMS', 'warranty_claims');
define('TABLE_SUPPLIER_LOGS', 'supplier_portal_logs');
define('TABLE_SUPPLIER_SESSIONS', 'supplier_sessions');

// ML Performance Tracking tables
define('TABLE_PERFORMANCE_METRICS', 'supplier_portal_performance_metrics');
define('TABLE_PERFORMANCE_BASELINES', 'supplier_portal_performance_baselines');
define('TABLE_ADAPTIVE_CACHE', 'supplier_portal_adaptive_cache');
define('TABLE_PERFORMANCE_RECOMMENDATIONS', 'supplier_portal_performance_recommendations');
define('TABLE_LOAD_FORECAST', 'supplier_portal_load_forecast');

// ============================================================================
// MACHINE LEARNING SETTINGS
// ============================================================================

define('ML_ENABLED', true);              // Enable ML performance tracking
define('ML_SAMPLE_RATE', 1.0);           // 1.0 = track all queries, 0.1 = 10% sampling
define('ML_BASELINE_MIN_SAMPLES', 10);   // Minimum samples before creating baseline
define('ML_ANOMALY_THRESHOLD', 0.5);     // 50% deviation triggers anomaly
define('ML_CACHE_ADJUST_MIN', 0.5);      // Min TTL multiplier (50% of base)
define('ML_CACHE_ADJUST_MAX', 3.0);      // Max TTL multiplier (3x base)
define('ML_AUTO_OPTIMIZE', true);        // Auto-apply safe optimizations
define('ML_FORECAST_ENABLED', true);     // Enable load forecasting

// ============================================================================
// FEATURE FLAGS
// ============================================================================

define('FEATURE_NEURO_AI', true);
define('FEATURE_BULK_DOWNLOADS', true);
define('FEATURE_WARRANTY_AUTO_ACCEPT', false);
define('FEATURE_ADVANCED_REPORTING', true);
define('FEATURE_EMAIL_NOTIFICATIONS', true);

// ============================================================================
// FILE UPLOAD SETTINGS
// ============================================================================

define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);  // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// ============================================================================
// PAGINATION SETTINGS
// ============================================================================

define('ITEMS_PER_PAGE', 50);
define('MAX_ITEMS_PER_PAGE', 200);

// ============================================================================
// API SETTINGS
// ============================================================================

define('API_TIMEOUT', 30);
define('API_MAX_RETRIES', 3);

// Neuro AI Configuration
$GLOBALS['neuro_ai_config'] = [
    'endpoint' => getenv('NEURO_API_ENDPOINT') ?: 'https://api.neurodao.ai/v1/chat',
    'api_key' => getenv('NEURO_API_KEY') ?: '',
    'model' => 'neuro-large',
    'temperature' => 0.7,
    'max_tokens' => 500,
    'enabled' => FEATURE_NEURO_AI,
];

// ============================================================================
// EMAIL SETTINGS
// ============================================================================

define('EMAIL_FROM_ADDRESS', 'noreply@vapeshed.co.nz');
define('EMAIL_FROM_NAME', 'The Vape Shed Supplier Portal');
define('EMAIL_ADMIN', 'suppliers@vapeshed.co.nz');

// ============================================================================
// LOGGING SETTINGS
// ============================================================================

define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'INFO');  // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', 10 * 1024 * 1024);  // 10MB
define('LOG_ROTATION', true);

// ============================================================================
// UI SETTINGS
// ============================================================================

define('THEME_PRIMARY_COLOR', '#1a252f');
define('THEME_SECONDARY_COLOR', '#2c3e50');
define('THEME_ACCENT_COLOR', '#3498db');

define('DASHBOARD_REFRESH_INTERVAL', 300);  // 5 minutes in seconds
define('SHOW_DEBUG_INFO', false);           // Set true for development

// ============================================================================
// PATHS
// ============================================================================

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('FUNCTIONS_PATH', BASE_PATH . '/functions');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('VIEWS_PATH', BASE_PATH . '/views');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('API_PATH', BASE_PATH . '/api');

// ============================================================================
// URLS
// ============================================================================

define('BASE_URL', '/supplier/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('CSS_URL', ASSETS_URL . 'css/');
define('JS_URL', ASSETS_URL . 'js/');
define('IMAGES_URL', ASSETS_URL . 'images/');
define('API_URL', BASE_URL . 'api/');

// ============================================================================
// ERROR REPORTING
// ============================================================================

if (SHOW_DEBUG_INFO) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . 'php-errors.log');
}

// ============================================================================
// AUTOLOAD FUNCTIONS
// ============================================================================

// Load all function files
$functionFiles = glob(FUNCTIONS_PATH . '/*.php');
if ($functionFiles) {
    foreach ($functionFiles as $file) {
        require_once $file;
    }
}
