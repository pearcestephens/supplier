<?php
/**
 * Warranty Claims List Page
 * 
 * Displays all warranty claims from faulty_products table
 * with filtering and search
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_warranty_claims');

// Get filter parameters
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$per_page = 25;
$offset = ($page_num - 1) * $per_page;

// Get warranty claims
$warranty_claims = get_supplier_warranty_claims($conn, $supplier_id, $status_filter, $per_page, $offset, $search);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM faulty_products fp
              JOIN vend_products p ON fp.product_id = p.id
              WHERE p.supplier_id = ?
              AND fp.deleted_at IS NULL";

if ($status_filter !== null) {
    $count_sql .= " AND fp.supplier_status = ?";
}

if ($search) {
    $count_sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR fp.serial_number LIKE ?)";
}

$count_stmt = $conn->prepare($count_sql);

if ($status_filter !== null && $search) {
    $search_param = "%{$search}%";
    $count_stmt->bind_param('sisss', $supplier_id, $status_filter, $search_param, $search_param, $search_param);
} elseif ($status_filter !== null) {
    $count_stmt->bind_param('si', $supplier_id, $status_filter);
} elseif ($search) {
    $search_param = "%{$search}%";
    $count_stmt->bind_param('ssss', $supplier_id, $search_param, $search_param, $search_param);
} else {
    $count_stmt->bind_param('s', $supplier_id);
}

$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);
$count_stmt->close();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Warranty Claims</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Warranty Claims</li>
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
                            <input type="hidden" name="page" value="warranty-claims">
                            
                            <!-- Search -->
                            <div class="form-group mr-3">
                                <label for="search" class="mr-2">Search:</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Product, SKU, Serial..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <!-- Status Filter -->
                            <div class="form-group mr-3">
                                <label for="status" class="mr-2">Status:</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="0" <?php echo ($status_filter === 0) ? 'selected' : ''; ?>>
                                        Pending Review
                                    </option>
                                    <option value="1" <?php echo ($status_filter === 1) ? 'selected' : ''; ?>>
                                        Resolved
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Buttons -->
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-search"></i> Apply
                            </button>
                            <a href="?page=warranty-claims" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warranty Claims Grid -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Warranty Claims 
                            <span class="badge badge-warning ml-2"><?php echo number_format($total_count); ?></span>
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-secondary">
                                Page <?php echo $page_num; ?> of <?php echo max(1, $total_pages); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($warranty_claims)): ?>
                            <div class="alert alert-info m-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>No warranty claims found.</strong>
                                <?php if ($status_filter !== null || $search): ?>
                                    Try adjusting your filters.
                                <?php else: ?>
                                    No warranty claims have been submitted yet.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Claim ID</th>
                                            <th>Product</th>
                                            <th>Store</th>
                                            <th>Fault Description</th>
                                            <th>Serial Number</th>
                                            <th>Media</th>
                                            <th>Submitted</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($warranty_claims as $claim): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo htmlspecialchars($claim['id']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($claim['product_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        SKU: <code><?php echo htmlspecialchars($claim['sku']); ?></code>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($claim['store_location'] ?? 'Unknown'); ?>
                                                </td>
                                                <td>
                                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php 
                                                        $fault = htmlspecialchars($claim['fault_desc'] ?? 'No description');
                                                        echo strlen($fault) > 100 ? substr($fault, 0, 100) . '...' : $fault;
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($claim['serial_number']): ?>
                                                        <code><?php echo htmlspecialchars($claim['serial_number']); ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($claim['media_count'] > 0): ?>
                                                        <span class="badge badge-info">
                                                            <i class="fas fa-images"></i> 
                                                            <?php echo $claim['media_count']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo date('M j, Y', strtotime($claim['time_created'])); ?>
                                                        <br>
                                                        <span class="text-muted">
                                                            <?php echo time_ago($claim['time_created']); ?>
                                                        </span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($claim['supplier_status'] == 0): ?>
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-clock"></i> Pending Review
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-check-circle"></i> Resolved
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($claim['supplier_update_status'] == 1): ?>
                                                        <br>
                                                        <span class="badge badge-info mt-1">
                                                            <i class="fas fa-comment"></i> Updated
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
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
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer clearfix">
                            <ul class="pagination pagination-sm m-0 float-right">
                                <?php if ($page_num > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=warranty-claims&pg=<?php echo $page_num - 1; ?><?php echo ($status_filter !== null) ? '&status=' . $status_filter : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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
                                        <a class="page-link" href="?page=warranty-claims&pg=<?php echo $i; ?><?php echo ($status_filter !== null) ? '&status=' . $status_filter : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page_num < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=warranty-claims&pg=<?php echo $page_num + 1; ?><?php echo ($status_filter !== null) ? '&status=' . $status_filter : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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
            <div class="col-md-4">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($stats['pending_claims']); ?></h3>
                        <p>Pending Review</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <a href="?page=warranty-claims&status=0" class="small-box-footer">
                        View Pending <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="small-box bg-success">
                    <div class="inner">
                        <?php
                        // Count resolved claims
                        $resolved_stmt = $conn->prepare("
                            SELECT COUNT(*) as cnt 
                            FROM faulty_products fp
                            JOIN vend_products p ON fp.product_id = p.id
                            WHERE p.supplier_id = ? 
                            AND fp.supplier_status = 1
                            AND fp.deleted_at IS NULL
                        ");
                        $resolved_stmt->bind_param('s', $supplier_id);
                        $resolved_stmt->execute();
                        $resolved_count = $resolved_stmt->get_result()->fetch_assoc()['cnt'];
                        $resolved_stmt->close();
                        ?>
                        <h3><?php echo number_format($resolved_count); ?></h3>
                        <p>Resolved Claims</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="?page=warranty-claims&status=1" class="small-box-footer">
                        View Resolved <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($total_count); ?></h3>
                        <p>Total Claims</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <a href="?page=warranty-claims" class="small-box-footer">
                        View All <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>
