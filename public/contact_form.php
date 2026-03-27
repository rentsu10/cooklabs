<?php
require_once __DIR__ . '/../inc/config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Validate inputs
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message, created_at, is_read) 
                VALUES (?, ?, ?, ?, NOW(), 0)
            ");
            $stmt->execute([$name, $email, $subject, $message]);
            
            // Set success message
            $success_message = 'Your message has been sent successfully! We will get back to you soon.';
            
        } catch (PDOException $e) {
            $error_message = 'Failed to send message. Please try again later.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Cooklabs Learning Management System</title>
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your exact CSS - unchanged */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, #1e3c72 10%, #2a5298 40%, #3498db 100%);
        }
        
        .contact-container {
            width: 100%;
            max-width: 520px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.98));
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.6s ease-out;
        }
        
        .contact-header {
            background: linear-gradient(135deg, #2980b9 0%, #1a75d2 100%);
            color: white;
            padding: 35px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .contact-header h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .contact-header p {
            opacity: 0.95;
            font-size: 16px;
            font-weight: 400;
        }
        
        .alert {
            margin: 20px 30px 0;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1) 0%, rgba(46, 204, 113, 0.05) 100%);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(231, 76, 60, 0.05) 100%);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        form {
            padding: 40px 35px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 15px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #3498db;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e1e8f0;
            border-radius: 10px;
            font-size: 16px;
            color: #2c3e50;
            transition: all 0.3s;
            background: linear-gradient(to right, #f8fafd, #ffffff);
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
            background: white;
            animation: glow 1.5s infinite alternate;
        }
        
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #a0aec0;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #3498db 0%, #1a75d2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s;
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: linear-gradient(135deg, #2980b9 0%, #1665b8 100%);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            transform: translateY(-3px);
        }
        
        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            box-shadow: none;
        }
        
        .btn-submit i {
            font-size: 18px;
        }
        
        .back-link {
            text-align: center;
            margin: 0 35px 35px;
            padding-top: 20px;
            border-top: 1px solid #e1e8f0;
        }
        
        .back-link a {
            color: #3498db;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        
        .back-link a:hover {
            color: #1a75d2;
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes glow {
            from {
                box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
            }
            to {
                box-shadow: 0 0 0 6px rgba(52, 152, 219, 0.3);
            }
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            .contact-container {
                max-width: 100%;
                border-radius: 12px;
            }
            
            .contact-header,
            form {
                padding: 25px 20px;
            }
            
            .contact-header h2 {
                font-size: 28px;
            }
            
            .btn-submit {
                padding: 16px;
                font-size: 16px;
            }
            
            .alert {
                margin: 15px 20px 0;
            }
            
            .back-link {
                margin: 0 20px 25px;
            }
        }

        /* Optional: Add a subtle pattern overlay */
        .contact-container::after {
            display: none;
        }

        /* Custom styling for icons in header */
        .contact-header h2 i {
            margin-right: 10px;
            font-size: 36px;
            vertical-align: middle;
        }

        /* Focus state for better accessibility */
        .btn-submit:focus,
        .back-link a:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: 2px solid #3498db;
            outline-offset: 2px;
        }

        /* Loading state button */
        .btn-submit.loading {
            position: relative;
            pointer-events: none;
        }

        .btn-submit.loading i {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="contact-container">
        <div class="contact-header">
            <h2><i class="fas fa-envelope"></i> Contact Us</h2>
            <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        </div>
        
        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="contactForm">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i>Your Name</label>
                <input type="text" id="name" name="name" placeholder="Enter your full name" required
                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i>Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="subject"><i class="fas fa-tag"></i>Subject</label>
                <input type="text" id="subject" name="subject" placeholder="What is this about?" required
                       value="<?= isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="message"><i class="fas fa-comment"></i>Message</label>
                <textarea id="message" name="message" placeholder="Type your message here..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                Send Message
            </button>
        </form>

        <div class="back-link">
            <a href="<?= BASE_URL ?>/public/index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        if (successAlert && successAlert.parentElement) {
                            successAlert.remove();
                        }
                    }, 500);
                }, 5000);
            }

            // Prevent double submission
            const form = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner"></i> Sending...';
                });
            }
        });
    </script>
</body>
</html>