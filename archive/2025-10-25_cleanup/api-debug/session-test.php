<?php
/**
 * Session Test Endpoint
 * 
 * Tests if session authentication works correctly
 * Returns session info and authentication status
 */

declare(strict_types=1);

// Load standalone libraries
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Session.php';
require_once __DIR__ . '/../lib/Auth.php';

// Start session
Session::start();

// Get session data
$sessionData = [
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
    'session_id' => session_id(),
    'session_name' => session_name(),
    'cookie_params' => session_get_cookie_params(),
    'is_authenticated' => Auth::check(),
    'supplier_id' => Auth::check() ? Auth::getSupplierId() : null,
    'supplier_name' => Auth::check() ? Auth::getSupplierName() : null,
    'session_data' => [
        'authenticated' => $_SESSION['authenticated'] ?? null,
        'supplier_id' => $_SESSION['supplier_id'] ?? null,
        'supplier_name' => $_SESSION['supplier_name'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'created' => $_SESSION['_created'] ?? null,
    ],
    'cookies_received' => $_COOKIE,
    'request_info' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'host' => $_SERVER['HTTP_HOST'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]
];

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Session test completed',
    'data' => $sessionData,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
