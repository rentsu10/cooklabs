<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer
// OR manually require if not using Composer:
// require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

function sendOTPEmail($recipientEmail, $recipientName, $otpCode) {
    $mail = new PHPMailer(true);
    
    try {
        // setting ng mail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'learningmanagement576@gmail.com'; // debugging
        $mail->Password   = 'ahkv dpsl urcn lbmr'; // debugging
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // encryption
        $mail->Port       = 587; // TLS: 587, SSL: 465
        
        // sender and recipient
        $mail->setFrom('learningmanagement576@gmail.com', 'LMS');
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code';
        
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <style>
        /* ----- SHARP GEOMETRIC DESIGN (matching login/welcome pages) ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Inter, Arial, sans-serif;
            background: #eaf2fc;  /* fresh blue background */
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
            border-radius: 0px;  /* sharp edges, no curves */
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }

        /* logo / header section - updated for 2:1 logo */
        .email-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Logo container with exact 2:1 ratio (width:height = 2:1) */
        .logo-container {
            width: 240px;      /* 2:1 ratio -> width 240px, height 120px */
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

        /* OTP code - the star of the show */
        .otp-section {
            background: #d7e9ff;
            border: 3px solid #15415e;
            box-shadow: 10px 10px 0 #1b3b58;
            padding: 2rem 1.5rem;
            margin: 1.5rem 0 2rem;
            text-align: center;
            border-radius: 0px;
        }

        .otp-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0a3458;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .otp-code {
            font-size: 3.5rem;
            font-weight: 800;
            color: #07223b;
            background: white;
            border: 3px solid #1d6fb0;
            box-shadow: 8px 8px 0 #0f4980;
            padding: 1rem 2rem;
            display: inline-block;
            margin: 0.5rem 0 1rem;
            letter-spacing: 8px;
            border-radius: 0px;
            font-family: Courier New, monospace;
        }

        .validity {
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

        .validity i {
            color: #1f6fb0;
            margin-right: 6px;
        }

        /* important admin note */
        .admin-note {
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

        .admin-note i {
            font-size: 2rem;
            color: #b88f1f;
        }

        .admin-note strong {
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
            .otp-code {
                font-size: 2.5rem;
                letter-spacing: 4px;
            }
            .logo-container {
                width: 180px;
                height: 90px;
            }
        }
    </style>
    <!-- Font Awesome for icons (same as your login page) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter (geometric) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="email-container">


        <!-- Greeting with name -->
        <div class="greeting">
            hello <span>there!</span>
        </div>
        
        <!-- Dynamic message with recipient name (placeholder) -->
        <div class="message">
            <i class="fas fa-envelope" style="margin-right: 8px; color: #1f6fb0;"></i> 
            Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,<br>
            Thank you for registering. Use the OTP below to verify your email address.
        </div>

        <!-- OTP section - main focus -->
        <div class="otp-section">
            <div class="otp-label">
                <i class="fas fa-shield-alt"></i> verification code
            </div>
            <div class="otp-code">' . $otpCode . '</div>
            <div class="validity">
                <i class="far fa-clock"></i> valid for 10 minutes
            </div>
        </div>

        <!-- Important admin confirmation notice (pending approval) -->
        <div class="admin-note">
            <i class="fas fa-hourglass-half"></i>
            <div>
                <strong>PLEASE WAIT FOR ADMIN CONFIRMATION BEFORE LOGGING IN.</strong><br>
                Your account will be activated after review.
            </div>
        </div>

        <!-- Additional info -->
        <p style="color: #1e4465; margin: 1rem 0; padding-left: 0.5rem; border-left: 3px solid #307fc7;">
            <i class="fas fa-info-circle" style="color: #1f6fb0;"></i> 
            If you didn\'t request this, please ignore this email.
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
        
        // plain text version for non-HTML email clients
        $mail->AltBody = "Your OTP code: $otpCode\nValid for 10 minutes.\n\nPLEASE WAIT FOR ADMIN CONFIRMATION BEFORE LOGGING IN.\n\nIf you didn't request this, please ignore.";
        
        $mail->send();
        return ["success" => true, "message" => "OTP sent successfully"];
        
    } catch (Exception $e) {
        return ["success" => false, "message" => "Mailer Error: {$mail->ErrorInfo}"];
    }
}

// For testing/development, you can use a simpler version or just bura this and run main
function sendOTPEmailSimple($email, $otp) {
    // For local development without SMTP
    if ($_SERVER["HTTP_HOST"] === "localhost" || $_SERVER["SERVER_NAME"] === "localhost") {
        // Just log it for local development 
        error_log("Local dev - OTP for $email: $otp");
        return ["success" => true, "message" => "OTP logged locally"];
    }
    
    // Production - use actual email this is the MAIN
    return sendOTPEmail($email, "User", $otp);
}
?>