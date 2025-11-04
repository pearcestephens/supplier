<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// ============================================================================
// DATABASE QUERIES - Orders Page Logic
// ============================================================================
$db = db();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(50, max(10, intval($_GET['per_page']))) : 25;
$offset = ($page - 1) * $perPage;

// Filters
$filterStatus = $_GET['status'] ?? 'active';
$filterOutlet = $_GET['outlet'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// ============================================================================
// QUERY 1: Get Total Count (for pagination)
// ============================================================================
$whereConditions = [
    "t.supplier_id = ?",
    "t.transfer_category = 'PURCHASE_ORDER'",
    "t.deleted_at IS NULL"
];
$params = [$supplierID];
$paramTypes = 's';

// Status filter
if ($filterStatus !== 'all') {
    switch ($filterStatus) {
        case 'open':
            $whereConditions[] = "t.state = 'OPEN'";
            break;
        case 'packing':
            $whereConditions[] = "t.state = 'PACKING'";
            break;
        case 'packed':
            $whereConditions[] = "t.state = 'PACKED'";
            break;
        case 'sent':
            $whereConditions[] = "t.state = 'SENT'";
            break;
        case 'receiving':
            $whereConditions[] = "t.state = 'RECEIVING'";
            break;
        case 'received':
            $whereConditions[] = "t.state = 'RECEIVED'";
            break;
        case 'cancelled':
            $whereConditions[] = "t.state = 'CANCELLED'";
            break;
        case 'active':
            $whereConditions[] = "t.state IN ('OPEN', 'PACKING', 'PACKED', 'SENT', 'RECEIVING')";
            break;
        case 'completed':
            $whereConditions[] = "t.state = 'RECEIVED'";
            break;
    }
}

// Outlet filter
if ($filterOutlet !== 'all') {
    $whereConditions[] = "t.outlet_to = ?";
    $params[] = $filterOutlet;
    $paramTypes .= 's';
}

// Search
if (!empty($searchTerm)) {
    $whereConditions[] = "(t.public_id LIKE ? OR t.reference LIKE ? OR t.vend_number LIKE ?)";
    $searchPattern = "%{$searchTerm}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $paramTypes .= 'sss';
}

$whereClause = implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) as total FROM vend_consignments t WHERE {$whereClause}";
$stmt = $db->prepare($countQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ============================================================================
// QUERY 2: Get Orders with Line Items
// ============================================================================
// Using transfer_id for JOIN with vend_consignment_line_items
$ordersQuery = "
    SELECT
        t.id,
        t.public_id,
        t.vend_number,
        t.supplier_id,
        t.outlet_to,
        t.state,
        t.created_at,
        t.updated_at,
        t.expected_delivery_date,
        t.tracking_number,
        COALESCE(SUM(ti.quantity * ti.unit_cost), 0) as total_value,
        o.name as outlet_name,
        o.id as store_code,
        COUNT(DISTINCT ti.id) as item_count,
        COALESCE(SUM(ti.quantity), 0) as total_quantity
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id AND ti.deleted_at IS NULL
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE {$whereClause}
    GROUP BY t.id, t.public_id, t.vend_number, t.supplier_id, t.outlet_to, t.state, t.created_at, t.updated_at, t.expected_delivery_date, t.tracking_number, o.name, o.id
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $db->prepare($ordersQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = ceil($totalOrders / $perPage);

// Check if any orders have expected delivery dates
$hasExpectedDelivery = false;
foreach ($orders as $order) {
    if (!empty($order['expected_delivery_date']) && $order['expected_delivery_date'] !== '0000-00-00' && $order['expected_delivery_date'] !== '0000-00-00 00:00:00') {
        $hasExpectedDelivery = true;
        break;
    }
}

// ============================================================================
// QUERY 3: Get Available Outlets (for filter dropdown)
// ============================================================================
$outletsQuery = "
    SELECT DISTINCT o.id, o.name, o.id as store_code
    FROM vend_consignments t
    JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
    ORDER BY o.name
";
$stmt = $db->prepare($outletsQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$availableOutlets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================================
// QUERY 4: Widget Stats - Active Orders
// ============================================================================
$activeStatsQuery = "
    SELECT
        COUNT(*) as active_count,
        SUM(CASE WHEN state = 'OPEN' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN state = 'SENT' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN state = 'RECEIVING' THEN 1 ELSE 0 END) as receiving_count
    FROM vend_consignments t
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.state IN ('OPEN', 'SENT', 'RECEIVING')
";
$stmt = $db->prepare($activeStatsQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$activeStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================================
// QUERY 5: Widget Stats - Monthly Performance
// ============================================================================
$monthlyStatsQuery = "
    SELECT
        COUNT(*) as orders_this_month,
        SUM(ti.quantity_sent) as units_this_month,
        SUM(ti.quantity_sent * ti.unit_cost) as value_this_month
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND MONTH(t.created_at) = MONTH(NOW())
      AND YEAR(t.created_at) = YEAR(NOW())
";
$stmt = $db->prepare($monthlyStatsQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$monthlyStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================================
// QUERY 6: Widget Stats - Top Outlets (Last 30 Days)
// ============================================================================
$topOutletsQuery = "
    SELECT
        o.name as outlet_name,
        o.id as store_code,
        COUNT(DISTINCT t.id) as order_count,
        SUM(ti.quantity_sent) as total_units,
        SUM(ti.quantity_sent * ti.unit_cost) as total_value
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY t.outlet_to
    ORDER BY total_value DESC
    LIMIT 5
";
$stmt = $db->prepare($topOutletsQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$topOutlets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================================
// QUERY 7: Widget Stats - Recent Activity
// ============================================================================
$recentActivityQuery = "
    SELECT
        t.id,
        t.public_id,
        t.state,
        t.created_at,
        o.name as outlet_name,
        COUNT(ti.id) as item_count
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 5
";
$stmt = $db->prepare($recentActivityQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================================
// QUERY 8: Widget Stats - Pending Deliveries
// ============================================================================
$pendingDeliveriesQuery = "
    SELECT
        COUNT(*) as pending_count,
        SUM(CASE WHEN expected_delivery_date < NOW() THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN expected_delivery_date >= NOW() AND expected_delivery_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as due_soon_count
    FROM vend_consignments t
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.state IN ('OPEN', 'SENT', 'RECEIVING')
      AND t.expected_delivery_date IS NOT NULL
";
$stmt = $db->prepare($pendingDeliveriesQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$pendingDeliveries = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Helper function for status badges
function getStatusBadgeClass($status) {
    $map = [
        'OPEN' => 'primary',
        'SENT' => 'info',
        'RECEIVING' => 'warning',
        'RECEIVED' => 'success',
        'CLOSED' => 'secondary',
        'CANCELLED' => 'danger'
    ];
    return $map[$status] ?? 'secondary';
}

$activeTab = 'orders';
$pageTitle = 'Purchase Orders';
$pageIcon = 'fa-solid fa-shopping-cart';
$pageDescription = 'Orders I need to fulfill and ship to The Vape Shed stores';
$breadcrumb = [
    ['text' => 'Purchase Orders', 'href' => '/supplier/orders.php']
];
$actionButtons = '
    <button class="btn btn-outline-secondary me-2" onclick="exportOrdersCSV()">
        <i class="fas fa-file-csv"></i> Export Report
    </button>
    <button class="btn btn-outline-secondary me-2" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>
    <button class="btn btn-success" onclick="bulkUpdateTracking()">
        <i class="fas fa-plus"></i> Create
    </button>
';
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

<!-- Modern Purchase Orders Page -->
<div class="purchase-orders-page">

    <!-- Filters and Search Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="orders">

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="PO#, Reference..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Orders</option>
                        <option value="open" <?php echo $filterStatus === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="packing" <?php echo $filterStatus === 'packing' ? 'selected' : ''; ?>>Packing</option>
                        <option value="packed" <?php echo $filterStatus === 'packed' ? 'selected' : ''; ?>>Packed</option>
                        <option value="sent" <?php echo $filterStatus === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="receiving" <?php echo $filterStatus === 'receiving' ? 'selected' : ''; ?>>Receiving</option>
                        <option value="received" <?php echo $filterStatus === 'received' ? 'selected' : ''; ?>>Received</option>
                        <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Outlet</label>
                    <select name="outlet" class="form-select">
                        <option value="all">All Outlets</option>
                        <?php foreach ($availableOutlets as $outlet): ?>
                            <option value="<?php echo htmlspecialchars($outlet['id']); ?>" <?php echo $filterOutlet === $outlet['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($outlet['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Per Page</label>
                    <select name="per_page" class="form-select">
                        <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Full-Width Orders Table -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <input type="checkbox" id="selectAllOrders" class="form-check-input me-2" title="Select All">
                    Orders List
                </h5>
                <div>
                    <!-- Bulk Actions Toolbar -->
                    <div class="btn-group me-2" role="group">
                        <button class="btn btn-sm btn-outline-primary bulk-action-btn" id="bulkDownloadBtn" onclick="bulkDownloadZip()" title="Download CSV (single) or ZIP of CSVs (multiple)" disabled>
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button class="btn btn-sm btn-outline-success bulk-action-btn" id="bulkAddTrackingBtn" onclick="bulkAddTracking()" title="Add Tracking Numbers" disabled>
                            <i class="fas fa-shipping-fast"></i> Add Tracking
                        </button>
                        <button class="btn btn-sm btn-outline-warning bulk-action-btn" id="bulkMarkShippedBtn" onclick="bulkMarkShipped()" title="Mark as Shipped" disabled>
                            <i class="fas fa-truck"></i> Mark Shipped
                        </button>
                    </div>
                    <small class="text-muted">
                        Showing <?php echo number_format((float)min($offset + 1, $totalOrders)); ?>
                        to <?php echo number_format((float)min($offset + $perPage, $totalOrders)); ?>
                        of <?php echo number_format((float)$totalOrders); ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 orders-table">
                    <thead class="table-dark">
                        <tr>
                            <th width="40px" class="text-center">
                                <input type="checkbox" class="form-check-input" id="selectAllOrdersHeader" onclick="toggleAllOrders(this)">
                            </th>
                            <th>#</th>
                            <th>Store Location</th>
                            <th>Date Ordered</th>
                            <?php if ($hasExpectedDelivery): ?>
                                <th>Expected Delivery</th>
                            <?php endif; ?>
                            <th class="text-center">Items</th>
                            <th class="text-end">Value</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Tracking</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <p class="mb-0">No purchase orders found matching your criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="clickable-row" data-order-id="<?php echo $order['id']; ?>" onclick="if(!event.target.closest('.no-click')) window.location.href='/supplier/order-detail.php?id=<?php echo $order['id']; ?>'" style="cursor: pointer;">
                                    <td class="text-center no-click" onclick="event.stopPropagation();">
                                        <input type="checkbox" class="form-check-input order-checkbox" value="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['vend_number'] ?? '-'); ?>">
                                    </td>                                    <td class="fw-bold">
                                        <?php
                                        // Show vend_number if exists, otherwise show blank (not hash ID)
                                        echo !empty($order['vend_number']) ? htmlspecialchars($order['vend_number']) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($order['outlet_name']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                    </td>
                                    <?php if ($hasExpectedDelivery): ?>
                                        <td>
                                            <?php if ($order['expected_delivery_date']): ?>
                                                <div><?php echo date('M d, Y', strtotime($order['expected_delivery_date'])); ?></div>
                                                <?php
                                                $daysUntil = floor((strtotime($order['expected_delivery_date']) - time()) / 86400);
                                                if ($daysUntil < 0): ?>
                                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> <?php echo abs($daysUntil); ?> days overdue</small>
                                                <?php elseif ($daysUntil <= 3): ?>
                                                    <small class="text-warning"><i class="fas fa-clock"></i> <?php echo $daysUntil; ?> days left</small>
                                                <?php else: ?>
                                                    <small class="text-muted"><?php echo $daysUntil; ?> days</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small class="text-muted">Not specified</small>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo number_format((float)($order['item_count'] ?? 0)); ?></span>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php
                                        $value = (float)($order['total_value'] ?? 0);
                                        if ($value > 0) {
                                            echo '$' . number_format($value, 2);
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['state']); ?>">
                                            <?php echo htmlspecialchars($order['state']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" onclick="event.stopPropagation();">
                                        <?php
                                        // Check if status allows modifications
                                        $lockedStatuses = ['RECEIVED', 'RECEIVING', 'CANCELLED', 'CLOSED'];
                                        $isStatusLocked = in_array($order['state'], $lockedStatuses);

                                        if (!empty($order['tracking_number'])):
                                        ?>
                                            <span class="badge bg-success" title="<?php echo htmlspecialchars($order['tracking_number']); ?>">
                                                <i class="fas fa-check-circle"></i> Has Tracking
                                            </span>
                                        <?php elseif ($isStatusLocked): ?>
                                            <span class="badge bg-secondary" title="Cannot attach tracking - order is <?php echo $order['state']; ?>">
                                                <i class="fas fa-lock"></i> Locked
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-dark" onclick="addTrackingModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['vend_number'] ?? ''); ?>')" title="Add Tracking">
                                                <i class="fas fa-plus-circle"></i> Attach
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-click" onclick="event.stopPropagation();">
                                        <?php
                                        // Check 24-hour lock: if tracking exists or status is SENT, check if updated within 24h
                                        $canEdit = false;
                                        $lockReason = '';

                                        if ($order['state'] === 'OPEN' || $order['state'] === 'SENT') {
                                            // Check if it's been more than 24 hours since last update
                                            $hoursSinceUpdate = (time() - strtotime($order['updated_at'])) / 3600;

                                            if ($order['state'] === 'SENT' || !empty($order['tracking_number'])) {
                                                if ($hoursSinceUpdate < 24) {
                                                    $canEdit = true;
                                                } else {
                                                    $lockReason = 'Locked after 24 hours';
                                                }
                                            } else {
                                                // OPEN status with no tracking - always editable
                                                $canEdit = true;
                                            }
                                        }
                                        ?>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-sm btn-outline-primary" onclick="quickViewOrder(<?php echo $order['id']; ?>)" title="Quick Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="/supplier/order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary" title="Full Details">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <?php if ($canEdit): ?>
                                                <button class="btn btn-sm btn-warning" onclick="editOrder(<?php echo $order['id']; ?>)" title="Edit Order">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php elseif (!empty($lockReason)): ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="<?php echo $lockReason; ?>">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-light">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <!-- Previous -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?tab=orders&page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>&status=<?php echo $filterStatus; ?>&outlet=<?php echo $filterOutlet; ?>&search=<?php echo urlencode($searchTerm); ?>">
                                Previous
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?tab=orders&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>&status=<?php echo $filterStatus; ?>&outlet=<?php echo $filterOutlet; ?>&search=<?php echo urlencode($searchTerm); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next -->
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?tab=orders&page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>&status=<?php echo $filterStatus; ?>&outlet=<?php echo $filterOutlet; ?>&search=<?php echo urlencode($searchTerm); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
/* Additional CSS for Orders Page */
.orders-table thead th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.orders-table tbody tr {
    transition: background-color 0.2s ease;
}

.orders-table tbody tr:hover {
    background-color: rgba(59, 130, 246, 0.05);
}

/* Clickable row styling */
.clickable-row {
    cursor: pointer;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.clickable-row:hover {
    background-color: rgba(59, 130, 246, 0.1) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
}

.card-header h6 {
    font-weight: 600;
}
</style>

    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Purchase Orders JavaScript -->
<script src="/supplier/assets/js/orders.js?v=<?php echo time(); ?>"></script>

</body>
</html>
