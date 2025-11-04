/**
 * Simple Add Tracking Modal with Item Assignment
 *
 * Shows modal with:
 * - Carrier dropdown
 * - Add tracking number to create boxes
 * - Assign items to each box
 * - Parcel counter
 * - List of boxes with assigned items
 *
 * Each tracking number = 1 box
 */

function showAddTrackingModal(orderId) {
    let boxes = []; // Array of {tracking: string, items: [{id, name, qty, assigned}]}
    let orderItems = []; // Will be loaded from server

    // First, fetch order items
    Swal.fire({
        title: 'Loading Order Items...',
        html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`/supplier/api/get-order-items.php?id=${orderId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.items) {
                throw new Error('Failed to load order items');
            }
            orderItems = data.items.map(item => ({
                id: item.id,
                sku: item.sku,
                name: item.product_name,
                total_qty: item.quantity_sent || item.quantity,
                assigned: 0 // How many have been assigned to boxes
            }));

            // Show the main modal
            showTrackingModalUI();
        })
        .catch(error => {
            Swal.fire('Error', error.message, 'error');
        });

    function showTrackingModalUI() {
        function renderTrackingUI() {
            // Calculate totals
            const totalItems = orderItems.reduce((sum, item) => sum + item.total_qty, 0);
            const totalAssigned = orderItems.reduce((sum, item) => sum + item.assigned, 0);
            const unassigned = totalItems - totalAssigned;

            let html = `
                <div class="text-left">
                    <div class="alert alert-info mb-3">
                        <strong>Items to Pack:</strong> ${totalItems} units total<br>
                        <strong>Assigned:</strong> ${totalAssigned} | <strong>Unassigned:</strong> <span class="${unassigned > 0 ? 'text-danger' : 'text-success'} font-weight-bold">${unassigned}</span>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Carrier (applies to all boxes)</label>
                        <select id="tracking_carrier" class="form-control">
                            <option value="CourierPost">CourierPost</option>
                            <option value="Aramex">Aramex</option>
                            <option value="DHL">DHL</option>
                            <option value="NZ Post">NZ Post</option>
                            <option value="Fastway">Fastway</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Add Box/Parcel</label>
                        <div class="input-group">
                            <input
                                type="text"
                                id="tracking_input"
                                class="form-control"
                                placeholder="Enter tracking number"
                                onkeypress="if(event.key==='Enter'){event.preventDefault();document.getElementById('add_box_btn').click();}"
                                style="font-family: monospace;"
                            >
                            <button class="btn btn-success" type="button" id="add_box_btn">
                                <i class="fas fa-plus"></i> Add Box
                            </button>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Boxes (${boxes.length})</label>
            `;

            if (boxes.length === 0) {
                html += '<p class="text-muted small">No boxes added yet. Add tracking numbers above.</p>';
            } else {
                boxes.forEach((box, boxIndex) => {
                    const boxTotal = box.items.reduce((sum, item) => sum + item.qty, 0);
                    html += `
                        <div class="card mb-2">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <strong>Box #${boxIndex + 1}</strong>
                                <code>${box.tracking}</code>
                                <span class="badge bg-primary">${boxTotal} units</span>
                                <button class="btn btn-sm btn-danger" onclick="window.removeBox(${boxIndex})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                ${box.items.length === 0 ? '<p class="text-muted small mb-0">No items assigned to this box yet</p>' : ''}
                                <div class="list-group list-group-flush">
            `;

                    box.items.forEach((item, itemIndex) => {
                        html += `
                            <div class="list-group-item p-2 d-flex justify-content-between align-items-center">
                                <span class="small">${item.name} (${item.sku})</span>
                                <div>
                                    <span class="badge bg-secondary">${item.qty}</span>
                                    <button class="btn btn-sm btn-outline-danger" onclick="window.removeItemFromBox(${boxIndex}, ${itemIndex})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });

                    html += `
                                </div>
                            </div>
                            <div class="card-footer p-2">
                                <button class="btn btn-sm btn-outline-primary btn-block" onclick="window.showAddItemToBox(${boxIndex})">
                                    <i class="fas fa-plus"></i> Add Item to This Box
                                </button>
                            </div>
                        </div>
                    `;
                });
            }

            html += `
                    </div>

                    ${unassigned > 0 ? '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> You must assign all items to boxes before submitting!</div>' : ''}
                    ${unassigned === 0 && boxes.length > 0 ? '<div class="alert alert-success"><i class="fas fa-check"></i> All items assigned! Ready to submit.</div>' : ''}
                </div>
            `;

            return html;
        }

        // Global functions for box/item management
        window.removeBox = (boxIndex) => {
            // Return items to unassigned pool
            boxes[boxIndex].items.forEach(boxItem => {
                const orderItem = orderItems.find(i => i.id === boxItem.id);
                if (orderItem) {
                    orderItem.assigned -= boxItem.qty;
                }
            });
            boxes.splice(boxIndex, 1);
            Swal.update({ html: renderTrackingUI() });
            attachAddBoxListener();
        };

        window.removeItemFromBox = (boxIndex, itemIndex) => {
            const removedItem = boxes[boxIndex].items[itemIndex];
            const orderItem = orderItems.find(i => i.id === removedItem.id);
            if (orderItem) {
                orderItem.assigned -= removedItem.qty;
            }
            boxes[boxIndex].items.splice(itemIndex, 1);
            Swal.update({ html: renderTrackingUI() });
            attachAddBoxListener();
        };

        window.showAddItemToBox = (boxIndex) => {
            // Show available items
            const availableItems = orderItems.filter(item => item.assigned < item.total_qty);

            if (availableItems.length === 0) {
                Swal.fire('No Items', 'All items have been assigned to boxes', 'info');
                return;
            }

            let itemsHtml = availableItems.map(item => {
                const remaining = item.total_qty - item.assigned;
                return `
                    <div class="form-check mb-2">
                        <input class="form-check-input item-select" type="checkbox" value="${item.id}" id="item_${item.id}">
                        <label class="form-check-label" for="item_${item.id}">
                            <strong>${item.name}</strong> (${item.sku})<br>
                            <small class="text-muted">Available: ${remaining} of ${item.total_qty}</small>
                        </label>
                        <input type="number" class="form-control form-control-sm mt-1 item-qty" id="qty_${item.id}"
                               min="1" max="${remaining}" value="${remaining}" style="width: 80px;">
                    </div>
                `;
            }).join('');

            Swal.fire({
                title: `Add Items to Box #${boxIndex + 1}`,
                html: `
                    <div class="text-left">
                        <p class="mb-3">Select items to add to this box:</p>
                        ${itemsHtml}
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Add to Box',
                preConfirm: () => {
                    const selected = [];
                    document.querySelectorAll('.item-select:checked').forEach(checkbox => {
                        const itemId = parseInt(checkbox.value);
                        const qty = parseInt(document.getElementById(`qty_${itemId}`).value) || 0;
                        if (qty > 0) {
                            const item = orderItems.find(i => i.id === itemId);
                            selected.push({
                                id: itemId,
                                sku: item.sku,
                                name: item.name,
                                qty: qty
                            });
                        }
                    });
                    return selected;
                }
            }).then(result => {
                if (result.isConfirmed && result.value.length > 0) {
                    // Add items to box and update assigned counts
                    result.value.forEach(selectedItem => {
                        boxes[boxIndex].items.push(selectedItem);
                        const orderItem = orderItems.find(i => i.id === selectedItem.id);
                        if (orderItem) {
                            orderItem.assigned += selectedItem.qty;
                        }
                    });
                    Swal.update({ html: renderTrackingUI() });
                    attachAddBoxListener();
                    showTrackingModalUI(); // Re-render main modal
                }
            });
        };

        function attachAddBoxListener() {
            const addBtn = document.getElementById('add_box_btn');
            const input = document.getElementById('tracking_input');

            if (addBtn && input) {
                addBtn.onclick = () => {
                    const value = input.value.trim();
                    if (value) {
                        // Check for duplicates
                        if (boxes.some(b => b.tracking === value)) {
                            Swal.showValidationMessage('This tracking number has already been added');
                            return;
                        }
                        boxes.push({
                            tracking: value,
                            items: []
                        });
                        input.value = '';
                        Swal.update({ html: renderTrackingUI() });
                        attachAddBoxListener();
                        setTimeout(() => input.focus(), 100);
                    } else {
                        Swal.showValidationMessage('Please enter a tracking number');
                        setTimeout(() => Swal.resetValidationMessage(), 2000);
                    }
                };
            }
        }

        Swal.fire({
            title: 'Add Tracking & Pack Items',
            html: renderTrackingUI(),
            width: 700,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Submit All',
            confirmButtonColor: '#28a745',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                attachAddBoxListener();
                document.getElementById('tracking_input').focus();
            },
            preConfirm: () => {
                const carrier = document.getElementById('tracking_carrier').value;
                const totalItems = orderItems.reduce((sum, item) => sum + item.total_qty, 0);
                const totalAssigned = orderItems.reduce((sum, item) => sum + item.assigned, 0);

                if (boxes.length === 0) {
                    Swal.showValidationMessage('Please add at least one box');
                    return false;
                }

                if (totalAssigned < totalItems) {
                    Swal.showValidationMessage(`You must assign all ${totalItems} items to boxes (currently ${totalAssigned} assigned)`);
                    return false;
                }

                return { carrier, boxes };
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                const { carrier, boxes: shipmentBoxes } = result.value;

                Swal.fire({
                    title: 'Creating Shipment...',
                    html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                    showConfirmButton: false,
                    allowOutsideClick: false
                });

                // Submit to API
                fetch('/supplier/api/add-tracking-with-boxes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        carrier: carrier,
                        boxes: shipmentBoxes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Shipment Created!',
                            html: `
                                <p>${shipmentBoxes.length} box(es) created with tracking</p>
                                <p class="text-muted">Order marked as SENT</p>
                            `,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.error || 'Failed to create shipment', 'error');
                    }
                })
                .catch(error => {
                    console.error('Add tracking error:', error);
                    Swal.fire('Error', `Request failed: ${error.message}`, 'error');
                });
            }
        });
    }
}

    function renderTrackingUI() {
        let html = `
            <div class="text-left">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Carrier</label>
                    <select id="tracking_carrier" class="form-control">
                        <option value="CourierPost">CourierPost</option>
                        <option value="Aramex">Aramex</option>
                        <option value="DHL">DHL</option>
                        <option value="NZ Post">NZ Post</option>
                        <option value="Fastway">Fastway</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold">Add Tracking Number (1 per parcel)</label>
                    <div class="input-group">
                        <input
                            type="text"
                            id="tracking_input"
                            class="form-control"
                            placeholder="Enter tracking number"
                            onkeypress="if(event.key==='Enter'){event.preventDefault();document.getElementById('add_tracking_btn').click();}"
                            style="font-family: monospace;"
                        >
                        <button class="btn btn-success" type="button" id="add_tracking_btn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i>
                        Enter one tracking number and click Add. Repeat for each parcel.
                    </small>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold">Parcel Count (readonly)</label>
                    <input
                        type="text"
                        class="form-control"
                        id="parcel_counter"
                        value="${trackingList.length}"
                        readonly
                        style="background-color: #e9ecef; font-weight: bold; font-size: 1.1em; text-align: center;"
                    >
                </div>

                <div class="form-group mb-3" id="tracking_list_container">
                    <label class="font-weight-bold">Tracking Numbers</label>
                    ${trackingList.length === 0 ? '<p class="text-muted small">No tracking numbers added yet</p>' : ''}
                    <div class="list-group" style="max-height: 250px; overflow-y: auto;">
        `;

        trackingList.forEach((tracking, index) => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span style="font-family: monospace;"><strong>Parcel #${index + 1}</strong>: ${tracking}</span>
                    <button class="btn btn-sm btn-danger" onclick="window.removeTrackingNumber(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <small>
                        <strong>Note:</strong> Each tracking number = 1 parcel/box.
                        The system will create ${trackingList.length || 0} consignment box${trackingList.length === 1 ? '' : 'es'} when marked as sent.
                    </small>
                </div>
            </div>
        `;

        return html;
    }

    // Global function to remove a tracking number
    window.removeTrackingNumber = (index) => {
        trackingList.splice(index, 1);
        Swal.update({ html: renderTrackingUI() });
        attachAddButtonListener();
    };

    function attachAddButtonListener() {
        const addBtn = document.getElementById('add_tracking_btn');
        const input = document.getElementById('tracking_input');

        if (addBtn && input) {
            addBtn.onclick = () => {
                const value = input.value.trim();
                if (value) {
                    // Check for duplicates
                    if (trackingList.includes(value)) {
                        Swal.showValidationMessage('This tracking number has already been added');
                        return;
                    }
                    trackingList.push(value);
                    input.value = ''; // Clear input
                    Swal.update({ html: renderTrackingUI() });
                    attachAddButtonListener();
                    setTimeout(() => input.focus(), 100);
                } else {
                    Swal.showValidationMessage('Please enter a tracking number');
                    setTimeout(() => Swal.resetValidationMessage(), 2000);
                }
            };
        }
    }

    Swal.fire({
        title: 'Add Tracking Numbers',
        html: renderTrackingUI(),
        width: 600,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Submit All Tracking',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel',
        didOpen: () => {
            attachAddButtonListener();
            document.getElementById('tracking_input').focus();
        },
        preConfirm: () => {
            const carrier = document.getElementById('tracking_carrier').value;

            if (trackingList.length === 0) {
                Swal.showValidationMessage('Please add at least one tracking number');
                return false;
            }

            return { carrier, trackingNumbers: trackingList };
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            const { carrier, trackingNumbers } = result.value;

            Swal.fire({
                title: 'Submitting...',
                html: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // Submit to API
            fetch('/supplier/api/add-tracking-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    tracking_numbers: trackingNumbers,
                    carrier: carrier
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Tracking Added!',
                        html: `
                            <p>${data.message || 'Success'}</p>
                            <p class="text-muted mb-0">
                                Order has been marked as sent with ${trackingNumbers.length} parcel${trackingNumbers.length > 1 ? 's' : ''}.
                            </p>
                        `,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error || 'Failed to add tracking', 'error');
                }
            })
            .catch(error => {
                console.error('Add tracking error:', error);
                Swal.fire('Error', `Request failed: ${error.message}`, 'error');
            });
        }
    });
}

/**
 * Quick add single tracking number
 * For orders with just 1 box
 */
function quickAddTracking(orderId) {
    Swal.fire({
        title: 'Add Single Tracking Number',
        html: `
            <div class="text-left">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Tracking Number</label>
                    <input
                        type="text"
                        id="single_tracking"
                        class="form-control form-control-lg text-center"
                        placeholder="Enter tracking number"
                        style="font-family: monospace; font-size: 1.1em; letter-spacing: 1px;"
                    >
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold">Carrier</label>
                    <select id="single_carrier" class="form-control">
                        <option value="CourierPost">CourierPost</option>
                        <option value="Aramex">Aramex</option>
                        <option value="DHL">DHL</option>
                        <option value="NZ Post">NZ Post</option>
                        <option value="Fastway">Fastway</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="text-center">
                    <small class="text-muted">
                        This will create 1 box for this order.
                    </small>
                </div>
            </div>
        `,
        width: 450,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Add Tracking',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const tracking = document.getElementById('single_tracking').value.trim();
            const carrier = document.getElementById('single_carrier').value;

            if (!tracking) {
                Swal.showValidationMessage('Please enter a tracking number');
                return false;
            }

            // Submit to API
            return fetch('/supplier/api/add-tracking-simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    tracking_numbers: [tracking],
                    carrier: carrier
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Failed to add tracking');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Success!
            Swal.fire({
                icon: 'success',
                title: 'Tracking Added!',
                text: 'Order has been marked as sent.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Reload page to show new tracking
                location.reload();
            });
        }
    });
}

/**
 * Button to open modal with choice
 */
function addTrackingWithOptions(orderId) {
    Swal.fire({
        title: 'How many boxes?',
        html: `
            <div class="text-center">
                <p class="mb-4">Choose how to add tracking numbers:</p>
                <button onclick="quickAddTracking(${orderId}); Swal.close();" class="btn btn-lg btn-primary btn-block mb-2">
                    <i class="fas fa-box"></i> Single Box
                    <br><small>Enter one tracking number</small>
                </button>
                <button onclick="showAddTrackingModal(${orderId}); Swal.close();" class="btn btn-lg btn-info btn-block">
                    <i class="fas fa-boxes"></i> Multiple Boxes
                    <br><small>Enter multiple tracking numbers</small>
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        width: 400
    });
}
