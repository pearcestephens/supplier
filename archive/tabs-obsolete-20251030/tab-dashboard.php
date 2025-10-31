<?php
/**
 * Dashboard Tab - Demo-Perfect Implementation
 * Exact 1:1 replication of demo/index.html with real data
 *
 * @package Supplier\Portal
 * @version 4.0.0 - Demo Migration Complete
 */

declare(strict_types=1);

if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}
?>

<!-- Chart.js 3.9.1 (Same as demo) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Page Body -->
<div class="page-body">

    <!-- METRIC CARDS - 6 Key Performance Indicators -->
    <div class="row g-3 mb-4">

        <!-- Card 1: Total Orders (30d) -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Total Orders (30d)</p>
                            <h3 class="mb-0 fw-bold" id="metric-total-orders">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-primary">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" id="metric-total-orders-progress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-total-orders-change">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 2: Active Products -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Active Products</p>
                            <h3 class="mb-0 fw-bold" id="metric-active-products">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-info">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center" id="metric-products-details">
                        <span class="small text-muted">Loading...</span>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-products-availability">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 3: Pending Claims -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card clickable" style="cursor: pointer;" onclick="window.location.href='/supplier/warranty.php'">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Pending Claims</p>
                            <h3 class="mb-0 fw-bold" id="metric-pending-claims">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-warning">
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                    <div class="d-flex gap-2" id="metric-claims-badges">
                        <span class="badge bg-secondary">Loading...</span>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-claims-alert">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 4: Average Order Value -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Avg Order Value</p>
                            <h3 class="mb-0 fw-bold" id="metric-avg-value">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" id="metric-avg-value-progress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-avg-value-change">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 5: Units Sold (30d) -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Units Sold (30d)</p>
                            <h3 class="mb-0 fw-bold" id="metric-units-sold">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-cyan">
                            <i class="fas fa-cubes"></i>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-cyan" id="metric-units-sold-progress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-units-sold-change">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 6: Revenue (30d) -->
        <div class="col-md-6 col-xl-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <p class="text-muted mb-1 small">Revenue (30d)</p>
                            <h3 class="mb-0 fw-bold" id="metric-revenue">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </h3>
                        </div>
                        <div class="metric-icon bg-purple">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center" id="metric-revenue-details">
                        <span class="small text-muted">Loading...</span>
                    </div>
                    <p class="mb-0 small mt-2" id="metric-revenue-change">
                        <span class="text-muted">Loading...</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ORDERS REQUIRING ACTION - Compact Table -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Orders Requiring Action</h5>
                        <small class="text-muted">Processing & Packing Required (<span id="orders-total-count">...</span> total orders)</small>
                    </div>
                    <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-success" onclick="window.location.href='/supplier/api/export-orders.php'">
                            <i class="fa-solid fa-file-archive"></i>
                            Download All CSV
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="window.print()">
                            <i class="fa-solid fa-print"></i>
                            Print Dashboard
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover compact-table mb-0">
                            <thead class="table-header-sticky">
                                <tr>
                                    <th style="width: 100px;">PO Number</th>
                                    <th style="width: 120px;">Outlet</th>
                                    <th style="width: 90px;">Status</th>
                                    <th style="width: 80px;" class="text-center">Items</th>
                                    <th style="width: 80px;" class="text-center">Units</th>
                                    <th style="width: 90px;" class="text-end">Value</th>
                                    <th style="width: 100px;">Order Date</th>
                                    <th style="width: 100px;">Due Date</th>
                                    <th style="width: 140px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table-body">
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="text-muted mt-2 mb-0">Loading orders...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white" id="orders-pagination">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Loading pagination...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STOCK ALERTS - Grid of Store Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            Stock Alerts - Low Inventory by Store
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Click any store to see which products need restocking</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light">
                            <i class="fas fa-filter me-1"></i>
                            Filter
                        </button>
                        <button class="btn btn-sm btn-warning">
                            <i class="fas fa-bell me-1"></i>
                            Set Alerts (<span id="alerts-count">...</span>)
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="stock-alerts-grid" id="stock-alerts-grid">
                        <div class="text-center py-4">
                            <div class="spinner-border" role="status"></div>
                            <p class="text-muted mt-2">Loading stock alerts...</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Showing stores with 1,000+ low stock items • Last updated <span id="alerts-last-updated">...</span>
                        </span>
                        <a href="#" class="btn btn-sm btn-primary">
                            View All <span id="alerts-total-stores">27</span> Stores
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ANALYTICS CHARTS - Items Sold & Warranty Claims -->
    <div class="row g-3 mb-4">
        <!-- Items Sold Last 3 Months -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Items Sold (Past 3 Months)</h5>
                    <small class="text-muted">Monthly unit sales trend</small>
                </div>
                <div class="card-body">
                    <canvas id="itemsSoldChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- Warranty Claims Trend -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Warranty Claims Trend</h5>
                    <small class="text-muted">Last 6 months resolution status</small>
                </div>
                <div class="card-body">
                    <canvas id="warrantyChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.page-body -->
