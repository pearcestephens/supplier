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
    $pdo = pdo();

    // Get order header
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.public_id as po_number,
            c.state as status,
            c.total_cost as total_amount,
            c.consignment_notes as notes,
            c.tracking_number,
            c.created_at,
            c.updated_at,
            o.name as outlet_name,
            CONCAT(o.physical_address_1, ', ', o.physical_city, ', ', o.physical_postcode) as outlet_address
        FROM vend_consignments c
        LEFT JOIN vend_outlets o ON c.outlet_to = o.id
        WHERE c.id = ?
        AND c.supplier_id = ?
        AND c.deleted_at IS NULL
        AND c.transfer_category = 'PURCHASE_ORDER'
    ");

    $stmt->execute([$orderId, $supplierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT
            li.id,
            p.name as product_name,
            p.sku,
            li.order_qty as quantity,
            li.order_purchase_price as unit_price,
            (li.order_qty * li.order_purchase_price) as line_total
        FROM purchase_order_line_items li
        LEFT JOIN vend_products p ON li.product_id = p.id
        WHERE li.purchase_order_id = ?
        AND li.deleted_at IS NULL
        AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
        ORDER BY p.name
    ");

    $stmt->execute([$orderId]);

    $items = [];
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
