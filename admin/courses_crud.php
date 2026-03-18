<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

// Add this at the top to load the PDF parser
require_once __DIR__ . '/../vendor/autoload.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
http_response_code(403);
exit('Access denied');
}

$act = $_GET['act'] ?? '';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;


// Check if updated_at column exists and add it if not
try {
$pdo->query("SELECT updated_at FROM courses LIMIT 1");
} catch (Exception $e) {
// Column doesn't exist, add it
try {
$pdo->exec("ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
} catch (Exception $e) {
// Column might already exist
}
}

/**
 * Calculate expiration date
 */
function calculateExpiry($expires_at, $valid_days) {
if (!empty($valid_days) && is_numeric($valid_days) && $valid_days > 0) {
return date('Y-m-d', strtotime("+{$valid_days} days"));
}
return !empty($expires_at) ? $expires_at : null;
}


/**
 * Handle file upload with PDF page counting
 */
function uploadFile($input, $dir, $allowed = []) {
if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
return null;
}

$ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
if ($allowed && !in_array($ext, $allowed)) {
return null;
}

$filename = bin2hex(random_bytes(8)) . '.' . $ext;
$upload_dir = __DIR__ . "/../uploads/$dir/";

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
mkdir($upload_dir, 0777, true);
}

$filepath = $upload_dir . $filename;

if (move_uploaded_file($_FILES[$input]['tmp_name'], $filepath)) {
    
    // If this is a PDF, return both filename and page count
    if ($ext === 'pdf') {
        $totalPages = countPdfPages($filepath);
        return [
            'filename' => $filename,
            'total_pages' => $totalPages
        ];
    }
    
    return $filename;
}

return null;
}

/**
 * Check if current user can edit/delete course
 * Returns true for admins OR if user owns the course
 */
function canModifyCourse($course_id, $pdo) {
if (is_admin() || is_superadmin()) {
return true;
}

$stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = :id");
$stmt->execute([':id' => $course_id]);
$course = $stmt->fetch();

return $course && $course['proponent_id'] == $_SESSION['user']['id'];
}

/* =========================
ADD COURSE
========================= */
if ($act === 'addform' && $_SERVER['REQUEST_METHOD'] === 'POST') {

$expires_at = calculateExpiry(
$_POST['expires_at'] ?? null,
$_POST['valid_days'] ?? null
);

// Handle thumbnail upload
$thumbnail = uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']);

// Handle PDF upload with page counting
$pdfResult = uploadFile('file_pdf', 'pdf', ['pdf']);
$pdfFilename = is_array($pdfResult) ? $pdfResult['filename'] : $pdfResult;
$totalPages = is_array($pdfResult) ? $pdfResult['total_pages'] : 0;

$stmt = $pdo->prepare("
INSERT INTO courses (
title, description, summary, thumbnail, file_pdf, total_pages,
proponent_id, created_at, expires_at, is_active
) VALUES (
:title, :description, :summary, :thumbnail, :pdf, :total_pages,
:proponent_id, NOW(), :expires_at, 1
)
");

$stmt->execute([
':title'         => $_POST['title'],
':description'   => $_POST['description'],
':summary'       => $_POST['summary'],
':thumbnail'     => $thumbnail,
':pdf'           => $pdfFilename,
':total_pages'   => $totalPages,
':proponent_id'  => $_SESSION['user']['id'],
':expires_at'    => $expires_at
]);

header('Location: courses_crud.php');
exit;
}

/* =========================
EDIT COURSE
========================= */
if ($act === 'edit' && $id) {

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
$stmt->execute([':id' => $id]);
$course = $stmt->fetch();

if (!$course) {
exit('Course not found');
}

// Check if user can edit this course
if (!canModifyCourse($id, $pdo)) {
http_response_code(403);
exit('Access denied: You can only edit your own courses');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$expires_at = calculateExpiry(
$_POST['expires_at'] ?? null,
$_POST['valid_days'] ?? null
);

// Handle file uploads
$thumbnail = uploadFile('thumbnail', 'images', ['jpg','jpeg','png','webp']);
$pdfResult = uploadFile('file_pdf', 'pdf', ['pdf']);

// Build the SQL dynamically
$sql = "
UPDATE courses SET
title       = :title,
description = :description,
summary     = :summary,
expires_at  = :expires_at,
thumbnail   = COALESCE(:thumbnail, thumbnail)";

$params = [
':title'       => $_POST['title'],
':description' => $_POST['description'],
':summary'     => $_POST['summary'],
':expires_at'  => $expires_at,
':thumbnail'   => $thumbnail,
':id'          => $id
];

// If a new PDF was uploaded, update it and the page count
if ($pdfResult !== null) {
    $pdfFilename = is_array($pdfResult) ? $pdfResult['filename'] : $pdfResult;
    $totalPages = is_array($pdfResult) ? $pdfResult['total_pages'] : 0;
    
    $sql .= ", file_pdf = :pdf, total_pages = :total_pages";
    $params[':pdf'] = $pdfFilename;
    $params[':total_pages'] = $totalPages;
}

$sql .= ", updated_at = NOW() WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Location: courses_crud.php');
exit;
}
}

/* =========================
DELETE COURSE
========================= */
if ($act === 'delete' && $id) {
// Check if user can delete this course
if (!canModifyCourse($id, $pdo)) {
http_response_code(403);
exit('Access denied: You can only delete your own courses');
}

$stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
$stmt->execute([':id' => $id]);
header('Location: courses_crud.php');
exit;
}

/* =========================
FETCH COURSES WITH UPDATED AT AND INSTRUCTOR NAME
========================= */

// Build query based on user role with instructor name
if (is_admin() || is_superadmin()) {
// Admins see all courses
$stmt = $pdo->query("
SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
FROM courses c 
LEFT JOIN users u ON c.proponent_id = u.id 
ORDER BY c.updated_at DESC, c.created_at DESC
");
} else {
// Proponents see only their courses
$stmt = $pdo->prepare("
SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
FROM courses c 
LEFT JOIN users u ON c.proponent_id = u.id 
WHERE c.proponent_id = :user_id
ORDER BY c.updated_at DESC, c.created_at DESC
");
$stmt->execute([':user_id' => $_SESSION['user']['id']]);
}
$courses = $stmt->fetchAll();

// Helper function for role-based access
function canCreateAssessment($course_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT proponent_id FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    return $course && $course['proponent_id'] == $_SESSION['user']['id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Course Management - CookLabs LMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<style>
/* ===== SHARP GEOMETRIC COURSE MANAGEMENT ===== */
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
.modern-courses-wrapper {
    margin-left: 280px;
    flex: 1;
    padding: 2rem 2.5rem;
    min-height: 100vh;
    overflow-y: auto;
}

/* Page Header - No icon */
.modern-courses-wrapper > h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 2rem;
    border-left: 8px solid #1d6fb0;
    padding-left: 1.2rem;
}

/* Controls Bar - Right aligned */
.controls-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

/* Search Bar */
.search-container {
    display: flex;
    align-items: center;
    background: white;
    border: 2px solid #1a4b77;
    box-shadow: 4px 4px 0 #123a5e;
    border-radius: 0px;
    overflow: hidden;
    width: 350px;
}

.search-input {
    flex: 1;
    padding: 0.7rem 1rem;
    border: none;
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    outline: none;
    color: #07223b;
}

.search-input::placeholder {
    color: #5f6f82;
    opacity: 0.7;
}

.search-icon {
    background: #1661a3;
    border: none;
    border-left: 2px solid #1a4b77;
    padding: 0.7rem 1.2rem;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Add Button */
.btn-add {
    background: #1661a3;
    border: 3px solid #0c314d;
    box-shadow: 5px 5px 0 #0b263b;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    color: white;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 0.95rem;
}

.btn-add:hover {
    transform: translate(-2px, -2px);
    box-shadow: 7px 7px 0 #0b263b;
    background: #1a70b5;
    color: white;
}

/* Divider Line */
.section-divider {
    border: 0;
    height: 3px;
    background: #1d6fb0;
    box-shadow: 2px 2px 0 #0f4980;
    margin-bottom: 2rem;
}

/* Search Results Info */
.search-info {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 4px 4px 0 #a0c0e0;
    padding: 0.8rem 1.2rem;
    margin-bottom: 1.5rem;
    display: none;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: #1e4465;
}

.search-info strong {
    color: #07223b;
}

.search-info.visible {
    display: flex;
}

/* Form Card */
.form-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 16px 16px 0 #123a5e;
    border-radius: 0px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.form-card h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 1.5rem;
    border-left: 6px solid #1d6fb0;
    padding-left: 1rem;
}

.form-label {
    font-weight: 600;
    color: #0a314b;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
    display: block;
}

.form-control {
    width: 100%;
    padding: 0.7rem 1rem;
    font-family: 'Inter', Arial, sans-serif;
    font-size: 0.95rem;
    border: 2px solid #1d6fb0;
    background: white;
    border-radius: 0px;
    transition: all 0.1s ease;
    outline: none;
    margin-bottom: 1rem;
}

.form-control:focus {
    border-color: #0f4980;
    background: #f0f8ff;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

/* Date Row */
.date-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.date-row .form-group {
    flex: 1;
}

/* Current Files */
.current-file {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 4px 4px 0 #a0c0e0;
    padding: 0.8rem;
    margin-top: 0.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.current-file img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border: 2px solid #1a4b77;
}

.current-file a {
    color: #1d6fb0;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 2px solid transparent;
}

.current-file a:hover {
    border-bottom-color: #1d6fb0;
}

.file-note {
    font-size: 0.8rem;
    color: #5f6f82;
    margin-left: 0.5rem;
}

/* Button Group */
.btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.btn-primary {
    background: #1661a3;
    border: 3px solid #0c314d;
    box-shadow: 4px 4px 0 #0b263b;
    padding: 0.7rem 1.8rem;
    font-weight: 600;
    color: white;
    border-radius: 0px;
    transition: all 0.1s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-primary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 #0b263b;
    background: #1a70b5;
}

.btn-secondary {
    background: white;
    border: 3px solid #0f3d5e;
    box-shadow: 4px 4px 0 #123a57;
    padding: 0.7rem 1.8rem;
    font-weight: 600;
    color: #0a314b;
    border-radius: 0px;
    transition: all 0.1s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 #123a57;
    background: #f0f8ff;
}

.btn-info {
    background: #8e44ad;
    border: 3px solid #6c3483;
    box-shadow: 4px 4px 0 #4a235a;
    padding: 0.7rem 1.8rem;
    font-weight: 600;
    color: white;
    border-radius: 0px;
    transition: all 0.1s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
}

.btn-info:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 #4a235a;
    background: #a569bd;
}

/* Course Grid */
.course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 0.5rem;
}

/* Course Card - Fixed height */
.course-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    border-top: none;
    box-shadow: 8px 8px 0 #123a5e;
    border-radius: 0px;
    overflow: hidden;
    transition: all 0.1s ease;
    height: 500px;
    display: flex;
    flex-direction: column;
}

.course-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 10px 10px 0 #123a5e;
}

/* Thumbnail Container - Fixed height */
.course-card-img {
    height: 200px;
    background: #d7e9ff;
    border-bottom: 3px solid #1a4b77;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.course-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Card Body - Takes remaining space */
.course-card-body {
    padding: 1.2rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}

/* Title - Exactly 2 lines max */
.course-card-title {
    margin-bottom: 0.5rem;
    height: 2.8rem;
    overflow: hidden;
}

.course-card-title h6 {
    font-weight: 700;
    color: #07223b;
    font-size: 1.1rem;
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Description - Exactly 2 lines max */
.course-card-body p {
    color: #1e4465;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 0.8rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    height: 2.4rem;
}

/* Course Info - Fixed height */
.course-info {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    padding: 0.8rem;
    margin-bottom: 0.8rem;
    flex-shrink: 0;
    height: 95px;
}

.course-info p {
    margin-bottom: 0.3rem;
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
    height: auto;
    -webkit-line-clamp: 1;
    overflow: visible;
}

.course-info .instructor {
    color: #1d6fb0;
    font-weight: 600;
    font-size: 0.8rem;
    margin-top: 0.2rem;
    padding-top: 0.2rem;
    border-top: 1px dashed #b8d6f5;
}

.course-info .instructor i {
    color: #1d6fb0;
    margin-right: 4px;
}

/* Action Buttons - Fixed at bottom */
.action-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    margin-top: auto;
    padding-top: 0.5rem;
    flex-shrink: 0;
}

.btn-view {
    background: #1661a3;
    border: 2px solid #0c314d;
    box-shadow: 3px 3px 0 #0b263b;
    color: white;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-view:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #0b263b;
    background: #1a70b5;
    color: white;
}

.btn-edit-course {
    background: #ffc107;
    border: 2px solid #b88f1f;
    box-shadow: 3px 3px 0 #8f6f1a;
    color: #07223b;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-edit-course:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #8f6f1a;
    background: #ffca2c;
}

.btn-delete-course {
    background: #b71c1c;
    border: 2px solid #8a1515;
    box-shadow: 3px 3px 0 #5a0e0e;
    color: white;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-delete-course:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #5a0e0e;
    background: #c62828;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    color: #b8d6f5;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #07223b;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #5f6f82;
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
    .modern-courses-wrapper {
        margin-left: 0;
        padding: 1rem;
    }
    .lms-sidebar-container {
        position: relative;
        width: 100%;
        height: auto;
    }
    .date-row {
        flex-direction: column;
        gap: 0;
    }
    .controls-bar {
        flex-direction: column;
        align-items: flex-end;
    }
    .search-container {
        width: 100%;
    }
    .btn-add {
        width: 100%;
        justify-content: center;
    }
}

/* Course Card Image with relative positioning for ribbon */
.course-card-img {
    height: 200px;
    background: #d7e9ff;
    border-bottom: 3px solid #1a4b77;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative; /* Added for ribbon positioning */
}

.course-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* RIBBON STYLES - Full width strip between thumbnail and title */
.course-ribbon {
    width: 100%;
    padding: 0.25rem 0;
    text-align: center;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 2px solid;
    border-top: 2px solid;
    flex-shrink: 0;
}

.ribbon-expired {
    background: #6c757d;
    border-color: #5a6268;
    color: white;
}

/* Adjust card layout to accommodate ribbon */
.course-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    border-top: none;
    box-shadow: 8px 8px 0 #123a5e;
    border-radius: 0px;
    overflow: hidden;
    transition: all 0.1s ease;
    height: 530px; /* Slightly increased to accommodate ribbon */
    display: flex;
    flex-direction: column;
}

/* Optional: Keep the expired border color if you want both indicators */
.course-card.expired {
    border-color: #565656;
    box-shadow: 8px 8px 0 #3e3e3e;
}

.course-card.expired:hover {
    box-shadow: 10px 10px 0 #b02a37;
}

</style>
</head>
<body>

<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">
<h3>Course Management</h3>

<?php if ($act === 'addform' || $act === 'edit'): ?>
<?php $editing = ($act === 'edit'); ?>

<div class="form-card">
<h4><?= $editing ? 'Edit Course' : 'Create New Course' ?></h4>
<form method="post" enctype="multipart/form-data">
    
    <div class="form-group">
        <label class="form-label">Course Title</label>
        <input type="text" name="title" class="form-control" placeholder="Enter course title" required
               value="<?= $editing ? htmlspecialchars($course['title']) : '' ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Course Description</label>
        <input type="text" name="description" class="form-control" placeholder="Brief description" required
               value="<?= $editing ? htmlspecialchars($course['description']) : '' ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Course Summary</label>
        <textarea name="summary" class="form-control" placeholder="Detailed summary of the course..." required><?= $editing ? htmlspecialchars($course['summary']) : '' ?></textarea>
    </div>

    <!-- Date fields -->
    <div class="date-row">
        <div class="form-group">
            <label class="form-label">Expiration Date</label>
            <input type="date" name="expires_at" id="expires_at" class="form-control"
                   value="<?= $editing && $course['expires_at'] ? $course['expires_at'] : '' ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Validity (Days)</label>
            <input type="number" name="valid_days" id="valid_days" class="form-control"
                   placeholder="e.g., 30"
                   value="<?= ($editing && !empty($course['expires_at']))
                        ? max(0, (int) ceil((strtotime($course['expires_at']) - time()) / 86400))
                        : '' ?>">
            <small class="file-note">Auto-calculated from date</small>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Thumbnail Image</label>
        <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
        <?php if ($editing && $course['thumbnail']): ?>
            <div class="current-file">
                <img src="<?= BASE_URL ?>/uploads/images/<?= $course['thumbnail'] ?>" alt="Thumbnail">
                <span class="file-note">Leave empty to keep current image</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label">PDF Material</label>
        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
        <?php if ($editing && $course['file_pdf']): ?>
            <div class="current-file">
                <a href="<?= BASE_URL ?>/uploads/pdf/<?= $course['file_pdf'] ?>" target="_blank">
                    <i class="fas fa-file-pdf"></i> View Current PDF
                </a>
                <span class="file-note">Leave empty to keep current file</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="btn-group">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> <?= $editing ? 'Update Course' : 'Create Course' ?>
        </button>

        <?php if ($editing && canCreateAssessment($id, $pdo)): ?>
            <?php
            // Check if assessment already exists for this course
            $stmt = $pdo->prepare("SELECT id FROM assessments WHERE course_id = ?");
            $stmt->execute([$id]);
            $hasAssessment = $stmt->fetch();
            ?>
            
            <?php if ($hasAssessment): ?>
                <a href="../admin/assessment_crud.php?course_id=<?= $id ?>" class="btn-info" style="background: #ffc107; border-color: #b88f1f; color: #07223b; box-shadow: 4px 4px 0 #8f6f1a;">
                    <i class="fas fa-edit"></i> Update Assessment
                </a>
            <?php else: ?>
                <a href="../admin/assessment_crud.php?course_id=<?= $id ?>" class="btn-info">
                    <i class="fas fa-file-alt"></i> Create Assessment
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <a href="courses_crud.php" class="btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>
</div>

<?php else: ?>

<!-- Controls Bar - Search and Add Button (right-aligned) -->
<div class="controls-bar">
    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" id="searchInput" class="search-input" placeholder="Search by title or instructor...">
        <span class="search-icon"><i class="fas fa-search"></i></span>
    </div>
    
    <!-- Add Button -->
    <a href="?act=addform" class="btn-add">
        <i class="fas fa-plus"></i> Add New Course
    </a>
</div>

<!-- Divider Line -->
<hr class="section-divider">

<!-- Search Results Info -->
<div class="search-info" id="searchInfo">
    <span><i class="fas fa-search"></i> Search results for: <strong id="searchTerm"></strong></span>
    <span id="resultCount"></span>
</div>

<?php if (empty($courses)): ?>
<div class="empty-state">
    <i class="fas fa-book-open"></i>
    <h4>No Courses Yet</h4>
    <p>Click the "Add New Course" button to create your first course.</p>
</div>
<?php else: ?>
<!-- Course Grid -->
<div class="course-grid" id="courseGrid">
    <?php foreach ($courses as $c): 
        // Check if course is expired
        $isExpired = false;
        if (!empty($c['expires_at']) && $c['expires_at'] != '0000-00-00') {
            $isExpired = strtotime($c['expires_at']) < time();
        }
    ?>
    <div class="course-card <?= $isExpired ? 'expired' : '' ?>" 
         data-title="<?= strtolower(htmlspecialchars($c['title'])) ?>"
         data-instructor="<?= strtolower(htmlspecialchars($c['instructor_name'] ?? '')) ?>">
        <div class="course-card-img">
            <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course">
        </div>
        
        <!-- EXPIRED Ribbon - Full width strip -->
        <?php if ($isExpired): ?>
        <div class="course-ribbon ribbon-expired">
            <i class="fas fa-hourglass-end"></i> Expired
        </div>
        <?php else: ?>
        <!-- Spacer to maintain consistent height -->
        <div style="height: 31px; flex-shrink: 0;"></div>
        <?php endif; ?>
        
        <div class="course-card-body">
            <div class="course-card-title">
                <h6><?= htmlspecialchars(substr($c['title'], 0, 50)) ?><?= strlen($c['title']) > 50 ? '...' : '' ?></h6>
            </div>
            <p><?= htmlspecialchars(substr($c['description'], 0, 50)) ?><?= strlen($c['description']) > 50 ? '...' : '' ?></p>

            <div class="course-info">
                <p><strong>Start:</strong> <span><?= date('M d, Y', strtotime($c['created_at'])) ?></span></p>
                <p><strong>Expires:</strong> 
                    <span><?= $c['expires_at'] ? date('M d, Y', strtotime($c['expires_at'])) : 'No expiry' ?></span>
                </p>
                <?php if (!empty($c['instructor_name'])): ?>
                <div class="instructor">
                    <i class="fas fa-chalkboard-teacher"></i> Instructor: <?= htmlspecialchars($c['instructor_name']) ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="action-group">
                <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="btn-view">
                    <i class="fas fa-eye"></i> View
                </a>

                <?php if (canModifyCourse($c['id'], $pdo)): ?>
                    <a href="?act=edit&id=<?= $c['id'] ?>" class="btn-edit-course">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?act=delete&id=<?= $c['id'] ?>" class="btn-delete-course" 
                       onclick="return confirm('Delete this course? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Empty State for search (hidden by default) -->
<div class="empty-state" id="emptyState" style="display: none;">
    <i class="fas fa-search"></i>
    <h4>No Matching Courses</h4>
    <p id="emptyStateMessage">No courses match your search criteria.</p>
</div>

<?php endif; ?>

<?php endif; ?>

<!-- Kitchen accent -->
<div class="kitchen-accent">
    <i class="fas fa-cube"></i>
    <i class="fas fa-utensils"></i>
    <i class="fas fa-cube"></i>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const expires = document.getElementById('expires_at');
    const days = document.getElementById('valid_days');

    if (expires && days) {
        expires.addEventListener('change', function () {
            if (!this.value) {
                days.value = '';
                return;
            }

            const today = new Date();
            today.setHours(0,0,0,0);

            const exp = new Date(this.value);
            exp.setHours(0,0,0,0);

            const diff = Math.ceil((exp - today) / (1000 * 60 * 60 * 24));
            days.value = diff >= 0 ? diff : 0;
        });
    }

    // Real-time search functionality
    const searchInput = document.getElementById('searchInput');
    const courseCards = document.querySelectorAll('.course-card');
    const courseGrid = document.getElementById('courseGrid');
    const emptyState = document.getElementById('emptyState');
    const searchInfo = document.getElementById('searchInfo');
    const searchTermSpan = document.getElementById('searchTerm');
    const resultCountSpan = document.getElementById('resultCount');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            // Update search info
            if (searchTerm.length > 0) {
                searchInfo.classList.add('visible');
                searchTermSpan.textContent = this.value;
            } else {
                searchInfo.classList.remove('visible');
            }
            
            // Filter courses
            courseCards.forEach(card => {
                const title = card.dataset.title || '';
                const instructor = card.dataset.instructor || '';
                
                if (title.includes(searchTerm) || instructor.includes(searchTerm)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update result count
            resultCountSpan.textContent = visibleCount + ' course(s) found';
            
            // Show/hide empty state
            if (visibleCount === 0 && searchTerm.length > 0) {
                emptyState.style.display = 'block';
                if (courseGrid) courseGrid.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                if (courseGrid) courseGrid.style.display = 'grid';
            }
            
            // If search is cleared, show all
            if (searchTerm.length === 0) {
                courseCards.forEach(card => {
                    card.style.display = 'flex';
                });
                if (courseGrid) courseGrid.style.display = 'grid';
                emptyState.style.display = 'none';
            }
        });
    }
});
</script>

</body>
</html>