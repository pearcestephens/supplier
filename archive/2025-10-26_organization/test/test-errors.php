<?php
/**
 * Error Handling Test Page
 * 
 * Use this to test the comprehensive error handling system
 * 
 * Usage:
 *   /supplier/test-errors.php?test=exception
 *   /supplier/test-errors.php?test=error
 *   /supplier/test-errors.php?test=fatal
 *   /supplier/test-errors.php?test=ajax
 *   /supplier/test-errors.php?test=validation
 */

require_once __DIR__ . '/bootstrap.php';

$testType = $_GET['test'] ?? 'none';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Handling Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 40px; }
        .test-card { margin-bottom: 20px; }
        .result { margin-top: 20px; padding: 15px; background: #fff; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">🧪 Error Handling Test Suite</h1>
        
        <div class="alert alert-info">
            <strong>Instructions:</strong> Click a button below to trigger different types of errors.
            The error handling system will catch and display them appropriately.
        </div>
        
        <div class="row">
            <!-- PHP Exception Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-danger text-white">
                        <strong>1. PHP Exception (HTML)</strong>
                    </div>
                    <div class="card-body">
                        <p>Triggers a PHP exception. Should show beautiful error page.</p>
                        <a href="?test=exception" class="btn btn-danger">Trigger Exception</a>
                    </div>
                </div>
            </div>
            
            <!-- PHP Error Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-warning">
                        <strong>2. PHP Error (HTML)</strong>
                    </div>
                    <div class="card-body">
                        <p>Triggers a PHP error. Converted to exception, shows error page.</p>
                        <a href="?test=error" class="btn btn-warning">Trigger Error</a>
                    </div>
                </div>
            </div>
            
            <!-- AJAX Error Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-primary text-white">
                        <strong>3. AJAX Error (JSON)</strong>
                    </div>
                    <div class="card-body">
                        <p>Sends AJAX request that fails. Should show popup + notification.</p>
                        <button onclick="testAjaxError()" class="btn btn-primary">Trigger AJAX Error</button>
                        <div id="ajax-result" class="result" style="display:none;"></div>
                    </div>
                </div>
            </div>
            
            <!-- JavaScript Error Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-info text-white">
                        <strong>4. JavaScript Error</strong>
                    </div>
                    <div class="card-body">
                        <p>Triggers a JavaScript runtime error. Should show popup + notification.</p>
                        <button onclick="testJavaScriptError()" class="btn btn-info">Trigger JS Error</button>
                    </div>
                </div>
            </div>
            
            <!-- Validation Error Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-secondary text-white">
                        <strong>5. Validation Error (JSON)</strong>
                    </div>
                    <div class="card-body">
                        <p>Sends invalid data to API. Should return 400 with details.</p>
                        <button onclick="testValidationError()" class="btn btn-secondary">Trigger Validation Error</button>
                        <div id="validation-result" class="result" style="display:none;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Promise Rejection Test -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-dark text-white">
                        <strong>6. Promise Rejection</strong>
                    </div>
                    <div class="card-body">
                        <p>Triggers unhandled promise rejection. Should be caught.</p>
                        <button onclick="testPromiseRejection()" class="btn btn-dark">Trigger Promise Rejection</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success mt-4">
            <strong>✅ Expected Behavior:</strong>
            <ul class="mb-0 mt-2">
                <li><strong>PHP Exceptions/Errors:</strong> Beautiful error page with copy/download buttons</li>
                <li><strong>AJAX Errors:</strong> Popup alert + red notification toast</li>
                <li><strong>JavaScript Errors:</strong> Popup alert + console logging</li>
                <li><strong>All Errors:</strong> Logged to console with full details</li>
            </ul>
        </div>
    </div>
    
    <!-- Load jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Load Error Handler -->
    <script src="/supplier/assets/js/error-handler.js"></script>
    
    <script>
        function testAjaxError() {
            console.log('Testing AJAX error...');
            
            fetch('/supplier/api/endpoint.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'nonexistent.method',
                    params: {}
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('ajax-result').style.display = 'block';
                document.getElementById('ajax-result').innerHTML = 
                    '<strong>Response:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        }
        
        function testJavaScriptError() {
            console.log('Testing JavaScript error...');
            // This will throw an error
            nonExistentFunction();
        }
        
        function testValidationError() {
            console.log('Testing validation error...');
            
            // Assuming we're logged in, this should fail validation
            fetch('/supplier/api/endpoint.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'orders.addNote',
                    params: {
                        order_id: -999,  // Invalid ID
                        note: ''         // Empty note
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('validation-result').style.display = 'block';
                document.getElementById('validation-result').innerHTML = 
                    '<strong>Response:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        }
        
        function testPromiseRejection() {
            console.log('Testing promise rejection...');
            Promise.reject(new Error('Test unhandled promise rejection'));
        }
        
        console.log('✅ Error handler loaded:', typeof ErrorHandler !== 'undefined');
    </script>
</body>
</html>

<?php
// Trigger errors based on test parameter
switch ($testType) {
    case 'exception':
        throw new Exception('Test Exception: This is a deliberately triggered exception to test error handling!');
        break;
        
    case 'error':
        // Trigger a PHP error (will be converted to exception)
        $undefinedVariable = $thisVariableDoesNotExist;
        break;
        
    case 'fatal':
        // Trigger a fatal error
        require_once '/this/file/does/not/exist.php';
        break;
        
    case 'validation':
        // This would be caught by API, not here
        echo json_encode(['error' => 'Use AJAX test for validation errors']);
        exit;
        
    default:
        // Do nothing, show test interface
        break;
}
?>
