/**
 * Supplier Portal - Main JavaScript Module
 *
 * Core functionality and initialization
 *
 * @version 3.0.0
 */

const SupplierPortal = {
    // Configuration
    config: {
        apiEndpoint: '/supplier/api/endpoint.php',
        version: '3.0.0',
        debug: false
    },

    // State management
    state: {
        authenticated: false,
        supplierId: null,
        supplierName: null,
        sessionToken: null
    },

    // Initialize the application
    init: function() {
        console.log('Supplier Portal v' + this.config.version + ' initializing...');

        // NOTE: Authentication is handled by PHP (bootstrap.php)
        // No need for JavaScript auth check

        // Set up global error handler
        this.setupErrorHandler();

        // Set up AJAX defaults
        this.setupAjax();

        // Initialize components
        this.initComponents();

        console.log('Supplier Portal initialized successfully');
    },

    // Check if user is authenticated
    checkAuth: function() {
        const self = this;

        this.api('auth.getSession', {}, function(response) {
            if (response.success && response.data.authenticated) {
                self.state.authenticated = true;
                self.state.supplierId = response.data.supplier.id;
                self.state.supplierName = response.data.supplier.name;
            } else {
                self.state.authenticated = false;
                // Redirect to login if not on login page
                if (!window.location.pathname.includes('login.php')) {
                    window.location.href = '/supplier/login.php';
                }
            }
        }, function(error) {
            console.error('Auth check failed:', error);
            if (!window.location.pathname.includes('login.php')) {
                window.location.href = '/supplier/login.php';
            }
        });
    },

    // Set up global error handler
    setupErrorHandler: function() {
        window.addEventListener('error', function(event) {
            SupplierPortal.showError({
                message: event.message,
                file: event.filename,
                line: event.lineno,
                column: event.colno,
                error: event.error
            });
        });

        window.addEventListener('unhandledrejection', function(event) {
            SupplierPortal.showError({
                message: 'Unhandled Promise Rejection: ' + event.reason,
                error: event.reason
            });
        });
    },

    // Set up AJAX defaults
    setupAjax: function() {
        // Set default AJAX error handler
        $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
            if (jqXHR.status === 401) {
                // Unauthorized - redirect to login
                window.location.href = '/supplier/login.php';
            }
        });
    },

    // Initialize UI components
    initComponents: function() {
        // Initialize tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Initialize popovers
        if (typeof bootstrap !== 'undefined') {
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }
    },

    // Make API call with standard envelope
    api: function(action, params, successCallback, errorCallback) {
        const self = this;

        const requestData = {
            action: action,
            params: params || {}
        };

        if (this.config.debug) {
            console.log('API Request:', requestData);
        }

        $.ajax({
            url: this.config.apiEndpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(requestData),
            dataType: 'json',
            success: function(response) {
                if (self.config.debug) {
                    console.log('API Response:', response);
                }

                if (response.success) {
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else {
                    // API returned error
                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    } else {
                        self.showError({
                            message: response.message || 'API request failed',
                            meta: response.meta
                        });
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);

                let errorMessage = 'Network error';
                let errorDetails = null;

                if (jqXHR.responseJSON) {
                    errorMessage = jqXHR.responseJSON.message || errorMessage;
                    errorDetails = jqXHR.responseJSON.meta;
                }

                if (typeof errorCallback === 'function') {
                    errorCallback({
                        success: false,
                        message: errorMessage,
                        meta: errorDetails
                    });
                } else {
                    self.showError({
                        message: errorMessage,
                        meta: errorDetails,
                        status: jqXHR.status
                    });
                }
            }
        });
    },

    // Show error popup with rich information
    showError: function(error) {
        console.error('Error:', error);

        // Build error modal HTML
        const errorId = 'error-' + Date.now();
        const errorHtml = `
            <div class="modal fade" id="${errorId}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error Occurred
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger mb-3">
                                <strong>Error Message:</strong><br>
                                ${this.escapeHtml(error.message || 'Unknown error')}
                            </div>

                            ${error.file ? `
                            <div class="mb-2">
                                <strong>File:</strong> <code>${this.escapeHtml(error.file)}</code>
                            </div>
                            ` : ''}

                            ${error.line ? `
                            <div class="mb-2">
                                <strong>Line:</strong> <code>${error.line}</code>
                                ${error.column ? ` <strong>Column:</strong> <code>${error.column}</code>` : ''}
                            </div>
                            ` : ''}

                            ${error.meta ? `
                            <div class="mt-3">
                                <strong>Additional Details:</strong>
                                <pre class="bg-light p-3 rounded mt-2" style="max-height: 300px; overflow-y: auto;"><code>${this.escapeHtml(JSON.stringify(error.meta, null, 2))}</code></pre>
                            </div>
                            ` : ''}

                            ${error.error && error.error.stack ? `
                            <div class="mt-3">
                                <strong>Stack Trace:</strong>
                                <pre class="bg-light p-3 rounded mt-2" style="max-height: 200px; overflow-y: auto; font-size: 11px;"><code>${this.escapeHtml(error.error.stack)}</code></pre>
                            </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="SupplierPortal.copyErrorDetails('${errorId}')">
                                <i class="fas fa-copy me-1"></i>
                                Copy Error Details
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing error modals
        $('.modal[id^="error-"]').remove();

        // Add new error modal
        $('body').append(errorHtml);

        // Show modal
        const errorModal = new bootstrap.Modal(document.getElementById(errorId));
        errorModal.show();

        // Auto-remove modal from DOM after it's hidden
        $('#' + errorId).on('hidden.bs.modal', function() {
            $(this).remove();
        });
    },

    // Copy error details to clipboard
    copyErrorDetails: function(modalId) {
        const modal = document.getElementById(modalId);
        const errorText = modal.querySelector('.modal-body').innerText;

        navigator.clipboard.writeText(errorText).then(function() {
            SupplierPortal.showToast('Error details copied to clipboard', 'success');
        }).catch(function(err) {
            console.error('Failed to copy:', err);
            SupplierPortal.showToast('Failed to copy error details', 'danger');
        });
    },

    // Show toast notification
    showToast: function(message, type = 'info', duration = 3000) {
        const toastId = 'toast-' + Date.now();
        const bgClass = {
            'success': 'bg-success',
            'danger': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';

        const toastHtml = `
            <div class="toast align-items-center text-white ${bgClass} border-0" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${this.escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        // Create toast container if it doesn't exist
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
        }

        // Add toast
        $('#toast-container').append(toastHtml);

        // Show toast
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();

        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            $(this).remove();
        });
    },

    // Escape HTML to prevent XSS
    escapeHtml: function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    },

    // Format currency
    formatCurrency: function(amount, currency = '$') {
        return currency + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    // Format date
    formatDate: function(dateString, format = 'M j, Y') {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const replacements = {
            'Y': date.getFullYear(),
            'y': String(date.getFullYear()).slice(-2),
            'M': months[date.getMonth()],
            'm': String(date.getMonth() + 1).padStart(2, '0'),
            'j': date.getDate(),
            'd': String(date.getDate()).padStart(2, '0'),
            'H': String(date.getHours()).padStart(2, '0'),
            'i': String(date.getMinutes()).padStart(2, '0'),
            's': String(date.getSeconds()).padStart(2, '0')
        };

        return format.replace(/Y|y|M|m|j|d|H|i|s/g, function(match) {
            return replacements[match];
        });
    },

    // Format time ago
    formatTimeAgo: function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';

        return this.formatDate(dateString);
    },

    // Show loading spinner
    showLoading: function(element) {
        const $element = $(element);
        $element.data('original-html', $element.html());
        $element.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
    },

    // Hide loading spinner
    hideLoading: function(element) {
        const $element = $(element);
        const originalHtml = $element.data('original-html');
        if (originalHtml) {
            $element.html(originalHtml).prop('disabled', false);
        }
    },

    // Confirm action
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    // Logout
    logout: function() {
        const self = this;

        this.api('auth.logout', {}, function(response) {
            window.location.href = '/supplier/login.php';
        }, function(error) {
            console.error('Logout error:', error);
            // Force redirect anyway
            window.location.href = '/supplier/login.php';
        });
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    SupplierPortal.init();
});
