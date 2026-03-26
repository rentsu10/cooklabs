<?php
// login.php
require_once __DIR__ . '/../inc/config.php';
// session_start();

$error = '';
$pending_contact = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Find user
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Check if user is confirmed
        if (isset($user['status']) && $user['status'] !== 'confirmed') {
            if ($user['status'] === 'pending') {
                $pending_contact = 'Your account is pending approval. Please ask Instructor/Admin for assistance.';
            } else {
                $error = 'Please confirm your email before logging in.';
            }
        } else {
            // Login successful
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'fname' => $user['fname'] ?? '',
                'lname' => $user['lname'] ?? '',
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'] ?? 'confirmed'
            ];
            
            header('Location: dashboard.php');
            exit();
        }
    } else {
        $error = 'Invalid username or password';
    }
}


$admin_email = '';
try {
$stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();
if ($admin) {
$admin_email = $admin['email'];
}
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CookLabs LMS · Login</title>
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="icon" type="image/png" href="/path/to/your/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
        .flash-notice {
            animation: fadeOut 2s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; display: none; }
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
        
        /* Make login card position relative for absolute positioning */
        .login-card {
            position: relative;
        }
    </style>
</head>
<body style="background: url('../uploads/images/cooklabs-bg.png') no-repeat center center fixed; background-size: cover;">
    <div class="login-wrapper">
        <div class="login-card">
            <!-- Back icon -->
            <a href="index.php" class="back-icon">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <!-- Logo above form -->
            <div class="logo-top">
                <div class="logo-sharp">
                    <img src="../uploads/images/cooklabs-logo.png" alt="Cooklabs Logo" title="Cooklabs Learning Management System">
                </div>
            </div>

            <!-- Form Header -->

            <div class="form-sub">
                Enter your credentials
            </div>

            <!-- Pending Notification -->
            <?php if (!empty($pending_contact)): ?>
            <div class="message-block pending-note">
                <i class="fas fa-clock"></i>
                <div>
                    <div style="font-weight:700;">Account pending approval</div>
                    <div><?= htmlspecialchars($pending_contact) ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if(!empty($error)): ?>
            <div class="message-block error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <div id="forgotMsg" class="forgot-message">
                <i class="fas fa-info-circle"></i>
                <span>Please contact the administrator to reset your password.</span>
            </div>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <!-- Username/Email -->
                <div class="form-group">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            placeholder="your username"
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input no-paste" 
                            placeholder="••••••••"
                            required
                            onpaste="return false;"
                            oncontextmenu="return false;"
                            oncopy="return false;"
                            oncut="return false;"
                            ondrop="return false;"
                        >
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div style = "margin-bottom: 1rem;">
                    <a href="#" class="forgot-link" 
                        onclick="document.getElementById('forgotMsg').classList.add('show'); 
                        setTimeout(function(){ document.getElementById('forgotMsg').classList.remove('show'); }, 5000); return false;">
                        Forgot password?
                    </a>
                </div>

                <!-- Login button -->
                <div>
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </div>

                <!-- Sign up link -->
                <div class="signup-prompt">
                    Don't have an account? 
                    <a href="<?= BASE_URL ?>/public/register.php" class="signup-link">Sign up now</a>
                </div>
            </form>

        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Input animation (subtle)
        const wrappers = document.querySelectorAll('.input-wrapper');
        wrappers.forEach(wrapper => {
            const input = wrapper.querySelector('.form-input');
            input.addEventListener('focus', () => {
                wrapper.style.transform = 'scale(1.01)';
            });
            input.addEventListener('blur', () => {
                wrapper.style.transform = 'scale(1)';
            });
        });
        
        // Clear error on typing
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const errorMessage = document.querySelector('.error-message');
        
        if(errorMessage) {
            usernameInput.addEventListener('input', () => errorMessage.style.display = 'none');
            passwordInput.addEventListener('input', () => errorMessage.style.display = 'none');
        }

        // Basic validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });

        // Additional protection against paste for password field
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('paste', (e) => {
                e.preventDefault();
                
                // Create and show notification
                const notification = document.createElement('div');
                notification.className = 'no-paste-notice flash-notice';
                notification.innerHTML = '<i class="fas fa-ban"></i> Pasting is disabled for security';
                notification.style.color = '#dc3545';
                notification.style.marginTop = '2px';
                
                // Remove any existing notification
                const existing = passwordField.closest('.form-group').querySelector('.flash-notice');
                if (existing) existing.remove();
                
                passwordField.closest('.form-group').appendChild(notification);
                
                // Remove after animation
                setTimeout(() => {
                    if (notification) {
                        notification.remove();
                    }
                }, 2000);
            });
        }

        // Auto-dismiss error message after 5 seconds
        setTimeout(function() {
            var errorMsg = document.getElementById('errorMessage');
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
            var forgotMsg = document.getElementById('forgotMsg');
            if (forgotMsg && forgotMsg.classList.contains('show')) {
                setTimeout(function() {
                    forgotMsg.classList.remove('show');
                }, 5000);
            }
        }, 100);
    </script>
</body>
</html>