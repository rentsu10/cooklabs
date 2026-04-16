    <?php
    require_once __DIR__ . '/../inc/config.php';
    require_once __DIR__ . '/../inc/auth.php';
    require_once __DIR__ . '/../inc/functions.php';

    require_login();
    $u = current_user();

    $courseId = intval($_GET['id'] ?? 0);
    if(!$courseId) die('Invalid course ID');

    // Fetch course
    $stmt = $pdo->prepare('SELECT c.*, u.fname, u.lname FROM courses c LEFT JOIN users u ON c.proponent_id = u.id WHERE c.id = ?');
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    if(!$course) die('Course not found');

    // Fetch enrollment if student
    $enrollment = null;
    $pdfProgress = [];

    if (is_student()) {

        // BLOCK if course expired or inactive
        $today = date('Y-m-d');

        if (
            $course['is_active'] == 0 ||
            ($course['expires_at'] && $today > $course['expires_at'])
        ) {
            die('<div class="alert alert-danger m-4">
                    <h5>Course Unavailable</h5>
                    <p>This course has expired or is no longer active.</p>
                </div>');
        }

        // Check enrollment
        $stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id=? AND course_id=?');
        $stmt->execute([$u['id'], $courseId]);
        $enrollment = $stmt->fetch();

        if (!$enrollment) {
            // Auto-create enrollment ONLY if course is valid
            $stmt = $pdo->prepare('
                INSERT INTO enrollments 
                (user_id, course_id, enrolled_at, status, progress) 
                VALUES (?, ?, NOW(), "ongoing", 0)
            ');
            $stmt->execute([$u['id'], $courseId]);

            $enrollmentId = $pdo->lastInsertId();
            $enrollment = [
                'id' => $enrollmentId,
                'progress' => 0,
                'status' => 'ongoing'
            ];
        } else {
            $enrollmentId = $enrollment['id'];
        }

        // Fetch PDF progress if any
        $stmt = $pdo->prepare('SELECT * FROM pdf_progress WHERE enrollment_id = ? ORDER BY page_number');
        $stmt->execute([$enrollmentId]);
        $pdfProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle AJAX PDF page tracking
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pdf_page']) && is_student()) {
        $page = intval($_POST['pdf_page']);
        $totalPages = intval($_POST['total_pages'] ?? 1);
        
        // Check if this page has been recorded
        $stmt = $pdo->prepare('SELECT id FROM pdf_progress WHERE enrollment_id = ? AND page_number = ?');
        $stmt->execute([$enrollment['id'], $page]);
        
        if (!$stmt->fetch()) {
            // Record new page view
            $stmt = $pdo->prepare('INSERT INTO pdf_progress (enrollment_id, page_number, viewed_at) VALUES (?, ?, NOW())');
            $stmt->execute([$enrollment['id'], $page]);
            
            // Calculate new progress percentage
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT page_number) as pages_viewed FROM pdf_progress WHERE enrollment_id = ?');
            $stmt->execute([$enrollment['id']]);
            $pagesViewed = $stmt->fetchColumn();
            
            $progressPercent = min(100, round(($pagesViewed / $totalPages) * 100));
            
            // Update enrollment progress and pages_viewed
            $stmt = $pdo->prepare('UPDATE enrollments SET progress = ?, pages_viewed = ? WHERE id = ?');
            $stmt->execute([$progressPercent, $pagesViewed, $enrollment['id']]);
            
            echo json_encode([
                'success' => true,
                'pages_viewed' => $pagesViewed,
                'total_pages' => $totalPages,
                'progress' => $progressPercent
            ]);
        } else {
            echo json_encode(['success' => true, 'already_viewed' => true]);
        }
        exit;
    }

    // Handle completion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed']) && is_student()) {

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Get total pages
            $totalPages = intval($_POST['total_pages'] ?? 1);
            
            // Get pages viewed
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT page_number) as pages_viewed FROM pdf_progress WHERE enrollment_id = ?');
            $stmt->execute([$enrollment['id']]);
            $pagesViewed = $stmt->fetchColumn();
            
            // Calculate progress
            $progressPercent = round(($pagesViewed / $totalPages) * 100);
            
            // Update enrollment status and progress
            $stmt = $pdo->prepare("
                UPDATE enrollments 
                SET status = 'completed', completed_at = NOW(), progress = ?, pages_viewed = ? 
                WHERE id = ?
            ");
            $stmt->execute([$progressPercent, $pagesViewed, $enrollment['id']]);

            // Get student info for email
            $studentName = trim($u['fname'] . ' ' . $u['lname']);
            if (empty($studentName)) $studentName = $u['username'];

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'pages_viewed' => $pagesViewed,
                'total_pages' => $totalPages,
                'progress' => $progressPercent
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error completing course: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    // ============================================
    // FETCH ENROLLED STUDENTS - FOR ADMIN/PROPONENT
    // ============================================

    // 1. ALL enrolled students (ongoing + completed)
    $stmt = $pdo->prepare('
        SELECT 
            u.id, 
            u.fname, 
            u.lname, 
            u.email,
            u.username,
            e.status,
            e.progress,
            e.pages_viewed,
            e.enrolled_at,
            e.completed_at,
            DATE_FORMAT(e.enrolled_at, "%M %d, %Y") as enrolled_date,
            DATE_FORMAT(e.completed_at, "%M %d, %Y") as completed_date,
            CASE 
                WHEN e.status = "completed" THEN "badge-completed"
                WHEN e.status = "ongoing" THEN "badge-ongoing"
                ELSE "badge-notenrolled"
            END as status_color,
            CASE 
                WHEN e.status = "completed" THEN "Completed"
                WHEN e.status = "ongoing" THEN "Ongoing"
                ELSE "Not Started"
            END as status_text
        FROM enrollments e
        JOIN users u ON e.user_id = u.id 
        WHERE e.course_id = ?
        ORDER BY 
            CASE e.status 
                WHEN "ongoing" THEN 1 
                WHEN "completed" THEN 2 
                ELSE 3 
            END,
            e.enrolled_at DESC
    ');
    $stmt->execute([$courseId]);
    $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Count statistics
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_enrolled,
            SUM(CASE WHEN status = "ongoing" THEN 1 ELSE 0 END) as ongoing_count,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count
        FROM enrollments 
        WHERE course_id = ?
    ');
    $stmt->execute([$courseId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Completion rate
    $completionRate = 0;
    if ($stats['total_enrolled'] > 0) {
        $completionRate = round(($stats['completed_count'] / $stats['total_enrolled']) * 100);
    }

    // 4. Export CSV
    if (isset($_GET['export']) && $_GET['export'] == 'csv' && (is_admin() || is_proponent())) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="enrolled_students_course_' . $courseId . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Name', 'Email', 'Username', 'Status', 'Enrolled Date', 'Completed Date', 'Progress (%)', 'Pages Viewed']);
        
        foreach ($enrolledStudents as $student) {
            fputcsv($output, [
                $student['fname'] . ' ' . $student['lname'],
                $student['email'],
                $student['username'],
                $student['status_text'],
                $student['enrolled_date'],
                $student['completed_date'] ?? 'N/A',
                $student['progress'] . '%',
                $student['pages_viewed'] ?? 0
            ]);
        }
        fclose($output);
        exit;
    }

    // Get total pages for PDF from the course table
    $totalPdfPages = $course['total_pages'] ?? 0;

    // Check if assessment exists for this course
    $assessment = null;
    $completedAssessment = null;
    if (is_student()) {
        $stmt = $pdo->prepare("SELECT id, title FROM assessments WHERE course_id = ?");
        $stmt->execute([$courseId]);
        $assessment = $stmt->fetch();
        
        if ($assessment) {
            $stmt = $pdo->prepare("
                SELECT id, status, score, completed_at FROM assessment_attempts 
                WHERE assessment_id = ? AND user_id = ? AND status = 'completed'
                ORDER BY completed_at DESC LIMIT 1
            ");
            $stmt->execute([$assessment['id'], $u['id']]);
            $completedAssessment = $stmt->fetch();
        }
    }
    ?>

    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?=htmlspecialchars($course['title'])?> - CookLabs LMS</title>
        <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
        <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/courseview.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
        <style>
           
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <div class="lms-sidebar-container">
            <?php include __DIR__ . '/../inc/sidebar.php'; ?>
        </div>

        <!-- Toast notification container -->
        <div id="toastContainer" class="toast-notification"></div>

        <!-- Main Content -->
        <div class="course-content-wrapper" id="mainContent">
            <!-- Course Header with instructor at bottom -->
            <div class="course-header">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div style="flex: 1;">
                        <h3><?=htmlspecialchars($course['title'])?></h3>
                        <p><?=nl2br(htmlspecialchars($course['description']))?></p>
                    </div>

                    <!-- Export Button for Admin/Proponent -->
                    <?php if((is_admin() || is_proponent()) && count($enrolledStudents) > 0): ?>
                        <a href="?id=<?= $courseId ?>&export=csv" class="export-btn">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Instructor info moved to bottom of header (no avatar) -->
                <div class="instructor-info">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span><strong>Instructor:</strong> <?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></span>
                </div>
            </div>

            <!-- Progress Section for Students -->
            <?php if(is_student()): ?>
            <div class="progress-section">
                <div class="progress-header">
                    <h5><i class="fas fa-chart-line"></i> Your Progress</h5>
                    <div class="time-spent">
                        <i class="fas fa-clock"></i>
                        Pages viewed: <span id="pagesViewed"><?= count($pdfProgress) ?></span> / <span id="totalPages"><?= $totalPdfPages ?></span>
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-stats">
                        <span>Course Progress</span>
                        <span id="progressPercent"><?= intval($enrollment['progress'] ?? 0) ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progressBar" style="width: <?= intval($enrollment['progress'] ?? 0) ?>%;"></div>
                    </div>
                </div>

                <div class="status-container">
                    <span id="statusBadge">
                        <?php if(($enrollment['status'] ?? '') === 'completed'): ?>
                            <span class="badge-completed">
                                <i class="fas fa-check-circle"></i> Completed
                            </span>
                        <?php else: ?>
                            <span class="badge-ongoing">
                                <i class="fas fa-spinner"></i> Ongoing
                            </span>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Complete Button - Only show if not completed -->
                <?php if(($enrollment['status'] ?? '') !== 'completed'): ?>
                    <button id="completeBtn" class="btn-complete mt-3" disabled>
                        <i class="fas fa-check-circle"></i> Mark as Complete
                    </button>
                    <small class="text-muted ms-2">
                        <i class="fas fa-info-circle"></i>
                        View all pages of the PDF to enable completion
                    </small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ASSESSMENT SECTION - Hidden by default, shown after completion -->
            <?php if(is_student() && $assessment): ?>
            <div id="assessmentContainer" class="assessment-container <?= ($enrollment['status'] ?? '') === 'completed' ? 'visible' : '' ?>">
                <div class="content-card" style="border-left: 6px solid #ffc107;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h5 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-file-alt" style="color: #1d6fb0;"></i> 
                                Course Assessment
                            </h5>
                            <p style="margin: 0.5rem 0 0 0; color: #1e4465;">
                                <?= htmlspecialchars($assessment['title']) ?>
                            </p>
                        </div>
                        
                        <?php if ($completedAssessment): ?>
                            <!-- Assessment already completed -->
                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <span class="badge-assessment-completed">
                                    <i class="fas fa-check-circle"></i> Assessment Completed
                                </span>
                                <span style="font-weight: 600; color: #07223b;">
                                    Score: <?= $completedAssessment['score'] ?>%
                                </span>
                                <a href="assessment_result.php?attempt_id=<?= $completedAssessment['id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Result
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Assessment ready to take -->
                            <a href="take_assessment.php?id=<?= $assessment['id'] ?>" class="btn-assessment-take">
                                <i class="fas fa-play-circle"></i> Take Assessment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- PDF Content -->
            <?php if($course['file_pdf']): ?>
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h5 style="margin: 0; border-left: 6px solid #1d6fb0; padding-left: 1rem;">
                        <i class="fas fa-file-pdf"></i> Course PDF Material
                    </h5>
                    
                    <?php if(is_student()): ?>
                    <button id="fullscreenBtn" class="fullscreen-btn">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                    <?php endif; ?>
                </div>

                <?php if(is_student()): ?>
                <!-- Scrollable PDF Viewer with page tracking -->
                <div class="pdf-scroll-container" id="pdfScrollContainer">
                    <div id="pdfPages"></div>
                </div>
                
                <?php else: ?>
                <!-- For non-students, show iframe -->
                <div class="pdf-viewer">
                    <iframe
                        src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                        width="100%"
                        height="600">
                    </iframe>
                </div>
                <?php endif; ?>

                <div class="mt-3">
                    <!-- Removed Open PDF in new tab button for students -->
                    <?php if(!is_student()): ?>
                    <a class="export-btn" href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Open PDF in new tab
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Video Content (if any) -->
            <?php if($course['file_video']): ?>
            <div class="content-card">
                <h5><i class="fas fa-video"></i> Course Video</h5>

                <div class="video-player">
                    <video id="courseVideo" width="100%" controls>
                        <source src="<?= BASE_URL ?>/uploads/video/<?= htmlspecialchars($course['file_video']) ?>" type="video/mp4">
                        Your browser does not support HTML5 video.
                    </video>
                </div>
            </div>
            <?php endif; ?>

            <!-- Students List for Admin/Proponent -->
            <?php if((is_admin() || is_proponent()) && count($enrolledStudents) > 0): ?>
            <div class="students-section mt-4">
                <!-- Your existing students table code here -->
            </div>
            <?php endif; ?>

            <!-- Kitchen accent -->
            <div class="kitchen-accent">
                <i class="fas fa-cube"></i>
                <i class="fas fa-utensils"></i>
                <i class="fas fa-cube"></i>
            </div>
        </div>

<script>
<?php if(is_student()): ?>
// ===== PDF VIEWER WITH 1-SECOND DELAY & 50% VISIBILITY =====
let pdfDoc = null;
let totalPages = <?= $totalPdfPages ?>;
let pagesViewed = <?= count($pdfProgress) ?>;
let viewedPages = <?= json_encode(array_column($pdfProgress, 'page_number')) ?>;
let completeBtn = document.getElementById('completeBtn');
let pagesContainer = document.getElementById('pdfPages');
let pageViewedConfirmed = {};
let isCompleted = <?= ($enrollment['status'] ?? '') === 'completed' ? 'true' : 'false' ?>;
let isFullscreen = false;
const mainContent = document.getElementById('mainContent');
const fullscreenBtn = document.getElementById('fullscreenBtn');
const assessmentContainer = document.getElementById('assessmentContainer');

// Initialize viewedPages as an array and create a Set for faster lookups
let viewedArray = viewedPages || [];
let viewedSet = new Set(viewedArray);

// Track which pages have been confirmed by server
let serverConfirmed = new Set(viewedArray);

// ===== Timer tracking for visible pages =====
let pageVisibleStart = {};           // Stores when a page became visible
const VIEW_TIME_REQUIRED = 1000;     // 1 second
const VISIBILITY_THRESHOLD = 50;     // 50% visible required
const UPDATE_INTERVAL = 200;         // Update every 200ms

// Update initial UI
function updateUI() {
    let progress = Math.min(100, Math.round((pagesViewed / totalPages) * 100));
    document.getElementById('pagesViewed').textContent = pagesViewed;
    document.getElementById('progressPercent').textContent = progress + '%';
    document.getElementById('progressBar').style.width = progress + '%';
    
    if (!isCompleted && completeBtn) {
        if (pagesViewed >= totalPages) {
            completeBtn.disabled = false;
        } else {
            completeBtn.disabled = true;
        }
    }
}

updateUI();

// Load the PDF
pdfjsLib.getDocument('<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>').promise.then(function(pdf) {
    pdfDoc = pdf;
    document.getElementById('totalPages').textContent = pdf.numPages;
    totalPages = pdf.numPages;
    updateUI();
    
    if (pagesContainer) pagesContainer.innerHTML = '';
    
    for (let num = 1; num <= pdf.numPages; num++) {
        renderPage(num);
    }
    
    setTimeout(checkVisiblePages, 500);
});

// Render a specific page
function renderPage(num) {
    pdfDoc.getPage(num).then(function(page) {
        const container = document.getElementById('pdfScrollContainer');
        const containerWidth = container ? container.clientWidth - 40 : 800;
        const viewport = page.getViewport({ scale: 1 });
        const scale = containerWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale: scale });
        
        const pageDiv = document.createElement('div');
        pageDiv.className = 'pdf-page';
        pageDiv.id = `page-${num}`;
        pageDiv.dataset.pageNum = num;
        pageDiv.style.marginBottom = '15px';
        pageDiv.style.position = 'relative';
        
        // Viewed badge (✓)
        const viewedBadge = document.createElement('div');
        viewedBadge.className = 'viewed-badge';
        viewedBadge.textContent = '✓';
        viewedBadge.style.display = viewedSet.has(num) ? 'block' : 'none';
        pageDiv.appendChild(viewedBadge);
        
        // Watch indicator (shows progress to 1 second)
        const watchIndicator = document.createElement('div');
        watchIndicator.className = 'watch-indicator';
        watchIndicator.style.cssText = `
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(29, 111, 176, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 0;
            font-size: 12px;
            border: 2px solid #0f4980;
            box-shadow: 2px 2px 0 #0b263b;
            display: none;
            align-items: center;
            gap: 6px;
            z-index: 10;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
        `;
        watchIndicator.innerHTML = '<i class="fas fa-hourglass-start"></i> <span>0%</span>';
        pageDiv.appendChild(watchIndicator);
        
        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-canvas';
        canvas.style.width = '100%';
        canvas.style.height = 'auto';
        const ctx = canvas.getContext('2d');
        
        canvas.height = scaledViewport.height;
        canvas.width = scaledViewport.width;
        
        pageDiv.appendChild(canvas);
        pagesContainer.appendChild(pageDiv);
        
        const renderContext = {
            canvasContext: ctx,
            viewport: scaledViewport
        };
        page.render(renderContext);
    });
}

// ===== Check visible pages with 50% threshold and 1-second timer =====
function checkVisiblePages() {
    if (isCompleted || !pagesContainer) return;
    
    const container = document.getElementById('pdfScrollContainer');
    if (!container) return;
    
    const containerRect = container.getBoundingClientRect();
    const now = Date.now();
    let currentlyVisible = new Set();
    
    for (let num = 1; num <= totalPages; num++) {
        if (serverConfirmed.has(num)) continue;
        
        const pageElement = document.getElementById(`page-${num}`);
        if (!pageElement) continue;
        
        const pageRect = pageElement.getBoundingClientRect();
        
        // Calculate visibility percentage
        const visibleHeight = Math.min(pageRect.bottom, containerRect.bottom) - 
                             Math.max(pageRect.top, containerRect.top);
        const pageHeight = pageRect.height;
        const visibilityPercent = Math.max(0, Math.min(100, (visibleHeight / pageHeight) * 100));
        const isVisible = visibilityPercent >= VISIBILITY_THRESHOLD;
        
        const watchIndicator = pageElement.querySelector('.watch-indicator');
        
        if (isVisible) {
            currentlyVisible.add(num);
            
            // Start timer if not already started
            if (!pageVisibleStart[num]) {
                pageVisibleStart[num] = now;
            }
            
            // Calculate progress
            const elapsed = now - pageVisibleStart[num];
            const progress = Math.min(100, Math.round((elapsed / VIEW_TIME_REQUIRED) * 100));
            
            // Update indicator
            if (watchIndicator) {
                watchIndicator.style.display = 'flex';
                
                if (progress < 50) {
                    watchIndicator.innerHTML = '<i class="fas fa-hourglass-start"></i> <span>0%</span>';
                } else if (progress < 100) {
                    watchIndicator.innerHTML = '<i class="fas fa-hourglass-half"></i> <span>50%</span>';
                } else {
                    watchIndicator.innerHTML = '<i class="fas fa-check"></i> <span>Ready</span>';
                    watchIndicator.style.background = 'rgba(40, 167, 69, 0.9)';
                }
            }
            
            // Check if 1 second has elapsed
            if (elapsed >= VIEW_TIME_REQUIRED && !pageViewedConfirmed[num]) {
                trackPageView(num);
                pageViewedConfirmed[num] = true;
                
                const badge = pageElement.querySelector('.viewed-badge');
                if (badge) badge.style.display = 'block';
                
                if (watchIndicator) {
                    watchIndicator.style.display = 'none';
                }
            }
        } else {
            // Page not 50% visible - reset timer
            if (pageVisibleStart[num]) {
                delete pageVisibleStart[num];
            }
            
            if (watchIndicator) {
                watchIndicator.style.display = 'none';
            }
        }
    }
    
    // Clean up timers for pages no longer visible
    for (let num in pageVisibleStart) {
        if (!currentlyVisible.has(parseInt(num))) {
            delete pageVisibleStart[num];
            
            const pageElement = document.getElementById(`page-${num}`);
            if (pageElement) {
                const watchIndicator = pageElement.querySelector('.watch-indicator');
                if (watchIndicator) watchIndicator.style.display = 'none';
            }
        }
    }
}

// Scroll listener
const scrollContainer = document.getElementById('pdfScrollContainer');
if (scrollContainer) {
    let scrollTimeout;
    scrollContainer.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(checkVisiblePages, 100);
    });
    
    // Check every 200ms to update progress indicators
    setInterval(checkVisiblePages, UPDATE_INTERVAL);
}

// Track page view (save to database)
function trackPageView(pageNum) {
    if (serverConfirmed.has(pageNum) || isCompleted) return;
    
    console.log('Page ' + pageNum + ' tracked (1s @ 50%+)');
    
    if (!viewedSet.has(pageNum)) {
        viewedSet.add(pageNum);
        pagesViewed = viewedSet.size;
        updateUI();
    }
    
    // Save to server
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            pdf_page: pageNum,
            total_pages: totalPages
        },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.success) {
                serverConfirmed.add(pageNum);
                
                if (response.pages_viewed !== pagesViewed) {
                    pagesViewed = response.pages_viewed;
                    updateUI();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving page:', error);
        }
    });
}

// Complete button click
if (completeBtn) {
    $('#completeBtn').on('click', function(){
        let btn = $(this);
        
        isCompleted = true;
        
        document.getElementById('pagesViewed').textContent = totalPages;
        document.getElementById('progressPercent').textContent = '100%';
        document.getElementById('progressBar').style.width = '100%';
        
        for (let num = 1; num <= totalPages; num++) {
            const badge = document.querySelector(`#page-${num} .viewed-badge`);
            if (badge) badge.style.display = 'block';
        }
        
        const statusContainer = document.querySelector('.status-container');
        if (statusContainer) {
            statusContainer.innerHTML = '<span class="badge-completed"><i class="fas fa-check-circle"></i> Completed</span>';
        }
        
        btn.replaceWith(`
            <div class="alert alert-success mt-3" style="border-radius: 0; border: 2px solid #1e7e34; box-shadow: 3px 3px 0 #166b2c;">
                <i class="fas fa-graduation-cap"></i>
                Congratulations! You have successfully completed this course 🎓
            </div>
        `);
        
        if (assessmentContainer) {
            assessmentContainer.classList.add('visible');
        }
        
        showToast('🎉 Course completed successfully!', 'success');
        
        $.post(window.location.href, { 
            mark_completed: 1,
            total_pages: totalPages 
        }).done(function(response) {
            if (response.success) {
                console.log('Server confirmed completion');
            }
        }).fail(function() {
            console.log('Background server update failed');
        });
    });
}

// Fullscreen toggle
if (fullscreenBtn) {
    fullscreenBtn.addEventListener('click', function() {
        isFullscreen = !isFullscreen;
        
        if (isFullscreen) {
            mainContent.classList.add('fullscreen-mode');
            fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i> Exit Fullscreen';
        } else {
            mainContent.classList.remove('fullscreen-mode');
            fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i> Fullscreen';
        }
        
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    });
}

// Handle ESC key to exit fullscreen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && isFullscreen) {
        isFullscreen = false;
        mainContent.classList.remove('fullscreen-mode');
        fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i> Fullscreen';
        
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    }
});

// Toast notification function (only used for completion)
function showToast(message, type = 'success') {
    const toast = $(`
        <div class="alert alert-${type} alert-dismissible fade show" style="border-radius: 0; border: 2px solid ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : '#dc3545'}; box-shadow: 3px 3px 0 ${type === 'success' ? '#166b2c' : type === 'warning' ? '#8f6f1a' : '#a11717'};">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);

    $('#toastContainer').append(toast);

    setTimeout(() => {
        toast.fadeOut(500, function() { $(this).remove(); });
    }, 5000);
}

<?php endif; ?>

// Animation on load
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.content-card, .progress-section, .course-info-card, .course-header');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>