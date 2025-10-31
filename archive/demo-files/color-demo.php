<?php
/**
 * Color Scheme Demo - The Vape Shed Supplier Portal
 * Test different AdminLTE 3 color schemes in real-time
 */

// Get scheme from URL or default
$scheme = isset($_GET['scheme']) ? htmlspecialchars($_GET['scheme']) : 'current';

// Define available schemes
$schemes = [
    'current' => [
        'name' => 'Dark Blue (Current)',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-dark navbar-primary',
        'sidebar' => 'sidebar-dark-primary',
        'description' => 'Professional blue theme (current setup)'
    ],
    'black' => [
        'name' => 'Pure Black Sidebar',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-dark',
        'sidebar' => 'sidebar-dark-dark',
        'description' => 'Black sidebar with dark gray navbar'
    ],
    'black-navy' => [
        'name' => 'Black Sidebar + Navy Top',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-navy',
        'sidebar' => 'sidebar-dark-dark',
        'description' => 'Pure black sidebar, navy navbar'
    ],
    'black-blue' => [
        'name' => 'Black Sidebar + Blue Top',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-primary',
        'sidebar' => 'sidebar-dark-dark',
        'description' => 'Pure black sidebar, blue navbar'
    ],
    'navy' => [
        'name' => 'Navy Blue',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-navy',
        'sidebar' => 'sidebar-dark-navy',
        'description' => 'Corporate & trustworthy'
    ],
    'indigo' => [
        'name' => 'Indigo',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-indigo',
        'sidebar' => 'sidebar-dark-indigo',
        'description' => 'Modern & tech-forward'
    ],
    'teal' => [
        'name' => 'Teal',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-teal',
        'sidebar' => 'sidebar-dark-teal',
        'description' => 'Fresh & clean'
    ],
    'purple' => [
        'name' => 'Purple',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-purple',
        'sidebar' => 'sidebar-dark-purple',
        'description' => 'Premium & luxury'
    ],
    'danger' => [
        'name' => 'Red',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-danger',
        'sidebar' => 'sidebar-dark-danger',
        'description' => 'Bold & energetic'
    ],
    'success' => [
        'name' => 'Green',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-success',
        'sidebar' => 'sidebar-dark-success',
        'description' => 'Natural & growth'
    ],
    'warning' => [
        'name' => 'Orange',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-warning',
        'sidebar' => 'sidebar-dark-warning',
        'description' => 'Energetic & warm'
    ],
    'dark' => [
        'name' => 'Dark Gray',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-dark',
        'sidebar' => 'sidebar-dark-gray-dark',
        'description' => 'Minimal & sleek'
    ],
    'light' => [
        'name' => 'Light Theme',
        'body_class' => 'hold-transition sidebar-mini layout-fixed',
        'navbar' => 'navbar-white navbar-light',
        'sidebar' => 'sidebar-light-primary',
        'description' => 'Bright & clean'
    ]
];

$current_scheme = $schemes[$scheme] ?? $schemes['current'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Color Scheme Demo - The Vape Shed Supplier Portal</title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- AdminLTE 3.2.0 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        /* Custom styles */
        .scheme-selector {
            position: fixed;
            top: 60px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            max-width: 300px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .scheme-button {
            display: block;
            width: 100%;
            margin-bottom: 10px;
            padding: 12px;
            text-align: left;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .scheme-button:hover {
            border-color: #007bff;
            background: #f8f9fa;
            transform: translateX(-5px);
        }
        
        .scheme-button.active {
            border-color: #28a745;
            background: #d4edda;
            font-weight: bold;
        }
        
        .scheme-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .scheme-desc {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .current-label {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            margin-left: 8px;
        }
        
        .demo-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin: 20px;
        }
        
        @media (max-width: 768px) {
            .scheme-selector {
                position: relative;
                top: 0;
                right: 0;
                margin: 10px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body class="<?php echo $current_scheme['body_class']; ?>">
<div class="wrapper">
    
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand <?php echo $current_scheme['navbar']; ?>">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="color-demo.php" class="nav-link">Color Demo</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar -->
    <aside class="main-sidebar <?php echo $current_scheme['sidebar']; ?> elevation-4">
        <!-- Brand Logo -->
        <a href="color-demo.php" class="brand-link">
            <img src="/supplier/assets/images/logo.png" alt="The Vape Shed Logo" class="brand-image elevation-3" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
            <span class="brand-text font-weight-light" style="display:none;"><i class="fas fa-warehouse"></i></span>
            <span class="brand-text font-weight-light"><strong>The Vape Shed</strong></span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <i class="fas fa-building fa-2x text-white" style="margin-top: 5px;"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block">Color Demo</a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-palette"></i>
                            <p>Color Schemes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="nav-icon fas fa-arrow-left"></i>
                            <p>Back to Portal</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">🎨 Color Scheme Demo</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">Color Demo</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Demo Notice -->
                <div class="demo-notice">
                    <h5><i class="fas fa-info-circle"></i> Interactive Color Scheme Demo</h5>
                    <p class="mb-0">
                        <strong>Current Scheme:</strong> <?php echo $current_scheme['name']; ?> - <?php echo $current_scheme['description']; ?>
                        <br>
                        Click any scheme button on the right to instantly switch colors!
                    </p>
                </div>

                <!-- Sample Info Boxes -->
                <div class="row">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-file-invoice-dollar"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Active Orders</span>
                                <span class="info-box-number">17</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Pending Claims</span>
                                <span class="info-box-number">0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">30-Day Orders</span>
                                <span class="info-box-number">0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-box"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Items Ordered</span>
                                <span class="info-box-number">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sample Cards -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shopping-cart"></i> Recent Orders</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>PO-28151</strong></td>
                                            <td>Oct 18, 2025</td>
                                            <td>12 items</td>
                                            <td><span class="badge badge-success">Received</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>PO-28146</strong></td>
                                            <td>Oct 17, 2025</td>
                                            <td>8 items</td>
                                            <td><span class="badge badge-warning">Pending</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-tools"></i> Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <a href="#" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-download"></i> Download Orders
                                </a>
                                <a href="#" class="btn btn-info btn-block mb-2">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </a>
                                <a href="#" class="btn btn-success btn-block">
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; 2025 The Vape Shed.</strong> All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 2.0.0 | <b>Demo Mode</b>
        </div>
    </footer>

</div>

<!-- Color Scheme Selector (Floating Panel) -->
<div class="scheme-selector">
    <h5 style="margin-bottom: 15px; text-align: center;">
        <i class="fas fa-palette"></i> Choose Color Scheme
    </h5>
    
    <?php foreach ($schemes as $key => $scheme_data): ?>
    <a href="color-demo.php?scheme=<?php echo $key; ?>" class="scheme-button <?php echo ($key === $scheme) ? 'active' : ''; ?>">
        <div class="scheme-name">
            <?php echo $scheme_data['name']; ?>
            <?php if ($key === $scheme): ?>
                <span class="current-label">CURRENT</span>
            <?php endif; ?>
        </div>
        <div class="scheme-desc"><?php echo $scheme_data['description']; ?></div>
    </a>
    <?php endforeach; ?>
    
    <hr>
    
    <div style="text-align: center; margin-top: 15px;">
        <a href="index.php" class="btn btn-sm btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Portal
        </a>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Smooth scroll on scheme change
document.querySelectorAll('.scheme-button').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        // Show loading state
        document.body.style.opacity = '0.5';
        document.body.style.transition = 'opacity 0.3s';
    });
});
</script>

</body>
</html>
