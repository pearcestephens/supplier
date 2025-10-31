<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    
    <?php
    // Dynamic title for supplier portal
    $___defaultTitle = 'The Vape Shed - Supplier Portal';
    $___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;
    ?>
    <title><?php echo htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- CORE CSS - BOOTSTRAP v4.1.1 COMPATIBLE -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- JQUERY UI -->
    <link href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    
    <!-- PACE LOADER -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css" rel="stylesheet">
    
    <!-- ICONS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css" rel="stylesheet">

    <!-- THE VAPE SHED SUPPLIER PORTAL STYLES -->
    <style>
        /* The Vape Shed Brand Colors */
        :root {
            --vs-primary: #1a252f;
            --vs-secondary: #2c3e50;
            --vs-accent: #3498db;
            --vs-success: #27ae60;
            --vs-warning: #f39c12;
            --vs-danger: #e74c3c;
            --vs-light: #ecf0f1;
            --vs-dark: #2c3e50;
            --vs-border: #ddd;
            --vs-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --vs-shadow-lg: 0 4px 20px rgba(0,0,0,0.15);
            --vs-gradient: linear-gradient(135deg, #1a252f 0%, #2c3e50 50%, #34495e 100%);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        /* App Layout with Sidebar */
        .app {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Top Admin Bar */
        .admin-topbar {
            background: var(--vs-gradient);
            color: white;
            padding: 0.5rem 1rem 0.5rem 300px; /* Account for sidebar width */
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1040;
        }
        
        .admin-topbar .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
        }
        
        .admin-topbar .breadcrumb-item,
        .admin-topbar .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        .admin-topbar .breadcrumb-item.active {
            color: white;
        }
        
        /* Main Header */
        .app-header {
            background: white;
            border-bottom: 1px solid var(--vs-border);
            box-shadow: var(--vs-shadow);
            margin-left: 280px; /* Account for sidebar width */
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: var(--vs-primary) !important;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand img {
            width: 32px;
            height: 32px;
            margin-right: 10px;
            border-radius: 6px;
        }
        
        .navbar-brand:hover {
            color: var(--vs-accent) !important;
        }
        
        .supplier-info {
            background: linear-gradient(135deg, var(--vs-accent), #5dade2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0 1rem;
        }
        
        .nav-link {
            color: var(--vs-secondary) !important;
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: var(--vs-accent) !important;
        }
        
        /* App Body with Sidebar Layout */
        .app-body {
            flex: 1;
            display: flex;
            background: #f8f9fa;
        }
        
        /* Main Content Area */
        .main {
            flex: 1;
            margin-left: 280px; /* Account for fixed sidebar */
            padding: 2rem;
            min-height: calc(100vh - 120px);
        }
        
        /* Cards */
        .card {
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            box-shadow: var(--vs-shadow);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--vs-primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: var(--vs-shadow);
            border-left: 4px solid var(--vs-accent);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--vs-shadow-lg);
        }
        
        .stats-card.success {
            border-left-color: var(--vs-success);
        }
        
        .stats-card.warning {
            border-left-color: var(--vs-warning);
        }
        
        .stats-card.danger {
            border-left-color: var(--vs-danger);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--vs-primary);
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Tables */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-top: none;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 600;
            color: var(--vs-primary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table td {
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }
        
        /* Buttons */
        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--vs-accent);
            border-color: var(--vs-accent);
        }
        
        .btn-primary:hover {
            background: #2980b9;
            border-color: #2980b9;
            transform: translateY(-1px);
        }
        
        /* Footer */
        .app-footer {
            background: white;
            border-top: 1px solid var(--vs-border);
            padding: 1rem 2rem;
            margin-left: 280px; /* Account for sidebar */
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #666;
        }
        
        /* Mobile Hamburger */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1060;
            background: var(--vs-primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            font-size: 1.2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
            
            .admin-topbar,
            .app-header,
            .main,
            .app-footer {
                margin-left: 0;
            }
            
            .main {
                padding: 1rem;
            }
            
            .supplier-info {
                display: none;
            }
        }
        
        /* Print Styles */
        @media print {
            .admin-topbar,
            .app-footer,
            .mobile-toggle {
                display: none !important;
            }
            
            .main {
                margin-left: 0;
                padding: 0;
            }
        }
    </style>

    <!-- JAVASCRIPT - JQUERY FIRST -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- MOMENT.JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <!-- Note: Bootstrap JS loaded in footer for proper dependency order -->

    <?php
    // Optional: extra head markup from page (styles, meta, links). String only for safety.
    if (isset($extraHead) && is_string($extraHead)) {
        echo $extraHead;
    }
    ?>
</head>
<body class="app">

    <!-- MOBILE SIDEBAR TOGGLE -->
    <button class="mobile-toggle" type="button" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- INCLUDE SUPPLIER MENU COMPONENT -->
    <?php 
    // Set supplier ID for menu component
    $supplierID = isset($_GET['supplierID']) ? (int)$_GET['supplierID'] : null;
    $GLOBALS['supplierID'] = $supplierID;
    
    // Include the separate menu component
    include_once 'supplier-menu.php'; 
    ?>

    <!-- ADMIN TOP BAR -->
    <div class="admin-topbar">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Supplier Portal</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </nav>
        <div class="admin-info">
            <i class="fas fa-clock"></i> <?php echo date('D, M j, Y g:i A'); ?>
        </div>
    </div>

    <!-- MAIN HEADER -->
    <header class="app-header navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <img src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" alt="The Vape Shed Logo">
                <strong>The Vape Shed</strong> Supplier Portal
            </a>

            <!-- SUPPLIER INFO DISPLAY -->
            <?php if ($supplierID): ?>
                <div class="supplier-info">
                    <i class="fas fa-tag mr-1"></i> Supplier #<?php echo $supplierID; ?>
                </div>
            <?php endif; ?>

            <!-- NAVIGATION ACTIONS -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> Account
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="#"><i class="fas fa-user-edit mr-2"></i> Profile</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-cog mr-2"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="window.print();"><i class="fas fa-print mr-2"></i> Print Page</a>
                    </div>
                </li>
            </ul>
        </div>
    </header>

    <!-- APP BODY -->
    <div class="app-body">
        <!-- MAIN CONTENT -->
        <main class="main">
            <div class="container-fluid">

    <!-- MOBILE SIDEBAR FUNCTIONALITY -->
    <script>
        $(document).ready(function() {
            // Mobile toggle functionality
            $('#mobileToggle').click(function() {
                $('#supplierSidebar').toggleClass('show');
                $(this).find('i').toggleClass('fa-bars fa-times');
            });
            
            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                if ($(window).width() <= 768) {
                    if (!$(e.target).closest('#supplierSidebar, #mobileToggle').length) {
                        $('#supplierSidebar').removeClass('show');
                        $('#mobileToggle i').removeClass('fa-times').addClass('fa-bars');
                    }
                }
            });
            
            // Update breadcrumb based on current page
            updateBreadcrumb();
            
            function updateBreadcrumb() {
                var currentPath = window.location.pathname;
                var breadcrumbMap = {
                    '/': 'Dashboard',
                    '/products': 'Products',
                    '/orders': 'Orders',
                    '/inventory': 'Inventory',
                    '/reports': 'Reports',
                    '/support': 'Support'
                };
                
                var currentPage = breadcrumbMap[currentPath] || 'Dashboard';
                $('.breadcrumb-item.active').text(currentPage);
            }
        });
    </script>
<?php
// Simple supplier portal variables (no database dependencies)
$GLOBALS['supplierID'] = $supplierID;
?>