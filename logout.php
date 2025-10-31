<?php
/**
 * Supplier Portal - Logout Handler
 * 
 * Destroys session and redirects to login
 * 
 * @package CIS\Supplier
 * @version 3.0.0 - Updated for bootstrap.php
 */

declare(strict_types=1);

// Define portal constant
define('SUPPLIER_PORTAL', true);

// Bootstrap application (uses bootstrap.php instead of app.php)
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

// Load portal configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Initialize session
init_session();

// Destroy session
destroy_session();

// Redirect to login with logout message
redirect(BASE_URL . 'login.php?logout=1');
