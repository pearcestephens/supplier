/**
 * Dashboard JavaScript
 * Handles all dashboard widgets, charts, and interactions
 *
 * @package Supplier Portal
 * @version 2.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard JS loaded');

    // Initialize all dashboard components
    loadDashboardStats();
    loadOrdersTable();
    loadStockAlerts();
    loadCharts();

    // Initialize legacy sparklines if present
    if (typeof window.dashboardData !== 'undefined' && window.dashboardData.sparkline) {
        initializeLegacySparklines();
    }
});

/**
 * Load dashboard statistics (metric cards)
 */
async function loadDashboardStats() {
    try {
        // Use new unified API handler
        const stats = await API.call('dashboard-stats', {}, {
            loadingElement: '#dashboard-stats-section'
        });

        // Update all metric cards with animation
        updateMetricCard('metric-total-orders', stats.total_orders || 0);
        updateMetricCard('metric-active-products', stats.active_products || 0);
        updateMetricCard('metric-pending-claims', stats.pending_claims || 0);
        updateMetricCard('metric-avg-value', '$' + parseFloat(stats.avg_order_value || 0).toFixed(2));
        updateMetricCard('metric-units-sold', (stats.units_sold || 0).toLocaleString());
        updateMetricCard('metric-revenue', '$' + parseFloat(stats.total_inventory_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        // Update progress bars
        const totalOrdersProgress = document.getElementById('metric-total-orders-progress');
        if (totalOrdersProgress) {
            totalOrdersProgress.style.width = (stats.total_orders_progress || 0) + '%';
        }

        const avgValueProgress = document.getElementById('metric-avg-value-progress');
        if (avgValueProgress) {
            avgValueProgress.style.width = (stats.total_orders_progress || 0) + '%';
        }

        const unitsSoldProgress = document.getElementById('metric-units-sold-progress');
        if (unitsSoldProgress) {
            unitsSoldProgress.style.width = (stats.total_orders_progress || 0) + '%';
        }

        // Update change indicators
        const totalOrdersChange = document.getElementById('metric-total-orders-change');
        if (totalOrdersChange && stats.total_orders_change !== undefined) {
            const changeClass = stats.total_orders_change >= 0 ? 'text-success' : 'text-danger';
            const changeIcon = stats.total_orders_change >= 0 ? '↑' : '↓';
            totalOrdersChange.innerHTML = `<span class="${changeClass}"><i class="fas fa-arrow-${stats.total_orders_change >= 0 ? 'up' : 'down'} me-1"></i>${Math.abs(stats.total_orders_change)}% vs last period</span>`;
        }

        const avgValueChange = document.getElementById('metric-avg-value-change');
        if (avgValueChange) {
            avgValueChange.textContent = 'Healthy order value';
        }

        const unitsSoldChange = document.getElementById('metric-units-sold-change');
        if (unitsSoldChange) {
            unitsSoldChange.textContent = 'On track this month';
        }

        const revenueChange = document.getElementById('metric-revenue-change');
        if (revenueChange) {
            revenueChange.textContent = `✓ Supply Price`;
        }

        // Update products details (in stock vs low stock)
        const productsDetails = document.getElementById('metric-products-details');
        if (productsDetails) {
            productsDetails.innerHTML = `
                <small class="text-success"><i class="fas fa-check-circle me-1"></i>${stats.products_in_stock || 0} in stock</small>
                <small class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>${stats.products_low_stock || 0} low stock</small>
            `;
        }

        const productsAvailability = document.getElementById('metric-products-availability');
        if (productsAvailability) {
            productsAvailability.textContent = `${Math.round(stats.products_availability || 0)}% availability`;
        }

        // Update pending claims badges
        const claimsBadges = document.getElementById('metric-claims-badges');
        if (claimsBadges) {
            const pendings = stats.pending_claims || 0;
            claimsBadges.innerHTML = `
                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>${pendings} Awaiting Inspection</span>
                <span class="badge bg-danger"><i class="fas fa-alert-triangle me-1"></i>Immediate Action</span>
            `;
        }

        const claimsAlert = document.getElementById('metric-claims-alert');
        if (claimsAlert) {
            claimsAlert.textContent = `${stats.pending_claims || 0} claims need review • Click to view details`;
        }

        console.log('✅ Dashboard stats loaded:', stats);
    } catch (error) {
        console.error('❌ Dashboard stats error:', error);
        // Error modal already shown by API handler
        // Show error state in cards
        document.querySelectorAll('.metric-value').forEach(el => {
            if (el) {
                el.textContent = 'Error';
                el.style.color = '#ef4444';
            }
        });
    }
}

/**
 * Update a single metric card with smooth animation
 */
function updateMetricCard(id, value) {
    const element = document.getElementById(id);
    if (!element) return;

    // Remove loading skeleton
    element.classList.remove('skeleton');

    // Animate value change
    element.style.opacity = '0';
    setTimeout(() => {
        element.textContent = value;
        element.style.opacity = '1';
    }, 150);
}

/**
 * Load orders requiring action table
 */
async function loadOrdersTable() {
    try {
        const response = await fetch('/supplier/api/dashboard-orders-table.php');
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load orders');

        const data = result.data;

        // Update last updated time (if element exists)
        const lastUpdatedEl = document.getElementById('orders-last-updated');
        if (lastUpdatedEl) {
            lastUpdatedEl.textContent = '2 hours ago';
        }

        let html = '';
        data.orders.forEach(order => {
            // Status badge colors based on consignment state
            let statusClass = 'bg-secondary';
            switch(order.status) {
                case 'OPEN':
                    statusClass = 'bg-primary';
                    break;
                case 'PACKING':
                    statusClass = 'bg-warning text-dark';
                    break;
                case 'PACKAGED':
                    statusClass = 'bg-info';
                    break;
                case 'SENT':
                    statusClass = 'bg-success';
                    break;
                case 'RECEIVING':
                    statusClass = 'bg-purple';
                    break;
            }

            // Only show PO number if it starts with "POR-" (valid format)
            const poDisplay = order.po_number && order.po_number.startsWith('POR-')
                ? `<strong>${order.po_number}</strong>`
                : '<span class="text-muted">-</span>';

            // Format currency value
            const valueFormatted = order.total_amount
                ? `$${parseFloat(order.total_amount).toFixed(2)}`
                : '$0.00';

            // Add priority/overdue indicators
            let dateClass = 'text-muted';
            let dateIcon = '';
            if (order.is_overdue) {
                dateClass = 'text-danger fw-bold';
                dateIcon = '<i class="fas fa-exclamation-triangle me-1"></i>';
            } else if (order.is_priority) {
                dateClass = 'text-warning fw-bold';
                dateIcon = '<i class="fas fa-clock me-1"></i>';
            }

            html += `
                <tr data-order-id="${order.id}">
                    <td>
                        <input type="checkbox" class="form-check-input order-checkbox" value="${order.id}">
                    </td>
                    <td>
                        ${poDisplay}
                        <br>
                        <small class="text-muted">${order.outlet}</small>
                    </td>
                    <td>${order.outlet}</td>
                    <td>
                        <span class="badge ${statusClass}">
                            ${order.status}
                        </span>
                    </td>
                    <td class="text-center">${order.items_count || 0}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary">${order.units_count || 0}</span>
                    </td>
                    <td class="text-end"><strong>${valueFormatted}</strong></td>
                    <td class="text-muted small">${order.created_at || 'N/A'}</td>
                    <td class="${dateClass} small">
                        ${dateIcon}${order.due_date || 'No due date'}
                    </td>
                    <td class="text-center">
                        <button class="btn btn-primary btn-sm action-btn" data-order-id="${order.id}">
                            <i class="fas fa-arrow-right me-1"></i>
                            Take Action
                        </button>
                    </td>
                </tr>
            `;
        });

        const ordersTableBody = document.getElementById('orders-table-body');
        if (ordersTableBody) {
            ordersTableBody.innerHTML = html;
        }

        // Add click handlers to action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const orderId = this.dataset.orderId;
                window.location.href = '/supplier/orders.php?id=' + orderId;
            });
        });

        // Add click handlers to table rows
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', function() {
                const orderId = this.dataset.orderId;
                window.location.href = '/supplier/orders.php?id=' + orderId;
            });
        });

        console.log('✅ Orders table loaded');
    } catch (error) {
        console.error('❌ Orders table error:', error);
        const ordersTableBody = document.getElementById('orders-table-body');
        if (ordersTableBody) {
            ordersTableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading orders
                    </td>
                </tr>
            `;
        }
    }
}

/**
 * Load stock alerts by store
 */
async function loadStockAlerts() {
    try {
        const response = await fetch('/supplier/api/dashboard-stock-alerts.php');
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load stock alerts');

        const stores = result.stores || [];
        const alerts = result.alerts || [];

        // Update last updated time
        const alertsLastUpdatedEl = document.getElementById('alerts-last-updated');
        if (alertsLastUpdatedEl) {
            alertsLastUpdatedEl.textContent = 'just now';
        }

        // Update total stores count
        const alertsTotalStoresEl = document.getElementById('alerts-total-stores');
        if (alertsTotalStoresEl) {
            alertsTotalStoresEl.textContent = result.total_stores || 0;
        }

        // Update alerts count
        const alertsCountEl = document.getElementById('alerts-count');
        if (alertsCountEl) {
            alertsCountEl.textContent = alerts.length;
        }

        let html = '';

        if (stores.length === 0) {
            html = `
                <div class="text-center py-4 text-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>All stores well-stocked!</h5>
                    <p class="text-muted">No low inventory alerts based on sales velocity</p>
                </div>
            `;
        } else {
            stores.forEach(store => {
                const severityClass = store.severity;
                const badgeClass = store.severity === 'critical' ? 'bg-danger' :
                                   store.severity === 'high' ? 'bg-warning text-dark' : 'bg-info';
                const iconClass = store.severity === 'critical' ? 'fa-exclamation-circle' :
                                 store.severity === 'high' ? 'fa-exclamation-triangle' : 'fa-info-circle';
                const btnClass = store.severity === 'critical' ? 'btn-danger' :
                                store.severity === 'high' ? 'btn-warning' : 'btn-info';
                const outOfStockClass = store.severity === 'critical' ? 'text-danger' :
                                       store.severity === 'high' ? 'text-warning' : 'text-info';

                const lowStock = parseInt(store.low_stock) || 0;
                const outOfStock = parseInt(store.out_of_stock) || 0;
                const daysLeft = parseInt(store.days_until_stockout) || 0;

                html += `
                    <div class="stock-alert-card ${severityClass} clickable">
                        <div class="store-header">
                            <div>
                                <h6 class="store-name mb-0">
                                    <i class="fas fa-store-alt me-2"></i>
                                    ${store.outlet_name}
                                </h6>
                                <span class="badge ${badgeClass} mt-1">${store.severity.charAt(0).toUpperCase() + store.severity.slice(1)}</span>
                                ${daysLeft < 999 ? `<small class="text-muted d-block mt-1">~${daysLeft} days until stockout</small>` : ''}
                            </div>
                            <div class="alert-icon">
                                <i class="fas ${iconClass}"></i>
                            </div>
                        </div>
                        <div class="stock-metrics">
                            <div class="metric">
                                <span class="metric-value">${lowStock.toLocaleString()}</span>
                                <span class="metric-label">Low Stock Items</span>
                            </div>
                            <div class="metric">
                                <span class="metric-value ${outOfStockClass}">${outOfStock.toLocaleString()}</span>
                                <span class="metric-label">Out of Stock</span>
                            </div>
                        </div>
                        <button class="btn btn-sm ${btnClass} btn-block mt-2">
                            <i class="fas fa-box me-1"></i>
                            View Products
                        </button>
                    </div>
                `;
            });
        }

        const stockAlertsGrid = document.getElementById('stock-alerts-grid');
        if (stockAlertsGrid) {
            stockAlertsGrid.innerHTML = html;
        }

        console.log('✅ Stock alerts loaded (sales velocity-based):', {
            stores: stores.length,
            alerts: alerts.length,
            algorithm: result.algorithm
        });
    } catch (error) {
        console.error('❌ Stock alerts error:', error);
        const stockAlertsGrid = document.getElementById('stock-alerts-grid');
        if (stockAlertsGrid) {
            stockAlertsGrid.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <p class="mb-0">Error loading stock alerts</p>
                    <small class="text-muted">${error.message}</small>
                </div>
            `;
        }
    }
}

/**
 * Load and initialize Chart.js charts
 * Uses separate API endpoints for each chart (AJAX loaded)
 */
async function loadCharts() {
    // Load both charts in parallel
    await Promise.all([
        loadItemsSoldChart(),
        loadWarrantyClaimsChart()
    ]);
}

/**
 * Load Items Sold Chart (Last 3 Months)
 */
async function loadItemsSoldChart() {
    try {
        const itemsSoldCanvas = document.getElementById('itemsSoldChart');
        if (!itemsSoldCanvas) {
            console.warn('⚠️ itemsSoldChart canvas not found, skipping');
            return;
        }

        const response = await fetch('/supplier/api/dashboard-items-sold.php');
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load items sold');

        const chartData = result.chart_data;

        const itemsSoldCtx = itemsSoldCanvas.getContext('2d');
        new Chart(itemsSoldCtx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000) {
                                    return (value / 1000).toFixed(1) + 'k';
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        });

        console.log('✅ Items Sold chart loaded:', result.summary);
    } catch (error) {
        console.error('❌ Items Sold chart error:', error);
        const itemsSoldCanvas = document.getElementById('itemsSoldChart');
        if (itemsSoldCanvas) {
            const ctx = itemsSoldCanvas.getContext('2d');
            ctx.font = '14px Arial';
            ctx.fillStyle = '#ef4444';
            ctx.textAlign = 'center';
            ctx.fillText('Error loading chart', itemsSoldCanvas.width / 2, itemsSoldCanvas.height / 2);
        }
    }
}

/**
 * Load Warranty Claims Chart (Last 6 Months)
 */
async function loadWarrantyClaimsChart() {
    try {
        const warrantyCanvas = document.getElementById('warrantyChart');
        if (!warrantyCanvas) {
            console.warn('⚠️ warrantyChart canvas not found, skipping');
            return;
        }

        const response = await fetch('/supplier/api/dashboard-warranty-claims.php');
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load warranty claims');

        const chartData = result.chart_data;

        const warrantyCtx = warrantyCanvas.getContext('2d');
        new Chart(warrantyCtx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' claims';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        console.log('✅ Warranty Claims chart loaded:', result.summary);
    } catch (error) {
        console.error('❌ Warranty Claims chart error:', error);
        const warrantyCanvas = document.getElementById('warrantyChart');
        if (warrantyCanvas) {
            const ctx = warrantyCanvas.getContext('2d');
            ctx.font = '14px Arial';
            ctx.fillStyle = '#ef4444';
            ctx.textAlign = 'center';
            ctx.fillText('Error loading chart', warrantyCanvas.width / 2, warrantyCanvas.height / 2);
        }
    }
}

/**
 * Initialize legacy sparkline charts (for backward compatibility)
 */
function initializeLegacySparklines() {
    if (typeof Chart === 'undefined') return;
    const data = window.dashboardData.sparkline;
    if (!data) return;

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { line: { tension: 0.4, borderWidth: 2 }, point: { radius: 0 } }
    };

    const initSpark = (id, cfg) => {
        const el = document.getElementById(id);
        if (!el || !cfg) return;
        const labels = new Array(cfg.series.length).fill('');
        new Chart(el, {
            type: 'line',
            data: { labels, datasets: [{ data: cfg.series, borderColor: cfg.color, backgroundColor: cfg.fill, fill: true }] },
            options: baseOptions
        });
    };

    initSpark('ordersSparkline', data.orders);
    initSpark('claimsSparkline', data.claims);
    initSpark('revenueSparkline', data.revenue);
    initSpark('productsSparkline', data.products);
}

/**
 * Initialize Flip Card Area Charts
 * Large, visible graphs for enhanced metric cards
 */
function initializeFlipCardCharts() {
    if (typeof Chart === 'undefined') {
        console.warn('⚠️ Chart.js not loaded, skipping flip card charts');
        return;
    }

    const charts = [
        { id: 'chart-1', color: '#000000', isPercentage: false },
        { id: 'chart-2', color: '#000000', isPercentage: false },
        { id: 'chart-3', color: '#000000', isPercentage: true },
        { id: 'chart-4', color: '#000000', isPercentage: false },
        { id: 'chart-5', color: '#000000', isPercentage: false },
        { id: 'chart-6', color: '#000000', isPercentage: false }
    ];

    function generateAreaChart(canvasElement, colorHex, isPercentage) {
        const data = [8, 12, 18, 22, 28, 35, 42, 50];
        const chartContainer = canvasElement.parentElement;

        // Hide chart if NOT a percentage metric OR if all data is 0
        const isAllZero = data.every(val => val === 0 || val === null || val === undefined);

        if (!isPercentage || isAllZero) {
            chartContainer.style.display = 'none';
            return;
        }

        chartContainer.style.display = 'block';
        const ctx = canvasElement.getContext('2d');

        // Determine scale
        const maxValue = Math.max(...data, 1);
        const yAxisMax = Math.ceil(maxValue * 1.2);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Today'],
                datasets: [{
                    label: 'Trend',
                    data: data,
                    borderColor: colorHex,
                    backgroundColor: colorHex + '20',
                    fill: true,
                    tension: 0.6,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: colorHex,
                    pointBorderWidth: 2,
                    borderWidth: 2,
                    clip: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0,0,0,0.7)',
                        padding: 8,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        corners: 8,
                        displayColors: false
                    },
                    filler: { propagate: true }
                },
                scales: {
                    x: {
                        display: true,
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.5)',
                            font: { size: 9, weight: '600' }
                        }
                    },
                    y: {
                        display: true,
                        beginAtZero: true,
                        min: 0,
                        max: yAxisMax,
                        grid: {
                            display: true,
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.4)',
                            font: { size: 8, weight: '600' },
                            stepSize: Math.ceil(yAxisMax / 4)
                        }
                    }
                }
            }
        });
    }

    // Initialize all charts
    window.addEventListener('load', () => {
        charts.forEach(chart => {
            const canvas = document.getElementById(chart.id);
            if (canvas) {
                const container = canvas.parentElement;
                canvas.width = container.offsetWidth;
                canvas.height = 90;
                generateAreaChart(canvas, chart.color, chart.isPercentage);
            }
        });
    });

    console.log('✅ Flip card charts initialized');
}

// Initialize flip card charts on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeFlipCardCharts();
});
