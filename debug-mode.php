<?php
/**
 * DEBUG MODE Control Panel
 *
 * Allows toggling DEBUG MODE on/off and viewing configuration
 * Shows current debug state, active supplier ID, and access logs
 *
 * This file is ONLY accessible locally for security
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

// Security: Only allow localhost access
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($clientIp, ['127.0.0.1', 'localhost', '::1']) || getenv('DEVELOPMENT_MODE');

if (!$isLocalhost) {
    http_response_code(403);
    die('Access denied - Debug Mode control panel is only accessible from localhost');
}

require_once __DIR__ . '/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG MODE Control Panel - Supplier Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .card { box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .debug-enabled { background: #d4edda; border-color: #c3e6cb; }
        .debug-disabled { background: #f8d7da; border-color: #f5c6cb; }
        .status-badge { font-size: 1.2rem; padding: 8px 16px; }
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            font-size: 0.9rem;
        }
        .log-entry { margin-bottom: 5px; }
        .log-entry-debug { color: #4ec9b0; }
        .log-entry-error { color: #f48771; }
        .control-form { background: #fff; padding: 20px; border-radius: 4px; }
        .mono { font-family: 'Courier New', monospace; }
        .badge-success { background: #28a745; }
        .badge-danger { background: #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">
                🔧 DEBUG MODE Control Panel
                <small class="text-muted">(Local access only)</small>
            </h1>
        </div>
    </div>

    <!-- Current Status -->
    <div class="row">
        <div class="col-md-6">
            <div class="card <?php echo defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED ? 'debug-enabled' : 'debug-disabled'; ?>">
                <div class="card-header">
                    <h5 class="mb-0">Current Status</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>DEBUG MODE:</strong>
                        </div>
                        <div class="col-6">
                            <?php if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED): ?>
                                <span class="badge badge-success status-badge">🟢 ENABLED</span>
                            <?php else: ?>
                                <span class="badge badge-danger status-badge">🔴 DISABLED</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Hardcoded Supplier ID:</strong>
                        </div>
                        <div class="col-6">
                            <span class="mono badge bg-info">
                                <?php echo defined('DEBUG_MODE_SUPPLIER_ID') ? DEBUG_MODE_SUPPLIER_ID : 'Not set'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <strong>Client IP:</strong>
                        </div>
                        <div class="col-6">
                            <span class="mono"><?php echo htmlspecialchars($clientIp); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">How It Works</h5>
                </div>
                <div class="card-body small">
                    <p><strong>✅ When DEBUG MODE is ON:</strong></p>
                    <ul class="mb-3">
                        <li>Bypasses login page requirement</li>
                        <li>Uses hardcoded Supplier ID for all requests</li>
                        <li>No session/cookie handling needed</li>
                        <li>All pages accessible directly</li>
                        <li>Debug access logged for audit trail</li>
                    </ul>

                    <p><strong>🔴 When DEBUG MODE is OFF:</strong></p>
                    <ul>
                        <li>Normal authentication required</li>
                        <li>Session/cookie validation enforced</li>
                        <li>Login page required</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">⚙️ Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>ℹ️ Note:</strong> To toggle DEBUG MODE, edit <code class="mono">config.php</code>:
                        <br><br>
                        <code class="mono">define('DEBUG_MODE_ENABLED', true);</code> ← Set to enable<br>
                        <code class="mono">define('DEBUG_MODE_SUPPLIER_ID', 1);</code> ← Change supplier ID
                    </div>

                    <h6>Example Configuration:</h6>
                    <div class="bg-light p-3 rounded mono small">
                        <code>
&lt;?php<br>
// In config.php - Toggle these two lines:<br><br>
define('DEBUG_MODE_ENABLED', <strong>true</strong>);  <span style="color: #28a745;">// Change to enable/disable</span><br>
define('DEBUG_MODE_SUPPLIER_ID', <strong>1</strong>);  <span style="color: #28a745;">// Change to test different supplier</span>
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Access Log -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Debug Mode Access Log</h5>
                </div>
                <div class="card-body">
                    <?php
                    $logFile = __DIR__ . '/logs/debug-mode.log';

                    if (file_exists($logFile)) {
                        $lines = file($logFile);
                        // Show last 50 lines
                        $lines = array_slice($lines, -50);

                        echo '<div class="log-viewer">';
                        foreach ($lines as $line) {
                            if (strpos($line, 'DEBUG MODE ACTIVE') !== false) {
                                echo '<div class="log-entry log-entry-debug">' . htmlspecialchars($line) . '</div>';
                            } else {
                                echo '<div class="log-entry">' . htmlspecialchars($line) . '</div>';
                            }
                        }
                        echo '</div>';
                    } else {
                        echo '<p class="text-muted">No debug access log yet. Enable DEBUG MODE and browse pages to generate log entries.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🔗 Quick Links (with DEBUG MODE)</h5>
                </div>
                <div class="card-body">
                    <?php if (defined('DEBUG_MODE_ENABLED') && DEBUG_MODE_ENABLED): ?>
                        <div class="alert alert-success">
                            <strong>✅ DEBUG MODE is ENABLED</strong> - Click any link below to browse without logging in:
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="dashboard.php" class="btn btn-sm btn-primary w-100">📊 Dashboard</a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="products.php" class="btn btn-sm btn-primary w-100">📦 Products</a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="orders.php" class="btn btn-sm btn-primary w-100">📋 Orders</a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="warranty.php" class="btn btn-sm btn-primary w-100">🔧 Warranty</a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="account.php" class="btn btn-sm btn-primary w-100">👤 Account</a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="reports.php" class="btn btn-sm btn-primary w-100">📈 Reports</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>🔴 DEBUG MODE is DISABLED</strong> - Enable it in config.php to access these links directly
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-danger">
                <strong>⚠️ SECURITY WARNING:</strong> This debug control panel should NEVER be exposed to the internet or production environments.
                It is restricted to localhost connections only.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
