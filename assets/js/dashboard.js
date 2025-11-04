/**
 * Dashboard JavaScript
 * Handles all dashboard widgets, charts, and interactions
 *
 * @package Supplier Portal
 * @version 2.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard JS loaded');

    // HIDE dashboard stats section initially (prevent flicker)
    const statsSection = document.getElementById('dashboard-stats-section');
    if (statsSection) {
        statsSection.style.opacity = '0';
        statsSection.style.transition = 'opacity 0.8s ease-in';
    }

    // Load data FIRST, then fade in after it's ready
    loadDashboardStats().then(() => {
        console.log('💫 Data loaded - fading in dashboard...');
        setTimeout(() => {
            if (statsSection) {
                statsSection.style.opacity = '1';
            }
        }, 200); // Small delay for smooth reveal
    }).catch(err => {
        console.error('Error loading dashboard:', err);
        // Still show it even on error
        if (statsSection) {
            statsSection.style.opacity = '1';
        }
    });

    // Load other components
    loadOrdersTable();
    loadStockAlerts();
    loadCharts();

    // Initialize legacy sparklines if present
    if (typeof window.dashboardData !== 'undefined' && window.dashboardData.sparkline) {
        initializeLegacySparklines();
    }
});

// Global chart registry to safely manage Chart.js instances
window.__dashboardCharts = window.__dashboardCharts || {};

/**
 * Destroy an existing Chart.js instance for a given canvas (if any)
 * Supports Chart.js v3+ via Chart.getChart and also our own registry fallback
 */
function destroyExistingChart(canvasEl) {
    if (!canvasEl) return;
    try {
        if (typeof Chart !== 'undefined') {
            // Preferred (v3+)
            if (typeof Chart.getChart === 'function') {
                const existing = Chart.getChart(canvasEl);
                if (existing) {
                    existing.destroy();
                }
            }
        }
    } catch (e) {
        console.warn('Chart destroy (Chart.getChart) warning:', e);
    }

    // Fallback to our registry
    const id = canvasEl.id || '__anonymous_canvas__';
    if (window.__dashboardCharts[id] && typeof window.__dashboardCharts[id].destroy === 'function') {
        try { window.__dashboardCharts[id].destroy(); } catch (e) {}
        delete window.__dashboardCharts[id];
    }
}

// PREVENT DOUBLE-LOADING! (v2 - cache bust)
window.__dashStatsLoading = window.__dashStatsLoading || false;
window.__dashStatsLastLoad = window.__dashStatsLastLoad || 0;

/**
 * Load dashboard statistics (metric cards)
 */
async function loadDashboardStats() {
    console.log('🔄 loadDashboardStats() called at:', new Date().toISOString());
    console.trace('Call stack:');

    // GUARD: Prevent double-loading within 1 second
    const now = Date.now();
    if (window.__dashStatsLoading) {
        console.log('⚠️ BLOCKED: Already loading dashboard stats!');
        return;
    }

    if (now - window.__dashStatsLastLoad < 1000) {
        console.log('⚠️ BLOCKED: Too soon since last load (debounce)');
        return;
    }

    window.__dashStatsLoading = true;
    window.__dashStatsLastLoad = now;

    try {
        // Use new unified API handler
        const stats = await API.call('dashboard-stats', {}, {
            loadingElement: '#dashboard-stats-section'
        });

        console.log('📊 API returned total_orders:', stats.total_orders);

        // Update all metric cards with animation
        updateMetricCard('metric-total-orders', stats.total_orders || 0);
        updateMetricCard('metric-active-products', stats.active_products || 0);
        updateMetricCard('metric-pending-claims', stats.pending_claims || 0);
        updateMetricCard('metric-avg-value', '$' + parseFloat(stats.avg_order_value || 0).toFixed(2));
        updateMetricCard('metric-units-sold', (stats.units_sold || 0).toLocaleString());
        updateMetricCard('metric-revenue', '$' + parseFloat(stats.total_inventory_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        // ========================================================================
        // DYNAMIC SMART PROGRESS BARS with contextual labels
        // ========================================================================

        // Card 1: Total Orders - Dynamic progress toward adaptive target
        const ordersTarget = stats.total_orders_target || 200; // From API or default
        updateSmartProgressBar('metric-total-orders', stats.total_orders, ordersTarget, {
            label: getOrdersLabel(stats.total_orders, ordersTarget),
            color: getProgressColor(stats.total_orders, ordersTarget)
        });

        // Card 2: Active Products - Contextual based on supplier size
        const productsTarget = stats.products_target || 100;
        updateSmartProgressBar('metric-active-products', stats.active_products, productsTarget, {
            label: getProductsLabel(stats.active_products, stats.products_in_stock, stats.products_low_stock),
            color: getProgressColor(stats.active_products, productsTarget)
        });

        // Card 3: Pending Claims - Status-driven labels
        updateSmartProgressBar('metric-pending-claims', stats.pending_claims, 10, {
            label: getClaimsLabel(stats.pending_claims),
            color: getClaimsColor(stats.pending_claims),
            inverse: true // Red when high, green when low
        });

        // Card 4: Avg Order Value - Performance tiers
        const avgValue = parseFloat(stats.avg_order_value || 0);
        updateSmartProgressBar('metric-avg-value', avgValue, 500, {
            label: getValueLabel(avgValue),
            color: getProgressColor(avgValue, 500)
        });

        // Card 5: Units Sold - Volume categories
        updateSmartProgressBar('metric-units-sold', stats.units_sold, 1000, {
            label: getUnitsLabel(stats.units_sold),
            color: getProgressColor(stats.units_sold, 1000)
        });

        // Card 6: Revenue/Inventory - Financial health
        updateSmartProgressBar('metric-revenue', stats.total_inventory_value, 50000, {
            label: getRevenueLabel(stats.total_inventory_value),
            color: getProgressColor(stats.total_inventory_value, 50000)
        });

        // ========================================================================
        // DYNAMIC FLIP-SIDE INSIGHTS - Show cool stats on card backs
        // ========================================================================
        updateCardInsights('metric-total-orders', generateOrdersInsights(stats));
        updateCardInsights('metric-active-products', generateProductsInsights(stats));
        updateCardInsights('metric-pending-claims', generateClaimsInsights(stats));
        updateCardInsights('metric-avg-value', generateValueInsights(stats));
        updateCardInsights('metric-units-sold', generateUnitsInsights(stats));
        updateCardInsights('metric-revenue', generateRevenueInsights(stats));

        // Update change indicators - ONLY show if meaningful data
        const totalOrdersChange = document.getElementById('metric-total-orders-change');
        if (totalOrdersChange) {
            if (stats.total_orders_change && Math.abs(stats.total_orders_change) > 0) {
                const changeClass = stats.total_orders_change >= 0 ? 'success' : 'danger';
                const changeIcon = stats.total_orders_change >= 0 ? 'up' : 'down';
                totalOrdersChange.className = `stat-badge ${changeClass}`;
                totalOrdersChange.innerHTML = `<i class="fas fa-arrow-${changeIcon} me-1"></i>${Math.abs(stats.total_orders_change)}% vs last month`;
                totalOrdersChange.style.display = '';
            } else {
                totalOrdersChange.style.display = 'none'; // Hide if no change
            }
        }

        const avgValueChange = document.getElementById('metric-avg-value-change');
        if (avgValueChange) {
            if (stats.avg_order_value && stats.avg_order_value > 0) {
                avgValueChange.className = 'stat-badge info';
                avgValueChange.innerHTML = `<i class="fas fa-dollar-sign me-1"></i>$${stats.avg_order_value.toFixed(2)} avg`;
                avgValueChange.style.display = '';
            } else {
                avgValueChange.style.display = 'none'; // Hide if no data
            }
        }

        const unitsSoldChange = document.getElementById('metric-units-sold-change');
        if (unitsSoldChange) {
            if (stats.units_sold && stats.units_sold > 0) {
                unitsSoldChange.className = 'stat-badge success';
                unitsSoldChange.innerHTML = `<i class="fas fa-box me-1"></i>${stats.units_sold.toLocaleString()} units this month`;
                unitsSoldChange.style.display = '';
            } else {
                unitsSoldChange.style.display = 'none'; // Hide if zero
            }
        }

        const revenueChange = document.getElementById('metric-revenue-change');
        if (revenueChange) {
            if (stats.total_inventory_value && stats.total_inventory_value > 0) {
                revenueChange.className = 'stat-badge info';
                revenueChange.innerHTML = `<i class="fas fa-warehouse me-1"></i>$${stats.total_inventory_value.toLocaleString()} inventory value`;
                revenueChange.style.display = '';
            } else {
                revenueChange.style.display = 'none'; // Hide if zero
            }
        }

        // Update products details (in stock vs low stock) - ONLY show if meaningful
        const productsDetails = document.getElementById('metric-products-details');
        if (productsDetails) {
            if (stats.products_in_stock > 0 || stats.products_low_stock > 0) {
                productsDetails.innerHTML = `
                    <small class="text-success"><i class="fas fa-check-circle me-1"></i>${stats.products_in_stock || 0} in stock</small>
                    <small class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>${stats.products_low_stock || 0} low stock</small>
                `;
            } else {
                productsDetails.innerHTML = '<small class="text-muted">No product data available</small>';
            }
        }

        const productsAvailability = document.getElementById('metric-products-availability');
        if (productsAvailability) {
            if (stats.active_products > 0) {
                productsAvailability.className = 'stat-badge success';
                productsAvailability.innerHTML = `<i class="fas fa-check me-1"></i>${stats.active_products} products listed`;
                productsAvailability.style.display = '';
            } else {
                productsAvailability.style.display = 'none'; // Hide if no products
            }
        }

        // Update pending claims badges - ONLY show if there are actual claims
        const claimsBadges = document.getElementById('metric-claims-badges');
        if (claimsBadges) {
            if (stats.pending_claims && stats.pending_claims > 0) {
                claimsBadges.innerHTML = `
                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>${stats.pending_claims} Awaiting Review</span>
                `;
            } else {
                claimsBadges.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>All clear</span>';
            }
        }

        // Card 3: Pending Claims - HIDE PROGRESS BAR if 0
        const claimsProgressContainer = document.querySelector('#metric-pending-claims').closest('.card-content').querySelector('.progress-bar-container');
        if (claimsProgressContainer) {
            if (stats.pending_claims && stats.pending_claims > 0) {
                claimsProgressContainer.style.display = ''; // Show
                const claimsProgressBar = claimsProgressContainer.querySelector('.progress-bar');
                if (claimsProgressBar) {
                    const claimsPercent = Math.min((stats.pending_claims / 10) * 100, 100); // Max 10 claims = 100%
                    claimsProgressBar.style.width = claimsPercent + '%';
                }
            } else {
                claimsProgressContainer.style.display = 'none'; // HIDE if 0
            }
        }

        const claimsAlert = document.getElementById('metric-claims-alert');
        if (claimsAlert) {
            if (stats.pending_claims && stats.pending_claims > 0) {
                claimsAlert.className = 'stat-badge warning';
                claimsAlert.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i>${stats.pending_claims} claims need review`;
                claimsAlert.style.display = '';
            } else {
                claimsAlert.style.display = 'none'; // Hide if no claims
            }
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

    } finally {
        // ALWAYS reset loading flag
        window.__dashStatsLoading = false;
    }
}

// ========================================================================
// DYNAMIC LABEL GENERATORS - Context-aware progress labels
// ========================================================================

function getOrdersLabel(current, target) {
    const percent = (current / target) * 100;
    const labels = [
        { max: 0, text: '🎯 No Orders Yet' },
        { max: 10, text: '🌱 Just Starting' },
        { max: 25, text: '📈 Building Momentum' },
        { max: 50, text: '💪 Getting There' },
        { max: 75, text: '🔥 Strong Performance' },
        { max: 90, text: '⭐ Almost There!' },
        { max: 100, text: '✨ Target Reached' },
        { max: 125, text: '🚀 Exceeding Goal' },
        { max: 150, text: '💎 Outstanding!' },
        { max: Infinity, text: '🏆 Exceptional Performance' }
    ];
    return labels.find(l => percent <= l.max)?.text || '🎯 In Progress';
}

function getProductsLabel(active, inStock, lowStock) {
    const labels = [
        { condition: active === 0, text: '📦 No Products Listed' },
        { condition: active < 10, text: '🌱 Small Catalog' },
        { condition: active < 25, text: '📚 Growing Selection' },
        { condition: active < 50, text: '🏪 Good Variety' },
        { condition: active < 75, text: '🎯 Strong Catalog' },
        { condition: active < 100, text: '⭐ Excellent Range' },
        { condition: active < 150, text: '🚀 Large Inventory' },
        { condition: active < 200, text: '💎 Extensive Selection' },
        { condition: lowStock > 5, text: '⚠️ Stock Alerts Active' },
        { condition: inStock === active, text: '✅ Fully Stocked' }
    ];
    return labels.find(l => l.condition)?.text || '📊 Managing Inventory';
}

function getClaimsLabel(claims) {
    const labels = [
        { max: 0, text: '✅ All Clear' },
        { max: 1, text: '📋 1 Pending Claim' },
        { max: 2, text: '📌 Few Claims Open' },
        { max: 3, text: '⚡ Some Attention Needed' },
        { max: 5, text: '⚠️ Multiple Claims' },
        { max: 7, text: '🔴 High Priority' },
        { max: 10, text: '🚨 Critical Level' },
        { max: Infinity, text: '⛔ Urgent Review Required' }
    ];
    return labels.find(l => claims <= l.max)?.text || '📊 Managing Claims';
}

function getValueLabel(avgValue) {
    const labels = [
        { max: 0, text: '💤 No Sales Yet' },
        { max: 50, text: '🌱 Small Orders' },
        { max: 100, text: '📦 Standard Orders' },
        { max: 200, text: '💼 Good Value' },
        { max: 350, text: '⭐ Strong Value' },
        { max: 500, text: '🎯 Target Reached' },
        { max: 750, text: '💎 Premium Orders' },
        { max: 1000, text: '🏆 High Value' },
        { max: 1500, text: '🚀 Exceptional Value' },
        { max: Infinity, text: '👑 Elite Performance' }
    ];
    return labels.find(l => avgValue <= l.max)?.text || '💰 Processing';
}

function getUnitsLabel(units) {
    const labels = [
        { max: 0, text: '📦 No Units Sold' },
        { max: 50, text: '🌱 Starting Sales' },
        { max: 100, text: '📈 Building Volume' },
        { max: 250, text: '💪 Good Movement' },
        { max: 500, text: '🔥 Strong Sales' },
        { max: 750, text: '⭐ High Volume' },
        { max: 1000, text: '🎯 Target Reached' },
        { max: 1500, text: '🚀 Excellent Flow' },
        { max: 2500, text: '💎 Outstanding Volume' },
        { max: Infinity, text: '🏆 Peak Performance' }
    ];
    return labels.find(l => units <= l.max)?.text || '📊 Moving Inventory';
}

function getRevenueLabel(revenue) {
    const labels = [
        { max: 0, text: '💤 No Inventory Value' },
        { max: 5000, text: '🌱 Small Holdings' },
        { max: 10000, text: '📦 Building Stock' },
        { max: 20000, text: '💼 Good Position' },
        { max: 35000, text: '⭐ Strong Holdings' },
        { max: 50000, text: '🎯 Target Reached' },
        { max: 75000, text: '💎 Significant Value' },
        { max: 100000, text: '🚀 Major Holdings' },
        { max: 150000, text: '🏆 Premium Portfolio' },
        { max: Infinity, text: '👑 Elite Inventory' }
    ];
    return labels.find(l => revenue <= l.max)?.text || '💰 Managing Assets';
}

// ========================================================================
// SMART PROGRESS BAR UPDATER
// ========================================================================

function updateSmartProgressBar(cardId, currentValue, target, options = {}) {
    const card = document.getElementById(cardId);
    if (!card) return;

    const cardContent = card.closest('.card-content');
    if (!cardContent) return;

    const progressContainer = cardContent.querySelector('.progress-bar-container');
    const progressBar = cardContent.querySelector('.progress-bar');
    const badge = card.nextElementSibling; // Badge is usually next to card-value

    if (!progressContainer || !progressBar) return;

    // Calculate percentage
    const percentage = Math.min((currentValue / target) * 100, 100);

    // Hide if value is 0 (unless explicitly shown)
    if (currentValue === 0 && !options.showWhenZero) {
        progressContainer.style.display = 'none';
        if (badge) badge.style.display = 'none';
        return;
    }

    // Show and update
    progressContainer.style.display = '';
    progressBar.style.width = percentage + '%';

    // Set color (inverse means red=good, green=bad - for things like claims)
    const color = options.inverse
        ? getInverseColor(percentage)
        : (options.color || getProgressColor(currentValue, target));

    progressBar.style.background = color;

    // Update badge with dynamic label
    if (badge && options.label) {
        badge.style.display = '';
        badge.textContent = options.label;
        badge.className = 'stat-badge ' + getBadgeClass(percentage, options.inverse);
    }
}

function getProgressColor(current, target) {
    const percent = (current / target) * 100;
    if (percent < 25) return 'linear-gradient(90deg, #dc3545 0%, #e57373 100%)'; // Red
    if (percent < 50) return 'linear-gradient(90deg, #ffc107 0%, #ffdb4d 100%)'; // Yellow
    if (percent < 75) return 'linear-gradient(90deg, #17a2b8 0%, #5dcceb 100%)'; // Blue
    if (percent < 100) return 'linear-gradient(90deg, #28a745 0%, #5cb85c 100%)'; // Green
    return 'linear-gradient(90deg, #6f42c1 0%, #9370db 100%)'; // Purple (exceeded)
}

function getClaimsColor(claims) {
    if (claims === 0) return 'linear-gradient(90deg, #28a745 0%, #5cb85c 100%)'; // Green
    if (claims <= 2) return 'linear-gradient(90deg, #17a2b8 0%, #5dcceb 100%)'; // Blue
    if (claims <= 5) return 'linear-gradient(90deg, #ffc107 0%, #ffdb4d 100%)'; // Yellow
    if (claims <= 7) return 'linear-gradient(90deg, #fd7e14 0%, #ff9f50 100%)'; // Orange
    return 'linear-gradient(90deg, #dc3545 0%, #e57373 100%)'; // Red
}

function getInverseColor(percent) {
    // For things where LOW is GOOD (like claims)
    if (percent === 0) return 'linear-gradient(90deg, #28a745 0%, #5cb85c 100%)'; // Green
    if (percent < 30) return 'linear-gradient(90deg, #17a2b8 0%, #5dcceb 100%)'; // Blue
    if (percent < 50) return 'linear-gradient(90deg, #ffc107 0%, #ffdb4d 100%)'; // Yellow
    if (percent < 75) return 'linear-gradient(90deg, #fd7e14 0%, #ff9f50 100%)'; // Orange
    return 'linear-gradient(90deg, #dc3545 0%, #e57373 100%)'; // Red
}

function getBadgeClass(percent, inverse = false) {
    if (inverse) {
        if (percent === 0) return 'success';
        if (percent < 30) return 'info';
        if (percent < 50) return 'warning';
        return 'danger';
    }
    if (percent < 25) return 'danger';
    if (percent < 50) return 'warning';
    if (percent < 75) return 'info';
    return 'success';
}

// ========================================================================
// FLIP-SIDE INSIGHTS GENERATORS - Dynamic, contextual, and meaningful
// ========================================================================

function generateOrdersInsights(stats) {
    const insights = [];
    const orders = stats.total_orders || 0;
    const avgValue = parseFloat(stats.avg_order_value || 0);
    const revenue = orders * avgValue;

    if (orders === 0) {
        insights.push('📦 Ready for your first order!');
        insights.push('🎯 Great things start here');
    } else if (orders === 1) {
        insights.push('🎉 First order complete!');
        insights.push(`💰 Revenue: $${avgValue.toFixed(2)}`);
    } else {
        insights.push(`💵 Total Revenue: $${revenue.toLocaleString('en-US', {minimumFractionDigits: 2})}`);
        insights.push(`📊 Avg per order: $${avgValue.toFixed(2)}`);

        const unitsPerOrder = stats.units_sold ? (stats.units_sold / orders).toFixed(1) : 0;
        insights.push(`📦 ${unitsPerOrder} units per order`);

        if (orders >= 10) {
            const momentum = orders >= 50 ? '🚀 Strong' : orders >= 25 ? '📈 Growing' : '💪 Building';
            insights.push(`${momentum} sales momentum`);
        }

        if (avgValue > 300) {
            insights.push('💎 High-value customer base');
        } else if (avgValue < 100) {
            insights.push('🌱 Volume opportunity');
        }
    }

    return insights;
}

/**
 * Load stock alerts by store
 */
async function loadStockAlerts() {
    try {
        const result = await API.call('stock-alerts');

        if (!result.success || !result.data) {
            throw new Error(result.error || 'Failed to load stock alerts');
        }

        const data = result.data;
        const container = document.getElementById('stock-alerts-grid');
        const alertsCount = document.getElementById('alerts-count');
        const alertsTotalStores = document.getElementById('alerts-total-stores');
        const alertsLastUpdated = document.getElementById('alerts-last-updated');

        if (!container) return;

        // Update counts
        if (alertsCount) alertsCount.textContent = data.total_alerts || 0;
        if (alertsTotalStores) alertsTotalStores.textContent = data.stores_with_alerts || 0;
        if (alertsLastUpdated) alertsLastUpdated.textContent = 'just now';

        // If no alerts, show success message
        if (!data.stores || data.stores.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 text-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>All Stores Well Stocked!</h5>
                    <p class="text-muted">No low inventory alerts at any location</p>
                </div>
            `;
            return;
        }

        // Build store cards grid
        let html = '<div class="row g-3 p-3">';

        data.stores.forEach(store => {
            const alertClass = store.critical_count > 0 ? 'danger' :
                              store.low_count > 0 ? 'warning' : 'info';

            html += `
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-${alertClass}">
                        <div class="card-header bg-${alertClass} bg-opacity-10 border-${alertClass}">
                            <h6 class="mb-0">
                                <i class="fas fa-store me-2"></i>
                                ${store.outlet_name || 'Store ' + store.outlet_id}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Critical:</span>
                                <span class="badge bg-danger">${store.critical_count || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Low:</span>
                                <span class="badge bg-warning text-dark">${store.low_count || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Total Alerts:</span>
                                <span class="badge bg-${alertClass}">${store.total_alerts || 0}</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <button class="btn btn-sm btn-outline-${alertClass} w-100 view-store-products"
                                    data-outlet-id="${store.outlet_id}"
                                    data-outlet-name="${store.outlet_name || 'Store ' + store.outlet_id}">
                                <i class="fas fa-eye me-1"></i> View Products
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Attach click handlers to view buttons
        document.querySelectorAll('.view-store-products').forEach(btn => {
            btn.addEventListener('click', function() {
                const outletId = this.getAttribute('data-outlet-id');
                const outletName = this.getAttribute('data-outlet-name');
                loadStoreProducts(outletId, outletName);
            });
        });

    } catch (error) {
        console.error('Error loading stock alerts:', error);
        const container = document.getElementById('stock-alerts-grid');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading stock alerts: ${error.message}
                </div>
            `;
        }
    }
}

/**
 * Load orders table for dashboard
 */
async function loadOrdersTable() {
    try {
        const result = await API.call('dashboard-orders', { limit: 10 });

        if (!result.success || !result.data) {
            throw new Error(result.error || 'Failed to load orders');
        }

        const tbody = document.getElementById('orders-table-body');
        if (!tbody) return;

        const data = result.data;

        // Update total count
        const totalCountEl = document.getElementById('orders-total-count');
        if (totalCountEl) {
            totalCountEl.textContent = data.total || data.orders.length;
        }

        let html = '';

        // Handle empty state
        if (!data.orders || data.orders.length === 0) {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>No Pending Orders</h5>
                        <p class="text-muted">All caught up! No orders require your attention right now.</p>
                    </td>
                </tr>
            `;
        } else {
            data.orders.forEach(order => {
                const statusClass = getStatusBadgeClass(order.status);
                const valueFormatted = order.total_amount ? `$${parseFloat(order.total_amount).toFixed(2)}` : '$0.00';

                html += `
                    <tr data-order-id="${order.id}">
                        <td>
                            <input type="checkbox" class="form-check-input order-checkbox" value="${order.id}">
                        </td>
                        <td>
                            <strong>${order.po_number || order.id}</strong>
                            <br>
                            <small class="text-muted">${order.outlet || 'N/A'}</small>
                        </td>
                        <td>${order.outlet || 'N/A'}</td>
                        <td>
                            <span class="badge ${statusClass}">
                                ${order.status || 'UNKNOWN'}
                            </span>
                        </td>
                        <td class="text-center">${order.items_count || 0}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary">${order.units_count || 0}</span>
                        </td>
                        <td class="text-end"><strong>${valueFormatted}</strong></td>
                        <td class="text-muted small">${order.created_at || 'N/A'}</td>
                        <td class="text-muted small">${order.due_date || 'No due date'}</td>
                        <td class="text-center">
                            <a href="/supplier/order-detail.php?id=${order.id}" class="btn btn-primary btn-sm">
                                <i class="fas fa-arrow-right me-1"></i>
                                View
                            </a>
                        </td>
                    </tr>
                `;
            });
        }

        tbody.innerHTML = html;

    } catch (error) {
        console.error('Error loading orders table:', error);
        const tbody = document.getElementById('orders-table-body');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4">
                        <div class="alert alert-danger m-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading orders: ${error.message}
                        </div>
                    </td>
                </tr>
            `;
        }
    }
}

function getStatusBadgeClass(status) {
    const statusMap = {
        'OPEN': 'bg-primary',
        'PACKING': 'bg-warning text-dark',
        'PACKED': 'bg-info',
        'PACKAGED': 'bg-info',
        'SENT': 'bg-success',
        'RECEIVING': 'bg-purple',
        'RECEIVED': 'bg-secondary',
        'CANCELLED': 'bg-danger'
    };
    return statusMap[status] || 'bg-secondary';
}

function generateProductsInsights(stats) {
    const insights = [];
    const products = stats.active_products || 0;
    const inStock = stats.products_in_stock || 0;
    const lowStock = stats.products_low_stock || 0;
    const outOfStock = products - inStock;

    if (products === 0) {
        insights.push('🆕 Add your first product');
        insights.push('📚 Build your catalog');
    } else {
        const stockPercent = products > 0 ? ((inStock / products) * 100).toFixed(0) : 0;
        insights.push(`✅ ${stockPercent}% in stock (${inStock}/${products})`);

        if (outOfStock > 0) {
            insights.push(`⚠️ ${outOfStock} out of stock`);
        }

        if (lowStock > 0) {
            insights.push(`📊 ${lowStock} low stock alerts`);
        } else if (inStock === products) {
            insights.push('🎯 Fully stocked!');
        }

        if (products < 25) {
            insights.push('💡 Expand catalog to boost sales');
        } else if (products > 100) {
            insights.push('🏆 Extensive product range');
        }

        const unitsPerProduct = stats.units_sold && products > 0 ? (stats.units_sold / products).toFixed(1) : 0;
        if (unitsPerProduct > 10) {
            insights.push(`🔥 ${unitsPerProduct} units per product`);
        }
    }

    return insights;
}

function generateClaimsInsights(stats) {
    const insights = [];
    const claims = stats.pending_claims || 0;
    const claimsProcessed = stats.claims_processed || 0;
    const claimsTotal = claims + claimsProcessed;

    if (claims === 0 && claimsTotal === 0) {
        insights.push('✅ No warranty claims');
        insights.push('🌟 Perfect track record!');
        insights.push('😊 Happy customers');
    } else if (claims === 0) {
        insights.push('✅ All claims resolved!');
        insights.push(`📋 ${claimsProcessed} processed this period`);
        insights.push('⚡ Great response time');
    } else {
        insights.push(`⏳ ${claims} awaiting your review`);

        if (claimsProcessed > 0) {
            const resolveRate = ((claimsProcessed / claimsTotal) * 100).toFixed(0);
            insights.push(`✅ ${resolveRate}% resolution rate`);
        }

        if (claims === 1) {
            insights.push('⚡ Quick action required');
        } else if (claims <= 3) {
            insights.push('📌 Review when convenient');
        } else if (claims <= 5) {
            insights.push('⚠️ Attention needed soon');
        } else {
            insights.push('🚨 High priority review');
        }

        const claimRate = stats.units_sold > 0 ? ((claims / stats.units_sold) * 100).toFixed(2) : 0;
        if (claimRate < 1) {
            insights.push(`💪 Low claim rate: ${claimRate}%`);
        } else if (claimRate > 3) {
            insights.push(`📊 Claim rate: ${claimRate}%`);
        }
    }

    return insights;
}

function generateValueInsights(stats) {
    const insights = [];
    const avgValue = parseFloat(stats.avg_order_value || 0);
    const orders = stats.total_orders || 0;
    const highValueOrders = stats.high_value_orders || 0;

    if (avgValue === 0) {
        insights.push('🎯 First sale awaits!');
        insights.push('💡 Great things coming');
    } else {
        const tier = avgValue >= 1000 ? 'Elite' : avgValue >= 500 ? 'Premium' : avgValue >= 250 ? 'Strong' : avgValue >= 100 ? 'Good' : 'Building';
        insights.push(`📊 ${tier} value tier`);

        if (orders > 0) {
            const totalRevenue = orders * avgValue;
            insights.push(`💰 Total: $${totalRevenue.toLocaleString('en-US', {minimumFractionDigits: 2})}`);
        }

        if (highValueOrders > 0) {
            const highPercent = ((highValueOrders / orders) * 100).toFixed(0);
            insights.push(`💎 ${highPercent}% high-value orders`);
        }

        if (avgValue >= 500) {
            insights.push('🏆 Premium customer base');
        } else if (avgValue < 100) {
            insights.push('💡 Opportunity: Bundle deals');
        } else if (avgValue < 250) {
            insights.push('📈 Upsell potential');
        }

        const unitsPerOrder = stats.units_sold && orders > 0 ? (stats.units_sold / orders).toFixed(1) : 0;
        if (unitsPerOrder > 5) {
            insights.push(`📦 ${unitsPerOrder} items per order`);
        }
    }

    return insights;
}

function generateUnitsInsights(stats) {
    const insights = [];
    const units = stats.units_sold || 0;
    const orders = stats.total_orders || 0;
    const products = stats.active_products || 0;

    if (units === 0) {
        insights.push('📦 Ready to ship!');
        insights.push('🎯 First sale coming soon');
    } else {
        const momentum = units >= 2500 ? '🚀 Explosive' : units >= 1000 ? '🔥 Strong' : units >= 500 ? '📈 Growing' : units >= 100 ? '💪 Building' : '🌱 Starting';
        insights.push(`${momentum} sales volume`);

        if (orders > 0) {
            const unitsPerOrder = (units / orders).toFixed(1);
            insights.push(`📊 ${unitsPerOrder} units per order`);

            if (unitsPerOrder > 10) {
                insights.push('💎 Bulk orders common');
            } else if (unitsPerOrder < 3) {
                insights.push('💡 Bundle opportunity');
            }
        }

        if (products > 0) {
            const unitsPerProduct = (units / products).toFixed(1);
            insights.push(`🎯 ${unitsPerProduct} units per product`);

            if (unitsPerProduct > 20) {
                insights.push('⭐ Best-sellers performing well');
            } else if (unitsPerProduct < 5) {
                insights.push('📚 Diversify sales spread');
            }
        }

        const avgValue = parseFloat(stats.avg_order_value || 0);
        if (avgValue > 0) {
            const pricePerUnit = (avgValue * orders / units).toFixed(2);
            insights.push(`💵 $${pricePerUnit} avg per unit`);
        }
    }

    return insights;
}

function generateRevenueInsights(stats) {
    const insights = [];
    const inventory = parseFloat(stats.total_inventory_value || 0);
    const revenue = (stats.total_orders || 0) * parseFloat(stats.avg_order_value || 0);
    const products = stats.active_products || 0;

    if (inventory === 0) {
        insights.push('🆕 Stock up to start selling');
        insights.push('💡 Inventory awaits');
    } else {
        const tier = inventory >= 100000 ? 'Elite' : inventory >= 50000 ? 'Premium' : inventory >= 25000 ? 'Strong' : inventory >= 10000 ? 'Good' : 'Building';
        insights.push(`📊 ${tier} inventory tier`);

        if (products > 0) {
            const avgInventoryPerProduct = (inventory / products).toFixed(0);
            insights.push(`💰 $${avgInventoryPerProduct} avg per product`);
        }

        if (revenue > 0) {
            const turnoverRate = ((revenue / inventory) * 100).toFixed(0);
            insights.push(`📈 ${turnoverRate}% turnover rate`);

            if (turnoverRate > 50) {
                insights.push('🔥 Fast-moving stock');
            } else if (turnoverRate < 10) {
                insights.push('💡 Increase sales velocity');
            } else if (turnoverRate > 25) {
                insights.push('✅ Healthy movement');
            }
        }

        if (inventory >= 50000) {
            insights.push('🏆 Significant capital invested');
        }

        const inStock = stats.products_in_stock || 0;
        if (products > 0 && inStock > 0) {
            const stockPercent = ((inStock / products) * 100).toFixed(0);
            insights.push(`✅ ${stockPercent}% products stocked`);
        }
    }

    return insights;
}

// ========================================================================
// UPDATE CARD FLIP-SIDE CONTENT
// ========================================================================

function updateCardInsights(cardId, insights) {
    const card = document.getElementById(cardId);
    if (!card) return;

    // Find the card's flip container
    const flipCard = card.closest('.metric-card');
    if (!flipCard) return;

    const backContent = flipCard.querySelector('.card-back .back-content');
    if (!backContent) return;

    // Generate HTML for insights
    if (insights.length === 0) {
        backContent.innerHTML = '<p class="text-muted">📊 No data yet</p>';
        return;
    }

    let html = '<div class="insights-list">';
    insights.forEach(insight => {
        html += `<div class="insight-item">
            <i class="fas fa-chart-line me-2"></i>
            ${insight}
        </div>`;
    });
    html += '</div>';

    backContent.innerHTML = html;
}

/**
 * Update a single metric card - NO FLICKER, INSTANT UPDATE
 */
function updateMetricCard(id, value) {
    const element = document.getElementById(id);
    if (!element) return;

    // Remove loading skeleton
    element.classList.remove('skeleton');

    // Check if value is different - NO UPDATE if same!
    const currentValue = element.textContent.trim();
    const newValue = String(value).trim();

    if (currentValue === newValue) {
        // Value hasn't changed - do NOTHING
        return;
    }

    // INSTANT update - NO opacity animation, NO flicker
    element.textContent = value;
}

/**
 * Load orders requiring action table
 */
async function loadOrdersTable() {
    try {
        const response = await fetch(`/supplier/api/dashboard-orders-table.php?_t=${Date.now()}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load orders');

        const data = result.data;

        // Update total count
        const totalCountEl = document.getElementById('orders-total-count');
        if (totalCountEl) {
            totalCountEl.textContent = data.orders.length;
        }

        // Update last updated time (if element exists)
        const lastUpdatedEl = document.getElementById('orders-last-updated');
        if (lastUpdatedEl) {
            lastUpdatedEl.textContent = '2 hours ago';
        }

        let html = '';

        // Handle empty state
        if (data.orders.length === 0) {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>No Pending Orders</h5>
                        <p class="text-muted">All caught up! No orders require your attention right now.</p>
                    </td>
                </tr>
            `;
        } else {
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
        }

        const ordersTableBody = document.getElementById('orders-table-body');
        if (ordersTableBody) {
            ordersTableBody.innerHTML = html;
        }

        // Update pagination
        const paginationEl = document.getElementById('orders-pagination');
        if (paginationEl && data.orders.length > 0) {
            paginationEl.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing ${data.orders.length} order${data.orders.length !== 1 ? 's' : ''}
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-sync-alt me-1"></i>
                        Last updated: just now
                    </div>
                </div>
            `;
        } else if (paginationEl) {
            paginationEl.innerHTML = `
                <div class="d-flex justify-content-center align-items-center">
                    <div class="text-muted small">
                        <i class="fas fa-check-circle me-1 text-success"></i>
                        No pending orders
                    </div>
                </div>
            `;
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

        // Update count to 0
        const totalCountEl = document.getElementById('orders-total-count');
        if (totalCountEl) {
            totalCountEl.textContent = '0';
        }

        const ordersTableBody = document.getElementById('orders-table-body');
        if (ordersTableBody) {
            ordersTableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h5>Error loading orders</h5>
                        <p class="text-muted">${error.message}</p>
                    </td>
                </tr>
            `;
        }

        // Update pagination
        const paginationEl = document.getElementById('orders-pagination');
        if (paginationEl) {
            paginationEl.innerHTML = `
                <div class="d-flex justify-content-center align-items-center">
                    <div class="text-danger small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Unable to load orders
                    </div>
                </div>
            `;
        }
    }
}

/**
 * Load stock alerts by store
 */
async function loadStockAlerts() {
    try {
        const response = await fetch(`/supplier/api/dashboard-stock-alerts.php?_t=${Date.now()}`);
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
                        <button class="btn btn-sm ${btnClass} btn-block mt-2" onclick="viewStoreProducts(${store.outlet_id}, '${store.outlet_name.replace(/'/g, "\\'")}')">
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

        const response = await fetch(`/supplier/api/dashboard-items-sold.php?_t=${Date.now()}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load items sold');

        const chartData = result.chart_data;

        // Ensure we don't double-bind a chart to the same canvas
        destroyExistingChart(itemsSoldCanvas);

        const itemsSoldCtx = itemsSoldCanvas.getContext('2d');
        const itemsSoldChart = new Chart(itemsSoldCtx, {
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

        // Register instance
        window.__dashboardCharts[itemsSoldCanvas.id || 'itemsSoldChart'] = itemsSoldChart;

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

        const response = await fetch(`/supplier/api/dashboard-warranty-claims.php?_t=${Date.now()}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load warranty claims');

        const chartData = result.chart_data;

        // Ensure we don't double-bind a chart to the same canvas
        destroyExistingChart(warrantyCanvas);

        const warrantyCtx = warrantyCanvas.getContext('2d');
        const warrantyChart = new Chart(warrantyCtx, {
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

        // Register instance
        window.__dashboardCharts[warrantyCanvas.id || 'warrantyChart'] = warrantyChart;

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
        destroyExistingChart(el);
        const labels = new Array(cfg.series.length).fill('');
        const chart = new Chart(el, {
            type: 'line',
            data: { labels, datasets: [{ data: cfg.series, borderColor: cfg.color, backgroundColor: cfg.fill, fill: true }] },
            options: baseOptions
        });
        window.__dashboardCharts[id] = chart;
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

        // CRITICAL FIX: Destroy existing chart before creating new one
        destroyExistingChart(canvasElement);

        const ctx = canvasElement.getContext('2d');

        // Determine scale
        const maxValue = Math.max(...data, 1);
        const yAxisMax = Math.ceil(maxValue * 1.2);

        const newChart = new Chart(ctx, {
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

        // Store in registry for cleanup
        const id = canvasElement.id || '__anonymous_canvas__';
        window.__dashboardCharts[id] = newChart;
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

/**
 * View Store Products Modal
 * Shows tight scrollable table of low-stock products for a specific store
 */
async function viewStoreProducts(outletId, outletName) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('storeProductsModal');
    if (!modal) {
        const modalHTML = `
            <div class="modal fade" id="storeProductsModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-store-alt me-2"></i>
                                <span id="modal-store-name">Store Name</span> - Low Stock Products
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0" style="max-height: 500px;">
                            <div id="store-products-content">
                                <div class="text-center py-5">
                                    <div class="spinner-border"></div>
                                    <p class="text-muted mt-2">Loading products...</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="btn-export-store-products">
                                <i class="fas fa-download me-1"></i>
                                Export to CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modal = document.getElementById('storeProductsModal');
    }

    // Update modal title
    document.getElementById('modal-store-name').textContent = outletName;

    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Load products
    const content = document.getElementById('store-products-content');
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border"></div>
            <p class="text-muted mt-2">Loading products...</p>
        </div>
    `;

    try {
        const response = await fetch(`/supplier/api/dashboard-store-products.php?outlet_id=${outletId}&_t=${Date.now()}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error || 'Failed to load products');

        const products = result.products || [];

        if (products.length === 0) {
            content.innerHTML = `
                <div class="text-center py-5 text-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>All Stocked!</h5>
                    <p class="text-muted">No low inventory items at this store</p>
                </div>
            `;
            return;
        }

        // Build tight table
        let html = `
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th class="text-center" style="width: 15%;">Current Stock</th>
                            <th class="text-center" style="width: 15%;">Min Required</th>
                            <th class="text-center" style="width: 15%;">Days Left</th>
                            <th class="text-center" style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        products.forEach(product => {
            const statusClass = product.current_stock === 0 ? 'danger' :
                               product.days_left <= 3 ? 'danger' :
                               product.days_left <= 7 ? 'warning' : 'info';

            const statusText = product.current_stock === 0 ? 'Out of Stock' :
                              product.days_left <= 3 ? 'Critical' :
                              product.days_left <= 7 ? 'Low' : 'Warning';

            html += `
                <tr>
                    <td>
                        <strong>${product.product_name}</strong>
                        ${product.sku ? `<br><small class="text-muted">SKU: ${product.sku}</small>` : ''}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-${product.current_stock === 0 ? 'danger' : 'secondary'}">
                            ${product.current_stock}
                        </span>
                    </td>
                    <td class="text-center">${product.recommended_min || 'N/A'}</td>
                    <td class="text-center">
                        ${product.days_left < 999 ? `~${product.days_left} days` : 'N/A'}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-${statusClass}">${statusText}</span>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        content.innerHTML = html;

        // Store data for export
        window.__currentStoreProducts = {
            outletId,
            outletName,
            products
        };

    } catch (error) {
        console.error('Error loading store products:', error);
        content.innerHTML = `
            <div class="alert alert-danger m-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading products: ${error.message}
            </div>
        `;
    }
}

// Export button handler
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'btn-export-store-products') {
        if (window.__currentStoreProducts) {
            const data = window.__currentStoreProducts;
            window.location.href = `/supplier/api/export-store-products.php?outlet_id=${data.outletId}&format=csv`;
        }
    }
});
