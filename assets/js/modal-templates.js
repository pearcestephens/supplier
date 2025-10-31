/**
 * Modal Template System
 * Reusable modal components with AJAX content loading
 *
 * Usage:
 * showModal({
 *     title: 'Order Details',
 *     body: '<p>Content here</p>',
 *     size: 'lg',
 *     footer: true
 * });
 */

class ModalManager {
    constructor() {
        this.modalElement = null;
        this.modalInstance = null;
        this.createModalContainer();
    }

    createModalContainer() {
        // Check if modal already exists
        let existing = document.getElementById('global-modal');
        if (existing) {
            this.modalElement = existing;
            return;
        }

        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="global-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="global-modal-title">Modal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="global-modal-body">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" id="global-modal-footer" style="display: none;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modalElement = document.getElementById('global-modal');
    }

    show(options = {}) {
        const {
            title = 'Modal',
            body = '',
            footer = true,
            size = 'md', // sm, md, lg, xl
            closeButton = true,
            backdrop = true, // true, false, 'static'
            keyboard = true,
            buttons = [],
            onShow = null,
            onHide = null
        } = options;

        // Set title
        const titleEl = document.getElementById('global-modal-title');
        titleEl.textContent = title;

        // Set body
        const bodyEl = document.getElementById('global-modal-body');
        bodyEl.innerHTML = body;

        // Set size
        const dialogEl = this.modalElement.querySelector('.modal-dialog');
        dialogEl.className = 'modal-dialog';
        if (size !== 'md') {
            dialogEl.classList.add(`modal-dialog-${size}`);
        }
        if (options.centered) {
            dialogEl.classList.add('modal-dialog-centered');
        }
        if (options.scrollable) {
            dialogEl.classList.add('modal-dialog-scrollable');
        }

        // Set footer
        const footerEl = document.getElementById('global-modal-footer');
        if (footer === false) {
            footerEl.style.display = 'none';
        } else if (buttons.length > 0) {
            footerEl.style.display = 'flex';
            footerEl.innerHTML = '';
            buttons.forEach(btn => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = btn.class || 'btn btn-primary';
                button.textContent = btn.text;
                if (btn.dismiss) {
                    button.setAttribute('data-bs-dismiss', 'modal');
                }
                button.addEventListener('click', () => {
                    if (btn.action) {
                        btn.action(this);
                    }
                });
                footerEl.appendChild(button);
            });
        } else {
            footerEl.style.display = 'flex';
            footerEl.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        }

        // Hide close button if needed
        const closeBtn = this.modalElement.querySelector('.btn-close');
        if (!closeButton) {
            closeBtn.style.display = 'none';
        } else {
            closeBtn.style.display = '';
        }

        // Create Bootstrap modal instance
        if (this.modalInstance) {
            this.modalInstance.dispose();
        }

        this.modalInstance = new bootstrap.Modal(this.modalElement, {
            backdrop: backdrop,
            keyboard: keyboard
        });

        // Event listeners
        if (onShow) {
            this.modalElement.addEventListener('shown.bs.modal', onShow, { once: true });
        }
        if (onHide) {
            this.modalElement.addEventListener('hidden.bs.modal', onHide, { once: true });
        }

        // Show modal
        this.modalInstance.show();
    }

    async showAjax(options = {}) {
        const {
            url,
            method = 'GET',
            data = {},
            ...modalOptions
        } = options;

        // Show loading state
        this.show({
            ...modalOptions,
            body: `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">Loading content...</p>
                </div>
            `,
            footer: false
        });

        try {
            const fetchOptions = {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (method !== 'GET') {
                fetchOptions.body = JSON.stringify(data);
            }

            const response = await fetch(url, fetchOptions);
            const result = await response.json();

            if (result.success) {
                // Update modal content
                const bodyEl = document.getElementById('global-modal-body');
                bodyEl.innerHTML = result.html || result.data;

                // Show footer if needed
                if (modalOptions.footer !== false) {
                    const footerEl = document.getElementById('global-modal-footer');
                    footerEl.style.display = 'flex';
                }
            } else {
                throw new Error(result.message || 'Failed to load content');
            }
        } catch (error) {
            console.error('AJAX modal error:', error);
            const bodyEl = document.getElementById('global-modal-body');
            bodyEl.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load content: ${error.message}
                </div>
            `;
        }
    }

    hide() {
        if (this.modalInstance) {
            this.modalInstance.hide();
        }
    }

    updateBody(html) {
        const bodyEl = document.getElementById('global-modal-body');
        bodyEl.innerHTML = html;
    }

    updateTitle(title) {
        const titleEl = document.getElementById('global-modal-title');
        titleEl.textContent = title;
    }
}

// Global modal instance
const globalModal = new ModalManager();

// Convenience functions
function showModal(options) {
    globalModal.show(options);
}

function showAjaxModal(options) {
    return globalModal.showAjax(options);
}

function hideModal() {
    globalModal.hide();
}

// Warranty Detail Modal Template
function showWarrantyDetailModal(claimId) {
    showAjaxModal({
        title: 'Warranty Claim Details',
        url: `/supplier/api/get-warranty-detail.php?id=${claimId}`,
        size: 'lg',
        centered: true,
        buttons: [
            {
                text: 'Close',
                class: 'btn btn-secondary',
                dismiss: true
            }
        ]
    });
}

// Order Detail Modal Template
function showOrderDetailModal(orderId) {
    showAjaxModal({
        title: 'Order Details',
        url: `/supplier/api/get-order-detail.php?id=${orderId}`,
        size: 'xl',
        centered: true,
        scrollable: true,
        buttons: [
            {
                text: 'Print',
                class: 'btn btn-outline-primary',
                action: () => window.print()
            },
            {
                text: 'Close',
                class: 'btn btn-secondary',
                dismiss: true
            }
        ]
    });
}

// Confirmation Modal Template
function showConfirmationModal(options) {
    const {
        title = 'Confirm Action',
        message = 'Are you sure?',
        confirmText = 'Confirm',
        confirmClass = 'btn-primary',
        onConfirm
    } = options;

    showModal({
        title: title,
        body: `<p>${message}</p>`,
        centered: true,
        buttons: [
            {
                text: 'Cancel',
                class: 'btn btn-secondary',
                dismiss: true
            },
            {
                text: confirmText,
                class: `btn ${confirmClass}`,
                action: (modal) => {
                    if (onConfirm) {
                        onConfirm();
                    }
                    modal.hide();
                }
            }
        ]
    });
}

// Auto-attach to data-modal-ajax elements
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-modal-ajax]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();

            const url = this.getAttribute('data-modal-ajax');
            const title = this.getAttribute('data-modal-title') || 'Details';
            const size = this.getAttribute('data-modal-size') || 'lg';

            showAjaxModal({
                title: title,
                url: url,
                size: size,
                centered: true
            });
        });
    });
});
