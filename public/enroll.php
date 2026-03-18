<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

// Only logged-in users can enroll
if(!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user']['id'];
$courseId = intval($_POST['course_id'] ?? 0);

if(!$courseId) {
    die('Invalid course ID.');
}

// Check if course exists and is active
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND is_active = 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if(!$course) {
    die('Course not found or inactive.');
}

// Check if user is already enrolled
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $courseId]);
$enrollment = $stmt->fetch();

if($enrollment) {
    // Already enrolled, redirect back with message
    $_SESSION['message'] = "You are already enrolled in '{$course['title']}'";
    header('Location: courses.php');
    exit;
}

// Enroll the user - REMOVED total_time_seconds
$stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_at, status, progress) VALUES (?, ?, NOW(), 'ongoing', 0)");
$stmt->execute([$userId, $courseId]);

$_SESSION['message'] = "Successfully enrolled in '{$course['title']}'";
header("Location: course_view.php?id={$courseId}");
exit;
?>