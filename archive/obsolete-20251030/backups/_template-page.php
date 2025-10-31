<?php
/**
 * MASTER PAGE TEMPLATE - Use this as reference for all pages
 * 
 * HOW TO USE:
 * 1. Copy this file to your new page (e.g., dashboard.php)
 * 2. Set $activeTab and $pageTitle variables
 * 3. Replace "YOUR CONTENT HERE" section with your page content
 * 4. Content should be ONLY the <div class="page-body"> section
 * 
 * @package SupplierPortal
 * @version 3.0.0 - Standardized Template
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
// PAGE CONFIGURATION (*** SET THESE FOR YOUR PAGE ***)
// ============================================================================
$activeTab = 'dashboard'; // dashboard, orders, warranty, reports, downloads, account
$pageTitle = 'Dashboard'; // Shown in header and browser tab

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
    
    <!-- Dashboard Widgets CSS (if needed for charts/widgets) -->
    <link rel="stylesheet" href="/supplier/assets/css/dashboard-widgets.css?v=<?php echo time(); ?>">
    
    <!-- Page-specific CSS can go here -->
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
             PAGE BODY - YOUR CONTENT GOES HERE
             ==================================================================== -->
        <div class="page-body">
            
            <!-- YOUR PAGE CONTENT HERE -->
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h3>Your Page Content</h3>
                                <p>Replace this section with your page content.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END PAGE CONTENT -->
            
        </div><!-- /.page-body -->
        
    </div><!-- /.page-wrapper -->
    
</div><!-- /.page -->

<!-- ============================================================================
     JAVASCRIPT LIBRARIES
     ========================================================================== -->
<!-- jQuery 3.6 (if needed) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5.3 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 3.9.1 (if needed for charts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<!-- Main App JS -->
<script src="/supplier/assets/js/app.js?v=<?php echo time(); ?>"></script>

<!-- Page-specific JavaScript can go here -->
<script>
// Initialize page
$(document).ready(function() {
    console.log('Page loaded: <?php echo $pageTitle; ?>');
    
    // Your page-specific JS here
});
</script>

</body>
</html>
