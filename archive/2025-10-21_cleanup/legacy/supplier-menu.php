<?php
/**
 * The Vape Shed - Supplier Portal Navigation Menu Component
 * Separate modular menu component for reusability across supplier portal
 * 
 * @file supplier-menu.php
 * @purpose Standalone navigation menu for supplier portal
 * @author Pearce Stephens
 * @last_modified 2025-10-08
 */

// Default supplier ID for demo purposes
$supplierID = isset($GLOBALS['supplierID']) ? $GLOBALS['supplierID'] : (isset($_GET['supplierID']) ? $_GET['supplierID'] : (isset($_GET['supplier_id']) ? $_GET['supplier_id'] : null));
?>

<!-- THE VAPE SHED SUPPLIER SIDEBAR (CoreUI Compatible) -->
<div class="sidebar">
    <nav class="sidebar-nav">
        <ul class="nav">
            <!-- SUPPLIER INFO BADGE -->
            <?php if ($supplierID): ?>
            <li class="nav-item px-3">
                <div class="text-center py-2">
                    <span class="badge badge-primary">
                        <i class="fa fa-building"></i> Supplier #<?php echo htmlspecialchars($supplierID); ?>
                    </span>
                </div>
            </li>
            <li class="divider"></li>
            <?php endif; ?>
            
            <!-- SUPPLIER MENU -->
            <li class="nav-title">Menu</li>
            <li class="nav-item">
                <a class="nav-link" href="supplier-orders.php<?php echo $supplierID ? '?supplier_id='.urlencode($supplierID) : ''; ?>">
                    <i class="nav-icon icon-basket"></i> Purchase Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="supplier-sales.php<?php echo $supplierID ? '?supplierID='.urlencode($supplierID) : ''; ?>">
                    <i class="nav-icon icon-chart"></i> Sales Reporting
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="supplier-warranty-returns.php<?php echo $supplierID ? '?supplierID='.urlencode($supplierID) : ''; ?>">
                    <i class="nav-icon icon-wrench"></i> Warranty Returns
                </a>
            </li>
        </ul>
    </nav>
    <button class="sidebar-minimizer brand-minimizer" type="button"></button>
</div>
