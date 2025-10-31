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
            .header { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 40px 30px; }
            .button { display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; font-size: 16px; }
            .button:hover { opacity: 0.9; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .warning strong { color: #856404; display: block; margin-bottom: 10px; }
            .warning ul { margin: 0; padding-left: 20px; }
            .warning li { color: #856404; font-size: 14px; margin-bottom: 5px; }
            .code-box { background: #f8f9fa; border: 1px solid #e0e0e0; padding: 15px; border-radius: 4px; word-break: break-all; font-family: monospace; font-size: 13px; color: #666; margin-top: 10px; }
            .footer { text-align: center; padding: 30px; background: #f8f9fa; color: #666; font-size: 13px; }
            .footer a { color: #3498db; text-decoration: none; }
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
    <title>Supplier Portal Access - The Vape Shed</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Login Page Specific Styles -->
    <link rel="stylesheet" href="/supplier/assets/css/06-login.css?v=<?php echo time(); ?>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -200px;
            left: -200px;
            animation: float 20s infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -150px;
            right: -150px;
            animation: float 15s infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, 50px) rotate(180deg); }
        }
        
        .login-container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 50px 35px;
            text-align: center;
            position: relative;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        
        .login-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .login-header p {
            font-size: 15px;
            opacity: 0.95;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .login-icon {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .login-icon i {
            font-size: 40px;
            color: white;
        }
        
        .login-body {
            padding: 45px 35px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #3498db;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .info-box h5 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        
        .info-box h5 i {
            margin-right: 10px;
            color: #3498db;
            font-size: 18px;
        }
        
        .info-box p {
            font-size: 14px;
            color: #555;
            margin: 0;
            line-height: 1.7;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #3498db;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            border: none;
            border-radius: 12px;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.5);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 18px 22px;
            margin-bottom: 28px;
            font-size: 14px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert i {
            margin-right: 14px;
            font-size: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .security-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 12px;
            margin-top: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .security-notice h6 {
            font-size: 15px;
            font-weight: 600;
            color: #856404;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        
        .security-notice h6 i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .security-notice ul {
            margin: 0;
            padding-left: 22px;
        }
        
        .security-notice li {
            font-size: 13px;
            color: #856404;
            margin-bottom: 6px;
            line-height: 1.5;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
            color: #666;
            font-size: 13px;
        }
        
        .footer-text a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .footer-text a:hover {
            color: #2c3e50;
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: none;
            margin-left: 10px;
        }
        
        .btn-primary.loading .loading-spinner {
            display: inline-block;
        }
        
        .btn-primary.loading .btn-text {
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1>Supplier Portal</h1>
                <p>Secure email-based authentication</p>
            </div>
            
            <!-- Body -->
            <div class="login-body">
                <!-- Info Box -->
                <div class="info-box">
                    <h5><i class="fas fa-info-circle"></i> Access Instructions</h5>
                    <p>Enter your registered supplier email address. We'll send you a secure access link that will automatically log you in to the portal.</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                    <div><?= $message ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Form -->
                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Your Supplier Email
                        </label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            placeholder="your.email@company.com"
                            required
                            autocomplete="email"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span class="btn-text">
                            <i class="fas fa-paper-plane"></i> Send Access Link
                        </span>
                        <span class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                </form>
                
                <!-- Security Notice -->
                <div class="security-notice">
                    <h6><i class="fas fa-lock"></i> Security Features</h6>
                    <ul>
                        <li><strong>Passwordless:</strong> No passwords to remember or manage</li>
                        <li><strong>Magic Links:</strong> Secure one-time links sent to your email</li>
                        <li><strong>Auto-Expire:</strong> Links expire after use or 24 hours</li>
                        <li><strong>Email Verification:</strong> Only registered suppliers can access</li>
                    </ul>
                </div>
                
                <!-- Footer -->
                <div class="footer-text">
                    <p style="margin-bottom: 8px;">Need help accessing your account?</p>
                    <p>Contact <a href="mailto:support@vapeshed.co.nz"><i class="fas fa-envelope"></i> support@vapeshed.co.nz</a></p>
                    <p style="margin-top: 15px; font-size: 12px; color: #999;">
                        <i class="fas fa-copyright"></i> <?= date('Y') ?> The Vape Shed. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Login Page JavaScript -->
    <script src="/supplier/assets/js/13-login.js?v=<?php echo time(); ?>"></script>
    
    <script>
        $(document).ready(function() {
            // Handle form submission
            $('#loginForm').on('submit', function() {
                const $btn = $('#submitBtn');
                $btn.addClass('loading');
                $btn.prop('disabled', true);
            });
            
            // Auto-focus email field
            $('#email').focus();
            
            // Email validation feedback
            $('#email').on('blur', function() {
                const email = $(this).val();
                if (email && !isValidEmail(email)) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }
        });
    </script>
</body>
</html>
