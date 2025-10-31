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
requireAuth();

header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // Get and validate claim ID
    $claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$claimId) {
        throw new Exception('Invalid claim ID');
    }

    // Get supplier ID from session
    $supplierId = getSupplierID();    if (!$supplierId) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get warranty claim details
    $pdo = pdo();

    $stmt = $pdo->prepare("
        SELECT
            fp.id,
            fp.id as claim_number,
            fp.supplier_status as status,
            p.name as product_name,
            p.sku,
            fp.fault_desc as issue_description,
            fp.staff_member as customer_name,
            '' as customer_email,
            DATE_SUB(fp.time_created, INTERVAL 30 DAY) as purchase_date,
            fp.time_created as claim_date,
            fp.fault_resolution as resolution,
            fp.supplier_status_timestamp as resolution_date,
            fp.fileName as images,
            o.name as outlet_name
        FROM faulty_products fp
        LEFT JOIN vend_products p ON fp.product_id = p.id
        LEFT JOIN vend_outlets o ON fp.store_location = o.id
        WHERE fp.id = ?
        AND p.supplier_id = ?
        AND p.deleted_at = '0000-00-00 00:00:00'
    ");

    $stmt->execute([$claimId, $supplierId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        throw new Exception('Warranty claim not found');
    }

    // Get claim notes/history - since there's no notes table, just create empty array
    $notes = [];
    
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
