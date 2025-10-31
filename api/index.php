<?php
/**
 * Unified API Endpoint - Single entry point for all supplier portal API calls
 *
 * STANDARD JSON ENVELOPE (ALL RESPONSES):
 * {
 *   "success": true|false,
 *   "data": {...},           // Only on success
 *   "message": "...",         // Human-readable message
 *   "error": {                // Only on failure
 *     "code": "ERROR_CODE",
 *     "message": "User-friendly error",
 *     "details": "Technical details",
 *     "field": "fieldName"   // Optional: for validation errors
 *   },
 *   "timestamp": "2025-10-30T12:00:00Z",
 *   "request_id": "unique-id"
 * }
 *
 * Usage: POST /supplier/api/?action=actionName
 * Or:    POST /supplier/api/ with JSON body: {"action": "actionName", ...}
 *
 * @package Supplier\Portal\API
 * @version 2.0.0
 */

declare(strict_types=1);

// Generate unique request ID for tracking
$requestId = uniqid('req_', true);

// Bootstrap application
require_once dirname(__DIR__) . '/bootstrap.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Request-ID: ' . $requestId);

/**
 * Send standardized API response
 */
function sendApiResponse(bool $success, $data = null, string $message = '', ?array $error = null, int $httpCode = 200): void {
    global $requestId;

    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c'),
        'request_id' => $requestId
    ];

    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error ?? [
            'code' => 'UNKNOWN_ERROR',
            'message' => $message ?: 'An unknown error occurred',
            'details' => null
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Only accept POST requests (except for health check)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'health') {
    sendApiResponse(true, ['status' => 'ok', 'version' => '2.0.0'], 'API is healthy');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiResponse(false, null, 'Method not allowed', [
        'code' => 'METHOD_NOT_ALLOWED',
        'message' => 'This endpoint only accepts POST requests',
        'details' => 'Use POST with action parameter or JSON body'
    ], 405);
}

// Get action from query string, POST data, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    // Try to parse JSON body
    $jsonInput = file_get_contents('php://input');
    if ($jsonInput) {
        $jsonData = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['action'])) {
            $action = $jsonData['action'];
            $_POST = array_merge($_POST, $jsonData); // Merge into $_POST for modules
        }
    }
}

if (!$action) {
    sendApiResponse(false, null, 'Missing required parameter', [
        'code' => 'MISSING_ACTION',
        'message' => 'The "action" parameter is required',
        'details' => 'Send as query string (?action=...) or in POST/JSON body'
    ], 400);
}

// Security: Validate action name (alphanumeric, hyphens, underscores only)
if (!preg_match('/^[a-z0-9_-]+$/i', $action)) {
    sendApiResponse(false, null, 'Invalid action parameter', [
        'code' => 'INVALID_ACTION',
        'message' => 'Action name contains invalid characters',
        'details' => 'Only alphanumeric, hyphens, and underscores allowed'
    ], 400);
}

// Load action modules
$modulesDir = __DIR__ . '/modules';
$actionFile = $modulesDir . '/' . $action . '.php';

// Check if action module exists
if (!file_exists($actionFile)) {
    sendApiResponse(false, null, 'Unknown action', [
        'code' => 'ACTION_NOT_FOUND',
        'message' => "The requested action '{$action}' does not exist",
        'details' => 'Check API documentation for available actions'
    ], 404);
}

// Execute action module with error handling
try {
    // Modules can use sendApiResponse() to send response
    // Or set $response array which will be sent automatically
    require $actionFile;

    // If module didn't send response, check for $response variable
    if (isset($response)) {
        if (is_array($response) && isset($response['success'])) {
            // Module returned complete response
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            // Module returned data only
            sendApiResponse(true, $response, 'Success');
        }
    }

} catch (PDOException $e) {
    error_log("API Error [{$requestId}] - Database: " . $e->getMessage());

    // Pass the ACTUAL database error message, not generic text
    sendApiResponse(false, null, $e->getMessage(), [
        'code' => 'DATABASE_ERROR',
        'message' => $e->getMessage(), // Real error message
        'details' => $e->getTraceAsString(),
        'query' => $e->getMessage(), // Often contains query info
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);

} catch (Exception $e) {
    error_log("API Error [{$requestId}] - {$action}: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Pass the ACTUAL error message, not generic text
    sendApiResponse(false, null, $e->getMessage(), [
        'code' => 'SERVER_ERROR',
        'message' => $e->getMessage(), // Real error message
        'details' => $e->getTraceAsString(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
