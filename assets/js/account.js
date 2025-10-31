/**
 * Account Management JavaScript
 * Supplier Portal - Account Page
 *
 * Handles:
 * - Company information editing
 * - NZ bank account management with comprehensive validation
 * - International bank account management with SWIFT/IBAN validation
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

    fetch('/supplier/api/update-profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Company information updated successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save changes');
    });
}

// ============================================================================
// NZ BANK ACCOUNT
// ============================================================================

function toggleEditNZBank() {
    document.getElementById('viewModeNZBank').style.display = 'none';
    document.getElementById('editModeNZBank').style.display = 'block';
    console.log('NZ Bank edit mode enabled');
}

function cancelEditNZBank() {
    document.getElementById('editModeNZBank').style.display = 'none';
    document.getElementById('viewModeNZBank').style.display = 'block';
    console.log('NZ Bank edit mode cancelled');
}

function saveNZBank(event) {
    event.preventDefault();

    const accountNumber = document.getElementById('nz_account_number').value;

    // Validate NZ account format
    if (!validateNZAccountNumber(accountNumber)) {
        alert('Invalid NZ bank account format. Please use XX-XXXX-XXXXXXX-XX or XX-XXXX-XXXXXXX-XXX');
        return;
    }

    const formData = {
        bank_name: document.getElementById('nz_bank_name').value,
        account_number: accountNumber,
        account_holder: document.getElementById('nz_account_holder').value
    };

    console.log('Saving NZ bank account:', formData);

    fetch('/supplier/api/update-nz-bank.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('display_nz_bank_name').textContent = formData.bank_name;
            document.getElementById('display_nz_account_number').textContent = formData.account_number;
            document.getElementById('display_nz_account_holder').textContent = formData.account_holder;
            cancelEditNZBank();
            alert('NZ bank account updated successfully');
        } else {
            alert('Error: ' + (data.error || 'Failed to update'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save bank details');
    });
}

// ============================================================================
// INTERNATIONAL BANK ACCOUNT
// ============================================================================

function toggleEditIntlBank() {
    document.getElementById('viewModeIntlBank').style.display = 'none';
    document.getElementById('editModeIntlBank').style.display = 'block';
    console.log('International Bank edit mode enabled');
}

function cancelEditIntlBank() {
    document.getElementById('editModeIntlBank').style.display = 'none';
    document.getElementById('viewModeIntlBank').style.display = 'block';
    console.log('International Bank edit mode cancelled');
}

function saveIntlBank(event) {
    event.preventDefault();

    const swiftCode = document.getElementById('intl_swift').value.toUpperCase();
    const iban = document.getElementById('intl_iban').value.toUpperCase();

    if (!validateSWIFTCode(swiftCode)) {
        alert('Invalid SWIFT/BIC code. Must be 8 or 11 characters (e.g., ABCDUS33XXX).');
        return;
    }

    if (iban && !validateIBAN(iban)) {
        alert('Invalid IBAN format. Please check the country code and account number.');
        return;
    }

    const formData = {
        bank_name: document.getElementById('intl_bank_name').value,
        swift_code: swiftCode,
        iban: iban,
        account_number: document.getElementById('intl_account').value,
        country: document.getElementById('intl_country').value,
        bank_address: document.getElementById('intl_address').value
    };

    console.log('Saving international bank account:', formData);

    fetch('/supplier/api/update-intl-bank.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('display_intl_bank_name').textContent = formData.bank_name;
            document.getElementById('display_intl_swift').textContent = formData.swift_code;
            document.getElementById('display_intl_iban').textContent = formData.iban || 'N/A';
            document.getElementById('display_intl_account').textContent = formData.account_number;
            document.getElementById('display_intl_country').textContent = getCountryName(formData.country);
            document.getElementById('display_intl_address').textContent = formData.bank_address || 'Not provided';
            cancelEditIntlBank();
            alert('International bank account updated successfully');
        } else {
            alert('Error: ' + (data.error || 'Failed to update'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save bank details');
    });
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

function validateNZAccountNumber(accountNumber) {
    const pattern = /^[0-9]{2}-[0-9]{4}-[0-9]{7}-[0-9]{2,3}$/;

    if (!pattern.test(accountNumber)) {
        console.error('NZ account number format invalid:', accountNumber);
        return false;
    }

    const parts = accountNumber.split('-');
    const bank = parts[0];

    const validBankCodes = {
        '01': 'ANZ', '02': 'BNZ', '03': 'Westpac', '06': 'ANZ',
        '08': 'ANZ', '09': 'ANZ', '11': 'BNZ', '12': 'ASB',
        '15': 'TSB', '20': 'HSBC', '21': 'HSBC', '25': 'Rabobank',
        '26': 'SBS Bank', '27': 'SBS Bank', '28': 'Bank of Baroda',
        '29': 'Bank of India', '30': 'The Co-operative Bank',
        '31': 'Kiwibank', '33': 'Kiwibank', '35': 'The Co-operative Bank',
        '38': 'Kiwibank'
    };

    if (!validBankCodes[bank]) {
        console.error('Invalid NZ bank code:', bank);
        return false;
    }

    console.log('✅ Valid NZ bank account for:', validBankCodes[bank]);
    return true;
}

function validateSWIFTCode(code) {
    const pattern = /^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/;
    const isValid = pattern.test(code);

    if (isValid) {
        console.log('✅ Valid SWIFT code:', code);
    } else {
        console.error('Invalid SWIFT code:', code);
    }

    return isValid;
}

function validateIBAN(iban) {
    const pattern = /^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/;

    if (!pattern.test(iban)) {
        console.error('IBAN format invalid:', iban);
        return false;
    }

    const rearranged = iban.substring(4) + iban.substring(0, 4);

    let numericString = '';
    for (let char of rearranged) {
        if (char >= 'A' && char <= 'Z') {
            numericString += (char.charCodeAt(0) - 55).toString();
        } else {
            numericString += char;
        }
    }

    let remainder = 0;
    for (let i = 0; i < numericString.length; i++) {
        remainder = (remainder * 10 + parseInt(numericString[i])) % 97;
    }

    const isValid = remainder === 1;

    if (isValid) {
        console.log('✅ Valid IBAN:', iban);
    } else {
        console.error('IBAN checksum failed:', iban);
    }

    return isValid;
}

function getCountryName(code) {
    const countries = {
        'AU': 'Australia', 'US': 'United States', 'GB': 'United Kingdom',
        'CA': 'Canada', 'CN': 'China', 'JP': 'Japan', 'DE': 'Germany',
        'FR': 'France', 'SG': 'Singapore', 'HK': 'Hong Kong', 'OTHER': 'Other'
    };
    return countries[code] || code;
}

// ============================================================================
// EVENT LISTENERS
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Account.js loaded - Banking features enabled');

    const nzAccountInput = document.getElementById('nz_account_number');
    if (nzAccountInput) {
        nzAccountInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            let formatted = '';

            if (value.length > 0) formatted = value.substring(0, 2);
            if (value.length > 2) formatted += '-' + value.substring(2, 6);
            if (value.length > 6) formatted += '-' + value.substring(6, 13);
            if (value.length > 13) formatted += '-' + value.substring(13, 16);

            e.target.value = formatted;
        });
    }

    const swiftInput = document.getElementById('intl_swift');
    if (swiftInput) {
        swiftInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }

    const ibanInput = document.getElementById('intl_iban');
    if (ibanInput) {
        ibanInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase().replace(/\s/g, '');
        });
    }
});
