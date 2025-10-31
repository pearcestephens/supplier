<?php
/**
 * Supplier Portal - Enhanced Orders Tab (Phase 3)
 * 
 * PHASE 3: Advanced Purchase Orders management with comprehensive functionality
 * Integrates with new API v2 endpoints for enhanced filtering, details, and operations
 * 
 * @package CIS\Supplier\Tabs
 * @version 3.0.0
 * @author CIS Development Team
 * @created October 23, 2025
 */

declare(strict_types=1);

// Get authenticated supplier ID
$supplierID = Auth::getSupplierId();

// ============================================================================
// QUICK STATS API CALL - Get dashboard metrics
// ============================================================================
$apiUrl = "/supplier/api/v2/po-list.php?quick_stats=1";
$statsResponse = @file_get_contents($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $apiUrl);
$stats = json_decode($statsResponse, true)['data'] ?? [];

// Fallback if API fails
if (empty($stats)) {
    $stats = [
        'total_orders' => 0,
        'total_value' => 0,
        'active_orders' => 0,
        'orders_30d' => 0,
        'avg_order_value' => 0
    ];
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-invoice-dollar"></i> Purchase Orders
        <span class="badge badge-secondary" id="totalOrdersBadge"><?php echo number_format($stats['total_orders']); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group mr-2">
            <button class="btn btn-sm btn-outline-info" onclick="refreshOrders();" title="Refresh">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-sm btn-success" onclick="showExportModal();" title="Export Orders">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-sm btn-primary" onclick="window.print();" title="Print">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilters();" id="filterToggle">
                <i class="fas fa-filter"></i> Filters
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions();" id="bulkToggle">
                <i class="fas fa-check-square"></i> Bulk Actions
            </button>
        </div>
    </div>
</div>

<!-- Enhanced Summary Stats Row -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="totalOrdersCount"><?php echo number_format($stats['total_orders']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Value</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="totalValueAmount">$<?php echo number_format($stats['total_value'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="activeOrdersCount"><?php echo number_format($stats['active_orders']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shipping-fast fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Last 30d</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="orders30dCount"><?php echo number_format($stats['orders_30d']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Avg Value</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="avgOrderValue">$<?php echo number_format($stats['avg_order_value'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-left-dark shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Selected</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="selectedCount">0</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-square fa-lg text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Filters Panel (Hidden by default) -->
<div class="card shadow mb-4" id="filtersPanel" style="display: none;">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter"></i> Advanced Filters
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Date Range -->
            <div class="col-md-3 mb-3">
                <label for="dateFrom" class="form-label">Date From:</label>
                <input type="date" class="form-control form-control-sm" id="dateFrom">
            </div>
            <div class="col-md-3 mb-3">
                <label for="dateTo" class="form-label">Date To:</label>
                <input type="date" class="form-control form-control-sm" id="dateTo">
            </div>
            
            <!-- Status Multi-Select -->
            <div class="col-md-3 mb-3">
                <label for="statusFilter" class="form-label">Status:</label>
                <select class="form-control form-control-sm" id="statusFilter" multiple>
                    <option value="DRAFT">Draft</option>
                    <option value="OPEN">Open</option>
                    <option value="PACKING">Packing</option>
                    <option value="PACKAGED">Packaged</option>
                    <option value="SENT">Sent</option>
                    <option value="RECEIVING">Receiving</option>
                    <option value="PARTIAL">Partial</option>
                    <option value="RECEIVED">Received</option>
                    <option value="CLOSED">Closed</option>
                    <option value="CANCELLED">Cancelled</option>
                    <option value="ARCHIVED">Archived</option>
                </select>
            </div>
            
            <!-- Outlets Multi-Select -->
            <div class="col-md-3 mb-3">
                <label for="outletFilter" class="form-label">Outlets:</label>
                <select class="form-control form-control-sm" id="outletFilter" multiple>
                    <!-- Populated by JavaScript -->
                </select>
            </div>
            
            <!-- Value Range -->
            <div class="col-md-3 mb-3">
                <label for="valueMin" class="form-label">Value Min ($):</label>
                <input type="number" class="form-control form-control-sm" id="valueMin" step="0.01" min="0">
            </div>
            <div class="col-md-3 mb-3">
                <label for="valueMax" class="form-label">Value Max ($):</label>
                <input type="number" class="form-control form-control-sm" id="valueMax" step="0.01" min="0">
            </div>
            
            <!-- Search -->
            <div class="col-md-3 mb-3">
                <label for="searchTerm" class="form-label">Search:</label>
                <input type="text" class="form-control form-control-sm" id="searchTerm" placeholder="PO#, Outlet, Reference...">
            </div>
            
            <!-- Actions -->
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <div class="btn-group btn-group-sm w-100">
                    <button class="btn btn-primary" onclick="applyFilters();">
                        <i class="fas fa-search"></i> Apply
                    </button>
                    <button class="btn btn-outline-secondary" onclick="clearFilters();">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Panel (Hidden by default) -->
<div class="card shadow mb-4" id="bulkActionsPanel" style="display: none;">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="btn-group">
                    <button class="btn btn-success btn-sm" onclick="bulkAction('acknowledge')" id="bulkAcknowledge">
                        <i class="fas fa-check"></i> Acknowledge Selected
                    </button>
                    <button class="btn btn-info btn-sm" onclick="bulkAction('mark_sent')" id="bulkMarkSent">
                        <i class="fas fa-truck"></i> Mark as Sent
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="bulkExport()" id="bulkExport">
                        <i class="fas fa-download"></i> Export Selected
                    </button>
                </div>
            </div>
            <div class="col-md-4 text-right">
                <small class="text-muted">
                    <span id="selectedCountText">0</span> orders selected
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Orders Table Container -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="m-0 font-weight-bold text-primary">Purchase Orders</h6>
            <small class="text-muted" id="tableSubtitle">Loading...</small>
        </div>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" onclick="toggleView('table')" id="viewTable">
                <i class="fas fa-table"></i> Table
            </button>
            <button class="btn btn-outline-secondary" onclick="toggleView('cards')" id="viewCards">
                <i class="fas fa-th-large"></i> Cards
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Loading indicator -->
        <div id="loadingIndicator" class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <div class="mt-2">Loading orders...</div>
        </div>
        
        <!-- Table view -->
        <div id="tableView" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="ordersTable">
                    <thead class="thead-light">
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked);">
                            </th>
                            <th>
                                <a href="#" onclick="sortBy('public_id')" class="text-decoration-none">
                                    PO Number <i class="fas fa-sort" id="sort_public_id"></i>
                                </a>
                            </th>
                            <th>
                                <a href="#" onclick="sortBy('created_at')" class="text-decoration-none">
                                    Created <i class="fas fa-sort" id="sort_created_at"></i>
                                </a>
                            </th>
                            <th>Outlet</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Progress</th>
                            <th class="text-right">
                                <a href="#" onclick="sortBy('total_value')" class="text-decoration-none">
                                    Value <i class="fas fa-sort" id="sort_total_value"></i>
                                </a>
                            </th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Cards view -->
        <div id="cardsView" style="display: none;">
            <div class="row" id="ordersCardsContainer">
                <!-- Populated by JavaScript -->
            </div>
        </div>
        
        <!-- Empty state -->
        <div id="emptyState" class="text-center p-5" style="display: none;">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No orders found</h5>
            <p class="text-muted">Try adjusting your filters or search criteria.</p>
            <button class="btn btn-primary" onclick="clearFilters();">
                <i class="fas fa-undo"></i> Clear Filters
            </button>
        </div>
    </div>
</div>

<!-- Pagination -->
<nav aria-label="Orders pagination">
    <ul class="pagination justify-content-center" id="paginationControls">
        <!-- Populated by JavaScript -->
    </ul>
</nav>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice-dollar"></i> Order Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printOrderDetails();">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-download"></i> Export Orders
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="exportType" class="form-label">Export Type:</label>
                    <select class="form-control" id="exportType">
                        <option value="summary">Summary (Basic info)</option>
                        <option value="detailed">Detailed (Full data)</option>
                        <option value="items_only">Items Only</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="exportFormat" class="form-label">Format:</label>
                    <select class="form-control" id="exportFormat">
                        <option value="csv">CSV (Excel compatible)</option>
                        <option value="json">JSON (Data format)</option>
                        <option value="excel">Excel (XLSX)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exportCurrentFilters" checked>
                        <label class="form-check-label" for="exportCurrentFilters">
                            Apply current filters
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="exportLimit" class="form-label">Limit (max 10,000):</label>
                    <input type="number" class="form-control" id="exportLimit" value="1000" min="1" max="10000">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="executeExport();">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include the JavaScript functionality -->
<script src="/supplier/assets/js/orders.js?v=3.0.0"></script>

<script>
// Initialize the orders page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize orders manager
    if (typeof OrdersManager !== 'undefined') {
        window.ordersManager = new OrdersManager();
        window.ordersManager.init();
    } else {
        console.error('OrdersManager not loaded');
        // Fallback: load orders with basic functionality
        loadOrdersBasic();
    }
});

// Fallback function if JavaScript fails to load
function loadOrdersBasic() {
    console.log('Loading orders in basic mode...');
    document.getElementById('loadingIndicator').style.display = 'none';
    document.getElementById('tableView').style.display = 'block';
    document.getElementById('ordersTableBody').innerHTML = '<tr><td colspan="9" class="text-center text-muted">JavaScript required for enhanced functionality</td></tr>';
}

// Basic functions for immediate functionality
function toggleFilters() {
    const panel = document.getElementById('filtersPanel');
    const isVisible = panel.style.display !== 'none';
    panel.style.display = isVisible ? 'none' : 'block';
    
    const toggle = document.getElementById('filterToggle');
    toggle.classList.toggle('btn-primary', !isVisible);
    toggle.classList.toggle('btn-outline-secondary', isVisible);
}

function toggleBulkActions() {
    const panel = document.getElementById('bulkActionsPanel');
    const isVisible = panel.style.display !== 'none';
    panel.style.display = isVisible ? 'none' : 'block';
    
    const toggle = document.getElementById('bulkToggle');
    toggle.classList.toggle('btn-primary', !isVisible);
    toggle.classList.toggle('btn-outline-secondary', isVisible);
}

function showExportModal() {
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(document.getElementById('exportModal')).show();
    } else {
        // Fallback
        document.getElementById('exportModal').style.display = 'block';
    }
}

function refreshOrders() {
    if (window.ordersManager) {
        window.ordersManager.refresh();
    } else {
        location.reload();
    }
}
</script>

<style>
/* Custom styles for enhanced orders page */
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }
.border-left-secondary { border-left: 0.25rem solid #858796 !important; }
.border-left-dark { border-left: 0.25rem solid #5a5c69 !important; }

.card:hover {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.btn-group-sm > .btn, .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.progress {
    height: 0.5rem;
}

#ordersTable th a {
    color: inherit;
}

#ordersTable th a:hover {
    color: #4e73df;
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.order-card {
    transition: all 0.2s ease-in-out;
    border: 1px solid #e3e6f0;
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

@media print {
    .btn-toolbar, .pagination, .card-header .btn-group {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>