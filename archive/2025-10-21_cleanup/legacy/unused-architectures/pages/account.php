<?php
/**
 * Account Settings Page
 * 
 * Supplier profile, contact information, and activity log
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_account');

// Get recent activity logs
$logs_sql = "SELECT * FROM supplier_portal_logs 
             WHERE supplier_id = ? 
             ORDER BY created_at DESC 
             LIMIT 50";
$logs_stmt = $conn->prepare($logs_sql);
$logs_stmt->bind_param('s', $supplier_id);
$logs_stmt->execute();
$activity_logs = $logs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logs_stmt->close();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Account Settings</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Account</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <div class="row">
            <!-- Left Column - Profile -->
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i> Supplier Profile
                        </h3>
                    </div>
                    <div class="card-body box-profile">
                        <?php if ($supplier['brand_logo_url']): ?>
                            <div class="text-center mb-3">
                                <img src="<?php echo htmlspecialchars($supplier['brand_logo_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($supplier['name']); ?>"
                                     class="img-fluid" style="max-height: 100px;">
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="profile-username text-center">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </h3>
                        
                        <p class="text-muted text-center">
                            Supplier ID: <code><?php echo htmlspecialchars($supplier['id']); ?></code>
                        </p>
                        
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b><i class="fas fa-envelope mr-1"></i> Email</b>
                                <a class="float-right">
                                    <?php echo htmlspecialchars($supplier['email'] ?? 'Not set'); ?>
                                </a>
                            </li>
                            <li class="list-group-item">
                                <b><i class="fas fa-envelope mr-1"></i> Claims Email</b>
                                <a class="float-right">
                                    <?php echo htmlspecialchars($supplier['claim_email'] ?? 'Not set'); ?>
                                </a>
                            </li>
                            <li class="list-group-item">
                                <b><i class="fas fa-phone mr-1"></i> Phone</b>
                                <a class="float-right">
                                    <?php echo htmlspecialchars($supplier['phone'] ?? 'Not set'); ?>
                                </a>
                            </li>
                            <li class="list-group-item">
                                <b><i class="fas fa-user mr-1"></i> Contact Person</b>
                                <a class="float-right">
                                    <?php echo htmlspecialchars($supplier['contact_name'] ?? 'Not set'); ?>
                                </a>
                            </li>
                            <li class="list-group-item">
                                <b><i class="fas fa-globe mr-1"></i> Website</b>
                                <a class="float-right" href="<?php echo htmlspecialchars($supplier['website'] ?? '#'); ?>" target="_blank">
                                    <?php 
                                    $website = $supplier['website'] ?? 'Not set';
                                    echo strlen($website) > 30 ? substr($website, 0, 30) . '...' : $website;
                                    ?>
                                </a>
                            </li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> To update your contact information, please contact The Vape Shed support team.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Activity Log -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i> Recent Activity
                        </h3>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($activity_logs)): ?>
                            <div class="p-3">
                                <p class="text-muted">No activity recorded yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Date/Time</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $action_labels = [
                                                    'login' => '<i class="fas fa-sign-in-alt text-success"></i> Logged In',
                                                    'logout' => '<i class="fas fa-sign-out-alt text-warning"></i> Logged Out',
                                                    'view_dashboard' => '<i class="fas fa-tachometer-alt text-info"></i> Viewed Dashboard',
                                                    'view_purchase_orders' => '<i class="fas fa-file-invoice text-primary"></i> Viewed Purchase Orders',
                                                    'view_purchase_order_detail' => '<i class="fas fa-file-invoice text-primary"></i> Viewed PO Detail',
                                                    'view_warranty_claims' => '<i class="fas fa-exclamation-triangle text-warning"></i> Viewed Warranty Claims',
                                                    'view_warranty_claim_detail' => '<i class="fas fa-exclamation-triangle text-warning"></i> Viewed Claim Detail',
                                                    'update_po_status' => '<i class="fas fa-edit text-success"></i> Updated PO Status',
                                                    'update_warranty_claim' => '<i class="fas fa-edit text-success"></i> Updated Warranty Claim',
                                                    'add_warranty_note' => '<i class="fas fa-comment text-info"></i> Added Warranty Note',
                                                    'view_analytics' => '<i class="fas fa-chart-line text-success"></i> Viewed Analytics',
                                                    'view_products' => '<i class="fas fa-boxes text-primary"></i> Viewed Products',
                                                    'view_account' => '<i class="fas fa-user text-info"></i> Viewed Account'
                                                ];
                                                
                                                echo $action_labels[$log['action']] ?? '<i class="fas fa-circle text-secondary"></i> ' . htmlspecialchars($log['action']);
                                                ?>
                                                
                                                <?php if ($log['resource_type'] && $log['resource_id']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($log['resource_type']); ?> #<?php echo htmlspecialchars($log['resource_id']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                                    <br>
                                                    <span class="text-muted"><?php echo time_ago($log['created_at']); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <small><code><?php echo htmlspecialchars($log['ip_address']); ?></code></small>
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

    </div>
</section>
