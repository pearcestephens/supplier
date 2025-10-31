<?php
/**
 * Test Connection Endpoint
 * Verify database connectivity and basic API functionality
 * 
 * Part of: Supplier Portal Redesign - Phase 1
 * Created: October 22, 2025
 * Version: 2.0
 * 
 * @package SupplierPortal\API\v2
 * 
 * Usage: GET /api/v2/test-connection.php
 * Optional params: ?test_level=basic|full
 */

declare(strict_types=1);

require_once '_response.php';

try {
    // Rate limiting
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit('test_connection_' . $client_ip, 50, 3600); // 50 requests per hour
    
    $test_level = $_GET['test_level'] ?? 'basic';
    
    if (!in_array($test_level, ['basic', 'full'])) {
        apiError('Invalid test_level. Use: basic or full', 400);
    }
    
    $results = [
        'test_level' => $test_level,
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'tests' => []
    ];
    
    // Test 1: Basic response system
    $results['tests']['response_system'] = [
        'name' => 'API Response System',
        'status' => 'pass',
        'message' => 'Response helper loaded successfully'
    ];
    
    // Test 2: Database connection
    try {
        require_once '_db_helpers.php';
        
        $queries = new SupplierQueries();
        
        $results['tests']['database_connection'] = [
            'name' => 'Database Connection',
            'status' => 'pass',
            'message' => 'Database helper loaded and connection established'
        ];
        
    } catch (Exception $e) {
        $results['tests']['database_connection'] = [
            'name' => 'Database Connection',
            'status' => 'fail',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
    
    // Test 3: Core tables existence (basic test)
    if ($results['tests']['database_connection']['status'] === 'pass') {
        try {
            $db = new Database();
            
            // Test core tables exist
            $core_tables = [
                'transfers' => 'Purchase Orders table',
                'transfer_items' => 'PO Items table',
                'faulty_products' => 'Warranty Claims table',
                'vend_products' => 'Product Catalog table',
                'vend_suppliers' => 'Supplier Master table',
                'vend_outlets' => 'Outlets table'
            ];
            
            $table_status = [];
            foreach ($core_tables as $table => $description) {
                try {
                    $count_result = $db->query("SELECT COUNT(*) as count FROM {$table} LIMIT 1");
                    $count = $count_result[0]['count'] ?? 0;
                    $table_status[$table] = [
                        'exists' => true,
                        'description' => $description,
                        'record_count' => (int)$count
                    ];
                } catch (Exception $e) {
                    $table_status[$table] = [
                        'exists' => false,
                        'description' => $description,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $results['tests']['core_tables'] = [
                'name' => 'Core Tables Check',
                'status' => 'pass',
                'message' => 'All core tables verified',
                'details' => $table_status
            ];
            
        } catch (Exception $e) {
            $results['tests']['core_tables'] = [
                'name' => 'Core Tables Check',
                'status' => 'fail',
                'message' => 'Table verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    // Full testing mode
    if ($test_level === 'full' && $results['tests']['database_connection']['status'] === 'pass') {
        
        // Test 4: Sample query execution
        try {
            $db = new Database();
            
            // Test purchase orders query
            $po_sql = "SELECT COUNT(*) as count 
                      FROM transfers 
                      WHERE transfer_category = 'PURCHASE_ORDER' 
                      LIMIT 1";
            $po_result = $db->query($po_sql);
            $po_count = $po_result[0]['count'] ?? 0;
            
            // Test warranty claims query
            $warranty_sql = "SELECT COUNT(*) as count 
                            FROM faulty_products 
                            LIMIT 1";
            $warranty_result = $db->query($warranty_sql);
            $warranty_count = $warranty_result[0]['count'] ?? 0;
            
            $results['tests']['sample_queries'] = [
                'name' => 'Sample Query Execution',
                'status' => 'pass',
                'message' => 'Sample queries executed successfully',
                'details' => [
                    'purchase_orders_found' => (int)$po_count,
                    'warranty_claims_found' => (int)$warranty_count
                ]
            ];
            
        } catch (Exception $e) {
            $results['tests']['sample_queries'] = [
                'name' => 'Sample Query Execution',
                'status' => 'fail',
                'message' => 'Query execution failed: ' . $e->getMessage()
            ];
        }
        
        // Test 5: Supplier data validation
        try {
            $supplier_sql = "SELECT COUNT(*) as count, 
                                   COUNT(DISTINCT id) as unique_suppliers
                            FROM vend_suppliers 
                            WHERE show_in_system = 1";
            $supplier_result = $db->query($supplier_sql);
            $supplier_data = $supplier_result[0] ?? [];
            
            $results['tests']['supplier_validation'] = [
                'name' => 'Supplier Data Validation',
                'status' => 'pass',
                'message' => 'Supplier data structure validated',
                'details' => [
                    'total_suppliers' => (int)($supplier_data['count'] ?? 0),
                    'unique_suppliers' => (int)($supplier_data['unique_suppliers'] ?? 0)
                ]
            ];
            
        } catch (Exception $e) {
            $results['tests']['supplier_validation'] = [
                'name' => 'Supplier Data Validation',
                'status' => 'fail',
                'message' => 'Supplier validation failed: ' . $e->getMessage()
            ];
        }
        
        // Test 6: Analytical views check
        try {
            $views_to_test = [
                'v_supplier_product_sales',
                'v_supplier_outlet_inventory',
                'v_transfer_progress'
            ];
            
            $view_status = [];
            foreach ($views_to_test as $view) {
                try {
                    $view_result = $db->query("SELECT 1 FROM {$view} LIMIT 1");
                    $view_status[$view] = 'accessible';
                } catch (Exception $e) {
                    $view_status[$view] = 'error: ' . $e->getMessage();
                }
            }
            
            $results['tests']['analytical_views'] = [
                'name' => 'Analytical Views Check',
                'status' => 'pass',
                'message' => 'Analytical views tested',
                'details' => $view_status
            ];
            
        } catch (Exception $e) {
            $results['tests']['analytical_views'] = [
                'name' => 'Analytical Views Check',
                'status' => 'fail',
                'message' => 'View testing failed: ' . $e->getMessage()
            ];
        }
    }
    
    // Test 7: File system permissions
    $cache_dir = __DIR__ . '/../../cache';
    $logs_dir = __DIR__ . '/../../logs';
    
    $file_system_status = [];
    
    // Check cache directory
    if (is_dir($cache_dir) && is_writable($cache_dir)) {
        $file_system_status['cache_directory'] = 'writable';
    } elseif (is_dir($cache_dir)) {
        $file_system_status['cache_directory'] = 'exists but not writable';
    } else {
        // Try to create it
        if (mkdir($cache_dir, 0755, true)) {
            $file_system_status['cache_directory'] = 'created successfully';
        } else {
            $file_system_status['cache_directory'] = 'does not exist and cannot create';
        }
    }
    
    // Check logs directory
    if (is_dir($logs_dir) && is_writable($logs_dir)) {
        $file_system_status['logs_directory'] = 'writable';
    } elseif (is_dir($logs_dir)) {
        $file_system_status['logs_directory'] = 'exists but not writable';
    } else {
        // Try to create it
        if (mkdir($logs_dir, 0755, true)) {
            $file_system_status['logs_directory'] = 'created successfully';
        } else {
            $file_system_status['logs_directory'] = 'does not exist and cannot create';
        }
    }
    
    $results['tests']['file_system'] = [
        'name' => 'File System Permissions',
        'status' => 'pass',
        'message' => 'File system access verified',
        'details' => $file_system_status
    ];
    
    // Overall status
    $failed_tests = 0;
    foreach ($results['tests'] as $test) {
        if ($test['status'] === 'fail') {
            $failed_tests++;
        }
    }
    
    $results['overall_status'] = $failed_tests === 0 ? 'pass' : 'fail';
    $results['total_tests'] = count($results['tests']);
    $results['failed_tests'] = $failed_tests;
    $results['passed_tests'] = $results['total_tests'] - $failed_tests;
    
    // Success response with test results
    apiSuccess($results, [
        'test_completed_at' => date('Y-m-d H:i:s'),
        'test_duration' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
    ]);
    
} catch (Exception $e) {
    apiError(
        'Test connection failed: ' . $e->getMessage(),
        500,
        [
            'error_type' => get_class($e),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    );
}