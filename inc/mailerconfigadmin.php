<?php
// Manual include of PHPMailer files (files are now present)
$phpmailerDir = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';

require_once $phpmailerDir . 'PHPMailer.php';
require_once $phpmailerDir . 'SMTP.php';
require_once $phpmailerDir . 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// SMTP Configuration
$smtpConfig = [
    'host' => 'smtp.gmail.com',
    'username' => 'learningmanagement576@gmail.com',
    'password' => 'ahkv dpsl urcn lbmr',
    'port' => 587,
    'from_email' => 'learningmanagement576@gmail.com',
    'from_name' => 'CookLabs LMS'
];

function sendConfirmationEmail($recipientEmail, $recipientName, $username = '', $password = '') {
    global $smtpConfig;
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtpConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpConfig['username'];
        $mail->Password   = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpConfig['port'];
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        // Recipients
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Has Been Created - CookLabs LMS';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border: 1px solid #ddd; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 10px; text-align: center; }
                .content { padding: 20px; }
                .credentials { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
                .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>CookLabs LMS</h2>
                </div>
                <div class="content">
                    <h3>Welcome to CookLabs, ' . htmlspecialchars($recipientName) . '!</h3>
                    <p>Your account has been created successfully. Below are your login credentials:</p>
                    <div class="credentials">
                        <p><strong>Username:</strong> ' . htmlspecialchars($username) . '</p>
                        <p><strong>Password:</strong> ' . htmlspecialchars($password) . '</p>
                    </div>
                    <p><strong>Please change your password after your first login for security purposes.</strong></p>
                    <p>You can now log in and start exploring courses.</p>
                    <p><a href="' . BASE_URL . '/login.php" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none;">Login Now</a></p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' CookLabs LMS. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Welcome to CookLabs!\n\nHello " . $recipientName . ",\n\nYour account has been created.\n\nUsername: " . $username . "\nPassword: " . $password . "\n\nLogin at: " . BASE_URL . "/login.php\n\nPlease change your password after first login.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}

function sendApprovalNotification($recipientEmail, $recipientName) {
    error_log("Approval notification would be sent to: $recipientEmail");
    return ['success' => true, 'message' => 'Notification logged'];
}

function sendRejectionNotification($recipientEmail, $recipientName) {
    error_log("Rejection notification would be sent to: $recipientEmail");
    return ['success' => true, 'message' => 'Notification logged'];
}
?>