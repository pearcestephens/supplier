<?php
/**
 * Purchase Orders List Page
 * 
 * Displays all purchase orders from transfers table
 * with filtering, search, and pagination
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_purchase_orders');

// Get filter parameters
$state_filter = isset($_GET['state']) ? $_GET['state'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$per_page = 25;
$offset = ($page_num - 1) * $per_page;

// Get purchase orders with filtering
$purchase_orders = get_supplier_purchase_orders($conn, $supplier_id, $state_filter, $per_page, $offset, $search);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM transfers 
              WHERE transfer_category = 'PURCHASE_ORDER' 
              AND supplier_id = ? 
              AND deleted_at IS NULL";

if ($state_filter) {
    $count_sql .= " AND state = ?";
}

if ($search) {
    $count_sql .= " AND public_id LIKE ?";
}

$count_stmt = $conn->prepare($count_sql);

if ($state_filter && $search) {
    $search_param = "%{$search}%";
    $count_stmt->bind_param('sss', $supplier_id, $state_filter, $search_param);
} elseif ($state_filter) {
    $count_stmt->bind_param('ss', $supplier_id, $state_filter);
} elseif ($search) {
    $search_param = "%{$search}%";
    $count_stmt->bind_param('ss', $supplier_id, $search_param);
} else {
    $count_stmt->bind_param('s', $supplier_id);
}

$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);
$count_stmt->close();

// Available states
$available_states = [
    'OPEN' => 'Open',
    'SENT' => 'Sent',
    'RECEIVING' => 'Receiving',
    'PARTIAL' => 'Partially Received',
    'RECEIVED' => 'Received',
    'CLOSED' => 'Closed',
    'CANCELLED' => 'Cancelled'
];
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Purchase Orders</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Purchase Orders</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Filters Row -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-filter"></i> Filters
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-inline">
                            <input type="hidden" name="page" value="purchase-orders">
                            
                            <!-- Search -->
                            <div class="form-group mr-3">
                                <label for="search" class="mr-2">Search PO:</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="PO Number..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <!-- State Filter -->
                            <div class="form-group mr-3">
                                <label for="state" class="mr-2">Status:</label>
                                <select class="form-control" id="state" name="state">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($available_states as $state_value => $state_label): ?>
                                        <option value="<?php echo $state_value; ?>" 
                                                <?php echo ($state_filter === $state_value) ? 'selected' : ''; ?>>
                                            <?php echo $state_label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Buttons -->
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-search"></i> Apply
                            </button>
                            <a href="?page=purchase-orders" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-invoice"></i> 
                            Your Purchase Orders 
                            <span class="badge badge-info ml-2"><?php echo number_format($total_count); ?></span>
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-secondary">
                                Page <?php echo $page_num; ?> of <?php echo max(1, $total_pages); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($purchase_orders)): ?>
                            <div class="alert alert-info m-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>No purchase orders found.</strong>
                                <?php if ($state_filter || $search): ?>
                                    Try adjusting your filters.
                                <?php else: ?>
                                    No purchase orders have been created yet.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Store</th>
                                            <th>Items</th>
                                            <th>Total Qty</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Due Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_orders as $po): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($po['public_id']); ?></strong>
                                                    <?php if ($po['notes']): ?>
                                                        <i class="fas fa-sticky-note text-warning ml-1" 
                                                           title="<?php echo htmlspecialchars(substr($po['notes'], 0, 100)); ?>"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($po['outlet_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-secondary">
                                                        <?php echo number_format($po['total_items']); ?> items
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $total_qty = $po['qty_requested'] ?? 0;
                                                    $qty_received = $po['qty_received'] ?? 0;
                                                    $qty_sent = $po['qty_sent'] ?? 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <?php if ($qty_received > 0): ?>
                                                            <div class="progress-bar bg-success" 
                                                                 style="width: <?php echo ($qty_received / max($total_qty, 1)) * 100; ?>%">
                                                                <?php echo number_format($qty_received); ?> recv
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($qty_sent > $qty_received): ?>
                                                            <div class="progress-bar bg-info" 
                                                                 style="width: <?php echo (($qty_sent - $qty_received) / max($total_qty, 1)) * 100; ?>%">
                                                                <?php echo number_format($qty_sent - $qty_received); ?> sent
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($total_qty > $qty_sent): ?>
                                                            <div class="progress-bar bg-light text-dark" 
                                                                 style="width: <?php echo (($total_qty - $qty_sent) / max($total_qty, 1)) * 100; ?>%">
                                                                <?php echo number_format($total_qty - $qty_sent); ?> pending
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        Requested: <?php echo number_format($total_qty); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo get_state_badge_class($po['state']); ?>">
                                                        <?php echo htmlspecialchars($po['state']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo date('M j, Y', strtotime($po['created_at'])); ?>
                                                        <br>
                                                        <span class="text-muted">
                                                            <?php echo time_ago($po['created_at']); ?>
                                                        </span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($po['due_at']): ?>
                                                        <small>
                                                            <?php 
                                                            echo date('M j, Y', strtotime($po['due_at']));
                                                            $days_until = (strtotime($po['due_at']) - time()) / 86400;
                                                            if ($days_until < 0) {
                                                                echo '<br><span class="badge badge-danger">Overdue</span>';
                                                            } elseif ($days_until < 7) {
                                                                echo '<br><span class="badge badge-warning">Due soon</span>';
                                                            }
                                                            ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?page=purchase-order-detail&id=<?php echo $po['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer clearfix">
                            <ul class="pagination pagination-sm m-0 float-right">
                                <?php if ($page_num > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=purchase-orders&pg=<?php echo $page_num - 1; ?><?php echo $state_filter ? '&state=' . urlencode($state_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            &laquo; Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page_num - 2);
                                $end_page = min($total_pages, $page_num + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo ($i === $page_num) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=purchase-orders&pg=<?php echo $i; ?><?php echo $state_filter ? '&state=' . urlencode($state_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page_num < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=purchase-orders&pg=<?php echo $page_num + 1; ?><?php echo $state_filter ? '&state=' . urlencode($state_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            Next &raquo;
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($stats['active_pos']); ?></h3>
                        <p>Active Orders</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <a href="?page=purchase-orders&state=OPEN" class="small-box-footer">
                        View Active <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <?php
                        // Count sent orders
                        $sent_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transfers WHERE supplier_id = ? AND transfer_category = 'PURCHASE_ORDER' AND state = 'SENT' AND deleted_at IS NULL");
                        $sent_stmt->bind_param('s', $supplier_id);
                        $sent_stmt->execute();
                        $sent_count = $sent_stmt->get_result()->fetch_assoc()['cnt'];
                        $sent_stmt->close();
                        ?>
                        <h3><?php echo number_format($sent_count); ?></h3>
                        <p>In Transit</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <a href="?page=purchase-orders&state=SENT" class="small-box-footer">
                        View Sent <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <?php
                        // Count received orders
                        $recv_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transfers WHERE supplier_id = ? AND transfer_category = 'PURCHASE_ORDER' AND state IN ('RECEIVED', 'CLOSED') AND deleted_at IS NULL");
                        $recv_stmt->bind_param('s', $supplier_id);
                        $recv_stmt->execute();
                        $recv_count = $recv_stmt->get_result()->fetch_assoc()['cnt'];
                        $recv_stmt->close();
                        ?>
                        <h3><?php echo number_format($recv_count); ?></h3>
                        <p>Received</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="?page=purchase-orders&state=RECEIVED" class="small-box-footer">
                        View Received <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo number_format($total_count); ?></h3>
                        <p>Total Orders</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <a href="?page=purchase-orders" class="small-box-footer">
                        View All <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>
