# 05 - Frontend Patterns

**UI components, JavaScript patterns, and demo-to-production migration**

---

## Table of Contents

1. [Tech Stack](#tech-stack)
2. [Component Structure](#component-structure)
3. [JavaScript Patterns](#javascript-patterns)
4. [Chart.js Integration](#chartjs-integration)
5. [AJAX Request Pattern](#ajax-request-pattern)
6. [Demo to Production Migration](#demo-to-production-migration)
7. [CSS Architecture](#css-architecture)

---

## Tech Stack

### Frontend Technologies

- **Bootstrap 5.3.0** - UI framework
- **Chart.js 3.9.1** - Data visualization
- **jQuery 3.6.0** - DOM manipulation & AJAX
- **Font Awesome 6.4.0** - Icons
- **Professional Black Theme** - Custom dark theme

### Color Palette

```css
/* Professional Black Theme */
--sidebar-bg: #0a0a0a;       /* Pure black sidebar */
--accent-primary: #3b82f6;   /* Blue accent */
--accent-success: #10b981;   /* Green success */
--accent-warning: #f59e0b;   /* Orange warning */
--accent-danger: #ef4444;    /* Red danger */
--text-light: #f9fafb;       /* Light text */
--text-muted: #9ca3af;       /* Muted text */
--border-color: #1f2937;     /* Dark borders */
```

---

## Component Structure

### Page Template Layout

Every page follows this structure:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
requireAuth();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - Supplier Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="/supplier/assets/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="/supplier/assets/css/fontawesome.min.css" rel="stylesheet">
    
    <!-- Professional Black Theme -->
    <link href="/supplier/assets/css/professional-black.css" rel="stylesheet">
    
    <!-- Page-specific CSS -->
    <link href="/supplier/assets/css/dashboard.css" rel="stylesheet">
</head>
<body class="dark-theme">
    
    <!-- Top Header (Brand + User Info) -->
    <?php include __DIR__ . '/../components/header-top.php'; ?>
    
    <!-- Bottom Header (Navigation Tabs) -->
    <?php include __DIR__ . '/../components/header-bottom.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (left column) -->
            <div class="col-md-3 col-lg-2 sidebar">
                <?php include __DIR__ . '/../components/sidebar.php'; ?>
            </div>
            
            <!-- Main Content (right column) -->
            <div class="col-md-9 col-lg-10 main-content">
                <h1><?= e($pageTitle) ?></h1>
                
                <!-- Page content here -->
                
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="/supplier/assets/js/jquery.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="/supplier/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js (if needed) -->
    <script src="/supplier/assets/js/chart.min.js"></script>
    
    <!-- Page-specific JS -->
    <script src="/supplier/assets/js/dashboard.js"></script>
</body>
</html>
```

### Header Top Component (`components/header-top.php`)

```php
<header class="header-top">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-6">
                <div class="brand">
                    <img src="/supplier/assets/images/logo.png" alt="The Vape Shed" height="40">
                    <span class="brand-name">Supplier Portal</span>
                </div>
            </div>
            <div class="col-6 text-end">
                <div class="user-info">
                    <span class="supplier-name"><?= e(Auth::getSupplierName() ?? 'Supplier') ?></span>
                    <a href="/supplier/logout.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
```

### Header Bottom Component (`components/header-bottom.php`)

```php
<?php
$currentPage = $currentPage ?? 'dashboard';
$tabs = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'tachometer-alt'],
    'orders' => ['label' => 'Orders', 'icon' => 'shopping-cart'],
    'warranty' => ['label' => 'Warranty Claims', 'icon' => 'tools'],
    'inventory' => ['label' => 'Inventory', 'icon' => 'boxes'],
    'reports' => ['label' => 'Reports', 'icon' => 'chart-bar'],
    'account' => ['label' => 'Account', 'icon' => 'user-circle']
];
?>
<nav class="header-bottom">
    <div class="container-fluid">
        <ul class="nav nav-tabs">
            <?php foreach ($tabs as $key => $tab): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === $key ? 'active' : '' ?>" 
                       href="/supplier/tabs/tab-<?= $key ?>.php">
                        <i class="fas fa-<?= $tab['icon'] ?>"></i>
                        <?= e($tab['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
```

### Sidebar Component (`components/sidebar.php`)

```php
<div class="sidebar-widget">
    <h3>Quick Stats</h3>
    <div id="quick-stats-loading">
        <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
    </div>
    <div id="quick-stats" style="display: none;">
        <div class="stat-item">
            <div class="stat-label">Pending Orders</div>
            <div class="stat-value" data-stat="pending_orders">-</div>
        </div>
        <div class="stat-item urgent">
            <div class="stat-label">Urgent Orders</div>
            <div class="stat-value" data-stat="urgent_orders">-</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Pending Claims</div>
            <div class="stat-value" data-stat="pending_claims">-</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value" data-stat="low_stock_products">-</div>
        </div>
    </div>
</div>

<div class="sidebar-widget">
    <h3>Recent Activity</h3>
    <div id="recent-activity-loading">
        <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
    </div>
    <ul id="recent-activity" class="activity-feed" style="display: none;">
        <!-- Populated by JS -->
    </ul>
</div>
```

---

## JavaScript Patterns

### Module Pattern (Recommended)

```javascript
// assets/js/dashboard.js
const Dashboard = (function() {
    'use strict';
    
    // Private variables
    let statsChart = null;
    let refreshInterval = null;
    
    // Private methods
    function init() {
        console.log('Dashboard initializing...');
        loadStats();
        loadCharts();
        loadActivity();
        
        // Auto-refresh every 5 minutes
        refreshInterval = setInterval(loadStats, 300000);
    }
    
    function loadStats() {
        callAPI('dashboard.getStats', { date_range: 30 })
            .then(data => {
                updateStatsUI(data);
            })
            .catch(error => {
                console.error('Failed to load stats:', error);
                showError('Failed to load statistics');
            });
    }
    
    function updateStatsUI(data) {
        $('#total-orders-value').text(data.total_orders);
        $('#total-orders-trend').text(data.total_orders_trend + '%')
            .removeClass('trend-up trend-down')
            .addClass(data.total_orders_trend >= 0 ? 'trend-up' : 'trend-down');
        
        $('#total-revenue-value').text('$' + formatNumber(data.total_revenue));
        // ... update other stats
    }
    
    function loadCharts() {
        callAPI('dashboard.getChartData', { 
            chart_type: 'revenue',
            period: 'monthly',
            months: 6
        })
        .then(data => {
            renderChart('revenue-chart', data);
        });
    }
    
    function renderChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        if (statsChart) {
            statsChart.destroy(); // Destroy previous instance
        }
        
        statsChart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function loadActivity() {
        callAPI('dashboard.getRecentActivity', { limit: 10 })
            .then(data => {
                renderActivity(data);
            });
    }
    
    function renderActivity(activities) {
        const $list = $('#recent-activity');
        $list.empty();
        
        if (activities.length === 0) {
            $list.append('<li class="no-activity">No recent activity</li>');
            return;
        }
        
        activities.forEach(activity => {
            const $item = $(`
                <li class="activity-item">
                    <div class="activity-icon ${activity.color}">
                        <i class="fas fa-${activity.icon}"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">${escapeHtml(activity.title)}</div>
                        <div class="activity-description">${escapeHtml(activity.description)}</div>
                        <div class="activity-time">${formatTimeAgo(activity.timestamp)}</div>
                    </div>
                </li>
            `);
            $list.append($item);
        });
        
        $('#recent-activity-loading').hide();
        $list.show();
    }
    
    // Utility: Format number with commas
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Utility: Format time ago
    function formatTimeAgo(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + ' minutes ago';
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return diffHours + ' hours ago';
        
        const diffDays = Math.floor(diffHours / 24);
        return diffDays + ' days ago';
    }
    
    // Utility: Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showError(message) {
        alert(message); // Replace with better notification system
    }
    
    // Public API
    return {
        init: init
    };
})();

// Initialize on document ready
$(document).ready(function() {
    Dashboard.init();
});
```

---

## Chart.js Integration

### Standard Chart Configuration

```javascript
function createChart(canvasId, data, options = {}) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    color: '#f9fafb',
                    font: {
                        family: "'Inter', sans-serif",
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#f9fafb',
                bodyColor: '#f9fafb',
                borderColor: '#3b82f6',
                borderWidth: 1
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#9ca3af'
                }
            },
            y: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#9ca3af'
                },
                beginAtZero: true
            }
        }
    };
    
    // Merge with custom options
    const finalOptions = {...defaultOptions, ...options};
    
    return new Chart(ctx, {
        type: data.type || 'line',
        data: data,
        options: finalOptions
    });
}
```

### Revenue Chart Example

```javascript
callAPI('dashboard.getChartData', {
    chart_type: 'revenue',
    period: 'monthly',
    months: 6
})
.then(data => {
    createChart('revenue-chart', data, {
        plugins: {
            title: {
                display: true,
                text: 'Revenue Trend (Last 6 Months)',
                color: '#f9fafb',
                font: { size: 16, weight: 'bold' }
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    });
});
```

---

## AJAX Request Pattern

### Unified API Call Function

```javascript
/**
 * Call API endpoint with error handling
 * 
 * @param {string} action - API action (e.g., 'dashboard.getStats')
 * @param {object} params - Parameters object
 * @returns {Promise} - Resolves with data, rejects with error
 */
function callAPI(action, params = {}) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: '/supplier/api/endpoint.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: action,
                params: params
            }),
            success: function(response) {
                if (response.success) {
                    resolve(response.data);
                } else {
                    reject(new Error(response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Handle HTTP errors
                if (xhr.status === 401) {
                    // Redirect to login
                    window.location.href = '/supplier/login.php';
                    return;
                }
                
                let errorMessage = 'Request failed';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.error || errorMessage;
                } catch (e) {
                    errorMessage = error || errorMessage;
                }
                
                reject(new Error(errorMessage));
            }
        });
    });
}
```

### Modern Fetch Alternative

```javascript
async function callAPIFetch(action, params = {}) {
    try {
        const response = await fetch('/supplier/api/endpoint.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Include session cookie
            body: JSON.stringify({ action, params })
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = '/supplier/login.php';
                throw new Error('Session expired');
            }
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'API error');
        }
        
        return data.data;
        
    } catch (error) {
        console.error('API call failed:', error);
        throw error;
    }
}
```

### Usage Examples

```javascript
// Using jQuery promise
callAPI('orders.getOrders', { page: 1, per_page: 25 })
    .then(data => {
        console.log('Orders:', data.orders);
        renderOrdersTable(data.orders);
        renderPagination(data.pagination);
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to load orders', 'error');
    });

// Using async/await
async function loadOrderDetail(orderId) {
    try {
        const order = await callAPIFetch('orders.getOrderDetail', { id: orderId });
        renderOrderDetail(order);
    } catch (error) {
        showNotification('Failed to load order: ' + error.message, 'error');
    }
}
```

---

## Demo to Production Migration

### Migration Requirement

**User requirement:** "1:1 HTML structure match with demo files"

The `/demo/*.html` files contain the finalized UI design. When migrating to production:

### Step 1: Copy HTML Structure

```html
<!-- From demo/dashboard.html -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value">45</div>
            <div class="stat-trend trend-up">+15.2%</div>
        </div>
    </div>
</div>
```

### Step 2: Replace Static Data with PHP/API

```php
<!-- In tabs/tab-dashboard.php -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value" id="total-orders-value">
                <div class="spinner-border spinner-border-sm"></div>
            </div>
            <div class="stat-trend" id="total-orders-trend">-</div>
        </div>
    </div>
</div>

<script>
// Load data via API
callAPI('dashboard.getStats', { date_range: 30 })
    .then(data => {
        $('#total-orders-value').text(data.total_orders);
        $('#total-orders-trend')
            .text(data.total_orders_trend + '%')
            .addClass(data.total_orders_trend >= 0 ? 'trend-up' : 'trend-down');
    });
</script>
```

### Step 3: Keep Exact CSS Classes

```css
/* ✅ CORRECT - Use exact demo classes */
.stats-grid { /* ... */ }
.stat-card { /* ... */ }
.stat-icon { /* ... */ }

/* ❌ WRONG - Don't rename or change structure */
.statistics-container { /* ... */ }
.card-statistic { /* ... */ }
```

### Migration Checklist

- [ ] Copy HTML structure exactly from demo file
- [ ] Replace static text with dynamic IDs
- [ ] Load data via API calls on page load
- [ ] Update DOM with API response
- [ ] Keep all CSS classes identical
- [ ] Test responsive behavior matches demo
- [ ] Verify Chart.js canvas IDs match

---

## CSS Architecture

### Professional Black Theme (`assets/css/professional-black.css`)

```css
:root {
    --sidebar-bg: #0a0a0a;
    --sidebar-text: #f9fafb;
    --sidebar-border: #1f2937;
    
    --accent-primary: #3b82f6;
    --accent-success: #10b981;
    --accent-warning: #f59e0b;
    --accent-danger: #ef4444;
    
    --bg-dark: #111827;
    --bg-darker: #0a0a0a;
    --text-light: #f9fafb;
    --text-muted: #9ca3af;
    --border-color: #1f2937;
}

body.dark-theme {
    background-color: var(--bg-dark);
    color: var(--text-light);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.sidebar {
    background-color: var(--sidebar-bg);
    border-right: 1px solid var(--sidebar-border);
    min-height: calc(100vh - 120px);
    padding: 20px 15px;
}

.sidebar-widget {
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.sidebar-widget h3 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-light);
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
}

.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-light);
}

.stat-item.urgent .stat-value {
    color: var(--accent-danger);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        min-height: auto;
        margin-bottom: 20px;
    }
}
```

### Page-Specific CSS (`assets/css/dashboard.css`)

```css
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.bg-primary { background: var(--accent-primary); color: #fff; }
.stat-icon.bg-success { background: var(--accent-success); color: #fff; }
.stat-icon.bg-warning { background: var(--accent-warning); color: #fff; }
.stat-icon.bg-danger { background: var(--accent-danger); color: #fff; }

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-light);
    margin-bottom: 5px;
}

.stat-trend {
    font-size: 12px;
    font-weight: 600;
}

.stat-trend.trend-up {
    color: var(--accent-success);
}

.stat-trend.trend-down {
    color: var(--accent-danger);
}
```

---

## Best Practices

### 1. Always Use `e()` Helper for Output

```php
<!-- ✅ CORRECT -->
<div><?= e($productName) ?></div>
<input value="<?= e($searchTerm) ?>">

<!-- ❌ WRONG - XSS vulnerability -->
<div><?= $productName ?></div>
<input value="<?= $searchTerm ?>">
```

### 2. Consistent Loading States

```html
<div id="content-loading">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
<div id="content" style="display: none;">
    <!-- Content here -->
</div>

<script>
callAPI('...').then(data => {
    // Hide loading, show content
    $('#content-loading').hide();
    $('#content').show();
});
</script>
```

### 3. Error Handling UI

```javascript
function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const $alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('#notifications-container').append($alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $alert.alert('close');
    }, 5000);
}
```

---

## Next Steps

- **Testing:** [06-TESTING-GUIDE.md](06-TESTING-GUIDE.md)
- **Deployment:** [07-DEPLOYMENT.md](07-DEPLOYMENT.md)
- **Code Snippets:** [09-CODE-SNIPPETS.md](09-CODE-SNIPPETS.md)

---

**Last Updated:** 2025-10-26  
**Related:** [03-API-REFERENCE.md](03-API-REFERENCE.md), [01-ARCHITECTURE.md](01-ARCHITECTURE.md)
