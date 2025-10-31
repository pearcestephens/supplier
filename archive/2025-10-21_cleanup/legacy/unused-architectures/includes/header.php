<?php
/**
 * Header Component
 * 
 * NO require statements - bootstrap already loaded
 * $supplier is available from index.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal - <?= htmlspecialchars($supplier['name'] ?? 'Dashboard') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        .nav-link:hover {
            color: white !important;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #667eea;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="?page=dashboard&id=<?= $supplier['id'] ?>">
            🏪 The Vape Shed - Supplier Portal
        </a>
        
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="?page=dashboard&id=<?= $supplier['id'] ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=orders&id=<?= $supplier['id'] ?>">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=warranty&id=<?= $supplier['id'] ?>">
                        <i class="fas fa-tools"></i> Warranty
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=downloads&id=<?= $supplier['id'] ?>">
                        <i class="fas fa-download"></i> Downloads
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=reports&id=<?= $supplier['id'] ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($supplier['name']) ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="?page=account&id=<?= $supplier['id'] ?>">
                            <i class="fas fa-cog"></i> Account Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="?page=logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
