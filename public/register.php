<?php
require_once __DIR__ . '/../inc/config.php';
// session_start();

// Check if PHPMailer exists
$mailer_path = __DIR__ . '/../inc/mailer.php';
if (file_exists($mailer_path)) {
require_once $mailer_path;
} else {
die("Mailer configuration not found!");
}

$err = ''; 
$success = '';
$showOTP = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_POST['otp_verify'])) {
// OTP VERIFICATION
$enteredOTP = $_POST['security_code'] ?? '';
$storedOTP = $_SESSION['registration_otp'] ?? '';
$userData = $_SESSION['registration_data'] ?? [];

if (empty($enteredOTP)) {
$err = 'Please enter the OTP';
$showOTP = true;
} elseif (empty($storedOTP) || empty($userData)) {
$err = 'OTP session expired. Please register again.';
} elseif ($enteredOTP !== $storedOTP) {
$err = 'Invalid OTP. Please try again.';
$showOTP = true;
} else {
// OTP is correct! Create the user account
$hash = password_hash($userData['password'], PASSWORD_DEFAULT);

// Insert into database 
$stmt = $pdo->prepare('INSERT INTO users (username, password, fname, lname, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, "user", "pending", NOW())');

if ($stmt->execute([
$userData['username'], 
$hash, 
$userData['fname'], 
$userData['lname'], 
$userData['email'],

])) {
// Clear session data
unset($_SESSION['registration_otp']);
unset($_SESSION['registration_data']);
unset($_SESSION['otp_time']);

// Redirect to login
header('Location: login.php?registered=1');
exit();
} else {
$err = 'Registration failed. Please try again.';
$showOTP = true;
}
}

} elseif (isset($_POST['resend_otp'])) {
// Resend OTP
$userData = $_SESSION['registration_data'] ?? [];

if (!empty($userData)) {
// Generate new OTP
$newOTP = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['registration_otp'] = $newOTP;
$_SESSION['otp_time'] = time();

// Send OTP
$fullName = $userData['fname'] . ' ' . $userData['lname'];
$emailResult = sendOTPEmail($userData['email'], $fullName, $newOTP);

if (!$emailResult['success']) {
$emailResult = sendOTPEmail($userData['email'], $fullName, $newOTP);
}

$success = $emailResult['success'] ? "New OTP sent to your email" : "Failed to resend OTP";
$showOTP = true;
} else {
$err = 'Session expired. Please register again.';
}

} else {
// INITIAL REGISTRATION FORM
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fname = trim($_POST['fname'] ?? '');
$lname = trim($_POST['lname'] ?? '');
$email = trim($_POST['email'] ?? '');
$course = trim($_POST['course'] ?? '');

// Validation
if (!$username || !$password || !$email) { 
$err = 'All fields are required.'; 
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$err = 'Invalid email format';
} elseif (strlen($password) < 8) {
$err = 'Password must be at least 8 characters';
} else {
// Check if user exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$stmt->execute([$username, $email]);
if ($stmt->fetch()) { 
$err = 'Username or email already exists'; 
} else {
// Generate OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// store session
$_SESSION['registration_data'] = [
    'username' => $username,
    'password' => $password,
    'fname' => $fname,
    'lname' => $lname,
    'email' => $email,
    'course' => $course
];
$_SESSION['registration_otp'] = $otp;
$_SESSION['otp_time'] = time();

// Send OTP
$fullName = $fname . ' ' . $lname;
$emailResult = sendOTPEmail($email, $fullName, $otp);

// If PHPMailer fails
if (!$emailResult['success']) {
    error_log("PHPMailer failed: " . $emailResult['message']);
    $emailResult = sendOTPEmail($email, $fullName, $otp);
    
    if ($emailResult['success']) {
        $success = "OTP generated: <strong>$otp</strong> (Check server logs)";
        $showOTP = true;
    } else {
        $err = 'Failed to generate OTP. Please try again.';
    }
} else {
    $success = "OTP has been sent to $email";
    $showOTP = true;
}
}
}
}
}

// Calculate OTP time left
if (isset($_SESSION['otp_time'])) {
$otpTime = $_SESSION['otp_time'];
$currentTime = time();
$timeElapsed = $currentTime - $otpTime;
$timeLeft = 600 - $timeElapsed; // 10 minutes
if ($timeLeft < 0) $timeLeft = 0;
} else {
$timeLeft = 600;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CookLabs LMS · Register</title>
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter (geometric friendly) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register.css">
    <style>
        /* Additional style to show paste is disabled */
        .no-paste-notice {
            font-size: 11px;
            color: #6c757d;
            margin-top: 4px;
            margin-left: 1rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .no-paste-notice i {
            font-size: 10px;
            color: #dc3545;
        }
        
        /* Back icon style */
        .back-icon {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 1.5rem;
            color: #1661a3;
            cursor: pointer;
            transition: all 0.1s ease;
            z-index: 10;
        }
        
        .back-icon:hover {
            color: #1a70b5;
            transform: translateX(-2px);
        }
        
        /* Make register card position relative for absolute positioning */
        .register-card {
            position: relative;
        }
    </style>
</head>
<body style="background: url('../uploads/images/cooklabs-bg.png') no-repeat center center fixed; background-size: cover;">
    <div class="register-wrapper">
        <div class="register-card">
            <!-- Back icon -->
            <a href="index.php" class="back-icon">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <!-- Logo above form (identical to login) -->
            <div class="logo-top">
                <div class="logo-sharp">
                    <img src="../uploads/images/cooklabs-logo.png" alt="Cooklabs Logo" title="Cooklabs Learning Management System">
                </div>
            </div>

            <!-- Form Header -->
            <div class="form-sub">
            Fill in your details to get started
            </div>

            <!-- Error/Success Messages -->
            <?php if($err): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($err) ?></span>
            </div>
            <?php endif; ?>

            <?php if($success && !$showOTP): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form action="../public/register.php" method="POST" id="registerForm">
                <?php if(!$showOTP): ?>
                <!-- First Name & Last Name Row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="fname" class="form-label">First Name</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input 
                                type="text" 
                                id="fname" 
                                name="fname" 
                                class="form-input" 
                                placeholder="John"
                                value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lname" class="form-label">Last Name</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input 
                                type="text" 
                                id="lname" 
                                name="lname" 
                                class="form-input" 
                                placeholder="Doe"
                                value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>"
                                required
                            >
                        </div>
                    </div>
                </div>

                <!-- Email Address -->    
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="john.doe@mail.com"
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                            required
                        >
                    </div>
                </div>
               
                <!-- Username -->
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-at input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="johndoe123"
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Password Row -->
                <div class="form-row">    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input no-paste" 
                                placeholder="Use strong password"
                                required
                                onkeyup="checkPasswordStrength()"
                                onpaste="return false;"
                                oncontextmenu="return false;"
                                oncopy="return false;"
                                oncut="return false;"
                                ondrop="return false;"
                            >
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', 'togglePasswordIcon')">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <small class="password-hint">Minimum 8 characters recommended</small>
                        <div class="password-strength">
                            <span>Strength:</span>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthBar"></div>
                            </div>
                            <span id="strengthText">Weak</span>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input no-paste" 
                                placeholder="Re-enter password"
                                required
                                onkeyup="validatePasswordMatch()"
                                onpaste="return false;"
                                oncontextmenu="return false;"
                                oncopy="return false;"
                                oncut="return false;"
                                ondrop="return false;"
                            >
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmIcon')">
                                <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                            </button>
                        </div>
                        <small id="passwordMatchMessage" style="color: #dc3545; font-size: 0.75rem; margin-left: 1rem;"></small>
                    </div>
                </div>

                <!-- Register Button -->
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

                <!-- Sign In Link -->
                <div class="signin-prompt">
                    Already have an account? 
                    <a href="../public/login.php" class="signin-link">Sign in now</a>
                </div>

                <?php else: ?>
                <!-- OTP Verification Section -->
                <div class="otp-section">
                    <?php if($success): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?= $success ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-shield-alt"></i> Verify Your Email
                    </h3>
                    
                    <p style="text-align: center; margin-bottom: 20px;">
                        Enter the 6-digit OTP sent to:<br>
                        <strong><?= htmlspecialchars($_SESSION['registration_data']['email'] ?? '') ?></strong>
                    </p>
                    
                    <div class="otp-input-container">
                        <input type="text" id="security_code" name="security_code" class="otp-input" 
                                placeholder="000000" maxlength="6" pattern="\d{6}" required 
                                autocomplete="off" inputmode="numeric">
                        <input type="hidden" name="otp_verify" value="1">
                    </div>
                    
                    <div class="timer-display" id="otpTimer">
                        <?php
                        if ($timeLeft <= 0) {
                            echo '<span class="timer-expired">OTP has expired</span>';
                        } else {
                            $minutes = floor($timeLeft / 60);
                            $seconds = $timeLeft % 60;
                            echo "OTP expires in: <span id='timeLeft'>" . sprintf('%02d:%02d', $minutes, $seconds) . "</span>";
                        }
                        ?>
                    </div>
                    
                    <div style="text-align: center; margin: 15px 0;">
                        <button type="button" class="resend-code-btn" id="resendCodeBtn" 
                                onclick="resendOTP()" <?= $timeLeft > 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                        <span id="resendTimer" style="font-size: 12px; color: #666; margin-left: 10px;"></span>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="register-btn btn-verify">
                            <i class="fas fa-check"></i> Verify & Register
                        </button>
                        <button type="button" class="register-btn btn-cancel" onclick="window.location.href='register.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                        <i class="fas fa-info-circle"></i> Didn't receive the code? Check your spam folder.
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- JavaScript (identical functionality, untouched) -->
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            if (password.match(/[$@#&!]+/)) strength += 25;
            
            strength = Math.min(strength, 100);
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 30) {
                strengthText.textContent = 'Weak';
                strengthBar.style.background = '#dc3545';
            } else if (strength < 55) {
                strengthText.textContent = 'Medium';
                strengthBar.style.background = '#ffc107';
            } else if (strength < 80) {
                strengthText.textContent = 'Strong';
                strengthBar.style.background = '#230cf5';            
            } else {
                strengthText.textContent = 'Complex';
                strengthBar.style.background = '#28a745';
            }
        }

        // Validate password match
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('passwordMatchMessage');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    message.textContent = '✓ Passwords match';
                    message.style.color = '#28a745';
                } else {
                    message.textContent = '✗ Passwords do not match';
                    message.style.color = '#dc3545';
                }
            } else {
                message.textContent = '';
            }
        }

        // OTP Timer and Auto-submit
        const otpTimeLeft = <?= $timeLeft ?>;
        let timeLeft = otpTimeLeft;
        let canResend = timeLeft <= 0;
        let resendCooldown = 60;

        function updateOTPTimer() {
            if (timeLeft > 0) {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;

                const timeLeftElement = document.getElementById('timeLeft');
                if (timeLeftElement) {
                    timeLeftElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }

                if (timeLeft <= 0) {
                    canResend = true;
                    document.getElementById('resendCodeBtn').disabled = false;
                    document.getElementById('otpTimer').innerHTML = '<span class="timer-expired">OTP has expired</span>';
                }
            }
        }

        function updateResendTimer() {
            const resendBtn = document.getElementById('resendCodeBtn');
            const resendTimerElement = document.getElementById('resendTimer');

            if (!canResend && resendCooldown > 0) {
                resendCooldown--;
                resendBtn.disabled = true;
                if (resendTimerElement) {
                    resendTimerElement.textContent = `Resend in ${resendCooldown}s`;
                }

                if (resendCooldown <= 0) {
                    canResend = true;
                    resendBtn.disabled = false;
                    if (resendTimerElement) {
                        resendTimerElement.textContent = '';
                    }
                }
            }
        }

        <?php if ($showOTP): ?>
        setInterval(updateOTPTimer, 1000);
        setInterval(updateResendTimer, 1000);
        <?php endif; ?>

        // Auto-submit OTP when 6 digits entered
        const securityCodeInput = document.getElementById('security_code');
        if (securityCodeInput) {
            securityCodeInput.focus();

            securityCodeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
                if (this.value.length === 6) {
                    setTimeout(() => {
                        document.getElementById('registerForm').submit();
                    }, 300);
                }
            });

            securityCodeInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = pastedText.replace(/\D/g, '');
                this.value = numbers.slice(0, 6);
                if (this.value.length === 6) {
                    setTimeout(() => {
                        document.getElementById('registerForm').submit();
                    }, 300);
                }
            });
        }

        // Additional protection against paste for password fields
        document.querySelectorAll('.no-paste').forEach(input => {
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                // Optional: Show a small notification
                const notification = document.createElement('div');
                notification.className = 'no-paste-notice flash-notice';
                notification.innerHTML = '<i class="fas fa-ban"></i> Pasting is disabled for password fields';
                notification.style.color = '#dc3545';
                notification.style.fontSize = '11px';
                notification.style.marginTop = '2px';
                
                // Remove any existing notification
                const existing = input.closest('.form-group').querySelector('.flash-notice');
                if (existing) existing.remove();
                
                input.closest('.form-group').appendChild(notification);
                
                // Fade out and remove
                setTimeout(() => {
                    if (notification) {
                        notification.style.transition = 'opacity 0.5s';
                        notification.style.opacity = '0';
                        setTimeout(() => notification.remove(), 500);
                    }
                }, 2000);
            });
        });

        function resendOTP() {
            if (!canResend) return;
            if (confirm('Resend OTP to your email?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'resend_otp';
                input.value = '1';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Form validation
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                <?php if (!$showOTP): ?>
                const username = document.getElementById('username')?.value.trim();
                const password = document.getElementById('password')?.value;
                const email = document.getElementById('email')?.value.trim();

                if (!username) {
                    e.preventDefault();
                    showError('Username is required');
                    return false;
                }
                if (!password) {
                    e.preventDefault();
                    showError('Password is required');
                    return false;
                }
                if (password.length < 8) {
                    if (!confirm('Your password is less than 8 characters. Continue anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                if (!email) {
                    e.preventDefault();
                    showError('Email is required');
                    return false;
                }

                <?php else: ?>
                const otp = document.getElementById('security_code')?.value.trim();
                if (!otp || otp.length !== 6) {
                    e.preventDefault();
                    showError('Please enter a valid 6-digit OTP');
                    return false;
                }
                <?php endif; ?>
            });
        }

        function showError(message) {
            let errorDiv = document.querySelector('.alert-danger');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${message}</span>`;
                form.insertBefore(errorDiv, form.firstChild);
            } else {
                errorDiv.querySelector('span').textContent = message;
            }
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Input animations
        const inputs = document.querySelectorAll('.form-input, .otp-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>