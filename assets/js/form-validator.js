/**
 * Form Validation Module
 */
const FormValidator = {
    validateRequired(form) {
        const fields = form.querySelectorAll('[required]');
        let valid = true;

        fields.forEach(field => {
            this.clearError(field);
            if (!field.value.trim()) {
                this.showError(field, 'This field is required.');
                valid = false;
            }
        });

        return valid;
    },

    validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    validatePhone(phone) {
        return /^[0-9+\-\s()]{7,20}$/.test(phone);
    },

    validatePassword(password) {
        return password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password);
    },

    showError(field, message) {
        field.classList.add('field-error');
        field.style.borderColor = '#dc2626';

        let errorEl = field.parentElement.querySelector('.field-error-msg');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'field-error-msg';
            errorEl.style.cssText = 'color:#dc2626;font-size:0.75rem;display:block;margin-top:0.25rem';
            field.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
    },

    clearError(field) {
        field.classList.remove('field-error');
        field.style.borderColor = '';
        const errorEl = field.parentElement.querySelector('.field-error-msg');
        if (errorEl) errorEl.remove();
    }
};
