<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$userId = $_SESSION['user']['id'];
$u = current_user();


// Get fresh user data from database to ensure we have latest
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id'] ?? 0]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Update session with fresh data (optional but good for consistency)
if ($userData) {
    $_SESSION['user'] = $userData;
    $u = $userData; // Update local variable
}

$createdAt = $userData['created_at'] ?? null;

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");

$stmt->execute([$userId]);
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate counters
$counter = ['ongoing' => 0, 'completed' => 0, 'not_enrolled' => 0];
foreach ($courses as $c) {
    if (!$c['enroll_status']) $counter['not_enrolled']++;
    elseif ($c['enroll_status'] === 'ongoing') $counter['ongoing']++;
    elseif ($c['enroll_status'] === 'completed') $counter['completed']++;

}

// Function to get role display name
function get_role_display_name($role) {
    $roles = [
        'superadmin' => 'SuperAdmin',
        'admin' => 'Admin',
        'proponent' => 'Instructor',
        'user' => 'Student',
    ];
    return $roles[$role] ?? ucfirst($role);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile - CookLabs LMS</title>
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter (geometric) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/profile.css">
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>              
</div>

<div class="profile-wrapper">
    <!-- Success Message -->
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="profile-header">
        <h1>My Profile</h1>
        <p>View and manage your account information</p>
    </div>

    <div class="profile-card">
        <!-- Avatar - CIRCLE with role-based color -->
        <div class="profile-avatar <?= strtolower(trim($u['role'] ?? 'user')) ?>">
            <?php
            $initials = 'U';
            if(isset($u['fname']) && !empty($u['fname'])) {
                $initials = '';
                $nameParts = explode(' ', $u['fname'] . ' ' . ($u['lname'] ?? ''));
                foreach($nameParts as $part) {
                    if(!empty(trim($part))) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    if(strlen($initials) >= 2) break;
                }
            }
            echo $initials ?: 'U';
            ?>
        </div>

        <!-- Basic Info -->
        <div class="user-info">
            <h2 class="user-name"><?= htmlspecialchars($u['fname'] . ' ' . ($u['lname'] ?? '')) ?></h2>
            
            <!-- Role badge - NO EDGES with role-based color -->
            <div class="user-role <?= strtolower(trim($u['role'] ?? 'user')) ?>">
                <?= htmlspecialchars(get_role_display_name($u['role'] ?? '')) ?>
            </div>
            
            <p class="user-email">
                <i class="fas fa-envelope"></i>
                <?= htmlspecialchars($u['email'] ?? 'No email provided') ?>
            </p>
            <p class="member-since">
                <i class="fas fa-calendar-alt"></i>
                Member since: 
                <?php 
                if($createdAt && !empty($createdAt)) {
                    echo date('F j, Y', strtotime($createdAt));
                } else {
                    echo 'Unknown';
                }
                ?>
            </p>
        </div>

        <!-- STATS CARDS REMOVED -->

        <!-- Edit Profile Button -->
        <a href="<?= BASE_URL ?>/public/edit_profile.php" class="modern-btn-warning">  
            <i class="fas fa-edit"></i> Edit Profile
        </a>
    </div>

    <!-- Kitchen accent line -->
    <div class="kitchen-accent">
        <i class="fas fa-cube"></i>
        <i class="fas fa-utensils"></i>
        <i class="fas fa-cube"></i>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-dismiss success alert after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    }
});
</script>

</body>
</html>