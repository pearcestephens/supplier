<?php
/**
 * Standalone Authentication Manager
 *
 * Manages supplier authentication with:
 * - Supplier ID-based authentication
 * - Session management
 * - Login/logout functionality
 * - Authentication checks
 *
 * @package Supplier\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth
{
    /**
     * Authenticate supplier by ID
     *
     * @param string $supplierId Supplier ID
     * @return bool Success
     */
    public static function loginById(string $supplierId): bool
    {
        try {
            // Validate supplier exists in database
            $supplier = Database::queryOne("
                SELECT id, name, email
                FROM vend_suppliers
                WHERE id = ?
                AND (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
                LIMIT 1
            ", [$supplierId]);

            if (!$supplier) {
                return false;
            }

            // Create session
            Session::start();

            // Set session data BEFORE regenerating
            Session::set('supplier_id', $supplier['id']);
            Session::set('supplier_name', $supplier['name']);
            Session::set('supplier_email', $supplier['email'] ?? '');
            Session::set('authenticated', true);
            Session::set('login_time', time());

            // Regenerate session ID (prevent session fixation)
            // This MUST be done AFTER setting data so it's copied to the new session
            Session::regenerate();

            return true;

        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if supplier is authenticated
     *
     * @return bool Authenticated
     */
    public static function check(): bool
    {
        // DEBUG MODE: Bypass session requirements
        if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true) {
            return self::initializeDebugMode();
        }

        Session::start();

        return Session::get('authenticated', false) === true
            && Session::has('supplier_id')
            && !empty(Session::get('supplier_id'));
    }

    /**
     * Initialize DEBUG MODE - hardcoded supplier without session
     *
     * Allows testing without session/cookie overhead
     * Validates supplier exists in database
     * Logs all debug mode access
     *
     * @return bool Success
     */
    private static function initializeDebugMode(): bool
    {
        // Allow dynamic debug supplier via URL: ?supplier_id=...
        $debugSupplierId = null;
        if (isset($_GET['supplier_id']) && is_string($_GET['supplier_id']) && $_GET['supplier_id'] !== '') {
            $debugSupplierId = $_GET['supplier_id'];
        } elseif (defined('DEBUG_MODE_SUPPLIER_ID') && DEBUG_MODE_SUPPLIER_ID !== '') {
            $debugSupplierId = DEBUG_MODE_SUPPLIER_ID;
        } else {
            return false; // no debug supplier specified
        }

        // Validate supplier exists in database
        $supplier = Database::queryOne("
            SELECT id, name, email
            FROM vend_suppliers
            WHERE id = ?
            AND (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
            LIMIT 1
        ", [$debugSupplierId]);

        if (!$supplier) {
            error_log("DEBUG MODE: Supplier ID {$debugSupplierId} not found or deleted");
            return false;
        }

        // CRITICAL: Start session before writing to $_SESSION
        Session::start();

        // Set in-memory session data (no database calls needed)
        $_SESSION['debug_mode'] = true;
        $_SESSION['supplier_id'] = $supplier['id'];
        $_SESSION['supplier_name'] = $supplier['name'];
        $_SESSION['supplier_email'] = $supplier['email'] ?? '';
        $_SESSION['authenticated'] = true;
        $_SESSION['debug_login_time'] = time();
        $_SESSION['debug_timestamp'] = date('Y-m-d H:i:s');

        // Log debug mode access (audit trail)
        $debugLog = __DIR__ . '/../logs/debug-mode.log';
        if (!file_exists(dirname($debugLog))) {
            mkdir(dirname($debugLog), 0755, true);
        }

        $logEntry = sprintf(
            "[%s] DEBUG MODE ACTIVE - Supplier ID: %s | User IP: %s | User Agent: %s | Page: %s\n",
            date('Y-m-d H:i:s'),
            $debugSupplierId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100),
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );

        file_put_contents($debugLog, $logEntry, FILE_APPEND);

        return true;
    }

    /**
     * Require authentication (redirect or JSON error if not authenticated)
     *
     * In DEBUG MODE, this check is bypassed automatically
     *
     * @param string $redirectUrl Redirect URL if not authenticated
     * @param bool $json Return JSON error instead of redirect
     */
    public static function require(string $redirectUrl = '/supplier/login.php', bool $json = false): void
    {
        // DEBUG MODE: Skip auth requirement entirely
        if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true) {
            if (self::initializeDebugMode()) {
                return; // Authentication satisfied in debug mode
            }
        }

        if (!self::check()) {
            if ($json || self::isAjaxRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'code' => 401,
                ]);
                exit;
            } else {
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }

    /**
     * Check if request is AJAX
     *
     * @return bool AJAX request
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get authenticated supplier ID
     *
     * Works in normal mode AND debug mode
     *
     * @return string|null Supplier ID or null if not authenticated
     */
    public static function getSupplierId(): ?string
    {
        // DEBUG MODE: Return hardcoded supplier ID
        if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true) {
            if (self::initializeDebugMode()) {
                return (string)DEBUG_MODE_SUPPLIER_ID;
            }
            return null;
        }

        if (!self::check()) {
            return null;
        }

        return Session::get('supplier_id');
    }

    /**
     * Get authenticated supplier name
     *
     * @return string|null Supplier name or null if not authenticated
     */
    public static function getSupplierName(): ?string
    {
        if (!self::check()) {
            return null;
        }

        return Session::get('supplier_name');
    }

    /**
     * Get authenticated supplier email
     *
     * @return string|null Supplier email or null if not authenticated
     */
    public static function getSupplierEmail(): ?string
    {
        if (!self::check()) {
            return null;
        }

        return Session::get('supplier_email');
    }

    /**
     * Get authenticated supplier data
     *
     * @return array|null Supplier data or null if not authenticated
     */
    public static function getSupplier(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => Session::get('supplier_id'),
            'name' => Session::get('supplier_name'),
            'email' => Session::get('supplier_email'),
            'login_time' => Session::get('login_time'),
        ];
    }

    /**
     * Logout supplier
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Get authentication statistics
     *
     * @return array Statistics
     */
    public static function getStats(): array
    {
        return [
            'authenticated' => self::check(),
            'supplier_id' => self::getSupplierId(),
            'supplier_name' => self::getSupplierName(),
            'login_time' => Session::get('login_time', 0),
            'session_active' => Session::isActive(),
        ];
    }
}
