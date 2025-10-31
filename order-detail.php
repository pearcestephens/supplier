<?php
/**
 * Order Detail Page - Full featured order management for suppliers
 * Shows complete order information with line items, tracking, and status updates
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// Get order ID from URL
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId) {
    header('Location: /supplier/orders.php');
    exit;
}

$db = db();

// Get order details
$stmt = $db->prepare("
    SELECT
        t.id,
        t.public_id,
        t.vend_number,
        t.supplier_id,
        t.outlet_to,
        t.state,
        t.created_at,
        t.expected_delivery_date,
        t.tracking_number,
        t.consignment_notes,
        t.reference,
        o.name as outlet_name,
        o.physical_address_1,
        o.physical_address_2,
        o.physical_suburb,
        o.physical_city,
        o.physical_postcode,
        o.physical_state,
        o.phone,
        o.email
    FROM vend_consignments t
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.id = ?
    AND t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.deleted_at IS NULL
");

$stmt->bind_param('is', $orderId, $supplierID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = 'Order not found or you do not have permission to view it.';
    header('Location: /supplier/orders.php');
    exit;
}

// Get line items
$stmt = $db->prepare("
    SELECT
        ti.id,
        ti.product_id,
        ti.quantity,
        ti.unit_cost,
        ti.received_qty,
        p.name as product_name,
        p.sku,
        p.active
    FROM vend_consignment_line_items ti
    LEFT JOIN vend_products p ON ti.product_id = p.id
    WHERE ti.transfer_id = ?
    AND ti.deleted_at IS NULL
    ORDER BY p.name ASC
");

$stmt->bind_param('i', $orderId);
$stmt->execute();
$lineItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate totals
$totalItems = count($lineItems);
$totalQuantity = array_sum(array_column($lineItems, 'quantity'));
$totalValue = array_sum(array_map(function($item) {
    return $item['quantity'] * $item['unit_cost'];
}, $lineItems));
$totalReceived = array_sum(array_column($lineItems, 'received_qty'));

// Calculate days until delivery
$daysUntilDelivery = null;
if ($order['expected_delivery_date']) {
    $daysUntilDelivery = floor((strtotime($order['expected_delivery_date']) - time()) / 86400);
}

require_once __DIR__ . '/components/html-header.php';
?>

<!-- Order Detail Page -->
<div class="container-fluid py-4">

    <!-- Back Button -->
    <div class="mb-3">
        <a href="/supplier/orders.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Orders
        </a>
    </div>

    <!-- Order Header Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="fas fa-file-invoice me-2"></i>
                        Order #<?php echo htmlspecialchars($order['vend_number'] ?? $order['public_id']); ?>
                    </h4>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo htmlspecialchars($order['state']); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">

                <!-- Order Information -->
                <div class="col-md-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>Order Information
                    </h5>
                    <div class="mb-2">
                        <strong>Order Date:</strong><br>
                        <span class="text-muted"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Expected Delivery:</strong><br>
                        <?php if ($order['expected_delivery_date']): ?>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($order['expected_delivery_date'])); ?></span>
                            <?php if ($daysUntilDelivery !== null): ?>
                                <?php if ($daysUntilDelivery < 0): ?>
                                    <br><span class="badge bg-danger"><i class="fas fa-exclamation-circle"></i> <?php echo abs($daysUntilDelivery); ?> days overdue</span>
                                <?php elseif ($daysUntilDelivery <= 3): ?>
                                    <br><span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> <?php echo $daysUntilDelivery; ?> days left</span>
                                <?php else: ?>
                                    <br><span class="badge bg-success"><?php echo $daysUntilDelivery; ?> days</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Not specified</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($order['reference']): ?>
                        <div class="mb-2">
                            <strong>Reference:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($order['reference']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <strong>Tracking Number:</strong><br>
                        <?php if ($order['tracking_number']): ?>
                            <code><?php echo htmlspecialchars($order['tracking_number']); ?></code>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyTracking()">
                                <i class="fas fa-copy"></i>
                            </button>
                        <?php else: ?>
                            <span class="text-muted">Not provided</span>
                            <?php if ($order['state'] === 'SENT' || $order['state'] === 'RECEIVING'): ?>
                                <button class="btn btn-sm btn-warning ms-2" onclick="updateTracking()">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Location -->
                <div class="col-md-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="fas fa-map-marker-alt text-success me-2"></i>Delivery Location
                    </h5>
                    <div class="mb-2">
                        <strong><?php echo htmlspecialchars($order['outlet_name']); ?></strong>
                    </div>
                    <?php if ($order['physical_address_1']): ?>
                        <div class="text-muted small">
                            <?php echo htmlspecialchars($order['physical_address_1']); ?><br>
                            <?php if ($order['physical_address_2']): ?>
                                <?php echo htmlspecialchars($order['physical_address_2']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($order['physical_suburb'] ?? ''); ?>
                            <?php echo htmlspecialchars($order['physical_city'] ?? ''); ?><br>
                            <?php echo htmlspecialchars($order['physical_postcode'] ?? ''); ?>
                            <?php echo htmlspecialchars($order['physical_state'] ?? ''); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['phone']): ?>
                        <div class="mt-2">
                            <i class="fas fa-phone text-muted me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>"><?php echo htmlspecialchars($order['phone']); ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($order['email']): ?>
                        <div class="mt-1">
                            <i class="fas fa-envelope text-muted me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>"><?php echo htmlspecialchars($order['email']); ?></a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Summary -->
                <div class="col-md-4">
                    <h5 class="border-bottom pb-2 mb-3">
                        <i class="fas fa-calculator text-info me-2"></i>Order Summary
                    </h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Items:</span>
                            <strong><?php echo number_format($totalItems); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Quantity:</span>
                            <strong><?php echo number_format($totalQuantity); ?> units</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Received:</span>
                            <strong class="text-success"><?php echo number_format($totalReceived); ?> units</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <strong>Total Value:</strong>
                            <strong class="text-primary fs-5">$<?php echo number_format($totalValue, 2); ?></strong>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="d-grid gap-2">
                        <?php if ($order['state'] === 'SENT' || $order['state'] === 'RECEIVING'): ?>
                            <button class="btn btn-warning" onclick="updateTracking()">
                                <i class="fas fa-shipping-fast me-2"></i>Update Tracking
                            </button>
                        <?php endif; ?>
                        <?php if ($order['state'] === 'OPEN'): ?>
                            <button class="btn btn-success" onclick="markAsShipped()">
                                <i class="fas fa-truck me-2"></i>Mark as Shipped
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Order
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportPDF()">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                    </div>
                </div>

            </div>

            <!-- Notes Section -->
            <?php if ($order['consignment_notes']): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-comment me-2"></i>Order Notes</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['consignment_notes'])); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Line Items Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Order Items
                        <span class="badge bg-secondary ms-2"><?php echo number_format($totalItems); ?></span>
                    </h5>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportItemsCSV()">
                        <i class="fas fa-download me-1"></i>Export Items
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">SKU</th>
                            <th width="40%">Product Name</th>
                            <th width="12%" class="text-center">Ordered</th>
                            <th width="12%" class="text-center">Received</th>
                            <th width="13%" class="text-end">Unit Cost</th>
                            <th width="13%" class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lineItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    No items in this order
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lineItems as $item): ?>
                                <?php
                                $lineTotal = $item['quantity'] * $item['unit_cost'];
                                $receivedPercent = $item['quantity'] > 0 ? ($item['received_qty'] / $item['quantity']) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <code class="small"><?php echo htmlspecialchars($item['sku']); ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <?php if (!$item['active']): ?>
                                            <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Inactive Product</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo number_format($item['quantity']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['received_qty'] > 0): ?>
                                            <span class="badge bg-success"><?php echo number_format($item['received_qty']); ?></span>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $receivedPercent; ?>%"></div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">$<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td class="text-end fw-bold">$<?php echo number_format($lineTotal, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2" class="text-end"><strong>Totals:</strong></td>
                            <td class="text-center"><strong><?php echo number_format($totalQuantity); ?></strong></td>
                            <td class="text-center"><strong class="text-success"><?php echo number_format($totalReceived); ?></strong></td>
                            <td></td>
                            <td class="text-end"><strong class="text-primary fs-5">$<?php echo number_format($totalValue, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
// Copy tracking number to clipboard
function copyTracking() {
    const tracking = '<?php echo addslashes($order['tracking_number'] ?? ''); ?>';
    navigator.clipboard.writeText(tracking).then(() => {
        showToast('Tracking number copied!', 'success');
    });
}

// Update tracking modal
function updateTracking() {
    Swal.fire({
        title: 'Update Tracking Number',
        html: `
            <input type="text" id="tracking_number" class="swal2-input" placeholder="Enter tracking number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>">
            <input type="text" id="carrier" class="swal2-input" placeholder="Carrier (optional)">
        `,
        showCancelButton: true,
        confirmButtonText: 'Update',
        preConfirm: () => {
            return {
                tracking: document.getElementById('tracking_number').value,
                carrier: document.getElementById('carrier').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value.tracking) {
            // Send update via API
            fetch('/supplier/api/update-tracking.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    tracking_number: result.value.tracking,
                    carrier: result.value.carrier
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Tracking updated!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Update failed', 'error');
                }
            });
        }
    });
}

// Mark as shipped
function markAsShipped() {
    Swal.fire({
        title: 'Mark as Shipped?',
        text: 'This will update the order status to SENT',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Ship It',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send API request
            fetch('/supplier/api/update-order-status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    status: 'SENT'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Order marked as shipped!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Update failed', 'error');
                }
            });
        }
    });
}

// Export items to CSV
function exportItemsCSV() {
    window.location.href = '/supplier/api/export-order-items.php?id=<?php echo $orderId; ?>';
}

// Export to PDF
function exportPDF() {
    window.open('/supplier/api/export-order-pdf.php?id=<?php echo $orderId; ?>', '_blank');
}

// Toast helper
function showToast(message, type = 'info') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}
</script>

<?php require_once __DIR__ . '/components/html-footer.php'; ?>
