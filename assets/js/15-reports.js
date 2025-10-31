/**
 * Advanced Reports & Analytics JavaScript
 * 
 * Features:
 * - Week-by-week navigation
 * - Real-time filtering
 * - Mini sparklines
 * - Forecast visualization
 * - Export functionality
 * - Performance metrics
 * 
 * @package SupplierPortal
 * @version 2.0.0
 */

(function() {
    'use strict';

    // State management
    let state = {
        currentWeek: 0,
        totalWeeks: 0,
        weeklySalesData: [],
        productData: [],
        forecastData: null,
        filters: {
            startDate: null,
            endDate: null,
            productSearch: ''
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Advanced Reports initializing...');
        
        initializeCharts();
        loadReportsData();
        setupEventListeners();
        
        console.log('✅ Reports 2.0 loaded');
    });

    /**
     * Initialize Chart.js charts
     */
    function initializeCharts() {
        // Revenue Trend Chart (existing)
        if (typeof monthlyTrend !== 'undefined' && document.getElementById('revenueTrendChart')) {
            initializeRevenueTrendChart();
        }

        // Status Breakdown Chart (existing)
        if (typeof fulfillmentMetrics !== 'undefined' && document.getElementById('statusBreakdownChart')) {
            initializeStatusBreakdownChart();
        }

        // Forecast Chart (new)
        const forecastCanvas = document.getElementById('forecastChart');
        if (forecastCanvas) {
            loadForecastData();
        }
    }

    /**
     * Load all reports data from APIs
     */
    function loadReportsData() {
        const startDate = document.querySelector('input[name="start_date"]')?.value;
        const endDate = document.querySelector('input[name="end_date"]')?.value;

        if (startDate && endDate) {
            state.filters.startDate = startDate;
            state.filters.endDate = endDate;
        }

        // Load weekly sales summary
        loadWeeklySales();

        // Load product performance
        loadProductPerformance();
    }

    /**
     * Load weekly sales data
     */
    async function loadWeeklySales() {
        try {
            const params = new URLSearchParams({
                start_date: state.filters.startDate || '',
                end_date: state.filters.endDate || ''
            });

            const response = await fetch(`/supplier/api/reports-sales-summary.php?${params}`);
            const result = await response.json();

            if (result.success) {
                state.weeklySalesData = result.data;
                state.totalWeeks = result.data.length;
                state.currentWeek = Math.max(0, state.totalWeeks - 1);
                
                updateWeekNavigation();
                updateWeeklySalesDisplay();
            }
        } catch (error) {
            console.error('Failed to load weekly sales:', error);
        }
    }

    /**
     * Load product performance data
     */
    async function loadProductPerformance() {
        try {
            const params = new URLSearchParams({
                start_date: state.filters.startDate || '',
                end_date: state.filters.endDate || '',
                limit: '50',
                sort_by: 'revenue'
            });

            const response = await fetch(`/supplier/api/reports-product-performance.php?${params}`);
            const result = await response.json();

            if (result.success) {
                state.productData = result.data;
                updateProductPerformanceTable();
            }
        } catch (error) {
            console.error('Failed to load product performance:', error);
        }
    }

    /**
     * Load forecast data
     */
    async function loadForecastData() {
        try {
            const response = await fetch('/supplier/api/reports-forecast.php?weeks=8');
            const result = await response.json();

            if (result.success) {
                state.forecastData = result;
                renderForecastChart();
                updateForecastSummary();
            }
        } catch (error) {
            console.error('Failed to load forecast:', error);
        }
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Week navigation buttons
        document.getElementById('prevWeek')?.addEventListener('click', navigatePreviousWeek);
        document.getElementById('nextWeek')?.addEventListener('click', navigateNextWeek);

        // Export buttons
        document.getElementById('exportCSV')?.addEventListener('click', () => exportReport('csv'));
        document.getElementById('exportExcel')?.addEventListener('click', () => exportReport('excel'));
        document.getElementById('exportPDF')?.addEventListener('click', () => exportReport('pdf'));

        // Product search filter
        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(filterProducts, 300));
        }

        // Refresh button
        document.getElementById('refreshData')?.addEventListener('click', loadReportsData);
    }

    /**
     * Week navigation - Previous
     */
    function navigatePreviousWeek() {
        if (state.currentWeek > 0) {
            state.currentWeek--;
            updateWeekNavigation();
            updateWeeklySalesDisplay();
        }
    }

    /**
     * Week navigation - Next
     */
    function navigateNextWeek() {
        if (state.currentWeek < state.totalWeeks - 1) {
            state.currentWeek++;
            updateWeekNavigation();
            updateWeeklySalesDisplay();
        }
    }

    /**
     * Update week navigation display
     */
    function updateWeekNavigation() {
        const prevBtn = document.getElementById('prevWeek');
        const nextBtn = document.getElementById('nextWeek');
        const weekLabel = document.getElementById('currentWeekLabel');

        if (prevBtn) prevBtn.disabled = state.currentWeek === 0;
        if (nextBtn) nextBtn.disabled = state.currentWeek >= state.totalWeeks - 1;

        if (weekLabel && state.weeklySalesData[state.currentWeek]) {
            const week = state.weeklySalesData[state.currentWeek];
            weekLabel.textContent = `Week of ${week.week_label}`;
        }
    }

    /**
     * Update weekly sales display
     */
    function updateWeeklySalesDisplay() {
        const container = document.getElementById('weeklyStatsContainer');
        if (!container || !state.weeklySalesData[state.currentWeek]) return;

        const week = state.weeklySalesData[state.currentWeek];
        
        container.innerHTML = `
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Orders</div>
                        <div class="metric-value">${week.order_count}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Units Sold</div>
                        <div class="metric-value">${week.total_units.toLocaleString()}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Revenue</div>
                        <div class="metric-value">$${week.total_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Avg Order</div>
                        <div class="metric-value">$${week.avg_order_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update product performance table
     */
    function updateProductPerformanceTable() {
        const tbody = document.getElementById('productPerformanceBody');
        if (!tbody) return;

        const filteredProducts = state.productData.filter(product => {
            if (!state.filters.productSearch) return true;
            const search = state.filters.productSearch.toLowerCase();
            return product.product_name.toLowerCase().includes(search) ||
                   (product.sku && product.sku.toLowerCase().includes(search));
        });

        tbody.innerHTML = filteredProducts.slice(0, 15).map(product => `
            <tr>
                <td>
                    <span class="indicator indicator-${getLifecycleColor(product.lifecycle)}"></span>
                    ${escapeHtml(product.product_name)}
                </td>
                <td><code>${escapeHtml(product.sku)}</code></td>
                <td class="text-center">${product.order_count}</td>
                <td class="text-end">${product.total_units.toLocaleString()}</td>
                <td class="text-end">$${product.total_revenue.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-end">${product.velocity.toFixed(1)}/wk</td>
                <td class="text-end">
                    <span class="trend-arrow trend-${product.growth_rate >= 0 ? 'up' : 'down'}">
                        <i class="fas fa-arrow-${product.growth_rate >= 0 ? 'up' : 'down'}"></i>
                        ${Math.abs(product.growth_rate).toFixed(1)}%
                    </span>
                </td>
                <td>
                    <span class="status-badge status-${product.lifecycle}">${product.lifecycle}</span>
                </td>
                <td>
                    <div class="performance-bar">
                        <div class="performance-bar-fill performance-${getPerformanceLevel(product.performance_score)}" 
                             style="width: ${product.performance_score}%"></div>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Render forecast chart using Chart.js
     */
    function renderForecastChart() {
        const canvas = document.getElementById('forecastChart');
        if (!canvas || !state.forecastData) return;

        const ctx = canvas.getContext('2d');
        const data = state.forecastData;

        // Combine historical and future weeks
        const allWeeks = [...data.weeks.historical, ...data.weeks.future];
        const historicalRevenue = [...data.revenue.historical, ...Array(data.forecast_weeks).fill(null)];
        const forecastRevenue = [...Array(data.historical_weeks).fill(null), ...data.revenue.predictions];
        const upper2sigma = [...Array(data.historical_weeks).fill(null), ...data.revenue.confidence_2sigma.upper];
        const lower2sigma = [...Array(data.historical_weeks).fill(null), ...data.revenue.confidence_2sigma.lower];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: allWeeks.map(w => new Date(w).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [
                    {
                        label: 'Historical Revenue',
                        data: historicalRevenue,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Forecast',
                        data: forecastRevenue,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: 'Upper Confidence (95%)',
                        data: upper2sigma,
                        borderColor: 'rgba(59, 130, 246, 0.3)',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 1,
                        borderDash: [2, 2],
                        fill: '+1',
                        tension: 0.4
                    },
                    {
                        label: 'Lower Confidence (95%)',
                        data: lower2sigma,
                        borderColor: 'rgba(59, 130, 246, 0.3)',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 1,
                        borderDash: [2, 2],
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + 
                                       (context.parsed.y || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
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

    /**
     * Update forecast summary
     */
    function updateForecastSummary() {
        const container = document.getElementById('forecastSummary');
        if (!container || !state.forecastData) return;

        const data = state.forecastData;
        const accuracy = data.accuracy;

        container.innerHTML = `
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Forecast Accuracy</div>
                        <div class="metric-value">${accuracy.accuracy_percent.toFixed(1)}%</div>
                        <div class="metric-change">MAPE: ${accuracy.mape.toFixed(2)}%</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Trend</div>
                        <div class="metric-value">${data.revenue.quality.trend}</div>
                        <div class="metric-change">R²: ${accuracy.r_squared.toFixed(3)}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Forecast Total</div>
                        <div class="metric-value">$${data.summary.forecast_total.toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
                        <div class="metric-change">${data.forecast_weeks} weeks</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card-compact">
                        <div class="metric-label">Std Deviation</div>
                        <div class="metric-value">$${data.summary.std_dev.toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Export report in specified format
     */
    function exportReport(format) {
        const startDate = state.filters.startDate || document.querySelector('input[name="start_date"]')?.value;
        const endDate = state.filters.endDate || document.querySelector('input[name="end_date"]')?.value;
        const reportType = document.querySelector('select[name="report_type"]')?.value || 'sales_summary';

        const params = new URLSearchParams({
            format: format,
            type: reportType,
            start_date: startDate || '',
            end_date: endDate || ''
        });

        window.location.href = `/supplier/api/reports-export.php?${params}`;
    }

    /**
     * Filter products based on search
     */
    function filterProducts() {
        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            state.filters.productSearch = searchInput.value;
            updateProductPerformanceTable();
        }
    }

    /**
     * Get lifecycle indicator color
     */
    function getLifecycleColor(lifecycle) {
        const colors = {
            'growth': 'green',
            'mature': 'amber',
            'decline': 'red',
            'new': 'amber'
        };
        return colors[lifecycle] || 'amber';
    }

    /**
     * Get performance level category
     */
    function getPerformanceLevel(score) {
        if (score >= 80) return 'excellent';
        if (score >= 60) return 'good';
        if (score >= 40) return 'average';
        return 'poor';
    }

    /**
     * Existing chart initialization (from original reports.js)
     */
    function initializeRevenueTrendChart() {
        const revenueTrendCtx = document.getElementById('revenueTrendChart');
        if (!revenueTrendCtx) return;

        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: monthlyTrend.map(m => {
                    const [year, month] = m.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue',
                    data: monthlyTrend.map(m => parseFloat(m.revenue)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
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

    function initializeStatusBreakdownChart() {
        const statusBreakdownCtx = document.getElementById('statusBreakdownChart');
        if (!statusBreakdownCtx) return;

        new Chart(statusBreakdownCtx, {
            type: 'doughnut',
            data: {
                labels: fulfillmentMetrics.map(f => f.state),
                datasets: [{
                    data: fulfillmentMetrics.map(f => parseInt(f.count)),
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b',
                        '#22c55e', '#6b7280', '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    /**
     * Utility: Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Utility: Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
