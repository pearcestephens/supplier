<?php
/**
 * Dashboard Page - Main Supplier Portal Dashboard
 * Demo-Perfect Implementation with Real Data
 * 
 * @package SupplierPortal
 * @version 3.0.0 - Standardized Architecture
 */

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// ============================================================================
// AUTHENTICATION & MAGIC LINK
// ============================================================================
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    Auth::loginById($_GET['supplier_id']);
}

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// ============================================================================
// NOTIFICATION COUNTS (for sidebar badges)
// ============================================================================
$warrantyClaimsCount = 0;
$pendingOrdersCount = 0;

try {
    $db = db();
    
    $warrantyStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM faulty_products fp
        INNER JOIN vend_products vp ON fp.product_id = vp.id
        WHERE fp.supplier_status = 0 
        AND vp.supplier_id = ?
        AND vp.deleted_at IS NULL
    ");
    $warrantyStmt->bind_param('s', $supplierID);
    $warrantyStmt->execute();
    $warrantyClaimsCount = $warrantyStmt->get_result()->fetch_assoc()['count'] ?? 0;
    $warrantyStmt->close();
    
    $ordersStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM vend_consignments 
        WHERE supplier_id = ? 
        AND state IN ('OPEN', 'SENT', 'RECEIVING') 
        AND deleted_at IS NULL
    ");
    $ordersStmt->bind_param('s', $supplierID);
    $ordersStmt->execute();
    $pendingOrdersCount = $ordersStmt->get_result()->fetch_assoc()['count'] ?? 0;
    $ordersStmt->close();
} catch (Exception $e) {
    error_log('Error loading notification counts: ' . $e->getMessage());
}

// ============================================================================
// PAGE CONFIGURATION
// ============================================================================
$activeTab = 'dashboard';
$pageTitle = 'Dashboard';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - The Vape Shed Supplier Portal</title>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Professional Black Theme - Demo Perfect -->
    <link rel="stylesheet" href="/supplier/assets/css/professional-black.css?v=<?php echo time(); ?>">
    
    <!-- Dashboard Widgets CSS -->
    <link rel="stylesheet" href="/supplier/assets/css/dashboard-widgets.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="page">
    
    <!-- ========================================================================
         SIDEBAR - Loads from component with notification counts
         ======================================================================== -->
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <!-- ========================================================================
         PAGE CONTENT WRAPPER
         ======================================================================== -->
    <div class="page-wrapper">
        
        <!-- ====================================================================
             HEADER TOP - Welcome message and user menu
             ==================================================================== -->
        <?php include __DIR__ . '/components/header-top.php'; ?>
        
        <!-- ====================================================================
             HEADER BOTTOM - Breadcrumb and action buttons
             ==================================================================== -->
        <?php include __DIR__ . '/components/header-bottom.php'; ?>
        
        <!-- ====================================================================
             PAGE BODY - DASHBOARD CONTENT
             ==================================================================== -->
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
                                    <h3 class="mb-0 fw-bold skeleton" id="total-orders">--</h3>
                                </div>
                                <div class="metric-icon bg-primary">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
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
                                    <h3 class="mb-0 fw-bold skeleton" id="active-products">--</h3>
                                </div>
                                <div class="metric-icon bg-info">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
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
                                    <h3 class="mb-0 fw-bold skeleton" id="pending-claims">--</h3>
                                </div>
                                <div class="metric-icon bg-warning">
                                    <i class="fas fa-wrench"></i>
                                </div>
                            </div>
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
                                    <h3 class="mb-0 fw-bold skeleton" id="avg-order-value">--</h3>
                                </div>
                                <div class="metric-icon bg-success">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
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
                                    <h3 class="mb-0 fw-bold skeleton" id="units-sold">--</h3>
                                </div>
                                <div class="metric-icon bg-cyan">
                                    <i class="fas fa-cubes"></i>
                                </div>
                            </div>
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
                                    <h3 class="mb-0 fw-bold skeleton" id="revenue">--</h3>
                                </div>
                                <div class="metric-icon bg-purple">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
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
        
    </div><!-- /.page-wrapper -->
    
</div><!-- /.page -->

<!-- ============================================================================
     JAVASCRIPT LIBRARIES
     ========================================================================== -->
<!-- jQuery 3.6 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5.3 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 3.9.1 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Main App JS -->
<script src="/supplier/assets/js/app.js?v=<?php echo time(); ?>"></script>

<!-- Dashboard JavaScript -->
<script src="/supplier/assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

</body>
</html>
