<?php
/**
 * Email List API Endpoint
 * 
 * GET /supplier/api/email-list.php
 * Returns all email addresses for the authenticated supplier
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

    $db = db();
    
    // Get all email addresses for this supplier
    $query = "
        SELECT 
            id,
            email,
            is_primary,
            verified,
            created_at,
            updated_at
        FROM supplier_email_addresses
        WHERE supplier_id = ?
        ORDER BY is_primary DESC, created_at ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $supplierID);
    $stmt->execute();
    $result = $stmt->get_result();
    $emails = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Convert boolean fields to actual booleans for JSON
    foreach ($emails as &$email) {
        $email['is_primary'] = (bool)$email['is_primary'];
        $email['verified'] = (bool)$email['verified'];
        $email['id'] = (int)$email['id'];
    }
    
    sendJsonResponse(true, [
        'emails' => $emails,
        'count' => count($emails)
    ], 'Email addresses retrieved successfully');

} catch (Exception $e) {
    error_log('Email List API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'email_list_error',
        'message' => $e->getMessage()
    ], 'Failed to retrieve email addresses', 500);
}
