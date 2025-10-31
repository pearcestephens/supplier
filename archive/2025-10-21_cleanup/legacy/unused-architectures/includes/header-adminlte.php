<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title ?? 'Supplier Portal'; ?> | <?php echo htmlspecialchars($supplier['name'] ?? 'The Vape Shed'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE 3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: <?php echo $supplier['primary_color'] ?? '#007bff'; ?>;
            --secondary-color: <?php echo $supplier['secondary_color'] ?? '#6c757d'; ?>;
        }
        
        .navbar-primary {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .brand-logo {
            max-height: 40px;
            max-width: 150px;
        }
        
        .small-box {
            border-radius: 0.5rem;
        }
        
        .info-box {
            box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="navbar navbar-expand navbar-primary navbar-dark no-print">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?page=dashboard" class="nav-link">Dashboard</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="?page=purchase-orders" class="nav-link">Purchase Orders</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <?php if (($stats['unread_notifications'] ?? 0) > 0): ?>
                    <span class="badge badge-warning navbar-badge"><?php echo $stats['unread_notifications']; ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header"><?php echo $stats['unread_notifications'] ?? 0; ?> Notifications</span>
                    <div class="dropdown-divider"></div>
                    <?php
                    $notifications = get_supplier_notifications($conn, $supplier['id'], true, 5);
                    if (empty($notifications)):
                    ?>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-info-circle mr-2"></i> No new notifications
                    </a>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                        <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="dropdown-item">
                            <i class="fas fa-envelope mr-2"></i> <?php echo htmlspecialchars($notif['title']); ?>
                            <span class="float-right text-muted text-sm"><?php echo time_ago($notif['created_at']); ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="?page=notifications" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
            
            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <div class="dropdown-divider"></div>
                    <a href="?page=account" class="dropdown-item">
                        <i class="fas fa-user mr-2"></i> Account Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="?page=logout" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4 no-print">
        <!-- Brand Logo -->
        <a href="?page=dashboard" class="brand-link">
            <?php if (!empty($supplier['brand_logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($supplier['brand_logo_url']); ?>" 
                     alt="Logo" class="brand-logo">
            <?php else: ?>
                <span class="brand-text font-weight-light"><?php echo htmlspecialchars($supplier['name']); ?></span>
            <?php endif; ?>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <i class="fas fa-building fa-2x text-white"></i>
                </div>
                <div class="info">
                    <a href="?page=account" class="d-block"><?php echo htmlspecialchars($supplier['name']); ?></a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="?page=dashboard" class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=purchase-orders" class="nav-link <?php echo ($page === 'purchase-orders') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-shopping-cart"></i>
                            <p>
                                Purchase Orders
                                <?php if (($stats['active_pos'] ?? 0) > 0): ?>
                                <span class="right badge badge-info"><?php echo $stats['active_pos']; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=warranty-claims" class="nav-link <?php echo ($page === 'warranty-claims') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-exclamation-triangle"></i>
                            <p>
                                Warranty Claims
                                <?php if (($stats['pending_claims'] ?? 0) > 0): ?>
                                <span class="right badge badge-warning"><?php echo $stats['pending_claims']; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=analytics" class="nav-link <?php echo ($page === 'analytics') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Analytics & Reports</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=products" class="nav-link <?php echo ($page === 'products') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-boxes"></i>
                            <p>
                                Products
                                <span class="right badge badge-secondary"><?php echo $stats['total_products'] ?? 0; ?></span>
                            </p>
                        </a>
                    </li>
                    
                    <li class="nav-header">SUPPORT</li>
                    
                    <li class="nav-item">
                        <a href="?page=downloads" class="nav-link <?php echo ($page === 'downloads') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-download"></i>
                            <p>Downloads</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=account" class="nav-link <?php echo ($page === 'account') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Account Settings</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="?page=logout" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Logout</p>
                        </a>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
