<?php
/**
 * Simple API Test - Dashboard Stats
 * 
 * Basic test to check API functionality
 */

declare(strict_types=1);

// Basic test response
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Test database connection
    $host = '127.0.0.1';
    $dbname = 'jcepnzzkmj';
    $username = 'jcepnzzkmj';
    $password = 'wprKh9Jq63';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Simple test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transfers LIMIT 1");
    $result = $stmt->fetch();
    
    $response = [
        'success' => true,
        'data' => [
            'test' => 'API connection working',
            'db_test' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => [
            'message' => 'API Error: ' . $e->getMessage(),
            'code' => 500
        ]
    ];
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>