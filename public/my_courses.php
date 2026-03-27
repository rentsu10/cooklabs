<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

// ===== PAGINATION SETUP =====
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 4; // 4 courses per page
$offset = ($page - 1) * $limit;

// ===== SEARCH SETUP =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get total count of enrolled courses for this user with search (EXCLUDING ARCHIVED)
if (!empty($search)) {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN users u ON c.proponent_id = u.id
        WHERE e.user_id = :user_id
        AND e.is_archived = 0
        AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
    ");
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $countStmt->execute();
} else {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM enrollments e
        WHERE e.user_id = :user_id AND e.is_archived = 0
    ");
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
}
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Fetch only courses the student is enrolled in with assessment info (EXCLUDING ARCHIVED)
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.thumbnail, 
            c.created_at, 
            c.expires_at,
            c.total_pages,
            e.progress, 
            e.pages_viewed,
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
            -- Get the latest assessment attempt score for this user
            (
                SELECT aa.score 
                FROM assessment_attempts aa 
                WHERE aa.assessment_id = a.id 
                AND aa.user_id = :user_id1 
                AND aa.status = 'completed'
                ORDER BY aa.completed_at DESC 
                LIMIT 1
            ) as latest_assessment_score,
            -- Check if student passed assessment (compare score with passing_score)
            CASE 
                WHEN a.id IS NOT NULL THEN
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM assessment_attempts aa 
                            WHERE aa.assessment_id = a.id 
                            AND aa.user_id = :user_id2 
                            AND aa.status = 'completed'
                            AND aa.score >= a.passing_score
                        ) THEN 1
                        ELSE 0
                    END
                ELSE 0
            END AS assessment_passed,
            -- Determine REAL completion status based on our logic
            CASE 
                WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
                ELSE
                    CASE 
                        -- Has assessment: need both PDF complete AND at least one passing assessment score
                        WHEN a.id IS NOT NULL THEN
                            CASE 
                                WHEN e.pages_viewed >= c.total_pages AND (
                                    SELECT COUNT(*) 
                                    FROM assessment_attempts aa 
                                    WHERE aa.assessment_id = a.id 
                                    AND aa.user_id = :user_id3 
                                    AND aa.status = 'completed'
                                    AND aa.score >= a.passing_score
                                ) > 0 THEN 'completed'
                                ELSE 'ongoing'
                            END
                        -- No assessment: just need PDF complete
                        ELSE
                            CASE 
                                WHEN e.pages_viewed >= c.total_pages THEN 'completed'
                                ELSE 'ongoing'
                            END
                    END
            END AS display_status
        FROM courses c
        JOIN enrollments e ON e.course_id = c.id AND e.user_id = :user_id4
        LEFT JOIN users u ON c.proponent_id = u.id
        LEFT JOIN assessments a ON a.course_id = c.id
        WHERE e.user_id = :user_id5
        AND e.is_archived = 0
        AND (c.title LIKE :search OR CONCAT(u.fname, ' ', u.lname) LIKE :search)
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 3
                WHEN (
                    (a.id IS NOT NULL AND e.pages_viewed >= c.total_pages AND (
                        SELECT COUNT(*) 
                        FROM assessment_attempts aa 
                        WHERE aa.assessment_id = a.id 
                        AND aa.user_id = :user_id6 
                        AND aa.status = 'completed'
                        AND aa.score >= a.passing_score
                    ) > 0) OR
                    (a.id IS NULL AND e.pages_viewed >= c.total_pages)
                ) THEN 2
                ELSE 1
            END,
            c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // Bind all parameters
    $stmt->bindValue(':user_id1', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id3', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id4', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id5', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id6', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.thumbnail, 
            c.created_at, 
            c.expires_at,
            c.total_pages,
            e.progress, 
            e.pages_viewed,
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
            -- Get the latest assessment attempt score for this user
            (
                SELECT aa.score 
                FROM assessment_attempts aa 
                WHERE aa.assessment_id = a.id 
                AND aa.user_id = :user_id1 
                AND aa.status = 'completed'
                ORDER BY aa.completed_at DESC 
                LIMIT 1
            ) as latest_assessment_score,
            -- Check if student passed assessment (compare score with passing_score)
            CASE 
                WHEN a.id IS NOT NULL THEN
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM assessment_attempts aa 
                            WHERE aa.assessment_id = a.id 
                            AND aa.user_id = :user_id2 
                            AND aa.status = 'completed'
                            AND aa.score >= a.passing_score
                        ) THEN 1
                        ELSE 0
                    END
                ELSE 0
            END AS assessment_passed,
            -- Determine REAL completion status based on our logic
            CASE 
                WHEN e.status = 'ongoing' AND c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 'expired'
                ELSE
                    CASE 
                        -- Has assessment: need both PDF complete AND at least one passing assessment score
                        WHEN a.id IS NOT NULL THEN
                            CASE 
                                WHEN e.pages_viewed >= c.total_pages AND (
                                    SELECT COUNT(*) 
                                    FROM assessment_attempts aa 
                                    WHERE aa.assessment_id = a.id 
                                    AND aa.user_id = :user_id3 
                                    AND aa.status = 'completed'
                                    AND aa.score >= a.passing_score
                                ) > 0 THEN 'completed'
                                ELSE 'ongoing'
                            END
                        -- No assessment: just need PDF complete
                        ELSE
                            CASE 
                                WHEN e.pages_viewed >= c.total_pages THEN 'completed'
                                ELSE 'ongoing'
                            END
                    END
            END AS display_status
        FROM courses c
        JOIN enrollments e ON e.course_id = c.id AND e.user_id = :user_id4
        LEFT JOIN users u ON c.proponent_id = u.id
        LEFT JOIN assessments a ON a.course_id = c.id
        WHERE e.user_id = :user_id5
        AND e.is_archived = 0
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 3
                WHEN (
                    (a.id IS NOT NULL AND e.pages_viewed >= c.total_pages AND (
                        SELECT COUNT(*) 
                        FROM assessment_attempts aa 
                        WHERE aa.assessment_id = a.id 
                        AND aa.user_id = :user_id6 
                        AND aa.status = 'completed'
                        AND aa.score >= a.passing_score
                    ) > 0) OR
                    (a.id IS NULL AND e.pages_viewed >= c.total_pages)
                ) THEN 2
                ELSE 1
            END,
            c.id DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // Bind all parameters
    $stmt->bindValue(':user_id1', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id3', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id4', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id5', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id6', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
}
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total pages
$totalPages = ceil($totalCount / $limit);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Courses - CookLabs LMS</title>
    <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<style>
/* ===== SHARP GEOMETRIC COURSES PAGE ===== */
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
.modern-section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #07223b;
    margin-bottom: 2rem;
    border-left: 8px solid #1d6fb0;
    padding-left: 1.2rem;
}

/* Controls Bar */
.controls-bar {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: right;
}

.search-container {
    position: relative;
    display: flex;
    width: 100%;
    max-width: 350px;
}

.search-input {
    flex: 1;
    padding: 0.7rem 1rem;
    border: 3px solid #1a4b77;
    background: white;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.1s ease;
}

.search-input:focus {
    border-color: #1d6fb0;
    box-shadow: 0 0 0 2px rgba(29, 111, 176, 0.2);
}

.search-icon {
    background: #1661a3;
    border: none;
    border-left: 2px solid #1a4b77;
    padding: 0.7rem 1.2rem;
    color: white;
    cursor: pointer;
    transition: all 0.1s ease;
}

.search-icon:hover {
    background: #1a70b5;
    transform: translate(-1px, -1px);
}

.btn-add {
    background: #6c757d;
    border: 3px solid #5a6268;
    box-shadow: 3px 3px 0 #404040;
    text-decoration: none;
    padding: 0.6rem 1.2rem;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    transition: all 0.1s ease;
}

.btn-add:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #404040;
    color: white;
    background: #5a6268;
}

/* Section Divider */
.section-divider {
    border: none;
    height: 3px;
    background: #1a4b77;
    margin: 1rem 0 1.5rem 0;
    opacity: 0.3;
}

/* Search Info */
.search-info {
    background: #d7e9ff;
    border-left: 6px solid #1d6fb0;
    padding: 0.8rem 1.2rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: #07223b;
}

.search-info span:first-child i {
    margin-right: 8px;
    color: #1d6fb0;
}

.search-info #resultCount {
    background: #1a4b77;
    color: white;
    padding: 0.2rem 0.8rem;
    font-weight: 600;
}

/* Course Grid */
.modern-courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* Course Card - INCREASED HEIGHT to accommodate all elements */
.modern-course-card {
    background: #ffffff;
    border: 3px solid #1a4b77;
    border-top: none;
    box-shadow: 8px 8px 0 #123a5e;
    border-radius: 0px;
    overflow: hidden;
    transition: all 0.1s ease;
    display: flex;
    flex-direction: column;
    height: 580px;
}

.modern-course-card:hover {
    transform: translate(-2px, -2px);
    box-shadow: 10px 10px 0 #123a5e;
}

/* Card Image */
.modern-card-img {
    height: 200px;
    background: #d7e9ff;
    border-bottom: 3px solid #1a4b77;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
}

.modern-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* RIBBON STYLES */
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

.ribbon-ongoing {
    background: #ffc107;
    border-color: #b88f1f;
    color: #07223b;
}

.ribbon-completed {
    background: #28a745;
    border-color: #1e7e34;
    color: white;
}

.ribbon-expired {
    background: #6c757d;
    border-color: #5a6268;
    color: white;
}

/* Card Body */
.modern-card-body {
    padding: 1rem 1.2rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

/* Title Section */
.modern-card-title h6 {
    font-weight: 700;
    color: #07223b;
    font-size: 1.1rem;
    margin: 0 0 0.8rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
    height: 2.6rem;
}

/* Description */
.modern-card-body p {
    color: #1e4465;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 0.8rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 3.6rem;
}

/* Course Info Section */
.modern-course-info {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 4px 4px 0 #a0c0e0;
    padding: 0.8rem;
    margin-bottom: 0.8rem;
    flex-shrink: 0;
}

.modern-course-info p {
    margin-bottom: 0.3rem;
    font-size: 0.75rem;
    display: flex;
    justify-content: space-between;
    color: #1e4465;
    height: auto;
}

.modern-course-info strong {
    color: #07223b;
    font-weight: 600;
}

.modern-course-info i {
    color: #1d6fb0;
    width: 14px;
    margin-right: 4px;
}

/* Progress Display */
.progress-display {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 3px 3px 0 #a0c0e0;
    padding: 0.6rem;
    margin-bottom: 0.8rem;
    font-size: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-display i {
    color: #1d6fb0;
    margin-right: 4px;
}

.progress-value {
    font-weight: 700;
    color: #07223b;
}

/* Assessment Status Badge */
.assessment-status {
    margin-bottom: 0.8rem;
    padding: 0.3rem;
    text-align: center;
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid;
}

.assessment-pending {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.assessment-passed {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.assessment-no-assessment {
    background: #e2e3e5;
    border-color: #6c757d;
    color: #383d41;
}

/* Action Buttons */
.modern-card-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: auto;
    padding-top: 0.5rem;
    flex-shrink: 0;
}

.modern-btn-sm {
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: 2px solid;
    min-width: 70px;
}

.modern-btn-warning {
    background: #1d6fb0;
    border-color: #0f4980;
    box-shadow: 3px 3px 0 #0a3458;
    color: white;
}

.modern-btn-warning:hover {
    transform: translate(-2px, -2px);
    box-shadow: 5px 5px 0 #0a3458;
    background: #2680cf;
    color: white;
}

.modern-btn-primary {
    background: #28a745;
    border-color: #1e7e34;
    box-shadow: 3px 3px 0 #166b2c;
    color: white;
}

.modern-btn-primary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 5px 5px 0 #166b2c;
    background: #34ce57;
    color: white;
}

.modern-btn-secondary {
    background: #6c757d;
    border-color: #5a6268;
    box-shadow: 3px 3px 0 #404040;
    color: white;
    opacity: 0.7;
    cursor: not-allowed;
}

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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    max-width: 600px;
    margin: 2rem auto;
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

.empty-state .btn-add {
    display: inline-block;
    margin-top: 1rem;
    background: #1661a3;
    border: 3px solid #0c314d;
    box-shadow: 4px 4px 0 #0b263b;
    padding: 0.6rem 1.5rem;
    color: white;
    text-decoration: none;
    border-radius: 0px;
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
    .modern-course-card {
        height: auto;
        min-height: 580px;
    }
}
</style>
</head>
<body>
    
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>
    
    <div class="modern-courses-wrapper">
        <h2 class="modern-section-title">My Courses</h2>

        <!-- Controls Bar - Search Bar -->
        <div class="controls-bar">
            <form method="GET" action="" style="display: flex; gap: 0.5rem; width: 100%; max-width: 350px;">
                <div class="search-container" style="width: 100%;">
                    <input type="text" name="search" class="search-input" placeholder="Search by title or instructor..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-icon" style="background: #1661a3; border: none; border-left: 2px solid #1a4b77; padding: 0.7rem 1.2rem; color: white; cursor: pointer;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                    <a href="my_courses.php" class="btn-add" style="background: #6c757d; box-shadow: 3px 3px 0 #404040; text-decoration: none; padding: 0.6rem 1.2rem; color: white; display: inline-flex; align-items: center; gap: 6px;">
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
        
        <?php if (!empty($myCourses)): ?>
            <div class="modern-courses-grid">
                <?php foreach ($myCourses as $c): 
                    // Determine ribbon style based on display_status
                    $showRibbon = false;
                    $ribbonClass = '';
                    $ribbonText = '';
                    $ribbonIcon = '';
                    
                    if ($c['display_status'] === 'ongoing') {
                        $showRibbon = true;
                        $ribbonClass = 'ribbon-ongoing';
                        $ribbonText = 'In Progress';
                        $ribbonIcon = 'fa-play-circle';
                    } elseif ($c['display_status'] === 'completed') {
                        $showRibbon = true;
                        $ribbonClass = 'ribbon-completed';
                        $ribbonText = 'Completed';
                        $ribbonIcon = 'fa-check-circle';
                    } elseif ($c['display_status'] === 'expired') {
                        $showRibbon = true;
                        $ribbonClass = 'ribbon-expired';
                        $ribbonText = 'Expired';
                        $ribbonIcon = 'fa-hourglass-end';
                    }
                    
                    // Determine assessment status
                    $assessmentStatus = '';
                    $assessmentClass = '';
                    if ($c['has_assessment']) {
                        if ($c['assessment_passed']) {
                            $assessmentStatus = 'Assessment Passed';
                            $assessmentClass = 'assessment-passed';
                        } else {
                            $assessmentStatus = 'Assessment Required';
                            $assessmentClass = 'assessment-pending';
                        }
                    } else {
                        $assessmentStatus = 'No Assessment';
                        $assessmentClass = 'assessment-no-assessment';
                    }
                ?>
                <div class="modern-course-card">
                    <div class="modern-card-img">
                        <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" 
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             onerror="this.src='<?= BASE_URL ?>/uploads/images/Course Image.png'">
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
                            <h6><?= htmlspecialchars($c['title']) ?></h6>
                        </div>
                        
                        <p><?= htmlspecialchars(substr($c['description'], 0, 120)) ?>...</p>
                        
                        <div class="modern-course-info">
                            <?php
                                $expiryDate = $c['expires_at']
                                    ? date('M d, Y', strtotime($c['expires_at']))
                                    : 'No expiry';
                            ?>
                            <p><strong><i class="fas fa-hourglass-half"></i> Expires:</strong> <span><?= $expiryDate ?></span></p>
                            
                            <!-- Show instructor name -->
                            <?php if (!empty($c['proponent_fullname'])): ?>
                            <p><strong><i class="fas fa-chalkboard-teacher"></i> Instructor:</strong> <span><?= htmlspecialchars($c['proponent_fullname']) ?></span></p>
                            <?php endif; ?>
                        </div>

                        <!-- Progress Display -->
                        <?php if ($c['display_status'] !== 'expired'): ?>
                            <div class="progress-display">
                                <span>
                                    <i class="fas fa-chart-line"></i> Overall Progress:
                                </span>
                                <span class="progress-value"><?= intval($c['progress']) ?>%</span>
                            </div>
                        <?php endif; ?>

                        <!-- Assessment Status Badge -->
                        <div class="assessment-status <?= $assessmentClass ?>">
                            <i class="fas <?= $c['has_assessment'] ? 'fa-clipboard-list' : 'fa-check-circle' ?>"></i>
                            <?= $assessmentStatus ?>
                            <?php if ($c['has_assessment'] && $c['latest_assessment_score']): ?>
                                <small>(Score: <?= $c['latest_assessment_score'] ?>%)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="modern-card-actions">
                            <a href="<?= BASE_URL ?>/public/course_preview.php?id=<?= $c['id'] ?>"
                               class="modern-btn-warning modern-btn-sm"
                               title="Preview course content">
                                <i class="fas fa-eye"></i> Preview
                            </a>            
                            
                            <?php if ($c['display_status'] === 'expired'): ?>
                                <span class="modern-btn-sm modern-btn-secondary" 
                                      style="cursor: not-allowed;"
                                      title="This course has expired">
                                    <i class="fas fa-ban"></i> Expired
                                </span>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" 
                                   class="modern-btn-primary modern-btn-sm">
                                    <i class="fas fa-play-circle"></i> 
                                    <?= $c['display_status'] === 'completed' ? 'Review' : 'Continue' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Navigation -->
            <?php if ($totalPages > 1 && !empty($myCourses)): ?>
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
                <div class="page-info">
                    Showing <?= count($myCourses) ?> of <?= $totalCount ?> courses | Page <?= $page ?> of <?= $totalPages ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h4><?= !empty($search) ? 'No Matching Courses' : 'No Courses Yet' ?></h4>
                <p><?= !empty($search) ? 'No enrolled courses match your search criteria.' : 'You are not enrolled in any courses yet.' ?></p>
                <?php if (!empty($search)): ?>
                    <a href="my_courses.php" class="btn-add">Clear Search</a>
                <?php else: ?>
                    <a href="courses.php" class="btn-add">Browse Courses</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Kitchen accent -->
        <div class="kitchen-accent">
            <i class="fas fa-cube"></i>
            <i class="fas fa-utensils"></i>
            <i class="fas fa-cube"></i>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>