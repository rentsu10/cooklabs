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
$userRole = $u['role'] ?? 'user';

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress, e.completed_at, e.is_archived,
           a.passing_score,
           (
               SELECT aa.score 
               FROM assessment_attempts aa 
               WHERE aa.assessment_id = a.id 
               AND aa.user_id = ? 
               AND aa.status = 'completed'
               ORDER BY aa.completed_at DESC 
               LIMIT 1
           ) as latest_score,
           CASE 
               WHEN a.id IS NOT NULL THEN
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 
                           FROM assessment_attempts aa 
                           WHERE aa.assessment_id = a.id 
                           AND aa.user_id = ? 
                           AND aa.status = 'completed'
                           AND aa.score >= a.passing_score
                       ) THEN 1
                       ELSE 0
                   END
               ELSE 0
           END AS assessment_passed
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    LEFT JOIN assessments a ON a.course_id = c.id
    WHERE c.is_active = 1
    ORDER BY e.completed_at DESC
");

$stmt->execute([$userId, $userId, $userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate counters
$counter = ['ongoing' => 0, 'completed' => 0, 'not_enrolled' => 0, 'archived' => 0];
$archivedCourses = [];

foreach ($courses as $c) {
    if ($c['is_archived'] == 1) {
        $counter['archived']++;
        // Only add to archived courses if they have a completed_at date or assessment passed
        if ($c['completed_at'] || $c['assessment_passed']) {
            $archivedCourses[] = $c;
        }
    } elseif (!$c['enroll_status']) {
        $counter['not_enrolled']++;
    } elseif ($c['enroll_status'] === 'ongoing') {
        $counter['ongoing']++;
    } elseif ($c['enroll_status'] === 'completed') {
        $counter['completed']++;
    }
}

// Function to get medal icon based on score
function getMedalIcon($score, $passed) {
    
    if ($score >= 90 && $passed) {
        return '<i class="fas fa-medal" style="color: #f4c429;"></i>';
    } elseif ($score >= 80) {
        return '<i class="fas fa-medal" style="color: #c0c0c0;"></i>';
    } elseif ($score >= 70) {
        return '<i class="fas fa-medal" style="color: #945618;"></i>';
    } else {
        return '<i class="fas fa-certificate" style="color: #1d6fb0;"></i>';
    }
}

// Function to format date
function formatBadgeDate($date) {
    if (!$date || $date == '0000-00-00 00:00:00') {
        return 'Date unknown';
    }
    return date('M d, Y', strtotime($date));
}

// Check if user is student
$isStudent = ($userRole === 'user');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile - CookLabs LMS</title>
    <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter (geometric) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/profile.css">
    <style>
        /* Center the profile card for non-student roles */
        .profile-wrapper.centered {
            justify-content: center;
            align-items: center;
        }
        
        .profile-wrapper.centered .profile-card {
            margin: 0 auto;
            max-width: 600px;
        }
        
        .profile-wrapper.centered .profile-header {
            text-align: center;
        }
        
        .profile-wrapper.centered .profile-header h1 {
            border-left: none;
            padding-left: 0;
        }
        
        .profile-wrapper.centered .profile-header p {
            margin-left: 0;
        }
    </style>
</head>
<body>

<div class="lms-sidebar-container">
    <?php include __DIR__ . '/../inc/sidebar.php'; ?>              
</div>

<div class="profile-wrapper <?= !$isStudent ? 'centered' : '' ?>">
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

    <?php if ($isStudent): ?>
        <!-- Student view: Two column layout with badges -->
        <div class="two-column-layout">
            <!-- LEFT COLUMN - Profile Info (70%) -->
            <div class="profile-left">
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
                        
                        <!-- Role badge -->
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

                    <!-- Edit Profile Button -->
                    <a href="<?= BASE_URL ?>/public/edit_profile.php" class="modern-btn-warning">  
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                </div>
            </div>

            <!-- RIGHT COLUMN - Badge Panel (30%) -->
            <div class="profile-right">
                <div class="badge-panel">
                    <div class="badge-header">
                        <h3>
                            <i class="fas fa-award" style="color: #ffc107;"></i> 
                            Achievement Badges
                            <span class="badge-count"><?= count($archivedCourses) ?></span>
                        </h3>
                        <p>Earned from completed courses</p>
                    </div>

                    <div class="badge-list">
                        <?php if(count($archivedCourses) > 0): ?>
                            <?php foreach($archivedCourses as $badge): 
                                $score = $badge['latest_score'] ?? null;
                                $passed = $badge['assessment_passed'] ?? false;
                                $medalIcon = getMedalIcon($score, $passed);
                                $acquiredDate = $badge['completed_at'] ?: ($badge['is_archived'] ? $badge['completed_at'] : null);
                            ?>
                            <div class="badge-item">
                                <div class="badge-icon">
                                    <?= $medalIcon ?>
                                </div>
                                <div class="badge-info">
                                    <div class="badge-title"><?= htmlspecialchars($badge['title']) ?></div>
                                    <?php if($score): ?>
                                        <div class="badge-score">
                                            Score: <?= $score ?>% 
                                            <?php if($passed): ?>
                                                <span style="color: #28a745;">✓ Passed</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="badge-date">
                                        <i class="fas fa-calendar-check"></i>
                                        <?= formatBadgeDate($acquiredDate) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-badges">
                                <i class="fas fa-medal"></i>
                                <p>No badges earned yet</p>
                                <small>Complete courses to earn achievement badges</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Non-student view: Centered profile card only -->
        <div class="profile-card" style="max-width: 600px; margin: 0 auto;">
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
                
                <!-- Role badge -->
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

            <!-- Edit Profile Button -->
            <a href="<?= BASE_URL ?>/public/edit_profile.php" class="modern-btn-warning">  
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>
    <?php endif; ?>

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