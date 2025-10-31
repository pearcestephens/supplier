<?php
/**
 * Auth Helper Class
 * 
 * Simple session-based authentication
 * No over-engineering - just what we need
 * 
 * @package SupplierPortal\Lib
 * @version 3.0.0
 */

declare(strict_types=1);

class AuthHelper
{
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['supplier_id']) && 
               isset($_SESSION['session_token']) &&
               self::validateSession();
    }
    
    /**
     * Validate session token against database
     */
    private static function validateSession(): bool
    {
        if (!isset($_SESSION['session_token'])) {
            return false;
        }
        
        $sql = "
            SELECT supplier_id 
            FROM supplier_portal_sessions 
            WHERE session_token = ? 
            AND expires_at > NOW()
            LIMIT 1
        ";
        
        $result = DatabasePDO::fetchOne($sql, [$_SESSION['session_token']]);
        
        return $result !== null;
    }
    
    /**
     * Get authenticated supplier ID (UUID)
     */
    public static function getSupplierId(): ?string
    {
        return $_SESSION['supplier_id'] ?? null;
    }
    
    /**
     * Get supplier name
     */
    public static function getSupplierName(): ?string
    {
        $supplierId = self::getSupplierId();
        if (!$supplierId) {
            return null;
        }
        
        $sql = "SELECT name FROM vend_suppliers WHERE id = ? LIMIT 1";
        $result = DatabasePDO::fetchOne($sql, [$supplierId]);
        
        return $result['name'] ?? null;
    }
    
    /**
     * Get supplier email
     */
    public static function getEmail(): ?string
    {
        $supplierId = self::getSupplierId();
        if (!$supplierId) {
            return null;
        }
        
        $sql = "SELECT email FROM vend_suppliers WHERE id = ? LIMIT 1";
        $result = DatabasePDO::fetchOne($sql, [$supplierId]);
        
        return $result['email'] ?? null;
    }
    
    /**
     * Get session token
     */
    public static function getSessionToken(): ?string
    {
        return $_SESSION['session_token'] ?? null;
    }
    
    /**
     * Login supplier
     */
    public static function login(string $supplierId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate secure session token
        $sessionToken = bin2hex(random_bytes(32));
        
        // Store in database
        $sql = "
            INSERT INTO supplier_portal_sessions 
            (supplier_id, session_token, expires_at, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
        ";
        
        DatabasePDO::execute($sql, [
            $supplierId,
            $sessionToken,
            SESSION_LIFETIME
        ]);
        
        // Store in session
        $_SESSION['supplier_id'] = $supplierId;
        $_SESSION['session_token'] = $sessionToken;
        
        // Log activity
        self::logActivity($supplierId, 'login', []);
        
        return $sessionToken;
    }
    
    /**
     * Logout supplier
     */
    public static function logout(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $supplierId = self::getSupplierId();
        $sessionToken = self::getSessionToken();
        
        // Delete from database
        if ($sessionToken) {
            $sql = "DELETE FROM supplier_portal_sessions WHERE session_token = ?";
            DatabasePDO::execute($sql, [$sessionToken]);
        }
        
        // Log activity
        if ($supplierId) {
            self::logActivity($supplierId, 'logout', []);
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        return true;
    }
    
    /**
     * Refresh session expiry
     */
    public static function refreshSession(): bool
    {
        $sessionToken = self::getSessionToken();
        if (!$sessionToken) {
            return false;
        }
        
        $sql = "
            UPDATE supplier_portal_sessions 
            SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE session_token = ?
        ";
        
        return DatabasePDO::execute($sql, [SESSION_LIFETIME, $sessionToken]) > 0;
    }
    
    /**
     * Log supplier activity
     */
    private static function logActivity(string $supplierId, string $action, array $meta): void
    {
        $sql = "
            INSERT INTO supplier_activity_log 
            (supplier_id, action, resource_type, resource_id, meta, created_at)
            VALUES (?, ?, NULL, NULL, ?, NOW())
        ";
        
        DatabasePDO::execute($sql, [
            $supplierId,
            $action,
            json_encode($meta)
        ]);
    }
    
    /**
     * Require authentication (redirect if not authenticated)
     */
    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            header('Location: /supplier/login.php');
            exit;
        }
    }
}
