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
// DATABASE QUERIES - Reports Page Logic
// ============================================================================
$db = db();

if (empty($supplierID)) {
    die('<div class="alert alert-danger">Supplier ID not found in session. Please log in again.</div>');
}

// Date range filter
// FIXED: Added default date values to form inputs and UTC timezone handling
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month (UTC/server time)
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today (UTC/server time)
$reportType = $_GET['report_type'] ?? 'overview';

// Validation: Ensure start_date <= end_date
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// QUERY 1: Overall Performance Stats
$performanceQuery = "
    SELECT
        COUNT(DISTINCT t.id) as total_orders,
        SUM(ti.quantity_sent) as total_units,
        SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
        AVG(ti.quantity_sent * ti.unit_cost) as avg_order_value,
        COUNT(DISTINCT t.outlet_to) as unique_stores,
        COUNT(DISTINCT ti.product_id) as unique_products,
        SUM(CASE WHEN t.state = 'RECEIVED' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN t.state = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_orders,
        AVG(DATEDIFF(t.expected_delivery_date, t.created_at)) as avg_delivery_days
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at BETWEEN ? AND ?
";
$stmt = $db->prepare($performanceQuery);
$stmt->bind_param('sss', $supplierID, $startDate, $endDate);
$stmt->execute();
$performance = $stmt->get_result()->fetch_assoc();
$stmt->close();

// QUERY 2: Monthly Trend Data (Last 12 Months)
$monthlyTrendQuery = "
    SELECT
        DATE_FORMAT(t.created_at, '%Y-%m') as month,
        COUNT(DISTINCT t.id) as order_count,
        SUM(ti.quantity_sent) as units_sold,
        SUM(ti.quantity_sent * ti.unit_cost) as revenue
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY month ASC
";
$stmt = $db->prepare($monthlyTrendQuery);
$stmt->bind_param('s', $supplierID);
$stmt->execute();
$monthlyTrend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// QUERY 3: Top Products (Best Sellers)
$topProductsQuery = "
    SELECT
        p.id,
        p.name as product_name,
        p.sku,
        COUNT(DISTINCT ti.transfer_id) as times_ordered,
        SUM(ti.quantity_sent) as total_quantity,
        SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
        AVG(ti.unit_cost) as avg_unit_price
    FROM vend_consignment_line_items ti
    JOIN vend_consignments t ON ti.transfer_id = t.id
    JOIN vend_products p ON ti.product_id = p.id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at BETWEEN ? AND ?
    GROUP BY ti.product_id
    ORDER BY total_revenue DESC
    LIMIT 10
";
$stmt = $db->prepare($topProductsQuery);
$stmt->bind_param('sss', $supplierID, $startDate, $endDate);
$stmt->execute();
$topProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// QUERY 4: Store Performance Analysis
$storePerformanceQuery = "
    SELECT
        o.id,
        o.name as store_name,
        o.store_code as outlet_code,
        COUNT(DISTINCT t.id) as order_count,
        SUM(ti.quantity_sent) as total_units,
        SUM(ti.quantity_sent * ti.unit_cost) as total_revenue,
        AVG(ti.quantity_sent * ti.unit_cost) as avg_order_value,
        MAX(t.created_at) as last_order_date
    FROM vend_consignments t
    LEFT JOIN vend_consignment_line_items ti ON t.id = ti.transfer_id
    LEFT JOIN vend_outlets o ON t.outlet_to = o.id
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at BETWEEN ? AND ?
    GROUP BY t.outlet_to
    ORDER BY total_revenue DESC
";
$stmt = $db->prepare($storePerformanceQuery);
$stmt->bind_param('sss', $supplierID, $startDate, $endDate);
$stmt->execute();
$storePerformance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// QUERY 5: Fulfillment Metrics
$fulfillmentQuery = "
    SELECT
        t.state,
        COUNT(*) as count,
        AVG(DATEDIFF(
            COALESCE(t.expected_delivery_date, NOW()),
            t.created_at
        )) as avg_days
    FROM vend_consignments t
    WHERE t.supplier_id = ?
      AND t.transfer_category = 'PURCHASE_ORDER'
      AND t.deleted_at IS NULL
      AND t.created_at BETWEEN ? AND ?
    GROUP BY t.state
";
$stmt = $db->prepare($fulfillmentQuery);
$stmt->bind_param('sss', $supplierID, $startDate, $endDate);
$stmt->execute();
$fulfillmentMetrics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate fulfillment rate
$totalOrdersInPeriod = array_sum(array_column($fulfillmentMetrics, 'count'));
$completedOrdersInPeriod = 0;
foreach ($fulfillmentMetrics as $metric) {
    if (in_array($metric['state'], ['RECEIVED', 'CLOSED'])) {
        $completedOrdersInPeriod += $metric['count'];
    }
}
$fulfillmentRate = $totalOrdersInPeriod > 0 ? ($completedOrdersInPeriod / $totalOrdersInPeriod) * 100 : 0;

$activeTab = 'reports';
$pageTitle = 'Reports';
$pageIcon = 'fa-solid fa-chart-bar';
$pageDescription = 'Analyze my sales, fulfillment, and product performance with The Vape Shed';
$breadcrumb = [
    ['text' => 'Reports', 'href' => '/supplier/reports.php']
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

<!-- Supplier Reports Page -->
<div class="reports-page">

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="reports">

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="overview" <?php echo $reportType === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Analysis</option>
                        <option value="stores" <?php echo $reportType === 'stores' ? 'selected' : ''; ?>>Store Performance</option>
                        <option value="fulfillment" <?php echo $reportType === 'fulfillment' ? 'selected' : ''; ?>>Fulfillment Metrics</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync"></i> Update Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="row g-4 mb-4">

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-left-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold mb-1">Total Revenue</div>
                            <h3 class="mb-0 text-primary">$<?php echo number_format((float)($performance['total_revenue'] ?? 0), 2); ?></h3>
                            <small class="text-muted"><?php echo number_format((int)($performance['total_orders'] ?? 0)); ?> orders</small>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-left-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold mb-1">Units Sold</div>
                            <h3 class="mb-0 text-success"><?php echo number_format((int)($performance['total_units'] ?? 0)); ?></h3>
                            <small class="text-muted"><?php echo number_format((int)($performance['unique_products'] ?? 0)); ?> products</small>
                        </div>
                        <i class="fas fa-box fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-left-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold mb-1">Avg Order Value</div>
                            <h3 class="mb-0 text-info">$<?php echo number_format((float)($performance['avg_order_value'] ?? 0), 2); ?></h3>
                            <small class="text-muted"><?php echo number_format((int)($performance['unique_stores'] ?? 0)); ?> stores served</small>
                        </div>
                        <i class="fas fa-chart-line fa-2x text-info opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-left-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold mb-1">Fulfillment Rate</div>
                            <h3 class="mb-0 text-warning"><?php echo number_format((float)$fulfillmentRate, 1); ?>%</h3>
                            <small class="text-muted"><?php echo number_format((int)($performance['completed_orders'] ?? 0)); ?> completed</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">

        <!-- Monthly Revenue Trend -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> Revenue Trend (Last 12 Months)</h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Fulfillment Status Breakdown -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-pie-chart"></i> Order Status</h6>
                </div>
                <div class="card-body">
                    <canvas id="statusBreakdownChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Top Products Table -->
    <?php if (!empty($topProducts)): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-star"></i> Top 10 Best Selling Products</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Qty Sold</th>
                            <th class="text-end">Avg Price</th>
                            <th class="text-end">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $index => $product): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                                <td class="text-center"><?php echo number_format((int)$product['times_ordered']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$product['total_quantity']); ?></td>
                                <td class="text-end">$<?php echo number_format((float)$product['avg_unit_price'], 2); ?></td>
                                <td class="text-end fw-bold text-success">$<?php echo number_format((float)$product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Store Performance Table -->
    <?php if (!empty($storePerformance)): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-store"></i> Store Performance Analysis</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Store Name</th>
                            <th>Code</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Units</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Avg Order</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($storePerformance as $store): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($store['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($store['outlet_code']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo number_format((int)$store['order_count']); ?></span>
                                </td>
                                <td class="text-end"><?php echo number_format((int)$store['total_units']); ?></td>
                                <td class="text-end fw-bold text-success">$<?php echo number_format((float)$store['total_revenue'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format((float)$store['avg_order_value'], 2); ?></td>
                                <td>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($store['last_order_date'])); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid #3b82f6 !important;
}

.border-left-success {
    border-left: 4px solid #10b981 !important;
}

.border-left-info {
    border-left: 4px solid #06b6d4 !important;
}

.border-left-warning {
    border-left: 4px solid #f59e0b !important;
}
</style>

    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Reports JavaScript -->
<script src="/supplier/assets/js/reports.js?v=<?php echo time(); ?>"></script>

</body>
</html>
