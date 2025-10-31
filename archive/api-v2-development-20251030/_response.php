<?php
/**
 * API Response Helper
 * Standard JSON response format for all API endpoints
 * 
 * Part of: Supplier Portal Redesign - Phase 1
 * Created: October 22, 2025
 * Version: 2.0
 * 
 * @package SupplierPortal\API\v2
 */

declare(strict_types=1);

/**
 * Send standardized API response
 * 
 * @param bool $success Success status
 * @param mixed $data Response data (null for errors)
 * @param mixed $error Error information (null for success)
 * @param array $meta Additional metadata
 * @return void
 */
function apiResponse(bool $success, mixed $data = null, mixed $error = null, array $meta = []): void {
    // Set proper headers
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => uniqid('req_', true),
        'api_version' => '2.0'
    ];
    
    if ($success) {
        $response['data'] = $data;
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
    } else {
        $response['error'] = $error;
        // Log error for debugging
        error_log("API Error: " . json_encode($error));
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message Error message
 * @param int $code HTTP status code
 * @param mixed $details Additional error details
 * @return void
 */
function apiError(string $message, int $code = 400, mixed $details = null): void {
    http_response_code($code);
    
    $error = [
        'message' => $message,
        'code' => $code
    ];
    
    if ($details !== null) {
        $error['details'] = $details;
    }
    
    apiResponse(false, null, $error);
}

/**
 * Send success response
 * 
 * @param mixed $data Response data
 * @param array $meta Additional metadata
 * @return void
 */
function apiSuccess(mixed $data, array $meta = []): void {
    apiResponse(true, $data, null, $meta);
}

/**
 * Validate required parameters
 * 
 * @param array $params Parameters to validate
 * @param array $required Required parameter names
 * @return bool
 */
function validateRequired(array $params, array $required): bool {
    foreach ($required as $field) {
        if (!isset($params[$field]) || $params[$field] === '') {
            apiError("Missing required parameter: {$field}", 400);
            return false; // Never reached due to exit in apiError
        }
    }
    return true;
}

/**
 * Sanitize input data
 * 
 * @param mixed $data Data to sanitize
 * @return mixed
 */
function sanitizeInput(mixed $data): mixed {
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return $data;
}

/**
 * Check authentication
 * 
 * @return bool
 */
function requireAuth(): bool {
    session_start();
    
    if (!isset($_SESSION['supplier_id']) || !isset($_SESSION['supplier_name'])) {
        apiError('Unauthorized - Please log in', 401);
        return false; // Never reached due to exit in apiError
    }
    
    return true;
}

/**
 * Get current supplier ID from session
 * 
 * @return string|null
 */
function getCurrentSupplierId(): ?string {
    session_start();
    return $_SESSION['supplier_id'] ?? null;
}

/**
 * Log API access for audit trail
 * 
 * @param string $endpoint
 * @param string $method
 * @param string $supplier_id
 * @return void
 */
function logApiAccess(string $endpoint, string $method, string $supplier_id): void {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'method' => $method,
        'supplier_id' => $supplier_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $log_file = __DIR__ . '/../../logs/api_access.log';
    
    // Ensure logs directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(
        $log_file, 
        json_encode($log_entry) . "\n", 
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Rate limiting check
 * 
 * @param string $key Rate limit key (usually IP or supplier_id)
 * @param int $max_requests Maximum requests
 * @param int $time_window Time window in seconds
 * @return bool
 */
function checkRateLimit(string $key, int $max_requests = 100, int $time_window = 3600): bool {
    $rate_file = __DIR__ . '/../../cache/rate_limit_' . md5($key) . '.json';
    
    $now = time();
    $data = [];
    
    if (file_exists($rate_file)) {
        $content = file_get_contents($rate_file);
        if ($content) {
            $data = json_decode($content, true) ?? [];
        }
    }
    
    // Clean old entries
    $data = array_filter($data, function($timestamp) use ($now, $time_window) {
        return ($now - $timestamp) < $time_window;
    });
    
    // Check if limit exceeded
    if (count($data) >= $max_requests) {
        apiError('Rate limit exceeded. Please try again later.', 429);
        return false; // Never reached
    }
    
    // Add current request
    $data[] = $now;
    
    // Ensure cache directory exists
    $cache_dir = dirname($rate_file);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Save updated data
    file_put_contents($rate_file, json_encode($data));
    
    return true;
}