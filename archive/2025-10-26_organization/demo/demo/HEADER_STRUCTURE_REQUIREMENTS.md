# Supplier Portal Pages - Header Structure Requirements

## Dashboard Header Structure (CORRECT - Use as Template)

```html
<!-- TWO-LAYER HEADER -->
<div class="page-wrapper">
    
    <!-- HEADER TOP LAYER - Branding, Notifications, User -->
    <header class="header-top">
        <div class="header-top-left">
            <h2 class="header-title">[PAGE TITLE]</h2>
            <p class="header-subtitle">[SUBTITLE TEXT]</p>
        </div>
        
        <div class="header-top-right">
            <!-- Search -->
            <button class="header-action-btn" title="Search">
                <i class="fa-solid fa-search"></i>
            </button>
            
            <!-- Notifications -->
            <button class="header-action-btn" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="badge-notification"></span>
            </button>
            
            <!-- User Dropdown -->
            <div class="user-dropdown">
                <img src="https://ui-avatars.com/api/?name=ACME+Vape+Co&background=3b82f6&color=fff&size=40" 
                     alt="ACME Vape Co." 
                     class="user-avatar">
                <div class="user-info">
                    <p class="user-name">ACME Vape Co.</p>
                    <p class="user-role">Supplier Account</p>
                </div>
                <i class="fa-solid fa-chevron-down ms-2"></i>
            </div>
        </div>
    </header>
    
    <!-- HEADER BOTTOM LAYER - Breadcrumb, Page Actions -->
    <header class="header-bottom">
        <div class="breadcrumb-nav">
            <ul class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="index.html">
                        <i class="fa-solid fa-home"></i>
                    </a>
                </li>
                <li class="breadcrumb-separator">/</li>
                <li class="breadcrumb-item active">[PAGE NAME]</li>
            </ul>
        </div>
        
        <div class="header-bottom-actions">
            <!-- Page-specific action buttons -->
        </div>
    </header>
    
    <!-- PAGE BODY -->
    <div class="page-body">
        <!-- Content here -->
    </div>
</div>
```

## Pages That Need Fixing

### ✅ DONE:
- index.html (Dashboard) - PERFECT TEMPLATE
- warranty.html - Has proper structure
- downloads.html - Has proper structure  

### ❌ NEEDS FIXING:
- **orders.html** - Has simple header, needs two-layer
- **reports.html** - Has simple header, needs two-layer  
- **account.html** - Has simple header, needs two-layer

## Page-Specific Headers

### Orders Page:
```
Title: Purchase Orders
Subtitle: Track and manage all store orders
Breadcrumb: Home / Purchase Orders
Actions: [Export Orders] [Bulk Actions]
```

### Reports Page:
```
Title: Reports & Analytics
Subtitle: 30-day performance insights and trends
Breadcrumb: Home / Reports
Actions: [Date Range] [Export Report] [Schedule Report]
```

### Account Page:
```
Title: Account Settings
Subtitle: Manage your supplier profile and preferences
Breadcrumb: Home / Account Settings
Actions: [Save Changes] [Reset Password]
```

## Required CSS
All pages must include:
- `/supplier/assets/css/professional-black.css` (BLACK SIDEBAR)
- `assets/css/demo-additions.css` (WIDGETS)

## Sidebar Structure (ALL PAGES)
```html
<aside class="navbar-vertical">
    <div class="navbar-brand">
        <img src="/supplier/assets/images/logo.jpg" alt="The Vape Shed" class="brand-logo">
    </div>
    
    <ul class="navbar-nav">
        <li class="nav-item [active]">
            <a class="nav-link" href="[page].html">
                <i class="fa-solid fa-[icon] nav-link-icon"></i>
                <span>[Page Name]</span>
            </a>
        </li>
    </ul>
    
    <!-- Sidebar Widget (page-specific stats) -->
    <div class="sidebar-widget mt-4 px-3">
        ...
    </div>
</aside>
```

## Action Required
Need to rebuild orders.html, reports.html, and account.html with:
1. Proper black sidebar (navbar-vertical)
2. Two-layer header (header-top + header-bottom)
3. Professional styling matching dashboard
4. All existing functionality preserved
