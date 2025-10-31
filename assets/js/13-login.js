/**
 * 13-login.js - Login Page Interactions
 * The Vape Shed Supplier Portal
 * 
 * Handles login page interactions including:
 * - Form validation
 * - Loading states
 * - Email validation
 * - Keyboard shortcuts
 * - Accessibility features
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const loginForm = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');

        if (!loginForm || !emailInput || !submitBtn) {
            return; // Not on login page
        }

        // Auto-focus email field
        emailInput.focus();

        // Real-time email validation
        emailInput.addEventListener('input', function() {
            validateEmail(emailInput);
        });

        emailInput.addEventListener('blur', function() {
            if (emailInput.value.trim()) {
                validateEmail(emailInput);
            }
        });

        // Form submission handling
        loginForm.addEventListener('submit', function(e) {
            // Validate before submitting
            if (!validateEmail(emailInput)) {
                e.preventDefault();
                emailInput.focus();
                return false;
            }

            // Show loading state
            setLoadingState(submitBtn, true);
            
            // Disable multiple submissions
            submitBtn.disabled = true;
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key submits form (default behavior, but ensure focus is correct)
            if (e.key === 'Enter' && document.activeElement === emailInput) {
                if (emailInput.value.trim()) {
                    // Use submit button click for better compatibility
                    const submitBtn = loginForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.click();
                    }
                }
            }

            // Escape key clears the form
            if (e.key === 'Escape') {
                emailInput.value = '';
                emailInput.classList.remove('is-valid', 'is-invalid');
                emailInput.focus();
            }
        });

        // Paste handling - trim whitespace
        emailInput.addEventListener('paste', function(e) {
            setTimeout(function() {
                emailInput.value = emailInput.value.trim();
                validateEmail(emailInput);
            }, 10);
        });

        // Add ARIA live region for screen readers
        if (!document.getElementById('login-announcer')) {
            const announcer = document.createElement('div');
            announcer.id = 'login-announcer';
            announcer.setAttribute('role', 'status');
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            announcer.className = 'visually-hidden';
            document.body.appendChild(announcer);
        }
    }

    /**
     * Validate email address
     */
    function validateEmail(input) {
        const email = input.value.trim();
        const announcer = document.getElementById('login-announcer');
        
        // Remove previous validation states
        input.classList.remove('is-valid', 'is-invalid');
        
        // Remove existing feedback messages
        const existingFeedback = input.parentElement.querySelector('.invalid-feedback, .valid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        if (!email) {
            return false;
        }

        // Email validation regex
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(email);

        if (isValid) {
            input.classList.add('is-valid');
            
            // Add success feedback
            const feedback = document.createElement('div');
            feedback.className = 'valid-feedback';
            feedback.textContent = 'Email format looks good';
            input.parentElement.appendChild(feedback);
            
            // Announce to screen readers
            if (announcer) {
                announcer.textContent = 'Email address is valid';
            }
            
            return true;
        } else {
            input.classList.add('is-invalid');
            
            // Add error feedback
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'Please enter a valid email address';
            input.parentElement.appendChild(feedback);
            
            // Announce to screen readers
            if (announcer) {
                announcer.textContent = 'Email address is invalid';
            }
            
            return false;
        }
    }

    /**
     * Set button loading state
     */
    function setLoadingState(button, isLoading) {
        if (isLoading) {
            button.classList.add('loading');
            button.disabled = true;
            
            // Update button text for screen readers
            const btnText = button.querySelector('.btn-text');
            if (btnText) {
                btnText.setAttribute('aria-hidden', 'true');
            }
            
            // Make loading spinner visible to screen readers
            const spinner = button.querySelector('.loading-spinner');
            if (spinner) {
                spinner.setAttribute('aria-label', 'Sending access link, please wait');
                spinner.removeAttribute('aria-hidden');
            }
        } else {
            button.classList.remove('loading');
            button.disabled = false;
            
            const btnText = button.querySelector('.btn-text');
            if (btnText) {
                btnText.removeAttribute('aria-hidden');
            }
            
            const spinner = button.querySelector('.loading-spinner');
            if (spinner) {
                spinner.setAttribute('aria-hidden', 'true');
                spinner.removeAttribute('aria-label');
            }
        }
    }

    /**
     * Show toast notification (if toast.js is available)
     */
    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

})();
