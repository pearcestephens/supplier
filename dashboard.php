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

// Allow magic link login via supplier_id
if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
    $supplierID = $_GET['supplier_id'];
    Auth::loginById($supplierID);
}

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// Notification counts (best-effort - MySQLi legacy helper)
$warrantyClaimsCount = 0;
$pendingOrdersCount = 0;
try {
    $db = db();
    $warrantyStmt = $db->prepare("SELECT COUNT(*) as count FROM faulty_products fp INNER JOIN vend_products vp ON fp.product_id = vp.id WHERE fp.supplier_status = 0 AND vp.supplier_id = ? AND vp.deleted_at IS NULL");
    $warrantyStmt->bind_param('s', $supplierID);
    $warrantyStmt->execute();
    $warrantyClaimsCount = $warrantyStmt->get_result()->fetch_assoc()['count'] ?? 0;
    $warrantyStmt->close();

    $ordersStmt = $db->prepare("SELECT COUNT(*) as count FROM vend_consignments WHERE supplier_id = ? AND state IN ('OPEN', 'SENT', 'RECEIVING') AND deleted_at IS NULL");
    $ordersStmt->bind_param('s', $supplierID);
    $ordersStmt->execute();
    $pendingOrdersCount = $ordersStmt->get_result()->fetch_assoc()['count'] ?? 0;
    $ordersStmt->close();
} catch (Exception $e) {
    error_log('Error loading notification counts: ' . $e->getMessage());
}

$activeTab = 'dashboard';
$pageTitle = 'Dashboard';
$pageIcon = 'fa-solid fa-chart-line';
$pageDescription = 'Track my orders, shipments, and performance supplying The Vape Shed';
$breadcrumb = []; // Dashboard is home, no additional breadcrumb needed
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

            <!-- METRIC CARDS - 6 Key Performance Indicators - ENHANCED FLIP CARDS -->
            <div class="row g-3 mb-4">

                <!-- Card 1: Total Orders (30d) -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-cyan">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Total Orders</div>
                                        <div class="card-stats">
                                            <span class="stat-badge success" id="metric-total-orders-change">✓ +12%</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-shopping-cart"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value-row">
                                        <div class="card-value" id="metric-total-orders">18</div>
                                    </div>
                                    <div class="card-subtitle">Last 30 days</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 65%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-1"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">📈</div>
                                    <h3>Order Velocity</h3>
                                    <p id="insight-1">18 orders trending up by 12%! Excellent momentum.</p>
                                    <div class="flip-back-action">→ View Details</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Active Products -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-gold">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Active Products</div>
                                        <div class="card-stats">
                                            <span class="stat-badge info" id="metric-products-availability">ℹ 100% Ready</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-box"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value-row">
                                        <div class="card-value" id="metric-active-products">50</div>
                                    </div>
                                    <div class="card-subtitle">In stock</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-2"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">🌟</div>
                                    <h3>Top Performer</h3>
                                    <p id="insight-2">Premium Vape Kit - 12 orders this month!</p>
                                    <div class="flip-back-action">→ View Stock</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Pending Claims -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-lime">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Pending Claims</div>
                                        <div class="card-stats">
                                            <span class="stat-badge success" id="metric-claims-alert">✓ Excellent</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-wrench"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value" id="metric-pending-claims">0</div>
                                    <div class="card-subtitle">Open claims</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 2%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-3"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">🏆</div>
                                    <h3>Quality Score</h3>
                                    <p id="insight-3">Exceptional! Only 2% claim rate. Top 5% of suppliers!</p>
                                    <div class="flip-back-action">→ View Details</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Average Order Value -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-magenta">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Avg Order Value</div>
                                        <div class="card-stats">
                                            <span class="stat-badge info" id="metric-avg-value-change">ℹ Steady</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value" id="metric-avg-value">$0</div>
                                    <div class="card-subtitle">Per order</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 45%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-4"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">📊</div>
                                    <h3>Revenue Forecast</h3>
                                    <p id="insight-4">Projected $X,XXX next month based on trends. On track!</p>
                                    <div class="flip-back-action">→ View Forecast</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Units Sold (30d) -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-violet">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Units Sold</div>
                                        <div class="card-stats">
                                            <span class="stat-badge warning" id="metric-units-sold-change">⚠ -5%</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-cubes"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value" id="metric-units-sold">0</div>
                                    <div class="card-subtitle">Last 30 days</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 38%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-5"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">⚡</div>
                                    <h3>Activity Level</h3>
                                    <p id="insight-5">45 active days this quarter. Highly engaged partner!</p>
                                    <div class="flip-back-action">→ View Activity</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Revenue (30d) -->
                <div class="col-md-6 col-xl-4">
                    <div class="flip-card-container">
                        <div class="flip-card">
                            <!-- FRONT -->
                            <div class="flip-front color-coral">
                                <div class="card-header">
                                    <div>
                                        <div class="card-label">Inventory Value</div>
                                        <div class="card-stats">
                                            <span class="stat-badge success" id="metric-revenue-change">✓ Supply Price</span>
                                        </div>
                                    </div>
                                    <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                                </div>
                                <div class="card-content">
                                    <div class="card-value" id="metric-revenue">$0</div>
                                    <div class="card-subtitle">Total In Stock</div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="card-chart">
                                    <canvas id="chart-6"></canvas>
                                </div>
                            </div>
                            <!-- BACK -->
                            <div class="flip-back">
                                <div class="flip-back-content">
                                    <div class="emoji-icon">💡</div>
                                    <h3>You're Crushing It!</h3>
                                    <p id="insight-6">Top 10% of suppliers! Your consistency is unmatched. 🚀</p>
                                    <div class="flip-back-action">→ View Goals</div>
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
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-check me-2 text-primary"></i>
                                    Your Orders Awaiting Action
                                </h5>
                                <small class="text-muted">
                                    <span id="orders-total-count">...</span> orders ready for processing, packing & dispatch
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-success" id="btn-download-csv" disabled>
                                    <i class="fas fa-download me-1"></i>
                                    CSV
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-download-pdf" disabled>
                                    <i class="fas fa-file-pdf me-1"></i>
                                    PDF
                                </button>
                            </div>
                        </div>

                        <!-- Bulk Actions Bar -->
                        <div class="card-body bg-light border-bottom py-2" id="bulk-actions-bar" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary me-2" id="selected-count">0 selected</span>
                                    <small class="text-muted">Shift+Click for range | Ctrl+Click for multiple</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" id="btn-quick-view">
                                        <i class="fas fa-eye me-1"></i>
                                        Quick View
                                    </button>
                                    <button class="btn btn-outline-success" id="btn-bulk-packing-slip">
                                        <i class="fas fa-clipboard-list me-1"></i>
                                        Packing Slips
                                    </button>
                                    <button class="btn btn-outline-info" id="btn-bulk-export">
                                        <i class="fas fa-download me-1"></i>
                                        Export Selected
                                    </button>
                                    <button class="btn btn-outline-secondary" id="btn-clear-selection">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover compact-table mb-0" id="orders-table">
                                    <thead class="table-header-sticky">
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="select-all-orders">
                                            </th>
                                            <th style="width: 100px;">PO Number</th>
                                            <th style="width: 120px;">Outlet</th>
                                            <th style="width: 90px;">Status</th>
                                            <th style="width: 80px;" class="text-center">Items</th>
                                            <th style="width: 80px;" class="text-center">Units</th>
                                            <th style="width: 90px;" class="text-end">Value</th>
                                            <th style="width: 100px;">Order Date</th>
                                            <th style="width: 100px;">Due Date</th>
                                            <th style="width: 180px;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="orders-table-body">
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
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

        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" style="margin-top: 56px;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Quick View - Selected Orders
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white" id="quick-view-content">
                <div class="text-center py-5">
                    <div class="spinner-border text-dark" role="status"></div>
                    <p class="text-muted mt-2">Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark" id="btn-view-full-orders">
                    <i class="fas fa-external-link-alt me-1"></i>
                    View Full Orders Page
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Dashboard JavaScript -->
<script src="/supplier/assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

<!-- Enhanced Table Selection Script -->
<script>
(function() {
    let selectedOrders = new Set();
    let lastSelected = null;

    // Handle "Select All" checkbox
    document.getElementById('select-all-orders')?.addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll('.order-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = e.target.checked;
            if (e.target.checked) {
                selectedOrders.add(cb.value);
            } else {
                selectedOrders.delete(cb.value);
            }
        });
        updateBulkActionsBar();
    });

    // Handle individual row selection with Shift+Click support
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('order-checkbox')) return;

        const checkbox = e.target;
        const orderId = checkbox.value;

        if (checkbox.checked) {
            selectedOrders.add(orderId);
        } else {
            selectedOrders.delete(orderId);
        }

        // Shift+Click range selection
        if (e.shiftKey && lastSelected) {
            const checkboxes = Array.from(document.querySelectorAll('.order-checkbox'));
            const start = checkboxes.indexOf(lastSelected);
            const end = checkboxes.indexOf(checkbox);
            const range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);

            range.forEach(cb => {
                cb.checked = checkbox.checked;
                if (checkbox.checked) {
                    selectedOrders.add(cb.value);
                } else {
                    selectedOrders.delete(cb.value);
                }
            });
        }

        lastSelected = checkbox;
        updateBulkActionsBar();
    });

    // Update bulk actions bar visibility and count
    function updateBulkActionsBar() {
        const bar = document.getElementById('bulk-actions-bar');
        const count = document.getElementById('selected-count');
        const csvBtn = document.getElementById('btn-download-csv');
        const pdfBtn = document.getElementById('btn-download-pdf');

        if (selectedOrders.size > 0) {
            // Slide down animation with 0.5s delay
            if (bar.style.display === 'none' || !bar.style.display) {
                // Update count FIRST before showing
                count.textContent = `${selectedOrders.size} selected`;

                // Enable export buttons FIRST
                if (csvBtn) {
                    csvBtn.disabled = false;
                    csvBtn.classList.remove('opacity-50');
                }
                if (pdfBtn) {
                    pdfBtn.disabled = false;
                    pdfBtn.classList.remove('opacity-50');
                }

                // Set initial state - completely invisible
                bar.style.display = 'block';
                bar.style.overflow = 'hidden';
                bar.style.opacity = '0';

                // Get the natural height
                const height = bar.scrollHeight;
                bar.style.height = '0';

                // Set transition with 0.5s delay before showing, then animate over 0.6s
                bar.style.transition = 'none';

                // Trigger animation on next frame with 0.5s delay
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        bar.style.transition = 'height 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.5s, opacity 0.5s ease 1s';
                        bar.style.height = height + 'px';
                        bar.style.opacity = '1';
                    });
                });

                // Clean up after animation completes
                bar.addEventListener('transitionend', function cleanup(e) {
                    if (e.propertyName === 'height') {
                        bar.style.height = 'auto';
                        bar.style.overflow = 'visible';
                        bar.removeEventListener('transitionend', cleanup);
                    }
                });
            } else {
                // Just update count if already visible
                count.textContent = `${selectedOrders.size} selected`;
            }
        } else {
            // Slide up animation with 0.5s delay before hiding
            if (bar.style.display !== 'none') {
                // Lock current height
                const height = bar.scrollHeight;
                bar.style.height = height + 'px';
                bar.style.overflow = 'hidden';

                // Set transition - fade out and collapse with 0.5s delay
                bar.style.transition = 'opacity 0.3s ease, height 0.5s cubic-bezier(0.4, 0, 0.2, 1) 0.5s';

                // Trigger animation on next frame
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        bar.style.opacity = '0';
                        bar.style.height = '0';
                    });
                });

                // Hide after animation (delay for 0.5s + animation time)
                bar.addEventListener('transitionend', function cleanup(e) {
                    if (e.propertyName === 'height') {
                        bar.style.display = 'none';
                        bar.removeEventListener('transitionend', cleanup);
                    }
                });
            }

            // Disable export buttons
            if (csvBtn) {
                csvBtn.disabled = true;
                csvBtn.classList.add('opacity-50');
            }
            if (pdfBtn) {
                pdfBtn.disabled = true;
                pdfBtn.classList.add('opacity-50');
            }
        }
    }

    // Quick View button
    document.getElementById('btn-quick-view')?.addEventListener('click', function() {
        if (selectedOrders.size === 0) {
            alert('Please select at least one order');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
        const content = document.getElementById('quick-view-content');

        // Reset to loading state
        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-dark" role="status"></div>
                <p class="text-muted mt-2">Loading order details...</p>
            </div>
        `;

        // Show modal
        modal.show();

        // Fetch order details
        fetch('/supplier/api/orders-quick-view.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_ids: Array.from(selectedOrders)})
        })
        .then(r => r.json())
        .then(response => {
            if (response.success && response.data) {
                // Build HTML from data
                let html = '';
                response.data.forEach((order, index) => {
                    const statusClass = order.status === 'OPEN' ? 'primary' :
                                       order.status === 'PACKING' ? 'warning' :
                                       order.status === 'SENT' ? 'success' : 'info';

                    html += `
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <strong>${order.po_number}</strong> - ${order.outlet_name}
                                    </h6>
                                    <small class="text-muted">${order.full_address}</small>
                                </div>
                                <span class="badge bg-${statusClass}">${order.status}</span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <small class="text-muted">Order Date</small>
                                        <div><strong>${new Date(order.created_at).toLocaleDateString()}</strong></div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Due Date</small>
                                        <div><strong>${order.due_date || 'Not specified'}</strong></div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Items</small>
                                        <div><strong>${order.items_count} products</strong></div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Total Units</small>
                                        <div><strong>${order.units_count} units</strong></div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>SKU</th>
                                                <th>Product</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Price</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${order.line_items.map(item => `
                                                <tr>
                                                    <td class="font-monospace small">${item.sku || '-'}</td>
                                                    <td>${item.product_name}</td>
                                                    <td class="text-center"><strong>${item.quantity}</strong></td>
                                                    <td class="text-end">$${parseFloat(item.price).toFixed(2)}</td>
                                                    <td class="text-end"><strong>$${parseFloat(item.line_total).toFixed(2)}</strong></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="4" class="text-end">Total:</th>
                                                <th class="text-end">$${parseFloat(order.total_amount).toFixed(2)}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    `;
                });

                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${response.error || 'Failed to load orders'}
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Quick View error:', err);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading orders. Please try again.
                </div>
            `;
        });
    });

    // Bulk Packing Slip
    document.getElementById('btn-bulk-packing-slip')?.addEventListener('click', function() {
        if (selectedOrders.size === 0) {
            alert('Please select at least one order');
            return;
        }

        const orderIds = Array.from(selectedOrders).join(',');
        window.open(`/supplier/api/generate-packing-slips.php?orders=${orderIds}`, '_blank');
    });

    // Bulk Export
    document.getElementById('btn-bulk-export')?.addEventListener('click', function() {
        if (selectedOrders.size === 0) {
            alert('Please select at least one order');
            return;
        }

        const orderIds = Array.from(selectedOrders).join(',');
        window.location.href = `/supplier/api/export-orders.php?orders=${orderIds}`;
    });

    // Clear Selection
    document.getElementById('btn-clear-selection')?.addEventListener('click', function() {
        selectedOrders.clear();
        document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('select-all-orders').checked = false;
        updateBulkActionsBar();
    });

    // View Full Orders Page
    document.getElementById('btn-view-full-orders')?.addEventListener('click', function() {
        const orderIds = Array.from(selectedOrders).join(',');
        window.location.href = `/supplier/orders.php?filter=${orderIds}`;
    });

    // CSV Download
    document.getElementById('btn-download-csv')?.addEventListener('click', function() {
        if (selectedOrders.size > 0) {
            const orderIds = Array.from(selectedOrders).join(',');
            window.location.href = `/supplier/api/export-orders.php?orders=${orderIds}&format=csv`;
        } else {
            window.location.href = '/supplier/api/export-orders.php?format=csv';
        }
    });

    // PDF Download
    document.getElementById('btn-download-pdf')?.addEventListener('click', function() {
        if (selectedOrders.size > 0) {
            const orderIds = Array.from(selectedOrders).join(',');
            window.open(`/supplier/api/export-orders.php?orders=${orderIds}&format=pdf`, '_blank');
        } else {
            window.open('/supplier/api/export-orders.php?format=pdf', '_blank');
        }
    });
})();
</script>

</body>
</html>
