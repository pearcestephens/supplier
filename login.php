<?php
/**
 * Supplier Portal - Email-Based Authentication
 *
 * Beautiful login page with magic link delivery via email
 * Only accessible via secure links sent to registered supplier emails
 *
 * @package Supplier
 * @version 3.0.0
 */

declare(strict_types=1);

// Load standalone libraries
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Utils.php';

// Start session
Session::start();

// If already logged in, redirect to portal
if (Auth::check()) {
    header('Location: /supplier/');
    exit;
}

// Initialize database connection
$db = Database::connect();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        // Check if email exists in supplier database
        try {
            $stmt = $db->prepare("
                SELECT id, name, email
                FROM vend_suppliers
                WHERE email = ?
                AND (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
                LIMIT 1
            ");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();

            if ($supplier) {
                // Generate magic login link
                $loginUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/supplier/index.php?supplier_id=' . urlencode($supplier['id']);

                // Send email
                $emailSent = sendLoginEmail($supplier['email'], $supplier['name'], $loginUrl);

                if ($emailSent) {
                    $message = 'Access link sent! Please check your email (' . htmlspecialchars($email) . ') for your secure login link.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send email. Please contact support.';
                    $messageType = 'danger';
                }
            } else {
                // Don't reveal if email exists or not (security best practice)
                $message = 'If this email is registered, you will receive an access link shortly.';
                $messageType = 'info';
            }
        } catch (Exception $e) {
            error_log('Login email error: ' . $e->getMessage());
            $message = 'System error. Please try again later.';
            $messageType = 'danger';
        }
    }
}

// Handle error from redirect
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_id') {
        $message = 'Invalid or expired access link. Please request a new one.';
        $messageType = 'danger';
    }
}

/**
 * Send login email with magic link
 */
function sendLoginEmail(string $email, string $name, string $loginUrl): bool
{
    $subject = 'Your Vape Shed Supplier Portal Access Link';

    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
            .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 40px 30px; }
            .button { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; font-size: 16px; }
            .button:hover { opacity: 0.9; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .warning strong { color: #856404; display: block; margin-bottom: 10px; }
            .warning ul { margin: 0; padding-left: 20px; }
            .warning li { color: #856404; font-size: 14px; margin-bottom: 5px; }
            .code-box { background: #f8f9fa; border: 1px solid #e0e0e0; padding: 15px; border-radius: 4px; word-break: break-all; font-family: monospace; font-size: 13px; color: #666; margin-top: 10px; }
            .footer { text-align: center; padding: 30px; background: #f8f9fa; color: #666; font-size: 13px; }
            .footer a { color: #667eea; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 Supplier Portal Access</h1>
                <p>The Vape Shed</p>
            </div>
            <div class="content">
                <h2 style="margin-top: 0; color: #333;">Hi ' . htmlspecialchars($name) . ',</h2>
                <p style="font-size: 16px; color: #555;">You requested access to the Supplier Portal. Click the button below to log in securely:</p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($loginUrl) . '" class="button">🚀 Access Supplier Portal</a>
                </div>

                <div class="warning">
                    <strong>🔒 Security Notice</strong>
                    <ul>
                        <li>This link will log you in automatically</li>
                        <li>Do not share this link with anyone</li>
                        <li>Link expires after first use or in 24 hours</li>
                        <li>If you didn\'t request this, please ignore this email</li>
                    </ul>
                </div>

                <p style="margin-top: 30px; color: #666; font-size: 14px;">
                    <strong>Can\'t click the button?</strong> Copy and paste this URL into your browser:
                </p>
                <div class="code-box">' . htmlspecialchars($loginUrl) . '</div>
            </div>
            <div class="footer">
                <p>This is an automated message from The Vape Shed Supplier Portal.</p>
                <p style="margin-top: 10px;">Need help? Contact us at <a href="mailto:support@vapeshed.co.nz">support@vapeshed.co.nz</a></p>
                <p style="margin-top: 10px; font-size: 12px;">&copy; ' . date('Y') . ' The Vape Shed. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    // Plain text version
    $textMessage = "Hi $name,\n\n";
    $textMessage .= "You requested access to the Supplier Portal.\n\n";
    $textMessage .= "Click this link to log in:\n$loginUrl\n\n";
    $textMessage .= "Security Notice:\n";
    $textMessage .= "- This link will log you in automatically\n";
    $textMessage .= "- Do not share this link with anyone\n";
    $textMessage .= "- Link expires after first use or in 24 hours\n\n";
    $textMessage .= "If you didn't request this, please ignore this email.\n\n";
    $textMessage .= "The Vape Shed Supplier Portal\n";
    $textMessage .= "Support: support@vapeshed.co.nz";

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: The Vape Shed Supplier Portal <noreply@vapeshed.co.nz>\r\n";
    $headers .= "Reply-To: support@vapeshed.co.nz\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n";

    // Send email
    return mail($email, $subject, $htmlMessage, $headers);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Vape Shed Supplier Portal</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* Subtle background pattern */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 50%, rgba(255, 204, 0, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 204, 0, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: #1a1a1a;
            border-radius: 16px;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 204, 0, 0.1);
            overflow: hidden;
            border-top: 4px solid #ffcc00;
        }

        .login-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            padding: 50px 40px 40px;
            text-align: center;
            position: relative;
        }

        /* Yellow accent line */
        .login-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 40px;
            right: 40px;
            height: 3px;
            background: linear-gradient(90deg, #ffcc00 0%, rgba(255, 204, 0, 0.3) 100%);
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo-text {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: -1.5px;
            background: linear-gradient(135deg, #ffcc00 0%, #ffd700 50%, #ffcc00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-transform: uppercase;
            text-shadow: 0 4px 20px rgba(255, 204, 0, 0.4);
            filter: drop-shadow(0 2px 10px rgba(255, 204, 0, 0.3));
        }

        .logo-subtext {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 4px;
            color: #666;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .login-header h1 {
            margin: 25px 0 12px 0;
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .login-header p {
            margin: 0;
            color: #888;
            font-size: 14px;
            line-height: 1.6;
        }

        .login-body {
            padding: 40px;
            background: #1a1a1a;
        }

        .form-label {
            font-weight: 600;
            color: #ffcc00;
            margin-bottom: 10px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            height: 54px;
            border-radius: 8px;
            border: 2px solid #333;
            background: #0d0d0d;
            color: #fff;
            padding: 14px 18px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ffcc00;
            background: #1a1a1a;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.1);
        }

        .form-control::placeholder {
            color: #666;
        }

        .btn-primary {
            height: 54px;
            border-radius: 8px;
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
            border: none;
            color: #000;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 4px 15px rgba(255, 204, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 204, 0, 0.5);
            background: linear-gradient(135deg, #ffd700 0%, #ffcc00 100%);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary.loading::after {
            content: "";
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.2);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px 18px;
            margin-bottom: 24px;
            background: #2d2d2d;
            border-left: 4px solid #ffcc00;
        }

        .alert-danger {
            border-left-color: #ff4444;
            background: #2d1a1a;
            color: #ff9999;
        }

        .alert-success {
            border-left-color: #00cc66;
            background: #1a2d1a;
            color: #99ffcc;
        }

        .info-box {
            background: #0d0d0d;
            border-left: 4px solid #ffcc00;
            padding: 18px;
            border-radius: 8px;
            margin-top: 24px;
        }

        .info-box i {
            color: #ffcc00;
            margin-right: 10px;
        }

        .info-box p {
            margin: 0;
            font-size: 13px;
            color: #999;
            line-height: 1.6;
        }

        .footer-text {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #333;
            margin-top: 24px;
        }

        .footer-text p {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #666;
        }

        .footer-text a {
            color: #ffcc00;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .footer-text a:hover {
            color: #ffd700;
            text-decoration: none;
        }

        .footer-text i {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-container {
                padding: 10px;
            }

            .login-header {
                padding: 40px 30px 30px;
            }

            .login-body {
                padding: 30px 25px;
            }

            .logo-text {
                font-size: 28px;
            }
        }
    </style>
