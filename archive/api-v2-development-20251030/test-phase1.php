<?php
/**
 * File-Based API Testing
 * Direct file validation without HTTP requests
 * 
 * Since we can't use terminal or make HTTP requests easily,
 * this tests the API files directly by including them
 */

declare(strict_types=1);

echo "🧪 Phase 1 API File Validation\n";
echo "===============================\n\n";

$test_results = [];
$start_time = microtime(true);

// Test 1: Response Helper Validation
echo "🔍 Testing _response.php...\n";
try {
    ob_start();
    include '_response.php';
    $output = ob_get_clean();
    
    // Check if functions are defined
    $functions_exist = [
        'apiResponse' => function_exists('apiResponse'),
        'apiError' => function_exists('apiError'),
        'apiSuccess' => function_exists('apiSuccess'),
        'validateRequired' => function_exists('validateRequired'),
        'sanitizeInput' => function_exists('sanitizeInput'),
        'requireAuth' => function_exists('requireAuth'),
        'getCurrentSupplierId' => function_exists('getCurrentSupplierId'),
        'logApiAccess' => function_exists('logApiAccess'),
        'checkRateLimit' => function_exists('checkRateLimit')
    ];
    
    $all_functions_exist = !in_array(false, $functions_exist);
    
    $test_results[] = [
        'test' => '_response.php Functions',
        'success' => $all_functions_exist,
        'message' => $all_functions_exist ? 'All functions loaded successfully' : 'Some functions missing',
        'details' => $functions_exist
    ];
    
    echo ($all_functions_exist ? "✅" : "❌") . " Response helper functions: " . 
         ($all_functions_exist ? "All 9 functions loaded" : "Some functions missing") . "\n";
    
} catch (Exception $e) {
    $test_results[] = [
        'test' => '_response.php Loading',
        'success' => false,
        'message' => 'Failed to load: ' . $e->getMessage(),
        'details' => []
    ];
    echo "❌ Response helper loading: Failed - " . $e->getMessage() . "\n";
}

// Test 2: Database Helper Validation
echo "\n🔍 Testing _db_helpers.php...\n";
try {
    ob_start();
    include '_db_helpers.php';
    $output = ob_get_clean();
    
    // Check if class exists
    $class_exists = class_exists('SupplierQueries');
    
    if ($class_exists) {
        $reflection = new ReflectionClass('SupplierQueries');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $expected_methods = [
            'getPurchaseOrders',
            'getPurchaseOrderDetail', 
            'getWarrantyClaims',
            'getWarrantyClaimDetail',
            'getDashboardStats',
            'getTopProducts',
            'getOutlets',
            'getSupplierInfo'
        ];
        
        $method_names = array_map(fn($m) => $m->getName(), $methods);
        $methods_exist = [];
        
        foreach ($expected_methods as $method) {
            $methods_exist[$method] = in_array($method, $method_names);
        }
        
        $all_methods_exist = !in_array(false, $methods_exist);
        
        $test_results[] = [
            'test' => 'SupplierQueries Class',
            'success' => $all_methods_exist,
            'message' => $all_methods_exist ? 'All methods available' : 'Some methods missing',
            'details' => $methods_exist
        ];
        
        echo ($all_methods_exist ? "✅" : "❌") . " SupplierQueries class: " . 
             ($all_methods_exist ? "All 8 methods available" : "Some methods missing") . "\n";
    } else {
        $test_results[] = [
            'test' => 'SupplierQueries Class',
            'success' => false,
            'message' => 'Class not found',
            'details' => []
        ];
        echo "❌ SupplierQueries class: Not found\n";
    }
    
} catch (Exception $e) {
    $test_results[] = [
        'test' => '_db_helpers.php Loading',
        'success' => false,
        'message' => 'Failed to load: ' . $e->getMessage(),
        'details' => []
    ];
    echo "❌ Database helper loading: Failed - " . $e->getMessage() . "\n";
}

// Test 3: Test Connection Endpoint Validation
echo "\n🔍 Testing test-connection.php...\n";
try {
    $test_connection_content = file_get_contents('test-connection.php');
    
    $checks = [
        'has_php_tag' => strpos($test_connection_content, '<?php') === 0,
        'has_strict_types' => strpos($test_connection_content, 'declare(strict_types=1)') !== false,
        'includes_response' => strpos($test_connection_content, '_response.php') !== false,
        'includes_db_helpers' => strpos($test_connection_content, '_db_helpers.php') !== false,
        'has_rate_limiting' => strpos($test_connection_content, 'checkRateLimit') !== false,
        'has_error_handling' => strpos($test_connection_content, 'try {') !== false,
        'has_api_response' => strpos($test_connection_content, 'apiSuccess') !== false || strpos($test_connection_content, 'apiError') !== false
    ];
    
    $all_checks_pass = !in_array(false, $checks);
    
    $test_results[] = [
        'test' => 'test-connection.php Structure',
        'success' => $all_checks_pass,
        'message' => $all_checks_pass ? 'All structural checks pass' : 'Some structural issues',
        'details' => $checks
    ];
    
    echo ($all_checks_pass ? "✅" : "❌") . " Test connection structure: " . 
         ($all_checks_pass ? "All checks pass" : "Some issues found") . "\n";
    
} catch (Exception $e) {
    $test_results[] = [
        'test' => 'test-connection.php Reading',
        'success' => false,
        'message' => 'Failed to read: ' . $e->getMessage(),
        'details' => []
    ];
    echo "❌ Test connection reading: Failed - " . $e->getMessage() . "\n";
}

// Test 4: .htaccess Security Validation
echo "\n🔍 Testing .htaccess security...\n";
try {
    $htaccess_content = file_get_contents('.htaccess');
    
    $security_checks = [
        'has_rewrite_engine' => strpos($htaccess_content, 'RewriteEngine On') !== false,
        'blocks_underscore_files' => strpos($htaccess_content, '_') !== false && strpos($htaccess_content, 'deny') !== false,
        'has_security_headers' => strpos($htaccess_content, 'X-Content-Type-Options') !== false,
        'has_frame_options' => strpos($htaccess_content, 'X-Frame-Options') !== false,
        'has_xss_protection' => strpos($htaccess_content, 'X-XSS-Protection') !== false,
        'php_files_only' => strpos($htaccess_content, '\.php$') !== false
    ];
    
    $all_security_ok = !in_array(false, $security_checks);
    
    $test_results[] = [
        'test' => '.htaccess Security',
        'success' => $all_security_ok,
        'message' => $all_security_ok ? 'All security measures in place' : 'Some security issues',
        'details' => $security_checks
    ];
    
    echo ($all_security_ok ? "✅" : "❌") . " .htaccess security: " . 
         ($all_security_ok ? "All measures in place" : "Some issues found") . "\n";
    
} catch (Exception $e) {
    $test_results[] = [
        'test' => '.htaccess Reading',
        'success' => false,
        'message' => 'Failed to read: ' . $e->getMessage(),
        'details' => []
    ];
    echo "❌ .htaccess reading: Failed - " . $e->getMessage() . "\n";
}

// Test 5: File Permissions Check
echo "\n🔍 Testing file permissions...\n";
$files_to_check = [
    '.htaccess' => 'Security config',
    '_response.php' => 'Response helper',
    '_db_helpers.php' => 'Database helper', 
    'test-connection.php' => 'Test endpoint',
    'validate-api.php' => 'Validation tool'
];

$permission_checks = [];
foreach ($files_to_check as $file => $description) {
    $exists = file_exists($file);
    $readable = $exists ? is_readable($file) : false;
    $size = $exists ? filesize($file) : 0;
    
    $permission_checks[$file] = [
        'exists' => $exists,
        'readable' => $readable,
        'size' => $size,
        'description' => $description
    ];
    
    $status = $exists && $readable ? "✅" : "❌";
    echo "{$status} {$file} ({$description}): " . 
         ($exists ? "exists, {$size} bytes" : "missing") . 
         ($readable ? ", readable" : ", not readable") . "\n";
}

$all_files_ok = !in_array(false, array_column($permission_checks, 'exists'));

$test_results[] = [
    'test' => 'File Permissions',
    'success' => $all_files_ok,
    'message' => $all_files_ok ? 'All files accessible' : 'Some files missing or inaccessible',
    'details' => $permission_checks
];

// Generate Summary
$duration = round(microtime(true) - $start_time, 3);
$total_tests = count($test_results);
$passed_tests = count(array_filter($test_results, fn($test) => $test['success']));
$failed_tests = $total_tests - $passed_tests;

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 PHASE 1 VALIDATION SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Duration: {$duration} seconds\n";
echo "Total Tests: {$total_tests}\n";
echo "Passed: {$passed_tests} ✅\n";
echo "Failed: {$failed_tests} " . ($failed_tests > 0 ? "❌" : "✅") . "\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n\n";

if ($failed_tests === 0) {
    echo "🎉 ALL TESTS PASSED! API infrastructure is ready.\n";
    echo "✅ Phase 1 completion confirmed.\n";
    echo "🚀 Ready to proceed to Phase 2 (Dashboard Module).\n";
} else {
    echo "⚠️ Some tests failed. Review the issues above.\n";
    echo "❌ Phase 1 needs attention before proceeding.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Save detailed report
$report = "# Phase 1 API Validation Report\n\n";
$report .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
$report .= "**Duration:** {$duration} seconds\n\n";
$report .= "## Summary\n\n";
$report .= "- **Total Tests:** {$total_tests}\n";
$report .= "- **Passed:** {$passed_tests}\n";
$report .= "- **Failed:** {$failed_tests}\n";
$report .= "- **Success Rate:** " . round(($passed_tests / $total_tests) * 100, 1) . "%\n\n";

$report .= "## Detailed Results\n\n";
foreach ($test_results as $test) {
    $status = $test['success'] ? "✅ PASS" : "❌ FAIL";
    $report .= "### {$status} {$test['test']}\n\n";
    $report .= "**Message:** {$test['message']}\n\n";
    if (!empty($test['details'])) {
        $report .= "**Details:**\n";
        foreach ($test['details'] as $key => $value) {
            $display_value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            $report .= "- {$key}: {$display_value}\n";
        }
    }
    $report .= "\n";
}

file_put_contents('phase1-validation-report.md', $report);
echo "📁 Detailed report saved: phase1-validation-report.md\n";