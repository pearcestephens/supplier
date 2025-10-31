# Email Management System - Quick Reference

## User Flows

### Add Email Flow
```
User clicks "Add Email" 
  → Modal opens with email input
  → User enters email
  → API: email-add.php
    → Validates format
    → Checks rate limit (5/hour)
    → Checks for duplicates
    → Generates 64-char token
    → Inserts to database
    → Sends verification email
  → Modal closes
  → Email list refreshes
  → Email appears with "Unverified" badge
```

### Verify Email Flow
```
User receives email
  → Clicks verification link
  → API: verify-email.php?token=XXX
    → Validates token exists
    → Checks not expired (24 hours)
    → Updates verified=1
    → Clears token
    → Logs action
  → Redirects to account.php?msg=email_verified
  → Shows success toast
  → Email list refreshes
  → "Verified" badge appears
  → "Set Primary" button available
```

### Set Primary Flow
```
User clicks "Set Primary" on verified email
  → Confirmation dialog
  → API: email-set-primary.php
    → Validates email is verified
    → Starts transaction
      → Clears is_primary on all emails
      → Sets is_primary=1 on selected
      → Updates vend_suppliers.email
    → Commits transaction
    → Sends notifications to both emails
    → Logs action
  → Page reloads
  → "Primary" badge moves to new email
```

### Remove Email Flow
```
User clicks "Remove" on non-primary email
  → Confirmation dialog
  → API: email-remove.php
    → Validates not primary
    → Validates ownership
    → Deletes from database
    → Logs action
  → Email list refreshes
  → Email removed from display
```

## Database Schema

### supplier_email_addresses
```sql
id                          INT PRIMARY KEY AUTO_INCREMENT
supplier_id                 VARCHAR(100) FK → vend_suppliers.id
email                       VARCHAR(255)
is_primary                  TINYINT(1) DEFAULT 0
verified                    TINYINT(1) DEFAULT 0
verification_token          VARCHAR(64) NULL
verification_token_expires  TIMESTAMP NULL
created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

UNIQUE KEY: (supplier_id, email)
INDEX: supplier_id, email, verification_token, is_primary, verified
```

## Security Measures

| Feature | Implementation |
|---------|----------------|
| Rate Limiting | 5 adds/resends per hour via supplier_email_rate_limit table |
| Token Security | 64-char hex (bin2hex(random_bytes(32))) |
| Token Expiry | 24 hours from generation |
| Email Validation | filter_var(FILTER_VALIDATE_EMAIL) |
| Ownership Check | supplier_id verification on all operations |
| Primary Protection | Cannot remove primary email |
| Verification Required | Must verify before setting as primary |
| Audit Trail | All actions logged in supplier_email_verification_log |
| XSS Protection | HTML escaping + data attributes |
| SQL Injection | Prepared statements throughout |
| CSRF Protection | Inherited from Auth system |

## UI Components

### Email List Item (Unverified)
```
[email@example.com] [Unverified Badge]
  [Resend Button] [Remove Button]
```

### Email List Item (Verified, Non-Primary)
```
[email@example.com] [Verified Badge]
  [Set Primary Button] [Remove Button]
```

### Email List Item (Primary)
```
[email@example.com] [Primary Badge] [Verified Badge]
  (no action buttons)
```

## Configuration

### config.php Changes
```php
define('SITE_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'vapeshed.co.nz'));
```
Used for generating verification links securely.

### Email Constants (from config.php)
```php
define('NOTIFICATION_EMAIL_FROM', 'noreply@vapeshed.co.nz');
define('NOTIFICATION_EMAIL_NAME', 'The Vape Shed Supplier Portal');
```

## Error Messages

| Scenario | Message |
|----------|---------|
| Empty email | "Email address is required" |
| Invalid format | "Invalid email address format" |
| Duplicate | "This email address is already added to your account" |
| Rate limit | "Rate limit exceeded. You can only add 5 email addresses per hour." |
| Already verified | "This email address is already verified" |
| Not verified | "Email must be verified before setting as primary" |
| Remove primary | "Cannot remove primary email. Please set another email as primary first." |
| Invalid token | "Invalid verification token" |
| Expired token | "Verification token has expired. Please request a new verification email." |

## Toast Notifications

| Action | Type | Message |
|--------|------|---------|
| Email added | Success | "Email added! Please check your inbox to verify." |
| Email verified | Success | "Email address verified successfully!" |
| Primary changed | Success | "Primary email updated" |
| Email removed | Success | "Email address removed" |
| Verification resent | Success | "Verification email sent! Please check your inbox." |
| Any error | Error | [Specific error message] |

## File Structure

```
/supplier/
├── migrations/
│   └── 008_supplier_email_addresses.sql
├── includes/
│   └── email-templates.php
├── api/
│   ├── email-list.php
│   ├── email-add.php
│   ├── email-remove.php
│   ├── email-set-primary.php
│   ├── verify-email.php
│   └── email-resend-verification.php
├── assets/js/
│   └── 16-email-management.js
├── account.php (modified)
├── config.php (modified)
└── EMAIL_MANAGEMENT_TESTING.md
```

## Integration Points

### Current Implementation
- Updates `vend_suppliers.email` when primary changed
- Uses existing Auth system for supplier_id
- Inherits CSRF protection from requireAuth()
- Uses existing toast notification pattern

### Future Enhancement Opportunities
- Send reports to all verified emails (configurable)
- BCC all verified emails on notifications
- Allow per-email notification preferences
- Email confirmation before primary change
- 2FA via email
- Login notification to all emails
- MX record validation for email domains
- Email deliverability scoring

## Monitoring & Maintenance

### Key Metrics to Track
- Number of emails per supplier (avg, max)
- Verification rate (% verified vs total)
- Time to verification (avg)
- Rate limit hits per day
- Failed email sends
- Expired tokens not used
- Primary email changes per month

### Maintenance Tasks
- Clean up expired tokens (>24 hours old)
- Clean up rate limit records (>1 hour old)
- Archive verification log (>90 days)
- Monitor email deliverability
- Review failed verification attempts

### SQL Maintenance Queries
```sql
-- Clean expired tokens
UPDATE supplier_email_addresses 
SET verification_token = NULL, verification_token_expires = NULL
WHERE verification_token_expires < NOW();

-- Clean old rate limits
DELETE FROM supplier_email_rate_limit 
WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Archive old logs
INSERT INTO supplier_email_verification_log_archive 
SELECT * FROM supplier_email_verification_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM supplier_email_verification_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Performance Considerations

- Indexes on `supplier_id`, `email`, `verification_token`
- Unique constraint prevents duplicate checks
- Rate limiting table keeps only last hour
- Verification log can grow - consider archiving
- Email sending is synchronous (consider queue for scale)

## Browser Compatibility

- Requires Bootstrap 5 for modal and toast
- Requires modern JavaScript (ES6+)
- Uses Fetch API (IE11 not supported)
- Graceful degradation for older browsers
- Mobile responsive design
