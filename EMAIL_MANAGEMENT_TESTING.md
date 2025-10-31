# Email Management System - Testing Guide

## Overview
This document provides instructions for testing the email management system implementation.

## Prerequisites
1. Database access to run migration
2. Supplier account with login credentials
3. Access to multiple email inboxes for testing verification

## Step 1: Run Database Migration

```bash
# Connect to MySQL database
mysql -h 127.0.0.1 -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj

# Run the migration
source /home/runner/work/supplier/supplier/migrations/008_supplier_email_addresses.sql;

# Verify tables were created
SHOW TABLES LIKE 'supplier_email%';

# Check that existing supplier emails were migrated
SELECT * FROM supplier_email_addresses LIMIT 5;
```

Expected output:
- 3 new tables created: `supplier_email_addresses`, `supplier_email_verification_log`, `supplier_email_rate_limit`
- Existing supplier emails are now in `supplier_email_addresses` with `is_primary=1` and `verified=1`

## Step 2: Access Account Page

1. Log in to the supplier portal
2. Navigate to `/supplier/account.php`
3. Verify the "Email Addresses" card is visible between "Company Information" and "NZ Bank Account"

Expected display:
- Card with header "Email Addresses"
- "Add Email" button in top-right
- List of current email(s) with badges:
  - Blue "Primary" badge
  - Green "Verified" badge

## Step 3: Add New Email Address

1. Click "Add Email" button
2. Modal should open with:
   - Info alert about verification
   - Email input field
   - "Add Email" submit button
3. Enter a valid email address you have access to
4. Click "Add Email"

Expected behavior:
- Success toast: "Email added! Please check your inbox to verify."
- Modal closes
- New email appears in list with:
  - Yellow "Unverified" badge
  - "Resend" button
  - "Remove" button
  - No "Set Primary" button (only shown after verification)

## Step 4: Email Verification

1. Check inbox of the email you just added
2. Find verification email with subject: "Verify your email address - The Vape Shed Supplier Portal"
3. Click the verification link (or copy and paste into browser)

Expected behavior:
- Redirects to `/supplier/account.php?msg=email_verified`
- Green toast: "Email address verified successfully!"
- Email list updates to show:
  - Green "Verified" badge
  - "Set Primary" button now available
  - "Resend" button removed

## Step 5: Set Primary Email

1. Click "Set Primary" button on a verified non-primary email
2. Confirm the action

Expected behavior:
- Success toast: "Primary email updated"
- Page reloads
- Blue "Primary" badge moves to the new primary email
- Old primary email loses "Primary" badge but keeps "Verified"
- Old primary email now shows action buttons (Set Primary, Remove)
- Both old and new primary emails receive notification

## Step 6: Resend Verification

1. Add another email (don't verify it)
2. Wait a moment
3. Click "Resend" button

Expected behavior:
- Success toast: "Verification email sent! Please check your inbox."
- New verification email received with fresh 24-hour token

## Step 7: Remove Email

1. Click "Remove" button on a non-primary email
2. Confirm deletion

Expected behavior:
- Confirmation dialog: "Are you sure you want to remove [email]?"
- Success toast: "Email address removed"
- Email disappears from list

## Step 8: Error Cases to Test

### Cannot Remove Primary Email
1. Try to remove the email with "Primary" badge
   - Expected: No "Remove" button is shown

### Cannot Set Unverified as Primary
1. Add new email (don't verify)
2. Try to set as primary
   - Expected: No "Set Primary" button shown until verified

### Rate Limiting
1. Add 6 emails in quick succession
   - Expected: 6th attempt shows error: "Rate limit exceeded. You can only add 5 email addresses per hour."

### Duplicate Email
1. Try to add an email that already exists
   - Expected: Error: "This email address is already added to your account"

### Invalid Email Format
1. Try to add "notanemail"
   - Expected: Browser validation error or API error: "Invalid email address format"

### Expired Verification Token
1. Add email and get verification link
2. Wait 25 hours (or manually update database to expire token)
3. Try to verify
   - Expected: Error message about expired token

## Step 9: Database Verification

```sql
-- Check emails for a supplier
SELECT * FROM supplier_email_addresses WHERE supplier_id = 'YOUR_SUPPLIER_ID';

-- Check verification log
SELECT * FROM supplier_email_verification_log WHERE supplier_id = 'YOUR_SUPPLIER_ID' ORDER BY created_at DESC;

-- Check rate limiting
SELECT * FROM supplier_email_rate_limit WHERE supplier_id = 'YOUR_SUPPLIER_ID';

-- Verify primary email matches main supplier record
SELECT 
    s.email as main_email,
    sea.email as primary_email,
    sea.is_primary
FROM vend_suppliers s
LEFT JOIN supplier_email_addresses sea ON s.id = sea.supplier_id AND sea.is_primary = 1
WHERE s.id = 'YOUR_SUPPLIER_ID';
```

## Security Checklist

- [x] Rate limiting prevents abuse (5 per hour)
- [x] Email validation prevents invalid formats
- [x] Verification required before setting as primary
- [x] Cannot remove primary email
- [x] Ownership verification on all operations
- [x] Verification tokens expire after 24 hours
- [x] All actions logged for audit trail
- [x] XSS protection in JavaScript
- [x] No host header injection vulnerability
- [x] Cryptographically secure random tokens

## API Endpoints Reference

### GET /supplier/api/email-list.php
Returns all emails for authenticated supplier
```json
{
  "success": true,
  "data": {
    "emails": [
      {
        "id": 1,
        "email": "primary@example.com",
        "is_primary": true,
        "verified": true,
        "created_at": "2025-10-31 12:00:00"
      }
    ],
    "count": 1
  }
}
```

### POST /supplier/api/email-add.php
```json
Request: {"email": "new@email.com"}
Response: {
  "success": true,
  "data": {
    "email_id": 2,
    "email": "new@email.com",
    "verification_sent": true
  },
  "message": "Email address added. Please check your inbox to verify."
}
```

### POST /supplier/api/email-remove.php
```json
Request: {"email_id": 2}
Response: {
  "success": true,
  "data": {
    "email_id": 2,
    "email": "removed@email.com"
  },
  "message": "Email address removed successfully"
}
```

### POST /supplier/api/email-set-primary.php
```json
Request: {"email_id": 2}
Response: {
  "success": true,
  "data": {
    "email": "new@primary.com",
    "old_primary": "old@primary.com"
  },
  "message": "Primary email updated successfully"
}
```

### GET /supplier/api/verify-email.php?token=XXX
Redirects to account page with message parameter

### POST /supplier/api/email-resend-verification.php
```json
Request: {"email_id": 2}
Response: {
  "success": true,
  "data": {
    "email": "unverified@email.com"
  },
  "message": "Verification email sent. Please check your inbox."
}
```

## Troubleshooting

### Emails Not Sending
- Check PHP `mail()` function is configured
- Verify SMTP settings if using external mail server
- Check spam folder
- Review error logs: `tail -f /home/runner/work/supplier/supplier/logs/php_errors.log`

### JavaScript Not Loading
- Clear browser cache
- Check browser console for errors
- Verify file path: `/supplier/assets/js/16-email-management.js`
- Check file has correct permissions

### Database Connection Issues
- Verify migration ran successfully
- Check database credentials in `config.php`
- Verify foreign key constraints are satisfied

## Success Criteria

✅ Can add multiple email addresses
✅ Verification emails sent and work correctly
✅ Can remove non-primary emails
✅ Can change primary email (verified only)
✅ Primary email syncs to `vend_suppliers.email`
✅ Proper security (CSRF, rate limiting, validation)
✅ Mobile responsive UI
✅ Toast notifications work
✅ No JavaScript or PHP errors
✅ CodeQL security scan passes
