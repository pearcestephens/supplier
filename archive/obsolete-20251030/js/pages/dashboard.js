/**
 * Dashboard Page Module
 * 
 * Handles dashboard-specific functionality:
 * - Stats loading and rendering
 * - Charts (revenue, orders, products)
 * - Recent activity
 * - Quick stats sidebar
 * - Auto-refresh
 * 
 * @package SupplierPortal
 * @version 3.0.0
 */

const Dashboard = {
    // Configuration
    config: {
        autoRefreshInterval: 60000, // 60 seconds
        chartColors: {
            primary: '#000000',
            secondary: '#fbbf24',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6',
            cyan: '#06b6d4',
            purple: '#8b5cf6'
        }
    },
    
    // Chart instances
    charts: {
        revenue: null,
        orders: null,
        products: null
    },
    
    // Auto-refresh timer
    refreshTimer: null,
    
    /**
     * Initialize dashboard
     */
    init() {
        console.log('Dashboard: Initializing...');
        
        // Load initial data
        this.loadStats();
        this.loadCharts();
        this.loadRecentActivity();
        this.loadQuickStats();
        this.loadPendingOrders();
        
        // Set up auto-refresh
        this.startAutoRefresh();
        
        // Set up event listeners
        this.setupEventListeners();
        
        console.log('Dashboard: Initialized successfully');
    },
    
    /**
     * Load dashboard statistics
     */
    loadStats() {
        SupplierPortal.api('dashboard.getStats', {}, (response) => {
            if (response.success && response.data) {
                this.renderStats(response.data);
            }
        });
    },
    
    /**
     * Render statistics to DOM
     */
    renderStats(stats) {
        // Total Orders
        if (stats.total_orders) {
            $('#stat-total-orders-value').text(stats.total_orders.value);
            $('#stat-total-orders-progress').css('width', Math.min(stats.total_orders.progress || 0, 100) + '%');
            
            const change = stats.total_orders.change_percent || 0;
            const icon = change >= 0 ? 'fa-arrow-up text-success' : 'fa-arrow-down text-danger';
            $('#stat-total-orders-icon').attr('class', 'fas ' + icon + ' me-1');
            $('#stat-total-orders-change').find('strong').text(
                (change >= 0 ? '+' : '') + change.toFixed(1) + '%'
            );
        }
        
        // Active Products
        if (stats.active_products) {
            $('#stat-active-products-value').text(stats.active_products.value);
            $('#stat-products-in-stock').text(stats.active_products.in_stock || 0);
            $('#stat-products-low-stock').text(stats.active_products.low_stock || 0);
            $('#stat-products-availability').text((stats.active_products.availability || 0).toFixed(1));
        }
        
        // Warranty Claims
        if (stats.warranty_claims) {
            $('#stat-warranty-claims-value').text(stats.warranty_claims.value);
            
            const urgent = stats.warranty_claims.urgent || 0;
            const standard = stats.warranty_claims.standard || 0;
            
            let badgesHtml = '';
            if (urgent > 0) {
                badgesHtml += `<span class="badge bg-danger">${urgent} Urgent</span>`;
            }
            if (standard > 0) {
                badgesHtml += `<span class="badge bg-warning text-dark">${standard} Standard</span>`;
            }
            $('#stat-warranty-claims-badges').html(badgesHtml);
            
            if (urgent > 0) {
                $('#stat-warranty-claims-message').html('<i class="fas fa-exclamation-triangle me-1"></i>Urgent claims require attention');
            } else {
                $('#stat-warranty-claims-message').html('<i class="fas fa-check-circle me-1"></i>All claims up to date');
            }
        }
        
        // Average Order Value
        if (stats.avg_order_value) {
            $('#stat-avg-order-value').text(SupplierPortal.formatCurrency(stats.avg_order_value.value));
            $('#stat-avg-order-progress').css('width', Math.min(stats.avg_order_value.progress || 0, 100) + '%');
            
            const change = stats.avg_order_value.change_percent || 0;
            $('#stat-avg-order-change').find('strong').text(
                (change >= 0 ? '+' : '') + change.toFixed(1) + '%'
            );
        }
        
        // Units Sold
        if (stats.units_sold) {
            $('#stat-units-sold-value').text(stats.units_sold.value.toLocaleString());
            $('#stat-units-sold-progress').css('width', Math.min(stats.units_sold.progress || 0, 100) + '%');
            
            const change = stats.units_sold.change_percent || 0;
            $('#stat-units-sold-change').find('strong').text(
                (change >= 0 ? '+' : '') + change.toFixed(1) + '%'
            );
        }
        
        // Fulfillment Time
        if (stats.fulfillment_time) {
            $('#stat-fulfillment-time-value').text(stats.fulfillment_time.value.toFixed(1) + ' days');
            
            const slaPercent = stats.fulfillment_time.sla_percent || 0;
            const slaClass = slaPercent >= 90 ? 'text-success' : (slaPercent >= 70 ? 'text-warning' : 'text-danger');
            $('#stat-fulfillment-sla').text(slaPercent.toFixed(0) + '% on time').attr('class', 'small fw-semibold ' + slaClass);
            
            const improvement = stats.fulfillment_time.improvement || 0;
            if (improvement !== 0) {
                $('#stat-fulfillment-change').find('strong').text(
                    (improvement > 0 ? '-' : '+') + Math.abs(improvement).toFixed(1) + ' days ' + 
                    (improvement > 0 ? 'improved' : 'longer')
                );
            }
        }
    },
    
    /**
     * Load chart data and render charts
     */
    loadCharts() {
        SupplierPortal.api('dashboard.getChartData', {}, (response) => {
            if (response.success && response.data) {
                this.renderRevenueChart(response.data.revenue);
                this.renderOrdersChart(response.data.orders);
                this.renderProductsChart(response.data.products);
            }
        });
    },
    
    /**
     * Render revenue line chart
     */
    renderRevenueChart(data) {
        const ctx = document.getElementById('chart-revenue');
        if (!ctx) return;
        
        if (this.charts.revenue) {
            this.charts.revenue.destroy();
        }
        
        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Revenue',
                    data: data.values || [],
                    borderColor: this.config.chartColors.primary,
                    backgroundColor: 'rgba(0, 0, 0, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
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
                        callbacks: {
                            label: (context) => SupplierPortal.formatCurrency(context.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '$' + (value / 1000).toFixed(0) + 'k'
                        }
                    }
                }
            }
        });
    },
    
    /**
     * Render orders bar chart
     */
    renderOrdersChart(data) {
        const ctx = document.getElementById('chart-orders');
        if (!ctx) return;
        
        if (this.charts.orders) {
            this.charts.orders.destroy();
        }
        
        this.charts.orders = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Orders',
                    data: data.values || [],
                    backgroundColor: this.config.chartColors.info,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    },
    
    /**
     * Render products doughnut chart
     */
    renderProductsChart(data) {
        const ctx = document.getElementById('chart-products');
        if (!ctx) return;
        
        if (this.charts.products) {
            this.charts.products.destroy();
        }
        
        this.charts.products = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels || ['High Stock', 'Medium Stock', 'Low Stock'],
                datasets: [{
                    data: data.values || [0, 0, 0],
                    backgroundColor: [
                        this.config.chartColors.success,
                        this.config.chartColors.warning,
                        this.config.chartColors.danger
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    },
    
    /**
     * Load recent activity for sidebar
     */
    loadRecentActivity() {
        SupplierPortal.api('dashboard.getRecentActivity', {}, (response) => {
            if (response.success && response.data) {
                this.renderRecentActivity(response.data);
            }
        });
    },
    
    /**
     * Render recent activity
     */
    renderRecentActivity(activities) {
        const container = $('#sidebar-activity');
        
        if (!activities || activities.length === 0) {
            container.html('<div class="activity-item"><div class="activity-content"><p class="mb-0 small text-muted">No recent activity</p></div></div>');
            return;
        }
        
        let html = '';
        activities.slice(0, 5).forEach(activity => {
            html += `
                <div class="activity-item">
                    <div class="activity-dot ${activity.color || 'bg-primary'}"></div>
                    <div class="activity-content">
                        <p class="mb-0 small">${activity.text}</p>
                        <span class="text-muted small">${SupplierPortal.formatTimeAgo(activity.date)}</span>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    },
    
    /**
     * Load quick stats for sidebar
     */
    loadQuickStats() {
        SupplierPortal.api('dashboard.getQuickStats', {}, (response) => {
            if (response.success && response.data) {
                this.renderQuickStats(response.data);
            }
        });
    },
    
    /**
     * Render quick stats
     */
    renderQuickStats(stats) {
        const container = $('#sidebar-stats');
        
        if (stats.active_orders) {
            container.find('.stat-item:eq(0) .stat-value').text(stats.active_orders);
        }
        
        if (stats.stock_health) {
            container.find('.stat-item:eq(1) .stat-value').text(stats.stock_health + '%');
            container.find('.stat-item:eq(1) .progress-bar').css('width', stats.stock_health + '%');
        }
        
        if (stats.revenue_month) {
            container.find('.stat-item:eq(2) .stat-value').text(SupplierPortal.formatCurrency(stats.revenue_month));
        }
    },
    
    /**
     * Load pending orders table
     */
    loadPendingOrders() {
        SupplierPortal.api('orders.getPending', { limit: 10 }, (response) => {
            if (response.success && response.data) {
                this.renderPendingOrders(response.data);
            }
        });
    },
    
    /**
     * Render pending orders table
     */
    renderPendingOrders(orders) {
        const tbody = $('#orders-table-body');
        
        if (!orders || orders.length === 0) {
            tbody.html('<tr><td colspan="9" class="text-center text-muted py-4">No orders requiring action</td></tr>');
            return;
        }
        
        let html = '';
        orders.forEach(order => {
            const statusClass = order.status === 'URGENT' ? 'danger' : 'warning';
            const dueDays = Math.ceil((new Date(order.due_date) - new Date()) / (1000 * 60 * 60 * 24));
            const dueDateClass = dueDays <= 2 ? 'text-danger fw-semibold' : '';
            
            html += `
                <tr onclick="window.location.href='/supplier/po-detail.php?id=${order.id}'" style="cursor: pointer;">
                    <td><strong>${order.po_number}</strong></td>
                    <td>${order.outlet_name}</td>
                    <td><span class="badge badge-${statusClass}">${order.status}</span></td>
                    <td class="text-center">${order.items_count}</td>
                    <td class="text-center">${order.units_count}</td>
                    <td class="text-end">${SupplierPortal.formatCurrency(order.total_value)}</td>
                    <td>${SupplierPortal.formatDate(order.created_at)}</td>
                    <td class="${dueDateClass}">${SupplierPortal.formatDate(order.due_date)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); window.location.href='/supplier/po-detail.php?id=${order.id}'">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tbody.html(html);
    },
    
    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            console.log('Dashboard: Auto-refreshing data...');
            this.loadStats();
            this.loadRecentActivity();
            this.loadQuickStats();
            this.loadPendingOrders();
        }, this.config.autoRefreshInterval);
    },
    
    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    },
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Date range filter (if exists)
        $('#date-range-filter').on('change', () => {
            this.loadStats();
            this.loadCharts();
        });
        
        // Export button (if exists)
        $('#btn-export').on('click', () => {
            this.exportDashboard();
        });
        
        // Refresh button (if exists)
        $('#btn-refresh').on('click', () => {
            this.loadStats();
            this.loadCharts();
            this.loadRecentActivity();
            this.loadQuickStats();
            this.loadPendingOrders();
            SupplierPortal.showToast('Dashboard refreshed', 'success', 2000);
        });
    },
    
    /**
     * Export dashboard data
     */
    exportDashboard() {
        SupplierPortal.api('dashboard.export', {}, (response) => {
            if (response.success && response.data && response.data.url) {
                window.location.href = response.data.url;
            } else {
                SupplierPortal.showToast('Export failed', 'danger');
            }
        });
    }
};

// Initialize dashboard when DOM is ready
$(document).ready(() => {
    if ($('#dashboard-page').length > 0) {
        Dashboard.init();
    }
});

// Cleanup on page unload
$(window).on('beforeunload', () => {
    Dashboard.stopAutoRefresh();
});
