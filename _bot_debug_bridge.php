<?php
/**
 * Supplier Debug Bridge - Unified Debug Mode Accessor
 *
 * Provides a single point of access for supplier ID that respects debug mode.
 * Uses existing DEBUG_MODE_ENABLED and DEBUG_MODE_SUPPLIER_ID constants.
 *
 * When debug is ON:
 * - Returns forced supplier ID (DEBUG_MODE_SUPPLIER_ID)
 * - Sets debug headers (X-Supplier-Debug, X-Supplier-Id)
 * - Bypasses authentication requirements
 *
 * When debug is OFF:
 * - Returns authenticated supplier ID from session/Auth
 * - Normal authentication flow applies
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

if (!function_exists('supplier_current_id_bridge')) {
    /**
     * Get current supplier ID with debug mode support
     *
     * This is the ONLY function all supplier code should use to get supplier ID.
     * It automatically handles debug vs normal mode.
     *
     * @return string Supplier ID (UUID format) or empty string if not authenticated
     */
    function supplier_current_id_bridge(): string
    {
        // Check if debug mode is enabled (using existing constants)
        $debugOn = defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true;

        if ($debugOn) {
            // Get forced supplier ID from existing debug constant
            $forcedId = defined('DEBUG_MODE_SUPPLIER_ID') ? DEBUG_MODE_SUPPLIER_ID : '';

            if (!empty($forcedId)) {
                // Set debug headers for observability
                if (!headers_sent()) {
                    header('X-Supplier-Debug: 1');
                    header('X-Supplier-Id: ' . $forcedId);
                }

                return (string)$forcedId;
            }
        }

        // Normal mode: use existing Auth system
        if (class_exists('Auth') && method_exists('Auth', 'getSupplierId')) {
            $sid = Auth::getSupplierId();
            if (!empty($sid)) {
                return (string)$sid;
            }
        }

        // Fallback: check session directly
        if (!empty($_SESSION['supplier_id'])) {
            return (string)$_SESSION['supplier_id'];
        }

        // Not authenticated
        return '';
    }
}

if (!function_exists('supplier_debug_mode_active')) {
    /**
     * Check if debug mode is currently active
     *
     * @return bool True if debug mode is enabled
     */
    function supplier_debug_mode_active(): bool
    {
        return defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED === true;
    }
}

if (!function_exists('supplier_require_auth_bridge')) {
    /**
     * Require authentication or fail with appropriate response
     *
     * In debug mode: automatically passes (uses forced supplier ID)
     * In normal mode: checks authentication and redirects/responds accordingly
     *
     * @param bool $isApi Whether this is an API endpoint (JSON response)
     * @return void Exits if not authenticated (except in debug mode)
     */
    function supplier_require_auth_bridge(bool $isApi = false): void
    {
        // Debug mode: always pass authentication
        if (supplier_debug_mode_active()) {
            return;
        }

        // Normal mode: check authentication
        if (class_exists('Auth') && method_exists('Auth', 'check')) {
            if (Auth::check()) {
                return; // Authenticated
            }
        } elseif (!empty($_SESSION['authenticated']) && !empty($_SESSION['supplier_id'])) {
            return; // Authenticated via session
        }

        // Not authenticated - respond accordingly
        if ($isApi) {
            // API response
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentication required to access this resource',
                    'type' => 'AuthenticationError'
                ],
                'timestamp' => date('c')
            ]);
        } else {
            // Web page redirect
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '/supplier/dashboard.php';
            header('Location: /supplier/login.php?redirect=' . urlencode($currentUrl));
        }

        exit;
    }
}
