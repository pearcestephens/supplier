<?php
/**
 * Dashboard Tab - Phase 3 API-Driven Implementation
 * Modern responsive dashboard with real-time data from API handlers
 * 
 * @package Supplier\Portal
 * @version 3.0.0 - API-driven with tested endpoints
 */

declare(strict_types=1);

if (!defined('TAB_FILE_INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}
?>

<!-- Dashboard Container -->
<div class="dashboard-container">
    
    <!-- Stats Grid - 4 Cards -->
    <div class="stats-grid">
        
        <!-- Total Orders -->
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
        
        <!-- Pending Orders -->
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
        
        <!-- Revenue (30 Days) -->
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
        
        <!-- Active Products -->
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
    
    <!-- Charts Grid - 2 Columns -->
    <div class="charts-grid">
        
        <!-- Revenue Chart -->
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
        
        <!-- Orders Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <h3 class="chart-card-title">
                    <i class="fa-solid fa-shopping-bag"></i>
                    Orders Trend
                </h3>
                <div class="chart-card-actions">
                    <button class="btn btn-sm btn-light" onclick="refreshOrdersChart()">
                        <i class="fa-solid fa-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="chart-orders" height="300"></canvas>
            </div>
        </div>
        
    </div>
    
    <!-- Recent Activity & Quick Stats -->
    <div class="bottom-grid">
        
        <!-- Recent Activity -->
        <div class="activity-card">
            <div class="activity-card-header">
                <h3 class="activity-card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="activity-card-body">
                <div id="recent-activity-list" class="activity-list">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Sidebar -->
        <div class="quick-stats-card">
            <div class="quick-stats-header">
                <h3 class="quick-stats-title">Quick Stats</h3>
            </div>
            <div class="quick-stats-body">
                
                <div class="quick-stat-item">
                    <div class="quick-stat-label">
                        <i class="fa-solid fa-box-open text-warning"></i>
                        Stock Health
                    </div>
                    <div class="quick-stat-value" id="quick-stat-stock-health">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                    </div>
                </div>
                
                <div class="quick-stat-item">
                    <div class="quick-stat-label">
                        <i class="fa-solid fa-calendar-month text-info"></i>
                        This Month
                    </div>
                    <div class="quick-stat-value" id="quick-stat-revenue-month">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                    </div>
                </div>
                
                <div class="quick-stat-item">
                    <div class="quick-stat-label">
                        <i class="fa-solid fa-tools text-danger"></i>
                        Warranty Claims
                    </div>
                    <div class="quick-stat-value" id="quick-stat-warranty-claims">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
    
</div>

<!-- Dashboard Styles -->
<style>
.dashboard-container {
    padding: 1.5rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: #1e1e1e;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stat-card-primary .stat-card-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.stat-card-warning .stat-card-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.stat-card-success .stat-card-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.stat-card-info .stat-card-icon {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
}

.stat-card-content {
    flex: 1;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-card-label {
    font-size: 0.875rem;
    color: #9ca3af;
    font-weight: 500;
}

.stat-card-change {
    font-size: 0.75rem;
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.stat-card-change.trend-up {
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.stat-card-change.trend-down {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.chart-card {
    background: #1e1e1e;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    overflow: hidden;
}

.chart-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #2a2a2a;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chart-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-card-body {
    padding: 1.5rem;
}

/* Bottom Grid */
.bottom-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.activity-card, .quick-stats-card {
    background: #1e1e1e;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    overflow: hidden;
}

.activity-card-header, .quick-stats-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #2a2a2a;
}

.activity-card-title, .quick-stats-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.activity-card-body, .quick-stats-body {
    padding: 1.5rem;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #2a2a2a;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.activity-item:hover {
    background: #333;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.activity-icon.primary { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.activity-icon.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.activity-icon.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.activity-icon.info { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 0.875rem;
    color: #e5e7eb;
    margin-bottom: 0.25rem;
}

.activity-date {
    font-size: 0.75rem;
    color: #6b7280;
}

/* Quick Stats */
.quick-stat-item {
    padding: 1rem;
    background: #2a2a2a;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.quick-stat-item:last-child {
    margin-bottom: 0;
}

.quick-stat-label {
    font-size: 0.875rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quick-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

/* Responsive */
@media (max-width: 1024px) {
    .bottom-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Dashboard JavaScript -->
<script>
(function() {
    'use strict';
    
    console.log('Dashboard JS v3.0.1 - API Envelope Format');
    
    // API endpoint
    const API_BASE = '/supplier/api/endpoint.php';
    
    // Chart instances
    let revenueChart = null;
    let ordersChart = null;
    
    // API Call Helper
    async function callAPI(handler, method, params = {}) {
        try {
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: `${handler}.${method}`,
                    params: params
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'API call failed');
            }
            
            return result.data;
            
        } catch (error) {
            console.error(`API Error [${handler}.${method}]:`, error);
            throw error;
        }
    }
    
    // Load Stats
    async function loadStats() {
        try {
            const stats = await callAPI('dashboard', 'getStats');
            
            // Update stat cards
            document.getElementById('stat-total-orders-value').textContent = stats.total_orders.value;
            document.getElementById('stat-pending-orders-value').textContent = stats.pending_orders;
            document.getElementById('stat-revenue-value').textContent = stats.revenue.formatted;
            document.getElementById('stat-active-products-value').textContent = stats.active_products.value;
            
            // Update change indicators
            if (stats.total_orders.change !== 0) {
                const changeEl = document.getElementById('stat-total-orders-change');
                changeEl.textContent = `${stats.total_orders.change > 0 ? '+' : ''}${stats.total_orders.change}%`;
                changeEl.className = 'stat-card-change trend-' + stats.total_orders.trend;
                changeEl.innerHTML = `<i class="fa-solid fa-arrow-${stats.total_orders.trend === 'up' ? 'up' : 'down'}"></i> ${changeEl.textContent}`;
            }
            
            if (stats.revenue.change !== 0) {
                const changeEl = document.getElementById('stat-revenue-change');
                changeEl.textContent = `${stats.revenue.change > 0 ? '+' : ''}${stats.revenue.change}%`;
                changeEl.className = 'stat-card-change trend-' + stats.revenue.trend;
                changeEl.innerHTML = `<i class="fa-solid fa-arrow-${stats.revenue.trend === 'up' ? 'up' : 'down'}"></i> ${changeEl.textContent}`;
            }
            
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    }
    
    // Load Chart Data
    async function loadCharts() {
        try {
            const chartData = await callAPI('dashboard', 'getChartData', { days: 30 });
            
            // Revenue Chart
            if (revenueChart) {
                revenueChart.destroy();
            }
            
            const revenueCtx = document.getElementById('chart-revenue').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: chartData.revenue.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: chartData.revenue.values,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e1e1e',
                            titleColor: '#fff',
                            bodyColor: '#9ca3af',
                            borderColor: '#2a2a2a',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return '$' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#2a2a2a' },
                            ticks: { color: '#9ca3af' }
                        },
                        y: {
                            grid: { color: '#2a2a2a' },
                            ticks: { 
                                color: '#9ca3af',
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            });
            
            // Orders Chart
            if (ordersChart) {
                ordersChart.destroy();
            }
            
            const ordersCtx = document.getElementById('chart-orders').getContext('2d');
            ordersChart = new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: chartData.orders.labels,
                    datasets: [{
                        label: 'Orders',
                        data: chartData.orders.values,
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e1e1e',
                            titleColor: '#fff',
                            bodyColor: '#9ca3af',
                            borderColor: '#2a2a2a',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#9ca3af' }
                        },
                        y: {
                            grid: { color: '#2a2a2a' },
                            ticks: { 
                                color: '#9ca3af',
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
        } catch (error) {
            console.error('Failed to load charts:', error);
        }
    }
    
    // Load Recent Activity
    async function loadRecentActivity() {
        try {
            const activities = await callAPI('dashboard', 'getRecentActivity', { limit: 10 });
            
            const listEl = document.getElementById('recent-activity-list');
            
            if (activities.length === 0) {
                listEl.innerHTML = '<div class="text-center text-muted py-4">No recent activity</div>';
                return;
            }
            
            listEl.innerHTML = activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon ${activity.color}">
                        <i class="fa-solid fa-shopping-cart"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">${escapeHtml(activity.text)}</div>
                        <div class="activity-date">${escapeHtml(activity.date)}</div>
                    </div>
                </div>
            `).join('');
            
        } catch (error) {
            console.error('Failed to load activity:', error);
            document.getElementById('recent-activity-list').innerHTML = 
                '<div class="text-center text-danger py-4">Failed to load activity</div>';
        }
    }
    
    // Load Quick Stats
    async function loadQuickStats() {
        try {
            const quickStats = await callAPI('dashboard', 'getQuickStats');
            
            document.getElementById('quick-stat-stock-health').textContent = quickStats.stock_health + '%';
            document.getElementById('quick-stat-revenue-month').textContent = quickStats.revenue_month;
            
            // Warranty claims from main stats
            const stats = await callAPI('dashboard', 'getStats');
            document.getElementById('quick-stat-warranty-claims').textContent = stats.warranty_claims.value;
            
        } catch (error) {
            console.error('Failed to load quick stats:', error);
        }
    }
    
    // Helper: Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Refresh Functions (called by buttons)
    window.refreshRevenueChart = function() {
        loadCharts();
    };
    
    window.refreshOrdersChart = function() {
        loadCharts();
    };
    
    // Initialize Dashboard
    async function init() {
        try {
            await Promise.all([
                loadStats(),
                loadCharts(),
                loadRecentActivity(),
                loadQuickStats()
            ]);
        } catch (error) {
            console.error('Dashboard initialization failed:', error);
        }
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
</script>
