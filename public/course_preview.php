<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Get database connection
global $pdo;

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$courseId = intval($_GET['id'] ?? 0);

if (!$courseId) {
    die('Invalid course ID');
}

// Fetch course with enrollment info for current user
$stmt = $pdo->prepare('
    SELECT 
        c.*, 
        u.fname, 
        u.lname,
        e.status AS enroll_status,
        e.progress,
        e.pages_viewed,
        e.enrolled_at
    FROM courses c 
    LEFT JOIN users u ON c.proponent_id = u.id 
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.id = ? AND c.is_active = 1
');
$stmt->execute([$userId, $courseId]);
$course = $stmt->fetch();

if (!$course) {
    die('Course not found');
}

// Check if course is expired
$isExpired = false;
if (!empty($course['expires_at'])) {
    $expiresAt = strtotime($course['expires_at']);
    $now = time();
    $isExpired = ($expiresAt < $now);
}

// Set enrollment status (override if expired)
$enrollStatus = $course['enroll_status'] ?? 'notenrolled';
if ($isExpired && $enrollStatus === 'ongoing') {
    $enrollStatus = 'expired';
}

// Handle enrollment POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    // Check if course is expired
    if ($isExpired) {
        $_SESSION['error'] = "Cannot enroll: This course has expired.";
        header("Location: course_preview.php?id=$courseId");
        exit;
    }
    
    // Check if already enrolled
    if ($enrollStatus === 'ongoing') {
        $_SESSION['error'] = "You are already enrolled in this course.";
        header("Location: course_preview.php?id=$courseId");
        exit;
    }
    
    // Proceed with enrollment
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO enrollments (user_id, course_id, status, enrolled_at, progress) 
            VALUES (?, ?, 'ongoing', NOW(), 0)
        ");
        $stmt->execute([$userId, $courseId]);
        
        $pdo->commit();
        $_SESSION['success'] = "Successfully enrolled in the course!";
        header("Location: " . BASE_URL . "/public/course_view.php?id=$courseId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Enrollment failed: " . $e->getMessage();
        header("Location: course_preview.php?id=$courseId");
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=htmlspecialchars($course['title'])?> - CookLabs LMS</title>
<link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/coursepreview.css">
</head>
<body>
    <!-- Sidebar -->
<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

    <!-- Main Content -->
<div class="course-content-wrapper">
    <!-- Display session messages -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
<i class="fas fa-check-circle"></i>
<?= $_SESSION['success'] ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
<i class="fas fa-exclamation-circle"></i>
<?= $_SESSION['error'] ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

    <!-- Course Header -->
<div class="course-header">
<h3>
<?=htmlspecialchars($course['title'])?>
<?php if ($isExpired): ?>
<span class="expired-badge"><i class="fas fa-hourglass-end"></i> EXPIRED</span>
<?php endif; ?>
</h3>
<p><?=nl2br(htmlspecialchars($course['description']))?></p>
</div>

    <!-- Course Info -->
<div class="course-info-card">
<div class="course-instructor">
<div class="instructor-avatar">
<?= substr($course['fname'] ?? 'I', 0, 1) . substr($course['lname'] ?? 'Instructor', 0, 1) ?>
</div>
<div class="instructor-info">
<h5><?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></h5>
<p>Course Instructor</p>
</div>
</div>

<div class="modern-course-info-meta">
<div class="meta-item">
<i class="fas fa-calendar-alt"></i>
<span><strong>Created on:</strong> <?= date('F j, Y', strtotime($course['created_at'] ?? '')) ?></span>
</div>
<div class="meta-item">
<i class="fas fa-clock"></i>
<span><strong>Expires on:</strong> <?= $course['expires_at'] ? date('F j, Y', strtotime($course['expires_at'])) : 'No expiration' ?></span>
 <?php if ($isExpired): ?>
 <span class="badge">Expired</span>
<?php endif; ?>
</div>
</div>

<div class="modern-card-actions">
<?php if ($isExpired): ?>
    <!-- EXPIRED - Gray Button -->
<button class="btn-expired" disabled>
<i class="fas fa-hourglass-end"></i> Course Expired
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> This course expired on <?= date('F j, Y', strtotime($course['expires_at'])) ?>
</small>
                    
<?php elseif ($enrollStatus === 'ongoing'): ?>
    <!-- ALREADY ENROLLED - Green Button -->
<button class="btn-enrolled" disabled>
<i class="fas fa-check-circle"></i> Already Enrolled
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> You are already enrolled in this course. 
<a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $course['id'] ?>" class="text-primary">Continue Learning →</a>
</small>
                    
<?php else: ?>
    <!-- ENROLL NOW POST form - No restrictions -->
<form method="POST" style="display: inline;">
<button type="submit" name="enroll" class="btn-enroll">
<i class="fas fa-sign-in-alt"></i> Enroll Now
</button>
</form>
<?php if ($course['expires_at']): ?>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> This course expires on <?= date('F j, Y', strtotime($course['expires_at'])) ?>
</small>
<?php endif; ?>
<?php endif; ?>
</div>
</div>

<!-- Course Preview Section -->
<div class="mt-4">
<h4>Course Preview</h4>
<div class="modern-course-info-content">
<?= nl2br(htmlspecialchars($course['summary'] ?? 'No preview available.')) ?>
</div>
</div>

<!-- Kitchen accent -->
<div class="kitchen-accent">
    <i class="fas fa-cube"></i>
    <i class="fas fa-utensils"></i>
    <i class="fas fa-cube"></i>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Confirmation for enrollment
    $('button[name="enroll"]').click(function(e) {
        if (!confirm('Are you sure you want to enroll in this course?')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>