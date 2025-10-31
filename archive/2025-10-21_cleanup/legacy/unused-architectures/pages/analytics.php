<?php
/**
 * Analytics Dashboard Page
 * 
 * Sales analytics, charts, and reports
 */

// Log page view
log_supplier_activity($conn, $supplier_id, 'view_analytics');

// Get date range filter
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$days = in_array($days, [7, 30, 90, 365]) ? $days : 30;

// Get top products for period
$top_products = get_top_selling_products($conn, $supplier_id, 20, $days);

// Get sales trend data
$trend_sql = "SELECT 
                DATE(s.sale_date) as sale_day,
                COUNT(DISTINCT s.id) as transaction_count,
                SUM(sli.quantity) as units_sold,
                SUM(sli.total_price) as revenue
              FROM vend_sales s
              JOIN vend_sales_line_items sli ON s.id = sli.sale_id
              JOIN vend_products p ON sli.product_id = p.id
              WHERE p.supplier_id = ?
              AND s.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND s.status = 'CLOSED'
              AND sli.is_return = 0
              GROUP BY DATE(s.sale_date)
              ORDER BY sale_day ASC";

$trend_stmt = $conn->prepare($trend_sql);
$trend_stmt->bind_param('si', $supplier_id, $days);
$trend_stmt->execute();
$trend_data = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trend_stmt->close();

// Calculate totals
$total_revenue = 0;
$total_units = 0;
$total_transactions = 0;

foreach ($trend_data as $day) {
    $total_revenue += $day['revenue'];
    $total_units += $day['units_sold'];
    $total_transactions += $day['transaction_count'];
}

// Get stock levels summary
$stock_sql = "SELECT 
                p.id, p.name, p.sku,
                SUM(i.count) as total_stock,
                o.name as outlet_name
              FROM vend_products p
              LEFT JOIN vend_inventory i ON p.id = i.product_id AND i.deleted_at IS NULL
              LEFT JOIN vend_outlets o ON i.outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
              WHERE p.supplier_id = ?
              AND p.deleted_at = '0000-00-00 00:00:00'
              GROUP BY p.id
              ORDER BY total_stock ASC
              LIMIT 20";

$stock_stmt = $conn->prepare($stock_sql);
$stock_stmt->bind_param('s', $supplier_id);
$stock_stmt->execute();
$low_stock_products = $stock_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stock_stmt->close();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Sales Analytics</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Home</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Date Range Filter -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group float-right">
                    <a href="?page=analytics&days=7" class="btn btn-<?php echo $days === 7 ? 'primary' : 'default'; ?>">
                        Last 7 Days
                    </a>
                    <a href="?page=analytics&days=30" class="btn btn-<?php echo $days === 30 ? 'primary' : 'default'; ?>">
                        Last 30 Days
                    </a>
                    <a href="?page=analytics&days=90" class="btn btn-<?php echo $days === 90 ? 'primary' : 'default'; ?>">
                        Last 90 Days
                    </a>
                    <a href="?page=analytics&days=365" class="btn btn-<?php echo $days === 365 ? 'primary' : 'default'; ?>">
                        Last Year
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo format_currency($total_revenue); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($total_units); ?></h3>
                        <p>Units Sold</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($total_transactions); ?></h3>
                        <p>Transactions</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $total_transactions > 0 ? format_currency($total_revenue / $total_transactions) : '$0.00'; ?></h3>
                        <p>Avg Transaction</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i> Sales Trend (Last <?php echo $days; ?> Days)
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products & Stock Levels -->
        <div class="row">
            <!-- Top Selling Products -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-trophy"></i> Top 20 Selling Products
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="text-right">Units</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $index => $product): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <span class="badge badge-<?php echo ['warning', 'secondary', 'info'][$index]; ?>">
                                                    #<?php echo $index + 1; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $index + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        </td>
                                        <td class="text-right"><?php echo number_format($product['units_sold']); ?></td>
                                        <td class="text-right"><?php echo format_currency($product['revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-right">Stock Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        </td>
                                        <td class="text-right">
                                            <?php 
                                            $stock = $product['total_stock'] ?? 0;
                                            $badge_class = $stock == 0 ? 'danger' : ($stock < 5 ? 'warning' : 'success');
                                            ?>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo number_format($stock); ?> units
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Prepare chart data
const chartLabels = <?php echo json_encode(array_column($trend_data, 'sale_day')); ?>;
const chartRevenue = <?php echo json_encode(array_column($trend_data, 'revenue')); ?>;
const chartUnits = <?php echo json_encode(array_column($trend_data, 'units_sold')); ?>;

// Create sales chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Revenue ($)',
            data: chartRevenue,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Units Sold',
            data: chartUnits,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Revenue ($)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Units Sold'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>
