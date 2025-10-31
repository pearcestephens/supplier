<?php
/**
 * Update Account API Endpoint
 * Handles inline editing of account fields
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
requireAuth();

header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }
    
    // Get supplier ID from session
    $supplierId = getSupplierID();    if (!$supplierId) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get field and value from request
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if (empty($field)) {
        throw new Exception('Field name is required');
    }

    // Whitelist of allowed fields to update
    $allowedFields = [
        'company_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'postal_code',
        'country',
        'notes'
    ];

    if (!in_array($field, $allowedFields)) {
        throw new Exception('Invalid field name');
    }

    // Validate value based on field type
    switch ($field) {
        case 'contact_email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }
            break;

        case 'contact_phone':
            // Basic phone validation (allow various formats)
            if (!empty($value) && !preg_match('/^[0-9\s\-\+\(\)]+$/', $value)) {
                throw new Exception('Invalid phone number');
            }
            break;

        case 'postal_code':
            // Allow various postal code formats
            if (!empty($value) && !preg_match('/^[A-Z0-9\s\-]+$/i', $value)) {
                throw new Exception('Invalid postal code');
            }
            break;
    }

    // Update the field
    $db = Database::getInstance();
    $mysqli = $db->getConnection();

    $stmt = $mysqli->prepare("
        UPDATE suppliers
        SET {$field} = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param('si', $value, $supplierId);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update field');
    }

    // Log the change
    error_log("Account update: Supplier #{$supplierId} updated {$field}");

    // Return success with formatted value
    $displayValue = $value;

    // Format display value based on field type
    if ($field === 'contact_phone' && !empty($value)) {
        // Format phone number for display (simple formatting)
        $displayValue = preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', preg_replace('/\D/', '', $value));
    }

    echo json_encode([
        'success' => true,
        'message' => ucwords(str_replace('_', ' ', $field)) . ' updated successfully',
        'field' => $field,
        'value' => $value,
        'display_value' => $displayValue
    ]);

} catch (Exception $e) {
    error_log("Update Account API Error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
