<?php
/**
 * Unified API Endpoint
 * 
 * Single endpoint for all supplier portal API requests
 * Uses standard envelope format for request/response
 * 
 * @package SupplierPortal
 * @version 3.0.0 - Enhanced with comprehensive error handling
 */

declare(strict_types=1);

// Load bootstrap for unified initialization and error handling
require_once dirname(__DIR__) . '/bootstrap.php';

// API-specific: Force JSON responses, override bootstrap HTML error pages
ini_set('display_errors', '0');

// Start output buffering to catch any stray output
ob_start();

// Initialize request variable
$request = [];

try {
    // Parse request
    $request = parseRequest();
    
    // Validate envelope format
    validateRequest($request);
    
    // Check authentication (except for login action)
    if ($request['action'] !== 'auth.login') {
        requireAuth(); // Uses bootstrap helper
        $supplierId = getSupplierID();
    } else {
        $supplierId = null;
    }
    
    // Route to appropriate handler
    $result = routeRequest($request, $supplierId);
    
    // Send success response
    sendResponse(true, $result['data'], $result['message'] ?? null, 200, $result['meta'] ?? []);
    
} catch (Exception $e) {
    // Build comprehensive error info
    $errorInfo = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Add debug info if in debug mode
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorInfo['trace'] = explode("\n", $e->getTraceAsString());
        $errorInfo['request'] = [
            'action' => $request['action'] ?? 'unknown',
            'params' => $request['params'] ?? [],
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
    }
    
    // Log error
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Send error response
    sendResponse(
        false, 
        null, 
        $e->getMessage(), 
        $e->getCode() ?: 500,
        ['error' => $errorInfo]
    );
}

/**
 * Parse incoming request
 */
function parseRequest(): array
{
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get raw input
    $rawInput = file_get_contents('php://input');
    
    // Try to parse as JSON first
    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }
    }
    
    // Fallback to POST/GET
    if ($method === 'POST') {
        return $_POST;
    }
    
    return $_GET;
}

/**
 * Validate request envelope
 */
function validateRequest(array $request): void
{
    if (!isset($request['action'])) {
        throw new Exception('Missing required field: action', 400);
    }
    
    if (!is_string($request['action']) || empty($request['action'])) {
        throw new Exception('Invalid action format', 400);
    }
}

/**
 * Route request to appropriate handler
 */
function routeRequest(array $request, ?string $supplierId): array
{
    $action = $request['action'];
    $params = $request['params'] ?? [];
    
    // Split action into module.method format
    $parts = explode('.', $action);
    if (count($parts) !== 2) {
        throw new Exception('Invalid action format. Expected: module.method', 400);
    }
    
    [$module, $method] = $parts;
    
    // Load handler file
    $handlerFile = __DIR__ . '/handlers/' . $module . '.php';
    if (!file_exists($handlerFile)) {
        throw new Exception("Unknown module: {$module}", 404);
    }
    
    require_once $handlerFile;
    
    // Build handler class name
    $className = 'Handler_' . ucfirst($module);
    if (!class_exists($className)) {
        throw new Exception("Handler class not found: {$className}", 500);
    }
    
    // Get PDO instance
    $pdo = DatabasePDO::getInstance();
    
    // Instantiate handler with PDO and supplier ID
    $handler = new $className($pdo, $supplierId);
    
    // Check method exists
    if (!method_exists($handler, $method)) {
        throw new Exception("Unknown method: {$method} in module: {$module}", 404);
    }
    
    // Call method with params
    $data = $handler->$method($params);
    
    // Return standardized result
    return [
        'data' => $data,
        'message' => null,
        'meta' => []
    ];
}

/**
 * Send standardized JSON response
 * Enhanced with comprehensive error information
 */
function sendResponse(bool $success, $data, ?string $message, int $httpCode, array $meta = []): void
{
    // Clear any buffered output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Build response envelope
    $response = [
        'success' => $success
    ];
    
    if ($success) {
        $response['data'] = $data;
        if ($message) {
            $response['message'] = $message;
        }
    } else {
        // Error response structure
        $response['error'] = [
            'message' => $message ?? 'An error occurred',
            'code' => $httpCode
        ];
        
        // Add detailed error info from meta if available
        if (isset($meta['error'])) {
            $response['error'] = array_merge($response['error'], $meta['error']);
            unset($meta['error']); // Remove from meta to avoid duplication
        }
        
        // Add data if provided (for validation errors, etc.)
        if ($data !== null) {
            $response['data'] = $data;
        }
    }
    
    // Add meta information
    $response['meta'] = array_merge($meta, [
        'timestamp' => date('Y-m-d H:i:s'),
        'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
        'request_id' => uniqid('req_', true)
    ]);
    
    // Set headers
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-API-Version: 3.0.0');
    header('X-Request-ID: ' . $response['meta']['request_id']);
    
    // Output response
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Note: Error handlers (handleError, handleException, handleShutdown) are now
// provided by bootstrap.php for consistent error handling across the application
