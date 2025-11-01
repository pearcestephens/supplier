/**
 * Email Management JavaScript
 * Supplier Portal - Account Page - Email Management
 * 
 * Handles:
 * - Loading and displaying email addresses
 * - Adding new email addresses
 * - Removing email addresses
 * - Setting primary email
 * - Resending verification emails
 */

// ============================================================================
// EMAIL LIST MANAGEMENT
// ============================================================================

/**
 * Load all email addresses for the current supplier
 */
function loadEmails() {
    fetch('/supplier/api/email-list.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderEmailList(data.data.emails);
        } else {
            console.error('Failed to load emails:', data);
            showToast('Failed to load email addresses', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading emails:', error);
        showToast('Failed to load email addresses', 'error');
    });
}

/**
 * Render email list in the UI
 */
function renderEmailList(emails) {
    const container = document.getElementById('emailListContainer');
    if (!container) return;
    
    if (emails.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No email addresses configured.</div>';
        return;
    }
    
    let html = '<div class="list-group mb-3">';
    
    emails.forEach(email => {
        const isPrimary = email.is_primary;
        const isVerified = email.verified;
        const emailAddress = escapeHtml(email.email);
        const emailId = email.id;
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${emailAddress}</strong>
                    ${isPrimary ? '<span class="badge bg-primary ms-2">Primary</span>' : ''}
                    ${isVerified ? '<span class="badge bg-success ms-1">Verified</span>' : '<span class="badge bg-warning text-dark ms-1">Unverified</span>'}
                </div>
                <div class="btn-group">
                    ${!isPrimary && isVerified ? `<button class="btn btn-sm btn-info" onclick="setPrimary(${emailId})">Set Primary</button>` : ''}
                    ${!isVerified ? `<button class="btn btn-sm btn-secondary" onclick="resendVerification(${emailId})">Resend</button>` : ''}
                    ${!isPrimary ? `<button class="btn btn-sm btn-danger" data-email-id="${emailId}" data-email="${emailAddress}" onclick="removeEmailHandler(this)">Remove</button>` : ''}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// ============================================================================
// ADD EMAIL
// ============================================================================

/**
 * Show add email modal
 */
function showAddEmailModal() {
    const modal = new bootstrap.Modal(document.getElementById('addEmailModal'));
    document.getElementById('newEmailAddress').value = '';
    modal.show();
}

/**
 * Add new email address
 */
function addEmail(event) {
    event.preventDefault();
    
    const email = document.getElementById('newEmailAddress').value.trim();
    
    if (!email) {
        showToast('Please enter an email address', 'error');
        return;
    }
    
    // Basic email validation
    if (!isValidEmail(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
    
    fetch('/supplier/api/email-add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (data.success) {
            showToast('Email added! Please check your inbox to verify.', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('addEmailModal'));
            modal.hide();
            loadEmails();
        } else {
            showToast(data.error?.message || 'Failed to add email', 'error');
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error('Error adding email:', error);
        showToast('Failed to add email address', 'error');
    });
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ============================================================================
// REMOVE EMAIL
// ============================================================================

/**
 * Handle remove email button click with data attributes
 */
function removeEmailHandler(button) {
    const emailId = parseInt(button.getAttribute('data-email-id'));
    const emailAddress = button.getAttribute('data-email');
    removeEmail(emailId, emailAddress);
}

/**
 * Remove email address with confirmation
 */
function removeEmail(emailId, emailAddress) {
    if (!confirm(`Are you sure you want to remove ${emailAddress}?`)) {
        return;
    }
    
    fetch('/supplier/api/email-remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email_id: emailId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Email address removed', 'success');
            loadEmails();
        } else {
            showToast(data.error?.message || 'Failed to remove email', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing email:', error);
        showToast('Failed to remove email address', 'error');
    });
}

// ============================================================================
// SET PRIMARY EMAIL
// ============================================================================

/**
 * Set email as primary
 */
function setPrimary(emailId) {
    if (!confirm('Set this email as your primary email address?')) {
        return;
    }
    
    fetch('/supplier/api/email-set-primary.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email_id: emailId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Primary email updated', 'success');
            loadEmails();
            // Reload page to update email display in other areas
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error?.message || 'Failed to set primary email', 'error');
        }
    })
    .catch(error => {
        console.error('Error setting primary email:', error);
        showToast('Failed to set primary email', 'error');
    });
}

// ============================================================================
// RESEND VERIFICATION
// ============================================================================

/**
 * Resend verification email
 */
function resendVerification(emailId) {
    fetch('/supplier/api/email-resend-verification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email_id: emailId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Verification email sent! Please check your inbox.', 'success');
        } else {
            showToast(data.error?.message || 'Failed to send verification email', 'error');
        }
    })
    .catch(error => {
        console.error('Error resending verification:', error);
        showToast('Failed to send verification email', 'error');
    });
}

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Check if toast container exists, if not create it
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Show toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    // Remove from DOM after hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Load emails on page load
    if (document.getElementById('emailListContainer')) {
        loadEmails();
    }
    
    // Check for URL parameters (verification messages)
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    
    if (msg === 'email_verified') {
        showToast('Email address verified successfully!', 'success');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (msg === 'already_verified') {
        showToast('This email is already verified', 'info');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (msg === 'verification_error') {
        const error = urlParams.get('error');
        showToast(error || 'Verification failed', 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
