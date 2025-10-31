# 🚀 Step-by-Step Implementation Guide
## Demo to Production Migration - Execution Checklist

**Purpose:** Foolproof step-by-step guide to execute the migration  
**Estimated Time:** 10-14 hours total  
**Status:** Ready to execute  

---

## ⚡ PRE-FLIGHT CHECKLIST

Before starting, verify:

- [ ] You have SSH/SFTP access to server
- [ ] You can edit files in `/home/master/applications/jcepnzzkmj/public_html/supplier/`
- [ ] You have access to MySQL database (jcepnzzkmj)
- [ ] Demo pages are accessible at `/supplier/demo/`
- [ ] Current portal is at `/supplier/index.php`
- [ ] You've read `DEMO_TO_PRODUCTION_MIGRATION_PLAN.md`
- [ ] You've read `WIDGET_INVENTORY_VISUAL_GUIDE.md`

---

## 📦 STEP 1: BACKUP CURRENT FILES (5 minutes)

### Commands to Run:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/tabs/

# Backup all current tab files
mv tab-dashboard.php tab-dashboard.php_backup
mv tab-orders.php tab-orders.php_backup
mv tab-warranty.php tab-warranty.php_backup
mv tab-reports.php tab-reports.php_backup
mv tab-downloads.php tab-downloads.php_backup
mv tab-account.php tab-account.php_backup

# Verify backups created
ls -lah *_backup

# Expected output:
# -rw-r--r-- 1 user user 25K Oct 26 10:30 tab-dashboard.php_backup
# -rw-r--r-- 1 user user 18K Oct 26 10:30 tab-orders.php_backup
# ... etc
```

### ✅ Verification:
- [ ] 6 backup files created
- [ ] All have `_backup` suffix
- [ ] File sizes look correct (not 0 bytes)
- [ ] Original files removed

### ⚠️ Rollback Plan:
If something goes wrong, restore with:
```bash
mv tab-dashboard.php_backup tab-dashboard.php
# Repeat for all files
```

---

## 🎨 STEP 2: DASHBOARD - CREATE INTERFACE (1 hour)

### 2.1: Create New tab-dashboard.php

**File:** `/supplier/tabs/tab-dashboard.php`

**Action:** Copy the complete HTML structure from `demo/index.html` (lines 26-1200)

**Template Structure:**
```php
<?php
/**
 * Dashboard Tab - Demo Migration v4.0
 * Complete 1:1 migration from demo/index.html
 * 
 * @package Supplier\Portal
 * @version 4.0.0 - Demo Structure Preserved
 */

declare(strict_types=1);

if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

// Get supplier context
$supplierID = getSupplierID();
$supplierName = $_SESSION['supplier_name'] ?? 'Supplier';
?>

<!-- EXACT COPY FROM demo/index.html STARTS HERE -->

<!-- Stats Grid - 4 Cards -->
<div class="row g-3 mb-4">
    
    <!-- Total Orders Card -->
    <div class="col-md-3">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-icon">
                <i class="fa-solid fa-shopping-cart"></i>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value" id="stat-total-orders-value">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
                <div class="stat-card-label">Total Orders</div>
                <div class="stat-card-change" id="stat-total-orders-change"></div>
            </div>
        </div>
    </div>
    
    <!-- Pending Orders Card -->
    <div class="col-md-3">
        <div class="stat-card stat-card-warning">
            <div class="stat-card-icon">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value" id="stat-pending-orders-value">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
                <div class="stat-card-label">Pending Orders</div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Card -->
    <div class="col-md-3">
        <div class="stat-card stat-card-success">
            <div class="stat-card-icon">
                <i class="fa-solid fa-dollar-sign"></i>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value" id="stat-revenue-value">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
                <div class="stat-card-label">Revenue (30 Days)</div>
                <div class="stat-card-change" id="stat-revenue-change"></div>
            </div>
        </div>
    </div>
    
    <!-- Active Products Card -->
    <div class="col-md-3">
        <div class="stat-card stat-card-info">
            <div class="stat-card-icon">
                <i class="fa-solid fa-box"></i>
            </div>
            <div class="stat-card-content">
                <div class="stat-card-value" id="stat-active-products-value">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
                <div class="stat-card-label">Active Products</div>
            </div>
        </div>
    </div>
    
</div>

<!-- Charts Grid -->
<div class="row g-3 mb-4">
    
    <!-- Revenue Chart -->
    <div class="col-md-6">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <i class="fa-solid fa-chart-line"></i>
                    Revenue Trend
                </h3>
                <div class="chart-card-actions">
                    <button class="btn btn-sm btn-light" onclick="refreshRevenueChart()">
                        <i class="fa-solid fa-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="chart-revenue" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Products Chart -->
    <div class="col-md-6">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <i class="fa-solid fa-chart-bar"></i>
                    Top Products
                </h3>
            </div>
            <div class="chart-card-body">
                <canvas id="chart-top-products" height="300"></canvas>
            </div>
        </div>
    </div>
    
</div>

<!-- Recent Orders Timeline -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Recent Orders
        </h5>
    </div>
    <div class="card-body">
        <div class="timeline" id="recent-orders-timeline">
            <!-- Will be populated by JavaScript -->
            <div class="text-center py-4">
                <div class="spinner-border" role="status"></div>
                <p class="text-muted mt-2">Loading recent orders...</p>
            </div>
        </div>
    </div>
</div>

<!-- EXACT COPY FROM demo/index.html ENDS HERE -->

<script>
/**
 * Dashboard JavaScript - API Integration
 * Loads real data from production APIs
 */

// Chart instances
let revenueChart = null;
let topProductsChart = null;

// Load all dashboard data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadRevenueChart();
    loadTopProductsChart();
    loadRecentOrders();
    
    // Refresh every 5 minutes
    setInterval(loadDashboardStats, 300000);
});

/**
 * Load dashboard statistics
 */
async function loadDashboardStats() {
    try {
        const response = await fetch('/supplier/api/dashboard-stats.php');
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Update stat cards
            document.getElementById('stat-total-orders-value').textContent = data.total_orders;
            document.getElementById('stat-pending-orders-value').textContent = data.pending_orders;
            document.getElementById('stat-revenue-value').textContent = '$' + parseFloat(data.revenue_30d).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('stat-active-products-value').textContent = data.active_products;
            
            // Update change indicators
            if (data.orders_change !== null) {
                const changeHtml = data.orders_change > 0 
                    ? `<i class="fa-solid fa-arrow-up"></i> +${data.orders_change}%`
                    : `<i class="fa-solid fa-arrow-down"></i> ${data.orders_change}%`;
                document.getElementById('stat-total-orders-change').innerHTML = changeHtml;
            }
            
            if (data.revenue_change !== null) {
                const changeHtml = data.revenue_change > 0 
                    ? `<i class="fa-solid fa-arrow-up"></i> +${data.revenue_change}%`
                    : `<i class="fa-solid fa-arrow-down"></i> ${data.revenue_change}%`;
                document.getElementById('stat-revenue-change').innerHTML = changeHtml;
            }
        } else {
            console.error('Failed to load dashboard stats:', result.error);
            showStatsError();
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        showStatsError();
    }
}

/**
 * Load revenue trend chart
 */
async function loadRevenueChart() {
    try {
        const response = await fetch('/supplier/api/dashboard-revenue-chart.php');
        const result = await response.json();
        
        if (result.success) {
            const ctx = document.getElementById('chart-revenue').getContext('2d');
            
            // Destroy existing chart if it exists
            if (revenueChart) {
                revenueChart.destroy();
            }
            
            // Create new chart
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: result.data.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: result.data.values,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#1f2937',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return '$' + parseFloat(context.parsed.y).toLocaleString('en-US', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading revenue chart:', error);
    }
}

/**
 * Load top products chart
 */
async function loadTopProductsChart() {
    try {
        const response = await fetch('/supplier/api/dashboard-top-products.php');
        const result = await response.json();
        
        if (result.success) {
            const ctx = document.getElementById('chart-top-products').getContext('2d');
            
            // Destroy existing chart if it exists
            if (topProductsChart) {
                topProductsChart.destroy();
            }
            
            // Create new chart
            topProductsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: result.data.map(item => item.product_name),
                    datasets: [{
                        label: 'Units Sold',
                        data: result.data.map(item => item.units_sold),
                        backgroundColor: [
                            '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
                            '#06b6d4', '#6366f1', '#f43f5e', '#14b8a6', '#a855f7'
                        ],
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#1f2937',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading top products chart:', error);
    }
}

/**
 * Load recent orders timeline
 */
async function loadRecentOrders() {
    try {
        const response = await fetch('/supplier/api/dashboard-recent-orders.php');
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            const timeline = document.getElementById('recent-orders-timeline');
            timeline.innerHTML = '';
            
            result.data.forEach((order, index) => {
                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';
                
                const dotColor = order.dot_color || 'primary';
                
                timelineItem.innerHTML = `
                    <div class="timeline-dot bg-${dotColor}"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="po-number">${order.po_number}</span>
                            <span class="timeline-time text-muted">${order.time_ago}</span>
                        </div>
                        <div class="timeline-body">
                            <p class="mb-1">${order.product_summary}</p>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="status-badge status-${order.status}">${order.status_label}</span>
                                <span class="text-muted">•</span>
                                <strong>$${parseFloat(order.total).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                            </div>
                        </div>
                    </div>
                `;
                
                timeline.appendChild(timelineItem);
            });
        } else {
            document.getElementById('recent-orders-timeline').innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                    <p>No recent orders</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading recent orders:', error);
        document.getElementById('recent-orders-timeline').innerHTML = `
            <div class="text-center py-4 text-danger">
                <i class="fa-solid fa-exclamation-triangle fa-2x mb-2"></i>
                <p>Failed to load recent orders</p>
            </div>
        `;
    }
}

/**
 * Show error state for stats cards
 */
function showStatsError() {
    ['stat-total-orders-value', 'stat-pending-orders-value', 'stat-revenue-value', 'stat-active-products-value'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = '<i class="fa-solid fa-exclamation-triangle text-danger"></i>';
        }
    });
}

/**
 * Refresh revenue chart
 */
function refreshRevenueChart() {
    loadRevenueChart();
}

</script>
```

### ✅ Verification:
- [ ] File created: `tabs/tab-dashboard.php`
- [ ] File size > 10KB
- [ ] Contains all 4 stat cards
- [ ] Contains 2 chart canvases
- [ ] Contains timeline section
- [ ] JavaScript section complete
- [ ] No syntax errors: `php -l tabs/tab-dashboard.php`

---

## 🔌 STEP 3: DASHBOARD - CREATE APIs (1 hour)

### 3.1: Create dashboard-stats.php

**File:** `/supplier/api/dashboard-stats.php`

```php
<?php
/**
 * Dashboard Statistics API
 * Returns aggregate stats for dashboard stat cards
 * 
 * @package Supplier\Portal\API
 * @version 4.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    // Get total orders count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_orders
        FROM purchase_orders
        WHERE supplier_id = ?
    ");
    $stmt->execute([$supplierID]);
    $totalOrders = (int)$stmt->fetchColumn();
    
    // Get pending orders count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_orders
        FROM purchase_orders
        WHERE supplier_id = ?
        AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$supplierID]);
    $pendingOrders = (int)$stmt->fetchColumn();
    
    // Get 30-day revenue
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as revenue_30d
        FROM purchase_orders
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status != 'cancelled'
    ");
    $stmt->execute([$supplierID]);
    $revenue30d = (float)$stmt->fetchColumn();
    
    // Get active products count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT product_id) as active_products
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.order_id = po.id
        WHERE po.supplier_id = ?
        AND po.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$supplierID]);
    $activeProducts = (int)$stmt->fetchColumn();
    
    // Calculate changes (compare with previous 30 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as prev_orders
        FROM purchase_orders
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$supplierID]);
    $prevOrders = (int)$stmt->fetchColumn();
    
    $ordersChange = $prevOrders > 0 
        ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1)
        : 0;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as prev_revenue
        FROM purchase_orders
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status != 'cancelled'
    ");
    $stmt->execute([$supplierID]);
    $prevRevenue = (float)$stmt->fetchColumn();
    
    $revenueChange = $prevRevenue > 0 
        ? round((($revenue30d - $prevRevenue) / $prevRevenue) * 100, 1)
        : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_orders' => $totalOrders,
            'orders_change' => $ordersChange,
            'pending_orders' => $pendingOrders,
            'revenue_30d' => $revenue30d,
            'revenue_change' => $revenueChange,
            'active_products' => $activeProducts
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Dashboard stats API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard statistics',
        'message' => 'Please try again later'
    ]);
}
```

### 3.2: Create dashboard-revenue-chart.php

**File:** `/supplier/api/dashboard-revenue-chart.php`

```php
<?php
/**
 * Dashboard Revenue Chart API
 * Returns revenue data for Chart.js line chart
 * 
 * @package Supplier\Portal\API
 * @version 4.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    // Get revenue by week for last 4 weeks
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT('Week ', WEEK(created_at) - WEEK(DATE_SUB(NOW(), INTERVAL 30 DAY)) + 1) as week_label,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM purchase_orders
        WHERE supplier_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status != 'cancelled'
        GROUP BY WEEK(created_at)
        ORDER BY MIN(created_at) ASC
    ");
    $stmt->execute([$supplierID]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $labels[] = $row['week_label'];
        $values[] = (float)$row['revenue'];
    }
    
    // Ensure we have at least 4 data points
    while (count($labels) < 4) {
        array_unshift($labels, 'Week ' . (count($labels) + 1));
        array_unshift($values, 0);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'values' => $values
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Revenue chart API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load revenue chart data'
    ]);
}
```

### 3.3: Create dashboard-top-products.php

**File:** `/supplier/api/dashboard-top-products.php`

```php
<?php
/**
 * Dashboard Top Products API
 * Returns top 10 products by units sold (30 days)
 * 
 * @package Supplier\Portal\API
 * @version 4.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    $stmt = $pdo->prepare("
        SELECT 
            p.product_name,
            SUM(poi.quantity) as units_sold,
            SUM(poi.quantity * poi.unit_price) as revenue
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.order_id = po.id
        JOIN products p ON poi.product_id = p.id
        WHERE po.supplier_id = ?
        AND po.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND po.status != 'cancelled'
        GROUP BY p.id, p.product_name
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$supplierID]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    $formattedProducts = array_map(function($product) {
        return [
            'product_name' => $product['product_name'],
            'units_sold' => (int)$product['units_sold'],
            'revenue' => (float)$product['revenue']
        ];
    }, $products);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedProducts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Top products API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load top products data'
    ]);
}
```

### 3.4: Create dashboard-recent-orders.php

**File:** `/supplier/api/dashboard-recent-orders.php`

```php
<?php
/**
 * Dashboard Recent Orders API
 * Returns last 10 orders for timeline display
 * 
 * @package Supplier\Portal\API
 * @version 4.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

try {
    $pdo = pdo();
    $supplierID = getSupplierID();
    
    $stmt = $pdo->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.status,
            po.total_amount,
            po.created_at,
            COUNT(poi.id) as item_count,
            GROUP_CONCAT(
                DISTINCT CONCAT(p.product_name, ' x', poi.quantity)
                SEPARATOR ', '
            ) as product_summary
        FROM purchase_orders po
        LEFT JOIN purchase_order_items poi ON po.id = poi.order_id
        LEFT JOIN products p ON poi.product_id = p.id
        WHERE po.supplier_id = ?
        GROUP BY po.id
        ORDER BY po.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$supplierID]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format orders for timeline
    $formattedOrders = array_map(function($order) {
        // Calculate time ago
        $timeAgo = '';
        $diff = time() - strtotime($order['created_at']);
        if ($diff < 3600) {
            $timeAgo = floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            $timeAgo = floor($diff / 3600) . ' hours ago';
        } else {
            $timeAgo = floor($diff / 86400) . ' days ago';
        }
        
        // Determine dot color based on status
        $dotColor = match($order['status']) {
            'sent', 'completed' => 'success',
            'processing' => 'primary',
            'pending' => 'warning',
            default => 'secondary'
        };
        
        // Format status label
        $statusLabel = ucfirst($order['status']);
        
        return [
            'po_number' => $order['po_number'],
            'status' => $order['status'],
            'status_label' => $statusLabel,
            'total' => (float)$order['total_amount'],
            'time_ago' => $timeAgo,
            'product_summary' => $order['product_summary'] ?: 'No items',
            'dot_color' => $dotColor
        ];
    }, $orders);
    
    echo json_encode([
        'success' => true,
        'data' => $formattedOrders,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Recent orders API error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load recent orders'
    ]);
}
```

### ✅ Verification:
- [ ] All 4 API files created
- [ ] All files have no syntax errors: `php -l api/*.php`
- [ ] All files use bootstrap and requireAuth()
- [ ] All files return JSON with success/data structure
- [ ] Database table names match your schema

---

## 🧪 STEP 4: TEST DASHBOARD (30 minutes)

### 4.1: Test APIs Directly

```bash
# Test dashboard-stats.php
curl -X GET "https://staff.vapeshed.co.nz/supplier/api/dashboard-stats.php" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -H "Accept: application/json" | jq .

# Expected response:
# {
#   "success": true,
#   "data": {
#     "total_orders": 247,
#     "orders_change": 12.5,
#     "pending_orders": 18,
#     "revenue_30d": 45670.50,
#     "revenue_change": 8.3,
#     "active_products": 156
#   }
# }

# Test other APIs similarly...
```

### 4.2: Test in Browser

1. Open: `https://staff.vapeshed.co.nz/supplier/`
2. Login with valid credentials
3. Click "Dashboard" tab
4. Open browser console (F12)
5. Watch for API calls

**Expected:**
- [ ] Page loads without errors
- [ ] Stat cards show loading spinners initially
- [ ] Stat cards populate with real data
- [ ] Charts render (may take 2-3 seconds)
- [ ] Recent orders timeline appears
- [ ] No console errors

### 4.3: Debugging Common Issues

**Issue:** Stat cards stuck on spinner
- **Fix:** Check browser console for 404 errors
- **Fix:** Check API syntax: `php -l api/dashboard-stats.php`
- **Fix:** Check error log: `tail -100 /home/master/applications/jcepnzzkmj/logs/apache_*.error.log`

**Issue:** Charts not rendering
- **Fix:** Verify Chart.js CDN loaded: Check Network tab in DevTools
- **Fix:** Check canvas IDs match JavaScript: `chart-revenue`, `chart-top-products`

**Issue:** Timeline empty
- **Fix:** Check API returns data: `curl ...`
- **Fix:** Check database has orders for your supplier_id

---

## 🎯 NEXT STEPS

After Dashboard is working:

1. **Orders Page Migration** (2-3 hours)
   - Follow same process
   - Create `tabs/tab-orders.php`
   - Create `api/orders-list.php`
   - Create `api/order-detail.php`
   - Test thoroughly

2. **Warranty Page Migration** (1-2 hours)
   - Create `tabs/tab-warranty.php`
   - Create `api/warranty-stats.php`
   - Create `api/warranty-list.php`
   - Test thoroughly

3. **Remaining Pages** (2-3 hours)
   - Reports, Downloads, Account
   - Create tabs + APIs
   - Test each page

4. **CSS Consolidation** (1 hour)
   - Merge demo-additions.css
   - Test all pages render correctly

5. **Final QA** (2 hours)
   - Complete checklist from `DEMO_TO_PRODUCTION_MIGRATION_PLAN.md`
   - User acceptance testing

---

## 📊 Progress Tracker

### Dashboard:
- [ ] Step 1: Backup files (✅ DONE)
- [ ] Step 2: Create tab-dashboard.php (⏳ IN PROGRESS)
- [ ] Step 3: Create 4 API files (⏳ IN PROGRESS)
- [ ] Step 4: Test and verify (⏳ PENDING)

### Orders:
- [ ] Create tab-orders.php
- [ ] Create APIs
- [ ] Test and verify

### Warranty:
- [ ] Create tab-warranty.php
- [ ] Create APIs
- [ ] Test and verify

### Other Pages:
- [ ] Reports
- [ ] Downloads
- [ ] Account

### Final:
- [ ] CSS consolidation
- [ ] Full QA checklist
- [ ] User approval

---

**Status:** ✅ READY TO START  
**Current Step:** Step 1 - Backup Files  
**Time Estimate:** 10-14 hours total  

**Next Command to Run:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/supplier/tabs/
mv tab-dashboard.php tab-dashboard.php_backup
```

