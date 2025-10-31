/**
 * Simple Add Tracking Modal
 *
 * Shows modal with:
 * - Single line text input with Add button
 * - Carrier dropdown
 * - Parcel counter (readonly)
 * - List of added tracking numbers with delete buttons
 *
 * Each tracking number = 1 box automatically
 */

function showAddTrackingModal(orderId) {
    let trackingList = [];

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
