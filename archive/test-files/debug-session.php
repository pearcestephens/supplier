<?php
/**
 * Debug Session - Check what's in the session
 */

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';

header('Content-Type: application/json');

Session::start();

$response = [
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? [],
    'auth_check' => Auth::check(),
    'supplier_id' => Auth::getSupplierId(),
    'supplier_name' => Auth::getSupplierName(),
];

echo json_encode($response, JSON_PRETTY_PRINT);
