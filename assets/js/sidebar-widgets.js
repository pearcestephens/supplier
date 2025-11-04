/**
 * Sidebar Widget Manager
 *
 * Loads and updates sidebar statistics and recent activity
 * Matches demo design exactly with animated progress bars
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    /**
     * Load sidebar stats from API
     */
    function loadSidebarStats() {
        fetch('/supplier/api/sidebar-stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSidebarStats(data.data);
                } else {
                    console.error('Failed to load sidebar stats:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading sidebar stats:', error);
            });
    }

    /**
     * Update sidebar statistics UI
     */
    function updateSidebarStats(stats) {
        // Active Orders
        const activeOrdersEl = document.getElementById('sidebar-active-orders');
        if (activeOrdersEl && stats.active_orders) {
            const valueEl = activeOrdersEl.querySelector('strong');
            const progressEl = activeOrdersEl.querySelector('.progress-bar');

            if (valueEl) {
                valueEl.textContent = stats.active_orders.count;
            }
            if (progressEl) {
                // Animate progress bar
                setTimeout(() => {
                    progressEl.style.width = stats.active_orders.percent + '%';
                }, 100);
            }
        }

        // Orders This Week
        const ordersWeekEl = document.getElementById('sidebar-orders-week');
        if (ordersWeekEl && stats.orders_this_week) {
            const valueEl = ordersWeekEl.querySelector('strong');
            const progressEl = ordersWeekEl.querySelector('.progress-bar');

            if (valueEl) {
                valueEl.textContent = stats.orders_this_week.count;
            }
            if (progressEl) {
                // Calculate progress (0-100% based on weekly average)
                const weeklyAvg = 10; // Estimate average weekly orders
                const progressPercent = Math.min(100, Math.round((stats.orders_this_week.count / weeklyAvg) * 100));

                setTimeout(() => {
                    progressEl.style.width = progressPercent + '%';
                }, 100);
            }
        }

        // Completed This Week
        const completedWeekEl = document.getElementById('sidebar-completed-week');
        if (completedWeekEl && stats.completed_this_week) {
            const valueEl = completedWeekEl.querySelector('strong');
            const progressEl = completedWeekEl.querySelector('.progress-bar');

            if (valueEl) {
                valueEl.textContent = stats.completed_this_week.count;
            }
            if (progressEl) {
                // Calculate progress based on orders this week (completion rate)
                let progressPercent = 0;
                if (stats.orders_this_week && stats.orders_this_week.count > 0) {
                    progressPercent = Math.min(100, Math.round((stats.completed_this_week.count / stats.orders_this_week.count) * 100));
                }

                setTimeout(() => {
                    progressEl.style.width = progressPercent + '%';
                }, 100);
            }
        }

        // Products Listed
        const productsListedEl = document.getElementById('sidebar-products-listed');
        if (productsListedEl && stats.products_listed) {
            const valueEl = productsListedEl.querySelector('strong');
            const progressEl = productsListedEl.querySelector('.progress-bar');

            if (valueEl) {
                valueEl.textContent = stats.products_listed.count;
            }
            if (progressEl) {
                // Show fixed 75% for products (visual indicator)
                setTimeout(() => {
                    progressEl.style.width = '75%';
                }, 100);
            }
        }

        // Recent Activity
        if (stats.recent_activity && stats.recent_activity.length > 0) {
            updateRecentActivity(stats.recent_activity);
        }
    }

    /**
     * Update recent activity list with rich notifications
     */
    function updateRecentActivity(activities) {
        const activityContainer = document.getElementById('sidebar-activity');
        if (!activityContainer) return;

        // Clear loading message
        activityContainer.innerHTML = '';

        // Check if activities is empty or not an array
        if (!activities || !Array.isArray(activities) || activities.length === 0) {
            activityContainer.innerHTML = `
                <div class="sidebar-activity-item">
                    <div class="activity-dot bg-secondary"></div>
                    <div class="activity-text">
                        <div class="activity-title" style="color: #888; font-size: 13px;">No recent activity</div>
                        <div class="activity-time" style="color: #666; font-size: 11px;">Check back soon</div>
                    </div>
                </div>
            `;
            return;
        }

        // Add each activity with icon
        activities.forEach(activity => {
            const activityItem = document.createElement('div');
            activityItem.className = 'sidebar-activity-item';

            // Use icon if provided, otherwise use dot
            const iconHtml = activity.icon
                ? `<i class="fa-solid fa-${escapeHtml(activity.icon)}" style="color: var(--bs-${escapeHtml(activity.color)}); font-size: 14px; margin-right: 12px; margin-top: 2px;"></i>`
                : `<div class="activity-dot bg-${escapeHtml(activity.color)}"></div>`;

            activityItem.innerHTML = `
                ${iconHtml}
                <div class="activity-text" style="flex: 1;">
                    <div class="activity-title" style="color: #fff; font-size: 13px; line-height: 1.4;">${escapeHtml(activity.label || 'Unknown activity')}</div>
                    <div class="activity-time" style="color: #888; font-size: 11px; margin-top: 2px;">${escapeHtml(activity.time || 'Just now')}</div>
                </div>
            `;

            activityContainer.appendChild(activityItem);
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize sidebar widgets
     * TEMPORARILY DISABLED - Database schema issues causing 500 errors
     */
    function initSidebarWidgets() {
        console.log('Sidebar widgets disabled - database schema fixes needed');
        // DISABLED: Load stats immediately
        // loadSidebarStats();

        // DISABLED: Refresh stats every 2 minutes
        // setInterval(loadSidebarStats, 120000);
    }

    // Auto-initialize when DOM is ready
    // DISABLED: initSidebarWidgets() causing database errors every 2 minutes
    // if (document.readyState === 'loading') {
    //     document.addEventListener('DOMContentLoaded', initSidebarWidgets);
    // } else {
    //     initSidebarWidgets();
    // }

})();
