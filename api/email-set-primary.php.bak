<?php
/**
 * Set Primary Email API Endpoint
 * 
 * POST /supplier/api/email-set-primary.php
 * Sets an email address as the primary email for the supplier
 * Updates vend_suppliers.email field to match
 * Only verified emails can be set as primary
 * 
 * Request: {"email_id": 123}
 * Response: {"success": true, "message": "Primary email updated"}
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
    
    // Get email details and verify ownership
    $query = "
        SELECT 
            id,
            supplier_id,
            email,
            is_primary,
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
    
    // Check if already primary
    if ($emailData['is_primary']) {
        sendJsonResponse(true, [
            'email' => $emailData['email']
        ], 'This email is already set as primary');
    }
    
    // Must be verified to set as primary
    if (!$emailData['verified']) {
        throw new Exception('Email must be verified before setting as primary. Please check your inbox for verification email.');
    }
    
    // Get current primary email for notification
    $currentPrimaryQuery = "
        SELECT email 
        FROM supplier_email_addresses 
        WHERE supplier_id = ? AND is_primary = 1
        LIMIT 1
    ";
    $stmt = $db->prepare($currentPrimaryQuery);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $currentPrimary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $oldPrimaryEmail = $currentPrimary['email'] ?? '';
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Remove primary flag from all emails for this supplier
        $updateQuery = "
            UPDATE supplier_email_addresses
            SET is_primary = 0
            WHERE supplier_id = ?
        ";
        $stmt = $db->prepare($updateQuery);
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $stmt->close();
        
        // Set new primary email
        $setPrimaryQuery = "
            UPDATE supplier_email_addresses
            SET is_primary = 1
            WHERE id = ? AND supplier_id = ?
            LIMIT 1
        ";
        $stmt = $db->prepare($setPrimaryQuery);
        $stmt->bind_param('is', $emailId, $supplierID);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to set primary email: ' . $stmt->error);
        }
        $stmt->close();
        
        // Update vend_suppliers.email to match new primary
        $updateSupplierQuery = "
            UPDATE vend_suppliers
            SET email = ?
            WHERE id = ?
            LIMIT 1
        ";
        $stmt = $db->prepare($updateSupplierQuery);
        $stmt->bind_param('ss', $emailData['email'], $supplierID);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update supplier email: ' . $stmt->error);
        }
        $stmt->close();
        
        // Commit transaction
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
    // Log action
    $logStmt = $db->prepare("
        INSERT INTO supplier_email_verification_log
        (supplier_id, email, action, ip_address, user_agent)
        VALUES (?, ?, 'primary_changed', ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logStmt->bind_param('ssss', $supplierID, $emailData['email'], $ipAddress, $userAgent);
    $logStmt->execute();
    $logStmt->close();
    
    // Send notification emails to both old and new primary
    if ($oldPrimaryEmail) {
        $emailTemplate = getPrimaryEmailChangedTemplate($supplierName, $oldPrimaryEmail, $emailData['email']);
        
        // Send to old primary email
        sendEmail(
            $oldPrimaryEmail,
            $emailTemplate['subject'],
            $emailTemplate['html'],
            $emailTemplate['body']
        );
        
        // Send to new primary email
        sendEmail(
            $emailData['email'],
            $emailTemplate['subject'],
            $emailTemplate['html'],
            $emailTemplate['body']
        );
    }
    
    sendJsonResponse(true, [
        'email' => $emailData['email'],
        'old_primary' => $oldPrimaryEmail
    ], 'Primary email updated successfully');

} catch (Exception $e) {
    error_log('Set Primary Email API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'email_set_primary_error',
        'message' => $e->getMessage()
    ], $e->getMessage(), 400);
}
