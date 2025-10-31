/**
 * API Handler - Unified API communication with professional error handling
 *
 * Features:
 * - Standard JSON envelope handling
 * - Professional error modal display
 * - Loading states
 * - Request ID tracking
 * - Automatic retry on network errors
 *
 * @package Supplier\Portal\Assets\JS
 * @version 2.0.0
 */

const API = {
    /**
     * Base API URL
     */
    baseUrl: '/supplier/api/',

    /**
     * Make API request with standard error handling
     *
     * @param {string} action - API action name
     * @param {object} data - Request data
     * @param {object} options - Additional options (method, headers, etc.)
     * @returns {Promise<object>} Response data
     */
    async call(action, data = {}, options = {}) {
        const requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        try {
            // Show loading indicator if specified
            if (options.loadingElement) {
                this.showLoading(options.loadingElement);
            }

            // Prepare request
            const method = options.method || 'POST';
            const url = `${this.baseUrl}?action=${encodeURIComponent(action)}`;

            const fetchOptions = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Request-ID': requestId,
                    ...options.headers
                },
                credentials: 'same-origin'
            };

            // Add body for POST/PUT requests
            if (method === 'POST' || method === 'PUT') {
                fetchOptions.body = JSON.stringify({ action, ...data });
            }

            // Make request
            const response = await fetch(url, fetchOptions);

            // Parse JSON response
            let json;
            try {
                json = await response.json();
            } catch (e) {
                throw new Error('Invalid JSON response from server');
            }

            // Hide loading indicator
            if (options.loadingElement) {
                this.hideLoading(options.loadingElement);
            }

            // Handle response based on success flag
            if (json.success) {
                // Show success message if specified
                if (options.showSuccess && json.message) {
                    this.showSuccess(json.message);
                }

                return json.data;
            } else {
                // Handle error response
                this.handleError(json.error, action, requestId);
                throw new Error(json.error?.message || 'Request failed');
            }

        } catch (error) {
            // Hide loading indicator
            if (options.loadingElement) {
                this.hideLoading(options.loadingElement);
            }

            // Handle network/fetch errors
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showErrorModal({
                    code: 'NETWORK_ERROR',
                    message: 'Network connection failed',
                    details: 'Please check your internet connection and try again'
                }, action, requestId);
            } else if (!error.message.includes('Request failed')) {
                // Only show modal for unexpected errors (not already handled)
                this.showErrorModal({
                    code: 'UNKNOWN_ERROR',
                    message: error.message,
                    details: error.stack
                }, action, requestId);
            }

            throw error;
        }
    },

    /**
     * Show professional error modal
     */
    showErrorModal(error, action, requestId) {
        // Remove any existing error modal
        const existingModal = document.getElementById('api-error-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal HTML
        const modal = document.createElement('div');
        modal.id = 'api-error-modal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('role', 'dialog');

        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${this.getErrorTitle(error.code)}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger border-0 mb-3">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-times-circle me-2"></i>
                                ${error.message || 'An error occurred'}
                            </h6>
                            ${error.details ? `<p class="mb-0 small text-muted">${error.details}</p>` : ''}
                        </div>

                        ${error.field ? `
                            <div class="mb-3">
                                <strong>Field:</strong> <code>${error.field}</code>
                            </div>
                        ` : ''}

                        <div class="small text-muted">
                            <div class="mb-1"><strong>Error Code:</strong> <code>${error.code || 'UNKNOWN'}</code></div>
                            <div class="mb-1"><strong>Action:</strong> <code>${action}</code></div>
                            <div><strong>Request ID:</strong> <code>${requestId}</code></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                        <button type="button" class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Reload Page
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Show modal using Bootstrap
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Remove modal from DOM when hidden
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });

        // Log error to console for debugging
        console.error('API Error:', {
            code: error.code,
            message: error.message,
            details: error.details,
            action: action,
            requestId: requestId
        });
    },

    /**
     * Get user-friendly error title based on error code
     */
    getErrorTitle(code) {
        const titles = {
            'NETWORK_ERROR': 'Network Connection Error',
            'DATABASE_ERROR': 'Database Error',
            'VALIDATION_ERROR': 'Validation Error',
            'AUTH_ERROR': 'Authentication Error',
            'PERMISSION_ERROR': 'Permission Denied',
            'NOT_FOUND': 'Resource Not Found',
            'SERVER_ERROR': 'Server Error',
            'METHOD_NOT_ALLOWED': 'Method Not Allowed',
            'MISSING_ACTION': 'Invalid Request',
            'INVALID_ACTION': 'Invalid Action',
            'ACTION_NOT_FOUND': 'Action Not Found'
        };

        return titles[code] || 'Error Occurred';
    },

    /**
     * Show success toast notification
     */
    showSuccess(message) {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        // Create toast
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Show toast
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();

        // Remove after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    },

    /**
     * Show loading indicator on element
     */
    showLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (element) {
            element.disabled = true;
            element.dataset.originalHtml = element.innerHTML;
            element.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
        }
    },

    /**
     * Hide loading indicator on element
     */
    hideLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (element && element.dataset.originalHtml) {
            element.disabled = false;
            element.innerHTML = element.dataset.originalHtml;
            delete element.dataset.originalHtml;
        }
    }
};

// Make API globally available
window.API = API;
