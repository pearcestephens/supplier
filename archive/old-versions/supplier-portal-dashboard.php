<?php
/**
 * The Vape Shed - Supplier Portal Dashboard
 * Main dashboard with integrated menu component and branding
 * 
 * @file supplier-portal-dashboard.php
 * @purpose Complete supplier dashboard with The Vape Shed branding
 * @author Pearce Stephens
 * @last_modified 2025-10-07
 */

$pageTitle = 'Supplier Dashboard - The Vape Shed';
$supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 12345;

// Include the updated header with logo and menu
include_once 'supplier-header-updated.php';
?>

<!-- SUPPLIER DASHBOARD CONTENT -->
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tachometer-alt text-primary mr-2"></i>
                Supplier Dashboard
            </h1>
            <p class="mb-0 text-muted">Welcome to The Vape Shed Supplier Portal</p>
        </div>
        <div class="col-lg-4 text-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STATS CARDS ROW -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Total Products</div>
                    <div class="stats-number">1,247</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-box fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card success">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Active Orders</div>
                    <div class="stats-number">18</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-shopping-cart fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card warning">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Low Stock Items</div>
                    <div class="stats-number">23</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="stats-label">Monthly Revenue</div>
                    <div class="stats-number">$45.2K</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-dollar-sign fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT ROW -->
<div class="row">
    <!-- RECENT ORDERS -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list mr-2"></i>Recent Orders
                </h6>
                <a href="/orders" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>#VS2025-001</strong></td>
                                <td>Oct 7, 2025</td>
                                <td>15 items</td>
                                <td>$2,847.50</td>
                                <td><span class="badge badge-success">Shipped</span></td>
                                <td>
                                    <a href="/order-details?id=001" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#VS2025-002</strong></td>
                                <td>Oct 6, 2025</td>
                                <td>8 items</td>
                                <td>$1,235.00</td>
                                <td><span class="badge badge-warning">Processing</span></td>
                                <td>
                                    <a href="/order-details?id=002" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#VS2025-003</strong></td>
                                <td>Oct 5, 2025</td>
                                <td>25 items</td>
                                <td>$4,892.75</td>
                                <td><span class="badge badge-info">Confirmed</span></td>
                                <td>
                                    <a href="/order-details?id=003" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS & ALERTS -->
    <div class="col-lg-4 mb-4">
        <!-- QUICK ACTIONS -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bolt mr-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/products/add" class="btn btn-primary btn-block mb-2">
                        <i class="fas fa-plus mr-2"></i>Add New Product
                    </a>
                    <a href="/orders/create" class="btn btn-success btn-block mb-2">
                        <i class="fas fa-shopping-cart mr-2"></i>Create Order
                    </a>
                    <a href="/inventory" class="btn btn-info btn-block mb-2">
                        <i class="fas fa-warehouse mr-2"></i>Check Inventory
                    </a>
                    <a href="/reports" class="btn btn-warning btn-block">
                        <i class="fas fa-chart-bar mr-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- SYSTEM ALERTS -->
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bell mr-2"></i>System Alerts
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-2">
                    <small><i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Low Stock:</strong> 23 products need restocking
                    </small>
                </div>
                <div class="alert alert-info mb-2">
                    <small><i class="fas fa-info-circle mr-1"></i>
                    <strong>Update:</strong> New pricing structure effective Oct 15
                    </small>
                </div>
                <div class="alert alert-success mb-0">
                    <small><i class="fas fa-check-circle mr-1"></i>
                    <strong>Success:</strong> 5 orders shipped today
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-line mr-2"></i>Sales Performance
                </h6>
            </div>
            <div class="card-body">
                <canvas id="salesChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-pie mr-2"></i>Product Categories
                </h6>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- DASHBOARD JAVASCRIPT -->
<script>
$(document).ready(function() {
    // Set active menu item
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('dashboard');
    }
    
    // Initialize dashboard
    initDashboard();
    
    function initDashboard() {
        // Show welcome notification
        setTimeout(function() {
            showSupplierNotification('Welcome to The Vape Shed Supplier Portal!', 'success');
        }, 1000);
        
        // Initialize charts
        initCharts();
        
        // Auto-refresh stats every 5 minutes
        setInterval(refreshStats, 300000);
    }
    
    function initCharts() {
        // Sales Performance Chart
        var salesCtx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Sales ($)',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true
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
                }
            }
        });
        
        // Category Chart
        var categoryCtx = document.getElementById('categoryChart').getContext('2d');
        var categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['E-Liquids', 'Devices', 'Accessories', 'Coils', 'Other'],
                datasets: [{
                    data: [45, 25, 15, 10, 5],
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

function refreshDashboard() {
    Pace.restart();
    showSupplierNotification('Dashboard refreshed successfully!', 'info');
    
    // Refresh stats
    refreshStats();
}

function refreshStats() {
    // Simulate API call to refresh statistics
    console.log('Refreshing dashboard statistics...');
    
    // Update badges if needed
    if (typeof updateMenuBadge === 'function') {
        updateMenuBadge('orders', 5);
        updateMenuBadge('products', 128);
    }
}
</script>

<!-- Chart.js for dashboard charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
// Include the updated footer
include_once 'supplier-footer-updated.php';
?>