<?php
/**
 * Order Detail Page - Full featured order management for suppliers
 * Shows complete order information with line items, tracking, and status updates
 */
declare(strict_types=1);

// Suppress PHP errors/warnings display on this page - log them instead
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
        t.supplier_reference,
        o.name as outlet_name,
        o.physical_address_1,
        o.physical_address_2,
        o.physical_suburb,
        o.physical_city,
        o.physical_postcode,
        o.physical_state,
        o.physical_phone_number as phone,
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
        ti.quantity_sent,
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

// Get shipment and parcel details
$parcels = [];
$stmt = $db->prepare("
    SELECT
        s.id as shipment_id,
        s.created_at as shipment_created,
        p.id as parcel_id,
        p.parcel_number,
        p.box_number,
        p.tracking_number,
        p.courier,
        p.weight_grams,
        p.weight_kg,
        p.length_mm,
        p.width_mm,
        p.height_mm,
        p.status as parcel_status,
        p.label_url,
        p.created_at as parcel_created
    FROM consignment_shipments s
    LEFT JOIN consignment_parcels p ON s.id = p.shipment_id
    WHERE s.consignment_id = ?
    ORDER BY p.box_number ASC, p.parcel_number ASC
");

$stmt->bind_param('i', $orderId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['parcel_id']) {
        // Get items in this parcel
        $itemStmt = $db->prepare("
            SELECT
                pi.id,
                pi.item_id,
                pi.qty,
                pi.qty_received,
                li.product_id,
                p.name as product_name,
                p.sku
            FROM consignment_parcel_items pi
            LEFT JOIN vend_consignment_line_items li ON pi.item_id = li.id
            LEFT JOIN vend_products p ON li.product_id = p.id
            WHERE pi.parcel_id = ?
        ");
        $itemStmt->bind_param('i', $row['parcel_id']);
        $itemStmt->execute();
        $row['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();

        $parcels[] = $row;
    }
}
$stmt->close();

// Calculate totals
$totalItems = count($lineItems);
$totalQuantity = array_sum(array_column($lineItems, 'quantity'));
$totalValue = array_sum(array_map(function($item) {
    return (float)$item['quantity'] * (float)$item['unit_cost'];
}, $lineItems));
$totalReceived = array_sum(array_column($lineItems, 'quantity_sent'));

// Calculate days until delivery
$daysUntilDelivery = null;
if ($order['expected_delivery_date']) {
    $daysUntilDelivery = floor((strtotime($order['expected_delivery_date']) - time()) / 86400);
}

// Set page title for header component
$pageTitle = 'Order Details';
$activeTab = 'orders';
$breadcrumb = [
    ['text' => 'Orders', 'href' => '/supplier/orders.php'],
    ['text' => 'PO #' . htmlspecialchars($order['public_id']), 'href' => '']
];
?>
<?php include __DIR__ . '/components/html-head.php'; ?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar-new.php'; ?>

<!-- Page Header (Fixed Top Bar) -->
<?php include __DIR__ . '/components/page-header.php'; ?>

<!-- Main Content Area -->
<div class="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

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
        <div class="card-header" style="background-color: #212529 !important;">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0 text-white">
                        <i class="fas fa-file-invoice me-2"></i>
                        Purchase Order: <?php echo htmlspecialchars($order['public_id']); ?>
                    </h4>
                </div>
                <div class="col-md-6 text-end">
                    <?php
                    $badgeClass = 'bg-light text-dark';
                    if ($order['state'] === 'CANCELLED') {
                        $badgeClass = 'bg-danger text-white';
                    }
                    ?>
                    <span class="badge <?php echo $badgeClass; ?> fs-6">
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
                    <?php if (!empty($order['supplier_reference'])): ?>
                        <div class="mb-2">
                            <strong>Reference:</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($order['supplier_reference']); ?></span>
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
                            <?php if ($order['state'] === 'OPEN' || $order['state'] === 'SENT' || $order['state'] === 'RECEIVING'): ?>
                                <button class="btn btn-sm btn-success ms-2" onclick="addTrackingWithOptions(<?php echo $orderId; ?>)">
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
                        <strong>The Vape Shed</strong>
                    </div>
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
                        <?php if ($order['state'] === 'PACKING'): ?>
                            <button class="btn btn-success" onclick="markAsPacked()">
                                <i class="fas fa-check-circle me-2"></i>Mark as Packed
                            </button>
                        <?php endif; ?>
                        <?php if ($order['state'] === 'OPEN' || $order['state'] === 'PACKING'): ?>
                            <button class="btn btn-primary" onclick="addTrackingWithOptions(<?php echo $orderId; ?>)">
                                <i class="fas fa-shipping-fast me-2"></i>Add Tracking Details
                            </button>
                        <?php endif; ?>
                        <?php if ($order['state'] === 'SENT' || $order['state'] === 'RECEIVING'): ?>
                            <button class="btn btn-outline-info" onclick="viewTrackingDetails(<?php echo $orderId; ?>)">
                                <i class="fas fa-box me-2"></i>View Boxes/Tracking
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" onclick="exportItemsCSV()">
                            <i class="fas fa-file-csv me-2"></i>Download as CSV
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportPDF()">
                            <i class="fas fa-print me-2"></i>Print Document
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
                            <th width="35%">Product Name</th>
                            <th width="10%" class="text-center">Ordered</th>
                            <th width="15%" class="text-center">
                                Sent
                                <div class="form-check form-check-sm mt-1">
                                    <input class="form-check-input" type="checkbox" id="enableEdit" onchange="toggleEditMode()">
                                    <label class="form-check-label small text-muted" for="enableEdit">
                                        <i class="fas fa-edit"></i> Edit
                                    </label>
                                </div>
                            </th>
                            <th width="12%" class="text-center">Received</th>
                            <th width="13%" class="text-end">Unit Cost</th>
                            <th width="15%" class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lineItems)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    No items in this order
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lineItems as $item): ?>
                                <?php
                                $lineTotal = (float)$item['quantity'] * (float)$item['unit_cost'];
                                $receivedPercent = $item['quantity'] > 0 ? ($item['quantity_sent'] / $item['quantity']) * 100 : 0;
                                ?>
                                <tr data-item-id="<?php echo $item['id']; ?>">
                                    <td class="text-center">
                                        <input type="checkbox" class="item-checkbox" disabled onchange="toggleItemEdit(this)">
                                    </td>
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
                                    <td class="text-center qty-sent-cell">
                                        <span class="qty-sent-display"><?php echo number_format($item['quantity_sent']); ?></span>
                                        <input type="number"
                                               class="form-control form-control-sm qty-sent-input d-none"
                                               value="<?php echo $item['quantity_sent']; ?>"
                                               min="0"
                                               max="<?php echo $item['quantity']; ?>"
                                               style="width: 80px; margin: 0 auto;">
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted">-</span>
                                    </td>
                                    <td class="text-end">$<?php echo number_format((float)$item['unit_cost'], 2); ?></td>
                                    <td class="text-end fw-bold">$<?php echo number_format((float)$lineTotal, 2); ?></td>
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
                            <td class="text-end"><strong class="text-primary fs-5">$<?php echo number_format((float)$totalValue, 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Shipment & Tracking Details -->
    <?php if (!empty($parcels)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header" style="background-color: #212529;">
            <h5 class="mb-0 text-white">
                <i class="fas fa-shipping-fast me-2"></i>Shipment Details & Tracking
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($parcels as $parcel): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-primary h-100">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-box me-2"></i>
                                    Box #<?php echo htmlspecialchars($parcel['box_number']); ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <?php
                                    $statusIcons = [
                                        'pending' => 'clock',
                                        'labelled' => 'barcode',
                                        'in_transit' => 'truck',
                                        'delivered' => 'check-circle',
                                        'received' => 'warehouse'
                                    ];
                                    $icon = $statusIcons[$parcel['parcel_status']] ?? 'question-circle';
                                    echo '<i class="fas fa-' . $icon . ' me-1"></i>' . ucfirst($parcel['parcel_status']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Tracking Information -->
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-hashtag me-1"></i>Tracking Number
                                </h6>
                                <div class="d-flex align-items-center">
                                    <code class="fs-6 me-2"><?php echo htmlspecialchars($parcel['tracking_number']); ?></code>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($parcel['tracking_number'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Courier Information -->
                            <?php if ($parcel['courier']): ?>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-truck me-1"></i>Courier
                                </h6>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($parcel['courier']); ?></span>
                            </div>
                            <?php endif; ?>

                            <!-- Weight & Dimensions -->
                            <?php if ($parcel['weight_kg'] || $parcel['length_mm']): ?>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-weight me-1"></i>Dimensions
                                </h6>
                                <div class="d-flex gap-3 flex-wrap">
                                    <?php if ($parcel['weight_kg']): ?>
                                    <span class="badge bg-info">
                                        Weight: <?php echo number_format($parcel['weight_kg'], 2); ?> kg
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($parcel['length_mm'] && $parcel['width_mm'] && $parcel['height_mm']): ?>
                                    <span class="badge bg-info">
                                        <?php echo $parcel['length_mm']; ?> × <?php echo $parcel['width_mm']; ?> × <?php echo $parcel['height_mm']; ?> mm
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Items in this Box -->
                            <?php if (!empty($parcel['items'])): ?>
                            <div class="mb-0">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-list me-1"></i>Contents (<?php echo count($parcel['items']); ?> items)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>SKU</th>
                                                <th>Product</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-center">Received</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parcel['items'] as $item): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($item['sku']); ?></code></td>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?php echo number_format($item['qty']); ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($item['qty_received'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo number_format($item['qty_received']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>No items assigned to this box yet.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="fas fa-calendar-alt me-1"></i>
                                Created: <?php echo date('M j, Y g:i A', strtotime($parcel['parcel_created'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Notes History -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-comments me-2"></i>Order Notes & History
            </h5>
        </div>
        <div class="card-body">
            <div id="notesHistory" class="mb-3">
                <!-- Notes will be loaded here via AJAX -->
                <p class="text-muted">Loading notes...</p>
            </div>
            <div class="border-top pt-3">
                <h6 class="mb-3">Add New Note</h6>
                <div class="input-group">
                    <textarea id="newNoteText" class="form-control" rows="3" placeholder="Enter your note about this order..."></textarea>
                </div>
                <button class="btn btn-primary mt-2" onclick="addOrderNote()">
                    <i class="fas fa-plus me-2"></i>Add Note
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// Load order notes on page load
document.addEventListener('DOMContentLoaded', function() {
    loadOrderNotes();
});

// Load order notes
function loadOrderNotes() {
    fetch('/supplier/api/get-order-history.php?id=<?php echo $orderId; ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                displayNotes(data.data);
            } else {
                document.getElementById('notesHistory').innerHTML = '<p class="text-muted">No notes yet.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading notes:', error);
            document.getElementById('notesHistory').innerHTML = '<p class="text-danger">Failed to load notes.</p>';
        });
}

// Display notes
function displayNotes(notes) {
    if (!notes || notes.length === 0) {
        document.getElementById('notesHistory').innerHTML = '<p class="text-muted">No notes yet.</p>';
        return;
    }

    let html = '<div class="list-group">';
    notes.forEach(note => {
        // Check if it's a system-generated note
        const isSystem = note.action && note.action !== 'Note added';
        const noteClass = isSystem ? 'list-group-item-light border-start border-info border-3' : '';
        const iconClass = isSystem ? 'fas fa-robot text-info' : 'fas fa-user text-primary';
        const userName = isSystem ? 'System' : (note.user_name || 'Supplier');

        html += `
            <div class="list-group-item ${noteClass}">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <h6 class="mb-1">
                        <i class="${iconClass} me-2"></i>${userName}
                        ${isSystem ? '<span class="badge bg-info ms-2">Automated</span>' : ''}
                    </h6>
                    <small class="text-muted">${note.created_at}</small>
                </div>
                ${isSystem ? `<p class="mb-1 text-muted small"><strong>${note.action}</strong></p>` : ''}
                <p class="mb-0">${note.note || ''}</p>
            </div>
        `;
    });
    html += '</div>';
    document.getElementById('notesHistory').innerHTML = html;
}

// Add order note
function addOrderNote() {
    const noteText = document.getElementById('newNoteText').value.trim();
    if (!noteText) {
        showToast('Please enter a note', 'warning');
        return;
    }

    fetch('/supplier/api/add-order-note.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            order_id: <?php echo $orderId; ?>,
            note: noteText
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Note added successfully', 'success');
            document.getElementById('newNoteText').value = '';
            loadOrderNotes();
        } else {
            showToast(data.message || 'Failed to add note', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to add note', 'error');
    });
}

// Toggle edit mode for quantities
function toggleEditMode() {
    const checkbox = document.getElementById('enableEdit');
    const isEditMode = checkbox.checked;

    // Toggle all row checkboxes
    const rowCheckboxes = document.querySelectorAll('.item-checkbox');
    rowCheckboxes.forEach(cb => {
        cb.disabled = !isEditMode;
        if (!isEditMode) {
            cb.checked = false;
        }
    });

    // Show/hide quantity inputs based on row checkbox state
    if (isEditMode) {
        showToast('Edit mode enabled. Check items to edit quantities.', 'info');
    } else {
        // Hide all inputs when disabling edit mode
        document.querySelectorAll('.qty-sent-display').forEach(span => span.classList.remove('d-none'));
        document.querySelectorAll('.qty-sent-input').forEach(input => input.classList.add('d-none'));
        showToast('Edit mode disabled.', 'info');
    }
}

// Toggle individual item edit
function toggleItemEdit(checkbox) {
    const row = checkbox.closest('tr');
    const display = row.querySelector('.qty-sent-display');
    const input = row.querySelector('.qty-sent-input');

    if (checkbox.checked) {
        display.classList.add('d-none');
        input.classList.remove('d-none');
        input.focus();
    } else {
        display.classList.remove('d-none');
        input.classList.add('d-none');
    }
}

// Save edited quantities
function saveEditedQuantities() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');

    if (checkedBoxes.length === 0) {
        showToast('No items selected to save', 'warning');
        return;
    }

    const updates = [];
    checkedBoxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const itemId = row.dataset.itemId;
        const input = row.querySelector('.qty-sent-input');
        const newQty = parseInt(input.value) || 0;

        updates.push({
            item_id: itemId,
            quantity_sent: newQty
        });
    });

    // Confirm before saving
    Swal.fire({
        title: 'Save Changes?',
        text: `Update quantities for ${updates.length} item(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Save',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Saving...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send API request
            fetch('/supplier/api/update-item-quantities.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    updates: updates
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Quantities updated successfully',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Update failed'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save changes'
                });
            });
        }
    });
}

// Copy tracking number to clipboard
function copyTracking() {
    const tracking = '<?php echo addslashes($order['tracking_number'] ?? ''); ?>';
    navigator.clipboard.writeText(tracking).then(() => {
        showToast('Tracking number copied!', 'success');
    });
}

// Old tracking function - DEPRECATED - Use addTrackingWithOptions() instead
// function updateTracking() {
//     Swal.fire({
//         title: 'Update Tracking Number',
//         html: `
//             <input type="text" id="tracking_number" class="swal2-input" placeholder="Enter tracking number" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>">
//             <input type="text" id="carrier" class="swal2-input" placeholder="Carrier (optional)">
//         `,
//         showCancelButton: true,
//         confirmButtonText: 'Update',
//         preConfirm: () => {
//             return {
//                 tracking: document.getElementById('tracking_number').value,
//                 carrier: document.getElementById('carrier').value
//             };
//         }
//     }).then((result) => {
//         if (result.isConfirmed && result.value.tracking) {
//             // Send update via API
//             fetch('/supplier/api/update-tracking.php', {
//                 method: 'POST',
//                 headers: {'Content-Type': 'application/json'},
//                 body: JSON.stringify({
//                     order_id: <?php echo $orderId; ?>,
//                     tracking_number: result.value.tracking,
//                     carrier: result.value.carrier
//                 })
//             })
//             .then(r => r.json())
//             .then(data => {
//                 if (data.success) {
//                     showToast('Tracking updated!', 'success');
//                     setTimeout(() => location.reload(), 1000);
//                 } else {
//                     showToast(data.message || 'Update failed', 'error');
//                 }
//             });
//         }
//     });
// }

// View tracking details and boxes
function viewTrackingDetails(orderId) {
    // Scroll to tracking section if it exists
    const trackingSection = document.querySelector('.card-header:has(.fa-shipping-fast)');
    if (trackingSection) {
        trackingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Highlight the section briefly
        const card = trackingSection.closest('.card');
        card.classList.add('border-success');
        setTimeout(() => {
            card.classList.remove('border-success');
        }, 2000);
    } else {
        Swal.fire({
            title: 'No Tracking Yet',
            text: 'No tracking information has been added for this order yet.',
            icon: 'info',
            confirmButtonText: 'Add Tracking Now',
            showCancelButton: true,
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                addTrackingWithOptions(orderId);
            }
        });
    }
}

// Copy to clipboard helper
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Tracking number copied to clipboard',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }).catch(err => {
            console.error('Failed to copy:', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

// Fallback copy method for older browsers
function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        document.execCommand('copy');
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Tracking number copied to clipboard',
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Copy Failed',
            text: 'Could not copy to clipboard. Please copy manually.',
            confirmButtonText: 'OK'
        });
    }

    document.body.removeChild(textArea);
}

// Mark as packed (PACKING -> PACKED)
function markAsPacked() {
    Swal.fire({
        title: 'Mark as Packed?',
        text: 'This will update the order status to PACKED',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Mark Packed',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('/supplier/api/update-order-status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    status: 'PACKED'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Order marked as packed!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Update failed'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update order status. Please try again.'
                });
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
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Updating...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send API request
            fetch('/supplier/api/update-order-status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    status: 'SENT'
                })
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('Network response was not ok');
                }
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Order marked as shipped!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Update failed'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update order status. Please try again.'
                });
            });
        }
    });
}

// Export items to CSV
function exportItemsCSV() {
    showToast('Preparing CSV export...', 'info');
    try {
        window.location.href = '/supplier/api/export-order-items.php?id=<?php echo $orderId; ?>';
    } catch (error) {
        showToast('Export failed. Please try again.', 'error');
        console.error('CSV export error:', error);
    }
}

// Export to PDF
function exportPDF() {
    showToast('Opening PDF document...', 'info');
    try {
        const pdfWindow = window.open('/supplier/api/export-order-pdf.php?id=<?php echo $orderId; ?>', '_blank');
        if (!pdfWindow) {
            showToast('Please allow pop-ups to view PDF', 'warning');
        }
    } catch (error) {
        showToast('Export failed. Please try again.', 'error');
        console.error('PDF export error:', error);
    }
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

<!-- Add Tracking Modal Script -->
<script src="/supplier/assets/js/add-tracking-modal.js"></script>

<?php require_once __DIR__ . '/components/html-footer.php'; ?>
