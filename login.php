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
require_once __DIR__ . '/lib/OneTimeAccess.php';
require_once __DIR__ . '/lib/RateLimiter.php';
require_once __DIR__ . '/lib/OneTimeAccess.php';

// Start session
Session::start();

// Initialize database connection
$db = Database::connect();

// Check if arriving with a supplier_id token (magic link) - DO THIS FIRST!
if (isset($_GET['supplier_id'])) {
    $supplierId = trim($_GET['supplier_id']);

    try {
        // Verify supplier exists and is active
        $stmt = $db->prepare("
            SELECT id, name, email
            FROM vend_suppliers
            WHERE id = ?
            AND (deleted_at = '0000-00-00 00:00:00' OR deleted_at = '' OR deleted_at IS NULL)
            LIMIT 1
        ");
        $stmt->bind_param('s', $supplierId);
        $stmt->execute();
        $result = $stmt->get_result();
        $supplier = $result->fetch_assoc();

        if ($supplier) {
            // Log them in using proper Auth method
            $_SESSION['supplier_id'] = $supplier['id'];
            $_SESSION['supplier_name'] = $supplier['name'];
            $_SESSION['supplier_email'] = $supplier['email'];
            $_SESSION['authenticated'] = true;  // Auth::check() looks for this!
            $_SESSION['login_time'] = time();

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Redirect to dashboard
            header('Location: /supplier/dashboard.php');
            exit;
        } else {
            // Invalid or expired token
            $message = 'Invalid or expired access link. Please request a new one.';
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        $message = 'System error. Please try again.';
        $messageType = 'danger';
    }
}

// If already logged in (and not arriving via token), redirect to portal
if (Auth::check()) {
    header('Location: /supplier/');
    exit;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    // Rate limit login attempts per IP
    try {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $limit = defined('RATE_LIMIT_LOGIN_PER_MIN') ? (int)RATE_LIMIT_LOGIN_PER_MIN : 10;
        $rl = new RateLimiter();
        [$allowed, $remaining, $reset] = $rl->check('login:' . $ip, $limit);
        if (!$allowed) {
            $message = 'Too many login attempts. Please try again shortly.';
            $messageType = 'danger';
        }
    } catch (Throwable $e) {
        // On limiter failure, continue without blocking, but log it
        error_log('RateLimiter error on login: ' . $e->getMessage());
    }

    if (empty($message)) {
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
                    // Generate login link directly to index (auto-login)
                    $loginUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/supplier/?supplier_id=' . urlencode($supplier['id']);

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
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #ffffff; background: #000000; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #0a0a0a; border-radius: 8px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.8); border: 1px solid #1a1a1a; }
            .header { background: #000000; color: #ffffff; padding: 40px 30px; text-align: center; border-bottom: 2px solid #ffcc00; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; }
            .header p { margin: 10px 0 0 0; opacity: 0.8; font-size: 14px; color: #ffcc00; }
            .content { padding: 40px 30px; background: #0a0a0a; }
            .content h2 { color: #ffffff; }
            .content p { color: #cccccc; }
            .button { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%); color: #000000 !important; text-decoration: none; border-radius: 8px; font-weight: 700; margin: 20px 0; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
            .button:hover { opacity: 0.9; }
            .warning { background: #1a1a0a; border-left: 4px solid #ffcc00; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .warning strong { color: #ffcc00; display: block; margin-bottom: 10px; }
            .warning ul { margin: 0; padding-left: 20px; }
            .warning li { color: #cccccc; font-size: 14px; margin-bottom: 5px; }
            .code-box { background: #000000; border: 1px solid #333333; padding: 15px; border-radius: 4px; word-break: break-all; font-family: monospace; font-size: 13px; color: #ffcc00; margin-top: 10px; }
            .footer { text-align: center; padding: 30px; background: #000000; color: #666666; font-size: 13px; border-top: 1px solid #1a1a1a; }
            .footer a { color: #ffcc00; text-decoration: none; }
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
                <h2 style="margin-top: 0; color: #ffffff;">Hi ' . htmlspecialchars($name) . ',</h2>
                <p style="font-size: 16px; color: #cccccc;">You requested access to the Supplier Portal. Click the button below to log in securely:</p>

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

                <p style="margin-top: 30px; color: #888888; font-size: 14px;">
                    <strong style="color: #ffcc00;">Can\'t click the button?</strong> Copy and paste this URL into your browser:
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
            color: #ffffff;
        }

        /* Subtle vapor clouds in background */
        body::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(255, 204, 0, 0.03) 0%, transparent 40%),
                radial-gradient(ellipse at 80% 60%, rgba(255, 204, 0, 0.02) 0%, transparent 50%),
                radial-gradient(ellipse at 40% 80%, rgba(255, 204, 0, 0.025) 0%, transparent 45%);
            pointer-events: none;
            animation: vapor 20s ease-in-out infinite;
        }

        body::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 60% 40%, rgba(255, 255, 255, 0.01) 0%, transparent 30%),
                radial-gradient(circle at 30% 70%, rgba(255, 255, 255, 0.008) 0%, transparent 35%);
            pointer-events: none;
            animation: vapor 25s ease-in-out infinite reverse;
        }

        @keyframes vapor {
            0%, 100% {
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            50% {
                transform: translate(30px, -30px) scale(1.1);
                opacity: 0.8;
            }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: #0a0a0a;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
            overflow: hidden;
            border-top: 2px solid #ffcc00;
            border: 1px solid #1a1a1a;
        }

        .login-header {
            background: #000000;
            color: #ffffff;
            padding: 18px 20px 10px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid #1a1a1a;
        }

        /* Yellow accent line */
        .login-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 30px;
            right: 30px;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, #ffcc00 50%, transparent 100%);
        }

        .logo-container {
            margin-bottom: 4px;
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
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 3px;
            color: #ffcc00;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .login-header h1 {
            margin: 12px 0 6px 0;
            font-size: 20px;
            font-weight: 600;
            color: #cccccc;
            letter-spacing: 0px;
        }

        .login-header p {
            margin: 0 0 8px 0;
            color: #888888;
            font-size: 13px;
            line-height: 1.4;
        }

        .login-body {
            padding: 18px;
            background: #0a0a0a;
        }
            line-height: 1.4;
        }

        .login-body {
            padding: 25px;
            background: #0a0a0a;
        }

        .form-label {
            font-weight: 600;
            color: #ffcc00;
            margin-bottom: 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            height: 42px;
            border-radius: 6px;
            border: 1px solid #333333;
            background: #000000;
            color: #ffffff;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ffcc00;
            background: #0a0a0a;
            color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255, 204, 0, 0.1);
        }

        .form-control::placeholder {
            color: #555555;
        }

        .btn-primary {
            height: 44px;
            border-radius: 6px;
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
            border: none;
            color: #000;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 8px rgba(255, 204, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 204, 0, 0.4);
            background: linear-gradient(135deg, #ffd700 0%, #ffcc00 100%);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary.loading::after {
            content: "";
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        /* Password form specific tweaks */
        .password-hint {
            color: #999999;
            font-size: 12px;
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
            border-left: 3px solid #ffcc00;
            padding: 14px;
            border-radius: 6px;
            margin-top: 16px;
        }

        .info-box i {
            color: #ffcc00;
            margin-right: 7px;
            font-size: 12px;
        }

        .info-box p {
            margin: 0;
            font-size: 11px;
            color: #888;
            line-height: 1.5;
        }

        .footer-text {
            text-align: center;
            padding-top: 16px;
            border-top: 1px solid #1a1a1a;
            margin-top: 18px;
        }

        .footer-text p {
            margin: 0 0 5px 0;
            font-size: 10px;
            color: #666;
        }

        .footer-text a {
            color: #ffcc00;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            font-size: 11px;
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
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-container">
                    <img src="https://www.vapeshed.co.nz/assets/template/vapeshed/images/vape-shed-logo.png"
                         alt="The Vape Shed"
                         style="max-width: 260px; height: auto; margin-bottom: 5px;">
                    <div class="logo-subtext">Supplier Portal</div>
                </div>
                <h1>Supplier Access</h1>
                <p>Enter your email to receive your secure login link</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope-fill"></i> Email Address
                        </label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            placeholder="your.email@company.com"
                            required
                            autocomplete="email"
                            autofocus
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send-fill"></i> Send Login Link
                    </button>
                </form>

                <div class="info-box">
                    <p>
                        <i class="bi bi-info-circle-fill"></i>
                        <strong>How it works:</strong> Enter your registered email address and we'll send you a secure login link.
                        Click the link in your email to access the portal instantly.
                    </p>
                </div>

                <div class="footer-text">
                    <p><i class="bi bi-shield-lock-fill"></i> Your connection is secure and encrypted</p>
                    <p>Need help? <a href="mailto:support@vapeshed.co.nz"><i class="bi bi-headset"></i> Contact Support</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</body>
</html>
