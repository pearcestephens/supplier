<?php
/**
 * Purchase Orders - Modern Full-Width Table with Real Data
 *
 * Features:
 * - Full-width responsive table
 * - Pagination (up to 50 per page)
 * - CSV export in one click
 * - Real data from vend_consignments
 * - Multiple widgets with smart arrangement
 *
 * @package CIS\Supplier\Tabs
 * @version 2.0.0
 */

declare(strict_types=1);

// Security: Prevent direct access
if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

// CRITICAL: Verify required globals are available
if (!isset($db) || !($db instanceof mysqli)) {
    die('<div class="alert alert-danger">Database connection not available. Please contact support.</div>');
}

if (!class_exists('Auth')) {
    die('<div class="alert alert-danger">Authentication system not loaded. Please contact support.</div>');
}

// Get authenticated supplier ID
$supplierID = Auth::getSupplierId();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(50, max(10, intval($_GET['per_page']))) : 25;
$offset = ($page - 1) * $perPage;

// Filters
$filterStatus = $_GET['status'] ?? 'all';
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
        case 'active':
            $whereConditions[] = "t.state IN ('OPEN', 'SENT', 'RECEIVING')";
            break;
        case 'completed':
            $whereConditions[] = "t.state IN ('RECEIVED', 'CLOSED')";
            break;
        case 'cancelled':
            $whereConditions[] = "t.state = 'CANCELLED'";
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
$ordersQuery = "
    SELECT
        t.id,
        t.public_id,
        t.vend_number,
        t.supplier_id,
        t.outlet_to,
        t.state as status,
        t.created_at,
        t.expected_delivery_date,
        t.tracking_number,
        t.total_cost as total_value,
        o.name as outlet_name,
        o.id as store_code,
        COUNT(ti.id) as item_count,
        SUM(ti.quantity) as total_quantity
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE {$whereClause}
    GROUP BY t.id, t.public_id, t.vend_number, t.supplier_id, t.outlet_to, t.state, t.created_at, t.expected_delivery_date, t.tracking_number, t.total_cost, o.name, o.id
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
?>

<!-- Modern Purchase Orders Page -->
<div class="purchase-orders-page">

    <!-- Page Header with Action Buttons -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title mb-1">
                    <i class="fas fa-boxes"></i> Orders from Vape Shed
                </h1>
                <p class="text-muted mb-0"><?php echo number_format($totalOrders); ?> orders received from customer</p>
            </div>
            <div class="col-md-6 text-md-end">
                <button class="btn btn-success me-2" onclick="exportOrdersCSV()">
                    <i class="fas fa-file-csv"></i> Export Report
                </button>
                <button class="btn btn-primary me-2" onclick="bulkUpdateTracking()">
                    <i class="fas fa-shipping-fast"></i> Bulk Update Tracking
                </button>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>    <!-- Filters and Search Bar -->
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
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
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
                <h5 class="mb-0">Orders List</h5>
                <small class="text-muted">
                    Showing <?php echo number_format(min($offset + 1, $totalOrders)); ?>
                    to <?php echo number_format(min($offset + $perPage, $totalOrders)); ?>
                    of <?php echo number_format($totalOrders); ?>
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 orders-table">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Store Location</th>
                            <th>Date Ordered</th>
                            <th>Expected Delivery</th>
                            <th class="text-center">Items</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Value</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Tracking</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <p class="mb-0">No purchase orders found matching your criteria</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($order['public_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($order['outlet_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['store_code'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                    </td>
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
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo number_format($order['item_count']); ?></span>
                                    </td>
                                    <td class="text-end"><?php echo number_format($order['total_quantity'] ?? 0); ?></td>
                                    <td class="text-end fw-bold">$<?php echo number_format($order['total_value'] ?? 0, 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['state']); ?>">
                                            <?php echo htmlspecialchars($order['state']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($order['state'] === 'SENT' || $order['state'] === 'RECEIVING'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="updateTracking(<?php echo $order['id']; ?>)" title="Add/Update Tracking">
                                                <i class="fas fa-shipping-fast"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?tab=orders&view=<?php echo $order['id']; ?>" class="btn btn-light" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($order['state'] === 'OPEN' || $order['state'] === 'SENT'): ?>
                                                <button class="btn btn-primary" onclick="updateOrder(<?php echo $order['id']; ?>)" title="Update Status">
                                                    <i class="fas fa-edit"></i>
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

    <!-- Additional Widgets Row -->
    <div class="row g-4 mb-4">

        <!-- Widget 1: Active Orders Status (always show) -->
        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-tasks"></i> Orders to Fulfill</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0"><?php echo number_format($activeStats['active_count']); ?></h3>
                        <i class="fas fa-box-open fa-2x text-primary opacity-25"></i>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">New Orders</span>
                            <span class="badge bg-primary"><?php echo number_format($activeStats['open_count']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Shipped</span>
                            <span class="badge bg-info"><?php echo number_format($activeStats['sent_count']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Being Received</span>
                            <span class="badge bg-warning"><?php echo number_format($activeStats['receiving_count']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Widget 2: Monthly Performance (show if has data) -->
        <?php if ($monthlyStats['orders_this_month'] > 0): ?>
        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> Your Sales This Month</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0"><?php echo number_format($monthlyStats['orders_this_month']); ?></h3>
                        <i class="fas fa-dollar-sign fa-2x text-success opacity-25"></i>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Units Sold</span>
                            <strong><?php echo number_format($monthlyStats['units_this_month']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Revenue Generated</span>
                            <strong class="text-success">$<?php echo number_format($monthlyStats['value_this_month'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Widget 3: Pending Deliveries (show if has data) -->
        <?php if ($pendingDeliveries['pending_count'] > 0): ?>
        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-truck"></i> Shipments Due</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0"><?php echo number_format($pendingDeliveries['pending_count']); ?></h3>
                        <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Past Due Date</span>
                            <span class="badge bg-danger"><?php echo number_format($pendingDeliveries['overdue_count']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Due This Week</span>
                            <span class="badge bg-warning"><?php echo number_format($pendingDeliveries['due_soon_count']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Additional Widgets Row 2 -->
    <div class="row g-4">

        <!-- Widget 4: Top Outlets (show if has data) -->
        <?php if (!empty($topOutlets)): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-store"></i> Top Customers (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Store Location</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-end">Your Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topOutlets as $outlet): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($outlet['outlet_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($outlet['store_code'] ?? ''); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo number_format($outlet['order_count']); ?></span>
                                        </td>
                                        <td class="text-end fw-bold">$<?php echo number_format($outlet['total_value'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Widget 5: Recent Activity (show if has data) -->
        <?php if (!empty($recentActivity)): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['public_id']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars($activity['outlet_name']); ?> •
                                            <?php echo $activity['item_count']; ?> items
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo getStatusBadgeClass($activity['state']); ?> mb-1">
                                            <?php echo $activity['state']; ?>
                                        </span>
                                        <div class="small text-muted">
                                            <?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
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
    transition: all 0.2s ease;
}

.orders-table tbody tr:hover {
    background-color: rgba(59, 130, 246, 0.05);
    transform: scale(1.01);
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
