<?php
/**
 * Dashboard Page - Real Data
 * Shows supplier overview, stats, recent POs, warranty claims
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_dashboard');

// Get recent purchase orders
$recent_pos = get_supplier_purchase_orders($conn, $supplier_id, null, 5);

// Get recent warranty claims
$recent_claims = get_supplier_warranty_claims($conn, $supplier_id, 0, 5); // Pending only

// Get top selling products
$top_products = get_top_selling_products($conn, $supplier_id, 5);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Welcome Banner -->
        <div class="row">
            <div class="col-12">
                <div class="callout callout-info">
                    <h5><i class="fas fa-info"></i> Welcome, <?php echo htmlspecialchars($supplier['name']); ?>!</h5>
                    <p>Access your purchase orders, manage warranty claims, and view product analytics from your dashboard.</p>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <!-- Active Purchase Orders -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $stats['active_pos']; ?></h3>
                        <p>Active Purchase Orders</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <a href="?page=purchase-orders" class="small-box-footer">
                        View All <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Pending Warranty Claims -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $stats['pending_claims']; ?></h3>
                        <p>Pending Warranty Claims</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <a href="?page=warranty-claims" class="small-box-footer">
                        Review Claims <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Total Products -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <a href="?page=products" class="small-box-footer">
                        View Products <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $stats['unread_notifications']; ?></h3>
                        <p>Unread Notifications</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <a href="?page=notifications" class="small-box-footer">
                        View Notifications <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Row -->
        <div class="row">
            <!-- Recent Purchase Orders -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-shopping-cart mr-2"></i>Recent Purchase Orders</h3>
                        <div class="card-tools">
                            <a href="?page=purchase-orders" class="btn btn-tool btn-sm">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_pos)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No recent purchase orders</p>
                        </div>
                        <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>PO #</th>
                                    <th>Outlet</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_pos as $po): ?>
                                <tr>
                                    <td>
                                        <a href="?page=purchase-order-detail&id=<?php echo $po['id']; ?>">
                                            <?php echo htmlspecialchars($po['public_id']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($po['outlet_name']); ?></td>
                                    <td><?php echo $po['total_items']; ?> items</td>
                                    <td>
                                        <span class="badge badge-<?php echo get_state_badge_class($po['state']); ?>">
                                            <?php echo htmlspecialchars($po['state']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo time_ago($po['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Warranty Claims -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2"></i>Pending Warranty Claims</h3>
                        <div class="card-tools">
                            <a href="?page=warranty-claims" class="btn btn-tool btn-sm">
                                <i class="fas fa-list"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_claims)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                            <p>No pending warranty claims</p>
                        </div>
                        <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Serial #</th>
                                    <th>Store</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_claims as $claim): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($claim['product_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($claim['sku']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($claim['serial_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($claim['store_location']); ?></td>
                                    <td><?php echo time_ago($claim['time_created']); ?></td>
                                    <td>
                                        <a href="?page=warranty-claim-detail&id=<?php echo $claim['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Selling Products -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Top Selling Products (Last 30 Days)</h3>
                        <div class="card-tools">
                            <a href="?page=analytics" class="btn btn-tool btn-sm">
                                <i class="fas fa-chart-bar"></i> Full Analytics
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($top_products)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                            <p>No sales data available for the last 30 days</p>
                        </div>
                        <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th class="text-right">Units Sold</th>
                                    <th class="text-right">Transactions</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $index => $product): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'light'); ?>">
                                            #<?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td class="text-right"><strong><?php echo number_format($product['units_sold']); ?></strong></td>
                                    <td class="text-right"><?php echo number_format($product['transaction_count']); ?></td>
                                    <td class="text-right"><?php echo format_currency((float)$product['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section>
