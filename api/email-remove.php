<?php
/**
 * Remove Email API Endpoint
 * 
 * POST /supplier/api/email-remove.php
 * Removes an email address from the supplier account
 * Cannot remove primary email
 * 
 * Request: {"email_id": 123}
 * Response: {"success": true, "message": "Email removed"}
 * 
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $supplierID = Auth::getSupplierId();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $emailId = (int)($input['email_id'] ?? 0);

    if ($emailId <= 0) {
        throw new Exception('Valid email ID is required');
    }

    $db = db();
    
    // Get email details and verify ownership
    $query = "
        SELECT 
            id,
            supplier_id,
            email,
            is_primary
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
    
    // Cannot remove primary email
    if ($emailData['is_primary']) {
        throw new Exception('Cannot remove primary email. Please set another email as primary first.');
    }
    
    // Delete the email
    $deleteQuery = "
        DELETE FROM supplier_email_addresses
        WHERE id = ? AND supplier_id = ?
        LIMIT 1
    ";
    $stmt = $db->prepare($deleteQuery);
    $stmt->bind_param('is', $emailId, $supplierID);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to remove email: ' . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affectedRows === 0) {
        throw new Exception('Email address not found');
    }
    
    // Log action
    $logStmt = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'removed', ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logStmt->bind_param('ssss', $supplierID, $emailData['email'], $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();
    
    sendJsonResponse(true, [
        'email_id' => $emailId,
        'email' => $emailData['email']
    ], 'Email address removed successfully');

} catch (Exception $e) {
    error_log('Remove Email API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'email_remove_error',
        'message' => $e->getMessage()
    ], $e->getMessage(), 400);
}
