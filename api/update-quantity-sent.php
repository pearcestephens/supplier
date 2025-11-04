<?php
/**
 * AJAX endpoint to update quantity_sent for a consignment line item
 *
 * Called by order-detail.php when editing quantities in edit mode
 * Updates happen live as user types (with debounce)
 */

require_once __DIR__ . '/../bootstrap.php';

// Force JSON response
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['supplier_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$item_id = $input['item_id'] ?? null;
$quantity_sent = $input['quantity_sent'] ?? null;

// Validate inputs
if (!$item_id || !is_numeric($item_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
    exit;
}

if (!is_numeric($quantity_sent) || $quantity_sent < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    exit;
}

$supplier_id = $_SESSION['supplier_id'];
$item_id = intval($item_id);
$quantity_sent = intval($quantity_sent);

try {
    $db = db();

    // Verify this item belongs to the logged-in supplier AND get max quantity
    $check_stmt = $db->prepare("
        SELECT
            vcli.id,
            vcli.quantity as max_quantity,
            vc.supplier_id
        FROM vend_consignment_line_items vcli
        JOIN vend_consignments vc ON vcli.transfer_id = vc.id
        WHERE vcli.id = ?
        AND vc.supplier_id = ?
        AND (vc.deleted_at IS NULL OR vc.deleted_at = '0000-00-00 00:00:00')
        LIMIT 1
    ");

    $check_stmt->bind_param('ii', $item_id, $supplier_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $item = $result->fetch_assoc();

    if (!$item) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Item not found or access denied']);
        exit;
    }

    // Validate quantity doesn't exceed ordered quantity
    if ($quantity_sent > $item['max_quantity']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot send more than ordered',
            'max_quantity' => $item['max_quantity']
        ]);
        exit;
    }

    // Update quantity_sent
    $update_stmt = $db->prepare("
        UPDATE vend_consignment_line_items
        SET quantity_sent = ?
        WHERE id = ?
    ");

    $update_stmt->bind_param('ii', $quantity_sent, $item_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'item_id' => $item_id,
            'quantity_sent' => $quantity_sent,
            'message' => 'Quantity updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $db->error
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
