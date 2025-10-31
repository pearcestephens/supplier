<?php
/**
 * Session Keep-Alive Ping Endpoint
 * Called every 30 seconds by portal.js to prevent timeout
 * 
 * @package CIS\Supplier
 * @version 2.0.0
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

try {
    requireAuth();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'time' => time(),
        'session_active' => true,
        'supplier_id' => getSupplierID()
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Session expired or invalid'
    ]);
}
