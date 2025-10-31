/**
 * Toast Notification System
 * Drop-in replacement for alerts with auto-dismiss and styling
 *
 * Usage:
 * showToast('Success', 'Order updated successfully', 'success');
 * showToast('Error', 'Failed to save changes', 'danger');
 * showToast('Info', 'Report is generating', 'info');
 * showToast('Warning', 'This action cannot be undone', 'warning');
 */

// Create toast container if it doesn't exist
function initToastContainer() {
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
}

// Show toast notification
function showToast(title, message, type = 'info', duration = 5000) {
    initToastContainer();

    const icons = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    const toastId = 'toast-' + Date.now();
    const icon = icons[type] || icons.info;

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="d-flex align-items-center">
                        <i class="fas ${icon} me-2"></i>
                        <div>
                            <strong class="d-block">${title}</strong>
                            <span>${message}</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const container = document.querySelector('.toast-container');
    container.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: duration
    });

    toast.show();

    // Remove from DOM after hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });

    return toast;
}

// Convenience methods
function showSuccessToast(message, title = 'Success') {
    return showToast(title, message, 'success');
}

function showErrorToast(message, title = 'Error') {
    return showToast(title, message, 'danger', 7000);
}

function showWarningToast(message, title = 'Warning') {
    return showToast(title, message, 'warning', 6000);
}

function showInfoToast(message, title = 'Info') {
    return showToast(title, message, 'info', 4000);
}

// Loading toast (doesn't auto-dismiss)
function showLoadingToast(message, title = 'Processing') {
    initToastContainer();

    const toastId = 'toast-loading-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>
                            <strong class="d-block">${title}</strong>
                            <span>${message}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    const container = document.querySelector('.toast-container');
    container.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: false
    });

    toast.show();

    // Return object with hide method
    return {
        hide: function(successMessage = null) {
            toast.hide();
            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
                if (successMessage) {
                    showSuccessToast(successMessage);
                }
            });
        },
        element: toastElement,
        toast: toast
    };
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initToastContainer);
