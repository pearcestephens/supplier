<?php
/**
 * Get Order Detail API Endpoint
 * Returns detailed order information for modal display
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
    
    // Get and validate order ID
    $orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$orderId) {
        throw new Exception('Invalid order ID');
    }
    
    // Get supplier ID from session
    $supplierId = getSupplierID();    if (!$supplierId) {
        throw new Exception('Supplier ID not found in session');
    }

    // Get order details
    $db = Database::getInstance();
    $mysqli = $db->getConnection();

    // Get order header
    $stmt = $mysqli->prepare("
        SELECT
            po.id,
            po.po_number,
            po.status,
            po.total_amount,
            po.notes,
            po.tracking_number,
            po.created_at,
            po.updated_at,
            o.outlet_name,
            o.outlet_address
        FROM purchase_orders po
        LEFT JOIN vend_outlets o ON po.outlet_id = o.outlet_id
        WHERE po.id = ?
        AND po.supplier_id = ?
    ");

    $stmt->bind_param('ii', $orderId, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Order not found');
    }

    $order = $result->fetch_assoc();

    // Get order items
    $stmt = $mysqli->prepare("
        SELECT
            poi.id,
            poi.product_name,
            poi.sku,
            poi.quantity,
            poi.unit_price,
            (poi.quantity * poi.unit_price) as line_total
        FROM purchase_order_items poi
        WHERE poi.purchase_order_id = ?
        ORDER BY poi.product_name
    ");

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();

    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }

    // Generate HTML for modal
    $statusBadge = renderStatusBadge($order['status'], 'order', true, true);

    $html = '<div class="order-detail-modal">';

    // Header section
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-6">';
    $html .= '<h5 class="mb-2">Order Information</h5>';
    $html .= '<p class="mb-1"><strong>PO Number:</strong> ' . htmlspecialchars($order['po_number']) . '</p>';
    $html .= '<p class="mb-1"><strong>Status:</strong> ' . $statusBadge . '</p>';
    $html .= '<p class="mb-1"><strong>Created:</strong> ' . date('M d, Y g:i A', strtotime($order['created_at'])) . '</p>';
    $html .= '<p class="mb-1"><strong>Updated:</strong> ' . date('M d, Y g:i A', strtotime($order['updated_at'])) . '</p>';
    $html .= '</div>';

    $html .= '<div class="col-md-6">';
    $html .= '<h5 class="mb-2">Delivery Information</h5>';
    $html .= '<p class="mb-1"><strong>Outlet:</strong> ' . htmlspecialchars($order['outlet_name']) . '</p>';
    $html .= '<p class="mb-1"><strong>Address:</strong> ' . htmlspecialchars($order['outlet_address'] ?? 'N/A') . '</p>';
    if ($order['tracking_number']) {
        $html .= '<p class="mb-1"><strong>Tracking:</strong> <span class="font-monospace" data-copyable>' . htmlspecialchars($order['tracking_number']) . '</span></p>';
    }
    $html .= '</div>';
    $html .= '</div>';

    // Items table
    $html .= '<h5 class="mb-3">Order Items</h5>';
    $html .= '<div class="table-responsive">';
    $html .= '<table class="table table-sm table-bordered">';
    $html .= '<thead class="table-light">';
    $html .= '<tr>';
    $html .= '<th>Product</th>';
    $html .= '<th>SKU</th>';
    $html .= '<th class="text-end">Qty</th>';
    $html .= '<th class="text-end">Unit Price</th>';
    $html .= '<th class="text-end">Total</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';

    foreach ($items as $item) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($item['product_name']) . '</td>';
        $html .= '<td><span class="font-monospace">' . htmlspecialchars($item['sku']) . '</span></td>';
        $html .= '<td class="text-end">' . number_format($item['quantity']) . '</td>';
        $html .= '<td class="text-end">$' . number_format((float)$item['unit_price'], 2) . '</td>';
        $html .= '<td class="text-end fw-bold">$' . number_format((float)$item['line_total'], 2) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '<tfoot class="table-light">';
    $html .= '<tr>';
    $html .= '<th colspan="4" class="text-end">Total:</th>';
    $html .= '<th class="text-end">$' . number_format((float)$order['total_amount'], 2) . '</th>';
    $html .= '</tr>';
    $html .= '</tfoot>';
    $html .= '</table>';
    $html .= '</div>';

    // Notes section
    if ($order['notes']) {
        $html .= '<div class="mt-4">';
        $html .= '<h5 class="mb-2">Notes</h5>';
        $html .= '<div class="alert alert-info">' . nl2br(htmlspecialchars($order['notes'])) . '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'order' => $order
    ]);

} catch (Exception $e) {
    error_log("Get Order Detail API Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load order details: ' . $e->getMessage()
    ]);
}
