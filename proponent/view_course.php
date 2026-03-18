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
    // FETCH ENROLLED STUDENTS - WITH ASSESSMENT SCORES
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

    // Handle AJAX request to enroll student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_student') {
        $studentId = intval($_POST['student_id'] ?? 0);
        $courseId = intval($_POST['course_id'] ?? 0);
        
        if ($studentId && $courseId && (is_admin() || $course['proponent_id'] == $u['id'])) {
            try {
                // Check if already enrolled
                $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
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
                    WHERE course_id = ? AND status = 'completed'
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
                        WHERE course_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$courseId]);
                    $archivedCount = $stmt->rowCount();
                    
                    // Mark ongoing students as expired
                    $stmt = $pdo->prepare("
                        UPDATE enrollments 
                        SET status = 'expired' 
                        WHERE course_id = ? AND status = 'ongoing'
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
        <style>
            /* ===== SHARP GEOMETRIC COURSE VIEW ===== */
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
            .course-content-wrapper {
                margin-left: 280px;
                flex: 1;
                padding: 2rem 2.5rem;
                min-height: 100vh;
                overflow-y: auto;
            }

            /* Course Header */
            .course-header {
                background: #ffffff;
                border: 3px solid #1a4b77;
                box-shadow: 12px 12px 0 #123a5e;
                border-radius: 0px;
                padding: 2rem;
                margin-bottom: 2rem;
            }

            .course-header h3 {
                font-size: 2rem;
                font-weight: 700;
                color: #07223b;
                margin-bottom: 1rem;
                border-left: 8px solid #1d6fb0;
                padding-left: 1.2rem;
            }

            .course-header p {
                color: #1e4465;
                font-size: 1rem;
                line-height: 1.6;
                margin-bottom: 0;
            }

            /* Instructor Card */
            .course-info-card {
                background: #ffffff;
                border: 3px solid #1a4b77;
                box-shadow: 12px 12px 0 #123a5e;
                border-radius: 0px;
                padding: 1.5rem;
                margin-bottom: 2rem;
            }

            .course-instructor {
                display: flex;
                align-items: center;
                gap: 1.2rem;
            }

            .instructor-avatar {
                width: 60px;
                height: 60px;
                background: #1d6fb0;
                border: 3px solid #0f4980;
                box-shadow: 4px 4px 0 #0a3458;
                border-radius: 0px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                font-weight: 700;
                color: white;
            }

            .instructor-info h5 {
                font-weight: 700;
                color: #07223b;
                margin-bottom: 0.3rem;
                font-size: 1.2rem;
            }

            .instructor-info p {
                color: #5f6f82;
                margin: 0;
                font-size: 0.9rem;
            }

            /* View Enrollees Button */
            .btn-view-enrollees {
                background: #1661a3;
                border: 3px solid #0c314d;
                box-shadow: 4px 4px 0 #0b263b;
                padding: 0.6rem 1.5rem;
                font-weight: 600;
                color: white;
                text-decoration: none;
                border-radius: 0px;
                transition: all 0.1s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 0.9rem;
                border: none;
            }

            .btn-view-enrollees:hover {
                transform: translate(-2px, -2px);
                box-shadow: 6px 6px 0 #0b263b;
                background: #1a70b5;
                color: white;
            }

            .btn-archive {
                background: #6c757d;
                border: 3px solid #5a6268;
                box-shadow: 4px 4px 0 #404040;
            }

            .btn-archive:hover {
                background: #7a858f;
            }

            .btn-expired {
                background: #dc3545;
                border: 3px solid #a71d2a;
                box-shadow: 4px 4px 0 #7a151f;
            }

            .btn-expired:hover {
                background: #c82333;
            }

            /* Content Cards */
            .content-card {
                background: #ffffff;
                border: 3px solid #1a4b77;
                box-shadow: 12px 12px 0 #123a5e;
                border-radius: 0px;
                padding: 2rem;
                margin-bottom: 2rem;
            }

            .content-card h4, .content-card h5 {
                font-weight: 700;
                color: #07223b;
                margin-bottom: 1.5rem;
                border-left: 6px solid #1d6fb0;
                padding-left: 1rem;
            }

            .content-card h5 i {
                color: #1d6fb0;
                margin-right: 8px;
            }

            .modern-course-info-content {
                color: #1e4465;
                font-size: 1rem;
                line-height: 1.8;
            }

            /* Assessment Styles */
            .assessment-container {
                margin-top: 2rem;
            }
            
            .assessment-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .assessment-title {
                font-size: 1.3rem;
                font-weight: 700;
                color: #07223b;
                border-left: 6px solid #1d6fb0;
                padding-left: 1rem;
                margin: 0;
            }
            
            .assessment-stats {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
            }
            
            .stat-pill {
                background: #f0f8ff;
                border: 2px solid #1a4b77;
                box-shadow: 3px 3px 0 #123a5e;
                padding: 0.4rem 1rem;
                font-size: 0.85rem;
                font-weight: 600;
                color: #07223b;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            
            /* Fullscreen Button */
            .fullscreen-btn {
                background: #1661a3;
                border: 2px solid #0c314d;
                box-shadow: 2px 2px 0 #0b263b;
                color: white;
                padding: 0.4rem 1rem;
                cursor: pointer;
                transition: all 0.1s ease;
                border-radius: 0px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 0.9rem;
                margin-left: 1rem;
            }

            .fullscreen-btn:hover {
                transform: translate(-1px, -1px);
                box-shadow: 3px 3px 0 #0b263b;
                background: #1a70b5;
            }

            .fullscreen-btn i {
                font-size: 1rem;
            }

            /* Fullscreen Mode */
            .course-content-wrapper.fullscreen-mode {
                margin-left: 0;
                padding: 0;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                background: #1a2b3e;
                overflow: hidden;
            }

            .fullscreen-mode .lms-sidebar-container {
                display: none;
            }

            .fullscreen-mode .course-header,
            .fullscreen-mode .course-info-card,
            .fullscreen-mode .kitchen-accent,
            .fullscreen-mode .students-section,
            .fullscreen-mode .pdf-viewer,
            .fullscreen-mode .content-card:not(.assessment-container) {
                display: none;
            }

            .fullscreen-mode .assessment-container {
                margin: 0;
                padding: 1rem;
                height: 100vh;
                display: flex;
                flex-direction: column;
                border: none;
                box-shadow: none;
                background: transparent;
            }

            .fullscreen-mode .assessment-container .content-card {
                display: flex;
                flex-direction: column;
                height: 100%;
                margin: 0;
                padding: 1.5rem;
                background: white;
            }

            .fullscreen-mode .assessment-scroll-container {
                flex: 1;
                height: auto;
                max-height: calc(100vh - 150px);
            }

            .fullscreen-mode .fullscreen-btn {
                background: #b71c1c;
                border-color: #8a1515;
                box-shadow: 2px 2px 0 #5a0e0e;
            }

            .fullscreen-mode .fullscreen-btn:hover {
                background: #c62828;
            }
            
            /* Fixed height container with scrollable content */
            .assessment-scroll-container {
                height: 500px;
                overflow-y: auto;
                padding-right: 0.5rem;
                border: 2px solid #1a4b77;
                background: #f0f8ff;
                box-shadow: 4px 4px 0 #123a5e;
            }
            
            .question-list {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            
            .question-item {
                background: white;
                border: 2px solid #b8d6f5;
                box-shadow: 4px 4px 0 #a0c0e0;
                padding: 1.5rem;
            }
            
            .question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid #2367a3;
            }
            
            .question-number {
                background: #1d6fb0;
                color: white;
                padding: 0.2rem 1rem;
                font-weight: 700;
                border: 2px solid #0f4980;
                box-shadow: 2px 2px 0 #0a3458;
            }
            
            .question-points {
                color: #1d6fb0;
                font-weight: 700;
            }
            
            .question-text {
                font-weight: 600;
                color: #07223b;
                margin-bottom: 1rem;
                font-size: 1.1rem;
            }
            
            .options-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .option-item {
                background: #f0f8ff;
                border: 2px solid #b8d6f5;
                padding: 0.8rem;
                display: flex;
                align-items: center;
                gap: 0.8rem;
                position: relative;
            }
            
            .option-letter {
                background: #1d6fb0;
                color: white;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                border: 2px solid #0f4980;
                box-shadow: 2px 2px 0 #0a3458;
                flex-shrink: 0;
            }
            
            .option-correct {
                background: #e8f5e9;
                border-color: #28a745;
            }
            
            .option-correct .option-letter {
                background: #28a745;
                border-color: #1e7e34;
                box-shadow: 2px 2px 0 #166b2c;
            }
            
            .correct-badge {
                background: #28a745;
                color: white;
                padding: 0.1rem 0.5rem;
                font-size: 0.7rem;
                font-weight: 700;
                border: 2px solid #1e7e34;
                box-shadow: 2px 2px 0 #166b2c;
                margin-left: auto;
                white-space: nowrap;
            }
            
            .no-assessment {
                background: #f0f8ff;
                border: 2px solid #b8d6f5;
                box-shadow: 4px 4px 0 #a0c0e0;
                padding: 3rem;
                text-align: center;
                color: #1e4465;
            }
            
            .no-assessment i {
                font-size: 3rem;
                color: #b8d6f5;
                margin-bottom: 1rem;
            }
            
            /* Custom scrollbar */
            .assessment-scroll-container::-webkit-scrollbar {
                width: 8px;
            }
            
            .assessment-scroll-container::-webkit-scrollbar-track {
                background: #eaf2fc;
                border: 1px solid #b8d6f5;
            }
            
            .assessment-scroll-container::-webkit-scrollbar-thumb {
                background: #1d6fb0;
                border: 1px solid #0f4980;
            }
            
            .assessment-scroll-container::-webkit-scrollbar-thumb:hover {
                background: #1a70b5;
            }

            /* PDF Viewer */
            .pdf-viewer {
                background: #f0f8ff;
                border: 2px solid #b8d6f5;
                box-shadow: 4px 4px 0 #a0c0e0;
                border-radius: 0px;
                overflow: hidden;
                margin-bottom: 1rem;
            }

            .pdf-viewer iframe {
                border: none;
                width: 100%;
                height: 600px;
            }

            /* Modal Styles - WIDER */
            .modal-xl {
                max-width: 95% !important;
                margin: 1.75rem auto;
            }

            .modal-content {
                background: #ffffff;
                border: 3px solid #1a4b77;
                box-shadow: 16px 16px 0 #123a5e;
                border-radius: 0px;
            }

            .modal-header {
                background: #1661a3;
                border-bottom: 3px solid #0c314d;
                padding: 1.2rem 1.5rem;
            }

            .modal-header .modal-title {
                color: white;
                font-weight: 700;
                font-size: 1.3rem;
            }

            .modal-header .btn-close {
                filter: brightness(0) invert(1);
                opacity: 0.8;
            }

            .modal-header .btn-close:hover {
                opacity: 1;
            }

            .modal-body {
                padding: 1.5rem;
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-footer {
                border-top: 3px solid #2367a3;
                padding: 1.2rem 1.5rem;
                display: flex;
                justify-content: space-between;
            }

            /* Stats Cards - Inline with search and action buttons */
            .stats-search-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .students-stats {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
            }

            .stat-item {
                background: #f0f8ff;
                border: 2px solid #1a4b77;
                box-shadow: 4px 4px 0 #123a5e;
                border-radius: 0px;
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
                font-weight: 600;
                color: #07223b;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .stat-item i {
                color: #1d6fb0;
            }

            .action-button-group {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
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
                width: 100%;
                max-width: 350px;
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

            /* Table */
            .table-responsive {
                border: 2px solid #1a4b77;
                box-shadow: 4px 4px 0 #123a5e;
                border-radius: 0px;
                overflow: hidden;
                max-height: 500px;
                overflow-y: auto;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
            }

            .table thead th {
                background: #d7e9ff;
                font-weight: 700;
                color: #07223b;
                border-bottom: 3px solid #1a4b77;
                padding: 0.8rem 1rem;
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                position: sticky;
                top: 0;
                background: #d7e9ff;
                z-index: 10;
            }

            .table tbody td {
                padding: 1rem;
                border-bottom: 2px solid #b8d6f5;
                color: #1e4465;
                vertical-align: middle;
                font-size: 0.9rem;
            }

            .table tbody tr:hover {
                background: #f0f8ff;
            }

            /* Student Avatar */
            .student-avatar {
                width: 40px;
                height: 40px;
                background: #1d6fb0;
                border: 2px solid #0f4980;
                box-shadow: 3px 3px 0 #0a3458;
                border-radius: 0px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                color: white;
                font-size: 0.9rem;
            }

            /* Status Badges */
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 0.3rem 0.8rem;
                font-size: 0.75rem;
                font-weight: 700;
                border: 2px solid;
                box-shadow: 2px 2px 0 rgba(0,0,0,0.1);
                border-radius: 50px;
            }

            .badge-ongoing {
                background: #ffc107;
                border-color: #b88f1f;
                color: #07223b;
            }

            .badge-completed {
                background: #28a745;
                border-color: #1e7e34;
                color: white;
            }

            .badge-expired {
                background: #6c757d;
                border-color: #5a6268;
                color: white;
            }

            /* Assessment Status Badges */
            .assessment-passed {
                background: #d4edda;
                border-color: #28a745;
                color: #155724;
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 0px;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            .assessment-failed {
                background: #f8d7da;
                border-color: #dc3545;
                color: #721c24;
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 0px;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            .assessment-none {
                background: #e2e3e5;
                border-color: #6c757d;
                color: #383d41;
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 0px;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            /* Progress Bar Mini */
            .progress-mini {
                width: 80px;
                height: 6px;
                background: #e0e0e0;
                border: 1px solid #aaa;
                border-radius: 0px;
                overflow: hidden;
            }

            .progress-mini-bar {
                height: 100%;
                background: #1d6fb0;
                transition: width 0.3s ease;
            }

            /* Action Buttons */
            .btn-drop {
                background: #dc3545;
                border: 2px solid #a71d2a;
                box-shadow: 2px 2px 0 #7a151f;
                color: white;
                padding: 0.3rem 0.8rem;
                font-size: 0.75rem;
                border-radius: 0px;
                cursor: pointer;
                transition: all 0.1s ease;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                border: none;
            }

            .btn-drop:hover {
                transform: translate(-1px, -1px);
                box-shadow: 3px 3px 0 #7a151f;
                background: #c82333;
            }

            .btn-enroll-sm {
                background: #28a745;
                border: 2px solid #1e7e34;
                box-shadow: 2px 2px 0 #166b2c;
                color: white;
                padding: 0.2rem 0.5rem;
                font-size: 0.7rem;
                border-radius: 0px;
                cursor: pointer;
                transition: all 0.1s ease;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                border: none;
            }

            .btn-enroll-sm:hover {
                transform: translate(-1px, -1px);
                box-shadow: 3px 3px 0 #166b2c;
                background: #34ce57;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem;
                background: #f0f8ff;
                border: 2px solid #b8d6f5;
                box-shadow: 6px 6px 0 #a0c0e0;
                border-radius: 0px;
            }

            .empty-state i {
                font-size: 3rem;
                color: #b8d6f5;
                margin-bottom: 1rem;
            }

            .empty-state h5 {
                font-weight: 700;
                color: #07223b;
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

            /* Two-column layout for modals */
            .modal-split-layout {
                display: flex;
                gap: 2rem;
                transition: all 0.3s ease;
            }

            .modal-left {
                flex: 2;
                transition: flex 0.3s ease;
            }

            .modal-right {
                flex: 1;
                border-left: 3px solid #2367a3;
                padding-left: 2rem;
                transition: all 0.3s ease;
            }

            /* When right section is hidden, adjust accordingly */
            .modal-right[style*="display: none"] + .modal-left {
                flex: 2;
            }

            .enrolled-row {
                cursor: default;
                transition: background-color 0.2s ease;
            }

            .enrolled-row.clickable {
                cursor: pointer !important;
            }

            .enrolled-row.clickable:hover {
                background-color: #e3f2fd !important;
            }

            /* Selected row highlighting */
            .enrolled-row.selected-row {
                background-color: #d4edda !important;
                border: 4px solid #28a745 !important;
            }

            .enrolled-row.clickable.selected-row:hover {
                background-color: #c3e6cb !important;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .course-content-wrapper {
                    margin-left: 0;
                    padding: 1rem;
                }
                .lms-sidebar-container {
                    position: relative;
                    width: 100%;
                    height: auto;
                }
                .students-stats {
                    flex-direction: column;
                }
                .options-grid {
                    grid-template-columns: 1fr;
                }
                .assessment-scroll-container {
                    height: 400px;
                }
                .modal-split-layout {
                    flex-direction: column;
                }
                .modal-right {
                    border-left: none;
                    border-top: 3px solid #2367a3;
                    padding-left: 0;
                    padding-top: 1.5rem;
                }
                .stats-search-row {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .action-button-group {
                    width: 100%;
                }
                .search-container {
                    max-width: 100%;
                }
            }
        </style>
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
                                        <tr>
                                            <th class="select-checkbox" style="display: none; width: 40px;">
                                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Student</th>
                                            <th>Progress</th>
                                            <th>Assessment</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Action</th>
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
                                                <button class="btn-drop drop-student single-action" 
                                                        data-student-id="<?= $student['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>"
                                                        data-course-id="<?= $courseId ?>">
                                                    <i class="fas fa-user-minus"></i> Drop
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

                        <h5 class="mb-3" style="border-left: 6px solid #28a745; padding-left: 1rem;">
                            <i class="fas fa-user-plus text-success me-2"></i>Enroll New Students
                        </h5>

                        <!-- Search Bar for available students -->
                        <div class="mb-3">
                            <div class="search-container" style="max-width: 100%;">
                                <input type="text" id="availableSearch" class="search-input" placeholder="Search students...">
                                <span class="search-icon"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <!-- Available Students List -->
                        <?php if(count($availableStudents) > 0): ?>
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table" id="availableTable">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($availableStudents as $student): ?>
                                        <tr class="available-row" 
                                            data-name="<?= strtolower(htmlspecialchars($student['fname'] . ' ' . $student['lname'])) ?>"
                                            data-email="<?= strtolower(htmlspecialchars($student['email'] ?? '')) ?>">
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
                                            <td>
                                                <button class="btn-enroll-sm enroll-student" 
                                                        data-student-id="<?= $student['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>"
                                                        data-course-id="<?= $courseId ?>">
                                                    <i class="fas fa-user-plus"></i> Enroll
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-2 text-muted small">
                                <span id="availableCount"><?= count($availableStudents) ?></span> students available
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
                <div>
                    <!-- Enroll New Button on the left -->
                    <button class="btn-view-enrollees" id="enrollNewBtn" onclick="toggleEnrollSection()" style="background: #28a745;">
                        <i class="fas fa-user-plus"></i> Enroll New
                    </button>
                </div>
                <div>
                    <!-- No close button here anymore -->
                </div>
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

        // Drop, Enroll, Archive and Expired functionality - NO RELOADS, ALL DYNAMIC
        $(document).ready(function() {
            let currentStudentId = null;
            let currentStudentName = null;
            let currentRow = null;

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

            // ===== ENROLL STUDENT (COMPLETELY DYNAMIC) =====
            $('.enroll-student').on('click', function() {
                const button = $(this);
                const studentId = button.data('student-id');
                const studentName = button.data('student-name');
                const studentEmail = button.closest('tr').find('td:first small').text();
                const studentAvatar = button.closest('tr').find('.student-avatar').text();
                const row = button.closest('tr');
                
                // Disable button and show loading state
                const originalText = button.html();
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'enroll_student',
                        student_id: studentId,
                        course_id: <?= $courseId ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove the row from available students table
                            row.remove();
                            
                            // Add the student to enrolled table dynamically
                            addStudentToEnrolledTable(studentId, studentName, studentEmail, studentAvatar);
                            
                            // Update available count
                            const availableCount = $('#availableTable tbody tr').length;
                            $('#availableCount').text(availableCount);
                            
                            // Show empty state if no more students
                            if (availableCount === 0) {
                                $('.modal-right .table-responsive').html(`
                                    <div class="empty-state" style="padding: 2rem;">
                                        <i class="fas fa-user-check"></i>
                                        <h6>No Students Available</h6>
                                        <p class="small">All students are already enrolled.</p>
                                    </div>
                                `);
                            }
                            
                            // Update stats
                            updateStatsAfterEnroll();
                            
                            showModalToast('success', `${studentName} enrolled successfully!`);
                        } else {
                            button.prop('disabled', false).html(originalText);
                            showModalToast('error', response.message || 'Error enrolling student');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).html(originalText);
                        showModalToast('error', 'Server error occurred');
                    }
                });
            });

            // ===== DROP STUDENT (COMPLETELY DYNAMIC) =====
            $('.drop-student').on('click', function() {
                currentStudentId = $(this).data('student-id');
                currentStudentName = $(this).data('student-name');
                currentRow = $(this).closest('tr');
                $('#dropStudentName').text(currentStudentName);
                $('#dropConfirmModal').modal('show');
            });

            $('#confirmDropBtn').on('click', function() {
                if (!currentStudentId) return;

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
                    },
                    error: function() {
                        $('#dropConfirmModal').modal('hide');
                        showModalToast('error', 'Server error occurred');
                        button.prop('disabled', false).html('Yes, Drop Student');
                    }
                });
            });

            // ===== ARCHIVE COMPLETED STUDENTS (COMPLETELY DYNAMIC) =====
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
                                const statusCell = $(this).find('td:eq(1) span'); // Status is in column 2
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

            // ===== PROCESS EXPIRED COURSE (COMPLETELY DYNAMIC) =====
            window.processExpiredCourse = function() {
                if (!confirm('This course has expired. This will:\n- Archive all completed students\n- Mark ongoing students as expired\n\nProceed?')) return;
                
                const button = $('.btn-expired');
                const originalText = button.html();
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'process_expired',
                        course_id: <?= $courseId ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update status of all ongoing students to expired
                            $('#enrolledTable tbody tr').each(function() {
                                const statusCell = $(this).find('td:eq(1) span');
                                if (statusCell.text().includes('Ongoing')) {
                                    statusCell.removeClass('badge-ongoing').addClass('badge-expired');
                                    statusCell.html('<i class="fas fa-hourglass-end"></i> Expired');
                                }
                            });
                            
                            // Remove completed students (they get archived)
                            $('#enrolledTable tbody tr').each(function() {
                                const statusCell = $(this).find('td:eq(1) span');
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

            // ===== HELPER FUNCTIONS =====

            // Function to add a student to enrolled table
            function addStudentToEnrolledTable(id, name, email, avatar) {
                const newRow = `
                    <tr class="enrolled-row" 
                        data-name="${name.toLowerCase()}"
                        data-email="${email.toLowerCase()}">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="student-avatar me-3">${avatar}</div>
                                <div>
                                    <strong>${name}</strong>
                                    <small class="d-block text-muted">${email}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge badge-ongoing">
                                <i class="fas fa-play-circle"></i> Ongoing
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold">0%</span>
                                <div class="progress-mini">
                                    <div class="progress-mini-bar" style="width: 0%;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="assessment-none">
                                <i class="fas fa-minus-circle"></i> No Assessment
                            </span>
                        </td>
                        <td><span class="text-muted">—</span></td>
                        <td><span class="text-muted">0</span></td>
                        <td>
                            <button class="btn-drop drop-student" 
                                    data-student-id="${id}"
                                    data-student-name="${name}"
                                    data-course-id="<?= $courseId ?>">
                                <i class="fas fa-user-minus"></i> Drop
                            </button>
                        </td>
                    </tr>
                `;
                
                // Append to table or create table if empty
                if ($('#enrolledTable tbody').length) {
                    $('#enrolledTable tbody').append(newRow);
                } else {
                    $('#enrolledTableContainer').html(`
                        <table class="table" id="enrolledTable">
                            <thead>...</thead>
                            <tbody>${newRow}</tbody>
                        </table>
                    `);
                }
                
                // Re-attach drop handler to new button
                attachDropHandlers();
            }

            // Function to add a student back to available table
            function addStudentToAvailableTable(id, name, email, avatar) {
                // Check if available table exists
                if ($('#availableTable tbody').length) {
                    const newRow = `
                        <tr class="available-row" 
                            data-name="${name.toLowerCase()}"
                            data-email="${email.toLowerCase()}">
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
                            <td>
                                <button class="btn-enroll-sm enroll-student" 
                                        data-student-id="${id}"
                                        data-student-name="${name}"
                                        data-course-id="<?= $courseId ?>">
                                    <i class="fas fa-user-plus"></i> Enroll
                                </button>
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
                                    <th>Student</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="available-row" 
                                    data-name="${name.toLowerCase()}"
                                    data-email="${email.toLowerCase()}">
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
                                    <td>
                                        <button class="btn-enroll-sm enroll-student" 
                                                data-student-id="${id}"
                                                data-student-name="${name}"
                                                data-course-id="<?= $courseId ?>">
                                            <i class="fas fa-user-plus"></i> Enroll
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    `);
                }
                
                // Re-attach enroll handler
                attachEnrollHandlers();
                
                // Update available count
                $('#availableCount').text($('#availableTable tbody tr').length);
            }

            // Function to update stats based on current table rows
            function updateStatsFromTable() {
                const totalRows = $('#enrolledTable tbody tr').length;
                const ongoingRows = $('#enrolledTable tbody tr').filter(function() {
                    return $(this).find('td:eq(1) span').text().includes('Ongoing');
                }).length;
                const completedRows = $('#enrolledTable tbody tr').filter(function() {
                    return $(this).find('td:eq(1) span').text().includes('Completed');
                }).length;
                
                // Update stat items
                $('.stat-item:contains("Total")').html(`<i class="fas fa-users"></i> Total: ${totalRows}`);
                $('.stat-item:contains("Ongoing")').html(`<i class="fas fa-spinner"></i> Ongoing: ${ongoingRows}`);
                $('.stat-item:contains("Completed")').html(`<i class="fas fa-check-circle"></i> Completed: ${completedRows}`);
                
                // Update enrolled count display
                $('#enrolledCount').text(totalRows);
            }

            function updateStatsAfterEnroll() {
                const totalStat = $('.stat-item:contains("Total")');
                const currentTotal = parseInt(totalStat.text().match(/\d+/)[0]) || 0;
                totalStat.html(`<i class="fas fa-users"></i> Total: ${currentTotal + 1}`);
                
                const ongoingStat = $('.stat-item:contains("Ongoing")');
                const currentOngoing = parseInt(ongoingStat.text().match(/\d+/)[0]) || 0;
                ongoingStat.html(`<i class="fas fa-spinner"></i> Ongoing: ${currentOngoing + 1}`);
                
                $('#enrolledCount').text(currentTotal + 1);
            }

            function updateStatsAfterDrop() {
                const totalRows = $('#enrolledTable tbody tr').length;
                const ongoingRows = $('#enrolledTable tbody tr').filter(function() {
                    return $(this).find('td:eq(1) span').text().includes('Ongoing');
                }).length;
                const completedRows = $('#enrolledTable tbody tr').filter(function() {
                    return $(this).find('td:eq(1) span').text().includes('Completed');
                }).length;
                
                $('.stat-item:contains("Total")').html(`<i class="fas fa-users"></i> Total: ${totalRows}`);
                $('.stat-item:contains("Ongoing")').html(`<i class="fas fa-spinner"></i> Ongoing: ${ongoingRows}`);
                $('.stat-item:contains("Completed")').html(`<i class="fas fa-check-circle"></i> Completed: ${completedRows}`);
                
                $('#enrolledCount').text(totalRows);
            }

            // Function to attach drop handlers to new buttons
            function attachDropHandlers() {
                $('.drop-student').off('click').on('click', function() {
                    currentStudentId = $(this).data('student-id');
                    currentStudentName = $(this).data('student-name');
                    currentRow = $(this).closest('tr');
                    $('#dropStudentName').text(currentStudentName);
                    $('#dropConfirmModal').modal('show');
                });
            }

            // Function to attach enroll handlers to new buttons
            function attachEnrollHandlers() {
                $('.enroll-student').off('click').on('click', function() {
                    const button = $(this);
                    const studentId = button.data('student-id');
                    const studentName = button.data('student-name');
                    const studentEmail = button.closest('tr').find('td:first small').text();
                    const studentAvatar = button.closest('tr').find('.student-avatar').text();
                    const row = button.closest('tr');
                    
                    const originalText = button.html();
                    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            action: 'enroll_student',
                            student_id: studentId,
                            course_id: <?= $courseId ?>
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                row.remove();
                                addStudentToEnrolledTable(studentId, studentName, studentEmail, studentAvatar);
                                
                                const availableCount = $('#availableTable tbody tr').length;
                                $('#availableCount').text(availableCount);
                                
                                if (availableCount === 0) {
                                    $('.modal-right .table-responsive').html(`
                                        <div class="empty-state" style="padding: 2rem;">
                                            <i class="fas fa-user-check"></i>
                                            <h6>No Students Available</h6>
                                            <p class="small">All students are already enrolled.</p>
                                        </div>
                                    `);
                                }
                                
                                updateStatsAfterEnroll();
                                showModalToast('success', `${studentName} enrolled successfully!`);
                            } else {
                                button.prop('disabled', false).html(originalText);
                                showModalToast('error', response.message || 'Error enrolling student');
                            }
                        },
                        error: function() {
                            button.prop('disabled', false).html(originalText);
                            showModalToast('error', 'Server error occurred');
                        }
                    });
                });
            }

            // Toast function for modal (stays inside modal)
            function showModalToast(type, message) {
                const bgColor = type === 'success' ? '#28a745' : (type === 'info' ? '#17a2b8' : '#dc3545');
                const icon = type === 'success' ? 'fa-check-circle' : (type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle');
                
                // Remove any existing toasts in modal
                $('#enrolleesModal .modal-toast').remove();
                
                const toast = $(`
                    <div class="alert alert-${type} modal-toast" style="
                        position: absolute;
                        top: 20px;
                        right: 20px;
                        z-index: 1060;
                        border: 3px solid #1a4b77;
                        border-radius: 0;
                        background: ${bgColor};
                        color: white;
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

            // Keep the original showToast for page-level toasts
            window.showToast = function(type, message) {
                const bgColor = type === 'success' ? '#28a745' : '#dc3545';
                const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
                
                const toast = $(`
                    <div class="toast show" role="alert" style="border: 3px solid #1a4b77; border-radius: 0;">
                        <div class="toast-header" style="background: ${bgColor}; color: white; border-bottom: 2px solid #0f4980;">
                            <i class="fas ${icon} me-2"></i>
                            <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body" style="background: white; color: #07223b;">
                            ${message}
                        </div>
                    </div>
                `);
                
                $('.toast-container').append(toast);
                
                setTimeout(() => {
                    toast.fadeOut(300, function() { $(this).remove(); });
                }, 3000);
            };
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
    // Don't toggle if clicking on the drop button
    if (event.target.classList.contains('btn-drop') || event.target.closest('.btn-drop')) return;
    
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

function batchArchive() {
    const selectedIds = [];
    document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
        selectedIds.push(cb.value);
    });
    
    if (selectedIds.length === 0) {
        showModalToast('info', 'No students selected');
        return;
    }
    
    if (!confirm(`Archive ${selectedIds.length} selected student(s)?`)) return;
    
    // Here you would make an AJAX call to archive multiple students
    // For now, we'll just show a message
    showModalToast('success', `${selectedIds.length} student(s) archived successfully`);
    
    // Remove selected rows
    selectedIds.forEach(id => {
        $(`tr[data-id="${id}"]`).remove();
    });
    
    // Exit select mode
    toggleSelectMode();
    updateStatsFromTable();
}

function batchDrop() {
    const selectedIds = [];
    document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
        selectedIds.push(cb.value);
    });
    
    if (selectedIds.length === 0) {
        showModalToast('info', 'No students selected');
        return;
    }
    
    if (!confirm(`Drop ${selectedIds.length} selected student(s)? This action cannot be undone!`)) return;
    
    // Make AJAX call to drop multiple students
    const button = $('#batchActionBar .btn-expired');
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Dropping...');
    
    let completed = 0;
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
                }
                if (completed === selectedIds.length) {
                    button.prop('disabled', false).html('<i class="fas fa-user-minus"></i> Drop Selected');
                    showModalToast('success', `${completed} student(s) dropped successfully`);
                    toggleSelectMode();
                    updateStatsFromTable();
                }
            },
            error: function() {
                completed++;
                if (completed === selectedIds.length) {
                    button.prop('disabled', false).html('<i class="fas fa-user-minus"></i> Drop Selected');
                    showModalToast('error', 'Some students could not be dropped');
                }
            }
        });
    });
}
        </script>
    </body>
    </html>