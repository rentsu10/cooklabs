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

// CHECK IF USER HAS ANY ACTIVE ENROLLMENT (excluding current course)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_count 
    FROM enrollments 
    WHERE user_id = ? AND status = 'ongoing' AND course_id != ?
");
$stmt->execute([$userId, $courseId]);
$activeEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$hasActiveEnrollment = ($activeEnrollment['active_count'] > 0);

// Get the active course details if exists
$activeCourseId = null;
$activeCourseTitle = null;
if ($hasActiveEnrollment) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title 
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ? AND e.status = 'ongoing' AND e.course_id != ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $courseId]);
    $activeCourse = $stmt->fetch();
    if ($activeCourse) {
        $activeCourseId = $activeCourse['id'];
        $activeCourseTitle = $activeCourse['title'];
    }
}

// Fetch all courses with enrollment info (for other queries you might need) - REMOVED total_time_seconds
$stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description, 
        c.summary,
        c.thumbnail,
        c.created_at, 
        c.expires_at AS course_expires_at,
        CASE 
            WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
            ELSE COALESCE(e.status, 'notenrolled')
        END AS enroll_status,
        e.progress, 
        e.pages_viewed,
        e.enrolled_at,
        c.proponent_id
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's enrolled courses - REMOVED total_time_seconds
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.created_at, c.expires_at,
           e.progress, e.pages_viewed,
           CASE 
               WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
               ELSE e.status 
           END AS enroll_status
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY c.id DESC
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    // CHECK FOR ACTIVE ENROLLMENT
    if ($hasActiveEnrollment) {
        $_SESSION['error'] = "You can only be enrolled in one course at a time. Please complete or drop your current course: <strong>" . htmlspecialchars($activeCourseTitle) . "</strong>";
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
<style>
/* ===== SHARP GEOMETRIC COURSE PREVIEW ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', Arial, sans-serif;
    background: #eaf2fc;
    min-height: 100vh;
    display: flex;
}

/* Sidebar */
.lms-sidebar-container {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    z-index: 1000;
}

/* Main Content */
.course-content-wrapper {
    margin-left: 280px;
    flex: 1;
    padding: 2rem 2.5rem;
    min-height: 100vh;
    overflow-y: auto;
}

/* Course Header */
.course-header {
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.course-header h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 1rem;
    border-left: 8px solid #1d6fb0;
    padding-left: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.course-header p {
    color: #1e4465;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 0;
}

/* Expired Badge */
.expired-badge {
    background: #b71c1c;
    border: 2px solid #8a1515;
    box-shadow: 2px 2px 0 #5a0e0e;
    color: white;
    padding: 0.3rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Alerts */
.alert-success, .alert-danger {
    border: 2px solid;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-radius: 0px;
    box-shadow: 5px 5px 0 rgba(0,0,0,0.1);
}

.alert-success {
    background: #e8f5e9;
    border-color: #2e7d32;
    box-shadow: 5px 5px 0 #1b5e20;
    color: #1b5e20;
}

.alert-danger {
    background: #ffebee;
    border-color: #b71c1c;
    box-shadow: 5px 5px 0 #7a1a1a;
    color: #a11717;
}

/* Active Course Alert */
.active-course-alert {
    background: #fff9e0;
    border: 2px solid #b88f1f;
    box-shadow: 5px 5px 0 #8f6f1a;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 0px;
    color: #5f4c0e;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.active-course-alert i {
    color: #b88f1f;
    font-size: 1.2rem;
}

.active-course-link {
    color: #0f4980;
    font-weight: 700;
    text-decoration: none;
    border-bottom: 2px solid #0f4980;
}

.active-course-link:hover {
    color: #1a70b5;
    border-bottom-color: #1a70b5;
}

/* Course Info Card */
.course-info-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.course-instructor {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    margin-bottom: 1.5rem;
}

.instructor-avatar {
    width: 60px;
    height: 60px;
    background: #1d6fb0;
    border: 3px solid #0f4980;
    box-shadow: 4px 4px 0 #0a3458;
    border-radius: 0px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.instructor-info h5 {
    font-weight: 700;
    color: #07223b;
    margin-bottom: 0.3rem;
    font-size: 1.2rem;
}

.instructor-info p {
    color: #5f6f82;
    margin: 0;
    font-size: 0.9rem;
}

/* Meta Items */
.modern-course-info-meta {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 4px 4px 0 #a0c0e0;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.5rem;
    color: #1e4465;
    font-size: 0.9rem;
}

.meta-item i {
    color: #1d6fb0;
    width: 20px;
}

.meta-item .badge {
    background: #b71c1c;
    border: 1px solid #8a1515;
    box-shadow: 1px 1px 0 #5a0e0e;
    color: white;
    border-radius: 0px;
    padding: 0.2rem 0.5rem;
}

/* Buttons */
.modern-card-actions {
    margin-top: 1rem;
}

.btn-enroll {
    background: #28a745;
    border: 3px solid #1e7e34;
    box-shadow: 4px 4px 0 #166b2c;
    padding: 0.7rem 1.8rem;
    font-weight: 700;
    color: white;
    border-radius: 0px;
    transition: all 0.1s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 1rem;
}

.btn-enroll:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 #166b2c;
    background: #34ce57;
}

.btn-enrolled {
    background: #28a745;
    border: 3px solid #1e7e34;
    box-shadow: 4px 4px 0 #166b2c;
    padding: 0.7rem 1.8rem;
    font-weight: 700;
    color: white;
    border-radius: 0px;
    opacity: 0.8;
    cursor: not-allowed;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
}

.btn-expired {
    background: #6c757d;
    border: 3px solid #5a6268;
    box-shadow: 4px 4px 0 #404040;
    padding: 0.7rem 1.8rem;
    font-weight: 700;
    color: white;
    border-radius: 0px;
    opacity: 0.8;
    cursor: not-allowed;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
}

.btn-locked {
    background: #ffc107;
    border: 3px solid #b88f1f;
    box-shadow: 4px 4px 0 #8f6f1a;
    padding: 0.7rem 1.8rem;
    font-weight: 700;
    color: #07223b;
    border-radius: 0px;
    opacity: 0.8;
    cursor: not-allowed;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
}

.text-primary {
    color: #1d6fb0;
    text-decoration: none;
    font-weight: 600;
}

.text-primary:hover {
    text-decoration: underline;
}

/* Course Preview Section */
.mt-4 h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 1rem;
    border-left: 6px solid #1d6fb0;
    padding-left: 1rem;
}

.modern-course-info-content {
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 8px 8px 0 #123a5e;
    border-radius: 0px;
    padding: 1.5rem;
    color: #1e4465;
    line-height: 1.8;
    font-size: 1rem;
}

/* Kitchen accent */
.kitchen-accent {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
    opacity: 0.4;
}

.kitchen-accent i {
    color: #1d6fb0;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .course-content-wrapper {
        margin-left: 0;
        padding: 1rem;
    }
    .lms-sidebar-container {
        position: relative;
        width: 100%;
        height: auto;
    }
}
</style>
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

    <!-- Warning if user is already enrolled in another course -->
<?php if ($hasActiveEnrollment && $enrollStatus !== 'ongoing' && !$isExpired): ?>
<div class="active-course-alert">
<i class="fas fa-info-circle fa-lg"></i> 
<div>
<strong>You have an active enrollment:</strong> 
You are currently enrolled in <a href="course_preview.php?id=<?= $activeCourseId ?>" class="active-course-link"><?= htmlspecialchars($activeCourseTitle) ?></a>. 
You can only be enrolled in one course at a time. Please complete your current course before enrolling in a new one.
</div>
</div>
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
                    
<?php elseif ($hasActiveEnrollment): ?>
    <!-- LOCKED - Yellow Button (has other active enrollment) -->
<button class="btn-locked" disabled>
<i class="fas fa-lock"></i> Enrollment Locked
</button>
<small class="text-muted d-block mt-2">
<i class="fas fa-info-circle"></i> You are currently enrolled in 
<a href="course_preview.php?id=<?= $activeCourseId ?>" class="text-primary"><?= htmlspecialchars($activeCourseTitle) ?></a>. 
Complete that course first.
</small>
                    
<?php else: ?>
    <!-- ENROLL NOW POST form -->
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
        if (!confirm('Are you sure you want to enroll in this course? You can only be enrolled in one course at a time.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>