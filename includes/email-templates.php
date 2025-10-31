<?php
/**
 * Email Templates for Supplier Portal
 * 
 * Provides email content templates for various notifications
 * 
 * @package SupplierPortal
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Get email verification template
 * 
 * @param string $supplierName Supplier company name
 * @param string $verificationLink Full verification URL
 * @param string $email Email being verified
 * @return array ['subject' => string, 'body' => string, 'html' => string]
 */
function getEmailVerificationTemplate(string $supplierName, string $verificationLink, string $email): array {
    $subject = "Verify your email address - The Vape Shed Supplier Portal";
    
    $textBody = <<<TEXT
Hi {$supplierName},

Please verify your email address by clicking the link below:

{$verificationLink}

This link expires in 24 hours.

If you didn't request this, please ignore this email.

---
The Vape Shed Supplier Portal
Support: suppliers@vapeshed.co.nz
TEXT;

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #000; color: #fbbf24; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .button { display: inline-block; padding: 12px 30px; background: #fbbf24; color: #000; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>The Vape Shed Supplier Portal</h1>
        </div>
        <div class="content">
            <h2>Verify Your Email Address</h2>
            <p>Hi <strong>{$supplierName}</strong>,</p>
            <p>You've added <strong>{$email}</strong> to your supplier account. Please verify this email address by clicking the button below:</p>
            <p style="text-align: center;">
                <a href="{$verificationLink}" class="button">Verify Email Address</a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: #fff; padding: 10px; border: 1px solid #ddd;">{$verificationLink}</p>
            <div class="warning">
                <strong>⏱️ Important:</strong> This verification link expires in 24 hours.
            </div>
            <p>If you didn't add this email address, please ignore this email or contact support.</p>
        </div>
        <div class="footer">
            <p>The Vape Shed Supplier Portal</p>
            <p>Support: <a href="mailto:suppliers@vapeshed.co.nz">suppliers@vapeshed.co.nz</a></p>
        </div>
    </div>
</body>
</html>
HTML;

    return [
        'subject' => $subject,
        'body' => $textBody,
        'html' => $htmlBody
    ];
}

/**
 * Get primary email changed notification template
 * 
 * @param string $supplierName Supplier company name
 * @param string $oldEmail Previous primary email
 * @param string $newEmail New primary email
 * @return array ['subject' => string, 'body' => string, 'html' => string]
 */
function getPrimaryEmailChangedTemplate(string $supplierName, string $oldEmail, string $newEmail): array {
    $subject = "Primary email address changed - The Vape Shed Supplier Portal";
    
    $textBody = <<<TEXT
Hi {$supplierName},

Your primary email address has been changed.

Old primary email: {$oldEmail}
New primary email: {$newEmail}

If you didn't make this change, please contact support immediately.

---
The Vape Shed Supplier Portal
Support: suppliers@vapeshed.co.nz
TEXT;

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #000; color: #fbbf24; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .info-box { background: #fff; padding: 15px; border: 1px solid #ddd; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>The Vape Shed Supplier Portal</h1>
        </div>
        <div class="content">
            <h2>Primary Email Address Changed</h2>
            <p>Hi <strong>{$supplierName}</strong>,</p>
            <p>This is a security notification that your primary email address has been changed.</p>
            <div class="info-box">
                <p><strong>Old primary email:</strong> {$oldEmail}</p>
                <p><strong>New primary email:</strong> {$newEmail}</p>
            </div>
            <div class="alert">
                <strong>⚠️ Security Notice:</strong> If you didn't make this change, please contact support immediately at <a href="mailto:suppliers@vapeshed.co.nz">suppliers@vapeshed.co.nz</a>
            </div>
        </div>
        <div class="footer">
            <p>The Vape Shed Supplier Portal</p>
            <p>Support: <a href="mailto:suppliers@vapeshed.co.nz">suppliers@vapeshed.co.nz</a></p>
        </div>
    </div>
</body>
</html>
HTML;

    return [
        'subject' => $subject,
        'body' => $textBody,
        'html' => $htmlBody
    ];
}

/**
 * Send email using PHP mail function
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @return bool Success status
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): bool {
    $from = NOTIFICATION_EMAIL_FROM ?? 'noreply@vapeshed.co.nz';
    $fromName = NOTIFICATION_EMAIL_NAME ?? 'The Vape Shed Supplier Portal';
    
    // Create email headers
    $headers = [];
    $headers[] = "From: {$fromName} <{$from}>";
    $headers[] = "Reply-To: {$from}";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "MIME-Version: 1.0";
    
    // Create multipart boundary
    $boundary = md5(uniqid((string)time()));
    
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
    
    // Build email body
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    
    $body .= "--{$boundary}--";
    
    // Send email
    try {
        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully to: {$to}");
        } else {
            error_log("Failed to send email to: {$to}");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Email sending exception: " . $e->getMessage());
        return false;
    }
}
