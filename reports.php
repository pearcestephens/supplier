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

<!-- Reports Specific CSS -->
<link rel="stylesheet" href="/supplier/assets/css/05-reports.css?v=<?php echo time(); ?>">

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar-new.php'; ?>

<!-- Page Header (Fixed Top Bar) -->
<?php include __DIR__ . '/components/page-header.php'; ?>

<!-- Main Content Area -->
<div class="main-content">
    <div class="content-wrapper p-4">

        <!-- Page Title Section -->
        <?php include __DIR__ . '/components/page-title.php'; ?>

<!-- Supplier Reports Page -->
<div class="reports-page">

    <!-- Filter Bar & Export Toolbar -->
    <div class="filter-bar">
        <form method="GET" action="" class="d-flex gap-3 flex-wrap align-items-end w-100">
            <input type="hidden" name="tab" value="reports">
$activeTab = 'reports';
$pageTitle = 'Analytics & Reports';
$pageIcon = 'fa-solid fa-chart-line';
$pageDescription = 'Comprehensive sales analytics and performance insights';
$breadcrumb = [
    ['text' => 'Analytics & Reports', 'href' => '/supplier/reports.php']
];
?>
<?php include __DIR__ . '/components/html-head.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
:root { --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.reports-header { background: var(--gradient-primary); color: white; padding: 2.5rem; margin: -1.5rem -1.5rem 2rem; border-radius: 12px 12px 0 0; text-align: center; }
.reports-header h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
.filter-toolbar { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 2rem; }
.quick-date-btn { padding: 0.4rem 0.8rem; font-size: 0.875rem; border: 1px solid #e5e7eb; background: white; border-radius: 6px; cursor: pointer; transition: all 0.2s; margin: 2px; }
.quick-date-btn:hover { background: #667eea; color: white; }
.kpi-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid; transition: transform 0.2s; }
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.kpi-card.primary { border-left-color: #667eea; }
.kpi-card.success { border-left-color: #10b981; }
.kpi-card.warning { border-left-color: #f59e0b; }
.kpi-card.info { border-left-color: #3b82f6; }
.kpi-value { font-size: 2rem; font-weight: 700; margin: 0.5rem 0 0.25rem; }
.kpi-label { font-size: 0.875rem; font-weight: 600; color: #6b7280; text-transform: uppercase; }
.kpi-meta { font-size: 0.875rem; color: #9ca3af; }
.chart-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: 100%; }
.chart-title { font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; }
.data-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.rank-badge { display: inline-flex; width: 32px; height: 32px; border-radius: 8px; align-items: center; justify-content: center; font-weight: 700; }
.rank-badge.gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.rank-badge.silver { background: linear-gradient(135deg, #d1d5db, #9ca3af); color: white; }
.rank-badge.bronze { background: linear-gradient(135deg, #f97316, #ea580c); color: white; }
.rank-badge.default { background: #f3f4f6; color: #6b7280; }
</style>

<?php include __DIR__ . '/components/sidebar-new.php'; ?>
<?php include __DIR__ . '/components/page-header.php'; ?>

<div class="main-content">
    <div class="content-wrapper p-4">
        
        <div class="reports-header">
            <h1><i class="fas fa-chart-line"></i> Analytics & Reports</h1>
            <p>Comprehensive insights into your sales performance and trends</p>
        </div>

        <div class="filter-toolbar">
            <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
                <div style="flex: 1; min-width: 150px;">
                    <label class="small fw-bold text-muted">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label class="small fw-bold text-muted">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div style="flex: 2;">
                    <label class="small fw-bold text-muted">Quick Select</label>
                    <div>
                        <button type="button" class="quick-date-btn" data-range="today">Today</button>
                        <button type="button" class="quick-date-btn" data-range="week">Week</button>
                        <button type="button" class="quick-date-btn" data-range="month">Month</button>
                        <button type="button" class="quick-date-btn" data-range="quarter">Quarter</button>
                        <button type="button" class="quick-date-btn" data-range="year">Year</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Update</button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </form>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card primary">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value">$<?php echo number_format((float)($performance['total_revenue'] ?? 0), 2); ?></div>
                    <div class="kpi-meta"><?php echo number_format((int)($performance['total_orders'] ?? 0)); ?> orders</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card success">
                    <div class="kpi-label">Units Sold</div>
                    <div class="kpi-value"><?php echo number_format((int)($performance['total_units'] ?? 0)); ?></div>
                    <div class="kpi-meta"><?php echo number_format((int)($performance['unique_products'] ?? 0)); ?> products</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card warning">
                    <div class="kpi-label">Avg Order Value</div>
                    <div class="kpi-value">$<?php echo number_format((float)($performance['avg_order_value'] ?? 0), 2); ?></div>
                    <div class="kpi-meta"><?php echo number_format((int)($performance['unique_stores'] ?? 0)); ?> stores</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card info">
                    <div class="kpi-label">Fulfillment Rate</div>
                    <div class="kpi-value"><?php echo number_format((float)$fulfillmentRate, 1); ?>%</div>
                    <div class="kpi-meta"><?php echo number_format((int)($performance['completed_orders'] ?? 0)); ?> completed</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <h2 class="chart-title"><i class="fas fa-chart-line"></i> Revenue Trend (Last 12 Months)</h2>
                    <canvas id="revenueTrendChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h2 class="chart-title"><i class="fas fa-pie-chart"></i> Order Status</h2>
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="chart-card">
                    <h2 class="chart-title"><i class="fas fa-store"></i> Store Performance</h2>
                    <canvas id="storePerformanceChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <?php if (!empty($topProducts)): ?>
        <div class="data-table mb-4">
            <div class="p-3 bg-light border-bottom">
                <h3 class="h5 mb-0"><i class="fas fa-star text-warning"></i> Top 10 Best Selling Products</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Avg Price</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $p): 
                            $rank = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : 'default'));
                        ?>
                        <tr>
                            <td><div class="rank-badge <?php echo $rank; ?>"><?php echo $i+1; ?></div></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($p['product_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                            <td class="text-center"><span class="badge bg-primary"><?php echo number_format((int)$p['times_ordered']); ?></span></td>
                            <td class="text-end"><?php echo number_format((int)$p['total_quantity']); ?></td>
                            <td class="text-end text-muted">$<?php echo number_format((float)$p['avg_unit_price'], 2); ?></td>
                            <td class="text-end fw-bold text-success">$<?php echo number_format((float)$p['total_revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($storePerformance)): ?>
        <div class="data-table mb-4">
            <div class="p-3 bg-light border-bottom">
                <h3 class="h5 mb-0"><i class="fas fa-store-alt text-info"></i> Store Performance Analysis</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
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
                        <?php foreach ($storePerformance as $s): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($s['store_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($s['outlet_code']); ?></code></td>
                            <td class="text-center"><span class="badge bg-info"><?php echo number_format((int)$s['order_count']); ?></span></td>
                            <td class="text-end"><?php echo number_format((int)$s['total_units']); ?></td>
                            <td class="text-end fw-bold text-success">$<?php echo number_format((float)$s['total_revenue'], 2); ?></td>
                            <td class="text-end text-muted">$<?php echo number_format((float)$s['avg_order_value'], 2); ?></td>
                            <td><small class="text-muted"><?php echo date('M d, Y', strtotime($s['last_order_date'])); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/components/html-footer.php'; ?>

<script>
const reportData = { monthlyTrend: <?php echo json_encode($monthlyTrend); ?>, fulfillmentMetrics: <?php echo json_encode($fulfillmentMetrics); ?>, storePerformance: <?php echo json_encode($storePerformance); ?> };

document.addEventListener('DOMContentLoaded', function() {
    // Revenue Trend
    if (document.getElementById('revenueTrendChart')) {
        const months = reportData.monthlyTrend.map(d => { const [y,m] = d.month.split('-'); return new Date(y, m-1).toLocaleDateString('en', {month:'short',year:'numeric'}); });
        const revenue = reportData.monthlyTrend.map(d => parseFloat(d.revenue || 0));
        new Chart(document.getElementById('revenueTrendChart'), {
            type: 'line',
            data: { labels: months, datasets: [{ label: 'Revenue', data: revenue, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', borderWidth: 3, fill: true, tension: 0.4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {display:false} }, scales: { y: { beginAtZero: true, ticks: { callback: v => '$'+v.toLocaleString() } } } }
        });
    }

    // Order Status
    if (document.getElementById('orderStatusChart')) {
        const labels = reportData.fulfillmentMetrics.map(m => m.state);
        const counts = reportData.fulfillmentMetrics.map(m => parseInt(m.count));
        new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: counts, backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ef4444','#6b7280'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {position:'bottom'} } }
        });
    }

    // Store Performance
    if (document.getElementById('storePerformanceChart') && reportData.storePerformance.length) {
        const stores = reportData.storePerformance.map(s => s.store_name);
        const revenue = reportData.storePerformance.map(s => parseFloat(s.total_revenue || 0));
        new Chart(document.getElementById('storePerformanceChart'), {
            type: 'bar',
            data: { labels: stores, datasets: [{ label: 'Revenue', data: revenue, backgroundColor: 'rgba(102,126,234,0.8)', borderColor: '#667eea', borderWidth: 2, borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {display:false} }, scales: { y: { beginAtZero: true, ticks: { callback: v => '$'+v.toLocaleString() } } } }
        });
    }

    // Quick date buttons
    document.querySelectorAll('.quick-date-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const range = this.dataset.range;
            const today = new Date();
            let start = today;
            switch(range) {
                case 'today': start = today; break;
                case 'week': start = new Date(today.setDate(today.getDate() - 7)); break;
                case 'month': start = new Date(today.getFullYear(), today.getMonth(), 1); break;
                case 'quarter': start = new Date(today.getFullYear(), Math.floor(today.getMonth()/3)*3, 1); break;
                case 'year': start = new Date(today.getFullYear(), 0, 1); break;
            }
            document.querySelector('input[name="start_date"]').value = start.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = new Date().toISOString().split('T')[0];
        });
    });
});
</script>
</body>
</html>
