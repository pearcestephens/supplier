<?php
/**
 * Supplier Portal - Dashboard Tab
 * 
 * Overview of key metrics: orders, warranty claims, recent activity
 * 
 * @package CIS\Supplier\Tabs
 */

declare(strict_types=1);

// Get real stats from database
$supplierID = Auth::getSupplierId();

// Active Purchase Orders (last 90 days) - using transfers table with PURCHASE_ORDER type
$activeOrdersCount = Database::queryOne("
    SELECT COUNT(DISTINCT t.id) as count
    FROM transfers t
    WHERE t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    AND t.state IN ('OPEN', 'SENT', 'RECEIVING', 'PARTIAL')
    AND t.deleted_at IS NULL
", [$supplierID]);

// Pending Warranty Claims
$pendingClaimsCount = Database::queryOne("
    SELECT COUNT(fp.id) as count
    FROM faulty_products fp
    INNER JOIN vend_products p ON fp.product_id = p.id
    WHERE p.supplier_id = ?
    AND fp.supplier_status = 0
    AND fp.status IN ('pending', 'open', 'new')
", [$supplierID]);

// 30-Day Stats (completed purchase orders)
$revenueStats = Database::queryOne("
    SELECT 
        COUNT(DISTINCT t.id) as order_count,
        COUNT(ti.id) as total_items
    FROM transfers t
    LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
    WHERE t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND t.state IN ('RECEIVED', 'CLOSED')
    AND t.deleted_at IS NULL
", [$supplierID]);

$stats = [
    'active_orders' => (int)($activeOrdersCount['count'] ?? 0),
    'pending_claims' => (int)($pendingClaimsCount['count'] ?? 0),
    'completed_orders_30d' => (int)($revenueStats['order_count'] ?? 0),
    'total_items_30d' => (int)($revenueStats['total_items'] ?? 0),
];

// Recent purchase orders (last 10)
$recentOrders = Database::queryAll("
    SELECT 
        t.id,
        CONCAT('PO-', t.id) as po_number,
        t.created_at as date,
        vo.name as outlet,
        t.state as status,
        COUNT(ti.id) as item_count
    FROM transfers t
    LEFT JOIN vend_outlets vo ON t.outlet_to = vo.id
    LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
    WHERE t.supplier_id = ?
    AND t.transfer_category = 'PURCHASE_ORDER'
    AND t.deleted_at IS NULL
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 10
", [$supplierID]);

// Pending warranty claims (last 10)
$pendingClaims = Database::queryAll("
    SELECT 
        fp.id as fault_id,
        p.name as product,
        fp.store_location as outlet,
        fp.time_created as submitted,
        DATEDIFF(NOW(), fp.time_created) as days_open
    FROM faulty_products fp
    INNER JOIN vend_products p ON fp.product_id = p.id
    WHERE p.supplier_id = ?
    AND fp.supplier_status = 0
    AND fp.status IN ('pending', 'open', 'new')
    ORDER BY fp.time_created DESC
    LIMIT 10
", [$supplierID]);
?>

<!-- Stats Cards - AdminLTE Info Boxes -->
<div class="row">
    
    <!-- Active Orders -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-file-invoice-dollar"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Orders</span>
                <span class="info-box-number">
                    <?php echo number_format($stats['active_orders']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Pending Claims -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending Claims</span>
                <span class="info-box-number">
                    <?php echo number_format($stats['pending_claims']); ?>
                    <?php if ($stats['pending_claims'] > 0): ?>
                        <a href="/supplier/?tab=warranty" class="btn btn-xs btn-warning ml-2">Review</a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- 30-Day Orders -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">30-Day Orders</span>
                <span class="info-box-number">
                    <?php echo number_format($stats['completed_orders_30d']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Total Items -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-box"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Items Ordered (30d)</span>
                <span class="info-box-number">
                    <?php echo number_format($stats['total_items_30d']); ?>
                </span>
            </div>
        </div>
    </div>
    
</div>

<!-- Recent Orders & Pending Claims Row -->
<div class="row">
    
    <!-- Recent Orders -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Purchase Orders</h6>
                <a href="/supplier/?tab=orders" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Outlet</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <p>No recent purchase orders found.</p>
                                        <small>Orders from the last 90 days will appear here.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['po_number']); ?></strong>
                                        </td>
                                        <td><?php echo date('j M Y', strtotime($order['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['outlet'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($order['item_count']); ?> items</td>
                                        <td>
                                            <?php 
                                            $status = strtoupper($order['status']);
                                            if ($status === 'RECEIVED' || $status === 'STOCKEDIN'): ?>
                                                <span class="badge badge-success">Received</span>
                                            <?php elseif ($status === 'SENT'): ?>
                                                <span class="badge badge-info">Sent</span>
                                            <?php elseif ($status === 'OPEN'): ?>
                                                <span class="badge badge-warning">Open</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/supplier/supplier-view-purchase-order.php?id=<?php echo (int)$order['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Warranty Claims -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-warning">Pending Warranty Claims</h6>
                <a href="/supplier/?tab=warranty" class="btn btn-sm btn-warning">Review All</a>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if (empty($pendingClaims)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                            <p>No pending warranty claims!</p>
                            <small>All claims have been reviewed.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingClaims as $claim): ?>
                            <a href="/supplier/?tab=warranty&fault_id=<?php echo (int)$claim['fault_id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-exclamation-circle text-warning"></i>
                                        Claim #<?php echo htmlspecialchars($claim['fault_id']); ?>
                                    </h6>
                                    <small class="text-danger"><?php echo (int)$claim['days_open']; ?> days open</small>
                                </div>
                                <p class="mb-1">
                                    <strong><?php echo htmlspecialchars($claim['product']); ?></strong>
                                </p>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($claim['outlet'] ?? 'Unknown'); ?> · 
                                    <?php echo date('j M Y', strtotime($claim['submitted'])); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Quick Actions Panel -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary btn-block" onclick="downloadAllOrders();">
                            <i class="fas fa-download"></i> Download All Orders
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-success btn-block" onclick="generate30DayReport();">
                            <i class="fas fa-file-pdf"></i> 30-Day Report
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-warning btn-block" onclick="exportWarrantyClaims();">
                            <i class="fas fa-file-csv"></i> Export Warranty CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 0.35rem;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-xs {
    font-size: 0.7rem;
    font-weight: 700;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}
</style>

<script>
function refreshDashboard() {
    showToast('Refreshing dashboard...', 'info');
    location.reload();
}

function downloadAllOrders() {
    window.location.href = '/supplier/api/download-orders.php?action=bulk&format=zip';
}

function generate30DayReport() {
    window.location.href = '/supplier/api/generate-report.php?period=30&format=pdf';
}

function exportWarrantyClaims() {
    window.location.href = '/supplier/supplier-warranty-returns.php?getCSV=1';
}

function showToast(message, type) {
    // TODO: Implement toast notification
    alert(message);
}
</script>
