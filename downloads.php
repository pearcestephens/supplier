<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Check if downloads feature is enabled
if (!defined('FEATURE_DOWNLOADS_ENABLED') || !FEATURE_DOWNLOADS_ENABLED) {
    header('Location: /supplier/dashboard.php');
    exit;
}

if (!Auth::check()) {
    header('Location: /supplier/login.php');
    exit;
}

$supplierID = Auth::getSupplierId();
$supplierName = Auth::getSupplierName();

// Get statistics for download counts
$db = db();

// Count total orders available for download
$orderCountStmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM vend_consignments
    WHERE supplier_id = ?
    AND transfer_category = 'PURCHASE_ORDER'
    AND deleted_at IS NULL
");
$orderCountStmt->bind_param('s', $supplierID);
$orderCountStmt->execute();
$totalOrders = $orderCountStmt->get_result()->fetch_assoc()['count'] ?? 0;
$orderCountStmt->close();

// Count warranty claims (join with vend_products to filter by supplier)
$warrantyCountStmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM faulty_products fp
    INNER JOIN vend_products vp ON fp.product_id = vp.id
    WHERE vp.supplier_id = ?
    AND vp.deleted_at IS NULL
");
$warrantyCountStmt->bind_param('s', $supplierID);
$warrantyCountStmt->execute();
$totalWarranties = $warrantyCountStmt->get_result()->fetch_assoc()['count'] ?? 0;
$warrantyCountStmt->close();

// Get date ranges for reports
$currentMonth = date('F Y');
$lastMonth = date('F Y', strtotime('-1 month'));
$currentYear = date('Y');

$activeTab = 'downloads';
$pageTitle = 'Downloads';
$pageIcon = 'fa-solid fa-download';
$pageDescription = 'Export order history, product lists, and reports for my records';
$breadcrumb = [
    ['text' => 'Downloads', 'href' => '/supplier/downloads.php']
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

<!-- Downloads Content -->
<div class="downloads-page">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 mb-0">
                <i class="fa-solid fa-download"></i> Downloads & Archives
            </h1>
            <p class="text-muted mt-2">Export your orders, reports, and warranty data</p>
        </div>
        <div>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print This Page
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Downloads Section -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fa-solid fa-bolt"></i> Quick Downloads
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <!-- All Orders CSV -->
                    <a href="/supplier/api/export-orders.php" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="me-3">
                            <i class="fa-solid fa-file-csv fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">All Orders (CSV)</h6>
                            <p class="mb-0 text-muted small">Export all orders with full details</p>
                        </div>
                        <div>
                            <i class="fa-solid fa-download text-primary"></i>
                        </div>
                    </a>

                    <!-- Filtered Orders CSV -->
                    <a href="?tab=orders" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="me-3">
                            <i class="fa-solid fa-filter fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Filtered Orders Export</h6>
                            <p class="mb-0 text-muted small">Go to Orders page to filter and export specific data</p>
                        </div>
                        <div>
                            <i class="fa-solid fa-arrow-right text-primary"></i>
                        </div>
                    </a>

                    <!-- Warranty Claims CSV -->
                    <button onclick="downloadWarrantyClaims()" class="list-group-item list-group-item-action d-flex align-items-center py-3" style="border: none; background: transparent;">
                        <div class="me-3">
                            <i class="fa-solid fa-wrench fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Warranty Claims (CSV)</h6>
                                                    <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-file-csv text-success"></i> Warranty Claims Report</h5>
                            <p class="mb-0 text-muted small">Export all <?php echo number_format((float)($totalWarranties ?? 0)); ?> warranty claims</p>
                        </div>
                        <div>
                            <i class="fa-solid fa-download text-primary"></i>
                        </div>
                    </button>

                    <!-- Monthly Report PDF -->
                    <button onclick="generateMonthlyReport()" class="list-group-item list-group-item-action d-flex align-items-center py-3" style="border: none; background: transparent;">
                        <div class="me-3">
                            <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Monthly Report (PDF)</h6>
                            <p class="mb-0 text-muted small"><?php echo $currentMonth; ?> performance summary</p>
                        </div>
                        <div>
                            <i class="fa-solid fa-download text-primary"></i>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Reports Section -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">
                    <i class="fa-solid fa-calendar"></i> Period Reports
                </h5>
            </div>
            <div class="card-body">
                <form id="custom-report-form" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Report Format</label>
                            <select name="format" class="form-select">
                                <option value="csv">CSV (Excel Compatible)</option>
                                <option value="pdf">PDF (Formatted Report)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-download"></i> Generate Custom Report
                            </button>
                        </div>
                    </div>
                </form>

                <hr>

                <h6 class="mb-3">Quick Period Exports</h6>
                <div class="d-grid gap-2">
                    <button onclick="downloadPeriodReport('this_month')" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-calendar-day"></i> This Month (<?php echo $currentMonth; ?>)
                    </button>
                    <button onclick="downloadPeriodReport('last_month')" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-calendar"></i> Last Month (<?php echo $lastMonth; ?>)
                    </button>
                    <button onclick="downloadPeriodReport('this_year')" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-calendar-year"></i> Year to Date (<?php echo $currentYear; ?>)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<?php include __DIR__ . '/components/html-footer.php'; ?>

<!-- Downloads JavaScript -->
<script src="/supplier/assets/js/downloads.js?v=<?php echo time(); ?>"></script>

</body>
</html>
