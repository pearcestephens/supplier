<?php
/**
 * Supplier Portal - Dashboard View
 * 
 * @package CIS\Supplier\Views
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

// Get supplier statistics
$stats = get_supplier_stats();

// Get recent orders
$recentOrders = get_recent_orders(5);

// Get pending claims
$pendingClaims = get_pending_claims(5);
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <p class="mb-0 text-gray-600">
        <?= htmlspecialchars($currentPage['description']) ?>
    </p>
    <div>
        <button class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard();">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <?php if (FEATURE_NEURO_AI): ?>
        <button class="btn btn-sm btn-primary" onclick="openNeuroAI();">
            <i class="fas fa-robot"></i> Ask Neuro
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards Row -->
<div class="row">
    
    <!-- Active Orders Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Active Orders
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($stats['active_orders']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Claims Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Claims
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($stats['pending_claims']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shield-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 30-Day Revenue Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            30-Day Revenue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= format_currency($stats['total_revenue_30d']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Average Order Value Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Avg Order Value
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= format_currency($stats['avg_order_value']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Content Row -->
<div class="row">
    
    <!-- Recent Orders -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-shopping-cart"></i> Recent Orders
                </h6>
                <a href="<?= BASE_URL ?>?page=orders" class="btn btn-sm btn-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        No recent orders
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Outlet</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>?page=orders&order_id=<?= urlencode($order['order_id']) ?>">
                                            <?= htmlspecialchars($order['order_id']) ?>
                                        </a>
                                    </td>
                                    <td><?= format_date($order['order_date']) ?></td>
                                    <td><?= htmlspecialchars($order['outlet_name'] ?? 'N/A') ?></td>
                                    <td><?= format_currency($order['total_amount']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $order['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Pending Warranty Claims -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-shield-alt"></i> Pending Warranty Claims
                </h6>
                <a href="<?= BASE_URL ?>?page=warranty" class="btn btn-sm btn-warning">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($pendingClaims)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                        No pending warranty claims
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Claim ID</th>
                                    <th>Product</th>
                                    <th>Outlet</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingClaims as $claim): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>?page=warranty&claim_id=<?= urlencode($claim['claim_id']) ?>">
                                            <?= htmlspecialchars($claim['claim_id']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($claim['product_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($claim['outlet_name'] ?? 'N/A') ?></td>
                                    <td><?= time_ago($claim['submitted_date']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>?page=warranty&claim_id=<?= urlencode($claim['claim_id']) ?>" 
                                           class="btn btn-xs btn-primary">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="<?= BASE_URL ?>?page=orders" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-search fa-2x mb-2 d-block"></i>
                            Search Orders
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= BASE_URL ?>?page=warranty" class="btn btn-outline-warning btn-block">
                            <i class="fas fa-clipboard-check fa-2x mb-2 d-block"></i>
                            Process Claims
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= BASE_URL ?>?page=reports" class="btn btn-outline-success btn-block">
                            <i class="fas fa-file-download fa-2x mb-2 d-block"></i>
                            Download Reports
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= BASE_URL ?>?page=account" class="btn btn-outline-info btn-block">
                            <i class="fas fa-user-cog fa-2x mb-2 d-block"></i>
                            Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    location.reload();
}
</script>
