<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
http_response_code(403);
exit('Access denied');
}

$act = $_GET['act'] ?? '';

// ===== PAGINATION SETUP =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 4; // 4 courses per page
$offset = ($page - 1) * $limit;

// ===== SEARCH SETUP =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

/* =========================
FETCH ALL COURSES WITH USER INFO WITH PAGINATION AND SEARCH
========================= */

// Get total count for pagination with search
if (!empty($search)) {
    $countSql = "
        SELECT COUNT(*) as total
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.is_active = 1 
        AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':search' => "%$search%"]);
} else {
    $countSql = "
        SELECT COUNT(*) as total
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.is_active = 1
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
}
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Build query for all courses with user full name with pagination and search
if (!empty($search)) {
    $sql = "
        SELECT c.*, 
               CONCAT(u.fname, ' ', u.lname) as proponent_fullname,
               CASE WHEN c.proponent_id = :user_id THEN 1 ELSE 0 END as is_owner
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.is_active = 1 
        AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
} else {
    $sql = "
        SELECT c.*, 
               CONCAT(u.fname, ' ', u.lname) as proponent_fullname,
               CASE WHEN c.proponent_id = :user_id THEN 1 ELSE 0 END as is_owner
        FROM courses c 
        LEFT JOIN users u ON c.proponent_id = u.id 
        WHERE c.is_active = 1
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user']['id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$courses = $stmt->fetchAll();

// Calculate total pages
$totalPages = ceil($totalCount / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Courses - CookLabs LMS</title>
<link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/allcourses.css">
</head>
<body>

<div class="lms-sidebar-container">
<?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="modern-courses-wrapper">

<!-- Page Title -->
<div class="page-title">All Courses
</div>

<!-- Controls Bar - Search Bar Only (right-aligned) -->
<div class="controls-bar">
    <form method="GET" action="" style="display: flex; gap: 0.5rem; width: 100%; max-width: 350px;">
        <div class="search-container" style="width: 100%;">
            <input type="text" name="search" class="search-input" placeholder="Search by title or instructor..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-icon" style="background: #1661a3; border: none; border-left: 2px solid #1a4b77; padding: 0.7rem 1.2rem; color: white; cursor: pointer;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <?php if (!empty($search)): ?>
            <a href="all_course.php" class="btn-add" style="background: #6c757d; box-shadow: 3px 3px 0 #404040; text-decoration: none; padding: 0.6rem 1.2rem; color: white; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>
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

<!-- Course Grid -->
<div class="course-grid" id="courseGrid">
    <?php if (empty($courses)): ?>
    <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <h4><?= !empty($search) ? 'No Matching Courses' : 'No Courses Available' ?></h4>
        <p><?= !empty($search) ? 'No courses match your search criteria.' : 'There are no courses to display at the moment.' ?></p>
    </div>
    <?php else: ?>
        <?php foreach ($courses as $c): 
            // Check if course is expired
            $isExpired = false;
            if (!empty($c['expires_at']) && $c['expires_at'] != '0000-00-00') {
                $isExpired = strtotime($c['expires_at']) < time();
            }
        ?>
        <div class="course-card <?= $isExpired ? 'expired' : '' ?>" 
             data-title="<?= strtolower(htmlspecialchars($c['title'])) ?>"
             data-instructor="<?= strtolower(htmlspecialchars($c['proponent_fullname'] ?? '')) ?>">
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
                    <?php if (!empty($c['proponent_fullname'])): ?>
                    <div class="instructor">
                        <i class="fas fa-chalkboard-teacher"></i> Instructor: <?= htmlspecialchars($c['proponent_fullname']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($c['is_owner']) && $c['is_owner']): ?>
                    <div class="owner-indicator">
                        <i class="fas fa-check-circle"></i> Your Course
                    </div>
                    <?php endif; ?>
                </div>

                <div class="action-group">
                    <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $c['id'] ?>" class="btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination Navigation -->
<?php if ($totalPages > 1 && !empty($courses)): ?>
<div class="pagination-container">
    <div class="pagination-nav">
        <!-- Previous Button -->
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?>" class="pagination-prev">
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
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?>" class="pagination-link">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <!-- Next Button -->
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?><?= $act ? '&act=' . $act : '' ?>" class="pagination-next">
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

<!-- Empty State for search (hidden by default) - REMOVED since we now use server-side search -->
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
    // No client-side search needed anymore - search is handled server-side
});
</script>

</body>
</html>