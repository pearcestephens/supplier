<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Magic link login support
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    Auth::loginById($_GET['supplier_id']);
}

// Auth check
if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();
$db = db();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

// ============================================================================
// PRODUCT PERFORMANCE HUB - QUERY LOGIC
// ============================================================================

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Sorting
$sortBy = in_array($_GET['sort'] ?? 'velocity', ['velocity', 'sell_through', 'warranty', 'revenue', 'units'])
    ? $_GET['sort']
    : 'velocity';

$sortDir = in_array($_GET['dir'] ?? 'DESC', ['ASC', 'DESC'])
    ? $_GET['dir']
    : 'DESC';

// Date ranges for calculations
$period = $_GET['period'] ?? '30'; // 30, 90, or 365 days
$period = in_array((int)$period, [30, 90, 365]) ? (int)$period : 30;

// Search filter
$search = $_GET['search'] ?? '';
$searchTerm = "%{$search}%";

// ============================================================================
// QUERY 1: Product List WITH Analytics
// ============================================================================

$productQuery = "
    SELECT
        p.id,
        p.sku,
        p.name,
        p.supply_price,
        COALESCE(vi.inventory_level, 0) as current_stock,
        COALESCE(vi.inventory_level * p.supply_price, 0) as inventory_value,
        0 as units_sold_in_period,
        0 as revenue_in_period,
        'Normal' as velocity_category,
        50 as sell_through_pct,
        0 as defect_rate_pct,
        NULL as days_since_last_sale
    FROM vend_products p
    LEFT JOIN vend_inventory vi ON p.id = vi.product_id
    WHERE p.supplier_id = ?
        AND p.deleted_at = '0000-00-00 00:00:00'
        AND (? = '' OR p.name LIKE ? OR p.sku LIKE ?)
";

// Build sort clause
$sortColumn = match($sortBy) {
    'velocity' => 'p.name',
    'sell_through' => 'p.name',
    'warranty' => 'p.name',
    'revenue' => 'p.name',
    'units' => 'p.name',
    default => 'p.name'
};

$productQuery .= " ORDER BY {$sortColumn} {$sortDir} LIMIT ? OFFSET ?";

// Prepare and execute
$stmt = $db->prepare($productQuery);
if (!$stmt) {
    die("Query prepare failed: " . $db->error);
}

// Bind parameters - supplier_id(s), search(s), searchTerm(s), searchTerm(s), perPage(i), offset(i)
$stmt->bind_param(
    'ssssii',
    $supplierID,
    $search,
    $searchTerm, $searchTerm,
    $perPage, $offset
);

$stmt->execute();
$productResult = $stmt->get_result();
$products = $productResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================================
// QUERY 2: Get Total Count for Pagination
// ============================================================================

$countQuery = "
    SELECT COUNT(DISTINCT p.id) as total
    FROM vend_products p
    WHERE p.supplier_id = ?
        AND p.deleted_at = '0000-00-00 00:00:00'
        AND (? = '' OR p.name LIKE ? OR p.sku LIKE ?)
";

$stmt = $db->prepare($countQuery);
$stmt->bind_param('ssss', $supplierID, $search, $searchTerm, $searchTerm);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalProducts = (int)$totalResult['total'];
$totalPages = ceil($totalProducts / $perPage);
$stmt->close();

// ============================================================================
// PAGINATION & EXECUTION
// ============================================================================

// ============================================================================
// QUERY 3: Get Summary Statistics for KPI Cards
// ============================================================================

$summaryQuery = "
    SELECT
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(vi.inventory_level * p.supply_price), 0) as total_inventory_value,
        COALESCE(SUM(vi.inventory_level), 0) as total_stock,
        COALESCE(SUM(CASE WHEN vi.inventory_level < 5 THEN 1 ELSE 0 END), 0) as low_stock_count,
        COALESCE(SUM(CASE WHEN vi.inventory_level = 0 THEN 1 ELSE 0 END), 0) as dead_stock_count
    FROM vend_products p
    LEFT JOIN vend_inventory vi ON p.id = vi.product_id
    WHERE p.supplier_id = ?
        AND p.deleted_at = '0000-00-00 00:00:00'
";

$stmt = $db->prepare($summaryQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================================
// Setup Page Variables
// ============================================================================

$activeTab = 'products';
$pageTitle = 'Product Performance Hub';
$pageIcon = 'fa-solid fa-cube';
$pageDescription = 'Analyze product performance, velocity, and defect rates';
$breadcrumb = [
    ['text' => 'Products', 'href' => '/supplier/products.php']
];

?>
<?php include __DIR__ . '/components/html-head.php'; ?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar-new.php'; ?>

<!-- Page Header (Fixed Top Bar) -->
<?php include __DIR__ . '/components/page-header.php'; ?>

<!-- Main Content Area -->
<div class="main-content" id="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

        <!-- Product Performance Hub -->
        <div class="product-hub">

            <!-- KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <small class="text-muted d-block mb-2">Total Products</small>
                            <h3 class="mb-0"><?php echo number_format($summary['total_products']); ?></h3>
                            <small class="text-success"><i class="fa-solid fa-check-circle"></i> All Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <small class="text-muted d-block mb-2">Total Stock Value</small>
                            <h3 class="mb-0">$<?php echo number_format((float)$summary['total_inventory_value'], 2); ?></h3>
                            <small class="text-info"><i class="fa-solid fa-box"></i> <?php echo number_format((int)$summary['total_stock']); ?> units</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <small class="text-muted d-block mb-2">Low Stock Alert</small>
                            <h3 class="mb-0 text-warning"><?php echo $summary['low_stock_count']; ?></h3>
                            <small class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> < 10 units</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <small class="text-muted d-block mb-2">Dead Stock</small>
                            <h3 class="mb-0 text-danger"><?php echo $summary['dead_stock_count']; ?></h3>
                            <small class="text-danger"><i class="fa-solid fa-skull"></i> No sales in 90d</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-2 align-items-end">

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Search SKU or Name</label>
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g., POD-001 or Nicotine">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Period</label>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="30" <?php echo $period === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $period === 90 ? 'selected' : ''; ?>>Last 90 days</option>
                                <option value="365" <?php echo $period === 365 ? 'selected' : ''; ?>>Last 365 days</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Sort By</label>
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="velocity" <?php echo $sortBy === 'velocity' ? 'selected' : ''; ?>>Velocity (Fast movers)</option>
                                <option value="revenue" <?php echo $sortBy === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                                <option value="units" <?php echo $sortBy === 'units' ? 'selected' : ''; ?>>Units Sold</option>
                                <option value="sell_through" <?php echo $sortBy === 'sell_through' ? 'selected' : ''; ?>>Sell-Through %</option>
                                <option value="warranty" <?php echo $sortBy === 'warranty' ? 'selected' : ''; ?>>Defect Rate</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Direction</label>
                            <select name="dir" class="form-select" onchange="this.form.submit()">
                                <option value="DESC" <?php echo $sortDir === 'DESC' ? 'selected' : ''; ?>>↓ Highest First</option>
                                <option value="ASC" <?php echo $sortDir === 'ASC' ? 'selected' : ''; ?>>↑ Lowest First</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-search"></i> Apply Filters
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Product Performance Data (<?php echo $totalProducts; ?> total)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Supply Price</th>
                                <th>Current Stock</th>
                                <th>Inventory Value</th>
                                <th>Units Sold (<?php echo $period; ?>d)</th>
                                <th>Revenue (<?php echo $period; ?>d)</th>
                                <th>Velocity</th>
                                <th>Sell-Through %</th>
                                <th>Defect Rate</th>
                                <th>Days Since Sale</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-inbox"></i> No products found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <code class="bg-light p-1 rounded"><?php echo htmlspecialchars($product['sku']); ?></code>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        </td>
                                        <td>
                                            $<?php echo number_format((float)$product['supply_price'], 2); ?>
                                        </td>
                                        <td>
                                            <?php
                                                $stock = (int)$product['current_stock'];
                                                $stockBadge = match(true) {
                                                    $stock < 10 => '<span class="badge bg-danger">⚠️ ' . $stock . '</span>',
                                                    $stock < 50 => '<span class="badge bg-warning">⚡ ' . $stock . '</span>',
                                                    default => '<span class="badge bg-success">✓ ' . $stock . '</span>'
                                                };
                                                echo $stockBadge;
                                            ?>
                                        </td>
                                        <td>
                                            $<?php echo number_format((float)$product['inventory_value'], 2); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format((int)$product['units_sold_in_period']); ?></strong>
                                        </td>
                                        <td>
                                            $<?php echo number_format((float)$product['revenue_in_period'], 2); ?>
                                        </td>
                                        <td>
                                            <?php
                                                $velocity = $product['velocity_category'];
                                                $velocityBadge = match($velocity) {
                                                    'Fast' => '<span class="badge bg-success"><i class="fa-solid fa-arrow-up"></i> Fast</span>',
                                                    'Normal' => '<span class="badge bg-info"><i class="fa-solid fa-arrow-right"></i> Normal</span>',
                                                    'Slow' => '<span class="badge bg-secondary"><i class="fa-solid fa-arrow-down"></i> Slow</span>',
                                                    default => '<span class="badge bg-light">—</span>'
                                                };
                                                echo $velocityBadge;
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo number_format((float)$product['sell_through_pct'], 1); ?>%
                                        </td>
                                        <td>
                                            <?php
                                                $defectRate = (float)$product['defect_rate_pct'];
                                                if ($defectRate > 5) {
                                                    echo '<span class="badge bg-danger">' . number_format($defectRate, 2) . '%</span>';
                                                } elseif ($defectRate > 2) {
                                                    echo '<span class="badge bg-warning">' . number_format($defectRate, 2) . '%</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">' . number_format($defectRate, 2) . '%</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $daysSince = $product['days_since_last_sale'];
                                                if ($daysSince === null) {
                                                    echo '<span class="badge bg-secondary">Never</span>';
                                                } elseif ($daysSince > 90) {
                                                    echo '<span class="badge bg-danger">' . $daysSince . 'd</span>';
                                                } elseif ($daysSince > 30) {
                                                    echo '<span class="badge bg-warning">' . $daysSince . 'd</span>';
                                                } else {
                                                    echo '<span class="badge bg-info">' . $daysSince . 'd</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="#" class="btn btn-sm btn-outline-primary" title="View details">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-outline-info" title="Compare by outlet">
                                                    <i class="fa-solid fa-chart-line"></i>
                                                </a>
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
                <nav class="mb-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&period=<?php echo $period; ?>&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&period=<?php echo $period; ?>&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&period=<?php echo $period; ?>&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&period=<?php echo $period; ?>&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $totalPages; ?>&period=<?php echo $period; ?>&sort=<?php echo $sortBy; ?>&dir=<?php echo $sortDir; ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Data Legend -->
            <div class="card bg-light-blue">
                <div class="card-body small">
                    <h6 class="mb-3"><i class="fa-solid fa-circle-info"></i> <strong>Metric Definitions</strong></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Velocity:</strong> How fast product sells. Fast = >2 units/day, Normal = 0.5-2 units/day, Slow = <0.5 units/day</p>
                            <p><strong>Sell-Through %:</strong> Units sold ÷ current stock (higher = more demand than supply)</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Defect Rate:</strong> Warranty claims ÷ units sold (% of products returned)</p>
                            <p><strong>Days Since Sale:</strong> How long since this product was last ordered (>90d = potential dead stock)</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
