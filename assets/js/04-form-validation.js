/**
 * Form Validation System
 * Real-time validation with visual feedback
 *
 * Usage:
 * <form data-validate="true">
 *     <input type="email" name="email" required data-validate-email>
 *     <input type="tel" name="phone" data-validate-phone>
 * </form>
 */

// Validation rules
const validationRules = {
    email: {
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        message: 'Please enter a valid email address'
    },
    phone: {
        pattern: /^[\d\s\-\+\(\)]{10,}$/,
        message: 'Please enter a valid phone number'
    },
    url: {
        pattern: /^https?:\/\/.+/,
        message: 'Please enter a valid URL (starting with http:// or https://)'
    },
    number: {
        pattern: /^\d+$/,
        message: 'Please enter numbers only'
    },
    alphanumeric: {
        pattern: /^[a-zA-Z0-9]+$/,
        message: 'Please use letters and numbers only'
    }
};

// Validate single field
function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name || 'This field';
    let isValid = true;
    let message = '';

    // Required check
    if (field.hasAttribute('required') && value === '') {
        isValid = false;
        message = `${fieldName} is required`;
    }

    // Min length
    if (isValid && field.hasAttribute('minlength')) {
        const minLength = parseInt(field.getAttribute('minlength'));
        if (value.length < minLength) {
            isValid = false;
            message = `${fieldName} must be at least ${minLength} characters`;
        }
    }

    // Max length
    if (isValid && field.hasAttribute('maxlength')) {
        const maxLength = parseInt(field.getAttribute('maxlength'));
        if (value.length > maxLength) {
            isValid = false;
            message = `${fieldName} must be less than ${maxLength} characters`;
        }
    }

    // Pattern validation
    for (const [ruleName, rule] of Object.entries(validationRules)) {
        if (field.hasAttribute(`data-validate-${ruleName}`) && value !== '') {
            if (!rule.pattern.test(value)) {
                isValid = false;
                message = rule.message;
                break;
            }
        }
    }

    // Custom validation
    if (field.hasAttribute('data-validate-custom')) {
        const customValidator = field.getAttribute('data-validate-custom');
        const validationResult = window[customValidator](value, field);
        if (validationResult !== true) {
            isValid = false;
            message = validationResult;
        }
    }

    return { isValid, message };
}

// Show validation state
function showValidationState(field, isValid, message = '') {
    const formGroup = field.closest('.mb-3, .form-group, .col');

    // Remove existing feedback
    const existingFeedback = formGroup?.querySelector('.invalid-feedback, .valid-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }

    // Remove validation classes
    field.classList.remove('is-valid', 'is-invalid');

    if (isValid) {
        field.classList.add('is-valid');

        // Add success icon
        const feedback = document.createElement('div');
        feedback.className = 'valid-feedback d-block';
        feedback.innerHTML = '<i class="fas fa-check-circle me-1"></i>Looks good';
        field.parentElement.appendChild(feedback);
    } else {
        field.classList.add('is-invalid');

        // Add error message
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
        field.parentElement.appendChild(feedback);
    }
}

// Validate entire form
function validateForm(form) {
    let isValid = true;
    const fields = form.querySelectorAll('input:not([type="hidden"]), textarea, select');

    fields.forEach(field => {
        const result = validateField(field);
        showValidationState(field, result.isValid, result.message);

        if (!result.isValid) {
            isValid = false;
        }
    });

    return isValid;
}

// Initialize validation on form
function initFormValidation(form) {
    const fields = form.querySelectorAll('input:not([type="hidden"]), textarea, select');

    fields.forEach(field => {
        // Validate on blur
        field.addEventListener('blur', function() {
            if (this.value.trim() !== '' || this.hasAttribute('required')) {
                const result = validateField(this);
                showValidationState(this, result.isValid, result.message);
            }
        });

        // Clear validation on focus
        field.addEventListener('focus', function() {
            this.classList.remove('is-valid', 'is-invalid');
            const feedback = this.parentElement.querySelector('.invalid-feedback, .valid-feedback');
            if (feedback) {
                feedback.remove();
            }
        });

        // Real-time validation (with debounce)
        let timeout;
        field.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (this.value.trim() !== '') {
                    const result = validateField(this);
                    showValidationState(this, result.isValid, result.message);
                }
            }, 500);
        });
    });

    // Form submit validation
    form.addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            e.stopPropagation();

            // Scroll to first error
            const firstError = this.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }

            showErrorToast('Please fix the errors in the form');
        }
    });
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[data-validate="true"]').forEach(form => {
        initFormValidation(form);
    });
});

// Custom validators (examples)
function validateTrackingNumber(value, field) {
    // Example: Tracking must be 10-20 alphanumeric characters
    if (value.length < 10 || value.length > 20) {
        return 'Tracking number must be 10-20 characters';
    }
    if (!/^[A-Z0-9]+$/.test(value)) {
        return 'Tracking number must contain only uppercase letters and numbers';
    }
    return true;
}

function validatePassword(value, field) {
    if (value.length < 8) {
        return 'Password must be at least 8 characters';
    }
    if (!/[A-Z]/.test(value)) {
        return 'Password must contain at least one uppercase letter';
    }
    if (!/[a-z]/.test(value)) {
        return 'Password must contain at least one lowercase letter';
    }
    if (!/[0-9]/.test(value)) {
        return 'Password must contain at least one number';
    }
    return true;
}

function validateConfirmPassword(value, field) {
    const passwordField = document.querySelector('[name="password"]');
    if (passwordField && value !== passwordField.value) {
        return 'Passwords do not match';
    }
    return true;
}
