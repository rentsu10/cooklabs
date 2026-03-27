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

// ===== PAGINATION SETUP =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 4; // 4 courses per page
$offset = ($page - 1) * $limit;

// ===== SEARCH SETUP =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
FETCH COURSES WITH PAGINATION AND SEARCH
========================= */

// Build query based on user role with instructor name, search, and pagination
if (is_admin() || is_superadmin()) {
    // Get total count for admins with search
    if (!empty($search)) {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 
            AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
        ");
        $countStmt->execute([':search' => "%$search%"]);
    } else {
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses WHERE is_active = 1");
        $countStmt->execute();
    }
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Admins see all courses with search and pagination
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 
            AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $courses = $stmt->fetchAll();
} else {
    // Get total count for proponent with search
    if (!empty($search)) {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 AND c.proponent_id = :user_id 
            AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
        ");
        $countStmt->execute([':user_id' => $_SESSION['user']['id'], ':search' => "%$search%"]);
    } else {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM courses c 
            WHERE c.is_active = 1 AND c.proponent_id = :user_id
        ");
        $countStmt->execute([':user_id' => $_SESSION['user']['id']]);
    }
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Proponents see only their courses with search and pagination
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 AND c.proponent_id = :user_id
            AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, CONCAT(u.fname, ' ', u.lname) as instructor_name
            FROM courses c 
            LEFT JOIN users u ON c.proponent_id = u.id 
            WHERE c.is_active = 1 AND c.proponent_id = :user_id
            ORDER BY c.updated_at DESC, c.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $courses = $stmt->fetchAll();
}

// Calculate total pages
$totalPages = ceil($totalCount / $limit);

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
<link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<link href="../assets/css/coursecrud.css" rel="stylesheet">
<style>
/* Pagination Styles */
.pagination-container {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.pagination-nav {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.pagination-link {
    background: #1661a3;
    border: 2px solid #0c314d;
    box-shadow: 2px 2px 0 #0b263b;
    padding: 0.4rem 0.8rem;
    min-width: 36px;
    text-align: center;
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.1s ease;
    display: inline-block;
}

.pagination-link:hover {
    transform: translate(-1px, -1px);
    box-shadow: 3px 3px 0 #0b263b;
    background: #1a70b5;
    color: white;
}

.pagination-link.active {
    background: #28a745;
    border-color: #1e7e34;
    box-shadow: 2px 2px 0 #166b2c;
}

.pagination-prev, .pagination-next {
    background: #1661a3;
    border: 2px solid #0c314d;
    box-shadow: 2px 2px 0 #0b263b;
    padding: 0.4rem 1rem;
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.pagination-prev:hover, .pagination-next:hover {
    transform: translate(-1px, -1px);
    box-shadow: 3px 3px 0 #0b263b;
    background: #1a70b5;
    color: white;
}

.pagination-disabled {
    background: #6c757d;
    border-color: #5a6268;
    box-shadow: 2px 2px 0 #404040;
    padding: 0.4rem 1rem;
    color: white;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: not-allowed;
    opacity: 0.6;
}

.page-info {
    color: #1e4465;
    font-size: 0.9rem;
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
    <!-- Search Bar - Form to submit search -->
    <form method="GET" action="" style="display: flex; gap: 0.5rem; width: 100%; max-width: 350px;">
        <div class="search-container" style="width: 100%;">
            <input type="text" name="search" class="search-input" placeholder="Search by title or instructor..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-icon" style="background: #1661a3; border: none; border-left: 2px solid #1a4b77; padding: 0.7rem 1.2rem; color: white; cursor: pointer;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <?php if (!empty($search)): ?>
            <a href="courses_crud.php" class="btn-add" style="background: #6c757d; box-shadow: 3px 3px 0 #404040;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>
    
    <!-- Add Button -->
    <a href="?act=addform" class="btn-add">
        <i class="fas fa-plus"></i> Add New Course
    </a>
</div>

<!-- Divider Line -->
<hr class="section-divider">

<!-- Search Results Info -->
<?php if (!empty($search)): ?>
<div class="search-info visible">
    <span><i class="fas fa-search"></i> Search results for: <strong><?= htmlspecialchars($search) ?></strong></span>
    <span id="resultCount"><?= $totalCount ?> course(s) found</span>
</div>
<?php endif; ?>

<?php if (empty($courses)): ?>
<div class="empty-state">
    <i class="fas fa-book-open"></i>
    <h4>No Courses Found</h4>
    <p><?= !empty($search) ? 'No courses match your search criteria.' : 'Click the "Add New Course" button to create your first course.' ?></p>
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

<!-- Pagination Navigation -->
<?php if ($totalPages > 1): ?>
<div class="pagination-container">
    <div class="pagination-nav">
        <!-- Previous Button -->
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?><?= $id ? '&id=' . $id : '' ?>" class="pagination-prev">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        <?php else: ?>
            <span class="pagination-disabled">
                <i class="fas fa-chevron-left"></i> Previous
            </span>
        <?php endif; ?>
        
        <!-- Page Numbers -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="pagination-link active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?><?= $id ? '&id=' . $id : '' ?>" class="pagination-link">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <!-- Next Button -->
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?><?= $id ? '&id=' . $id : '' ?>" class="pagination-next">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="pagination-disabled">
                Next <i class="fas fa-chevron-right"></i>
            </span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
});
</script>

</body>
</html> 