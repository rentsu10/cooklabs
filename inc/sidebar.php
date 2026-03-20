<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$u = current_user();

// Dynamically define BASE_URL if not defined
if(!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/lms');
}

// Function to get role icon
function get_role_icon($role = '') {
    $icons = [
        'superadmin' => 'fa-user-tie',
        'admin' => 'fa-user-shield',
        'proponent' => 'fa-chalkboard-teacher',
        'user' => 'fa-user-graduate',
    ];
    return $icons[$role] ?? 'fa-user';
}

// Note: get_role_display_name() is already defined in profile.php
// We'll use that function directly
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CookLabs · Sidebar</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <style>
        /* Additional styles for the new divisions */
        .nav-section-divider {
            border-top: 3px solid #2367a3;
            margin: 0.5rem 0 0.5rem 0;
            opacity: 0.6;
        }
        
        /* Ensure proper spacing */
        .nav-item {
            margin-bottom: 0.2rem;
        }
    </style>
</head>
<body>
    <div class="lms-sidebar-container"> 
        <nav class="sidebar lms-sidebar">
            <!-- Logo section with your CookLabs mini logo (2:1 container) -->
            <div class="sidebar-logo">
                <div class="logo-container">
                    <img src="<?= BASE_URL ?>/uploads/images/cooklabs-logo.png" 
                         alt="CookLabs Logo" 
                         class="logo-img">
                </div>
            </div>

            <ul class="nav flex-column">
                <!-- Profile section (first item) with CIRCLE avatar - consistent blue style -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/profile.php">
                        <!-- Circular avatar with consistent blue border, white bg, blue icon -->
                        <div class="profile-icon-mini">
                            <i class="fas <?= get_role_icon($u['role'] ?? '') ?>"></i>
                        </div>
                        <div class="profile-details-mini">
                            <h6 title="<?= htmlspecialchars($u['fname'] ?? '') ?>">
                                 <?= htmlspecialchars($u['fname'] ?? '') ?>
                                 <?= htmlspecialchars($u['lname'] ?? '') ?>
                            </h6>
                            <small>
                                <?php 
                                // Use the function from profile.php
                                if (function_exists('get_role_display_name')) {
                                    echo htmlspecialchars(get_role_display_name($u['role'] ?? 'Guest'));
                                } else {
                                    echo htmlspecialchars(ucfirst($u['role'] ?? 'Guest'));
                                }
                                ?>
                            </small>
                        </div>
                    </a>
                </li>

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/dashboard.php">
                        <i class="fa fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>

                <!-- First Divider -->
                <div class="nav-section-divider"></div>
                
                <!-- COURSES SECTION (no header) -->
                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                <!-- Manage Courses (for proponents/admins) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/admin/courses_crud.php">
                        <i class="fa fa-cog"></i> Manage Courses
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if($u && is_proponent()): ?>
                <!-- All Courses (for proponents) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/proponent/all_course.php">
                        <i class="fa fa-list"></i> All Courses
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if($u && is_student()): ?>
                <!-- All Courses (for students) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/courses.php">
                        <i class="fa fa-list"></i> All Courses
                    </a>
                </li>
                <!-- My Courses (for students) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/my_courses.php">
                        <i class="fa fa-graduation-cap"></i> My Courses
                    </a>
                </li>
                <?php endif; ?>

                <!-- Second Divider (only if there are items after it) -->
                <?php if(($u && (is_admin() || is_superadmin())) || ($u && (is_proponent() || is_admin() || is_superadmin()))): ?>
                <div class="nav-section-divider"></div>
                <?php endif; ?>
                
                <!-- ADMIN & CONTENT SECTION (no header) -->
                <?php if($u && (is_admin() || is_superadmin())): ?>
                <!-- User Management -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/admin/users_crud.php">
                        <i class="fa fa-users"></i> User Management
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- NEWS SECTION (visible to proponents, admins, superadmins) -->
                <?php if($u && (is_proponent() || is_admin() || is_superadmin())): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/admin/news_crud.php">
                        <i class="fa fa-newspaper"></i> News
                    </a>
                </li>
                <?php endif; ?>

                <!-- CONTACT MESSAGES (admin only) -->
                <?php if($u && (is_admin() || is_superadmin())): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/admin/admin_contacts.php">
                        <i class="fa fa-envelope"></i> Contact Messages
                        <?php
                        // Get unread count
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
                        $countStmt->execute();
                        $unread = $countStmt->fetchColumn();
                        if ($unread > 0):
                        ?>
                            <span class="badge bg-danger float-end"><?= $unread ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- ACCOUNT SECTION (no header) -->
                <!-- Logout (last item) -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/public/logout.php">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>

            <!-- Optional small kitchen accent (consistent with theme) -->
            <div class="sidebar-footer-accent">
                <i class="fas fa-cube"></i>
                <i class="fas fa-utensils"></i>
                <i class="fas fa-cube"></i>
            </div>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>