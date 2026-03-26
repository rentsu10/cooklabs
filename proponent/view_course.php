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

    // Check if user has access to view enrollees (proponent who created the course OR admin)
    $canViewEnrollees = (is_admin() || $course['proponent_id'] == $u['id']);

    // Check if assessment exists for this course
    $stmt = $pdo->prepare("SELECT * FROM assessments WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();

    // Fetch questions if assessment exists
    $questions = [];
    if ($assessment) {
        $stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY order_num");
        $stmt->execute([$assessment['id']]);
        $questions = $stmt->fetchAll();
    }

    // Check if course is expired
    $today = date('Y-m-d');
    $isCourseExpired = (!empty($course['expires_at']) && $today > $course['expires_at']);

    // ============================================
    // FETCH ENROLLED STUDENTS - WITH ASSESSMENT SCORES (EXCLUDING ARCHIVED)
    // ============================================

    // 1. ALL enrolled students with their assessment info (excluding archived)
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
            e.is_archived,
            DATE_FORMAT(e.enrolled_at, "%M %d, %Y") as enrolled_date,
            DATE_FORMAT(e.completed_at, "%M %d, %Y") as completed_date,
            -- Get the latest assessment score if exists
            (
                SELECT aa.score 
                FROM assessment_attempts aa 
                JOIN assessments a ON aa.assessment_id = a.id
                WHERE a.course_id = ? 
                AND aa.user_id = u.id 
                AND aa.status = "completed"
                ORDER BY aa.completed_at DESC 
                LIMIT 1
            ) as latest_score,
            -- Check if they passed the assessment
            CASE 
                WHEN a.id IS NOT NULL THEN
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM assessment_attempts aa 
                            WHERE aa.assessment_id = a.id 
                            AND aa.user_id = u.id 
                            AND aa.status = "completed"
                            AND aa.score >= a.passing_score
                        ) THEN "Passed"
                        ELSE "Failed/Not Taken"
                    END
                ELSE "No Assessment"
            END as assessment_status,
            -- Count total attempts
            (
                SELECT COUNT(*) 
                FROM assessment_attempts aa 
                WHERE aa.assessment_id = a.id 
                AND aa.user_id = u.id
            ) as attempt_count,
            CASE 
                WHEN e.status = "completed" THEN "badge-completed"
                WHEN e.status = "ongoing" THEN "badge-ongoing"
                WHEN e.status = "expired" THEN "badge-expired"
                ELSE "badge-notenrolled"
            END as status_color,
            CASE 
                WHEN e.status = "completed" THEN "Completed"
                WHEN e.status = "ongoing" THEN "Ongoing"
                WHEN e.status = "expired" THEN "Expired"
                ELSE "Not Started"
            END as status_text
        FROM enrollments e
        JOIN users u ON e.user_id = u.id 
        LEFT JOIN assessments a ON a.course_id = e.course_id
        WHERE e.course_id = ? AND e.is_archived = 0
        GROUP BY u.id
        ORDER BY 
            CASE e.status 
                WHEN "ongoing" THEN 1 
                WHEN "completed" THEN 2 
                WHEN "expired" THEN 3
                ELSE 4 
            END,
            e.enrolled_at DESC
    ');
    $stmt->execute([$courseId, $courseId]);
    $enrolledStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Count statistics with all statuses including archived
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_enrolled,
            SUM(CASE WHEN status = "ongoing" AND is_archived = 0 THEN 1 ELSE 0 END) as ongoing_count,
            SUM(CASE WHEN status = "completed" AND is_archived = 0 THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = "expired" AND is_archived = 0 THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived_count
        FROM enrollments 
        WHERE course_id = ?
    ');
    $stmt->execute([$courseId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch ALL students NOT enrolled in this course (for enrollment modal)
    $stmt = $pdo->prepare('
        SELECT 
            u.id, 
            u.fname, 
            u.lname, 
            u.email,
            u.username,
            u.role,
            u.created_at,
            DATE_FORMAT(u.created_at, "%M %d, %Y") as joined_date
        FROM users u
        WHERE u.id NOT IN (
            SELECT user_id FROM enrollments WHERE course_id = ? AND is_archived = 0
        )
        AND u.role = "user"
        ORDER BY u.fname ASC
    ');
    $stmt->execute([$courseId]);
    $availableStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle AJAX request to drop student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'drop_student') {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($studentId && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                $pdo->beginTransaction();
                
                // Delete assessment answers first (through attempts)
                $stmt = $pdo->prepare("
                    DELETE aa FROM assessment_answers aa
                    INNER JOIN assessment_attempts at ON aa.attempt_id = at.id
                    WHERE at.user_id = ? AND at.assessment_id IN (
                        SELECT id FROM assessments WHERE course_id = ?
                    )
                ");
                $stmt->execute([$studentId, $courseId]);
                
                // Delete assessment attempts
                $stmt = $pdo->prepare("
                    DELETE FROM assessment_attempts 
                    WHERE user_id = ? AND assessment_id IN (
                        SELECT id FROM assessments WHERE course_id = ?
                    )
                ");
                $stmt->execute([$studentId, $courseId]);
                
                // Delete PDF progress
                $stmt = $pdo->prepare("
                    DELETE pp FROM pdf_progress pp
                    INNER JOIN enrollments e ON pp.enrollment_id = e.id
                    WHERE e.user_id = ? AND e.course_id = ?
                ");
                $stmt->execute([$studentId, $courseId]);
                
                // Delete enrollment
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$studentId, $courseId]);
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Student dropped successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error dropping student: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters or insufficient permissions']);
        }
        exit;
    }

    // Handle AJAX request to archive individual student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_student') {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($studentId && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE enrollments 
                    SET is_archived = 1 
                    WHERE user_id = ? AND course_id = ? AND is_archived = 0
                ");
                $stmt->execute([$studentId, $courseId]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Student archived successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student already archived or not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error archiving student: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters or insufficient permissions']);
        }
        exit;
    }

    // Handle AJAX request to archive multiple selected students
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_multiple') {
        $courseId = intval($_POST['course_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        
        if (!empty($studentIds) && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                $pdo->beginTransaction();
                $archived = 0;
                
                foreach ($studentIds as $studentId) {
                    $studentId = intval($studentId);
                    $stmt = $pdo->prepare("
                        UPDATE enrollments 
                        SET is_archived = 1 
                        WHERE user_id = ? AND course_id = ? AND is_archived = 0
                    ");
                    $stmt->execute([$studentId, $courseId]);
                    if ($stmt->rowCount() > 0) {
                        $archived++;
                    }
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => "$archived student(s) archived successfully"]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error archiving students: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters or insufficient permissions']);
        }
        exit;
    }

    // Handle AJAX request to enroll student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_student') {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($studentId && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                // Check if already enrolled
                $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND is_archived = 0");
                $stmt->execute([$studentId, $courseId]);
                
                if (!$stmt->fetch()) {
                    // Create enrollment
                    $stmt = $pdo->prepare('
                        INSERT INTO enrollments 
                        (user_id, course_id, enrolled_at, status, progress) 
                        VALUES (?, ?, NOW(), "ongoing", 0)
                    ');
                    $stmt->execute([$studentId, $courseId]);
                    
                    echo json_encode(['success' => true, 'message' => 'Student enrolled successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student is already enrolled']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error enrolling student: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters or insufficient permissions']);
        }
        exit;
    }

    // Handle AJAX request to archive completed students
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_completed') {
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                // Archive all completed students
                $stmt = $pdo->prepare("
                    UPDATE enrollments 
                    SET is_archived = 1 
                    WHERE course_id = ? AND status = 'completed' AND is_archived = 0
                ");
                $stmt->execute([$courseId]);
                
                $archivedCount = $stmt->rowCount();
                
                echo json_encode(['success' => true, 'message' => "$archivedCount students archived successfully"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error archiving students: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        }
        exit;
    }

    // Handle AJAX request to process expired courses
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_expired') {
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                $pdo->beginTransaction();
                
                // Check if course is expired
                $today = date('Y-m-d');
                $isExpired = (!empty($course['expires_at']) && $today > $course['expires_at']);
                
                if ($isExpired) {
                    // Archive completed students
                    $stmt = $pdo->prepare("
                        UPDATE enrollments 
                        SET is_archived = 1 
                        WHERE course_id = ? AND status = 'completed' AND is_archived = 0
                    ");
                    $stmt->execute([$courseId]);
                    $archivedCount = $stmt->rowCount();
                    
                    // Mark ongoing students as expired
                    $stmt = $pdo->prepare("
                        UPDATE enrollments 
                        SET status = 'expired' 
                        WHERE course_id = ? AND status = 'ongoing' AND is_archived = 0
                    ");
                    $stmt->execute([$courseId]);
                    $expiredCount = $stmt->rowCount();
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Course expired: $archivedCount completed students archived, $expiredCount ongoing students marked as expired"
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Course is not yet expired']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error processing expired course: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        }
        exit;
    }
    
    // Handle AJAX request to enroll multiple students
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_multiple') {
        session_start();
        $courseId = intval($_POST['course_id'] ?? 0);
        $studentIds = $_POST['enroll_ids'] ?? [];
        
        if (!empty($studentIds) && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                $pdo->beginTransaction();
                $enrolled = 0;
                $skipped = 0;
                
                foreach ($studentIds as $studentId) {
                    $studentId = intval($studentId);
                    
                    // Check if already enrolled
                    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND is_archived = 0");
                    $stmt->execute([$studentId, $courseId]);
                    
                    if (!$stmt->fetch()) {
                        // Create enrollment
                        $stmt = $pdo->prepare('
                            INSERT INTO enrollments 
                            (user_id, course_id, enrolled_at, status, progress) 
                            VALUES (?, ?, NOW(), "ongoing", 0)
                        ');
                        $stmt->execute([$studentId, $courseId]);
                        $enrolled++;
                    } else {
                        $skipped++;
                    }
                }
                
                $pdo->commit();
                
                // Set success message
                $_SESSION['success'] = "$enrolled student(s) enrolled successfully";
                if ($skipped > 0) {
                    $_SESSION['warning'] = "$skipped student(s) were already enrolled";
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Error enrolling students: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Invalid parameters or insufficient permissions';
        }
        
        // Redirect back to the same page with the modal open
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $courseId . '#enrolleesModal');
        exit;
    }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=htmlspecialchars($course['title'])?> - CookLabs LMS</title>
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/viewcourse.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="course-content-wrapper" id="mainContent">
        <!-- Course Header -->
        <div class="course-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div style="flex: 1;">
                    <h3><?=htmlspecialchars($course['title'])?></h3>
                    <p><?=nl2br(htmlspecialchars($course['description']))?></p>
                </div>
            </div>
        </div>

        <!-- Instructor Info -->
        <div class="course-info-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="course-instructor">
                    <div class="instructor-avatar">
                        <?= substr($course['fname'] ?? 'I', 0, 1) . substr($course['lname'] ?? 'nstructor', 0, 1) ?>
                    </div>
                    <div class="instructor-info">
                        <h5><?= htmlspecialchars($course['fname'] ?? 'Instructor') ?> <?= htmlspecialchars($course['lname'] ?? '') ?></h5>
                        <p>Course Instructor</p>
                    </div>
                </div>
                
                <!-- Button to toggle List of enrollees - Only for proponent/admin -->
                <?php if($canViewEnrollees): ?>
                <button class="btn-view-enrollees" type="button" data-bs-toggle="modal" data-bs-target="#enrolleesModal">
                    <i class="fas fa-users"></i> View Enrollees (<?= $stats['total_enrolled'] ?? 0 ?>)
                </button>
                <?php endif; ?>
            </div>
        </div>

<!-- Enrollees Modal (with split layout) - Only shown if user has permission -->
<?php if($canViewEnrollees): ?>
<div class="modal fade" id="enrolleesModal" tabindex="-1" aria-labelledby="enrolleesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="enrolleesModalLabel">
                    <i class="fas fa-users me-2"></i>
                    Enrolled Students - <?= htmlspecialchars($course['title']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="modal-split-layout">
                    <!-- LEFT SIDE: Enrolled Students List -->
                    <div class="modal-left" id="modalLeft">
                        <!-- Students Stats with Search Inline -->
                        <div class="stats-search-row">
                            <div class="students-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>    
                                    Total: <?= $stats['total_enrolled'] ?? 0 ?>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-spinner"></i>
                                    Ongoing: <?= $stats['ongoing_count'] ?? 0 ?>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-check-circle"></i>
                                    Completed: <?= $stats['completed_count'] ?? 0 ?>
                                </div>
                            </div>
                            
                            <!-- Search Bar and Select Multiple Button inline -->
                            <div class="action-button-group" style="display: flex; gap: 0.5rem; align-items: center;">
                                <button class="btn-view-enrollees" id="selectMultipleBtn" onclick="toggleSelectMode()" style="white-space: nowrap;">
                                    <i class="fas fa-check-double"></i> Select Multiple
                                </button>
                                
                                <!-- Search Bar -->
                                <div class="search-container" style="width: 250px;">
                                    <input type="text" id="enrolledSearch" class="search-input" placeholder="Search enrolled students...">
                                    <span class="search-icon"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                        </div>

                        <!-- Students Table -->
                        <?php if(count($enrolledStudents) > 0): ?>
                            <div class="table-responsive" id="enrolledTableContainer">
                                <table class="table" id="enrolledTable">
                                    <thead>
                                        32
                                            <th class="select-checkbox" style="display: none; width: 40px;">
                                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Student</th>
                                            <th>Progress</th>
                                            <th>Assessment</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($enrolledStudents as $student): 
                                            // Determine if student should be marked as completed
                                            $isPdfComplete = ($student['pages_viewed'] >= $course['total_pages']);
                                            $isAssessmentPassed = ($student['assessment_status'] === 'Passed');
                                            
                                            // Completion logic: PDF complete AND (no assessment OR assessment passed)
                                            $shouldBeCompleted = $isPdfComplete && (!$assessment || $isAssessmentPassed);
                                            
                                            // Override status if conditions are met
                                            $displayStatus = $shouldBeCompleted ? 'Completed' : $student['status_text'];
                                            $displayStatusColor = $shouldBeCompleted ? 'badge-completed' : $student['status_color'];
                                        ?>
                                        <tr class="enrolled-row" 
                                            data-id="<?= $student['id'] ?>"
                                            data-name="<?= strtolower(htmlspecialchars($student['fname'] . ' ' . $student['lname'])) ?>"
                                            data-email="<?= strtolower(htmlspecialchars($student['email'] ?? '')) ?>"
                                            data-username="<?= strtolower(htmlspecialchars($student['username'] ?? '')) ?>">
                                            <td class="select-checkbox" style="display: none;">
                                                <input type="checkbox" class="student-checkbox" value="<?= $student['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?= strtoupper(substr($student['fname'] ?? '', 0, 1) . substr($student['lname'] ?? '', 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($student['fname'] ?? '') ?> <?= htmlspecialchars($student['lname'] ?? '') ?></strong>
                                                        <small class="d-block text-muted"><?= htmlspecialchars($student['email'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold"><?= intval($student['progress'] ?? 0) ?>%</span>
                                                    <div class="progress-mini">
                                                        <div class="progress-mini-bar" style="width: <?= intval($student['progress'] ?? 0) ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($student['assessment_status'] === 'Passed'): ?>
                                                    <span class="assessment-passed">
                                                        <i class="fas fa-check-circle"></i> Passed
                                                    </span>
                                                <?php elseif($student['assessment_status'] === 'Failed/Not Taken'): ?>
                                                    <span class="assessment-failed">
                                                        <i class="fas fa-times-circle"></i> Failed/Not Taken
                                                    </span>
                                                <?php else: ?>
                                                    <span class="assessment-none">
                                                        <i class="fas fa-minus-circle"></i> No Assessment
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($student['latest_score']): ?>
                                                    <strong class="<?= $student['latest_score'] >= ($assessment['passing_score'] ?? 0) ? 'text-success' : 'text-danger' ?>">
                                                        <?= $student['latest_score'] ?>%
                                                    </strong>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $displayStatusColor ?>">
                                                    <i class="fas fa-<?= $displayStatus === 'Completed' ? 'check-circle' : 'play-circle' ?>"></i>
                                                    <?= $displayStatus ?>
                                                </span>
                                            </td>
                                            <td class="action-cell">
                                                <button class="btn-drop drop-student" 
                                                        data-student-id="<?= $student['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>"
                                                        data-course-id="<?= $courseId ?>"
                                                        style="margin-right: 5px;">
                                                    <i class="fas fa-user-minus"></i> Drop
                                                </button>
                                                <button class="btn-archive archive-student" 
                                                        data-student-id="<?= $student['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>"
                                                        data-course-id="<?= $courseId ?>">
                                                    <i class="fas fa-archive"></i> Archive
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Batch Action Bar (hidden by default) -->
                            <div id="batchActionBar" class="stats-search-row" style="display: none; margin-top: 1rem;">
                                <div>
                                    <span id="selectedCount" class="fw-bold">0</span> student(s) selected
                                </div>
                                <div class="action-button-group">
                                    <button class="btn-view-enrollees btn-archive" onclick="batchArchive()">
                                        <i class="fas fa-archive"></i> Archive Selected
                                    </button>
                                    <button class="btn-view-enrollees btn-expired" onclick="batchDrop()">
                                        <i class="fas fa-user-minus"></i> Drop Selected
                                    </button>
                                    <button class="btn-view-enrollees" onclick="cancelSelectMode()">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <!-- Empty div to maintain layout -->
                                </div>
                                <div class="text-muted">
                                    <small>Showing <span id="enrolledCount"><?= count($enrolledStudents) ?></span> of <?= $stats['total_enrolled'] ?? 0 ?> students</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h5>No Enrolled Students Yet</h5>
                                <p>This course hasn't been taken by any students yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT SIDE: Enroll New Students (hidden by default) -->
                    <div class="modal-right" id="enrollSection" style="display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h5 class="mb-0" style="border-left: 6px solid #28a745; padding-left: 1rem;">
                                <i class="fas fa-user-plus text-success me-2"></i>Enroll New Students
                            </h5>
                            <button class="btn-view-enrollees" onclick="toggleEnrollSection()" style="background: #dc3545; padding: 0.3rem 0.8rem; font-size: 0.8rem;">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>

                        <!-- Search Bar for available students -->
                        <div class="mb-3">
                            <div class="search-container" style="max-width: 100%;">
                                <input type="text" id="availableSearch" class="search-input" placeholder="Search students...">
                                <span class="search-icon"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <!-- Available Students List with Checkboxes -->
                        <?php if(count($availableStudents) > 0): ?>
                            <div class="table-responsive" style="max-height: 350px;">
                                <table class="table" id="availableTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAllAvailable" onchange="toggleAllAvailable()">
                                            </th>
                                            <th>Student</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($availableStudents as $student): ?>
                                        <tr class="available-row available-checkbox-row" 
                                            data-id="<?= $student['id'] ?>"
                                            data-name="<?= strtolower(htmlspecialchars($student['fname'] . ' ' . $student['lname'])) ?>"
                                            data-email="<?= strtolower(htmlspecialchars($student['email'] ?? '')) ?>">
                                            <td>
                                                <input type="checkbox" class="available-checkbox" value="<?= $student['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                        <?= strtoupper(substr($student['fname'] ?? '', 0, 1) . substr($student['lname'] ?? '', 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($student['fname'] ?? '') ?> <?= htmlspecialchars($student['lname'] ?? '') ?></strong>
                                                        <small class="d-block text-muted"><?= htmlspecialchars($student['email'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <span id="availableCount"><?= count($availableStudents) ?></span> students available
                                    <span id="selectedAvailableCount" class="ms-2 badge bg-primary" style="display: none;">0 selected</span>
                                </div>
                                <button class="btn-view-enrollees" id="enrollSelectedBtn" onclick="enrollSelectedStudents()" style="background: #28a745;">
                                    <i class="fas fa-user-plus"></i> Enroll Selected (<span id="enrollCount">0</span>)
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 2rem;">
                                <i class="fas fa-user-check"></i>
                                <h6>No Students Available</h6>
                                <p class="small">All students are already enrolled.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <!-- Enroll New Button on the left -->
                        <button class="btn-view-enrollees" id="enrollNewBtn" onclick="toggleEnrollSection()" style="background: #28a745;">
                            <i class="fas fa-user-plus"></i> Enroll New
                        </button>
                        <!-- Generate Report Button -->
                        <button class="btn-view-enrollees ms-2" onclick="showReportModal()" style="background: #1661a3;">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </button>
                    </div>
                    <div>
                        <!-- No close button here anymore -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Archive Confirmation Modal -->
<div class="modal fade" id="archiveConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #ffc107;">
                <h5 class="modal-title">
                    <i class="fas fa-archive"></i> Confirm Archive Student
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to archive <strong id="archiveStudentName"></strong> from this course?</p>
                <p class="text-warning small">
                    <i class="fas fa-info-circle"></i> 
                    Archiving will:
                </p>
                <ul class="text-warning small">
                    <li>Hide this student from the enrolled list</li>
                    <li>Remove them from available students for enrollment</li>
                    <li>Keep their progress and assessment data for records</li>
                </ul>
                <p class="text-warning small">You cannot undo this action directly, but you can re-enroll them if needed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-danger" id="confirmArchiveBtn" style="background: #ffc107; border-color: #b88f1f; color: #07223b;">Yes, Archive Student</button>
            </div>
        </div>
    </div>
</div>

<!-- Drop Confirmation Modal -->
<div class="modal fade" id="dropConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3545;">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Drop Student
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to drop <strong id="dropStudentName"></strong> from this course?</p>
                <p class="text-danger small">
                    <i class="fas fa-info-circle"></i> 
                    This will permanently remove:
                </p>
                <ul class="text-danger small">
                    <li>Their enrollment record</li>
                    <li>All PDF progress</li>
                    <li>All assessment attempts and answers</li>
                </ul>
                <p class="text-danger small">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDropBtn">Yes, Drop Student</button>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #1661a3;">
                <h5 class="modal-title" id="reportModalLabel">
                    <i class="fas fa-file-alt me-2"></i>
                    Student Report - <?= htmlspecialchars($course['title']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Report Table -->
                <div class="table-responsive">
                    <table class="table" id="reportTable">
                        <thead>
                            32
                                <th>Student</th>
                                <th>Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <?php foreach($enrolledStudents as $student): 
                                // Determine if student should be marked as completed
                                $isPdfComplete = ($student['pages_viewed'] >= $course['total_pages']);
                                $isAssessmentPassed = ($student['assessment_status'] === 'Passed');
                                
                                // Completion logic: PDF complete AND (no assessment OR assessment passed)
                                $shouldBeCompleted = $isPdfComplete && (!$assessment || $isAssessmentPassed);
                                
                                // Override status if conditions are met
                                $displayStatus = $shouldBeCompleted ? 'Completed' : $student['status_text'];
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="student-avatar me-2" style="width: 30px; height: 30px; font-size: 0.7rem;">
                                            <?= strtoupper(substr($student['fname'] ?? '', 0, 1) . substr($student['lname'] ?? '', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($student['fname'] ?? '') ?> <?= htmlspecialchars($student['lname'] ?? '') ?></strong>
                                            <small class="d-block text-muted"><?= htmlspecialchars($student['email'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($student['latest_score']): ?>
                                        <strong class="<?= $student['latest_score'] >= ($assessment['passing_score'] ?? 0) ? 'text-success' : 'text-danger' ?>">
                                            <?= $student['latest_score'] ?>%
                                        </strong>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $shouldBeCompleted ? 'badge-completed' : ($student['status'] === 'ongoing' ? 'badge-ongoing' : 'badge-expired') ?>">
                                        <?= $displayStatus ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-view-enrollees" onclick="downloadCSV()" style="background: #28a745;">
                    <i class="fas fa-download"></i> Download CSV
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
        
        <!-- Course Summary -->
        <div class="content-card">
            <h4>Course Summary</h4>
            <div class="modern-course-info-content">
                <?= nl2br(htmlspecialchars($course['summary'] ?? 'No summary available.')) ?>
            </div>
        </div>

        <!-- PDF Content -->
        <?php if($course['file_pdf']): ?>
        <div class="content-card">
            <h5><i class="fas fa-file-pdf"></i> Course PDF Material</h5>
            
            <div class="pdf-viewer">
                <iframe
                    src="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>"
                    width="100%"
                    height="600">
                </iframe>
            </div>

            <div class="mt-3">
                <a class="btn-view-enrollees" href="<?= BASE_URL ?>/uploads/pdf/<?= htmlspecialchars($course['file_pdf']) ?>" target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>Open PDF in new tab
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assessment Section - For Proponent View -->
        <?php if($assessment): ?>
        <div class="content-card assessment-container">
            <div class="assessment-header">
                <h5 class="assessment-title">
                    <i class="fas fa-file-alt" style="color: #1d6fb0; margin-right: 8px;"></i>
                    Course Assessment
                </h5>
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="assessment-stats">
                        <span class="stat-pill">
                            <i class="fas fa-list"></i> <?= count($questions) ?> Questions
                        </span>
                        <?php if($assessment['time_limit']): ?>
                        <span class="stat-pill">
                            <i class="fas fa-clock"></i> <?= $assessment['time_limit'] ?> min
                        </span>
                        <?php endif; ?>
                        <span class="stat-pill">
                            <i class="fas fa-check-circle"></i> Passing: <?= $assessment['passing_score'] ?>%
                        </span>
                        <?php if($assessment['attempts_allowed']): ?>
                        <span class="stat-pill">
                            <i class="fas fa-redo"></i> <?= $assessment['attempts_allowed'] ?> attempts
                        </span>
                        <?php endif; ?>
                    </div>
                    <button id="fullscreenBtn" class="fullscreen-btn">
                        <i class="fas fa-expand"></i> Fullscreen
                    </button>
                </div>
            </div>

            <?php if(!empty($questions)): ?>
                <div class="assessment-scroll-container">
                    <div class="question-list">
                        <?php foreach($questions as $index => $q): ?>
                            <div class="question-item">
                                <div class="question-header">
                                    <span class="question-number">Question <?= $index + 1 ?></span>
                                    <span class="question-points"><?= $q['points'] ?> points</span>
                                </div>
                                <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>
                                
                                <div class="options-grid">
                                    <?php 
                                    $options = [
                                        'A' => $q['option_a'],
                                        'B' => $q['option_b'],
                                        'C' => $q['option_c'],
                                        'D' => $q['option_d']
                                    ];
                                    foreach($options as $letter => $text):
                                        if(empty($text)) continue;
                                        $isCorrect = ($letter === $q['correct_option']);
                                    ?>
                                    <div class="option-item <?= $isCorrect ? 'option-correct' : '' ?>">
                                        <span class="option-letter"><?= $letter ?></span>
                                        <span><?= htmlspecialchars($text) ?></span>
                                        <?php if($isCorrect): ?>
                                            <span class="correct-badge">✓ Correct</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-assessment">
                    <i class="fas fa-file-alt"></i>
                    <h5>No Questions Added</h5>
                    <p>This assessment has no questions yet.</p>
                </div>
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

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animation
        const cards = document.querySelectorAll('.content-card, .course-info-card, .course-header');
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

    // Fullscreen toggle for assessment
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const mainContent = document.getElementById('mainContent');
    let isFullscreen = false;

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
        });
    }

    // Handle ESC key to exit fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isFullscreen) {
            isFullscreen = false;
            mainContent.classList.remove('fullscreen-mode');
            if (fullscreenBtn) {
                fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i> Fullscreen';
            }
        }
    });

    // Drop, Archive, Enroll functionality - NO RELOADS, ALL DYNAMIC
    $(document).ready(function() {
        let currentStudentId = null;
        let currentStudentName = null;
        let currentRow = null;
        let currentAction = null; // 'drop' or 'archive'

        // ===== SEARCH FUNCTIONALITY =====
        function filterTable(searchTerm, tableId, rowClass) {
            let visibleCount = 0;
            $(tableId + ' tbody ' + rowClass).each(function() {
                const name = $(this).data('name') || '';
                const email = $(this).data('email') || '';
                const username = $(this).data('username') || '';
                
                if (name.includes(searchTerm) || email.includes(searchTerm) || username.includes(searchTerm)) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });
            return visibleCount;
        }

        // Search in enrolled students
        $('#enrolledSearch').on('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const visibleCount = filterTable(searchTerm, '#enrolledTable', '.enrolled-row');
            $('#enrolledCount').text(visibleCount);
        });

        // Search in available students
        $('#availableSearch').on('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const visibleCount = filterTable(searchTerm, '#availableTable', '.available-row');
            $('#availableCount').text(visibleCount);
        });

        // ===== ARCHIVE STUDENT (INDIVIDUAL) =====
        $(document).on('click', '.archive-student', function() {
            currentStudentId = $(this).data('student-id');
            currentStudentName = $(this).data('student-name');
            currentRow = $(this).closest('tr');
            currentAction = 'archive';
            $('#archiveStudentName').text(currentStudentName);
            $('#archiveConfirmModal').modal('show');
        });

        // ===== DROP STUDENT (INDIVIDUAL) =====
        $(document).on('click', '.drop-student', function() {
            currentStudentId = $(this).data('student-id');
            currentStudentName = $(this).data('student-name');
            currentRow = $(this).closest('tr');
            currentAction = 'drop';
            $('#dropStudentName').text(currentStudentName);
            $('#dropConfirmModal').modal('show');
        });

        // Confirm Archive
        $('#confirmArchiveBtn').on('click', function() {
            if (!currentStudentId || currentAction !== 'archive') return;

            const button = $(this);
            const originalText = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Archiving...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'archive_student',
                    student_id: currentStudentId,
                    course_id: <?= $courseId ?>
                },
                dataType: 'json',
                success: function(response) {
                    $('#archiveConfirmModal').modal('hide');
                    
                    if (response.success) {
                        // Remove the row from enrolled students table
                        currentRow.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update counts
                            const enrolledCount = $('#enrolledTable tbody tr').length;
                            $('#enrolledCount').text(enrolledCount);
                            updateStatsFromTable();
                            
                            // Show empty state if no more students
                            if (enrolledCount === 0) {
                                $('#enrolledTableContainer').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-user-graduate"></i>
                                        <h5>No Enrolled Students Yet</h5>
                                        <p>This course hasn't been taken by any students yet.</p>
                                    </div>
                                `);
                            }
                            
                            showModalToast('success', `${currentStudentName} archived successfully!`);
                        });
                    } else {
                        showModalToast('error', response.message || 'Error archiving student');
                    }
                    
                    button.prop('disabled', false).html('Yes, Archive Student');
                    currentStudentId = null;
                    currentStudentName = null;
                    currentRow = null;
                    currentAction = null;
                },
                error: function() {
                    $('#archiveConfirmModal').modal('hide');
                    showModalToast('error', 'Server error occurred');
                    button.prop('disabled', false).html('Yes, Archive Student');
                }
            });
        });

        // Confirm Drop
        $('#confirmDropBtn').on('click', function() {
            if (!currentStudentId || currentAction !== 'drop') return;

            const button = $(this);
            const originalText = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Dropping...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'drop_student',
                    student_id: currentStudentId,
                    course_id: <?= $courseId ?>
                },
                dataType: 'json',
                success: function(response) {
                    $('#dropConfirmModal').modal('hide');
                    
                    if (response.success) {
                        // Get student info before removing
                        const studentRow = currentRow;
                        const studentName = currentStudentName;
                        const studentEmail = studentRow.find('td:first small').text();
                        const studentAvatar = studentRow.find('.student-avatar').text();
                        
                        // Remove the row from enrolled students table
                        studentRow.remove();
                        
                        // Add the student back to available table
                        addStudentToAvailableTable(currentStudentId, studentName, studentEmail, studentAvatar);
                        
                        // Update enrolled count
                        const enrolledCount = $('#enrolledTable tbody tr').length;
                        $('#enrolledCount').text(enrolledCount);
                        
                        // Update stats
                        updateStatsAfterDrop();
                        
                        // Show empty state if no more students
                        if (enrolledCount === 0) {
                            $('#enrolledTableContainer').html(`
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate"></i>
                                    <h5>No Enrolled Students Yet</h5>
                                    <p>This course hasn't been taken by any students yet.</p>
                                </div>
                            `);
                        }
                        
                        showModalToast('success', `${currentStudentName} dropped successfully!`);
                    } else {
                        showModalToast('error', response.message || 'Error dropping student');
                    }
                    
                    button.prop('disabled', false).html('Yes, Drop Student');
                    currentStudentId = null;
                    currentStudentName = null;
                    currentRow = null;
                    currentAction = null;
                },
                error: function() {
                    $('#dropConfirmModal').modal('hide');
                    showModalToast('error', 'Server error occurred');
                    button.prop('disabled', false).html('Yes, Drop Student');
                }
            });
        });

        // ===== ARCHIVE COMPLETED STUDENTS =====
        window.archiveCompletedStudents = function() {
            const completedCount = <?= $stats['completed_count'] ?? 0 ?>;
            if (completedCount === 0) {
                showModalToast('info', 'No completed students to archive');
                return;
            }
            
            if (!confirm(`Archive all ${completedCount} completed students?`)) return;
            
            const button = $('.btn-archive');
            const originalText = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Archiving...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'archive_completed',
                    course_id: <?= $courseId ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Remove all completed students from the table
                        $('#enrolledTable tbody tr').each(function() {
                            const statusCell = $(this).find('td:eq(5) .status-badge');
                            if (statusCell.text().includes('Completed')) {
                                $(this).remove();
                            }
                        });
                        
                        // Update stats
                        updateStatsFromTable();
                        
                        showModalToast('success', response.message);
                    } else {
                        showModalToast('error', response.message);
                    }
                    button.prop('disabled', false).html(originalText);
                },
                error: function() {
                    showModalToast('error', 'Server error occurred');
                    button.prop('disabled', false).html(originalText);
                }
            });
        };

        // ===== BATCH ARCHIVE SELECTED =====
        window.batchArchive = function() {
            const selectedIds = [];
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
                selectedIds.push(cb.value);
            });
            
            if (selectedIds.length === 0) {
                showModalToast('info', 'No students selected');
                return;
            }
            
            if (!confirm(`Archive ${selectedIds.length} selected student(s)?`)) return;
            
            const button = $('#batchActionBar .btn-archive');
            const originalText = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Archiving...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'archive_multiple',
                    student_ids: selectedIds,
                    course_id: <?= $courseId ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Remove selected rows
                        selectedIds.forEach(id => {
                            $(`tr[data-id="${id}"]`).remove();
                        });
                        
                        // Update counts
                        updateStatsFromTable();
                        
                        // Exit select mode
                        toggleSelectMode();
                        
                        showModalToast('success', response.message);
                    } else {
                        showModalToast('error', response.message);
                    }
                    button.prop('disabled', false).html('<i class="fas fa-archive"></i> Archive Selected');
                },
                error: function() {
                    showModalToast('error', 'Server error occurred');
                    button.prop('disabled', false).html('<i class="fas fa-archive"></i> Archive Selected');
                }
            });
        };

        // ===== BATCH DROP SELECTED =====
        window.batchDrop = function() {
            const selectedIds = [];
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
                selectedIds.push(cb.value);
            });
            
            if (selectedIds.length === 0) {
                showModalToast('info', 'No students selected');
                return;
            }
            
            if (!confirm(`Drop ${selectedIds.length} selected student(s)? This action cannot be undone!`)) return;
            
            const button = $('#batchActionBar .btn-expired');
            const originalText = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Dropping...');
            
            let completed = 0;
            let failed = false;
            
            selectedIds.forEach(id => {
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'drop_student',
                        student_id: id,
                        course_id: <?= $courseId ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        completed++;
                        if (response.success) {
                            $(`tr[data-id="${id}"]`).remove();
                        } else {
                            failed = true;
                        }
                        if (completed === selectedIds.length) {
                            button.prop('disabled', false).html('<i class="fas fa-user-minus"></i> Drop Selected');
                            updateStatsFromTable();
                            toggleSelectMode();
                            if (failed) {
                                showModalToast('warning', `${completed} student(s) dropped, some failed`);
                            } else {
                                showModalToast('success', `${completed} student(s) dropped successfully`);
                            }
                        }
                    },
                    error: function() {
                        completed++;
                        failed = true;
                        if (completed === selectedIds.length) {
                            button.prop('disabled', false).html('<i class="fas fa-user-minus"></i> Drop Selected');
                            updateStatsFromTable();
                            toggleSelectMode();
                            showModalToast('error', 'Some students could not be dropped');
                        }
                    }
                });
            });
        };

        // ===== HELPER FUNCTIONS =====

        // Function to add student to available table
        function addStudentToAvailableTable(id, name, email, avatar) {
            // Check if available table exists
            if ($('#availableTable tbody').length) {
                const newRow = `
                    <tr class="available-row available-checkbox-row" 
                        data-id="${id}"
                        data-name="${name.toLowerCase()}"
                        data-email="${email.toLowerCase()}">
                        <td>
                            <input type="checkbox" class="available-checkbox" value="${id}">
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="student-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                    ${avatar}
                                </div>
                                <div>
                                    <strong>${name}</strong>
                                    <small class="d-block text-muted">${email}</small>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
                $('#availableTable tbody').append(newRow);
            } else {
                // Recreate the table
                $('.modal-right .table-responsive').html(`
                    <table class="table" id="availableTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllAvailable" onchange="toggleAllAvailable()">
                                </th>
                                <th>Student</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="available-row available-checkbox-row" 
                                data-id="${id}"
                                data-name="${name.toLowerCase()}"
                                data-email="${email.toLowerCase()}">
                                <td>
                                    <input type="checkbox" class="available-checkbox" value="${id}">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="student-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                            ${avatar}
                                        </div>
                                        <div>
                                            <strong>${name}</strong>
                                            <small class="d-block text-muted">${email}</small>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                `);
            }
            
            // Update available count
            $('#availableCount').text($('#availableTable tbody tr').length);
        }

        // Function to update stats based on current table rows
        function updateStatsFromTable() {
            const totalRows = $('#enrolledTable tbody tr').length;
            const ongoingRows = $('#enrolledTable tbody tr').filter(function() {
                return $(this).find('td:eq(5) .status-badge').text().includes('Ongoing');
            }).length;
            const completedRows = $('#enrolledTable tbody tr').filter(function() {
                return $(this).find('td:eq(5) .status-badge').text().includes('Completed');
            }).length;
            
            // Update stat items
            $('.stat-item:contains("Total")').html(`<i class="fas fa-users"></i> Total: ${totalRows}`);
            $('.stat-item:contains("Ongoing")').html(`<i class="fas fa-spinner"></i> Ongoing: ${ongoingRows}`);
            $('.stat-item:contains("Completed")').html(`<i class="fas fa-check-circle"></i> Completed: ${completedRows}`);
            
            // Update enrolled count display
            $('#enrolledCount').text(totalRows);
        }

        function updateStatsAfterDrop() {
            const totalRows = $('#enrolledTable tbody tr').length;
            const ongoingRows = $('#enrolledTable tbody tr').filter(function() {
                return $(this).find('td:eq(5) .status-badge').text().includes('Ongoing');
            }).length;
            const completedRows = $('#enrolledTable tbody tr').filter(function() {
                return $(this).find('td:eq(5) .status-badge').text().includes('Completed');
            }).length;
            
            $('.stat-item:contains("Total")').html(`<i class="fas fa-users"></i> Total: ${totalRows}`);
            $('.stat-item:contains("Ongoing")').html(`<i class="fas fa-spinner"></i> Ongoing: ${ongoingRows}`);
            $('.stat-item:contains("Completed")').html(`<i class="fas fa-check-circle"></i> Completed: ${completedRows}`);
            
            $('#enrolledCount').text(totalRows);
        }

        // Toast function for modal
        function showModalToast(type, message) {
            const bgColor = type === 'success' ? '#28a745' : (type === 'info' ? '#17a2b8' : (type === 'warning' ? '#ffc107' : '#dc3545'));
            const icon = type === 'success' ? 'fa-check-circle' : (type === 'info' ? 'fa-info-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'));
            
            // Remove any existing toasts in modal
            $('#enrolleesModal .modal-toast').remove();
            
            const toast = $(`
                <div class="alert modal-toast" style="
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    z-index: 1060;
                    border: 3px solid #1a4b77;
                    border-radius: 0;
                    background: ${bgColor};
                    color: ${type === 'warning' ? '#07223b' : 'white'};
                    padding: 1rem;
                    min-width: 250px;
                    box-shadow: 8px 8px 0 #123a5e;
                    animation: slideIn 0.3s ease;
                ">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `);
            
            $('#enrolleesModal .modal-body').append(toast);
            
            setTimeout(() => {
                toast.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        }
    });

    // Toggle Enroll New Students section
    function toggleEnrollSection() {
        const enrollSection = document.getElementById('enrollSection');
        const modalLeft = document.getElementById('modalLeft');
        const enrollBtn = document.getElementById('enrollNewBtn');
        
        if (enrollSection.style.display === 'none') {
            // Show enroll section
            enrollSection.style.display = 'block';
            modalLeft.style.flex = '1.5'; // Reduce left side width
            enrollBtn.innerHTML = '<i class="fas fa-times"></i> Close';
            enrollBtn.style.background = '#dc3545';
        } else {
            // Hide enroll section
            enrollSection.style.display = 'none';
            modalLeft.style.flex = '2'; // Restore left side width
            enrollBtn.innerHTML = '<i class="fas fa-user-plus"></i> Enroll New';
            enrollBtn.style.background = '#28a745';
        }
    }

    // Toggle all available checkboxes
    function toggleAllAvailable() {
        const selectAll = document.getElementById('selectAllAvailable').checked;
        document.querySelectorAll('.available-checkbox').forEach(cb => {
            cb.checked = selectAll;
        });
        updateSelectedAvailableCount();
    }

    // Update selected count
    function updateSelectedAvailableCount() {
        const count = document.querySelectorAll('.available-checkbox:checked').length;
        document.getElementById('enrollCount').textContent = count;
        
        const badge = document.getElementById('selectedAvailableCount');
        if (count > 0) {
            badge.style.display = 'inline';
            badge.textContent = count + ' selected';
        } else {
            badge.style.display = 'none';
        }
    }

    // Add change event to available checkboxes
    $(document).on('change', '.available-checkbox', function() {
        updateSelectedAvailableCount();
        
        // Update select all checkbox
        const totalCheckboxes = document.querySelectorAll('.available-checkbox').length;
        const checkedCheckboxes = document.querySelectorAll('.available-checkbox:checked').length;
        document.getElementById('selectAllAvailable').checked = totalCheckboxes === checkedCheckboxes;
    });

    // Make rows clickable to toggle checkbox
    $(document).on('click', '.available-checkbox-row', function(e) {
        if (e.target.type === 'checkbox') return;
        const checkbox = $(this).find('.available-checkbox')[0];
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            $(checkbox).trigger('change');
        }
    });

    // Enroll selected students
    function enrollSelectedStudents() {
        const selectedIds = [];
        document.querySelectorAll('.available-checkbox:checked').forEach(cb => {
            selectedIds.push(cb.value);
        });
        
        if (selectedIds.length === 0) {
            showModalToast('info', 'No students selected');
            return;
        }
        
        if (!confirm(`Enroll ${selectedIds.length} selected student(s)?`)) return;
        
        const button = $('#enrollSelectedBtn');
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enrolling...');
        
        // Create a form to submit multiple enrollments
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Add all selected IDs
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'enroll_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        // Add action and course_id
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'enroll_multiple';
        form.appendChild(actionInput);
        
        const courseInput = document.createElement('input');
        courseInput.type = 'hidden';
        courseInput.name = 'course_id';
        courseInput.value = '<?= $courseId ?>';
        form.appendChild(courseInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    // Show report modal
    function showReportModal() {
        $('#reportModal').modal('show');
    }

    // Download CSV
    function downloadCSV() {
        // Get data from the report table
        const rows = [];
        const headers = ['Student', 'Score', 'Status'];
        rows.push(headers.join(','));
        
        // Get data rows (only visible rows, respecting search filter)
        const visibleRows = document.querySelectorAll('#enrolledTable tbody tr:not([style*="display: none"])');
        
        visibleRows.forEach(row => {
            const studentName = row.querySelector('td:nth-child(2) strong').textContent;
            const studentEmail = row.querySelector('td:nth-child(2) small').textContent;
            const score = row.querySelector('td:nth-child(5)')?.textContent.trim() || '—';
            const status = row.querySelector('td:nth-child(6) .status-badge').textContent.trim();
            
            // Combine name and email for the Student column
            const student = `${studentName} (${studentEmail})`;
            
            // Clean up data
            const cleanStudent = student.replace(/,/g, ''); // Remove commas to avoid CSV issues
            const cleanScore = score.replace('%', '');
            const cleanStatus = status.replace(/\s+/g, ' ').trim();
            
            rows.push(`"${cleanStudent}",${cleanScore},"${cleanStatus}"`);
        });
        
        if (rows.length === 1) {
            showModalToast('info', 'No data to export');
            return;
        }
        
        // Create CSV content
        const csvContent = rows.join('\n');
        
        // Create download link
        const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        
        // Get current date for filename
        const date = new Date();
        const dateStr = date.toISOString().split('T')[0];
        const filename = `student_report_<?= $courseId ?>_${dateStr}.csv`;
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        showModalToast('success', `Report downloaded with ${visibleRows.length} students`);
    }

    // Select Multiple Mode Functions
    let selectMode = false;

    function toggleSelectMode() {
        selectMode = !selectMode;
        const checkboxes = document.querySelectorAll('.select-checkbox');
        const actionCells = document.querySelectorAll('.action-cell');
        const selectBtn = document.getElementById('selectMultipleBtn');
        const batchBar = document.getElementById('batchActionBar');
        const rows = document.querySelectorAll('.enrolled-row');
        
        if (selectMode) {
            // Show checkboxes, hide action buttons
            checkboxes.forEach(cb => cb.style.display = 'table-cell');
            actionCells.forEach(cell => cell.style.display = 'none');
            selectBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Selection';
            selectBtn.style.background = '#dc3545';
            batchBar.style.display = 'flex';
            
            // Add click handler to rows
            rows.forEach(row => {
                row.classList.add('clickable');
                row.addEventListener('click', handleRowClick);
            });
            
            // Uncheck all checkboxes and remove highlights
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                cb.checked = false;
                cb.closest('tr').classList.remove('selected-row');
            });
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedCount();
        } else {
            // Hide checkboxes, show action buttons
            checkboxes.forEach(cb => cb.style.display = 'none');
            actionCells.forEach(cell => cell.style.display = 'table-cell');
            selectBtn.innerHTML = '<i class="fas fa-check-double"></i> Select Multiple';
            selectBtn.style.background = '';
            batchBar.style.display = 'none';
            
            // Remove click handler and highlights from rows
            rows.forEach(row => {
                row.classList.remove('clickable');
                row.classList.remove('selected-row');
                row.removeEventListener('click', handleRowClick);
            });
        }
    }

    function handleRowClick(event) {
        // Don't toggle if clicking on the checkbox itself (to avoid double toggling)
        if (event.target.type === 'checkbox') return;
        // Don't toggle if clicking on buttons
        if (event.target.classList.contains('btn-drop') || event.target.closest('.btn-drop')) return;
        if (event.target.classList.contains('archive-student') || event.target.closest('.archive-student')) return;
        
        const row = event.currentTarget;
        const checkbox = row.querySelector('.student-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            // Toggle highlight class
            if (checkbox.checked) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
            // Trigger change event to update select all and count
            $(checkbox).trigger('change');
        }
    }

    function cancelSelectMode() {
        if (selectMode) {
            toggleSelectMode();
        }
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAllCheckbox').checked;
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.checked = selectAll;
            const row = cb.closest('tr');
            if (selectAll) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const count = document.querySelectorAll('.student-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count;
    }

    // Add change event to individual checkboxes
    $(document).on('change', '.student-checkbox', function() {
        const row = $(this).closest('tr')[0];
        if (this.checked) {
            row.classList.add('selected-row');
        } else {
            row.classList.remove('selected-row');
        }
        
        updateSelectedCount();
        
        // Update select all checkbox
        const totalCheckboxes = document.querySelectorAll('.student-checkbox').length;
        const checkedCheckboxes = document.querySelectorAll('.student-checkbox:checked').length;
        document.getElementById('selectAllCheckbox').checked = totalCheckboxes === checkedCheckboxes;
    });
    </script>
</body>
</html>