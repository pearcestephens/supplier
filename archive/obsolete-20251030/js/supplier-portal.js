/**
 * Supplier Portal - Main JavaScript
 * 
 * Core functionality for supplier portal
 * 
 * @package CIS\Supplier\Assets
 * @version 2.0.0
 */

(function() {
    'use strict';
    
    // Initialize when DOM ready
    $(document).ready(function() {
        console.log('Supplier Portal v2.0.0 initialized');
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Initialize popovers
        $('[data-toggle="popover"]').popover();
        
        // Auto-update notification count
        updateNotificationCount();
        setInterval(updateNotificationCount, 60000); // Every minute
    });
    
    /**
     * Update notification count badge
     */
    function updateNotificationCount() {
        $.ajax({
            url: '/supplier/api/notifications-count.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const $badge = $('#notification-count');
                const $badgeText = $('#notification-count-text');
                
                if (response.count > 0) {
                    // Update count on bell badge
                    $badge.text(response.count).show();
                    
                    // Update count in dropdown header
                    $badgeText.text(response.count);
                    
                    // Update badge color based on urgency
                    $badge.removeClass('bg-danger bg-warning bg-success');
                    $badgeText.removeClass('bg-danger bg-warning bg-primary');
                    
                    switch(response.urgency) {
                        case 'critical':
                            $badge.addClass('bg-danger');
                            $badgeText.addClass('bg-danger');
                            break;
                        case 'warning':
                            $badge.addClass('bg-warning');
                            $badgeText.addClass('bg-warning');
                            break;
                        default:
                            $badge.addClass('bg-success');
                            $badgeText.addClass('bg-primary');
                    }
                    
                    // Populate notification list
                    const $notifList = $('#notification-list');
                    $notifList.empty();
                    
                    if (response.messages.overdue) {
                        $notifList.append(`
                            <li><a class="dropdown-item" href="?tab=warranty">
                                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                ${response.messages.overdue}
                            </a></li>
                        `);
                    }
                    if (response.messages.urgent) {
                        $notifList.append(`
                            <li><a class="dropdown-item" href="?tab=orders">
                                <i class="fas fa-clock text-warning me-2"></i>
                                ${response.messages.urgent}
                            </a></li>
                        `);
                    }
                    if (response.messages.pending) {
                        $notifList.append(`
                            <li><a class="dropdown-item" href="?tab=warranty">
                                <i class="fas fa-wrench text-info me-2"></i>
                                ${response.messages.pending}
                            </a></li>
                        `);
                    }
                    
                } else {
                    $badge.hide();
                    $badgeText.text('0');
                    $('#notification-list').html(`
                        <div class="text-center text-muted py-3 small">
                            No new notifications
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.warn('Failed to update notification count:', error);
                // Keep existing badge visible on error
            }
        });
    }
    
    /**
     * Show toast notification
     */
    window.showToast = function(message, type = 'info') {
        // TODO: Implement proper toast system
        // For now, use browser alert
        alert(message);
    };
    
    /**
     * Confirm action dialog
     */
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    /**
     * Download file helper
     */
    window.downloadFile = function(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
    
    /**
     * Format currency
     */
    window.formatCurrency = function(amount) {
        return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    };
    
    /**
     * Format date
     */
    window.formatDate = function(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-NZ', options);
    };
    
})();
