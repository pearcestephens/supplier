/**
 * Simple Add Tracking Modal
 *
 * Shows modal with:
 * - Text input for tracking numbers (one per line)
 * - Carrier dropdown
 * - Submit button
 *
 * Each tracking number = 1 box automatically
 */

function showAddTrackingModal(orderId) {
    Swal.fire({
        title: 'Add Tracking Numbers',
        html: `
            <div class="text-left">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Tracking Numbers</label>
                    <textarea
                        id="tracking_numbers"
                        class="form-control"
                        rows="6"
                        placeholder="Enter one tracking number per line:&#10;ABC123456789&#10;XYZ987654321&#10;DEF456789012"
                        style="font-family: monospace; font-size: 0.9em;"
                    ></textarea>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i>
                        One tracking number = One box. Enter as many as you need.
                    </small>
                </div>

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

                <div class="alert alert-info mb-0">
                    <small>
                        <strong>Example:</strong> If you enter 3 tracking numbers,
                        the system will create 3 boxes for this order.
                    </small>
                </div>
            </div>
        `,
        width: 550,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Add Tracking',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const trackingText = document.getElementById('tracking_numbers').value.trim();
            const carrier = document.getElementById('tracking_carrier').value;

            if (!trackingText) {
                Swal.showValidationMessage('Please enter at least one tracking number');
                return false;
            }

            // Split by newlines and filter empty lines
            const trackingNumbers = trackingText
                .split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0);

            if (trackingNumbers.length === 0) {
                Swal.showValidationMessage('Please enter at least one tracking number');
                return false;
            }

            // Show confirmation
            const boxWord = trackingNumbers.length === 1 ? 'box' : 'boxes';
            const confirmMsg = `Add ${trackingNumbers.length} ${boxWord} with tracking numbers?`;

            return Swal.fire({
                title: 'Confirm',
                text: confirmMsg,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add them',
                cancelButtonText: 'Go back'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit to API
                    return fetch('/supplier/api/add-tracking-simple.php', {
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
                        if (!data.success) {
                            throw new Error(data.error || 'Failed to add tracking');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error.message}`);
                    });
                } else {
                    return false; // Go back to input
                }
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Success!
            Swal.fire({
                icon: 'success',
                title: 'Tracking Added!',
                html: `
                    <p>${result.value.message}</p>
                    <p class="text-muted mb-0">
                        Order has been marked as sent with ${result.value.data.total_boxes} box${result.value.data.total_boxes > 1 ? 'es' : ''}.
                    </p>
                `,
                confirmButtonText: 'OK'
            }).then(() => {
                // Reload page to show new tracking
                location.reload();
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
