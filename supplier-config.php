<?php
/**
 * Supplier Portal - Configuration
 * 
 * Central config for supplier portal settings
 * 
 * @package CIS\Supplier
 */

declare(strict_types=1);

// Database connection (uses main CIS connection)
// Assume app.php has already been loaded by the calling script
// No need to require it again here - $db should already be available

// Supplier Portal Settings
define('SUPPLIER_PORTAL_VERSION', '2.0.0');
define('SUPPLIER_SESSION_TIMEOUT', 3600); // 1 hour
define('SUPPLIER_MAX_DOWNLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('SUPPLIER_ITEMS_PER_PAGE', 50);

// Feature Flags
define('FEATURE_NEURO_AI_ENABLED', true);
define('FEATURE_BULK_DOWNLOADS_ENABLED', true);
define('FEATURE_WARRANTY_AUTO_ACCEPT', false);

// Allowed file extensions for media
$GLOBALS['allowed_media_extensions'] = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];

// Neuro AI Configuration
$GLOBALS['neuro_ai_config'] = [
    'endpoint' => 'https://api.neurodao.ai/v1/chat', // Example endpoint
    'api_key' => getenv('NEURO_API_KEY') ?: '',
    'model' => 'neuro-large',
    'temperature' => 0.7,
    'max_tokens' => 500,
];

// Helper Functions
function get_supplier_session(): ?array
{
    if (!isset($_SESSION['supplier_id'])) {
        return null;
    }
    
    return [
        'supplier_id' => $_SESSION['supplier_id'],
        'supplier_name' => $_SESSION['supplier_name'] ?? 'Unknown Supplier',
        'email' => $_SESSION['supplier_email'] ?? '',
        'last_activity' => $_SESSION['last_activity'] ?? time(),
    ];
}

function verify_supplier_auth(): bool
{
    $session = get_supplier_session();
    
    if (!$session) {
        return false;
    }
    
    // Check session timeout
    if ((time() - $session['last_activity']) > SUPPLIER_SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

function log_supplier_action(string $action, array $data = []): void
{
    global $db;
    
    $session = get_supplier_session();
    
    if (!$session) {
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO supplier_portal_logs (supplier_id, action, data, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        'sssss',
        $session['supplier_id'],
        $action,
        json_encode($data),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    );
    
    $stmt->execute();
}
