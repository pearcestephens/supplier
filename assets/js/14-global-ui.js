/**
 * 14-global-ui.js - Global UI Helpers
 * The Vape Shed Supplier Portal
 * 
 * Provides global UI functionality including:
 * - Tooltips initialization
 * - Loading states
 * - Smooth scrolling
 * - Keyboard navigation
 * - Accessibility features
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initTooltips();
        initPopovers();
        initSmoothScroll();
        initLoadingButtons();
        initKeyboardNav();
        initAccessibility();
        initTableResponsive();
        initEmptyStates();
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initTooltips() {
        // Bootstrap 5 tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus',
                boundary: 'window'
            });
        });

        // Also support data-toggle for backward compatibility
        const oldTooltipList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
        oldTooltipList.forEach(function (tooltipTriggerEl) {
            tooltipTriggerEl.setAttribute('data-bs-toggle', 'tooltip');
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover focus',
                boundary: 'window'
            });
        });
    }

    /**
     * Initialize Bootstrap popovers
     */
    function initPopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.forEach(function (popoverTriggerEl) {
            new bootstrap.Popover(popoverTriggerEl);
        });
    }

    /**
     * Smooth scrolling for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Update focus for accessibility
                    target.setAttribute('tabindex', '-1');
                    target.focus();
                }
            });
        });
    }

    /**
     * Loading button states
     */
    function initLoadingButtons() {
        // Auto-add loading states to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.classList.contains('no-loading')) {
                    setButtonLoading(submitBtn, true);
                }
            });
        });
    }

    /**
     * Set button loading state
     */
    function setButtonLoading(button, isLoading) {
        if (isLoading) {
            button.disabled = true;
            button.classList.add('btn-loading');
            
            // Store original content
            if (!button.dataset.originalContent) {
                button.dataset.originalContent = button.innerHTML;
            }
            
            // Add spinner
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + 
                              (button.dataset.loadingText || 'Loading...');
        } else {
            button.disabled = false;
            button.classList.remove('btn-loading');
            
            // Restore original content
            if (button.dataset.originalContent) {
                button.innerHTML = button.dataset.originalContent;
            }
        }
    }

    /**
     * Keyboard navigation enhancements
     */
    function initKeyboardNav() {
        // Escape key closes modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                });
            }
        });

        // Add keyboard navigation to tables
        const tables = document.querySelectorAll('.table-hover tbody tr');
        tables.forEach((row, index) => {
            row.setAttribute('tabindex', '0');
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    row.click();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = tables[index + 1];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = tables[index - 1];
                    if (prev) prev.focus();
                }
            });
        });
    }

    /**
     * Accessibility improvements
     */
    function initAccessibility() {
        // Add aria-labels to icon-only buttons
        document.querySelectorAll('.btn i').forEach(icon => {
            const btn = icon.closest('.btn');
            if (btn && !btn.textContent.trim() && !btn.getAttribute('aria-label')) {
                // Try to infer label from icon class
                const iconClass = icon.className;
                let label = 'Button';
                
                if (iconClass.includes('edit')) label = 'Edit';
                else if (iconClass.includes('delete') || iconClass.includes('trash')) label = 'Delete';
                else if (iconClass.includes('view') || iconClass.includes('eye')) label = 'View';
                else if (iconClass.includes('download')) label = 'Download';
                else if (iconClass.includes('close') || iconClass.includes('times')) label = 'Close';
                else if (iconClass.includes('save')) label = 'Save';
                else if (iconClass.includes('print')) label = 'Print';
                else if (iconClass.includes('search')) label = 'Search';
                
                btn.setAttribute('aria-label', label);
            }
        });

        // Add alt text to images missing it
        document.querySelectorAll('img:not([alt])').forEach(img => {
            img.setAttribute('alt', '');
        });

        // Ensure all form inputs have labels
        document.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.id && !document.querySelector(`label[for="${input.id}"]`)) {
                console.warn('Input missing label:', input);
            }
        });
    }

    /**
     * Table responsive enhancements
     */
    function initTableResponsive() {
        // Add table-responsive wrapper to tables that don't have it
        document.querySelectorAll('.table').forEach(table => {
            if (!table.parentElement.classList.contains('table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }

    /**
     * Empty states
     */
    function initEmptyStates() {
        // Auto-detect empty tables and show friendly message
        document.querySelectorAll('.table tbody').forEach(tbody => {
            if (tbody.children.length === 0) {
                const tr = document.createElement('tr');
                const colSpan = tbody.parentElement.querySelector('thead tr').children.length;
                tr.innerHTML = `
                    <td colspan="${colSpan}" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
                            <h5>No data available</h5>
                            <p class="text-muted">There are no items to display at this time.</p>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            }
        });
    }

    /**
     * Show loading overlay
     */
    function showLoadingOverlay(message = 'Loading...') {
        // Remove existing overlay
        removeLoadingOverlay();
        
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div>${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    /**
     * Remove loading overlay
     */
    function removeLoadingOverlay() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    /**
     * Confirm dialog helper
     */
    function confirmAction(message, callback) {
        if (typeof Swal !== 'undefined') {
            // Use SweetAlert2 if available
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3498db',
                cancelButtonColor: '#e74c3c',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        } else {
            // Fallback to native confirm
            if (confirm(message)) {
                if (typeof callback === 'function') {
                    callback();
                }
            }
        }
    }

    /**
     * Copy to clipboard helper
     */
    function copyToClipboard(text, successMessage = 'Copied to clipboard!') {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(successMessage, 'success');
            }).catch(() => {
                fallbackCopyToClipboard(text, successMessage);
            });
        } else {
            fallbackCopyToClipboard(text, successMessage);
        }
    }

    /**
     * Fallback copy to clipboard for older browsers
     */
    function fallbackCopyToClipboard(text, successMessage) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showToast(successMessage, 'success');
        } catch (err) {
            console.error('Failed to copy:', err);
            showToast('Failed to copy to clipboard', 'error');
        }
        
        document.body.removeChild(textArea);
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        // Check if toast.js is available
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else if (typeof Swal !== 'undefined') {
            // Use SweetAlert2 toast
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    // Expose global functions
    window.GlobalUI = {
        setButtonLoading: setButtonLoading,
        showLoadingOverlay: showLoadingOverlay,
        removeLoadingOverlay: removeLoadingOverlay,
        confirmAction: confirmAction,
        copyToClipboard: copyToClipboard,
        showToast: showToast
    };

})();
