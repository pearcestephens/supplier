<?php
/**
 * Email Verification Endpoint
 * 
 * GET /supplier/api/verify-email.php?token=XXX
 * Verifies email address using verification token
 * Redirects to account page with success/error message
 * 
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $token = trim($_GET['token'] ?? '');

    if (empty($token)) {
        throw new Exception('Verification token is required');
    }

    $db = db();
    
    // Find email by token
    $query = "
        SELECT 
            id,
            supplier_id,
            email,
            verified,
            verification_token_expires
        FROM supplier_email_addresses
        WHERE verification_token = ?
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $emailData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$emailData) {
        throw new Exception('Invalid verification token');
    }
    
    if ($emailData['verified']) {
        // Already verified - redirect with info message
        header('Location: /supplier/account.php?msg=already_verified');
        exit;
    }
    
    // Check if token has expired
    if (strtotime($emailData['verification_token_expires']) < time()) {
        throw new Exception('Verification token has expired. Please request a new verification email.');
    }
    
    // Mark email as verified and clear token
    $updateQuery = "
        UPDATE supplier_email_addresses
        SET verified = 1,
            verification_token = NULL,
            verification_token_expires = NULL
        WHERE id = ?
        LIMIT 1
    ";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param('i', $emailData['id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify email: ' . $stmt->error);
    }
    $stmt->close();
    
    // Log verification
    $logStmt = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'verified', ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logStmt->bind_param('ssss', $emailData['supplier_id'], $emailData['email'], $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();
    
    // Redirect to account page with success message
    header('Location: /supplier/account.php?msg=email_verified');
    exit;

} catch (Exception $e) {
    error_log('Email Verification Error: ' . $e->getMessage());
    
    // Redirect to account page with error message
    $errorMsg = urlencode($e->getMessage());
    header('Location: /supplier/account.php?msg=verification_error&error=' . $errorMsg);
    exit;
}
