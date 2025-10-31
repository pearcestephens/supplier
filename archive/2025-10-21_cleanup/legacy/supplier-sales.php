<?php
/**
 * The Vape Shed - Supplier Portal Sales Dashboard
 * Sales analytics and management with integrated template and branding
 * 
 * @file supplier-sales.php
 * @purpose Supplier sales management and analytics interface
 * @author Pearce Stephens
 * @last_modified 2025-10-07
 */

$pageTitle = 'Sales Management - The Vape Shed Supplier Portal';
$supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 12345;

// Include the updated header with logo and menu
include_once 'supplier-header-updated.php';
?>

<!-- SALES DASHBOARD CONTENT -->
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-chart-line text-primary mr-2"></i>
                Sales Management
            </h1>
            <p class="mb-0 text-muted">Track and analyze your sales performance with The Vape Shed</p>
        </div>
        <div class="col-lg-4 text-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" onclick="refreshSalesData()">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" onclick="exportSalesReport()">
                    <i class="fas fa-download mr-1"></i> Export Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SALES STATS ROW -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Total Sales</div>
                    <div class="stats-number">$127.5K</div>
                    <div class="text-success small">
                        <i class="fas fa-arrow-up mr-1"></i>12.5% vs last month
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-dollar-sign fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Orders</div>
                    <div class="stats-number">2,847</div>
                    <div class="text-success small">
                        <i class="fas fa-arrow-up mr-1"></i>8.2% vs last month
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Average Order</div>
                    <div class="stats-number">$44.78</div>
                    <div class="text-warning small">
                        <i class="fas fa-arrow-down mr-1"></i>2.1% vs last month
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-calculator fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Top Product</div>
                    <div class="stats-number" style="font-size: 1.2rem;">SMOK Nord 4</div>
                    <div class="text-info small">
                        487 units sold
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-trophy fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DATE RANGE FILTER -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form id="salesFilterForm" class="row align-items-end">
                    <div class="col-md-3">
                        <label for="startDate">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="startDate" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="endDate">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="endDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="productCategory">Category</label>
                        <select class="form-control" id="productCategory" name="category">
                            <option value="">All Categories</option>
                            <option value="devices">Devices</option>
                            <option value="eliquids">E-Liquids</option>
                            <option value="accessories">Accessories</option>
                            <option value="coils">Coils</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search mr-1"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-area mr-2"></i>Sales Trend
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" onclick="changeChartPeriod('7days')">7 Days</button>
                    <button type="button" class="btn btn-outline-primary" onclick="changeChartPeriod('30days')">30 Days</button>
                    <button type="button" class="btn btn-outline-primary" onclick="changeChartPeriod('90days')">90 Days</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-pie mr-2"></i>Sales by Category
                </h6>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TOP PRODUCTS TABLE -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-star mr-2"></i>Top Selling Products
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Units</th>
                                <th>Revenue</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>SMOK Nord 4 Kit</strong></td>
                                <td>487</td>
                                <td>$21,915</td>
                                <td><span class="text-success"><i class="fas fa-arrow-up"></i> 15%</span></td>
                            </tr>
                            <tr>
                                <td><strong>Vaporesso Gen S</strong></td>
                                <td>342</td>
                                <td>$30,780</td>
                                <td><span class="text-success"><i class="fas fa-arrow-up"></i> 8%</span></td>
                            </tr>
                            <tr>
                                <td><strong>Geekvape Aegis</strong></td>
                                <td>298</td>
                                <td>$35,760</td>
                                <td><span class="text-danger"><i class="fas fa-arrow-down"></i> 3%</span></td>
                            </tr>
                            <tr>
                                <td><strong>Lost Vape Orion</strong></td>
                                <td>245</td>
                                <td>$19,600</td>
                                <td><span class="text-success"><i class="fas fa-arrow-up"></i> 22%</span></td>
                            </tr>
                            <tr>
                                <td><strong>Voopoo Drag X</strong></td>
                                <td>198</td>
                                <td>$11,880</td>
                                <td><span class="text-warning"><i class="fas fa-minus"></i> 0%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-store mr-2"></i>Sales by Store
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Auckland Central</strong></td>
                                <td>1,247</td>
                                <td>$56,115</td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: 44%"></div>
                                    </div>
                                    <small>44%</small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Wellington CBD</strong></td>
                                <td>892</td>
                                <td>$40,140</td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: 31%"></div>
                                    </div>
                                    <small>31%</small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Christchurch</strong></td>
                                <td>456</td>
                                <td>$20,520</td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: 16%"></div>
                                    </div>
                                    <small>16%</small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Hamilton</strong></td>
                                <td>252</td>
                                <td>$11,340</td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: 9%"></div>
                                    </div>
                                    <small>9%</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RECENT TRANSACTIONS -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-receipt mr-2"></i>Recent Transactions
                </h6>
                <a href="/transactions" class="btn btn-sm btn-primary">View All Transactions</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Store</th>
                                <th>Customer</th>
                                <th>Products</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>TXN-2025-10001</strong></td>
                                <td>Oct 7, 2025 2:45 PM</td>
                                <td>Auckland Central</td>
                                <td>John Smith</td>
                                <td>SMOK Nord 4, E-Liquid 30ml</td>
                                <td>$67.99</td>
                                <td><span class="badge badge-info">Card</span></td>
                                <td><span class="badge badge-success">Complete</span></td>
                            </tr>
                            <tr>
                                <td><strong>TXN-2025-10002</strong></td>
                                <td>Oct 7, 2025 2:32 PM</td>
                                <td>Wellington CBD</td>
                                <td>Sarah Johnson</td>
                                <td>Vaporesso Gen S Kit</td>
                                <td>$89.99</td>
                                <td><span class="badge badge-secondary">Cash</span></td>
                                <td><span class="badge badge-success">Complete</span></td>
                            </tr>
                            <tr>
                                <td><strong>TXN-2025-10003</strong></td>
                                <td>Oct 7, 2025 2:18 PM</td>
                                <td>Christchurch</td>
                                <td>Mike Wilson</td>
                                <td>Coil Pack x5, Battery</td>
                                <td>$45.50</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td><span class="badge badge-warning">Processing</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SALES JAVASCRIPT -->
<script>
$(document).ready(function() {
    // Set active menu item
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('sales');
    }
    
    // Initialize sales dashboard
    initSalesDashboard();
    
    function initSalesDashboard() {
        // Initialize charts
        initSalesCharts();
        
        // Initialize tables
        $('#transactionsTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [7] }
            ]
        });
        
        // Filter form handler
        $('#salesFilterForm').on('submit', function(e) {
            e.preventDefault();
            filterSalesData();
        });
        
        // Auto-refresh every 5 minutes
        setInterval(refreshSalesData, 300000);
    }
    
    function initSalesCharts() {
        // Sales Trend Chart
        var trendCtx = document.getElementById('salesTrendChart').getContext('2d');
        window.salesTrendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: ['Oct 1', 'Oct 2', 'Oct 3', 'Oct 4', 'Oct 5', 'Oct 6', 'Oct 7'],
                datasets: [{
                    label: 'Daily Sales ($)',
                    data: [4200, 3800, 5100, 4600, 5400, 4900, 5200],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Category Chart
        var categoryCtx = document.getElementById('categoryChart').getContext('2d');
        var categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Devices', 'E-Liquids', 'Accessories', 'Coils', 'Other'],
                datasets: [{
                    data: [42, 28, 15, 10, 5],
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#f39c12',
                        '#e74c3c',
                        '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});

function refreshSalesData() {
    console.log('Refreshing sales data...');
    Pace.restart();
    
    // Simulate API call
    setTimeout(function() {
        showSupplierNotification('Sales data refreshed successfully!', 'success');
    }, 1500);
}

function filterSalesData() {
    var formData = $('#salesFilterForm').serialize();
    console.log('Filtering sales data with:', formData);
    
    // Simulate API call
    showSupplierNotification('Sales data filtered successfully!', 'info');
    
    // Update charts with filtered data
    updateChartsWithFilter();
}

function changeChartPeriod(period) {
    console.log('Changing chart period to:', period);
    
    // Update button states
    $('.btn-group button').removeClass('active');
    event.target.classList.add('active');
    
    // Update chart data based on period
    var chartData = {
        '7days': {
            labels: ['Oct 1', 'Oct 2', 'Oct 3', 'Oct 4', 'Oct 5', 'Oct 6', 'Oct 7'],
            data: [4200, 3800, 5100, 4600, 5400, 4900, 5200]
        },
        '30days': {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            data: [28400, 31200, 29800, 33600]
        },
        '90days': {
            labels: ['Month 1', 'Month 2', 'Month 3'],
            data: [95400, 102300, 127500]
        }
    };
    
    // Update the chart
    if (window.salesTrendChart) {
        window.salesTrendChart.data.labels = chartData[period].labels;
        window.salesTrendChart.data.datasets[0].data = chartData[period].data;
        window.salesTrendChart.update();
    }
    
    showSupplierNotification('Chart updated to show ' + period.replace('days', ' days'), 'info');
}

function updateChartsWithFilter() {
    // This would normally fetch filtered data from API
    console.log('Updating charts with filter data...');
}

function exportSalesReport() {
    console.log('Exporting sales report...');
    showSupplierNotification('Sales report export started. Download will begin shortly.', 'info');
    
    // Simulate export
    setTimeout(function() {
        showSupplierNotification('Sales report exported successfully!', 'success');
    }, 2000);
}
</script>

<!-- Chart.js for sales charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<?php
// Include the updated footer
include_once 'supplier-footer-updated.php';
?>