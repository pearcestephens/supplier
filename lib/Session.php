<?php
/**
 * Standalone Session Manager
 * 
 * Secure session management with:
 * - Auto-start with secure settings
 * - Session fixation protection
 * - Session hijacking protection
 * - Idle timeout management
 * - Flash messages
 * - CSRF token generation
 * 
 * @package Supplier\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

class Session
{
    private static bool $started = false;
    private static int $lifetime = 28800; // 8 hours
    private static int $idleTimeout = 1800; // 30 minutes
    
    /**
     * Start session with secure settings
     * 
     * @param array $options Session configuration options
     * @return bool Success
     */
    public static function start(array $options = []): bool
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        
        // **CRITICAL FIX: Set consistent session name and path for entire supplier portal**
        // This ensures the SAME session cookie is shared between /supplier/index.php and /supplier/api/*
        
        // Set session name (unique to supplier portal to avoid conflicts with main CIS)
        session_name('CIS_SUPPLIER_SESSION');
        
        // Set cookie path to /supplier/ so it's shared across all supplier portal pages and API calls
        ini_set('session.cookie_path', '/supplier/');
        
        // Set cookie domain to match the main domain (staff.vapeshed.co.nz)
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($domain)) {
            // Remove port if present
            $domain = preg_replace('/:\d+$/', '', $domain);
            ini_set('session.cookie_domain', $domain);
        }
        
        // Set ini settings BEFORE starting session
        ini_set('session.cookie_lifetime', (string)self::$lifetime);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string)self::$lifetime);
        
        // Start session without options array
        $result = session_start();
        
        if ($result) {
            self::$started = true;
            
            // Initialize session if new
            if (!self::has('_initialized')) {
                self::set('_initialized', true);
                self::set('_created', time());
                self::set('_ip', self::getClientIp());
                self::set('_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
            }
            
            // Check for session hijacking
            if (!self::validateSession()) {
                self::destroy();
                session_start();
                self::$started = true;
            }
            
            // Update last activity
            self::set('_last_activity', time());
            
            // Check idle timeout
            if (self::isExpired()) {
                self::destroy();
                return false;
            }
        }
        
        return $result;
    }
    
    /**
     * Validate session against hijacking
     * 
     * @return bool Valid session
     */
    private static function validateSession(): bool
    {
        // Check IP address (optional - can be disabled for dynamic IPs)
        $currentIp = self::getClientIp();
        $sessionIp = self::get('_ip', '');
        
        // Allow IP change for now (dynamic IPs common)
        // Can enable strict check: if ($currentIp !== $sessionIp) return false;
        
        // Check User-Agent
        $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionUA = self::get('_user_agent', '');
        
        if ($currentUA !== $sessionUA) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if session is expired (idle timeout)
     * 
     * @return bool Expired
     */
    private static function isExpired(): bool
    {
        $lastActivity = self::get('_last_activity', 0);
        
        if ($lastActivity === 0) {
            return false;
        }
        
        return (time() - $lastActivity) > self::$idleTimeout;
    }
    
    /**
     * Get client IP address (handles proxies)
     * 
     * @return string IP address
     */
    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Check for IP from proxies/load balancers
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $ip;
    }
    
    /**
     * Get session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return mixed Session value or default
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Value to set
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool True if key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     * 
     * @param string $key Session key
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * Get all session data
     * 
     * @return array Session data
     */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }
    
    /**
     * Clear all session data (but keep session active)
     */
    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
            self::$started = false;
        }
    }
    
    /**
     * Regenerate session ID (prevent session fixation)
     * 
     * @param bool $deleteOldSession Delete old session data
     * @return bool Success
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        
        $result = session_regenerate_id($deleteOldSession);
        
        if ($result) {
            // Update session metadata
            self::set('_regenerated', time());
        }
        
        return $result;
    }
    
    /**
     * Set flash message (available only for next request)
     * 
     * @param string $key Flash key
     * @param mixed $value Flash value
     */
    public static function flash(string $key, $value): void
    {
        self::start();
        
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        
        $_SESSION['_flash'][$key] = $value;
    }
    
    /**
     * Get flash message and remove it
     * 
     * @param string $key Flash key
     * @param mixed $default Default value if not found
     * @return mixed Flash value or default
     */
    public static function getFlash(string $key, $default = null)
    {
        self::start();
        
        if (!isset($_SESSION['_flash'][$key])) {
            return $default;
        }
        
        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        
        return $value;
    }
    
    /**
     * Check if flash message exists
     * 
     * @param string $key Flash key
     * @return bool True if exists
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public static function getCsrfToken(): string
    {
        self::start();
        
        if (!self::has('_csrf_token')) {
            self::set('_csrf_token', bin2hex(random_bytes(32)));
        }
        
        return self::get('_csrf_token');
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string|null $token Token to validate (from request)
     * @return bool Valid token
     */
    public static function validateCsrfToken(?string $token = null): bool
    {
        $token = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        $sessionToken = self::get('_csrf_token', '');
        
        return $token !== '' && hash_equals($sessionToken, $token);
    }
    
    /**
     * Get CSRF token HTML input field
     * 
     * @return string HTML input field
     */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
    
    /**
     * Get CSRF token meta tag
     * 
     * @return string HTML meta tag
     */
    public static function csrfMeta(): string
    {
        $token = self::getCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
    
    /**
     * Get session ID
     * 
     * @return string Session ID
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }
    
    /**
     * Get session name
     * 
     * @return string Session name
     */
    public static function getName(): string
    {
        return session_name();
    }
    
    /**
     * Check if session is active
     * 
     * @return bool Active
     */
    public static function isActive(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }
    
    /**
     * Set session lifetime
     * 
     * @param int $seconds Lifetime in seconds
     */
    public static function setLifetime(int $seconds): void
    {
        self::$lifetime = $seconds;
    }
    
    /**
     * Set idle timeout
     * 
     * @param int $seconds Idle timeout in seconds
     */
    public static function setIdleTimeout(int $seconds): void
    {
        self::$idleTimeout = $seconds;
    }
    
    /**
     * Get session statistics
     * 
     * @return array Statistics
     */
    public static function getStats(): array
    {
        self::start();
        
        return [
            'id' => self::getId(),
            'name' => self::getName(),
            'active' => self::isActive(),
            'created' => self::get('_created', 0),
            'last_activity' => self::get('_last_activity', 0),
            'idle_seconds' => time() - self::get('_last_activity', time()),
            'key_count' => count($_SESSION),
        ];
    }
    
    /**
     * Get comprehensive session debug information
     * 
     * @return array Debug info
     */
    public static function getDebugInfo(): array
    {
        $params = session_get_cookie_params();
        
        return [
            'session_status' => [
                'php_session_active' => session_status() === PHP_SESSION_ACTIVE,
                'class_started' => self::$started,
                'session_id' => session_status() === PHP_SESSION_ACTIVE ? session_id() : 'NOT_STARTED',
                'session_name' => session_name(),
            ],
            'cookie_params' => [
                'lifetime' => $params['lifetime'],
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'not_set',
            ],
            'session_data' => [
                'authenticated' => self::get('authenticated', false),
                'supplier_id' => self::get('supplier_id', 'NOT_SET'),
                'supplier_name' => self::get('supplier_name', 'NOT_SET'),
                'initialized' => self::get('_initialized', false),
                'created' => self::get('_created', 0),
                'last_activity' => self::get('_last_activity', 0),
                'idle_time' => session_status() === PHP_SESSION_ACTIVE ? (time() - self::get('_last_activity', time())) : 0,
            ],
            'request_info' => [
                'url' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'client_ip' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
                'cookie_sent' => isset($_COOKIE[session_name()]),
                'cookie_value' => isset($_COOKIE[session_name()]) ? substr($_COOKIE[session_name()], 0, 10) . '...' : 'NOT_SET',
            ],
            'server_info' => [
                'php_version' => PHP_VERSION,
                'session_save_path' => session_save_path(),
                'session_gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            ],
        ];
    }
}
