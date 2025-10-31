<?php
/**
 * Supplier Portal API - Update Profile
 * 
 * Updates supplier profile information (name, email, phone, website)
 * 
 * @package CIS\Supplier\API
 * @version 2.0.0
 */

declare(strict_types=1);

// Load application bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';

// Require authentication
requireAuth();
$supplierID = getSupplierID();

// Set JSON headers
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    if (empty($data['name'])) {
        throw new Exception('Company name is required');
    }
    
    if (empty($data['email'])) {
        throw new Exception('Email address is required');
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address format');
    }
    
    // Validate website URL format if provided
    if (!empty($data['website'])) {
        if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid website URL format');
        }
    }
    
    // Sanitize inputs
    $name = trim($data['name']);
    $email = trim(strtolower($data['email']));
    $phone = !empty($data['phone']) ? trim($data['phone']) : null;
    $website = !empty($data['website']) ? trim($data['website']) : null;
    
    // Check if email is already in use by another supplier
    $emailCheckQuery = "
        SELECT id 
        FROM vend_suppliers 
        WHERE email = ? 
        AND id != ? 
        AND deleted_at IS NULL
        LIMIT 1
    ";
    $stmt = $db->prepare($emailCheckQuery);
    $stmt->bind_param('ss', $email, $supplierID);
    $stmt->execute();
    $existingSupplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existingSupplier) {
        throw new Exception('Email address is already in use by another supplier');
    }
    
    // Update supplier profile
    $updateQuery = "
        UPDATE vend_suppliers
        SET 
            name = ?,
            email = ?,
            phone = ?,
            website = ?
        WHERE id = ?
        AND deleted_at IS NULL
    ";
    
    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param('sssss', $name, $email, $phone, $website, $supplierID);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected === 0) {
        // No rows updated - either no changes or supplier not found
        // Check if supplier exists
        $checkQuery = "SELECT id FROM vend_suppliers WHERE id = ? AND deleted_at IS NULL";
        $stmt = $db->prepare($checkQuery);
        $stmt->bind_param('s', $supplierID);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if (!$exists) {
            throw new Exception('Supplier account not found');
        }
        
        // Supplier exists but no changes made - this is OK
    }
    
    // Log the profile update activity
    try {
        $activityQuery = "
            INSERT INTO supplier_activity_log (supplier_id, activity_type, details, created_at)
            VALUES (?, 'Profile Updated', ?, NOW())
        ";
        $details = "Updated profile information (name, email, phone, website)";
        $stmt = $db->prepare($activityQuery);
        $stmt->bind_param('ss', $supplierID, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Log activity failed but don't fail the whole operation
        error_log("Failed to log profile update activity: " . $e->getMessage());
    }
    
    // Return success
    sendJsonResponse(true, [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'website' => $website
    ], 'Profile updated successfully');
    
} catch (Exception $e) {
    error_log("Profile update error for supplier $supplierID: " . $e->getMessage());
    
    sendJsonResponse(false, [
        'error_type' => 'validation_error',
        'message' => $e->getMessage()
    ], $e->getMessage(), 400);
}
