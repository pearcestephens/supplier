/**
 * Confirmation Dialog System
 * Beautiful confirmation modals for destructive actions
 * Uses SweetAlert2 (include in html-head.php: https://cdn.jsdelivr.net/npm/sweetalert2@11)
 *
 * Usage:
 * confirmAction('Delete this order?', 'This cannot be undone', () => {
 *     // User confirmed
 *     deleteOrder(id);
 * });
 */

// Standard confirmation dialog
function confirmAction(title, message, onConfirm, onCancel = null) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        } else if (onCancel) {
            onCancel();
        }
    });
}

// Delete confirmation (more specific)
function confirmDelete(itemName, onConfirm) {
    Swal.fire({
        title: 'Delete ' + itemName + '?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete it',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        }
    });
}

// Approval confirmation
function confirmApproval(itemName, onConfirm) {
    Swal.fire({
        title: 'Approve ' + itemName + '?',
        text: 'This will mark the item as approved',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, approve',
        cancelButtonText: 'Cancel',
        focusCancel: false,
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        }
    });
}

// Rejection confirmation
function confirmReject(itemName, onConfirm, requireReason = true) {
    if (requireReason) {
        Swal.fire({
            title: 'Reject ' + itemName + '?',
            input: 'textarea',
            inputLabel: 'Reason for rejection (required)',
            inputPlaceholder: 'Enter reason...',
            inputAttributes: {
                'aria-label': 'Enter rejection reason'
            },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-times me-2"></i>Yes, reject',
            cancelButtonText: 'Cancel',
            focusCancel: false,
            reverseButtons: true,
            inputValidator: (value) => {
                if (!value || value.trim().length < 10) {
                    return 'Please provide a reason (minimum 10 characters)';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                onConfirm(result.value);
            }
        });
    } else {
        Swal.fire({
            title: 'Reject ' + itemName + '?',
            text: 'This action cannot be undone',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-times me-2"></i>Yes, reject',
            cancelButtonText: 'Cancel',
            focusCancel: false,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
    }
}

// Custom input dialog
function promptInput(title, inputType = 'text', placeholder = '', onConfirm) {
    Swal.fire({
        title: title,
        input: inputType,
        inputPlaceholder: placeholder,
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Submit',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (!value || value.trim().length === 0) {
                return 'This field is required';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm(result.value);
        }
    });
}

// Success message
function showSuccessMessage(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonColor: '#198754',
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
    });
}

// Error message
function showErrorMessage(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'OK'
    });
}

// Info message
function showInfoMessage(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'info',
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Got it'
    });
}

// Example usage with promise chain:
/*
function deleteOrder(id) {
    confirmDelete('this order', () => {
        const loadingToast = showLoadingToast('Deleting order...');

        fetch(`/supplier/api/delete-order.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(r => r.json())
        .then(data => {
            loadingToast.hide();

            if (data.success) {
                showSuccessMessage('Deleted', 'Order has been deleted');
                // Refresh table
                location.reload();
            } else {
                showErrorMessage('Error', data.error || 'Failed to delete order');
            }
        })
        .catch(error => {
            loadingToast.hide();
            showErrorMessage('Error', 'Network error occurred');
        });
    });
}
*/
