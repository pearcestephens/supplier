<?php
/**
 * Account Update API Endpoint
 *
 * SECURITY: Server-side validation and sanitization of all inputs
 * Prevents injection attacks and ensures data integrity
 *
 * POST /supplier/api/account-update.php
 * {
 *   "field": "name|email|phone|website",
 *   "value": "new value"
 * }
 *
 * @package Supplier\Portal\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    // Get supplier ID from session
    $supplierID = Auth::getSupplierId();
    if (!$supplierID) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);

    $field = trim($input['field'] ?? '');
    $value = trim($input['value'] ?? '');

    // ========================================================================
    // VALIDATION - Whitelist allowed fields
    // ========================================================================

    $allowedFields = ['name', 'email', 'phone', 'website'];

    if (!in_array($field, $allowedFields)) {
        throw new Exception('Invalid field: ' . $field);
    }

    if (empty($value) && $field !== 'phone' && $field !== 'website') {
        throw new Exception($field . ' is required');
    }

    // Field-specific validation
    switch ($field) {
        case 'name':
            if (strlen($value) < 3 || strlen($value) > 255) {
                throw new Exception('Company name must be between 3 and 255 characters');
            }
            break;

        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }
            break;

        case 'phone':
            // Allow empty, or validate phone format
            if (!empty($value)) {
                if (!preg_match('/^[\d\s\-\+\(\)]{7,}$/', $value)) {
                    throw new Exception('Invalid phone number format');
                }
            }
            break;

        case 'website':
            // Allow empty, or validate URL
            if (!empty($value)) {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid website URL');
                }
            }
            break;
    }

    // ========================================================================
    // UPDATE RECORD (with validated data)
    // ========================================================================

    $db = db();

    $updateQuery = "UPDATE vend_suppliers SET {$field} = ? WHERE id = ? LIMIT 1";
    $stmt = $db->prepare($updateQuery);

    if (!$stmt) {
        throw new Exception('Database error: ' . $db->error);
    }

    $stmt->bind_param('ss', $value, $supplierID);

    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }

    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows === 0) {
        throw new Exception('No changes made. Account may not exist.');
    }

    // ========================================================================
    // LOG ACTION
    // ========================================================================

    $logStmt = $db->prepare("
        INSERT INTO supplier_activity_log
        (supplier_id, action_type, action_details, created_at)
        VALUES (?, 'account_update', ?, NOW())
    ");

    if ($logStmt) {
        $logDetails = "Updated account field: {$field}";
        $logStmt->bind_param('ss', $supplierID, $logDetails);
        $logStmt->execute();
        $logStmt->close();
    }

    // ========================================================================
    // SUCCESS RESPONSE
    // ========================================================================

    sendJsonResponse(true, [
        'field' => $field,
        'value' => $value,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'Account information updated successfully');

} catch (Exception $e) {
    error_log('Account Update API Error: ' . $e->getMessage());
    sendJsonResponse(false, [
        'error' => 'account_update_error',
        'message' => $e->getMessage()
    ], 'Failed to update account information', 400);
}
