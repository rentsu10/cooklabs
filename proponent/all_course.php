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

/* =========================
FETCH ALL COURSES WITH USER INFO
========================= */

// Build query for all courses with user full name
$sql = "
SELECT c.*, 
       CONCAT(u.fname, ' ', u.lname) as proponent_fullname,
       CASE WHEN c.proponent_id = :user_id THEN 1 ELSE 0 END as is_owner
FROM courses c 
LEFT JOIN users u ON c.proponent_id = u.id 
WHERE c.is_active = 1
ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $_SESSION['user']['id']);
$stmt->execute();
$courses = $stmt->fetchAll();
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

/* Page Title */
.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 2rem;
    border-left: 8px solid #1d6fb0;
    padding-left: 1.2rem;
}

/* Controls Bar - Search Bar Only (right-aligned) */
.controls-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 1.5rem;
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

/* Divider Line */
.section-divider {
    border: 0;
    height: 3px;
    background: #1d6fb0;
    box-shadow: 2px 2px 0 #0f4980;
    margin-bottom: 2rem;
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

/* Course Grid */
.course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

/* Course Card - Fixed height (increased to accommodate ribbon) */
.course-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    border-top: none;
    box-shadow: 8px 8px 0 #123a5e;
    border-radius: 0px;
    overflow: hidden;
    transition: all 0.1s ease;
    height: 530px; /* Increased from 500px to accommodate ribbon */
    display: flex;
    flex-direction: column;
}

.course-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 10px 10px 0 #123a5e;
}

/* Optional: Add red border for expired courses */
.course-card.expired {
    border-color: #565656;
    box-shadow: 8px 8px 0 #3e3e3e;
}

.course-card.expired:hover {
    box-shadow: 10px 10px 0 #b02a37;
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
    height: 115px;
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

/* Owner indicator */
.owner-indicator {
    color: #1d6fb0;
    font-weight: 600;
    font-size: 0.75rem;
    margin-top: 0.2rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.owner-indicator i {
    color: #1d6fb0;
}

/* Action Buttons - Only View button */
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
    .controls-bar {
        justify-content: flex-end;
    }
    .search-container {
        width: 100%;
    }
    .course-card {
        height: auto;
        min-height: 530px;
    }
}
</style>
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
    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" id="searchInput" class="search-input" placeholder="Search by title or instructor...">
        <span class="search-icon"><i class="fas fa-search"></i></span>
    </div>
</div>

<!-- Divider Line -->
<hr class="section-divider">

<!-- Search Results Info -->
<div class="search-info" id="searchInfo">
    <span><i class="fas fa-search"></i> Search results for: <strong id="searchTerm"></strong></span>
    <span id="resultCount"></span>
</div>

<!-- Course Grid -->
<div class="course-grid" id="courseGrid">
    <?php if (empty($courses)): ?>
    <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <h4>No Courses Available</h4>
        <p>There are no courses to display at the moment.</p>
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

<!-- Empty State for search (hidden by default) -->
<div class="empty-state" id="emptyState" style="display: none;">
    <i class="fas fa-search"></i>
    <h4>No Matching Courses</h4>
    <p id="emptyStateMessage">No courses match your search criteria.</p>
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
document.addEventListener('DOMContentLoaded', function () {
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
                courseGrid.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                courseGrid.style.display = 'grid';
            }
            
            // If search is cleared, show all
            if (searchTerm.length === 0) {
                courseCards.forEach(card => {
                    card.style.display = 'flex';
                });
                courseGrid.style.display = 'grid';
                emptyState.style.display = 'none';
            }
        });
    }
});
</script>

</body>
</html>