<?php
/**
 * Add Email API Endpoint
 *
 * POST /supplier/api/email-add.php
 * Adds a new email address to the supplier account with verification
 *
 * Request: {"email": "new@email.com"}
 * Response: {"success": true, "message": "Verification email sent", "email_id": 123}
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/_bot_debug_bridge.php';
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/email-templates.php';
supplier_require_auth_bridge(true); // API endpoint

header('Content-Type: application/json');

try {
    $supplierID = supplier_current_id_bridge();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found');
    }

    // Get supplier name for email
    $supplierName = Auth::getSupplierName() ?? 'Supplier';

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    // Validation
    if (empty($email)) {
        throw new Exception('Email address is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address format');
    }

    $db = db();

    // Rate limiting - check if supplier has added too many emails recently
    $rateLimitQuery = "
        SELECT COUNT(*) as count
        FROM supplier_email_rate_limit
        WHERE supplier_id = ?
        AND action_type = 'add_email'
        AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = $db->prepare($rateLimitQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $rateLimit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($rateLimit['count'] >= 5) {
        throw new Exception('Rate limit exceeded. You can only add 5 email addresses per hour.');
    }

    // Check if email already exists for this supplier
    $checkQuery = "
        SELECT id
        FROM supplier_email_addresses
        WHERE supplier_id = ? AND email = ?
    ";
    $stmt = $db->prepare($checkQuery);
    $stmt->bind_param('ss', $supplierID, $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        throw new Exception('This email address is already added to your account');
    }

    // Generate verification token (64 characters)
    $verificationToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Insert new email
    $insertQuery = "
        INSERT INTO supplier_email_addresses
        (supplier_id, email, is_primary, verified, verification_token, verification_token_expires)
        VALUES (?, ?, 0, 0, ?, ?)
    ";
    $stmt = $db->prepare($insertQuery);
    $stmt->bind_param('ssss', $supplierID, $email, $verificationToken, $tokenExpiry);

    if (!$stmt->execute()) {
        throw new Exception('Failed to add email address: ' . $stmt->error);
    }

    $emailId = $stmt->insert_id;
    $stmt->close();

    // Log rate limit action
    $logRateLimit = $db->prepare("
        INSERT INTO supplier_email_rate_limit (supplier_id, action_type, window_start)
        VALUES (?, 'add_email', NOW())
    ");
    $logRateLimit->bind_param('s', $supplierID);
    $logRateLimit->execute();
    $logRateLimit->close();

    // Log action
    $logStmt = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'added', ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logStmt->bind_param('ssss', $supplierID, $email, $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();

    // Send verification email
    $verificationLink = SITE_URL . "/supplier/api/verify-email.php?token=" . $verificationToken;
    $emailTemplate = getEmailVerificationTemplate($supplierName, $verificationLink, $email);

    $emailSent = sendEmail(
        $email,
        $emailTemplate['subject'],
        $emailTemplate['html'],
        $emailTemplate['body']
    );

    if (!$emailSent) {
        error_log("Failed to send verification email to: {$email}");
    }

    // Log verification sent
    $logVerification = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'verification_sent', ?, ?)
    ");
    $logVerification->bind_param('ssss', $supplierID, $email, $ipAddress, $userAgent);
    $logVerification->execute();
    $logVerification->close();

    sendJsonResponse(true, [
        'email_id' => $emailId,
        'email' => $email,
        'verification_sent' => $emailSent
    ], 'Email address added. Please check your inbox to verify.');

} catch (Exception $e) {
    error_log('Add Email API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'email_add_error',
        'message' => $e->getMessage()
    ], $e->getMessage(), 400);
}
