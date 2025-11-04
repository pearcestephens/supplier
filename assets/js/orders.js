/**
 * Orders Page JavaScript
 * Extracted from tab-orders.php inline scripts
 * Handles all order management functionality
 *
 * @package SupplierPortal
 * @version 1.0.0
 */

// =============================================================================
// QUICK EDIT ORDER (STREAMLINED)
// =============================================================================

/**
 * Quick edit modal - Status, Tracking, Notes
 * Allows OPEN ↔ SENT with 24-hour grace period
 */
function quickEditOrder(orderId, orderNumber, currentStatus, storeName, trackingNumber, updatedAt) {
    // Check if within 24-hour edit window
    const updatedDate = new Date(updatedAt);
    const now = new Date();
    const hoursSinceUpdate = (now - updatedDate) / (1000 * 60 * 60);
    const canRevertStatus = hoursSinceUpdate < 24;

    // Determine available status transitions
    let statusOptions = '';
    if (currentStatus === 'OPEN') {
        statusOptions = `
            <option value="OPEN" selected>OPEN</option>
            <option value="SENT">SENT</option>
        `;
    } else if (currentStatus === 'SENT' && canRevertStatus) {
        statusOptions = `
            <option value="OPEN">OPEN (revert)</option>
            <option value="SENT" selected>SENT</option>
        `;
    } else if (currentStatus === 'SENT' && !canRevertStatus) {
        statusOptions = `<option value="SENT" selected disabled>SENT (locked after 24h)</option>`;
    } else if (currentStatus === 'RECEIVING' || currentStatus === 'RECEIVED') {
        statusOptions = `<option value="${currentStatus}" selected disabled>${currentStatus} (locked)</option>`;
    } else {
        statusOptions = `<option value="${currentStatus}" selected>${currentStatus}</option>`;
    }

    Swal.fire({
        title: `Quick Edit: ${orderNumber}`,
        html: `
            <div class="text-start">
                <div class="alert alert-info small mb-3">
                    <strong>Store:</strong> ${storeName}<br>
                    <strong>Order:</strong> ${orderNumber}
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <select id="quick-status" class="form-select">
                        ${statusOptions}
                    </select>
                    ${!canRevertStatus && currentStatus === 'SENT' ?
                        '<small class="text-muted">Status locked after 24 hours</small>' : ''}
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Carrier</label>
                    <select id="quick-carrier" class="form-select">
                        <option value="">-- Select Carrier --</option>
                        <option value="NZ Post">NZ Post</option>
                        <option value="CourierPost">CourierPost</option>
                        <option value="Aramex">Aramex</option>
                        <option value="DHL">DHL</option>
                        <option value="FedEx">FedEx</option>
                        <option value="UPS">UPS</option>
                        <option value="Fastway">Fastway</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tracking Number</label>
                    <input type="text" id="quick-tracking" class="form-control"
                           placeholder="Enter tracking number (optional)"
                           value="${trackingNumber || ''}">
                    <small class="text-muted">For multiple parcels, use "Add Tracking" instead</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Add Note (optional)</label>
                    <textarea id="quick-note" class="form-control" rows="3"
                              placeholder="Enter any notes about this order..."></textarea>
                </div>
            </div>
        `,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Save Changes',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const status = document.getElementById('quick-status').value;
            const carrier = document.getElementById('quick-carrier').value;
            const tracking = document.getElementById('quick-tracking').value.trim();
            const note = document.getElementById('quick-note').value.trim();

            return { status, carrier, tracking, note };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { status, carrier, tracking, note } = result.value;

            Swal.fire({
                title: 'Saving...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            fetch('/supplier/api/quick-update-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status,
                    carrier: carrier,
                    tracking_number: tracking,
                    note: note
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: data.message || 'Order updated successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to update order', 'error');
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                Swal.fire('Error', 'Failed to update order', 'error');
            });
        }
    });
}

// =============================================================================
// CSV EXPORT
// =============================================================================

/**
 * Export orders to CSV with current filters
 */
function exportOrdersCSV() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status') || 'all';
    const outlet = urlParams.get('outlet') || 'all';
    const search = urlParams.get('search') || '';

    const params = new URLSearchParams({
        export: 'csv',
        status: status,
        outlet: outlet,
        search: search
    });

    window.location.href = '/supplier/api/export-orders.php?' + params.toString();
}

// =============================================================================
// TRACKING UPDATE
// =============================================================================

function updateTracking(orderId) {
    const trackingNumber = prompt('Enter tracking number for this shipment:');
    if (!trackingNumber) return;

    const carrier = prompt('Enter carrier name (e.g., FedEx, UPS, DHL, NZ Post):');
    if (!carrier) return;

    fetch('/supplier/api/update-tracking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: orderId,
            tracking_number: trackingNumber,
            carrier: carrier
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tracking information updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Update failed'));
        }
    })
    .catch(error => {
        console.error('Update tracking error:', error);
        alert('Failed to update tracking: ' + error.message);
    });
}

//============================================================================
// BULK TRACKING UPDATE
// =============================================================================

function bulkUpdateTracking() {
    const csvData = prompt(
        'Paste CSV data (Order Number, Tracking Number):\n\n' +
        'Example:\n' +
        'JCE-PO-12345,TNT123456\n' +
        'JCE-PO-12346,TNT123457'
    );

    if (!csvData) return;

    const lines = csvData.trim().split('\n');
    if (lines.length === 0) {
        alert('No data provided');
        return;
    }

    const updates = [];
    for (const line of lines) {
        const [orderNumber, tracking] = line.split(',').map(s => s.trim());
        if (orderNumber && tracking) {
            updates.push({ order: orderNumber, tracking: tracking });
        }
    }

    if (updates.length === 0) {
        alert('Invalid CSV format');
        return;
    }

    // TODO: Send to bulk update API
    alert(`Ready to update ${updates.length} orders. API integration pending.`);
}

// =============================================================================
// ORDER STATUS UPDATE
// =============================================================================

function updateOrder(orderId) {
    const status = prompt(
        'Update order status:\n\n' +
        'Enter:\n' +
        '1 = Mark as Shipped\n' +
        '2 = Add Note\n' +
        '3 = Request More Info'
    );

    if (status === '1') {
        updateTracking(orderId);
    }
    else if (status === '2') {
        const note = prompt('Enter your note:');
        if (note) {
            fetch('/supplier/api/add-order-note.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    note: note
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Note added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to add note'));
                }
            })
            .catch(error => {
                console.error('Add note error:', error);
                alert('Failed to add note: ' + error.message);
            });
        }
    }
    else if (status === '3') {
        const message = prompt('What information do you need?');
        if (message) {
            fetch('/supplier/api/request-info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Request sent to Vape Shed team!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to send request'));
                }
            })
            .catch(error => {
                console.error('Request info error:', error);
                alert('Failed to send request: ' + error.message);
            });
        }
    }
}

// =============================================================================
// AUTO-SUBMIT FILTERS
// =============================================================================

document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll(
        'select[name="per_page"], select[name="status"], select[name="outlet"]'
    );

    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    console.log('Orders page JavaScript loaded');
    console.log(`Initialized ${filterSelects.length} auto-submit filters`);
});

// =============================================================================
// QUICK VIEW ORDER MODAL
// =============================================================================

/**
 * Show quick preview of order in modal without navigating away
 */
function quickViewOrder(orderId) {
    Swal.fire({
        title: 'Loading Order Details...',
        html: '<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i></div>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`/supplier/api/get-order-detail.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const order = data.order;
                const items = data.items || [];

                let itemsHtml = '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;"><table class="table table-sm table-bordered">';
                itemsHtml += '<thead><tr><th>SKU</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>';

                items.forEach(item => {
                    const qty = parseFloat(item.quantity || 0);
                    const unitPrice = parseFloat(item.unit_price || 0);
                    const lineTotal = parseFloat(item.line_total || (qty * unitPrice));

                    itemsHtml += `<tr>
                        <td><code>${item.sku || '-'}</code></td>
                        <td>${item.product_name || 'Unknown'}</td>
                        <td class="text-center">${qty}</td>
                        <td class="text-end">$${unitPrice.toFixed(2)}</td>
                        <td class="text-end fw-bold">$${lineTotal.toFixed(2)}</td>
                    </tr>`;
                });

                itemsHtml += '</tbody></table></div>';

                Swal.fire({
                    title: `Order #${order.vend_number || order.public_id}`,
                    html: `
                        <div class="text-start">
                            <div class="mb-3">
                                <strong>Store:</strong> ${order.outlet_name}<br>
                                <strong>Status:</strong> <span class="badge bg-primary">${order.state}</span><br>
                                <strong>Created:</strong> ${new Date(order.created_at).toLocaleDateString()}<br>
                                ${order.tracking_number ? `<strong>Tracking:</strong> <code>${order.tracking_number}</code><br>` : ''}
                            </div>
                            <h6>Line Items</h6>
                            ${itemsHtml}
                        </div>
                    `,
                    width: '800px',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-external-link-alt"></i> View Full Details',
                    cancelButtonText: 'Close',
                    confirmButtonColor: '#0d6efd'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `/supplier/order-detail.php?id=${orderId}`;
                    }
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to load order details', 'error');
            }
        })
        .catch(error => {
            console.error('Quick view error:', error);
            Swal.fire('Error', 'Failed to load order details', 'error');
        });
}

// =============================================================================
// ADD TRACKING MODAL (MULTIPLE TRACKING NUMBERS)
// =============================================================================

/**
 * Show modal to add one or more tracking numbers to an order
 * ONE LINE PER PARCEL with Add button and parcel counter
 */
function addTrackingModal(orderId, orderNumber) {
    let trackingList = [];

    function renderTrackingUI() {
        let html = `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Carrier</label>
                    <select id="tracking-carrier" class="form-select">
                        <option value="NZ Post">NZ Post</option>
                        <option value="CourierPost">CourierPost</option>
                        <option value="FedEx">FedEx</option>
                        <option value="DHL">DHL</option>
                        <option value="UPS">UPS</option>
                        <option value="TNT">TNT</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Add Tracking Number (1 per parcel)</label>
                    <div class="input-group">
                        <input type="text" id="tracking-input" class="form-control" placeholder="Enter tracking number" onkeypress="if(event.key==='Enter'){event.preventDefault();document.getElementById('add-tracking-btn').click();}">
                        <button class="btn btn-success" type="button" id="add-tracking-btn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <small class="text-muted">Enter one tracking number and click Add. Repeat for each parcel.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Parcel Count (readonly)</label>
                    <input type="text" class="form-control" id="parcel-counter" value="${trackingList.length}" readonly style="background-color: #e9ecef; font-weight: bold;">
                </div>

                <div class="mb-3" id="tracking-list-container">
                    <label class="form-label fw-bold">Tracking Numbers</label>
                    ${trackingList.length === 0 ? '<p class="text-muted small">No tracking numbers added yet</p>' : ''}
                    <div class="list-group" style="max-height: 200px; overflow-y: auto;">
        `;

        trackingList.forEach((tracking, index) => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>#${index + 1}</strong> <code>${tracking}</code></span>
                    <button class="btn btn-sm btn-danger" onclick="window.removeTracking(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    // Global function to remove tracking
    window.removeTracking = (index) => {
        trackingList.splice(index, 1);
        Swal.update({ html: renderTrackingUI() });
        attachAddButtonListener();
    };

    function attachAddButtonListener() {
        const addBtn = document.getElementById('add-tracking-btn');
        const input = document.getElementById('tracking-input');

        if (addBtn && input) {
            addBtn.onclick = () => {
                const value = input.value.trim();
                if (value) {
                    trackingList.push(value);
                    Swal.update({ html: renderTrackingUI() });
                    attachAddButtonListener();
                    setTimeout(() => input.focus(), 100);
                } else {
                    Swal.showValidationMessage('Please enter a tracking number');
                }
            };
        }
    }

    Swal.fire({
        title: `Add Tracking - Order #${orderNumber}`,
        html: renderTrackingUI(),
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Submit All Tracking',
        cancelButtonText: 'Cancel',
        width: '600px',
        didOpen: () => {
            attachAddButtonListener();
            document.getElementById('tracking-input').focus();
        },
        preConfirm: () => {
            const carrier = document.getElementById('tracking-carrier').value;

            if (trackingList.length === 0) {
                Swal.showValidationMessage('Please add at least one tracking number');
                return false;
            }

            return { carrier, trackingNumbers: trackingList };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { carrier, trackingNumbers } = result.value;

            Swal.fire({
                title: 'Adding Tracking...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            fetch('/supplier/api/add-tracking-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    tracking_numbers: trackingNumbers,
                    carrier_name: carrier
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        html: `Added ${trackingNumbers.length} parcel(s) with tracking`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to add tracking', 'error');
                }
            })
            .catch(error => {
                console.error('Add tracking error:', error);
                Swal.fire('Error', 'Failed to add tracking numbers', 'error');
            });
        }
    });
}

// =============================================================================
// EDIT ORDER MODAL (STATUS + TRACKING)
// =============================================================================

/**
 * Show modal to edit order status and tracking
 */
function editOrderModal(orderId, currentStatus) {
    // Track all tracking numbers
    let trackingList = [];

    // Function to update the tracking display and box counter
    function updateTrackingDisplay() {
        const trackingDisplayDiv = document.getElementById('tracking-display');
        const boxCounterInput = document.getElementById('box-counter');

        if (!trackingDisplayDiv || !boxCounterInput) return;

        // Update box counter
        boxCounterInput.value = trackingList.length;

        // Update tracking display
        if (trackingList.length === 0) {
            trackingDisplayDiv.innerHTML = '<p class="text-muted small mb-0 p-3">No tracking numbers added yet</p>';
        } else {
            let html = '<div class="list-group list-group-flush">';
            trackingList.forEach((tracking, index) => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-2">
                        <code class="mb-0">${tracking}</code>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-tracking" data-index="${index}" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            trackingDisplayDiv.innerHTML = html;

            // Add event listeners to remove buttons
            document.querySelectorAll('.btn-remove-tracking').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    trackingList.splice(index, 1);
                    updateTrackingDisplay();
                });
            });
        }
    }

    // Function to add tracking number
    function addTrackingNumber() {
        const input = document.getElementById('tracking-input');
        if (!input) return;

        const trackingNumber = input.value.trim();

        if (!trackingNumber) {
            Swal.showValidationMessage('Please enter a tracking number');
            setTimeout(() => Swal.resetValidationMessage(), 2000);
            return;
        }

        // Check for duplicates
        if (trackingList.includes(trackingNumber)) {
            Swal.showValidationMessage('This tracking number has already been added');
            setTimeout(() => Swal.resetValidationMessage(), 2000);
            return;
        }

        // Add to list
        trackingList.push(trackingNumber);
        input.value = '';
        updateTrackingDisplay();
        input.focus();
    }

    Swal.fire({
        title: 'Edit Order',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Order Status</label>
                    <select id="edit-status" class="form-select">
                        <option value="OPEN" ${currentStatus === 'OPEN' ? 'selected' : ''}>Open (Not Shipped)</option>
                        <option value="SENT" ${currentStatus === 'SENT' ? 'selected' : ''}>Sent (Shipped)</option>
                        <option value="RECEIVING" ${currentStatus === 'RECEIVING' ? 'selected' : ''}>Receiving (In Transit)</option>
                        <option value="RECEIVED" ${currentStatus === 'RECEIVED' ? 'selected' : ''}>Received (Complete)</option>
                        <option value="CANCELLED" ${currentStatus === 'CANCELLED' ? 'selected' : ''}>Cancelled</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Number of Parcels</label>
                    <input type="number" id="box-counter" class="form-control form-control-lg text-center fw-bold" value="0" readonly style="background-color: #e9ecef; cursor: not-allowed; font-size: 1.5rem;" title="Auto-counted from tracking numbers">
                    <small class="text-muted"><i class="fas fa-info-circle"></i> Each tracking number = 1 parcel</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Add Tracking Number</label>
                    <div class="input-group">
                        <input type="text" id="tracking-input" class="form-control" placeholder="Enter tracking number" autocomplete="off">
                        <button class="btn btn-primary" type="button" id="btn-add-tracking">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <small class="text-muted">Enter one tracking number at a time, then click Add</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tracking Numbers Added</label>
                    <div id="tracking-display" class="border rounded bg-light" style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                        <p class="text-muted small mb-0 p-3">No tracking numbers added yet</p>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Note (Optional)</label>
                    <textarea id="edit-note" class="form-control" rows="2" placeholder="Add a note about this change..."></textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Update Order',
        cancelButtonText: 'Cancel',
        width: '650px',
        didOpen: () => {
            // Add event listener to Add button
            const addBtn = document.getElementById('btn-add-tracking');
            const trackingInput = document.getElementById('tracking-input');

            if (addBtn) {
                addBtn.addEventListener('click', addTrackingNumber);
            }

            // Allow Enter key to add tracking
            if (trackingInput) {
                trackingInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addTrackingNumber();
                    }
                });
                trackingInput.focus();
            }

            // Initial display
            updateTrackingDisplay();
        },
        preConfirm: () => {
            const status = document.getElementById('edit-status').value;
            const note = document.getElementById('edit-note').value.trim();
            const boxCount = document.getElementById('box-counter').value;

            return { status, trackingNumbers: trackingList, note, boxCount };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { status, trackingNumbers, note, boxCount } = result.value;

            Swal.fire({
                title: 'Updating Order...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            fetch('/supplier/api/update-order-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    new_status: status,
                    tracking_numbers: trackingNumbers, // Send as array
                    tracking_number: trackingNumbers.join(', '), // Also send combined for backward compatibility
                    box_count: boxCount,
                    note: note
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const boxCountText = boxCount > 0 ? ` (${boxCount} box${boxCount > 1 ? 'es' : ''})` : '';
                    Swal.fire({
                        icon: 'success',
                        title: 'Order Updated!',
                        text: `Status updated successfully${boxCountText}`,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to update order', 'error');
                }
            })
            .catch(error => {
                console.error('Update order error:', error);
                Swal.fire('Error', 'Failed to update order', 'error');
            });
        }
    });
}

// =============================================================================
// BULK ADD TRACKING
// =============================================================================

/**
 * Bulk add tracking to multiple selected orders
 * Shows modal with one-line-per-parcel system
 */
function bulkAddTracking() {
    const selectedOrders = getSelectedOrders();

    if (selectedOrders.length === 0) {
        Swal.fire('No Orders Selected', 'Please select at least one order to add tracking', 'warning');
        return;
    }

    let trackingList = [];

    function renderBulkTrackingUI() {
        let html = `
            <div class="text-start">
                <div class="alert alert-info mb-3">
                    <strong>Bulk Adding Tracking to ${selectedOrders.length} order(s)</strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Carrier (applies to all)</label>
                    <select id="bulk-tracking-carrier" class="form-select">
                        <option value="NZ Post">NZ Post</option>
                        <option value="CourierPost">CourierPost</option>
                        <option value="FedEx">FedEx</option>
                        <option value="DHL">DHL</option>
                        <option value="UPS">UPS</option>
                        <option value="TNT">TNT</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Add Tracking Number</label>
                    <div class="input-group">
                        <input type="text" id="bulk-tracking-input" class="form-control" placeholder="Enter tracking number" onkeypress="if(event.key==='Enter'){event.preventDefault();document.getElementById('bulk-add-tracking-btn').click();}">
                        <button class="btn btn-success" type="button" id="bulk-add-tracking-btn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <small class="text-muted">Add ${selectedOrders.length} tracking numbers (one per order)</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tracking Count (readonly)</label>
                    <input type="text" class="form-control" id="bulk-parcel-counter" value="${trackingList.length} / ${selectedOrders.length}" readonly style="background-color: #e9ecef; font-weight: bold;">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tracking Numbers</label>
                    ${trackingList.length === 0 ? '<p class="text-muted small">No tracking numbers added yet. Need ' + selectedOrders.length + ' total.</p>' : ''}
                    <div class="list-group" style="max-height: 200px; overflow-y: auto;">
        `;

        trackingList.forEach((tracking, index) => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>#${index + 1}</strong> <code>${tracking}</code></span>
                    <button class="btn btn-sm btn-danger" onclick="window.removeBulkTracking(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                ${trackingList.length === selectedOrders.length ? '<div class="alert alert-success"><i class="fas fa-check"></i> All tracking numbers added! Ready to submit.</div>' : ''}
                ${trackingList.length > selectedOrders.length ? '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You have more tracking numbers than orders!</div>' : ''}
            </div>
        `;

        return html;
    }

    window.removeBulkTracking = (index) => {
        trackingList.splice(index, 1);
        Swal.update({ html: renderBulkTrackingUI() });
        attachBulkAddButtonListener();
    };

    function attachBulkAddButtonListener() {
        const addBtn = document.getElementById('bulk-add-tracking-btn');
        const input = document.getElementById('bulk-tracking-input');

        if (addBtn && input) {
            addBtn.onclick = () => {
                const value = input.value.trim();
                if (value) {
                    trackingList.push(value);
                    Swal.update({ html: renderBulkTrackingUI() });
                    attachBulkAddButtonListener();
                    setTimeout(() => input.focus(), 100);
                } else {
                    Swal.showValidationMessage('Please enter a tracking number');
                }
            };
        }
    }

    Swal.fire({
        title: 'Bulk Add Tracking',
        html: renderBulkTrackingUI(),
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Submit All',
        cancelButtonText: 'Cancel',
        width: '600px',
        didOpen: () => {
            attachBulkAddButtonListener();
            document.getElementById('bulk-tracking-input').focus();
        },
        preConfirm: () => {
            const carrier = document.getElementById('bulk-tracking-carrier').value;

            if (trackingList.length === 0) {
                Swal.showValidationMessage('Please add at least one tracking number');
                return false;
            }

            if (trackingList.length !== selectedOrders.length) {
                Swal.showValidationMessage(`You need exactly ${selectedOrders.length} tracking numbers (one per order). Currently have ${trackingList.length}.`);
                return false;
            }

            return { carrier, trackingNumbers: trackingList };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { carrier, trackingNumbers } = result.value;

            Swal.fire({
                title: 'Processing Bulk Tracking...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-3">Adding tracking to ' + selectedOrders.length + ' orders...</p>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // Process each order
            const requests = selectedOrders.map((orderId, index) => {
                return fetch('/supplier/api/add-tracking-simple.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        tracking_numbers: [trackingNumbers[index]], // One tracking per order
                        carrier_name: carrier
                    })
                }).then(r => r.json());
            });

            Promise.all(requests)
                .then(results => {
                    const successful = results.filter(r => r.success).length;
                    const failed = results.length - successful;

                    if (failed === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            html: `Added tracking to all ${successful} orders`,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Partially Complete',
                            html: `Successfully updated: ${successful}<br>Failed: ${failed}`,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    }
                })
                .catch(error => {
                    console.error('Bulk tracking error:', error);
                    Swal.fire('Error', 'Failed to process bulk tracking', 'error');
                });
        }
    });
}

// =============================================================================
// BULK DOWNLOAD PACKING SLIPS
// =============================================================================

function bulkDownloadPackingSlips() {
    const selectedOrders = getSelectedOrders();

    if (selectedOrders.length === 0) {
        Swal.fire('No Orders Selected', 'Please select at least one order', 'warning');
        return;
    }

    if (selectedOrders.length === 1) {
        // Single order - open PDF directly
        window.open(`/supplier/api/export-order-pdf.php?id=${selectedOrders[0]}`, '_blank');
    } else {
        // Multiple orders - download as ZIP
        Swal.fire({
            title: 'Preparing Download',
            html: `Creating ZIP file with ${selectedOrders.length} packing slips...`,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Create ZIP download
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/supplier/api/bulk-download-pdfs.php';
        form.target = '_blank';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order_ids';
        input.value = JSON.stringify(selectedOrders);

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        setTimeout(() => {
            Swal.close();
            showToast(`Downloading ${selectedOrders.length} packing slips as ZIP`, 'success');
        }, 1000);
    }
}

// =============================================================================
// BULK MARK AS SHIPPED
// =============================================================================

function bulkMarkShipped() {
    const selectedOrders = getSelectedOrders();

    if (selectedOrders.length === 0) {
        Swal.fire('No Orders Selected', 'Please select at least one order', 'warning');
        return;
    }

    Swal.fire({
        title: 'Mark Orders as Shipped?',
        html: `This will mark <strong>${selectedOrders.length}</strong> order(s) as SENT/SHIPPED`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Mark as Shipped',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            const requests = selectedOrders.map(orderId => {
                return fetch('/supplier/api/update-order-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: orderId,
                        status: 'SENT'
                    })
                }).then(r => r.json());
            });

            Promise.all(requests)
                .then(results => {
                    const successful = results.filter(r => r.success).length;
                    const failed = results.length - successful;

                    if (failed === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            html: `Marked ${successful} order(s) as shipped`,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Partially Complete',
                            html: `Success: ${successful}<br>Failed: ${failed}`
                        }).then(() => location.reload());
                    }
                })
                .catch(error => {
                    console.error('Bulk mark shipped error:', error);
                    Swal.fire('Error', 'Failed to update orders', 'error');
                });
        }
    });
}

// =============================================================================
// BULK EXPORT CSV
// =============================================================================

function bulkExportCSV() {
    const selectedOrders = getSelectedOrders();

    if (selectedOrders.length === 0) {
        Swal.fire('No Orders Selected', 'Please select at least one order', 'warning');
        return;
    }

    showToast(`Exporting ${selectedOrders.length} order(s) to CSV...`, 'info');

    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/supplier/api/bulk-export-csv.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'order_ids';
    input.value = JSON.stringify(selectedOrders);

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// =============================================================================
// BULK DOWNLOAD AS ZIP (Combined function - replaces CSV/ZIP buttons)
// =============================================================================

function bulkDownloadZip() {
    const selectedOrders = getSelectedOrders();

    if (selectedOrders.length === 0) {
        Swal.fire('No Orders Selected', 'Please select at least one order', 'warning');
        return;
    }

    // Post to bulk-export-csv.php (handles single CSV or ZIP automatically)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/supplier/api/bulk-export-csv.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'order_ids';
    input.value = JSON.stringify(selectedOrders);

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// =============================================================================
// HELPER: GET SELECTED ORDER IDS
// =============================================================================

function getSelectedOrders() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

// =============================================================================
// HELPER: SHOW TOAST MESSAGE
// =============================================================================

function showToast(message, type = 'info') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

// =============================================================================
// TOGGLE ALL CHECKBOXES
// =============================================================================

function toggleAllOrders(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkActionButtons();
}

// =============================================================================
// UPDATE BULK ACTION BUTTON STATES
// =============================================================================

/**
 * Enable/disable bulk action buttons based on checkbox selection
 * Adds dimmed appearance when disabled
 */
function updateBulkActionButtons() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const bulkButtons = document.querySelectorAll('.bulk-action-btn');

    if (checkboxes.length > 0) {
        // Enable buttons
        bulkButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
    } else {
        // Disable buttons
        bulkButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.4';
            btn.style.cursor = 'not-allowed';
        });
    }
}

// =============================================================================
// INITIALIZE ON PAGE LOAD
// =============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Initial state - disable bulk buttons
    updateBulkActionButtons();

    // Add change listeners to all checkboxes
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkActionButtons);
    });

    // Add listener to "select all" checkbox
    const selectAllCheckbox = document.getElementById('selectAllOrdersHeader');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', updateBulkActionButtons);
    }
});
