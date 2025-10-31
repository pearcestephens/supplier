<?php
/**
 * Supplier Portal - Session Management
 * 
 * Handles session initialization, validation, and authentication
 * 
 * @package CIS\Supplier\Config
 * @version 2.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// ============================================================================
// SESSION INITIALIZATION
// ============================================================================

/**
 * Initialize session with secure settings
 * 
 * @return void
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session settings
        ini_set('session.cookie_httponly', SESSION_HTTPONLY ? '1' : '0');
        ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
        ini_set('session.cookie_samesite', SESSION_SAMESITE);
        ini_set('session.cookie_lifetime', (string)SESSION_COOKIE_LIFETIME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Authenticate supplier by ID (UUID format)
 * 
 * @param string $supplierId Supplier ID (UUID)
 * @return bool Success status
 */
function authenticate_supplier(string $supplierId): bool
{
    // Validate UUID format
    if (empty($supplierId) || !validate_uuid($supplierId)) {
        log_security_event('failed_login_invalid_uuid', ['supplier_id' => $supplierId]);
        return false;
    }
    
    // Fetch supplier details from database (using UUID and deleted_at filter)
    $query = "SELECT id, name, email, phone 
              FROM " . TABLE_SUPPLIERS . " 
              WHERE id = ? AND deleted_at = '' 
              LIMIT 1";
    
    $supplier = db_fetch_one($query, [$supplierId], 's');
    
    if (!$supplier) {
        log_security_event('failed_login', ['supplier_id' => $supplierId]);
        return false;
    }
    
    // Check if supplier is allowed (additional validation)
    if (!is_supplier_allowed($supplierId)) {
        log_security_event('blocked_login', ['supplier_id' => $supplierId]);
        return false;
    }
    
    // Set session variables (using UUID from suppliers.id)
    $_SESSION['supplier_id'] = $supplier['id']; // UUID
    $_SESSION['supplier_name'] = $supplier['name'];
    $_SESSION['supplier_email'] = $supplier['email'];
    $_SESSION['supplier_phone'] = $supplier['phone'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['authenticated'] = true;
    
    // Log successful login
    log_supplier_action('login', [
        'supplier_id' => $supplierId,
        'ip' => $_SESSION['ip_address'],
    ]);
    
    // Store session in database
    store_session_db();
    
    return true;
}

/**
 * Check if user is authenticated
 * 
 * @return bool Authentication status
 */
function is_authenticated(): bool
{
    return isset($_SESSION['authenticated']) 
        && $_SESSION['authenticated'] === true 
        && isset($_SESSION['supplier_id']);
}

/**
 * Verify session is valid
 * 
 * @return bool Validation status
 */
function verify_session(): bool
{
    if (!is_authenticated()) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > SESSION_TIMEOUT) {
            destroy_session();
            return false;
        }
    }
    
    // Verify IP hasn't changed (optional security measure)
    if (isset($_SESSION['ip_address']) && ENABLE_IP_WHITELIST) {
        $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($_SESSION['ip_address'] !== $currentIP) {
            log_security_event('session_hijack_attempt', [
                'original_ip' => $_SESSION['ip_address'],
                'current_ip' => $currentIP,
            ]);
            destroy_session();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Check if supplier is allowed to access portal
 * 
 * @param string $supplierId Supplier ID
 * @return bool Allowed status
 */
function is_supplier_allowed(string $supplierId): bool
{
    // Check IP whitelist if enabled
    if (ENABLE_IP_WHITELIST) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (!in_array($ip, ALLOWED_IPS, true)) {
            return false;
        }
    }
    
    // Check rate limiting if enabled
    if (ENABLE_RATE_LIMITING) {
        if (is_rate_limited($supplierId)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if supplier is rate limited
 * 
 * @param string $supplierId Supplier ID
 * @return bool Rate limited status
 */
function is_rate_limited(string $supplierId): bool
{
    $key = 'login_attempts_' . $supplierId;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'timestamp' => time()];
    }
    
    $attempts = $_SESSION[$key];
    
    // Reset if window expired
    if (time() - $attempts['timestamp'] > RATE_LIMIT_WINDOW) {
        $_SESSION[$key] = ['count' => 0, 'timestamp' => time()];
        return false;
    }
    
    // Check if exceeded limit
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    
    return false;
}

/**
 * Get current supplier session data
 * 
 * @return array|null Session data or null if not authenticated
 */
function get_session(): ?array
{
    if (!is_authenticated()) {
        return null;
    }
    
    return [
        'supplier_id' => $_SESSION['supplier_id'] ?? '',
        'supplier_name' => $_SESSION['supplier_name'] ?? '',
        'supplier_email' => $_SESSION['supplier_email'] ?? '',
        'supplier_phone' => $_SESSION['supplier_phone'] ?? '',
        'last_activity' => $_SESSION['last_activity'] ?? 0,
        'ip_address' => $_SESSION['ip_address'] ?? '',
    ];
}

/**
 * Get supplier ID from session
 * 
 * @return string|null Supplier ID or null
 */
function get_supplier_id(): ?string
{
    return $_SESSION['supplier_id'] ?? null;
}

/**
 * Store session in database
 * 
 * @return void
 */
function store_session_db(): void
{
    $supplierId = get_supplier_id();
    
    if (!$supplierId) {
        return;
    }
    
    $query = "INSERT INTO " . TABLE_SUPPLIER_SESSIONS . " 
              (supplier_id, session_id, ip_address, user_agent, created_at, last_activity) 
              VALUES (?, ?, ?, ?, NOW(), NOW())
              ON DUPLICATE KEY UPDATE 
              last_activity = NOW()";
    
    db_execute($query, [
        $supplierId,
        session_id(),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
    ], 'ssss');
}

/**
 * Destroy session and logout
 * 
 * @return void
 */
function destroy_session(): void
{
    $supplierId = get_supplier_id();
    
    if ($supplierId) {
        // Log logout
        log_supplier_action('logout', ['supplier_id' => $supplierId]);
        
        // Remove session from database
        $query = "DELETE FROM " . TABLE_SUPPLIER_SESSIONS . " 
                  WHERE supplier_id = ? AND session_id = ?";
        
        db_execute($query, [$supplierId, session_id()], 'ss');
    }
    
    // Clear session data
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            SESSION_NAME,
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Require authentication - redirect to login if not authenticated
 * 
 * @return void
 */
function require_auth(): void
{
    if (!verify_session()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generate_csrf_token(): string
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 * 
 * @param string|null $token Token to verify
 * @return bool Validation status
 */
function verify_csrf_token(?string $token): bool
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get CSRF token input field (HTML)
 * 
 * @return string HTML input field
 */
function csrf_field(): string
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token meta tag (HTML)
 * 
 * @return string HTML meta tag
 */
function csrf_meta(): string
{
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}
