<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$isAdmin = is_admin();
$isProponent = is_proponent();

// ===== PAGINATION SETUP =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 4; // 4 courses per page
$offset = ($page - 1) * $limit;

// ===== SEARCH SETUP =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get total count for pagination with search (EXCLUDE ALL ENROLLED COURSES - INCLUDING ARCHIVED)
if (!empty($search)) {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM courses c
        WHERE c.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM enrollments e 
            WHERE e.course_id = c.id 
            AND e.user_id = :user_id
        )
        AND (c.title LIKE :search OR EXISTS (
            SELECT 1 FROM users u WHERE u.id = c.proponent_id AND CONCAT(u.fname, ' ', u.lname) LIKE :search
        ))
    ");
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $countStmt->execute();
} else {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM courses c
        WHERE c.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM enrollments e 
            WHERE e.course_id = c.id 
            AND e.user_id = :user_id
        )
    ");
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
}
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get only courses that the user has NEVER enrolled in (including archived)
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.summary,
            c.thumbnail,
            c.created_at, 
            c.expires_at AS course_expires_at,
            c.total_pages,
            c.proponent_id,
            u.fname as proponent_fname,
            u.lname as proponent_lname,
            CONCAT(u.fname, ' ', u.lname) as proponent_fullname,
            -- Check if course has assessment
            CASE 
                WHEN a.id IS NOT NULL THEN 1 
                ELSE 0 
            END AS has_assessment,
            a.id as assessment_id,
            a.passing_score,
            'notenrolled' AS enroll_status,
            NULL AS progress,
            NULL AS pages_viewed,
            NULL AS enrolled_at,
            NULL AS completed_at,
            NULL AS latest_assessment_score,
            0 AS assessment_passed,
            'notenrolled' AS display_status
        FROM courses c
        LEFT JOIN users u ON c.proponent_id = u.id
        LEFT JOIN assessments a ON a.course_id = c.id
        WHERE c.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM enrollments e 
            WHERE e.course_id = c.id 
            AND e.user_id = :user_id
        )
        AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
        GROUP BY c.id
        ORDER BY c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.summary,
            c.thumbnail,
            c.created_at, 
            c.expires_at AS course_expires_at,
            c.total_pages,
            c.proponent_id,
            u.fname as proponent_fname,
            u.lname as proponent_lname,
            CONCAT(u.fname, ' ', u.lname) as proponent_fullname,
            -- Check if course has assessment
            CASE 
                WHEN a.id IS NOT NULL THEN 1 
                ELSE 0 
            END AS has_assessment,
            a.id as assessment_id,
            a.passing_score,
            'notenrolled' AS enroll_status,
            NULL AS progress,
            NULL AS pages_viewed,
            NULL AS enrolled_at,
            NULL AS completed_at,
            NULL AS latest_assessment_score,
            0 AS assessment_passed,
            'notenrolled' AS display_status
        FROM courses c
        LEFT JOIN users u ON c.proponent_id = u.id
        LEFT JOIN assessments a ON a.course_id = c.id
        WHERE c.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM enrollments e 
            WHERE e.course_id = c.id 
            AND e.user_id = :user_id
        )
        GROUP BY c.id
        ORDER BY c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total pages
$totalPages = ceil($totalCount / $limit);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Courses - CookLabs LMS</title>
<link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/course.css">
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
    <h2 class="modern-section-title">All Courses</h2>

    <!-- Controls Bar - Search Bar only -->
    <div class="controls-bar">
        <form method="GET" action="" style="display: flex; gap: 0.5rem; width: 100%; max-width: 350px;">
            <div class="search-container" style="width: 100%;">
                <input type="text" name="search" class="search-input" placeholder="Search by title or instructor..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-icon" style="background: #1661a3; border: none; border-left: 2px solid #1a4b77; padding: 0.7rem 1.2rem; color: white; cursor: pointer;">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <?php if (!empty($search)): ?>
                <a href="courses.php" class="btn-add" style="background: #6c757d; box-shadow: 3px 3px 0 #404040; text-decoration: none; padding: 0.6rem 1.2rem; color: white; display: inline-flex; align-items: center; gap: 6px;">
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

    <?php if (empty($courses)): ?>
    <div class="alert-info">
        <i class="fas fa-book-open"></i>
        <h4><?= !empty($search) ? 'No Matching Courses' : 'No Courses Available' ?></h4>
        <p><?= !empty($search) ? 'No courses match your search criteria.' : 'Check back later for new courses.' ?></p>
    </div>
    <?php else: ?>
    <div class="modern-courses-grid" id="courseGrid">
        <?php foreach ($courses as $c): 
            // Check if course is expired
            $isExpired = false;
            if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
                $isExpired = strtotime($c['course_expires_at']) < time();
            }
            
            // Determine ribbon style - only show expired ribbon for expired courses
            $showRibbon = false;
            $ribbonClass = '';
            $ribbonText = '';
            $ribbonIcon = '';
            
            if ($isExpired) {
                $showRibbon = true;
                $ribbonClass = 'ribbon-expired';
                $ribbonText = 'Expired';
                $ribbonIcon = 'fa-hourglass-end';
            }
        ?>
        
        <div class="modern-course-card" 
             data-title="<?= strtolower(htmlspecialchars($c['title'] ?? '')) ?>"
             data-instructor="<?= strtolower(htmlspecialchars($c['proponent_fullname'] ?? '')) ?>">
            <div class="modern-card-img">
                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" 
                     alt="<?= htmlspecialchars($c['title']) ?>"
                     onerror="this.src='<?= BASE_URL ?>/uploads/images/Course Image.png'">
                
                <!-- lock overlay for expired courses -->
                <?php if ($isExpired): ?>
                <div class="lock-overlay">
                    <i class="fas fa-lock"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIBBON -->
            <?php if ($showRibbon): ?>
            <div class="course-ribbon <?= $ribbonClass ?>">
                <i class="fas <?= $ribbonIcon ?>"></i> <?= $ribbonText ?>
            </div>
            <?php else: ?>
            <!-- Spacer for no ribbon -->
            <div style="height: 31px; flex-shrink: 0;"></div>
            <?php endif; ?>

            <div class="modern-card-body">
                <div class="modern-card-title">
                    <h6><?= htmlspecialchars(substr($c['title'], 0, 50)) ?><?= strlen($c['title']) > 50 ? '...' : '' ?></h6>
                </div>
                <p><?= htmlspecialchars(substr($c['description'], 0, 50)) ?><?= strlen($c['description']) > 50 ? '...' : '' ?></p>
                
                <!-- Course Info -->
                <div class="modern-course-info">
                    <?php
                    
                    $expiryDate = 'No expiry';
                    if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
                        $expiryDate = date('M d, Y', strtotime($c['course_expires_at']));
                    }
                    ?>
                    <p><strong><i class="fas fa-hourglass-half"></i> Expires:</strong> <span><?= $expiryDate ?></span></p>
                    
                    <!-- Show instructor name -->
                    <?php if (!empty($c['proponent_fullname'])): ?>
                    <p><strong><i class="fas fa-chalkboard-teacher"></i> Instructor:</strong> <span><?= htmlspecialchars($c['proponent_fullname']) ?></span></p>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="modern-card-actions">
                    <!-- PREVIEW BUTTON - Always show -->
                    <a href="<?= BASE_URL ?>/public/course_preview.php?id=<?= $c['id'] ?>"
                       class="modern-btn-warning modern-btn-sm"
                       title="Preview course content">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                    
                    <?php if ($isExpired): ?>
                        <!-- EXPIRED COURSE - Show info icon -->
                        <span class="tooltip-icon-ex" title="This course has expired">
                            <i class="fas fa-info-circle"></i>
                        </span>
                        
                    <?php else: ?>
                        <!-- NOT ENROLLED - Show Enroll button -->
                        <form method="POST" action="<?= BASE_URL ?>/public/enroll.php" style="display: inline;">
                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="modern-btn-primary modern-btn-sm" 
                                onclick="return confirm('Enroll in this course?');">
                                <i class="fas fa-sign-in-alt"></i> Enroll
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- ADMIN/PROPONENT EDIT BUTTON -->
                    <?php if ($isAdmin || $isProponent): ?>
                        <a href="<?= BASE_URL ?>/public/course_edit.php?id=<?= $c['id'] ?>" 
                           class="btn-outline-secondary"
                           title="Edit course">
                            <i class="fas fa-edit"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Disabled reason message (for expired courses) -->
                <?php if ($isExpired): ?>
                <div class="mt-2 small text-muted text-center">
                    <i class="fas fa-info-circle"></i> 
                    This course has expired
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination Navigation -->
    <?php if ($totalPages > 1 && !empty($courses)): ?>
    <div class="pagination-container">
        <div class="pagination-nav">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="pagination-prev">
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
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="pagination-link">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <!-- Next Button -->
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="pagination-next">
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

    <!-- Kitchen accent -->
    <div class="kitchen-accent">
        <i class="fas fa-cube"></i>
        <i class="fas fa-utensils"></i>
        <i class="fas fa-cube"></i>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
</body>
</html>