<?php
/**
 * Supplier Portal Bootstrap - Enterprise Grade
 *
 * Central application initialization point
 * Initializes ALL shared resources in correct order
 * Provides unified database (MySQLi + PDO), session, and auth
 *
 * @package SupplierPortal
 * @version 4.0.0 - Enterprise Unified Architecture
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === 'bootstrap.php') {
    http_response_code(403);
    exit('Direct access not permitted');
}

// ============================================================================
// STEP 1: Load configuration
// ============================================================================
require_once __DIR__ . '/config.php';

// ============================================================================
// STEP 2: Load core libraries (in dependency order)
// ============================================================================
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/DatabasePDO.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/status-badge-helper.php';

// ============================================================================
// STEP 3: Initialize session (MUST be before Auth)
// ============================================================================
try {
    Session::start();
} catch (Exception $e) {
    error_log("Session initialization failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);

    if (isJsonRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(), // REAL session error message
            'error' => [
                'code' => 'SESSION_INIT_ERROR',
                'message' => $e->getMessage(), // REAL error message
                'details' => 'Session could not be started',
                'type' => get_class($e)
            ],
            'timestamp' => date('c'),
            'request_id' => uniqid('err_', true)
        ]);
    } else {
        echo '<div style="padding:20px;border:1px solid #dc3545;background:#fee;color:#721c24;margin:20px;border-radius:4px;">';
        echo '<strong>Application Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '<br><small>Please contact support if this problem persists.</small>';
        echo '</div>';
    }
    exit;
}

// ============================================================================
// STEP 4: Initialize database connections (BOTH MySQLi and PDO)
// ============================================================================
try {
    // MySQLi connection (for tabs using prepared statements)
    $db = Database::connect();

    // Verify connection is working
    if (!$db->ping()) {
        throw new Exception('MySQLi connection verification failed');
    }

    // Make available globally
    $GLOBALS['db'] = $db;

    // PDO connection (for API handlers)
    $pdo = DatabasePDO::getInstance();

    // Verify PDO connection
    $pdo->query('SELECT 1');

    // Make available globally
    $GLOBALS['pdo'] = $pdo;

} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);

    if (isJsonRequest()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(), // REAL database error message
            'error' => [
                'code' => 'DATABASE_INIT_ERROR',
                'message' => $e->getMessage(), // REAL error message
                'details' => 'Database connection could not be established',
                'type' => get_class($e)
            ],
            'timestamp' => date('c'),
            'request_id' => uniqid('err_', true)
        ]);
    } else {
        echo '<div style="padding:20px;border:1px solid #dc3545;background:#fee;color:#721c24;margin:20px;border-radius:4px;">';
        echo '<strong>Database Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '<br><small>Please contact support if this problem persists.</small>';
        echo '</div>';
    }
    exit;
}

// ============================================================================
// STEP 5: Set application-wide error handlers (ENHANCED)
// ============================================================================

/**
 * Display Inline Error
 * Shows a tidy, compact error notice inline in the page
 *
 * @param int $errno Error level
 * @param string $errstr Error message
 * @param string $errfile File where error occurred
 * @param int $errline Line number
 */
function displayInlineError(int $errno, string $errstr, string $errfile, int $errline): void {
    // Map error types to colors and labels
    $errorTypes = [
        E_WARNING => ['color' => '#ff9800', 'label' => 'Warning', 'icon' => '⚠️'],
        E_NOTICE => ['color' => '#2196F3', 'label' => 'Notice', 'icon' => 'ℹ️'],
        E_USER_WARNING => ['color' => '#ff9800', 'label' => 'Warning', 'icon' => '⚠️'],
        E_USER_NOTICE => ['color' => '#2196F3', 'label' => 'Notice', 'icon' => 'ℹ️'],
        E_STRICT => ['color' => '#9e9e9e', 'label' => 'Strict', 'icon' => '📋'],
        E_DEPRECATED => ['color' => '#795548', 'label' => 'Deprecated', 'icon' => '🔧']
    ];

    $info = $errorTypes[$errno] ?? ['color' => '#f44336', 'label' => 'Error', 'icon' => '❌'];
    $shortFile = basename($errfile);

    echo <<<HTML
<div style="
    margin: 15px 0;
    padding: 12px 15px;
    background: #fff;
    border-left: 4px solid {$info['color']};
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 14px;
    line-height: 1.5;
">
    <div style="display: flex; align-items: flex-start; gap: 10px;">
        <span style="font-size: 20px; flex-shrink: 0;">{$info['icon']}</span>
        <div style="flex: 1;">
            <div style="font-weight: 600; color: {$info['color']}; margin-bottom: 4px;">
                {$info['label']}
            </div>
            <div style="color: #333; margin-bottom: 6px;">
                {$errstr}
            </div>
            <div style="font-size: 12px; color: #666; font-family: 'Courier New', monospace;">
                {$shortFile} : line {$errline}
            </div>
        </div>
    </div>
</div>
HTML;
}

/**
 * Enhanced Exception Handler
 * - Shows full debug info in DEBUG_MODE
 * - Returns JSON for AJAX/API requests
 * - Shows copy-paste friendly HTML error page
 * - Includes JavaScript popup for errors
 */
set_exception_handler(function(Throwable $e) {
    // Log to error log
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Detect if this is an AJAX/JSON request
    $isAjax = isAjaxRequest();
    $isJson = isJsonRequest();
    $wantsJson = $isAjax || $isJson || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    // Build comprehensive error data
    $errorData = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'trace_array' => $e->getTrace(),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'post_data' => !empty($_POST) ? $_POST : null,
        'get_data' => !empty($_GET) ? $_GET : null,
        'server' => [
            'PHP_VERSION' => PHP_VERSION,
            'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    ];

    // Return JSON for AJAX/API requests
    if ($wantsJson) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(), // REAL error message at top level
            'error' => [
                'code' => $e->getCode() ?: 'EXCEPTION_ERROR',
                'message' => $e->getMessage(), // REAL error message
                'type' => get_class($e),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ],
            'debug' => $errorData,
            'timestamp' => date('c'),
            'request_id' => uniqid('err_', true)
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Show enhanced HTML error page
    http_response_code(500);
    displayEnhancedErrorPage($errorData, $e);
    exit;
});

/**
 * Enhanced Error Handler
 * - Fatal errors: Full page display
 * - Warnings/Notices: Inline tidy display
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't throw exception if error reporting is turned off
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Log all errors
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");

    // Fatal errors: Convert to exception for full-page display
    if ($errno === E_ERROR || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR || $errno === E_USER_ERROR) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    // Warnings and Notices: For API/AJAX requests, just log them (don't display HTML)
    if ($errno === E_WARNING || $errno === E_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_NOTICE || $errno === E_STRICT || $errno === E_DEPRECATED) {
        // For API/AJAX requests, suppress HTML output (already logged above)
        if (isAjaxRequest() || isJsonRequest()) {
            return true; // Suppress default PHP error handler
        }

        // For regular page requests, display inline error
        displayInlineError($errno, $errstr, $errfile, $errline);
        return true; // Don't execute PHP internal error handler
    }

    // For other errors, convert to exception
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/**
 * Display Enhanced HTML Error Page
 * Shows comprehensive debugging info with copy-paste functionality
 *
 * @param array $errorData Complete error information
 * @param Throwable $exception The original exception
 */
function displayEnhancedErrorPage(array $errorData, Throwable $exception): void {
    $requestId = uniqid('err_', true);
    $copyText = "=== ERROR REPORT ===\n";
    $copyText .= "Request ID: {$requestId}\n";
    $copyText .= "Timestamp: {$errorData['timestamp']}\n";
    $copyText .= "Type: {$errorData['type']}\n";
    $copyText .= "Message: {$errorData['message']}\n";
    $copyText .= "File: {$errorData['file']}\n";
    $copyText .= "Line: {$errorData['line']}\n";
    $copyText .= "URL: {$errorData['request_uri']}\n";
    $copyText .= "Method: {$errorData['request_method']}\n\n";
    $copyText .= "Stack Trace:\n{$errorData['trace']}\n\n";

    if ($errorData['post_data']) {
        $copyText .= "POST Data:\n" . print_r($errorData['post_data'], true) . "\n\n";
    }
    if ($errorData['get_data']) {
        $copyText .= "GET Data:\n" . print_r($errorData['get_data'], true) . "\n\n";
    }

    $copyText .= "Server Info:\n";
    $copyText .= "PHP Version: {$errorData['server']['PHP_VERSION']}\n";
    $copyText .= "Server: {$errorData['server']['SERVER_SOFTWARE']}\n";
    $copyText .= "IP: {$errorData['server']['REMOTE_ADDR']}\n";

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>500 Internal Server Error</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            .error-container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .error-header {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .error-header h1 {
                font-size: 3rem;
                margin-bottom: 10px;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            }
            .error-header p {
                font-size: 1.2rem;
                opacity: 0.9;
            }
            .error-body {
                padding: 30px;
            }
            .error-section {
                margin-bottom: 25px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #dc3545;
            }
            .error-section h3 {
                color: #dc3545;
                margin-bottom: 15px;
                font-size: 1.3rem;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .error-section h3:before {
                content: '⚠️';
                font-size: 1.5rem;
            }
            .error-detail {
                margin: 10px 0;
                padding: 10px;
                background: white;
                border-radius: 4px;
                font-family: 'Courier New', monospace;
                font-size: 0.9rem;
            }
            .error-detail strong {
                color: #495057;
                display: inline-block;
                min-width: 120px;
            }
            .error-detail code {
                color: #dc3545;
                background: #fff3cd;
                padding: 2px 6px;
                border-radius: 3px;
            }
            .stack-trace {
                background: #2d3748;
                color: #e2e8f0;
                padding: 20px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                font-size: 0.85rem;
                line-height: 1.6;
                max-height: 400px;
                overflow-y: auto;
                white-space: pre-wrap;
                word-break: break-all;
            }
            .copy-section {
                margin-top: 20px;
                padding: 20px;
                background: #e7f3ff;
                border-radius: 8px;
                border: 2px dashed #007bff;
            }
            .btn-group {
                display: flex;
                gap: 15px;
                margin-top: 15px;
                flex-wrap: wrap;
            }
            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-size: 1rem;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            .btn-primary {
                background: #007bff;
                color: white;
            }
            .btn-primary:hover {
                background: #0056b3;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,123,255,0.3);
            }
            .btn-success {
                background: #28a745;
                color: white;
            }
            .btn-success:hover {
                background: #218838;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(40,167,69,0.3);
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            .btn-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
            }
            .copied-notification {
                display: none;
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            }
            .copied-notification.show {
                display: block;
            }
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            .request-id {
                background: #fff3cd;
                border: 1px solid #ffc107;
                color: #856404;
                padding: 12px;
                border-radius: 6px;
                font-family: 'Courier New', monospace;
                font-size: 0.95rem;
                margin-bottom: 20px;
                text-align: center;
                font-weight: bold;
            }
            textarea {
                width: 100%;
                height: 300px;
                padding: 15px;
                border: 2px solid #ced4da;
                border-radius: 6px;
                font-family: 'Courier New', monospace;
                font-size: 0.85rem;
                resize: vertical;
                margin-top: 10px;
            }
            .badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
                background: #dc3545;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-header">
                <h1>500 Internal Server Error</h1>
                <p>The application encountered an unexpected error</p>
            </div>

            <div class="error-body">
                <div class="request-id">
                    Request ID: <?= htmlspecialchars((string)$requestId) ?>
                </div>

                <div class="error-section">
                    <h3>Error Details</h3>
                    <div class="error-detail">
                        <strong>Type:</strong> <span class="badge"><?= htmlspecialchars((string)$errorData['type']) ?></span>
                    </div>
                    <div class="error-detail">
                        <strong>Message:</strong> <code><?= htmlspecialchars((string)$errorData['message']) ?></code>
                    </div>
                    <div class="error-detail">
                        <strong>File:</strong> <code><?= htmlspecialchars((string)$errorData['file']) ?></code>
                    </div>
                    <div class="error-detail">
                        <strong>Line:</strong> <code><?= htmlspecialchars((string)$errorData['line']) ?></code>
                    </div>
                    <div class="error-detail">
                        <strong>Timestamp:</strong> <?= htmlspecialchars((string)$errorData['timestamp']) ?>
                    </div>
                </div>

                <div class="error-section">
                    <h3>Request Information</h3>
                    <div class="error-detail">
                        <strong>URL:</strong> <code><?= htmlspecialchars((string)$errorData['request_uri']) ?></code>
                    </div>
                    <div class="error-detail">
                        <strong>Method:</strong> <code><?= htmlspecialchars((string)$errorData['request_method']) ?></code>
                    </div>
                    <?php if ($errorData['post_data']): ?>
                    <div class="error-detail">
                        <strong>POST Data:</strong>
                        <pre style="margin-top:10px;background:#f8f9fa;padding:10px;border-radius:4px;overflow-x:auto;"><?= htmlspecialchars(print_r($errorData['post_data'], true)) ?></pre>
                    </div>
                    <?php endif; ?>
                    <?php if ($errorData['get_data']): ?>
                    <div class="error-detail">
                        <strong>GET Data:</strong>
                        <pre style="margin-top:10px;background:#f8f9fa;padding:10px;border-radius:4px;overflow-x:auto;"><?= htmlspecialchars(print_r($errorData['get_data'], true)) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="error-section">
                    <h3>Stack Trace</h3>
                    <div class="stack-trace"><?= htmlspecialchars((string)$errorData['trace']) ?></div>
                </div>

                <div class="copy-section">
                    <h3 style="color:#007bff;margin-bottom:15px;">📋 Copy Error Report</h3>
                    <p style="margin-bottom:10px;">Select and copy the text below to share with developers:</p>
                    <textarea id="errorReport" readonly><?= htmlspecialchars($copyText) ?></textarea>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="copyToClipboard()">
                            📋 Copy to Clipboard
                        </button>
                        <button class="btn btn-success" onclick="downloadReport()">
                            💾 Download as TXT
                        </button>
                        <button class="btn btn-secondary" onclick="window.location.reload()">
                            🔄 Reload Page
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="copied-notification" id="copiedNotification">
            ✅ Error report copied to clipboard!
        </div>

        <script>
            // Auto-show popup alert
            alert('⚠️ APPLICATION ERROR\n\n' +
                  'Type: <?= addslashes($errorData['type']) ?>\n' +
                  'Message: <?= addslashes($errorData['message']) ?>\n\n' +
                  'Request ID: <?= $requestId ?>\n\n' +
                  'Please copy the error report below and send to support.');

            function copyToClipboard() {
                const textarea = document.getElementById('errorReport');
                textarea.select();
                textarea.setSelectionRange(0, 99999); // For mobile

                try {
                    document.execCommand('copy');
                    showNotification();
                } catch (err) {
                    // Fallback for modern browsers
                    navigator.clipboard.writeText(textarea.value).then(() => {
                        showNotification();
                    }).catch(err => {
                        alert('Failed to copy. Please select and copy manually.');
                    });
                }
            }

            function showNotification() {
                const notification = document.getElementById('copiedNotification');
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);
            }

            function downloadReport() {
                const textarea = document.getElementById('errorReport');
                const blob = new Blob([textarea.value], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'error-report-<?= $requestId ?>.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                alert('✅ Error report downloaded!');
            }

            // Log error to console for developer tools
            console.error('Application Error:', <?= json_encode($errorData) ?>);
        </script>
    </body>
    </html>
    <?php
}

// ============================================================================
// STEP 6: Define helper functions (available application-wide)
// ============================================================================

/**
 * Get MySQLi database connection (for tabs using prepared statements)
 *
 * @return mysqli
 * @throws Exception If database not initialized
 */
function db(): mysqli {
    if (!isset($GLOBALS['db']) || !($GLOBALS['db'] instanceof mysqli)) {
        throw new Exception('Database not initialized - ensure bootstrap.php is loaded');
    }
    return $GLOBALS['db'];
}

/**
 * Get PDO database connection (for API handlers)
 *
 * @return PDO
 * @throws Exception If PDO not initialized
 */
function pdo(): PDO {
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        throw new Exception('PDO not initialized - ensure bootstrap.php is loaded');
    }
    return $GLOBALS['pdo'];
}

/**
 * Require authentication - redirect to login if not authenticated
 * Call this at the top of any protected page
 *
 * @return void
 */
function requireAuth(): void {
    if (!Auth::check()) {
        // If AJAX/API request, return standardized 401 JSON with specific message
        if (isAjaxRequest() || isJsonRequest()) {
            sendApiResponse(false, null, 'Authentication required', [
                'code' => 'AUTH_REQUIRED',
                'message' => 'You must be logged in to access this resource',
                'details' => 'Your session may have expired. Please log in again.',
                'action' => 'redirect',
                'redirect_url' => '/supplier/login.php'
            ], 401);
        }

        // Regular page request - redirect to login
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/supplier/';
        header('Location: /supplier/login.php?redirect=' . urlencode($currentUrl));
        exit;
    }
}

/**
 * Get authenticated supplier ID
 *
 * @return string|null Supplier UUID or null if not authenticated
 */
function getSupplierID(): ?string {
    return Auth::getSupplierId();
}

/**
 * Check if current request is AJAX
 * Enhanced detection for various AJAX methods
 *
 * @return bool
 */
function isAjaxRequest(): bool {
    // Check X-Requested-With header (jQuery, Axios, etc.)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    // Check Fetch API indicators
    if (isset($_SERVER['HTTP_SEC_FETCH_MODE'])
        && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'cors') {
        return true;
    }

    // Check if Accept header prefers JSON
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false
        && strpos($accept, 'text/html') === false) {
        return true;
    }

    return false;
}

/**
 * Check if current request expects JSON response
 *
 * @return bool
 */
function isJsonRequest(): bool {
    // Check Accept header
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) {
        return true;
    }

    // Check Content-Type header
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        return true;
    }

    // Check if AJAX request
    if (isAjaxRequest()) {
        return true;
    }

    // Check if request is to /api/ endpoint
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/api/') !== false) {
        return true;
    }

    return false;
}

/**
 * Send JSON response and exit
 *
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string|null $message Optional message
 * @param int $httpCode HTTP status code
 * @return never
 */
function sendJsonResponse(bool $success, $data = null, ?string $message = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json');

    $response = ['success' => $success];

    if ($success) {
        $response['data'] = $data;
        if ($message) {
            $response['message'] = $message;
        }
    } else {
        $response['error'] = $message ?? 'An error occurred';
        if ($data !== null) {
            $response['details'] = $data;
        }
    }

    echo json_encode($response);
    exit;
}

/**
 * Send standardized API response (Enhanced version for unified API)
 *
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message User-friendly message
 * @param array|null $error Error details array with code, message, details, field
 * @param int $httpCode HTTP status code
 * @return void (exits after sending response)
 */
if (!function_exists('sendApiResponse')) {
    function sendApiResponse(bool $success, $data = null, string $message = '', ?array $error = null, int $httpCode = 200): void {
        global $requestId;

        // Use global request ID if set, otherwise generate one
        if (!isset($requestId)) {
            $requestId = uniqid('req_', true);
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Request-ID: ' . $requestId);

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
}

/**
 * Sanitize string for safe HTML output
 *
 * @param string|null $value Value to sanitize
 * @return string Sanitized value
 */
function e(?string $value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date consistently across application
 *
 * @param string|int|null $date Date string or Unix timestamp
 * @param string $format Output format (default: 'd M Y', 'time': 'H:i:s', 'datetime': 'd M Y H:i')
 * @return string Formatted date or empty string
 */
function formatDate($date, string $format = 'd M Y'): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }

    try {
        // Handle Unix timestamps (integers)
        if (is_numeric($date)) {
            $dt = new DateTime();
            $dt->setTimestamp((int)$date);
        } else {
            $dt = new DateTime($date);
        }

        // Handle named formats
        if ($format === 'time') {
            return $dt->format('H:i:s');
        } elseif ($format === 'datetime') {
            return $dt->format('d M Y H:i');
        } elseif ($format === 'display') {
            return $dt->format('d M Y');
        }

        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Log message to application log
 *
 * @param string $message Message to log
 * @param string $level Log level (INFO, WARNING, ERROR)
 * @param array $context Additional context data
 * @return void
 */
function logMessage(string $message, string $level = 'INFO', array $context = []): void {
    $logFile = __DIR__ . '/logs/application.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $supplierID = getSupplierID() ?? 'GUEST';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $logEntry = "[{$timestamp}] [{$level}] [Supplier:{$supplierID}] [IP:{$ip}] {$message}";

    if (!empty($context)) {
        $logEntry .= ' | Context: ' . json_encode($context);
    }

    $logEntry .= "\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// ============================================================================
// STEP 7: Bootstrap complete - mark as initialized
// ============================================================================
define('APP_BOOTSTRAPPED', true);

// ============================================================================
// HELPER FUNCTIONS AVAILABLE GLOBALLY
// ============================================================================

// End of bootstrap.php - All helper functions defined above
