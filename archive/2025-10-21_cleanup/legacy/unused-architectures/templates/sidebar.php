<?php
/**
 * Supplier Portal - Sidebar Template
 * 
 * @package CIS\Supplier\Templates
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('SUPPLIER_PORTAL')) {
    die('Direct access not permitted');
}

$currentPage = get_param('page', 'dashboard');
?>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
    
    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= BASE_URL ?>">
        <div class="sidebar-brand-icon">
            <i class="fas fa-store"></i>
        </div>
        <div class="sidebar-brand-text mx-3">
            The Vape Shed<br>
            <small style="font-size: 0.7rem;">Supplier Portal</small>
        </div>
    </a>
    
    <!-- Divider -->
    <hr class="sidebar-divider my-0">
    
    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=dashboard">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>
    
    <!-- Divider -->
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-heading">
        Orders & Claims
    </div>
    
    <!-- Nav Item - Orders -->
    <li class="nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=orders">
            <i class="fas fa-fw fa-shopping-cart"></i>
            <span>Purchase Orders</span>
        </a>
    </li>
    
    <!-- Nav Item - Warranty -->
    <li class="nav-item <?= $currentPage === 'warranty' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=warranty">
            <i class="fas fa-fw fa-shield-alt"></i>
            <span>Warranty Claims</span>
        </a>
    </li>
    
    <!-- Divider -->
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-heading">
        Resources
    </div>
    
    <!-- Nav Item - Downloads -->
    <li class="nav-item <?= $currentPage === 'downloads' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=downloads">
            <i class="fas fa-fw fa-download"></i>
            <span>Downloads</span>
        </a>
    </li>
    
    <!-- Nav Item - Reports -->
    <li class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=reports">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Reports</span>
        </a>
    </li>
    
    <!-- Divider -->
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-heading">
        Settings
    </div>
    
    <!-- Nav Item - Account -->
    <li class="nav-item <?= $currentPage === 'account' ? 'active' : '' ?>">
        <a class="nav-link" href="<?= BASE_URL ?>?page=account">
            <i class="fas fa-fw fa-user-cog"></i>
            <span>Account Settings</span>
        </a>
    </li>
    
    <?php if (FEATURE_NEURO_AI): ?>
    <!-- Nav Item - AI Assistant -->
    <li class="nav-item">
        <a class="nav-link" href="#" onclick="openNeuroAI(); return false;">
            <i class="fas fa-fw fa-robot"></i>
            <span>AI Assistant</span>
        </a>
    </li>
    <?php endif; ?>
    
    <!-- Nav Item - Logout -->
    <li class="nav-item">
        <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal">
            <i class="fas fa-fw fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </li>
    
    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">
    
    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
    
    <!-- Sidebar Footer - Session Info -->
    <div class="sidebar-footer">
        <div class="text-center text-white-50" style="font-size: 0.75rem; padding: 10px;">
            <div><i class="fas fa-user"></i> <?= htmlspecialchars($supplier['supplier_name'] ?? 'Unknown') ?></div>
            <div class="mt-1"><i class="fas fa-clock"></i> Session: <?= format_datetime($supplier['last_activity'] ?? time(), 'H:i') ?></div>
        </div>
    </div>
    
</ul>
<!-- End of Sidebar -->
