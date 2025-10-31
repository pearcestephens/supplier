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
            fp.serial_number,
            fp.fault_desc as issue_description,
            fp.staff_member as customer_name,
            '' as customer_email,
            fp.time_created as claim_date,
            fp.supplier_status_timestamp as resolution_date,
            o.name as outlet_name,
            o.id as outlet_id
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

    // Get claim notes/history from faulty_product_notes table
    $stmt = $pdo->prepare("
        SELECT
            note,
            action,
            internal_ref,
            created_by,
            created_at
        FROM faulty_product_notes
        WHERE faulty_product_id = ?
        AND supplier_id = ?
        ORDER BY created_at DESC
    ");

    $stmt->execute([$claimId, $supplierId]);
    $notes = [];
    while ($note = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notes[] = $note;
    }

    // Get media uploads for this fault
    $stmt = $pdo->prepare("
        SELECT
            id,
            fileName as original_name,
            tempFileName as stored_name,
            upload_time
        FROM faulty_product_media_uploads
        WHERE fault_id = ?
        ORDER BY upload_time DESC
    ");

    $stmt->execute([$claimId]);
    $images = [];
    while ($image = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $images[] = $image;
    }

    // Generate HTML for modal
    $statusBadge = renderStatusBadge($claim['status'], 'warranty', true, true);

    // Calculate days open
    $daysOpen = floor((time() - strtotime($claim['claim_date'])) / 86400);

    $html = '<div class="warranty-detail-modal">';

    // HEADER SECTION - Enhanced with timeline
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-4">';
    $html .= '<h5 class="mb-3"><i class="fas fa-clipboard-check text-primary me-2"></i>Claim Information</h5>';
    $html .= '<div class="mb-2"><span class="badge bg-secondary">Claim #' . htmlspecialchars($claim['claim_number']) . '</span></div>';
    $html .= '<div class="mb-2"><strong>Status:</strong> ' . $statusBadge . '</div>';
    $html .= '<div class="mb-2"><strong>Filed:</strong> <span class="text-muted">' . date('M d, Y g:i A', strtotime($claim['claim_date'])) . '</span></div>';
    if ($claim['resolution_date']) {
        $html .= '<div class="mb-2"><strong>Resolved:</strong> <span class="text-success">' . date('M d, Y g:i A', strtotime($claim['resolution_date'])) . '</span></div>';
    } else {
        $html .= '<div class="mb-2"><strong>Days Open:</strong> <span class="badge bg-warning text-dark">' . $daysOpen . ' days</span></div>';
    }
    $html .= '</div>';

    $html .= '<div class="col-md-4">';
    $html .= '<h5 class="mb-3"><i class="fas fa-store text-info me-2"></i>Store & Contact</h5>';
    $html .= '<div class="mb-2"><strong>Staff:</strong> ' . htmlspecialchars($claim['customer_name']) . '</div>';
    $html .= '<div class="mb-2"><strong>Outlet:</strong> <span class="text-primary">' . htmlspecialchars($claim['outlet_name'] ?? 'N/A') . '</span></div>';
    if (!empty($claim['serial_number'])) {
        $html .= '<div class="mb-2"><strong>Serial:</strong> <code class="small">' . htmlspecialchars($claim['serial_number']) . '</code></div>';
    }
    $html .= '</div>';

    $html .= '<div class="col-md-4">';
    $html .= '<h5 class="mb-3"><i class="fas fa-box text-warning me-2"></i>Product Details</h5>';
    $html .= '<div class="mb-2"><strong>' . htmlspecialchars($claim['product_name']) . '</strong></div>';
    $html .= '<div class="mb-2"><strong>SKU:</strong> <code>' . htmlspecialchars($claim['sku']) . '</code></div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<hr class="my-4">';

    // ISSUE DESCRIPTION - Enhanced with card
    $html .= '<div class="mb-4">';
    $html .= '<h5 class="mb-3"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Issue Description</h5>';
    $html .= '<div class="card border-warning">';
    $html .= '<div class="card-body bg-light">';
    $html .= '<div class="issue-text">' . nl2br(htmlspecialchars($claim['issue_description'])) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    // EXCEPTIONAL IMAGE GALLERY with lightbox functionality
    if (!empty($images)) {
        $html .= '<div class="mb-4">';
        $html .= '<h5 class="mb-3"><i class="fas fa-images text-success me-2"></i>Evidence Photos <span class="badge bg-secondary">' . count($images) . '</span></h5>';
        $html .= '<div class="image-gallery">';
        $html .= '<div class="row g-3">';

        foreach ($images as $idx => $image) {
            $imagePath = '/supplier/uploads/faulty_products/' . htmlspecialchars($image['stored_name']);
            $uploadDate = date('M d, Y', strtotime($image['upload_time']));

            $html .= '<div class="col-md-3 col-sm-6">';
            $html .= '<div class="image-wrapper position-relative">';
            $html .= '<div class="image-card shadow-sm" style="cursor: pointer;" onclick="openImageLightbox(' . $idx . ')" data-image-src="' . $imagePath . '">';
            $html .= '<img src="' . $imagePath . '" class="img-fluid rounded" alt="Evidence photo" loading="lazy" style="width: 100%; height: 200px; object-fit: cover;">';
            $html .= '<div class="image-overlay">';
            $html .= '<i class="fas fa-search-plus fa-2x text-white"></i>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="text-center mt-2">';
            $html .= '<small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>' . $uploadDate . '</small>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }

    // NOTES/HISTORY - Enhanced timeline view
    if (count($notes) > 0) {
        $html .= '<div class="mb-4">';
        $html .= '<h5 class="mb-3"><i class="fas fa-history text-info me-2"></i>Activity Timeline <span class="badge bg-secondary">' . count($notes) . '</span></h5>';
        $html .= '<div class="timeline-container">';

        foreach ($notes as $note) {
            $actionBadge = '';
            $actionIcon = 'fa-comment';
            $actionColor = 'secondary';

            switch (strtolower($note['action'] ?? '')) {
                case 'investigating':
                    $actionBadge = '<span class="badge bg-warning"><i class="fas fa-search me-1"></i>Investigating</span>';
                    $actionIcon = 'fa-search';
                    $actionColor = 'warning';
                    break;
                case 'accepted':
                    $actionBadge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Accepted</span>';
                    $actionIcon = 'fa-check-circle';
                    $actionColor = 'success';
                    break;
                case 'declined':
                    $actionBadge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Declined</span>';
                    $actionIcon = 'fa-times-circle';
                    $actionColor = 'danger';
                    break;
                case 'replacement':
                    $actionBadge = '<span class="badge bg-primary"><i class="fas fa-exchange-alt me-1"></i>Replacement</span>';
                    $actionIcon = 'fa-exchange-alt';
                    $actionColor = 'primary';
                    break;
                case 'refund':
                    $actionBadge = '<span class="badge bg-info"><i class="fas fa-dollar-sign me-1"></i>Refund</span>';
                    $actionIcon = 'fa-dollar-sign';
                    $actionColor = 'info';
                    break;
                default:
                    $actionBadge = '<span class="badge bg-secondary"><i class="fas fa-comment me-1"></i>Note</span>';
            }

            $html .= '<div class="card mb-3 border-' . $actionColor . '">';
            $html .= '<div class="card-body">';
            $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
            $html .= '<div>' . $actionBadge;
            if (!empty($note['internal_ref'])) {
                $html .= ' <small class="text-muted">Ref: ' . htmlspecialchars($note['internal_ref']) . '</small>';
            }
            $html .= '</div>';
            $html .= '<div class="text-end">';
            $html .= '<small class="text-muted d-block"><i class="fas fa-clock me-1"></i>' . date('M d, Y g:i A', strtotime($note['created_at'])) . '</small>';
            if (!empty($note['created_by'])) {
                $html .= '<small class="text-muted"><i class="fas fa-user me-1"></i>' . htmlspecialchars($note['created_by']) . '</small>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="note-content">' . nl2br(htmlspecialchars($note['note'])) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
    } else {
        $html .= '<div class="mb-4">';
        $html .= '<h5 class="mb-3"><i class="fas fa-history text-info me-2"></i>Activity Timeline</h5>';
        $html .= '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No activity recorded yet.</div>';
        $html .= '</div>';
    }

    // ADD NOTE FORM - For supplier to add comments
    $html .= '<div class="mt-4 pt-4 border-top">';
    $html .= '<h5 class="mb-3"><i class="fas fa-comment-medical text-primary me-2"></i>Add Response</h5>';
    $html .= '<form id="addWarrantyNoteForm" data-claim-id="' . $claimId . '">';
    $html .= '<div class="row g-3">';

    $html .= '<div class="col-md-4">';
    $html .= '<label class="form-label">Action <span class="text-danger">*</span></label>';
    $html .= '<select class="form-select" name="action" required>';
    $html .= '<option value="">Choose action...</option>';
    $html .= '<option value="investigating">Investigating</option>';
    $html .= '<option value="accepted">Accepted</option>';
    $html .= '<option value="declined">Declined</option>';
    $html .= '<option value="replacement">Replacement Sent</option>';
    $html .= '<option value="refund">Refund Processed</option>';
    $html .= '<option value="note">General Note</option>';
    $html .= '</select>';
    $html .= '</div>';

    $html .= '<div class="col-md-8">';
    $html .= '<label class="form-label">Internal Reference #</label>';
    $html .= '<input type="text" class="form-control" name="internal_ref" placeholder="Your ticket/RMA number (optional)">';
    $html .= '</div>';

    $html .= '<div class="col-12">';
    $html .= '<label class="form-label">Response / Notes <span class="text-danger">*</span></label>';
    $html .= '<textarea class="form-control" name="note" rows="4" required placeholder="Add your response or notes here..."></textarea>';
    $html .= '</div>';

    $html .= '<div class="col-12">';
    $html .= '<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit Response</button>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</form>';
    $html .= '</div>';

    $html .= '</div>';

    // Add CSS for enhanced styling
    $html .= '<style>';
    $html .= '.image-wrapper { overflow: hidden; }';
    $html .= '.image-card { position: relative; border-radius: 8px; overflow: hidden; transition: transform 0.3s ease; }';
    $html .= '.image-card:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.3) !important; }';
    $html .= '.image-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }';
    $html .= '.image-card:hover .image-overlay { opacity: 1; }';
    $html .= '.timeline-container .card { transition: all 0.3s ease; }';
    $html .= '.timeline-container .card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateX(5px); }';
    $html .= '.issue-text { font-size: 1.05rem; line-height: 1.6; }';
    $html .= '.note-content { white-space: pre-wrap; line-height: 1.6; }';
    $html .= '</style>';

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
