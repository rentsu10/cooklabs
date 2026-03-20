<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration from a separate file
$smtpConfig = [
    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
    'username' => defined('SMTP_USERNAME') ? trim(SMTP_USERNAME) : 'learningmanagement576@gmail.com',
    'password' => defined('SMTP_PASSWORD') ? trim(SMTP_PASSWORD) : 'ahkv dpsl urcn lbmr',
    'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
    'from_email' => defined('SMTP_FROM_EMAIL') ? trim(SMTP_FROM_EMAIL) : 'learningmanagement576@gmail.com',
    'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'LMS'
];

function sendConfirmationEmail($recipientEmail, $recipientName) {
    global $smtpConfig;
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        // Sender and recipient
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Activated - CookLabs LMS';
        
        // EXACT DESIGN MATCHING OTP MAILER
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ----- SHARP GEOMETRIC DESIGN (matching OTP mailer) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, Arial, sans-serif;
            background: #eaf2fc;
            padding: 30px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 3px solid #1a4b77;
            box-shadow: 16px 16px 0 #123a5e;
            border-radius: 0px;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }

        /* logo / header section */
        .email-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Logo container with exact 2:1 ratio */
        .logo-container {
            width: 240px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            background: white;
            border-radius: 0px;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* greeting / title */
        .greeting {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            margin-bottom: 1rem;
            border-left: 8px solid #1d6fb0;
            padding-left: 1.2rem;
        }

        .greeting span {
            color: #2680cf;
        }

        .message {
            font-size: 1rem;
            color: #1e4465;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 5px 5px 0 #a0c0e0;
            padding: 1rem 1.5rem;
            border-radius: 0px;
        }

        /* activation section */
        .activation-section {
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 10px 10px 0 #1b3b58;
            padding: 2rem 1.5rem;
            margin: 1.5rem 0 2rem;
            text-align: center;
            border-radius: 0px;
        }

        .activation-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0a3458;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .activation-badge {
            font-size: 2.5rem;
            font-weight: 800;
            color: #07223b;
            background: white;
            border: 3px solid #28a745;
            box-shadow: 8px 8px 0 #1e7e34;
            padding: 1rem 2rem;
            display: inline-block;
            margin: 0.5rem 0 1rem;
            border-radius: 0px;
        }

        .status {
            font-size: 1rem;
            font-weight: 500;
            color: #10487a;
            background: white;
            border: 2px solid #14568a;
            box-shadow: 4px 4px 0 #1d4670;
            padding: 0.6rem 1.2rem;
            display: inline-block;
            border-radius: 0px;
        }

        .status i {
            color: #1f6fb0;
            margin-right: 6px;
        }

        /* success note */
        .success-note {
            background: #e8f5e9;
            border: 2px solid #28a745;
            box-shadow: 6px 6px 0 #1e7e34;
            padding: 1.2rem 1.5rem;
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: 0px;
            color: #155724;
            font-weight: 500;
        }

        .success-note i {
            font-size: 2rem;
            color: #28a745;
        }

        .success-note strong {
            font-weight: 700;
            color: #0d4715;
        }

        /* button */
        .login-button {
            display: inline-block;
            background: #1661a3;
            border: 3px solid #0c314d;
            box-shadow: 6px 6px 0 #0b263b;
            padding: 0.8rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 1rem 0;
            transition: all 0.1s ease;
        }

        .login-button:hover {
            transform: translate(-2px, -2px);
            box-shadow: 8px 8px 0 #0b263b;
            background: #1a70b5;
        }

        /* footer */
        .email-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 4px solid #2367a3;
            text-align: center;
        }

        .footer-note {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #5f6f82;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .line {
            flex: 1;
            height: 3px;
            background: #2367a3;
        }

        .signature {
            color: #1e4465;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .signature strong {
            color: #1a4b77;
            font-weight: 700;
        }

        /* responsive */
        @media (max-width: 600px) {
            .email-container {
                padding: 1.5rem;
            }
            .activation-badge {
                font-size: 1.8rem;
                padding: 0.8rem 1.5rem;
            }
            .logo-container {
                width: 180px;
                height: 90px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="email-container">

        <!-- Greeting -->
        <div class="greeting">
            account <span>activated!</span>
        </div>
        
        <!-- Dynamic message with recipient name -->
        <div class="message">
            <i class="fas fa-check-circle" style="margin-right: 8px; color: #28a745;"></i> 
            Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,<br>
            Your account has been successfully activated. You can now access all features of the CookLabs Learning Management System.
        </div>

        <!-- Activation section -->
        <div class="activation-section">
            <div class="activation-label">
                <i class="fas fa-check-circle"></i> account status
            </div>
            <div class="activation-badge">✓ ACTIVATED</div>
            <div class="status">
                <i class="fas fa-clock"></i> ready to learn
            </div>
        </div>

        <!-- Success note -->
        <div class="success-note">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>YOUR ACCOUNT IS NOW ACTIVE.</strong><br>
                You can now log in and start exploring courses.
            </div>
        </div>


        <!-- Additional info -->
        <p style="color: #1e4465; margin: 1rem 0; padding-left: 0.5rem; border-left: 3px solid #307fc7;">
            <i class="fas fa-info-circle" style="color: #1f6fb0;"></i> 
            If you have any questions, please contact the administrator.
        </p>

        <!-- Footer with signature -->
        <div class="email-footer">
            <div class="footer-note">
                <span class="line"></span>
                <span>CookLabs LMS</span>
                <span class="line"></span>
            </div>
            <div class="signature">
                <strong>Best regards,</strong><br>
                Your CookLabs Team<br>
                <span style="font-size: 0.8rem; color: #5f6f82;">
                    <i class="fas fa-cube"></i> sharp edges, soft learning 
                    <i class="fas fa-cube"></i>
                </span>
            </div>
        </div>
    </div>
</body>
</html>';
        
        $mail->AltBody = "Account Activated!\n\nHello " . $recipientName . ",\n\nYour account has been successfully activated.\n\nYou can now login at: " . BASE_URL . "/login.php\n\nBest regards,\nCookLabs LMS Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Activation email sent'];
        
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return ['success' => false, 'message' => "Failed to send email: " . $e->getMessage()];
    }
}

// Keep simple version for testing
function sendConfirmationEmailSimple($email, $name) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Confirmation email for $name ($email)");
        return ['success' => true, 'message' => 'Email logged locally'];
    }
    return sendConfirmationEmail($email, $name);
}

function sendWelcomeEmail($recipientEmail, $recipientName, $username, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? trim(SMTP_USERNAME) : 'learningmanagement576@gmail.com';
        $mail->Password   = defined('SMTP_PASSWORD') ? trim(SMTP_PASSWORD) : 'ahkv dpsl urcn lbmr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        
        // Sender and recipient
        $fromEmail = defined('SMTP_FROM_EMAIL') ? trim(SMTP_FROM_EMAIL) : 'learningmanagement576@gmail.com';
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'CookLabs LMS Admin';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to CookLabs LMS - Your Account Has Been Created';
        
        // EXACT DESIGN MATCHING OTP MAILER
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ----- SHARP GEOMETRIC DESIGN (matching OTP mailer) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, Arial, sans-serif;
            background: #eaf2fc;
            padding: 30px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 3px solid #1a4b77;
            box-shadow: 16px 16px 0 #123a5e;
            border-radius: 0px;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }

        /* logo / header section */
        .email-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Logo container with exact 2:1 ratio */
        .logo-container {
            width: 240px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            background: white;
            border-radius: 0px;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* greeting / title */
        .greeting {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            margin-bottom: 1rem;
            border-left: 8px solid #1d6fb0;
            padding-left: 1.2rem;
        }

        .greeting span {
            color: #2680cf;
        }

        .message {
            font-size: 1rem;
            color: #1e4465;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 5px 5px 0 #a0c0e0;
            padding: 1rem 1.5rem;
            border-radius: 0px;
        }

        /* credentials section */
        .credentials-section {
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 10px 10px 0 #1b3b58;
            padding: 2rem 1.5rem;
            margin: 1.5rem 0 2rem;
            border-radius: 0px;
        }

        .credentials-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0a3458;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .cred-box {
            background: white;
            border: 3px solid #1d6fb0;
            box-shadow: 5px 5px 0 #0f4980;
            padding: 1rem 1.5rem;
            margin: 1rem 0;
            border-radius: 0px;
        }

        .cred-row {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #b8d6f5;
        }

        .cred-row:last-child {
            border-bottom: none;
        }

        .cred-label {
            font-weight: 600;
            color: #0f4980;
            width: 100px;
        }

        .cred-value {
            font-family: Courier New, monospace;
            font-size: 1.1rem;
            font-weight: 600;
            color: #07223b;
            background: #f0f8ff;
            padding: 0.3rem 0.8rem;
            border: 1px solid #b8d6f5;
        }

        /* warning note */
        .warning-note {
            background: #fff9e0;
            border: 2px solid #b88f1f;
            box-shadow: 6px 6px 0 #8f6f1a;
            padding: 1.2rem 1.5rem;
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: 0px;
            color: #5f4c0e;
            font-weight: 500;
        }

        .warning-note i {
            font-size: 2rem;
            color: #b88f1f;
        }

        .warning-note strong {
            font-weight: 700;
            color: #3d2e06;
        }

        /* login button */
        .login-button {
            display: inline-block;
            background: #1661a3;
            border: 3px solid #0c314d;
            box-shadow: 6px 6px 0 #0b263b;
            padding: 0.8rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 1rem 0;
            transition: all 0.1s ease;
        }

        .login-button:hover {
            transform: translate(-2px, -2px);
            box-shadow: 8px 8px 0 #0b263b;
            background: #1a70b5;
        }

        /* footer */
        .email-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 4px solid #2367a3;
            text-align: center;
        }

        .footer-note {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #5f6f82;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .line {
            flex: 1;
            height: 3px;
            background: #2367a3;
        }

        .signature {
            color: #1e4465;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .signature strong {
            color: #1a4b77;
            font-weight: 700;
        }

        /* responsive */
        @media (max-width: 600px) {
            .email-container {
                padding: 1.5rem;
            }
            .logo-container {
                width: 180px;
                height: 90px;
            }
            .cred-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }
            .cred-label {
                width: auto;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="email-container">

        <!-- Greeting -->
        <div class="greeting">
            welcome <span>to CookLabs!</span>
        </div>
        
        <!-- Dynamic message with recipient name -->
        <div class="message">
            <i class="fas fa-user-plus" style="margin-right: 8px; color: #1f6fb0;"></i> 
            Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,<br>
            Your account has been created by an administrator. Below are your login credentials.
        </div>

        <!-- Credentials section -->
        <div class="credentials-section">
            <div class="credentials-label">
                <i class="fas fa-key"></i> your login credentials
            </div>
            
            <div class="cred-box">
                <div class="cred-row">
                    <span class="cred-label">Username:</span>
                    <span class="cred-value">' . htmlspecialchars($username) . '</span>
                </div>
                <div class="cred-row">
                    <span class="cred-label">Password:</span>
                    <span class="cred-value">' . htmlspecialchars($password) . '</span>
                </div>
            </div>
        </div>

        <!-- Warning note -->
        <div class="warning-note">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>IMPORTANT SECURITY NOTICE.</strong><br>
                Please change your password immediately after logging in.
            </div>
        </div>

        <!-- Login button -->
        <div style="text-align: center; margin: 1.5rem 0;">
            <a href="' . BASE_URL . '/login.php" class="login-button">
                <i class="fas fa-sign-in-alt"></i> LOGIN TO YOUR ACCOUNT
            </a>
        </div>

        <!-- Additional info -->
        <p style="color: #1e4465; margin: 1rem 0; padding-left: 0.5rem; border-left: 3px solid #307fc7;">
            <i class="fas fa-info-circle" style="color: #1f6fb0;"></i> 
            If you have any questions, please contact the administrator.
        </p>

        <!-- Footer with signature -->
        <div class="email-footer">
            <div class="footer-note">
                <span class="line"></span>
                <span>CookLabs LMS</span>
                <span class="line"></span>
            </div>
            <div class="signature">
                <strong>Best regards,</strong><br>
                Your CookLabs Team<br>
                <span style="font-size: 0.8rem; color: #5f6f82;">
                    <i class="fas fa-cube"></i> sharp edges, soft learning 
                    <i class="fas fa-cube"></i>
                </span>
            </div>
        </div>
    </div>
</body>
</html>';
        
        // Plain text alternative
        $mail->AltBody = "Welcome to CookLabs!\n\nHello " . $recipientName . ",\n\nYour account has been created.\n\nUsername: " . $username . "\nPassword: " . $password . "\n\nIMPORTANT: Please change your password immediately after logging in.\n\nLogin at: " . BASE_URL . "/login.php\n\nBest regards,\nCookLabs LMS Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Welcome email sent successfully'];
        
    } catch (Exception $e) {
        error_log('Welcome email failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ===== NEW FUNCTION: Account Approval Notification =====
function sendApprovalNotification($recipientEmail, $recipientName) {
    global $smtpConfig;
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        // Sender and recipient
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Approved - CookLabs LMS';
        
        // DESIGN MATCHING OTP MAILER - Approval Notification
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ----- SHARP GEOMETRIC DESIGN (matching OTP mailer) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, Arial, sans-serif;
            background: #eaf2fc;
            padding: 30px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 3px solid #1a4b77;
            box-shadow: 16px 16px 0 #123a5e;
            border-radius: 0px;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }

        /* logo / header section */
        .email-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Logo container with exact 2:1 ratio */
        .logo-container {
            width: 240px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            background: white;
            border-radius: 0px;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* greeting / title */
        .greeting {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            margin-bottom: 1rem;
            border-left: 8px solid #1d6fb0;
            padding-left: 1.2rem;
        }

        .greeting span {
            color: #2680cf;
        }

        .message {
            font-size: 1rem;
            color: #1e4465;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 5px 5px 0 #a0c0e0;
            padding: 1rem 1.5rem;
            border-radius: 0px;
        }

        /* approval section */
        .approval-section {
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 10px 10px 0 #1b3b58;
            padding: 2rem 1.5rem;
            margin: 1.5rem 0 2rem;
            text-align: center;
            border-radius: 0px;
        }

        .approval-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0a3458;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .approval-badge {
            font-size: 2.5rem;
            font-weight: 800;
            color: #07223b;
            background: white;
            border: 3px solid #28a745;
            box-shadow: 8px 8px 0 #1e7e34;
            padding: 1rem 2rem;
            display: inline-block;
            margin: 0.5rem 0 1rem;
            border-radius: 0px;
        }

        .status {
            font-size: 1rem;
            font-weight: 500;
            color: #10487a;
            background: white;
            border: 2px solid #14568a;
            box-shadow: 4px 4px 0 #1d4670;
            padding: 0.6rem 1.2rem;
            display: inline-block;
            border-radius: 0px;
        }

        .status i {
            color: #1f6fb0;
            margin-right: 6px;
        }

        /* success note */
        .success-note {
            background: #e8f5e9;
            border: 2px solid #28a745;
            box-shadow: 6px 6px 0 #1e7e34;
            padding: 1.2rem 1.5rem;
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: 0px;
            color: #155724;
            font-weight: 500;
        }

        .success-note i {
            font-size: 2rem;
            color: #28a745;
        }

        .success-note strong {
            font-weight: 700;
            color: #0d4715;
        }

        /* button */
        .login-button {
            display: inline-block;
            background: #1661a3;
            border: 3px solid #0c314d;
            box-shadow: 6px 6px 0 #0b263b;
            padding: 0.8rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 1rem 0;
            transition: all 0.1s ease;
        }

        .login-button:hover {
            transform: translate(-2px, -2px);
            box-shadow: 8px 8px 0 #0b263b;
            background: #1a70b5;
        }

        /* footer */
        .email-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 4px solid #2367a3;
            text-align: center;
        }

        .footer-note {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #5f6f82;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .line {
            flex: 1;
            height: 3px;
            background: #2367a3;
        }

        .signature {
            color: #1e4465;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .signature strong {
            color: #1a4b77;
            font-weight: 700;
        }

        /* responsive */
        @media (max-width: 600px) {
            .email-container {
                padding: 1.5rem;
            }
            .approval-badge {
                font-size: 1.8rem;
                padding: 0.8rem 1.5rem;
            }
            .logo-container {
                width: 180px;
                height: 90px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="email-container">

        <!-- Greeting -->
        <div class="greeting">
            account <span>approved!</span>
        </div>
        
        <!-- Dynamic message with recipient name -->
        <div class="message">
            <i class="fas fa-check-circle" style="margin-right: 8px; color: #28a745;"></i> 
            Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,<br>
            Your account has been approved by an administrator. You can now log in and start using the CookLabs Learning Management System.
        </div>

        <!-- Approval section -->
        <div class="approval-section">
            <div class="approval-label">
                <i class="fas fa-check-circle"></i> account status
            </div>
            <div class="approval-badge">✓ APPROVED</div>
            <div class="status">
                <i class="fas fa-clock"></i> ready to log in
            </div>
        </div>

        <!-- Success note -->
        <div class="success-note">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>YOUR ACCOUNT HAS BEEN ACTIVATED.</strong><br>
                You can now log in and access all features.
            </div>
        </div>

        <!-- Footer with signature -->
        <div class="email-footer">
            <div class="footer-note">
                <span class="line"></span>
                <span>CookLabs LMS</span>
                <span class="line"></span>
            </div>
            <div class="signature">
                <strong>Best regards,</strong><br>
                Your CookLabs Team<br>
                <span style="font-size: 0.8rem; color: #5f6f82;">
                    <i class="fas fa-cube"></i> sharp edges, soft learning 
                    <i class="fas fa-cube"></i>
                </span>
            </div>
        </div>
    </div>
</body>
</html>';
        
        $mail->AltBody = "Account Approved!\n\nHello " . $recipientName . ",\n\nYour account has been approved by an administrator. You can now log in at: " . BASE_URL . "/login.php\n\nBest regards,\nCookLabs LMS Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Approval notification email sent'];
        
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return ['success' => false, 'message' => "Failed to send email: " . $e->getMessage()];
    }
}

// Simple version for local testing
function sendApprovalNotificationSimple($email, $name) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Approval notification for $name ($email)");
        return ['success' => true, 'message' => 'Email logged locally'];
    }
    return sendApprovalNotification($email, $name);
}

// Simple version for local testing
function sendWelcomeEmailSimple($email, $name, $username, $password) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Welcome email for $name ($email) - Username: $username, Password: $password");
        return ['success' => true, 'message' => 'Welcome email logged locally'];
    }
    return sendWelcomeEmail($email, $name, $username, $password);
}

// ===== NEW FUNCTION: Account Rejection Notification =====
function sendRejectionNotification($recipientEmail, $recipientName) {
    global $smtpConfig;
    $mail = new PHPMailer(true);
    
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        // Sender and recipient
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Account Application Status - CookLabs LMS';
        
        // DESIGN MATCHING OTP MAILER - Rejection Notification
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ----- SHARP GEOMETRIC DESIGN (matching OTP mailer) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, Arial, sans-serif;
            background: #eaf2fc;
            padding: 30px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border: 3px solid #1a4b77;
            box-shadow: 16px 16px 0 #123a5e;
            border-radius: 0px;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }

        /* logo / header section */
        .email-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Logo container with exact 2:1 ratio */
        .logo-container {
            width: 240px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            background: white;
            border-radius: 0px;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* greeting / title */
        .greeting {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            margin-bottom: 1rem;
            border-left: 8px solid #1d6fb0;
            padding-left: 1.2rem;
        }

        .greeting span {
            color: #2680cf;
        }

        .message {
            font-size: 1rem;
            color: #1e4465;
            margin-bottom: 1.8rem;
            line-height: 1.5;
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 5px 5px 0 #a0c0e0;
            padding: 1rem 1.5rem;
            border-radius: 0px;
        }

        /* rejection section */
        .rejection-section {
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 10px 10px 0 #1b3b58;
            padding: 2rem 1.5rem;
            margin: 1.5rem 0 2rem;
            text-align: center;
            border-radius: 0px;
        }

        .rejection-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0a3458;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .rejection-badge {
            font-size: 2.5rem;
            font-weight: 800;
            color: #07223b;
            background: white;
            border: 3px solid #dc3545;
            box-shadow: 8px 8px 0 #a71d2a;
            padding: 1rem 2rem;
            display: inline-block;
            margin: 0.5rem 0 1rem;
            border-radius: 0px;
        }

        .status {
            font-size: 1rem;
            font-weight: 500;
            color: #10487a;
            background: white;
            border: 2px solid #14568a;
            box-shadow: 4px 4px 0 #1d4670;
            padding: 0.6rem 1.2rem;
            display: inline-block;
            border-radius: 0px;
        }

        .status i {
            color: #1f6fb0;
            margin-right: 6px;
        }

        /* info note */
        .info-note {
            background: #fff9e0;
            border: 2px solid #b88f1f;
            box-shadow: 6px 6px 0 #8f6f1a;
            padding: 1.2rem 1.5rem;
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: 0px;
            color: #5f4c0e;
            font-weight: 500;
        }

        .info-note i {
            font-size: 2rem;
            color: #b88f1f;
        }

        .info-note strong {
            font-weight: 700;
            color: #3d2e06;
        }

        /* footer */
        .email-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 4px solid #2367a3;
            text-align: center;
        }

        .footer-note {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #5f6f82;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
        }

        .line {
            flex: 1;
            height: 3px;
            background: #2367a3;
        }

        .signature {
            color: #1e4465;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .signature strong {
            color: #1a4b77;
            font-weight: 700;
        }

        /* responsive */
        @media (max-width: 600px) {
            .email-container {
                padding: 1.5rem;
            }
            .rejection-badge {
                font-size: 1.8rem;
                padding: 0.8rem 1.5rem;
            }
            .logo-container {
                width: 180px;
                height: 90px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="email-container">

        <!-- Greeting -->
        <div class="greeting">
            application <span>status</span>
        </div>
        
        <!-- Dynamic message with recipient name -->
        <div class="message">
            <i class="fas fa-info-circle" style="margin-right: 8px; color: #1f6fb0;"></i> 
            Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,<br>
            Thank you for your interest in CookLabs Learning Management System.
        </div>

        <!-- Rejection section -->
        <div class="rejection-section">
            <div class="rejection-label">
                <i class="fas fa-info-circle"></i> application status
            </div>
            <div class="rejection-badge">NOT APPROVED</div>
            <div class="status">
                <i class="fas fa-clock"></i> application reviewed
            </div>
        </div>

        <!-- Info note -->
        <div class="info-note">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>ACCOUNT APPLICATION NOT APPROVED.</strong><br>
                Unfortunately, your account application could not be approved at this time. This may be due to verification requirements or account type availability.
            </div>
        </div>

        <!-- Encouragement message -->
        <div style="background: white; border: 2px solid #b8d6f5; padding: 1rem; margin: 1rem 0;">
            <p style="color: #1e4465; margin: 0;">
                <i class="fas fa-lightbulb" style="color: #ffc107; margin-right: 8px;"></i>
                You may reapply in the future or contact the administrator if you have questions.
            </p>
        </div>

        <!-- Additional info -->
        <p style="color: #1e4465; margin: 1rem 0; padding-left: 0.5rem; border-left: 3px solid #307fc7;">
            <i class="fas fa-envelope" style="color: #1f6fb0;"></i> 
            For questions, please contact the administrator.
        </p>

        <!-- Footer with signature -->
        <div class="email-footer">
            <div class="footer-note">
                <span class="line"></span>
                <span>CookLabs LMS</span>
                <span class="line"></span>
            </div>
            <div class="signature">
                <strong>Best regards,</strong><br>
                Your CookLabs Team<br>
                <span style="font-size: 0.8rem; color: #5f6f82;">
                    <i class="fas fa-cube"></i> sharp edges, soft learning 
                    <i class="fas fa-cube"></i>
                </span>
            </div>
        </div>
    </div>
</body>
</html>';
        
        $mail->AltBody = "Application Status - CookLabs LMS\n\nHello " . $recipientName . ",\n\nThank you for your interest in CookLabs LMS.\n\nUnfortunately, your account application could not be approved at this time.\n\nYou may reapply in the future or contact the administrator if you have questions.\n\nBest regards,\nCookLabs LMS Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Rejection notification email sent'];
        
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return ['success' => false, 'message' => "Failed to send email: " . $e->getMessage()];
    }
}

// Simple version for local testing
function sendRejectionNotificationSimple($email, $name) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Local dev - Rejection notification for $name ($email)");
        return ['success' => true, 'message' => 'Email logged locally'];
    }
    return sendRejectionNotification($email, $name);
}
?>

