/**
 * Global JavaScript Error Handler
 *
 * Catches all AJAX errors and JavaScript errors, displays popup alerts
 * and shows detailed error information
 *
 * @package CIS\Supplier\Assets
 * @version 2.0.0
 */

(function() {
    'use strict';

    // =========================================================================
    // Configuration
    // =========================================================================
    const CONFIG = {
        showAlerts: false,             // DISABLED: No blocking alert() popups
        logToConsole: true,            // Log errors to console
        showDetailedErrors: true,      // Show detailed error info in toasts
        autoRetry: false,              // Auto-retry failed requests
        retryCount: 0,                 // Number of retries (0 = no retry)
        retryDelay: 2000              // Delay between retries (ms)
    };

    // =========================================================================
    // Global AJAX Error Handler (jQuery)
    // =========================================================================
    if (typeof jQuery !== 'undefined') {
        $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            handleAjaxError(jqXHR, ajaxSettings, thrownError);
        });

        // Enhance all $.ajax calls to include X-Requested-With header
        $.ajaxSetup({
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            }
        });
    }

    // =========================================================================
    // Global Fetch Error Handler (Modern)
    // =========================================================================
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                // Fast-path on success
                if (response.ok) return response;

                // On error statuses, read body ONCE as text, then try JSON parse
                const cloned = response.clone();
                return cloned.text().then(bodyText => {
                    let errorData = {};
                    try {
                        errorData = JSON.parse(bodyText);
                    } catch (_) {
                        // not JSON, wrap as text
                        errorData = { error: bodyText };
                    }

                    handleFetchError(response, errorData, args[0]);
                    const message = (errorData && (errorData.error?.message || errorData.message || errorData.error)) || 'Request failed';
                    throw new Error(typeof message === 'string' ? message : 'Request failed');
                });
            })
            .catch(error => {
                if (CONFIG.logToConsole) {
                    console.error('Fetch error:', error);
                }
                throw error;
            });
    };

    // =========================================================================
    // Global JavaScript Error Handler
    // =========================================================================
    window.addEventListener('error', function(event) {
        handleJavaScriptError(event.error, event.filename, event.lineno, event.colno);
    });

    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        handleJavaScriptError(event.reason, 'Promise', 0, 0);
    });

    // =========================================================================
    // Error Handling Functions
    // =========================================================================

    /**
     * Handle jQuery AJAX errors
     */
    function handleAjaxError(jqXHR, ajaxSettings, thrownError) {
        let errorMessage = 'An error occurred';
        let errorDetails = {};

        // Try to parse JSON error response
        try {
            const response = JSON.parse(jqXHR.responseText);

            if (response.error) {
                errorMessage = response.error.message || response.error;
                errorDetails = response.error;

                // Show detailed popup
                if (CONFIG.showAlerts) {
                    showErrorAlert('AJAX Error', errorMessage, errorDetails);
                }

                // Log to console
                if (CONFIG.logToConsole) {
                    console.error('AJAX Error:', response);
                    if (response.debug) {
                        console.error('Debug Info:', response.debug);
                    }
                }

                return;
            }
        } catch (e) {
            // Not JSON, use raw response
            errorMessage = jqXHR.responseText || jqXHR.statusText || thrownError;
        }

        // Build error details
        errorDetails = {
            status: jqXHR.status,
            statusText: jqXHR.statusText,
            url: ajaxSettings.url,
            method: ajaxSettings.type,
            responseText: jqXHR.responseText
        };

        // Show popup
        if (CONFIG.showAlerts) {
            showErrorAlert('HTTP ' + jqXHR.status + ' Error', errorMessage, errorDetails);
        }

        // Log to console
        if (CONFIG.logToConsole) {
            console.error('AJAX Error:', errorDetails);
        }
    }

    /**
     * Handle Fetch API errors
     */
    function handleFetchError(response, errorData, url) {
        let errorMessage = 'Request failed';
        let errorDetails = {};

        if (errorData.error) {
            errorMessage = errorData.error.message || errorData.error;
            errorDetails = errorData.error;
        }

        errorDetails.status = response.status;
        errorDetails.statusText = response.statusText;
        errorDetails.url = url;

        // Show popup
        if (CONFIG.showAlerts) {
            showErrorAlert('Fetch Error', errorMessage, errorDetails);
        }

        // Log to console
        if (CONFIG.logToConsole) {
            console.error('Fetch Error:', errorDetails);
            if (errorData.debug) {
                console.error('Debug Info:', errorData.debug);
            }
        }
    }

    /**
     * Handle JavaScript runtime errors
     */
    function handleJavaScriptError(error, filename, lineno, colno) {
        const errorMessage = error?.message || error?.toString() || 'Unknown error';
        const errorDetails = {
            message: errorMessage,
            filename: filename || 'unknown',
            line: lineno || 0,
            column: colno || 0,
            stack: error?.stack || 'No stack trace available'
        };

        // Show popup
        if (CONFIG.showAlerts) {
            showErrorAlert('JavaScript Error', errorMessage, errorDetails);
        }

        // Log to console
        if (CONFIG.logToConsole) {
            console.error('JavaScript Error:', errorDetails);
        }
    }

    /**
     * Show enhanced error alert (TOAST ONLY - No blocking popups)
     */
    function showErrorAlert(title, message, details) {
        // Log to console for debugging
        if (CONFIG.logToConsole) {
            console.group('🚨 ' + title);
            console.error(message);
            if (details) {
                console.error('Details:', details);
            }
            console.groupEnd();
        }

        // Always show toast notification (non-blocking)
        showNotification(title, message, 'error');
    }

    /**
     * Show styled notification toast
     */
    function showNotification(title, message, type = 'error') {
        // Check if notification already exists
        let container = document.getElementById('error-notification-container');

        if (!container) {
            container = document.createElement('div');
            container.id = 'error-notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        const notification = document.createElement('div');
        notification.style.cssText = `
            background: ${type === 'error' ? '#dc3545' : '#28a745'};
            color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            margin-bottom: 10px;
            animation: slideInRight 0.3s ease;
            cursor: pointer;
        `;

        notification.innerHTML = `
            <div style="display:flex;align-items:start;gap:10px;">
                <div style="font-size:24px;">${type === 'error' ? '⚠️' : '✅'}</div>
                <div style="flex:1;">
                    <div style="font-weight:bold;margin-bottom:5px;">${escapeHtml(title)}</div>
                    <div style="font-size:0.9em;opacity:0.9;">${escapeHtml(message)}</div>
                </div>
                <div style="font-size:20px;opacity:0.7;cursor:pointer;" onclick="this.parentElement.parentElement.remove()">✕</div>
            </div>
        `;

        // Add CSS animation
        if (!document.getElementById('error-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'error-notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }

        container.appendChild(notification);

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 10000);

        // Click to dismiss
        notification.addEventListener('click', () => {
            notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 300);
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

    // =========================================================================
    // Public API
    // =========================================================================
    window.ErrorHandler = {
        config: CONFIG,
        showError: showErrorAlert,
        showNotification: showNotification
    };

    console.log('✅ Global Error Handler loaded');

})();
