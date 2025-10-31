<?php
/**
 * Resend Email Verification API Endpoint
 * 
 * POST /supplier/api/email-resend-verification.php
 * Resends verification email for an unverified email address
 * 
 * Request: {"email_id": 123}
 * Response: {"success": true, "message": "Verification email sent"}
 * 
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';
requireAuth();

header('Content-Type: application/json');

try {
    $supplierID = Auth::getSupplierId();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found in session');
    }
    
    $supplierName = Auth::getSupplierName() ?? 'Supplier';

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $emailId = (int)($input['email_id'] ?? 0);

    if ($emailId <= 0) {
        throw new Exception('Valid email ID is required');
    }

    $db = db();
    
    // Rate limiting - check if supplier has resent too many times
    $rateLimitQuery = "
        SELECT COUNT(*) as count
        FROM supplier_email_rate_limit
        WHERE supplier_id = ?
        AND action_type = 'resend_verification'
        AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = $db->prepare($rateLimitQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $rateLimit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($rateLimit['count'] >= 5) {
        throw new Exception('Rate limit exceeded. You can only resend verification 5 times per hour.');
    }
    
    // Get email details and verify ownership
    $query = "
        SELECT 
            id,
            supplier_id,
            email,
            verified
        FROM supplier_email_addresses
        WHERE id = ? AND supplier_id = ?
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('is', $emailId, $supplierID);
    $stmt->execute();
    $emailData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$emailData) {
        throw new Exception('Email address not found or does not belong to your account');
    }
    
    // Check if already verified
    if ($emailData['verified']) {
        throw new Exception('This email address is already verified');
    }
    
    // Generate new verification token
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update verification token
    $updateQuery = "
        UPDATE supplier_email_addresses
        SET verification_token = ?,
            verification_token_expires = ?
        WHERE id = ? AND supplier_id = ?
        LIMIT 1
    ";
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param('ssis', $verificationToken, $tokenExpiry, $emailId, $supplierID);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update verification token: ' . $stmt->error);
    }
    $stmt->close();
    
    // Log rate limit action
    $logRateLimit = $db->prepare("
        INSERT INTO supplier_email_rate_limit (supplier_id, action_type, window_start)
        VALUES (?, 'resend_verification', NOW())
    ");
    $logRateLimit->bind_param('s', $supplierID);
    $logRateLimit->execute();
    $logRateLimit->close();
    
    // Send verification email
    $verificationLink = SITE_URL . "/supplier/api/verify-email.php?token=" . $verificationToken;
    $emailTemplate = getEmailVerificationTemplate($supplierName, $verificationLink, $emailData['email']);
    
    $emailSent = sendEmail(
        $emailData['email'],
        $emailTemplate['subject'],
        $emailTemplate['html'],
        $emailTemplate['body']
    );
    
    if (!$emailSent) {
        error_log("Failed to send verification email to: {$emailData['email']}");
        throw new Exception('Failed to send verification email. Please try again later.');
    }
    
    // Log verification sent
    $logVerification = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'verification_sent', ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logVerification->bind_param('ssss', $supplierID, $emailData['email'], $ipAddress, $userAgent);
    $logVerification->execute();
    $logVerification->close();
    
    sendJsonResponse(true, [
        'email' => $emailData['email']
    ], 'Verification email sent. Please check your inbox.');

} catch (Exception $e) {
    error_log('Resend Verification API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'email_resend_error',
        'message' => $e->getMessage()
    ], $e->getMessage(), 400);
}
