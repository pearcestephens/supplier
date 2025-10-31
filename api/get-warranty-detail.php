<?php
/**
 * Get Warranty Detail API Endpoint
 * Returns detailed warranty claim information for modal display
 * 
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
Auth::requireAuth();

header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }
    
    // Get claim ID
    $claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$claimId) {
        throw new Exception('Invalid claim ID');
    }
    
    // Get supplier ID from session
    $supplierId = Auth::getSupplierId();
    
    if (!$supplierId) {
        throw new Exception('Supplier ID not found in session');
    }
    
    // Get warranty claim details
    $db = Database::getInstance();
    $mysqli = $db->getConnection();
    
    $stmt = $mysqli->prepare("
        SELECT 
            wc.id,
            wc.claim_number,
            wc.status,
            wc.product_name,
            wc.sku,
            wc.issue_description,
            wc.customer_name,
            wc.customer_email,
            wc.purchase_date,
            wc.claim_date,
            wc.resolution,
            wc.resolution_date,
            wc.images,
            o.outlet_name
        FROM warranty_claims wc
        LEFT JOIN vend_outlets o ON wc.outlet_id = o.outlet_id
        WHERE wc.id = ?
        AND wc.supplier_id = ?
    ");
    
    $stmt->bind_param('ii', $claimId, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Warranty claim not found');
    }
    
    $claim = $result->fetch_assoc();
    
    // Get claim notes/history
    $stmt = $mysqli->prepare("
        SELECT 
            note,
            created_by,
            created_at
        FROM warranty_claim_notes
        WHERE claim_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('i', $claimId);
    $stmt->execute();
    $notesResult = $stmt->get_result();
    
    $notes = [];
    while ($note = $notesResult->fetch_assoc()) {
        $notes[] = $note;
    }
    
    // Generate HTML for modal
    $statusBadge = renderStatusBadge($claim['status'], 'warranty', true, true);
    
    $html = '<div class="warranty-detail-modal">';
    
    // Header section
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-6">';
    $html .= '<h5 class="mb-2">Claim Information</h5>';
    $html .= '<p class="mb-1"><strong>Claim #:</strong> ' . htmlspecialchars($claim['claim_number']) . '</p>';
    $html .= '<p class="mb-1"><strong>Status:</strong> ' . $statusBadge . '</p>';
    $html .= '<p class="mb-1"><strong>Claim Date:</strong> ' . date('M d, Y', strtotime($claim['claim_date'])) . '</p>';
    if ($claim['resolution_date']) {
        $html .= '<p class="mb-1"><strong>Resolved:</strong> ' . date('M d, Y', strtotime($claim['resolution_date'])) . '</p>';
    }
    $html .= '</div>';
    
    $html .= '<div class="col-md-6">';
    $html .= '<h5 class="mb-2">Customer Information</h5>';
    $html .= '<p class="mb-1"><strong>Name:</strong> ' . htmlspecialchars($claim['customer_name']) . '</p>';
    $html .= '<p class="mb-1"><strong>Email:</strong> ' . htmlspecialchars($claim['customer_email']) . '</p>';
    $html .= '<p class="mb-1"><strong>Outlet:</strong> ' . htmlspecialchars($claim['outlet_name'] ?? 'N/A') . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Product section
    $html .= '<div class="mb-4">';
    $html .= '<h5 class="mb-2">Product Information</h5>';
    $html .= '<div class="card">';
    $html .= '<div class="card-body">';
    $html .= '<p class="mb-1"><strong>Product:</strong> ' . htmlspecialchars($claim['product_name']) . '</p>';
    $html .= '<p class="mb-1"><strong>SKU:</strong> <span class="font-monospace">' . htmlspecialchars($claim['sku']) . '</span></p>';
    $html .= '<p class="mb-0"><strong>Purchase Date:</strong> ' . date('M d, Y', strtotime($claim['purchase_date'])) . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Issue description
    $html .= '<div class="mb-4">';
    $html .= '<h5 class="mb-2">Issue Description</h5>';
    $html .= '<div class="alert alert-warning">' . nl2br(htmlspecialchars($claim['issue_description'])) . '</div>';
    $html .= '</div>';
    
    // Images
    if ($claim['images']) {
        $images = json_decode($claim['images'], true);
        if (is_array($images) && count($images) > 0) {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="mb-2">Images</h5>';
            $html .= '<div class="row g-2">';
            foreach ($images as $image) {
                $html .= '<div class="col-md-3">';
                $html .= '<img src="' . htmlspecialchars($image) . '" class="img-fluid img-thumbnail lazy-load" data-src="' . htmlspecialchars($image) . '" alt="Claim image">';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    // Resolution
    if ($claim['resolution']) {
        $html .= '<div class="mb-4">';
        $html .= '<h5 class="mb-2">Resolution</h5>';
        $html .= '<div class="alert alert-success">' . nl2br(htmlspecialchars($claim['resolution'])) . '</div>';
        $html .= '</div>';
    }
    
    // Notes/History
    if (count($notes) > 0) {
        $html .= '<div class="mb-4">';
        $html .= '<h5 class="mb-2">History</h5>';
        foreach ($notes as $note) {
            $html .= '<div class="card mb-2">';
            $html .= '<div class="card-body py-2">';
            $html .= '<div class="d-flex justify-content-between align-items-start">';
            $html .= '<div>' . nl2br(htmlspecialchars($note['note'])) . '</div>';
            $html .= '<small class="text-muted ms-3">' . date('M d, Y g:i A', strtotime($note['created_at'])) . '</small>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'claim' => $claim
    ]);
    
} catch (Exception $e) {
    error_log("Get Warranty Detail API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load warranty details: ' . $e->getMessage()
    ]);
}
