<?php
/**
 * API Validation Suite
 * File-based testing tool for supplier portal API endpoints
 * 
 * Part of: Supplier Portal Redesign - Phase 1 Testing
 * Created: October 22, 2025
 * Version: 2.0
 * 
 * This tool simulates HTTP requests without requiring terminal/curl
 * Uses PHP file operations for comprehensive API testing
 * 
 * @package SupplierPortal\Testing
 */

declare(strict_types=1);

class APIValidator {
    private $base_url;
    private $test_results = [];
    private $start_time;
    
    public function __construct(string $base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->start_time = microtime(true);
    }
    
    /**
     * Simulate HTTP GET request using file_get_contents
     */
    private function httpGet(string $endpoint, array $params = []): array {
        $url = $this->base_url . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: SupplierPortal-APIValidator/2.0',
                    'Accept: application/json',
                    'X-Test-Request: true'
                ],
                'timeout' => 30
            ]
        ]);
        
        $start_time = microtime(true);
        $response = @file_get_contents($url, false, $context);
        $duration = microtime(true) - $start_time;
        
        $http_response_header = $http_response_header ?? [];
        $status_code = 0;
        
        // Parse status code from headers
        if (!empty($http_response_header)) {
            $status_line = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $status_line, $matches)) {
                $status_code = (int)$matches[1];
            }
        }
        
        return [
            'success' => $response !== false,
            'status_code' => $status_code,
            'response' => $response,
            'duration' => $duration,
            'headers' => $http_response_header,
            'url' => $url
        ];
    }
    
    /**
     * Test basic endpoint availability
     */
    public function testEndpointAvailability(): void {
        echo "🔍 Testing Endpoint Availability...\n";
        
        $endpoints = [
            'test-connection.php' => 'Test Connection Endpoint',
            '_response.php' => 'Response Helper (should be blocked)',
            '_db_helpers.php' => 'Database Helper (should be blocked)'
        ];
        
        foreach ($endpoints as $endpoint => $description) {
            $result = $this->httpGet($endpoint);
            
            $test_name = "Endpoint: {$description}";
            
            if (strpos($endpoint, '_') === 0) {
                // These should be blocked by .htaccess
                $expected_blocked = true;
                $success = !$result['success'] || $result['status_code'] === 403;
                $message = $success ? "Correctly blocked direct access" : "ERROR: Should be blocked but accessible";
            } else {
                // These should be accessible
                $expected_blocked = false;
                $success = $result['success'] && $result['status_code'] === 200;
                $message = $success ? "Accessible as expected" : "ERROR: Should be accessible but failed";
            }
            
            $this->test_results[] = [
                'test' => $test_name,
                'success' => $success,
                'message' => $message,
                'details' => [
                    'url' => $result['url'],
                    'status_code' => $result['status_code'],
                    'duration' => round($result['duration'] * 1000, 2) . 'ms',
                    'expected_blocked' => $expected_blocked
                ]
            ];
            
            echo ($success ? "✅" : "❌") . " {$test_name}: {$message}\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test connection endpoint with different parameters
     */
    public function testConnectionEndpoint(): void {
        echo "🧪 Testing Connection Endpoint Functionality...\n";
        
        $test_cases = [
            ['params' => [], 'description' => 'Basic test (default)'],
            ['params' => ['test_level' => 'basic'], 'description' => 'Basic test (explicit)'],
            ['params' => ['test_level' => 'full'], 'description' => 'Full test'],
            ['params' => ['test_level' => 'invalid'], 'description' => 'Invalid test level (should fail)']
        ];
        
        foreach ($test_cases as $test_case) {
            $result = $this->httpGet('test-connection.php', $test_case['params']);
            
            $test_name = "Connection Test: {$test_case['description']}";
            
            if ($result['success'] && $result['status_code'] === 200) {
                $response_data = json_decode($result['response'], true);
                
                if ($response_data) {
                    $is_error = isset($response_data['error']);
                    $has_tests = isset($response_data['data']['tests']);
                    
                    if ($test_case['params']['test_level'] ?? '' === 'invalid') {
                        // Should return error for invalid test level
                        $success = $is_error && $response_data['status'] === 'error';
                        $message = $success ? "Correctly rejected invalid parameter" : "ERROR: Should reject invalid test_level";
                    } else {
                        // Should return success with test results
                        $success = !$is_error && $has_tests && $response_data['status'] === 'success';
                        $message = $success ? "Test executed successfully" : "ERROR: Test execution failed";
                        
                        if ($success && $has_tests) {
                            $test_count = count($response_data['data']['tests']);
                            $overall_status = $response_data['data']['overall_status'] ?? 'unknown';
                            $message .= " ({$test_count} tests, status: {$overall_status})";
                        }
                    }
                } else {
                    $success = false;
                    $message = "ERROR: Invalid JSON response";
                }
            } else {
                $success = false;
                $message = "ERROR: HTTP request failed (Status: {$result['status_code']})";
            }
            
            $this->test_results[] = [
                'test' => $test_name,
                'success' => $success,
                'message' => $message,
                'details' => [
                    'params' => $test_case['params'],
                    'status_code' => $result['status_code'],
                    'duration' => round($result['duration'] * 1000, 2) . 'ms',
                    'response_size' => strlen($result['response']) . ' bytes'
                ]
            ];
            
            echo ($success ? "✅" : "❌") . " {$test_name}: {$message}\n";
        }
        
        echo "\n";
    }
    
    /**
     * Validate response format compliance
     */
    public function testResponseFormat(): void {
        echo "📋 Testing Response Format Compliance...\n";
        
        $result = $this->httpGet('test-connection.php', ['test_level' => 'basic']);
        
        if ($result['success'] && $result['status_code'] === 200) {
            $response_data = json_decode($result['response'], true);
            
            if ($response_data) {
                $tests = [
                    'Has status field' => isset($response_data['status']),
                    'Status is success' => $response_data['status'] === 'success',
                    'Has data field' => isset($response_data['data']),
                    'Has meta field' => isset($response_data['meta']),
                    'Has request_id' => isset($response_data['request_id']),
                    'Data has tests' => isset($response_data['data']['tests']),
                    'Data has overall_status' => isset($response_data['data']['overall_status']),
                    'Meta has timestamp' => isset($response_data['meta']['timestamp']),
                    'Request ID is valid UUID' => preg_match('/^[a-f0-9\-]{36}$/', $response_data['request_id'] ?? ''),
                ];
                
                foreach ($tests as $test_name => $result) {
                    $this->test_results[] = [
                        'test' => "Response Format: {$test_name}",
                        'success' => $result,
                        'message' => $result ? "Valid" : "Missing or invalid",
                        'details' => []
                    ];
                    
                    echo ($result ? "✅" : "❌") . " {$test_name}: " . ($result ? "Valid" : "Missing or invalid") . "\n";
                }
            } else {
                echo "❌ Response Format: JSON decode failed\n";
            }
        } else {
            echo "❌ Response Format: Could not retrieve response for testing\n";
        }
        
        echo "\n";
    }
    
    /**
     * Generate comprehensive test report
     */
    public function generateReport(): string {
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, fn($test) => $test['success']));
        $failed_tests = $total_tests - $passed_tests;
        $duration = round(microtime(true) - $this->start_time, 3);
        
        $report = "# API Validation Report\n\n";
        $report .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
        $report .= "**Duration:** {$duration} seconds\n";
        $report .= "**Base URL:** {$this->base_url}\n\n";
        
        $report .= "## Summary\n\n";
        $report .= "- **Total Tests:** {$total_tests}\n";
        $report .= "- **Passed:** {$passed_tests} ✅\n";
        $report .= "- **Failed:** {$failed_tests} " . ($failed_tests > 0 ? "❌" : "✅") . "\n";
        $report .= "- **Success Rate:** " . round(($passed_tests / $total_tests) * 100, 1) . "%\n\n";
        
        if ($failed_tests > 0) {
            $report .= "## Failed Tests\n\n";
            foreach ($this->test_results as $test) {
                if (!$test['success']) {
                    $report .= "### ❌ {$test['test']}\n\n";
                    $report .= "**Message:** {$test['message']}\n\n";
                    if (!empty($test['details'])) {
                        $report .= "**Details:**\n";
                        foreach ($test['details'] as $key => $value) {
                            $report .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                        }
                        $report .= "\n";
                    }
                }
            }
        }
        
        $report .= "## All Test Results\n\n";
        $report .= "| Test | Status | Message | Details |\n";
        $report .= "|------|--------|---------|----------|\n";
        
        foreach ($this->test_results as $test) {
            $status = $test['success'] ? "✅ PASS" : "❌ FAIL";
            $details = !empty($test['details']) ? json_encode($test['details']) : "-";
            $report .= "| {$test['test']} | {$status} | {$test['message']} | {$details} |\n";
        }
        
        $report .= "\n## Recommendations\n\n";
        
        if ($failed_tests === 0) {
            $report .= "🎉 **All tests passed!** The API infrastructure is working correctly.\n\n";
            $report .= "### Next Steps:\n";
            $report .= "- Proceed with Phase 2 implementation\n";
            $report .= "- Begin dashboard module development\n";
            $report .= "- Set up additional endpoint testing\n";
        } else {
            $report .= "⚠️ **Some tests failed.** Please review and fix the following issues:\n\n";
            
            $failed_tests_by_category = [];
            foreach ($this->test_results as $test) {
                if (!$test['success']) {
                    $category = explode(':', $test['test'])[0];
                    $failed_tests_by_category[$category][] = $test['test'];
                }
            }
            
            foreach ($failed_tests_by_category as $category => $tests) {
                $report .= "**{$category} Issues:**\n";
                foreach ($tests as $test) {
                    $report .= "- {$test}\n";
                }
                $report .= "\n";
            }
        }
        
        return $report;
    }
}

// ============================================================================
// TEST EXECUTION
// ============================================================================

echo "🚀 Starting API Validation Suite...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

// Initialize validator
$base_url = 'http://localhost/supplier/api/v2'; // Adjust as needed
$validator = new APIValidator($base_url);

// Run all tests
$validator->testEndpointAvailability();
$validator->testConnectionEndpoint();
$validator->testResponseFormat();

// Generate and save report
$report = $validator->generateReport();

echo str_repeat("=", 60) . "\n";
echo "📊 Test Results Summary:\n";
echo str_repeat("-", 30) . "\n";

// Extract summary from report
$lines = explode("\n", $report);
$in_summary = false;
foreach ($lines as $line) {
    if (strpos($line, '## Summary') !== false) {
        $in_summary = true;
        continue;
    }
    if ($in_summary && strpos($line, '##') !== false) {
        break;
    }
    if ($in_summary && !empty(trim($line))) {
        echo $line . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Validation complete! Full report saved to test-report.md\n";

// Save report to file
file_put_contents(__DIR__ . '/test-report.md', $report);

echo "📁 Report location: " . __DIR__ . "/test-report.md\n";
echo "🔗 View report: cat " . __DIR__ . "/test-report.md\n";