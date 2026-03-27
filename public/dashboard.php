<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'] ?? 'user';
$isAdmin = ($userRole === 'admin' || $userRole === 'superadmin');
$isProponent = ($userRole === 'proponent');

// ========== COMMON DATA FOR ALL ROLES ==========

// Fetch all courses with enrollment info for current user
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.thumbnail, c.file_pdf, c.file_video,
           e.status AS enroll_status, e.progress, e.completed_at
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
    WHERE c.is_active = 1
    ORDER BY c.id DESC
");
 $stmt->execute([$userId]);
 $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate courses by status
$ongoingCourses = [];
$completedCourses = [];

foreach ($courses as $c) {
    if (isset($c['enroll_status']) && $c['enroll_status'] === 'ongoing') {
        $ongoingCourses[] = $c;
    } elseif (isset($c['enroll_status']) && $c['enroll_status'] === 'completed') {
        $completedCourses[] = $c;
    }
}

// Fetch news/announcements with read status
try {
    $newsStmt = $pdo->prepare("
        SELECT n.id, n.title, n.body AS content, n.created_at, 
               u.username, u.fname, u.lname, u.role,
               CONCAT(u.fname, ' ', u.lname) as author_fullname,
               CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END as is_read
        FROM news n 
        LEFT JOIN users u ON n.created_by = u.id 
        LEFT JOIN news_read nr ON nr.news_id = n.id AND nr.user_id = ?
        WHERE n.is_published = 1 
        ORDER BY n.created_at DESC 
        LIMIT 10
    ");
    $newsStmt->execute([$userId]);
    $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $news = [];
    error_log("News fetch error: " . $e->getMessage());
}

// Count unread news
$unreadCount = 0;
foreach ($news as $item) {
    if (!($item['is_read'] ?? 0)) {
        $unreadCount++;
    }
}

// ========== ADMIN-SPECIFIC DATA ==========

$pendingUsers = [];
$totalPending = 0;

if ($isAdmin) {
    // Fetch pending users for admin dashboard
    $stmt = $pdo->prepare("
        SELECT id, username, fname, lname, email, created_at 
        FROM users 
        WHERE status = 'pending' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $pendingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for "View All" link
    $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $totalPending = $countStmt->fetchColumn();
}

// ========== PROPONENT-SPECIFIC DATA ==========

$proponentCourses = [];
if ($isProponent) {
    // Fetch courses created by this proponent
    $courseStmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.thumbnail, c.created_at,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'completed') as completed_count
        FROM courses c
        WHERE c.proponent_id = ? AND c.is_active = 1
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $courseStmt->execute([$userId]);
    $proponentCourses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX request to mark news as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $newsId = intval($_POST['news_id'] ?? 0);
    $userId = intval($_SESSION['user']['id'] ?? 0);
    
    if ($newsId && $userId) {
        try {
            // Check if already marked as read
            $checkStmt = $pdo->prepare("SELECT id FROM news_read WHERE user_id = ? AND news_id = ?");
            $checkStmt->execute([$userId, $newsId]);
            
            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("INSERT INTO news_read (user_id, news_id, read_at) VALUES (?, ?, NOW())");
                $insertStmt->execute([$userId, $newsId]);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

// Helper function for role badges (rounded 50px)
function get_role_badge($role) {
    $badges = [
        'superadmin' => 'badge-superadmin',
        'admin' => 'badge-admin',
        'proponent' => 'badge-proponent',
        'user' => 'badge-user'
    ];
    $class = $badges[$role] ?? 'badge-user';
    
    // Change display text based on role
    $displayText = match($role) {
        'user' => 'Student',
        'proponent' => 'Instructor',
        'admin' => 'Admin',
        'superadmin' => 'Super Admin',
        default => ucfirst($role)
    };
    
    return "<span class='role-badge $class'>" . $displayText . "</span>";
}

// Helper function to get author display name
function get_author_name($item) {
    if (!empty($item['author_fullname']) && trim($item['author_fullname']) !== '') {
        return trim($item['author_fullname']);
    }
    if (!empty($item['fname'])) {
        $name = trim($item['fname'] . ' ' . ($item['lname'] ?? ''));
        if (!empty($name)) {
            return $name;
        }
    }
    return $item['username'] ?? 'Admin';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CookLabs · Dashboard</title>
    <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter (geometric) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Welcome Back, <?= htmlspecialchars($_SESSION['user']['fname'] ?? 'User') ?>!</h1>
            <p>
                <?php 
                if ($isAdmin) echo 'System Overview';
                elseif ($isProponent) echo 'Course Management Overview';
                else echo 'Track your ongoing and completed courses';
                ?>
            </p>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- LEFT COLUMN: News (full height) - SAME FOR ALL ROLES -->
            <div>
                <div class="dashboard-card news-card">
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-counter" id="unreadCounter"><?= $unreadCount ?></span>
                    <?php endif; ?>
                    
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-newspaper"></i>News & Announcements
                        </h3>
                        <a href="<?= BASE_URL ?>/admin/news_crud.php">View All</a>
                    </div>
                    
                    <div class="card-content" id="newsContainer">
                        <?php if (!empty($news)): ?>
                            <?php foreach ($news as $index => $item): 
                                $authorName = get_author_name($item);
                                $authorRole = $item['role'] ?? 'user';
                                $isRead = $item['is_read'] ?? 0;
                            ?>
                                <div class="news-item <?= $isRead ? 'read' : 'unread' ?>" 
                                     data-news-id="<?= $item['id'] ?>"
                                     data-news-index="<?= $index ?>">
                                    <div class="news-header">
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <i class="fas fa-chevron-down expand-icon"></i>
                                    </div>
                                    <?php if (!$isRead): ?>
                                        <span class="unread-dot" title="Unread"></span>
                                    <?php endif; ?>
                                    <div class="news-full-content"><?= nl2br(htmlspecialchars($item['content'])) ?></div>
                                    <div class="news-meta">
                                        <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                        <span class="news-author">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($authorName) ?>
                                            <?= get_role_badge($authorRole) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-newspaper"></i>
                                <h4>No announcements yet</h4>
                                <p>Check back later</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Role-specific content -->
            <div class="courses-container">
                <?php if ($isAdmin): ?>
                    <!-- ADMIN VIEW: Pending Users Preview (Top) -->
                    <div class="course-card-wrapper">
                        <div class="section-header">
                            <h3><i class="fas fa-user-clock"></i> Pending Approvals</h3>
                            <?php if ($totalPending > 5): ?>
                                <a href="<?= BASE_URL ?>/admin/users_crud.php" class="view-all-link">
                                    View All (<?= $totalPending ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-content">
                            <?php if (!empty($pendingUsers)): ?>
                                <table class="pending-users-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Registered</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></strong>
                                                <br><small><?= htmlspecialchars($user['username']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= date('M d', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/admin/users_crud.php?act=confirm&id=<?= $user['id'] ?>" 
                                                   class="btn-approve-small"
                                                   onclick="return confirm('Approve this user?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>/admin/users_crud.php?act=reject&id=<?= $user['id'] ?>" 
                                                   class="btn-reject-small"
                                                   onclick="return confirm('Reject this user?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-check"></i>
                                    <h4>No pending approvals</h4>
                                    <p>All users are confirmed</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ADMIN VIEW: Calendar & Clock (Bottom) -->
                    <div class="course-card-wrapper" style="padding-top: 0;">
                        <div class="datetime-split-container" style="height: 100%;">
                            <!-- LEFT SIDE: Calendar (full height) -->
                            <div class="calendar-container" style="height: 100%; display: flex; flex-direction: column;">
                                <div class="calendar-header" id="calendarMonthYear"></div>
                                <div class="calendar-weekdays">
                                    <div>Su</div>
                                    <div>Mo</div>
                                    <div>Tu</div>
                                    <div>We</div>
                                    <div>Th</div>
                                    <div>Fr</div>
                                    <div>Sa</div>
                                </div>
                                <div class="calendar-days" id="miniCalendar" style="flex: 1;"></div>
                            </div>
                            
                            <!-- RIGHT SIDE: Vintage Alarm Clock (full height) -->
                            <div class="alarm-clock-container" style="height: 100%; display: flex; flex-direction: column; justify-content: center;">
                                <div class="alarm-clock-face">
                                    <div class="alarm-time" id="alarmTime" style="color: #4fc3ff; text-shadow: 0 0 8px #1e88e5, 0 0 2px #0d47a1;">00:00:00</div>
                                    <div class="alarm-date">SYSTEM TIME · COOKLABS 2026</div>
                                </div>
                                <div class="alarm-buttons">
                                    <div class="alarm-btn alarm-btn-red" title="Snooze"></div>
                                    <div class="alarm-btn alarm-btn-green" title="Alarm On/Off"></div>
                                    <div class="alarm-btn alarm-btn-yellow" title="Light"></div>
                                </div>
                                <div class="alarm-label">LE FARCEUR v.1225</div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($isProponent): ?>
                    <!-- PROPONENT VIEW: Course Management Preview (Top) -->
                    <div class="course-card-wrapper">
                        <div class="section-header">
                            <h3><i class="fas fa-chalkboard-teacher"></i> Your Courses</h3>
                            <a href="<?= BASE_URL ?>/proponent/courses_crud.php" class="view-all-link">
                                <i class="fas fa-plus"></i> Manage Courses
                            </a>
                        </div>
                        
                        <div class="card-content">
                            <?php if (!empty($proponentCourses)): ?>
                                <?php foreach ($proponentCourses as $course): 
                                    $enrollmentRate = $course['student_count'] > 0 
                                        ? round(($course['completed_count'] / $course['student_count']) * 100) 
                                        : 0;
                                ?>
                                    <a href="<?= BASE_URL ?>/proponent/view_course.php?id=<?= $course['id'] ?>" class="course-card-link">
                                        <div class="course-card">
                                            <div class="course-card-img">
                                                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($course['thumbnail'] ?: 'Course Image.png') ?>" alt="Course">
                                            </div>
                                            <div class="course-card-body">
                                                <div class="course-card-title">
                                                    <h6><?= htmlspecialchars(substr($course['title'], 0, 50)) ?><?= strlen($course['title']) > 50 ? '...' : '' ?></h6>
                                                </div>
                                                <p><?= htmlspecialchars(substr($course['description'], 0, 60)) ?>...</p>
                                                
                                                <div class="course-stats" style="display: flex; gap: 0.5rem; margin-top: 0.3rem; font-size: 0.75rem; color: #1e4465;">
                                                    <span><i class="fas fa-users"></i> <?= $course['student_count'] ?> enrolled</span>
                                                    <span><i class="fas fa-check-circle"></i> <?= $course['completed_count'] ?> completed</span>
                                                </div>
                                                
                                                <div class="course-progress" style="margin-top: 0.3rem;">
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $enrollmentRate ?>%;"></div>
                                                    </div>
                                                    <div class="progress-percent" style="font-size: 0.65rem;"><?= $enrollmentRate ?>% completion rate</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <h4>No courses yet</h4>
                                    <p>Create your first course to get started</p>
                                    <a href="<?= BASE_URL ?>/proponent/courses_crud.php?act=addform" class="btn-view-enrollees" style="margin-top: 0.5rem; padding: 0.3rem 1rem; font-size: 0.8rem;">
                                        <i class="fas fa-plus"></i> Create Course
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PROPONENT VIEW: Calendar & Clock (Bottom) - Same as Admin -->
                    <div class="course-card-wrapper" style="padding-top: 0;">
                        <div class="datetime-split-container" style="height: 100%;">
                            <!-- LEFT SIDE: Calendar (full height) -->
                            <div class="calendar-container" style="height: 100%; display: flex; flex-direction: column;">
                                <div class="calendar-header" id="calendarMonthYear"></div>
                                <div class="calendar-weekdays">
                                    <div>Su</div>
                                    <div>Mo</div>
                                    <div>Tu</div>
                                    <div>We</div>
                                    <div>Th</div>
                                    <div>Fr</div>
                                    <div>Sa</div>
                                </div>
                                <div class="calendar-days" id="miniCalendar" style="flex: 1;"></div>
                            </div>
                            
                            <!-- RIGHT SIDE: Vintage Alarm Clock (full height) -->
                            <div class="alarm-clock-container" style="height: 100%; display: flex; flex-direction: column; justify-content: center;">
                                <div class="alarm-clock-face">
                                    <div class="alarm-time" id="alarmTime" style="color: #4fc3ff; text-shadow: 0 0 8px #1e88e5, 0 0 2px #0d47a1;">00:00:00</div>
                                    <div class="alarm-date" id="alarmDate">SYSTEM TIME · COOKLABS 2026</div>
                                </div>
                                <div class="alarm-buttons">
                                    <div class="alarm-btn alarm-btn-red" title="Snooze"></div>
                                    <div class="alarm-btn alarm-btn-green" title="Alarm On/Off"></div>
                                    <div class="alarm-btn alarm-btn-yellow" title="Light"></div>
                                </div>
                                <div class="alarm-label">LE FARCEUR v.1225</div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- REGULAR USER VIEW: Ongoing Courses (Top) -->
                    <div class="course-card-wrapper">
                        <div class="section-header">
                            <h3><i class="fas fa-play-circle"></i>Ongoing Courses</h3>
                        </div>
                        
                        <div class="card-content">
                            <?php if (!empty($ongoingCourses)): ?>
                                <?php foreach ($ongoingCourses as $c): 
                                    $progressPercent = 0;
                                    if ($c['progress'] && ($c['file_pdf'] || $c['file_video'])) {
                                        $totalDuration = 0;
                                        if ($c['file_pdf']) $totalDuration += 60;
                                        if ($c['file_video']) $totalDuration += 300;
                                        if ($totalDuration > 0) {
                                            $progressPercent = min(100, round(($c['progress'] / $totalDuration) * 100));
                                        }
                                    }
                                ?>
                                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="course-card-link">
                                        <div class="course-card">
                                            <div class="course-card-img">
                                                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course">
                                            </div>
                                            <div class="course-card-body">
                                                <div class="course-card-title">
                                                    <h6><?= htmlspecialchars($c['title']) ?></h6>
                                                    <span class="course-badge badge-ongoing">
                                                        <i class="fas fa-play"></i> Ongoing
                                                    </span>
                                                </div>
                                                <p><?= htmlspecialchars(substr($c['description'], 0, 60)) ?>...</p>
                                                
                                                <div class="course-progress">
                                                    <div class="progress">
                                                        <div class="progress-bar" style="width: <?= $progressPercent ?>%;"></div>
                                                    </div>
                                                    <div class="progress-percent"><?= $progressPercent ?>% complete</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-play-circle"></i>
                                    <h4>No ongoing courses</h4>
                                    <p>Browse courses to start learning</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- REGULAR USER VIEW: Completed Courses (Bottom) -->
                    <div class="course-card-wrapper">
                        <div class="section-header">
                            <h3><i class="fas fa-check-circle"></i>Completed Courses</h3>
                        </div>
                        
                        <div class="card-content">
                            <?php if (!empty($completedCourses)): ?>
                                <?php foreach ($completedCourses as $c): ?>
                                    <a href="<?= BASE_URL ?>/public/course_view.php?id=<?= $c['id'] ?>" class="course-card-link">
                                        <div class="course-card">
                                            <div class="course-card-img">
                                                <img src="<?= BASE_URL ?>/uploads/images/<?= htmlspecialchars($c['thumbnail'] ?: 'Course Image.png') ?>" alt="Course">
                                            </div>
                                            <div class="course-card-body">
                                                <div class="course-card-title">
                                                    <h6><?= htmlspecialchars($c['title']) ?></h6>
                                                    <span class="course-badge badge-completed">
                                                        <i class="fas fa-check"></i> Done
                                                    </span>
                                                </div>
                                                <p><?= htmlspecialchars(substr($c['description'], 0, 60)) ?>...</p>
                                                
                                                <?php if (!empty($c['completed_at'])): ?>
                                                    <div class="completed-date">
                                                        <i class="fas fa-calendar-check"></i> 
                                                        <?= date('M d, Y', strtotime($c['completed_at'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h4>No completed courses yet</h4>
                                    <p>Keep learning!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
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
        // Auto-hiding scrollbar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const newsContainer = document.querySelector('.news-card .card-content');
            
            if (newsContainer) {
                let scrollTimeout;
                
                function showScrollbar() {
                    newsContainer.classList.add('scrollbar-visible');
                    newsContainer.classList.remove('scrollbar-hidden');
                }
                
                function hideScrollbar() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        newsContainer.classList.remove('scrollbar-visible');
                        newsContainer.classList.add('scrollbar-hidden');
                    }, 2000);
                }
                
                newsContainer.classList.add('scrollbar-hidden');
                newsContainer.addEventListener('scroll', showScrollbar);
                newsContainer.addEventListener('scroll', hideScrollbar);
                newsContainer.addEventListener('mouseenter', showScrollbar);
                newsContainer.addEventListener('mouseleave', hideScrollbar);
            }
            
            // News items expandable/collapsible and mark as read
            const newsItems = document.querySelectorAll('.news-item');
            let unreadCounter = document.getElementById('unreadCounter');
            
            newsItems.forEach(item => {
                const newsId = item.dataset.newsId;
                const isRead = item.classList.contains('read');
                
                item.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A') return;
                    
                    this.classList.toggle('expanded');
                    
                    if (!isRead && this.classList.contains('expanded')) {
                        markAsRead(newsId, this);
                    }
                    
                    if (this.classList.contains('expanded')) {
                        setTimeout(() => {
                            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                });
            });
            
            // Update alarm clock
            function updateAlarmClock() {
                const now = new Date();
                
                // Format time with AM/PM
                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;
                
                const timeStr = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
                const alarmTime = document.getElementById('alarmTime');
                if (alarmTime) alarmTime.textContent = timeStr;
                
                // Format date
                const dateStr = now.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const alarmDate = document.getElementById('alarmDate');
                if (alarmDate) alarmDate.textContent = dateStr;
                
                // Update calendar
                updateMiniCalendar(now);
                
                // Update calendar header
                const monthYear = now.toLocaleDateString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                const calendarHeader = document.getElementById('calendarMonthYear');
                if (calendarHeader) calendarHeader.textContent = monthYear;
            }
            
            function updateMiniCalendar(date) {
                const calendar = document.getElementById('miniCalendar');
                if (!calendar) return;
                
                const year = date.getFullYear();
                const month = date.getMonth();
                const today = date.getDate();
                
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                
                let html = '';
                
                // Previous month days
                for (let i = 0; i < firstDay; i++) {
                    html += `<div class="calendar-day other-month"></div>`;
                }
                
                // Current month days
                for (let d = 1; d <= daysInMonth; d++) {
                    const isToday = d === today ? 'today' : '';
                    html += `<div class="calendar-day ${isToday}">${d}</div>`;
                }
                
                calendar.innerHTML = html;
            }
            
            // Update clock every second
            setInterval(updateAlarmClock, 1000);
            updateAlarmClock(); // Initial call
        });
        
        function markAsRead(newsId, newsElement) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&news_id=' + newsId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    newsElement.classList.remove('unread');
                    newsElement.classList.add('read');
                    
                    const dot = newsElement.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    
                    const counter = document.getElementById('unreadCounter');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent);
                        if (currentCount > 1) {
                            counter.textContent = currentCount - 1;
                        } else {
                            counter.style.transition = 'opacity 0.3s, transform 0.3s';
                            counter.style.opacity = '0';
                            counter.style.transform = 'scale(0)';
                            setTimeout(() => {
                                counter.remove();
                            }, 300);
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>