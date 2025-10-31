/**
 * Account Management JavaScript
 * Supplier Portal - Account Page
 * 
 * Handles:
 * - Company information editing
 * - NZ bank account management with validation
 * - International bank account management with validation
 */

// ============================================================================
// COMPANY INFORMATION
// ============================================================================

function toggleEditCompany() {
    document.getElementById('viewModeCompany').style.display = 'none';
    document.getElementById('editModeCompany').style.display = 'block';
    console.log('Company edit mode enabled');
}

function cancelEditCompany() {
    document.getElementById('editModeCompany').style.display = 'none';
    document.getElementById('viewModeCompany').style.display = 'block';
    console.log('Company edit mode cancelled');
}

function saveCompany(event) {
    event.preventDefault();

    const formData = {
        name: document.getElementById('edit_name').value,
        email: document.getElementById('edit_email').value,
        phone: document.getElementById('edit_phone').value,
        website: document.getElementById('edit_website').value
    };

    console.log('Saving company info:', formData);

    // Send to API
    fetch('/supplier/api/update-profile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update view mode with new data
            document.querySelectorAll('#viewMode .col-md-9').forEach((el, index) => {
                if (index === 0) el.textContent = formData.name;
                if (index === 1) el.innerHTML = formData.email + ' <span class="badge bg-success ms-2"><i class="fa-solid fa-check"></i> Verified</span>';
                if (index === 2) el.textContent = formData.phone || 'Not provided';
                if (index === 3) {
                    if (formData.website) {
                        el.innerHTML = `<a href="${formData.website}" target="_blank" rel="noopener">${formData.website} <i class="fa-solid fa-external-link-alt fa-xs"></i></a>`;
                    } else {
                        el.textContent = 'Not provided';
                    }
                }
            });

            // Show success message
            showToast('Profile updated successfully!', 'success');

            // Switch back to view mode
            cancelEdit();

            console.log('✅ Profile saved successfully');
        } else {
            showToast(data.message || 'Failed to update profile', 'danger');
            console.error('Profile save failed:', data.message);
        }
    })
    .catch(error => {
        console.error('Profile update error:', error);
        showToast('An error occurred while updating your profile', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Simple toast notification function
function showToast(message, type = 'info') {
    // Simple alert for now - can be enhanced with Bootstrap toasts
    if (type === 'success') {
        alert('✅ ' + message);
    } else if (type === 'danger') {
        alert('❌ ' + message);
    } else {
        alert(message);
    }
}

console.log('✅ Account.js loaded');
