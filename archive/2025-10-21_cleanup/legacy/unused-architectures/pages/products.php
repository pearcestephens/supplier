<?php
/**
 * Products Catalog Page
 * 
 * List all supplier products with inventory levels
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_products');

// Get search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$per_page = 50;
$offset = ($page_num - 1) * $per_page;

// Get products with inventory
$products_sql = "SELECT 
                    p.id, p.name, p.sku, p.description,
                    p.price_including_tax as retail_price, p.supply_price
                 FROM vend_products p
                 WHERE p.supplier_id = ?
                 AND p.deleted_at = '0000-00-00 00:00:00'";

if ($search) {
    $products_sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
}

$products_sql .= " ORDER BY p.name ASC
                   LIMIT ? OFFSET ?";

$products_stmt = $conn->prepare($products_sql);

if ($search) {
    $search_param = "%{$search}%";
    $products_stmt->bind_param('sssii', $supplier_id, $search_param, $search_param, $per_page, $offset);
} else {
    $products_stmt->bind_param('sii', $supplier_id, $per_page, $offset);
}

$products_stmt->execute();
$products = $products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$products_stmt->close();

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM vend_products WHERE supplier_id = ? AND deleted_at = '0000-00-00 00:00:00'";
if ($search) {
    $count_sql .= " AND (name LIKE ? OR sku LIKE ?)";
}

$count_stmt = $conn->prepare($count_sql);
if ($search) {
    $search_param = "%{$search}%";
    $count_stmt->bind_param('sss', $supplier_id, $search_param, $search_param);
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
                <h1 class="m-0">Product Catalog</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Products</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Search Bar -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="" class="form-inline">
                            <input type="hidden" name="page" value="products">
                            <div class="input-group input-group-lg" style="width: 100%; max-width: 600px;">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by product name or SKU..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if ($search): ?>
                                        <a href="?page=products" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-boxes"></i> 
                            Your Products 
                            <span class="badge badge-primary ml-2"><?php echo number_format($total_count); ?></span>
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info m-3">
                                <i class="fas fa-info-circle"></i>
                                <?php if ($search): ?>
                                    No products found matching your search.
                                <?php else: ?>
                                    No products found.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Product Name</th>
                                            <th class="text-right">Supply Price</th>
                                            <th class="text-right">Retail Price</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars($product['sku']); ?></code>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                    <?php if ($product['description']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $desc = htmlspecialchars($product['description']);
                                                            echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right">
                                                    <?php echo format_currency($product['supply_price'] ?? 0); ?>
                                                </td>
                                                <td class="text-right">
                                                    <?php echo format_currency($product['retail_price'] ?? 0); ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-success">Active</span>
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
                                        <a class="page-link" href="?page=products&pg=<?php echo $page_num - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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
                                        <a class="page-link" href="?page=products&pg=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page_num < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=products&pg=<?php echo $page_num + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
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

        <!-- Summary Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="info-box bg-gradient-info">
                    <span class="info-box-icon"><i class="fas fa-boxes"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Products in Catalog</span>
                        <span class="info-box-number"><?php echo number_format($total_count); ?></span>
                        <div class="progress">
                            <div class="progress-bar" style="width: 100%"></div>
                        </div>
                        <span class="progress-description">
                            Showing <?php echo count($products); ?> products on this page
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
