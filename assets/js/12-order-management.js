/**
 * Order Management - Streamlined Edition
 *
 * Handles:
 * - Status changes (OPEN ↔ SENT with 24-hour grace period)
 * - Simplified tracking with carrier
 * - Edit modal with store, PO, status, tracking
 * - Notes system
 *
 * @version 2.0.0
 * @date 2025-10-31
 */

// =============================================================================
// STREAMLINED EDIT ORDER MODAL
// =============================================================================

/**
 * Show comprehensive edit order modal
 * Includes: Status, Store, PO, Tracking, Carrier, Notes
 */
function editOrder(orderId) {
    // Show loading
    Swal.fire({
        title: 'Loading Order...',
        html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    // Fetch order details
    fetch(`/supplier/api/get-order-detail.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load order');
            }

            const order = data.order;
            showEditOrderModal(order);
        })
        .catch(error => {
            console.error('Load order error:', error);
            Swal.fire('Error', error.message || 'Failed to load order details', 'error');
        });
}

function showEditOrderModal(order) {
    const canChangeStatus = checkStatusChangePermission(order);
    let trackingNumbers = order.tracking_number ? order.tracking_number.split(',').map(t => t.trim()).filter(t => t) : [];

    function renderModalContent() {
        return `
            <div class="text-start">
                <!-- Order Info Section -->
                <div class="edit-order-section">
                    <h6><i class="fas fa-info-circle"></i> Order Information</h6>
                    <div class="order-info-row">
                        <span class="order-info-label">Store:</span>
                        <span class="order-info-value">${order.outlet_name || 'Unknown'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">PO Number:</span>
                        <span class="order-info-value">${order.vend_number || order.public_id || '-'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Created:</span>
                        <span class="order-info-value">${new Date(order.created_at).toLocaleString()}</span>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="edit-order-section">
                    <h6><i class="fas fa-exchange-alt"></i> Status</h6>
                    ${renderStatusChangeUI(order, canChangeStatus)}
                </div>

                <!-- Carrier Section -->
                <div class="edit-order-section">
                    <h6><i class="fas fa-truck"></i> Carrier</h6>
                    <select id="edit-carrier" class="carrier-select form-control">
                        <option value="NZ Post" ${order.carrier === 'NZ Post' ? 'selected' : ''}>NZ Post</option>
                        <option value="CourierPost" ${order.carrier === 'CourierPost' ? 'selected' : ''}>CourierPost</option>
                        <option value="Aramex" ${order.carrier === 'Aramex' ? 'selected' : ''}>Aramex</option>
                        <option value="DHL" ${order.carrier === 'DHL' ? 'selected' : ''}>DHL</option>
                        <option value="FedEx" ${order.carrier === 'FedEx' ? 'selected' : ''}>FedEx</option>
                        <option value="TNT" ${order.carrier === 'TNT' ? 'selected' : ''}>TNT</option>
                        <option value="Other" ${order.carrier === 'Other' || !order.carrier ? 'selected' : ''}>Other</option>
                    </select>
                </div>

                <!-- Tracking Section (Compact) -->
                <div class="edit-order-section">
                    <h6><i class="fas fa-barcode"></i> Tracking Numbers</h6>
                    <div class="tracking-input-group">
                        <input type="text" id="edit-tracking-input" class="form-control" placeholder="Add tracking number" onkeypress="if(event.key==='Enter'){event.preventDefault();document.getElementById('edit-add-tracking-btn').click();}">
                        <button class="btn btn-success btn-add" type="button" id="edit-add-tracking-btn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Parcel Count</label>
                        <input type="text" class="parcel-counter form-control form-control-sm" id="edit-parcel-counter" value="${trackingNumbers.length}" readonly>
                    </div>
                    <div class="tracking-list" id="edit-tracking-list">
                        ${renderTrackingList(trackingNumbers)}
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="edit-order-section">
                    <h6><i class="fas fa-sticky-note"></i> Add Note (Optional)</h6>
                    <textarea id="edit-note" class="form-control" rows="3" placeholder="Enter order note..."></textarea>
                </div>
            </div>
        `;
    }

    function renderStatusChangeUI(order, canChange) {
        const currentStatus = order.state || 'OPEN';

        if (!canChange.allowed) {
            return `
                <div class="status-locked">
                    <i class="fas fa-lock"></i>
                    <p class="mb-0"><strong>Status Locked:</strong> ${canChange.reason}</p>
                </div>
                <div class="mt-2">
                    <span class="status-badge status-${currentStatus.toLowerCase()}">${currentStatus}</span>
                </div>
            `;
        }

        if (canChange.warning) {
            return `
                <div class="status-change-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p class="mb-0">${canChange.warning}</p>
                </div>
                <select id="edit-status" class="form-select">
                    <option value="OPEN" ${currentStatus === 'OPEN' ? 'selected' : ''}>OPEN</option>
                    <option value="SENT" ${currentStatus === 'SENT' ? 'selected' : ''}>SENT</option>
                </select>
            `;
        }

        return `
            <select id="edit-status" class="form-select">
                <option value="OPEN" ${currentStatus === 'OPEN' ? 'selected' : ''}>OPEN</option>
                <option value="SENT" ${currentStatus === 'SENT' ? 'selected' : ''}>SENT</option>
            </select>
            <small class="text-muted mt-1 d-block">You can change between OPEN and SENT within 24 hours</small>
        `;
    }

    function renderTrackingList(numbers) {
        if (numbers.length === 0) {
            return '<div class="tracking-list-empty">No tracking numbers added</div>';
        }

        return numbers.map((num, index) => `
            <div class="tracking-item">
                <div>
                    <span class="tracking-item-badge">#${index + 1}</span>
                    <span class="tracking-item-number">${num}</span>
                </div>
                <button class="btn btn-sm btn-danger" onclick="window.editRemoveTracking(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }

    // Global functions for tracking management
    window.editRemoveTracking = (index) => {
        trackingNumbers.splice(index, 1);
        updateTrackingDisplay();
    };

    function updateTrackingDisplay() {
        const listEl = document.getElementById('edit-tracking-list');
        const counterEl = document.getElementById('edit-parcel-counter');

        if (listEl) listEl.innerHTML = renderTrackingList(trackingNumbers);
        if (counterEl) counterEl.value = trackingNumbers.length;
    }

    function attachTrackingListeners() {
        const addBtn = document.getElementById('edit-add-tracking-btn');
        const input = document.getElementById('edit-tracking-input');

        if (addBtn && input) {
            addBtn.onclick = () => {
                const value = input.value.trim();
                if (value) {
                    if (trackingNumbers.includes(value)) {
                        Swal.showValidationMessage('This tracking number already exists');
                        return;
                    }
                    trackingNumbers.push(value);
                    input.value = '';
                    updateTrackingDisplay();
                    setTimeout(() => input.focus(), 100);
                }
            };
        }
    }

    // Show modal
    Swal.fire({
        title: `Edit Order #${order.vend_number || order.public_id}`,
        html: renderModalContent(),
        width: '650px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Save Changes',
        cancelButtonText: 'Cancel',
        didOpen: () => {
            attachTrackingListeners();
        },
        preConfirm: () => {
            const status = document.getElementById('edit-status')?.value || order.state;
            const carrier = document.getElementById('edit-carrier')?.value;
            const note = document.getElementById('edit-note')?.value.trim();

            return {
                order_id: order.id,
                status: status,
                carrier: carrier,
                tracking_numbers: trackingNumbers,
                note: note || null
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveOrderChanges(result.value);
        }
    });
}

/**
 * Check if status can be changed
 * Rules:
 * - OPEN ↔ SENT: Allowed within 24 hours
 * - RECEIVED/CANCELLED: Locked
 */
function checkStatusChangePermission(order) {
    const status = order.state || 'OPEN';
    const updated = new Date(order.updated_at);
    const now = new Date();
    const hoursSinceUpdate = (now - updated) / (1000 * 60 * 60);

    // Status is RECEIVED or CANCELLED - completely locked
    if (status === 'RECEIVED' || status === 'RECEIVING' || status === 'CANCELLED') {
        return {
            allowed: false,
            reason: `Order status is ${status} and cannot be changed`
        };
    }

    // SENT status but more than 24 hours - locked
    if (status === 'SENT' && hoursSinceUpdate > 24) {
        return {
            allowed: false,
            reason: '24-hour grace period has expired'
        };
    }

    // SENT status within 24 hours - warning but allowed
    if (status === 'SENT' && hoursSinceUpdate <= 24) {
        const remaining = Math.ceil(24 - hoursSinceUpdate);
        return {
            allowed: true,
            warning: `You have ${remaining} hour(s) remaining to modify this order`
        };
    }

    // OPEN status - fully allowed
    return {
        allowed: true,
        warning: null
    };
}

/**
 * Save order changes to backend
 */
function saveOrderChanges(data) {
    Swal.fire({
        title: 'Saving Changes...',
        html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch('/supplier/api/update-order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Order Updated!',
                text: result.message || 'Changes saved successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', result.message || 'Failed to save changes', 'error');
        }
    })
    .catch(error => {
        console.error('Save order error:', error);
        Swal.fire('Error', 'Failed to save changes', 'error');
    });
}

// =============================================================================
// NOTES SYSTEM
// =============================================================================

/**
 * View order notes in modal
 */
function viewOrderNotes(orderId) {
    Swal.fire({
        title: 'Loading Notes...',
        html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`/supplier/api/get-order-history.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load notes');
            }

            showNotesModal(orderId, data.history || []);
        })
        .catch(error => {
            console.error('Load notes error:', error);
            Swal.fire('Error', error.message || 'Failed to load notes', 'error');
        });
}

function showNotesModal(orderId, history) {
    const notesHtml = history.length === 0
        ? '<p class="text-muted text-center">No notes yet</p>'
        : history.map(entry => `
            <div class="note-item ${entry.created_by === 'system' ? 'note-system' : ''}">
                <div class="note-header">
                    <span class="note-author">
                        ${entry.created_by === 'system' ? '<i class="fas fa-robot"></i> System' : entry.created_by || 'Unknown'}
                    </span>
                    <span class="note-date">${new Date(entry.created_at).toLocaleString()}</span>
                </div>
                <div class="note-content">${entry.action}${entry.note ? '\n' + escapeHtml(entry.note) : ''}</div>
            </div>
        `).join('');

    Swal.fire({
        title: 'Order Notes & History',
        html: `
            <div class="text-start">
                <div class="notes-input-section">
                    <label class="label-bold mb-2">Add New Note</label>
                    <textarea id="new-note-text" class="notes-textarea" placeholder="Enter note..."></textarea>
                    <button class="btn btn-primary btn-sm mt-2 w-100" onclick="window.addOrderNote(${orderId})">
                        <i class="fas fa-plus"></i> Add Note
                    </button>
                </div>
                <div class="notes-history">
                    <label class="label-bold mb-2">History</label>
                    ${notesHtml}
                </div>
            </div>
        `,
        width: '700px',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close'
    });
}

window.addOrderNote = function(orderId) {
    const text = document.getElementById('new-note-text')?.value.trim();

    if (!text) {
        Swal.showValidationMessage('Please enter a note');
        return;
    }

    fetch('/supplier/api/add-order-note.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: orderId,
            note_text: text
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notes
            viewOrderNotes(orderId);
        } else {
            Swal.showValidationMessage(data.message || 'Failed to add note');
        }
    })
    .catch(error => {
        console.error('Add note error:', error);
        Swal.showValidationMessage('Failed to add note');
    });
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// =============================================================================
// QUICK ORDER VIEW
// =============================================================================

/**
 * Show quick order overview with notes
 */
function quickViewOrderWithNotes(orderId) {
    Swal.fire({
        title: 'Loading Order...',
        html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    Promise.all([
        fetch(`/supplier/api/get-order-detail.php?id=${orderId}`).then(r => r.json()),
        fetch(`/supplier/api/get-order-history.php?id=${orderId}`).then(r => r.json())
    ])
    .then(([orderData, historyData]) => {
        if (!orderData.success) throw new Error('Failed to load order');

        const order = orderData.order;
        const history = historyData.success ? historyData.history : [];

        showQuickOrderModal(order, history);
    })
    .catch(error => {
        console.error('Quick view error:', error);
        Swal.fire('Error', error.message || 'Failed to load order', 'error');
    });
}

function showQuickOrderModal(order, history) {
    const notesPreview = history.slice(0, 3).map(entry => `
        <div class="note-item ${entry.created_by === 'system' ? 'note-system' : ''}" style="margin-bottom: 0.5rem; padding: 0.5rem;">
            <div class="note-header">
                <span class="note-author" style="font-size: 0.85rem;">${entry.created_by || 'System'}</span>
                <span class="note-date" style="font-size: 0.75rem;">${new Date(entry.created_at).toLocaleDateString()}</span>
            </div>
            <div class="note-content" style="font-size: 0.85rem;">${escapeHtml((entry.note || entry.action).substring(0, 100))}${(entry.note || entry.action).length > 100 ? '...' : ''}</div>
        </div>
    `).join('');

    Swal.fire({
        title: `Order #${order.vend_number || order.public_id}`,
        html: `
            <div class="quick-modal-body">
                <div class="quick-modal-section">
                    <div class="quick-info-grid">
                        <div class="quick-info-item">
                            <span class="quick-info-label">Store</span>
                            <span class="quick-info-value">${order.outlet_name || '-'}</span>
                        </div>
                        <div class="quick-info-item">
                            <span class="quick-info-label">Status</span>
                            <span class="quick-info-value">
                                <span class="status-badge status-${(order.state || 'open').toLowerCase()}">${order.state || 'OPEN'}</span>
                            </span>
                        </div>
                        <div class="quick-info-item">
                            <span class="quick-info-label">Carrier</span>
                            <span class="quick-info-value">${order.carrier || 'Not specified'}</span>
                        </div>
                        <div class="quick-info-item">
                            <span class="quick-info-label">Parcels</span>
                            <span class="quick-info-value">${order.tracking_number ? order.tracking_number.split(',').length : 0}</span>
                        </div>
                    </div>
                </div>

                ${history.length > 0 ? `
                    <div class="quick-modal-section">
                        <h6>Recent Notes</h6>
                        ${notesPreview}
                        ${history.length > 3 ? `<p class="text-muted small mb-0 mt-2">+ ${history.length - 3} more notes</p>` : ''}
                    </div>
                ` : ''}

                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="editOrder(${order.id}); Swal.close();">
                        <i class="fas fa-edit"></i> Edit Order
                    </button>
                    <button class="btn btn-outline-secondary" onclick="viewOrderNotes(${order.id}); Swal.close();">
                        <i class="fas fa-sticky-note"></i> View All Notes
                    </button>
                </div>
            </div>
        `,
        width: '600px',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close'
    });
}
