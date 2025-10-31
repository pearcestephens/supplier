<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Diagnostic Tool</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
        }
        .status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status.success {
            background: #28a745;
            color: white;
        }
        .status.error {
            background: #dc3545;
            color: white;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #333;
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .test-section {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin: 20px 0;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        #testResults {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            max-height: 500px;
            overflow-y: auto;
        }
        .cookie-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .cookie-item {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-left: 3px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Session Diagnostic Tool</h1>
        
        <?php
        require_once __DIR__ . '/lib/Session.php';
        require_once __DIR__ . '/lib/Auth.php';
        
        Session::start();
        $isAuth = Auth::check();
        ?>
        
        <div>
            <span class="status <?php echo $isAuth ? 'success' : 'error'; ?>">
                <?php echo $isAuth ? '✓ AUTHENTICATED' : '✗ NOT AUTHENTICATED'; ?>
            </span>
        </div>

        <h2>Session Information</h2>
        <div class="info-grid">
            <div class="info-label">Session Status:</div>
            <div class="info-value"><?php echo session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE'; ?></div>
            
            <div class="info-label">Session Name:</div>
            <div class="info-value"><?php echo session_name(); ?></div>
            
            <div class="info-label">Session ID:</div>
            <div class="info-value"><?php echo session_id(); ?></div>
            
            <div class="info-label">Is Authenticated:</div>
            <div class="info-value"><?php echo $isAuth ? 'YES' : 'NO'; ?></div>
            
            <?php if ($isAuth): ?>
            <div class="info-label">Supplier ID:</div>
            <div class="info-value"><?php echo Auth::getSupplierId(); ?></div>
            
            <div class="info-label">Supplier Name:</div>
            <div class="info-value"><?php echo Auth::getSupplierName(); ?></div>
            <?php endif; ?>
        </div>

        <h2>Cookie Parameters</h2>
        <?php $cookieParams = session_get_cookie_params(); ?>
        <div class="info-grid">
            <div class="info-label">Cookie Lifetime:</div>
            <div class="info-value"><?php echo $cookieParams['lifetime']; ?> seconds (<?php echo round($cookieParams['lifetime']/3600, 1); ?> hours)</div>
            
            <div class="info-label">Cookie Path:</div>
            <div class="info-value"><?php echo $cookieParams['path']; ?></div>
            
            <div class="info-label">Cookie Domain:</div>
            <div class="info-value"><?php echo $cookieParams['domain'] ?: '(current domain)'; ?></div>
            
            <div class="info-label">HTTP Only:</div>
            <div class="info-value"><?php echo $cookieParams['httponly'] ? 'YES' : 'NO'; ?></div>
            
            <div class="info-label">Secure:</div>
            <div class="info-value"><?php echo $cookieParams['secure'] ? 'YES' : 'NO'; ?></div>
            
            <div class="info-label">SameSite:</div>
            <div class="info-value"><?php echo $cookieParams['samesite'] ?? 'None'; ?></div>
        </div>

        <h2>Browser Cookies</h2>
        <div class="cookie-list">
            <?php if (!empty($_COOKIE)): ?>
                <?php foreach ($_COOKIE as $name => $value): ?>
                <div class="cookie-item">
                    <strong><?php echo htmlspecialchars($name); ?>:</strong>
                    <?php echo strlen($value) > 100 ? substr($value, 0, 100) . '...' : htmlspecialchars($value); ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: #dc3545;">No cookies found!</div>
            <?php endif; ?>
        </div>

        <h2>Session Data</h2>
        <div class="info-grid">
            <div class="info-label">Authenticated:</div>
            <div class="info-value"><?php echo isset($_SESSION['authenticated']) ? json_encode($_SESSION['authenticated']) : 'NOT SET'; ?></div>
            
            <div class="info-label">Supplier ID:</div>
            <div class="info-value"><?php echo $_SESSION['supplier_id'] ?? 'NOT SET'; ?></div>
            
            <div class="info-label">Supplier Name:</div>
            <div class="info-value"><?php echo $_SESSION['supplier_name'] ?? 'NOT SET'; ?></div>
            
            <div class="info-label">Login Time:</div>
            <div class="info-value"><?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'NOT SET'; ?></div>
            
            <div class="info-label">Session Created:</div>
            <div class="info-value"><?php echo isset($_SESSION['_created']) ? date('Y-m-d H:i:s', $_SESSION['_created']) : 'NOT SET'; ?></div>
        </div>

        <h2>🧪 Live API Tests</h2>
        <div class="test-section">
            <p>Test if API endpoints can access the same session:</p>
            <button onclick="testSessionEndpoint()">Test Session Endpoint</button>
            <button onclick="testUnifiedAPI()">Test Unified API</button>
            <button onclick="testNotifications()">Test Notifications API</button>
            <button onclick="clearResults()">Clear Results</button>
            <div id="testResults"></div>
        </div>

        <h2>Request Information</h2>
        <div class="info-grid">
            <div class="info-label">Host:</div>
            <div class="info-value"><?php echo $_SERVER['HTTP_HOST'] ?? 'N/A'; ?></div>
            
            <div class="info-label">Request URI:</div>
            <div class="info-value"><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></div>
            
            <div class="info-label">User Agent:</div>
            <div class="info-value"><?php echo substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 100); ?></div>
            
            <div class="info-label">Remote Address:</div>
            <div class="info-value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'N/A'; ?></div>
            
            <div class="info-label">HTTPS:</div>
            <div class="info-value"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'YES' : 'NO'; ?></div>
        </div>
    </div>

    <script>
        function clearResults() {
            document.getElementById('testResults').textContent = '';
        }

        async function testSessionEndpoint() {
            const results = document.getElementById('testResults');
            results.textContent = 'Testing /api/session-test.php...\n\n';
            
            try {
                const response = await fetch('/supplier/api/session-test.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                results.textContent += 'Status: ' + response.status + '\n';
                results.textContent += 'Response:\n' + JSON.stringify(data, null, 2);
                
                if (data.data && data.data.is_authenticated) {
                    results.textContent += '\n\n✅ SUCCESS: API can access authenticated session!';
                } else {
                    results.textContent += '\n\n❌ FAILED: API cannot access session';
                }
            } catch (error) {
                results.textContent += '\n\n❌ ERROR: ' + error.message;
            }
        }

        async function testUnifiedAPI() {
            const results = document.getElementById('testResults');
            results.textContent = 'Testing /api/endpoint.php (dashboard.getStats)...\n\n';
            
            try {
                const response = await fetch('/supplier/api/endpoint.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'dashboard.getStats',
                        params: {}
                    })
                });
                const data = await response.json();
                results.textContent += 'Status: ' + response.status + '\n';
                results.textContent += 'Response:\n' + JSON.stringify(data, null, 2);
                
                if (data.success) {
                    results.textContent += '\n\n✅ SUCCESS: Unified API authenticated!';
                } else {
                    results.textContent += '\n\n❌ FAILED: ' + (data.message || 'Unknown error');
                }
            } catch (error) {
                results.textContent += '\n\n❌ ERROR: ' + error.message;
            }
        }

        async function testNotifications() {
            const results = document.getElementById('testResults');
            results.textContent = 'Testing /api/notifications-count.php...\n\n';
            
            try {
                const response = await fetch('/supplier/api/notifications-count.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                results.textContent += 'Status: ' + response.status + '\n';
                results.textContent += 'Response:\n' + JSON.stringify(data, null, 2);
                
                if (!data.error) {
                    results.textContent += '\n\n✅ SUCCESS: Notifications API authenticated!';
                } else {
                    results.textContent += '\n\n❌ FAILED: ' + data.error;
                }
            } catch (error) {
                results.textContent += '\n\n❌ ERROR: ' + error.message;
            }
        }
    </script>
</body>
</html>
