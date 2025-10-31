<?php
/**
 * The Vape Shed - Complete Supplier Portal Demo
 * Demonstrates all updated components with The Vape Shed branding
 * 
 * @file supplier-portal-complete-demo.php
 * @purpose Complete demonstration of updated supplier portal
 * @author Pearce Stephens
 * @last_modified 2025-10-07
 */

$pageTitle = 'Complete Demo - The Vape Shed Supplier Portal';
$supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : 12345;

// Include the updated header with logo and menu
include_once 'supplier-header-updated.php';
?>

<!-- DEMO NAVIGATION -->
<div class="alert alert-info mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h5><i class="fas fa-info-circle mr-2"></i>The Vape Shed Supplier Portal Demo</h5>
            <p class="mb-0">Complete demonstration of all updated components with integrated logo and branding</p>
        </div>
        <div class="col-lg-4 text-right">
            <div class="btn-group" role="group">
                <a href="supplier-portal-dashboard.php" class="btn btn-primary btn-sm">Dashboard</a>
                <a href="supplier-claims.php" class="btn btn-info btn-sm">Claims</a>
                <a href="supplier-sales.php" class="btn btn-success btn-sm">Sales</a>
                <a href="supplier-purchase-order.php" class="btn btn-warning btn-sm">Purchase Order</a>
            </div>
        </div>
    </div>
</div>

<!-- COMPONENT SHOWCASE -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">
                    <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="VS" style="width: 24px; height: 24px; border-radius: 4px; margin-right: 8px;">
                    Updated Components Overview
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-bars fa-3x text-primary mb-3"></i>
                                <h6>Separate Menu Component</h6>
                                <p class="small text-muted">Modular sidebar navigation with The Vape Shed branding</p>
                                <a href="#menu-demo" class="btn btn-primary btn-sm">View Demo</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-tachometer-alt fa-3x text-success mb-3"></i>
                                <h6>Dashboard</h6>
                                <p class="small text-muted">Complete dashboard with stats, charts, and quick actions</p>
                                <a href="supplier-portal-dashboard.php" class="btn btn-success btn-sm">View Dashboard</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-file-invoice-dollar fa-3x text-info mb-3"></i>
                                <h6>Claims Management</h6>
                                <p class="small text-muted">Warranty claims and returns management system</p>
                                <a href="supplier-claims.php" class="btn btn-info btn-sm">View Claims</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                                <h6>Sales Analytics</h6>
                                <p class="small text-muted">Comprehensive sales tracking and analytics</p>
                                <a href="supplier-sales.php" class="btn btn-warning btn-sm">View Sales</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BRANDING SHOWCASE -->
<div class="row mb-4" id="menu-demo">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-palette mr-2"></i>The Vape Shed Branding Integration
                </h6>
            </div>
            <div class="card-body">
                <div class="branding-showcase">
                    <div class="brand-element mb-3">
                        <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="The Vape Shed Logo" class="logo-preview">
                        <div class="brand-info">
                            <h6>Logo Integration</h6>
                            <p class="small text-muted">Logo appears in sidebar, header, and footer components</p>
                        </div>
                    </div>
                    <div class="color-palette">
                        <h6>Color Palette</h6>
                        <div class="color-swatches">
                            <div class="color-swatch" style="background: #1a252f;" title="Primary Dark"></div>
                            <div class="color-swatch" style="background: #2c3e50;" title="Secondary"></div>
                            <div class="color-swatch" style="background: #3498db;" title="Accent Blue"></div>
                            <div class="color-swatch" style="background: #27ae60;" title="Success Green"></div>
                            <div class="color-swatch" style="background: #f39c12;" title="Warning Orange"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-cogs mr-2"></i>Technical Features
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Modular Menu Component
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Logo Integration
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Responsive Design
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Professional Admin Styling
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Chart.js Integration
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        DataTables Integration
                        <span class="badge badge-success">✓ Complete</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- FILE STRUCTURE -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-folder-tree mr-2"></i>Updated File Structure
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Core Components</h6>
                        <div class="file-tree">
                            <div class="file-item">
                                <i class="fas fa-file-code text-primary mr-2"></i>
                                <strong>supplier-header-updated.php</strong>
                                <div class="file-description">Complete header with logo and layout</div>
                            </div>
                            <div class="file-item">
                                <i class="fas fa-file-code text-success mr-2"></i>
                                <strong>supplier-menu.php</strong>
                                <div class="file-description">Separate modular menu component</div>
                            </div>
                            <div class="file-item">
                                <i class="fas fa-file-code text-info mr-2"></i>
                                <strong>supplier-footer-updated.php</strong>
                                <div class="file-description">Updated footer with branding</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Application Pages</h6>
                        <div class="file-tree">
                            <div class="file-item">
                                <i class="fas fa-file-alt text-primary mr-2"></i>
                                <strong>supplier-portal-dashboard.php</strong>
                                <div class="file-description">Main dashboard with stats and charts</div>
                            </div>
                            <div class="file-item">
                                <i class="fas fa-file-alt text-warning mr-2"></i>
                                <strong>supplier-claims.php</strong>
                                <div class="file-description">Claims management interface</div>
                            </div>
                            <div class="file-item">
                                <i class="fas fa-file-alt text-success mr-2"></i>
                                <strong>supplier-sales.php</strong>
                                <div class="file-description">Sales analytics and management</div>
                            </div>
                            <div class="file-item">
                                <i class="fas fa-file-alt text-info mr-2"></i>
                                <strong>supplier-purchase-order.php</strong>
                                <div class="file-description">Purchase order view and management</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK TEST ACTIONS -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-play mr-2"></i>Quick Test Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-primary btn-block" onclick="testNotification()">
                            <i class="fas fa-bell mr-2"></i>Test Notification
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-success btn-block" onclick="testMenuHighlight()">
                            <i class="fas fa-highlighter mr-2"></i>Test Menu Highlight
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-info btn-block" onclick="testResponsive()">
                            <i class="fas fa-mobile-alt mr-2"></i>Test Mobile Menu
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-warning btn-block" onclick="testPrint()">
                            <i class="fas fa-print mr-2"></i>Test Print Mode
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DEMO JAVASCRIPT -->
<script>
$(document).ready(function() {
    // Set active menu item
    if (typeof setActiveMenuItem === 'function') {
        setActiveMenuItem('dashboard');
    }
    
    // Initialize demo
    initDemo();
    
    function initDemo() {
        // Show welcome message
        setTimeout(function() {
            showSupplierNotification('Welcome to The Vape Shed Supplier Portal Demo!', 'info');
        }, 1000);
        
        // Initialize logo animations
        $('.logo-preview').hover(
            function() {
                $(this).css('transform', 'scale(1.1) rotate(3deg)');
            },
            function() {
                $(this).css('transform', 'scale(1) rotate(0deg)');
            }
        );
    }
});

function testNotification() {
    showSupplierNotification('This is a test notification from The Vape Shed Supplier Portal!', 'success');
}

function testMenuHighlight() {
    // Cycle through menu items
    var menuItems = ['dashboard', 'products', 'orders', 'inventory', 'reports', 'support'];
    var currentIndex = 0;
    
    var interval = setInterval(function() {
        if (typeof setActiveMenuItem === 'function') {
            setActiveMenuItem(menuItems[currentIndex]);
        }
        currentIndex++;
        
        if (currentIndex >= menuItems.length) {
            clearInterval(interval);
            if (typeof setActiveMenuItem === 'function') {
                setActiveMenuItem('dashboard');
            }
            showSupplierNotification('Menu highlighting test completed!', 'info');
        }
    }, 800);
}

function testResponsive() {
    $('#supplierSidebar').toggleClass('show');
    $('#mobileToggle i').toggleClass('fa-bars fa-times');
    showSupplierNotification('Mobile menu toggled!', 'info');
}

function testPrint() {
    showSupplierNotification('Print preview will open shortly...', 'info');
    setTimeout(function() {
        window.print();
    }, 1000);
}
</script>

<!-- DEMO STYLES -->
<style>
.branding-showcase {
    padding: 1rem 0;
}

.brand-element {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.logo-preview {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    margin-right: 1rem;
    transition: transform 0.3s ease;
}

.brand-info {
    flex: 1;
}

.color-palette {
    margin-top: 1rem;
}

.color-swatches {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.color-swatch {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.color-swatch:hover {
    transform: scale(1.1);
}

.file-tree {
    padding-left: 1rem;
}

.file-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.file-item:last-child {
    border-bottom: none;
}

.file-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin-left: 1.5rem;
}

/* Demo specific animations */
.stats-card {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php
// Include the updated footer
include_once 'supplier-footer-updated.php';
?>