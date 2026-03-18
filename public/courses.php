<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$user = $_SESSION['user'];
$userId = $user['id'] ?? 0;
$isAdmin = is_admin();
$isProponent = is_proponent();

// Get all courses with their assessment info and enrollment status
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
        e.status AS enroll_status,
        e.progress,
        e.pages_viewed,
        e.enrolled_at,
        e.completed_at,
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
        -- Get the latest assessment attempt score for this user
        (
            SELECT aa.score 
            FROM assessment_attempts aa 
            WHERE aa.assessment_id = a.id 
            AND aa.user_id = ? 
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
                        AND aa.user_id = ? 
                        AND aa.status = 'completed'
                        AND aa.score >= a.passing_score
                    ) THEN 1
                    ELSE 0
                END
            ELSE 0
        END AS assessment_passed,
        -- Determine REAL completion status based on our logic
        CASE 
            WHEN e.id IS NULL THEN 'notenrolled'
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
                                AND aa.user_id = ? 
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
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    LEFT JOIN users u ON c.proponent_id = u.id
    LEFT JOIN assessments a ON a.course_id = c.id
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY 
        -- Show ongoing courses first, then completed, then not enrolled
        CASE 
            WHEN e.id IS NULL THEN 3
            WHEN c.expires_at IS NOT NULL AND c.expires_at < NOW() THEN 4
            WHEN (
                (a.id IS NOT NULL AND e.pages_viewed >= c.total_pages AND (
                    SELECT COUNT(*) 
                    FROM assessment_attempts aa 
                    WHERE aa.assessment_id = a.id 
                    AND aa.user_id = ? 
                    AND aa.status = 'completed'
                    AND aa.score >= a.passing_score
                ) > 0) OR
                (a.id IS NULL AND e.pages_viewed >= c.total_pages)
            ) THEN 2
            ELSE 1
        END,
        c.id DESC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For debugging - you can remove this after testing
/*
echo "<pre>";
foreach ($courses as $c) {
    echo "Course: " . $c['title'] . "\n";
    echo "Has Assessment: " . $c['has_assessment'] . "\n";
    echo "Pages Viewed: " . $c['pages_viewed'] . "/" . $c['total_pages'] . "\n";
    echo "Latest Score: " . ($c['latest_assessment_score'] ?? 'N/A') . "\n";
    echo "Passing Score: " . ($c['passing_score'] ?? 'N/A') . "\n";
    echo "Assessment Passed: " . $c['assessment_passed'] . "\n";
    echo "Display Status: " . $c['display_status'] . "\n";
    echo "------------------------\n";
}
echo "</pre>";
*/
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Courses - CookLabs LMS</title>
<link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts: Inter -->
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
    margin-bottom: 1.5rem;
    border-left: 8px solid #1d6fb0;
    padding-left: 1.2rem;
}

/* Controls Bar */
.controls-bar {
    display: flex;
    justify-content: flex-end;
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

/* Search Results Info */
.search-info {
    background: #f0f8ff;
    border: 2px solid #b8d6f5;
    box-shadow: 4px 4px 0 #a0c0e0;
    padding: 0.8rem 1.2rem;
    margin-bottom: 1rem;
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

/* Divider Line */
.section-divider {
    border: 0;
    height: 3px;
    background: #1d6fb0;
    box-shadow: 2px 2px 0 #0f4980;
    margin-bottom: 2rem;
}

/* Course Grid */
.modern-courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* Course Card */
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
    height: 520px;
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

/* Lock Overlay */
.lock-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    backdrop-filter: blur(2px);
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
    margin: 0 0 0.5rem 0;
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
    -webkit-line-clamp: 1;
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

/* Button Base Styles */
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

/* Preview Button - BLUE */
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

/* Enroll/Continue Button - GREEN */
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

/* Tooltip Icons */
.tooltip-icon, .tooltip-icon-ex {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: white;
    border: 2px solid;
    box-shadow: 2px 2px 0 rgba(0,0,0,0.1);
    border-radius: 0px;
    cursor: help;
}

.tooltip-icon {
    border-color: #ffc107;
    color: #ffc107;
}

.tooltip-icon-ex {
    border-color: #dc3545;
    color: #dc3545;
}

/* Admin Edit Button */
.btn-outline-secondary {
    background: white;
    border: 2px solid #6c757d;
    box-shadow: 2px 2px 0 #5a6268;
    color: #6c757d;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    text-decoration: none;
    border-radius: 0px;
    transition: all 0.1s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-outline-secondary:hover {
    transform: translate(-1px, -1px);
    box-shadow: 3px 3px 0 #5a6268;
    background: #f8f9fa;
}

/* Empty State */
.alert-info {
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    padding: 3rem;
    text-align: center;
    color: #1e4465;
    grid-column: 1 / -1;
}

.alert-info i {
    font-size: 3rem;
    color: #b8d6f5;
    margin-bottom: 1rem;
    display: block;
}

/* Empty State for search */
.empty-state {
    text-align: center;
    padding: 3rem;
    background: #ffffff;
    border: 3px solid #1a4b77;
    box-shadow: 12px 12px 0 #123a5e;
    border-radius: 0px;
    grid-column: 1 / -1;
    display: none;
}

.empty-state.visible {
    display: block;
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
    .modern-courses-grid {
        grid-template-columns: 1fr;
    }
    .modern-course-card {
        height: auto;
        min-height: 520px;
    }
    .controls-bar {
        justify-content: center;
    }
    .search-container {
        width: 100%;
    }
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

    <?php if (empty($courses)): ?>
    <div class="alert-info">
        <i class="fas fa-book-open"></i>
        <h4>No Courses Available</h4>
        <p>Check back later for new courses.</p>
    </div>
    <?php else: ?>
    <div class="modern-courses-grid" id="courseGrid">
        <?php foreach ($courses as $c): 
            // Check if course is expired
            $isExpired = false;
            if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
                $isExpired = strtotime($c['course_expires_at']) < time();
            }
            
            $enroll_status = $c['enroll_status'] ?? 'notenrolled';
            $display_status = $c['display_status'] ?? $enroll_status;
            
            // Check if user can enroll in course
            $canEnroll = ($enroll_status === 'notenrolled' && !$isExpired);
            
            // Check if user can continue course (ongoing only)
            $canContinue = ($display_status === 'ongoing' && !$isExpired);
            
            // Check if user can review course (completed)
            $canReview = ($display_status === 'completed' && !$isExpired);
            
            // Enrollment reason why btn is off
            $enrollDisabledReason = '';
            if ($enroll_status !== 'notenrolled') {
                $enrollDisabledReason = 'You are already enrolled in this course';
            } elseif ($isExpired) {
                $enrollDisabledReason = 'This course has expired';
            }
            
            // Determine ribbon style
            $showRibbon = false;
            $ribbonClass = '';
            $ribbonText = '';
            $ribbonIcon = '';
            
            if ($display_status === 'ongoing') {
                $showRibbon = true;
                $ribbonClass = 'ribbon-ongoing';
                $ribbonText = 'In Progress';
                $ribbonIcon = 'fa-play-circle';
            } elseif ($display_status === 'completed') {
                $showRibbon = true;
                $ribbonClass = 'ribbon-completed';
                $ribbonText = 'Completed';
                $ribbonIcon = 'fa-check-circle';
            } elseif ($isExpired) {
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
                
                <!-- lock overlay for expired not-enrolled courses -->
                <?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && $isExpired): ?>
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
                    $startDate = !empty($c['created_at']) 
                        ? date('M d, Y', strtotime($c['created_at']))
                        : 'Not set';
                    
                    $expiryDate = 'No expiry';
                    if (!empty($c['course_expires_at']) && $c['course_expires_at'] != '0000-00-00') {
                        $expiryDate = date('M d, Y', strtotime($c['course_expires_at']));
                    }
                    ?>
                    <p><strong><i class="fas fa-calendar-alt"></i> Start:</strong> <span><?= $startDate ?></span></p>
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
                        
                    <?php elseif ($canContinue): ?>
                        <!-- ONGOING - Show Continue button -->
                        <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
                           class="modern-btn-primary modern-btn-sm">
                            <i class="fas fa-play-circle"></i> Continue
                        </a>
                        
                    <?php elseif ($canReview): ?>
                        <!-- COMPLETED - Show Review button -->
                        <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>"
                           class="modern-btn-primary modern-btn-sm">
                            <i class="fas fa-redo-alt"></i> Review
                        </a>
                        
                    <?php elseif ($enroll_status === 'notenrolled'): ?>
                        <!-- NOT ENROLLED - Show Enroll button if possible -->
                        <?php if ($canEnroll || $isAdmin): ?>
                            <form method="POST" action="<?= BASE_URL ?>/public/enroll.php" style="display: inline;">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="modern-btn-primary modern-btn-sm" 
                                    onclick="return confirm('Enroll in this course?');">
                                    <i class="fas fa-sign-in-alt"></i> Enroll
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Disabled enroll button -->
                            <span class="tooltip-icon" 
                                  title="<?= htmlspecialchars($enrollDisabledReason) ?>">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        <?php endif; ?>
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
                
                <!-- Disabled reason message (for expired not-enrolled) -->
                <?php if (!$canEnroll && $enroll_status === 'notenrolled' && !$isAdmin && $isExpired): ?>
                <div class="mt-2 small text-muted text-center">
                    <i class="fas fa-info-circle"></i> 
                    <?= htmlspecialchars($enrollDisabledReason) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State for search -->
    <div class="empty-state" id="emptyState">
        <i class="fas fa-search"></i>
        <h4>No Matching Courses</h4>
        <p id="emptyStateMessage">No courses match your search criteria.</p>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Real-time search functionality
    const searchInput = document.getElementById('searchInput');
    const courseCards = document.querySelectorAll('.modern-course-card');
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
                emptyState.classList.add('visible');
                if (courseGrid) courseGrid.style.display = 'none';
            } else {
                emptyState.classList.remove('visible');
                if (courseGrid) courseGrid.style.display = 'grid';
            }
            
            // If search is cleared, show all
            if (searchTerm.length === 0) {
                courseCards.forEach(card => {
                    card.style.display = 'flex';
                });
                if (courseGrid) courseGrid.style.display = 'grid';
                emptyState.classList.remove('visible');
            }
        });
    }
});
</script>
</body>
</html>