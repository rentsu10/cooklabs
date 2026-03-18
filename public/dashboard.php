<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();

$userId = $_SESSION['user']['id'];

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
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter (geometric) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
    <style>
        /* Role badge styles - rounded 50px */
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            border: 2px solid;
            box-shadow: 2px 2px 0 rgba(0, 0, 0, 0.1);
            border-radius: 50px !important; /* Rounded, not sharp */
            color: white;
            white-space: nowrap;
            margin-left: 5px;
        }
        
        .badge-superadmin { 
            background: #1d6fb0; 
            border-color: #0f4980; 
        }
        .badge-admin { 
            background: #c0392b; 
            border-color: #a93226; 
        }
        .badge-proponent { 
            background: #8e44ad; 
            border-color: #6c3483; 
        }
        .badge-user { 
            background: #28a745; 
            border-color: #1e7e34; 
        }
        
        /* Update news author display */
        .news-author {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .news-author i {
            margin-right: 2px;
        }
        
        /* Make news container scrollable */
        .news-card .card-content {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        /* Auto-hiding scrollbar */
        .news-card .card-content {
            scrollbar-width: thin;
            scrollbar-color: #1d6fb0 #eaf2fc;
            transition: scrollbar-color 0.3s ease;
        }
        
        .news-card .card-content::-webkit-scrollbar {
            width: 8px;
            transition: opacity 0.3s ease;
        }
        
        .news-card .card-content::-webkit-scrollbar-track {
            background: #eaf2fc;
            border: 1px solid #b8d6f5;
        }
        
        .news-card .card-content::-webkit-scrollbar-thumb {
            background: #1d6fb0;
            border: 1px solid #0f4980;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .news-card .card-content::-webkit-scrollbar-thumb:hover {
            background: #1a70b5;
        }
        
        /* Auto-hide scrollbar when not scrolling */
        .news-card .card-content.scrollbar-hidden::-webkit-scrollbar-thumb {
            opacity: 0;
        }
        
        .news-card .card-content.scrollbar-visible::-webkit-scrollbar-thumb {
            opacity: 1;
        }
        
        /* Unread indicator styles - using green shades */
        .news-item {
            position: relative;
            transition: all 0.2s ease;
        }
        
        .news-item.unread {
            background-color: #e8f5e9;  /* Light green background */
            border-left: 4px solid #4caf50;  /* Green left border */
        }
        
        .news-item.unread .news-header h5 {
            font-weight: 700;  /* Bold title for unread */
            color: #2e7d32;  /* Dark green text */
        }
        
        .unread-dot {
            position: absolute;
            top: 12px;
            right: 40px;
            width: 10px;
            height: 10px;
            background-color: #f44336;  /* Red dot for visibility */
            border: 2px solid #ffffff;
            border-radius: 50%;
            box-shadow: 0 0 0 1px #f44336;
            animation: pulse 2s infinite;
            z-index: 3;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(244, 67, 54, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
            }
        }
        
        /* Read state - back to normal */
        .news-item.read {
            background-color: transparent;
            border-left: none;
        }
        
        .news-item.read .news-header h5 {
            font-weight: 500;
            color: #07223b;
        }
        
        /* Ensure the expand icon doesn't overlap with the dot */
        .news-item .expand-icon {
            position: relative;
            z-index: 2;
        }
        
        /* Notification counter - positioned at top right of the card */
        .news-card {
            position: relative;
        }
        
        .notification-counter {
            position: absolute;
            top: -10px;
            right: -10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #dc3545;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            border-radius: 50px;
            border: 2px solid #a71d2a;
            box-shadow: 3px 3px 0 #7a151f;
            z-index: 10;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Update section header to remove inline counter */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-header h3 {
            display: flex;
            align-items: center;
            margin: 0;
        }
    </style>
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
            <h1>Welcome Back, <?= htmlspecialchars($_SESSION['user']['fname'] ?? 'Chef') ?>!</h1>
            <p>Track your ongoing and completed courses</p>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- LEFT COLUMN: News (full height) -->
            <div>
                <div class="dashboard-card news-card">
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-counter" id="unreadCounter"><?= $unreadCount ?></span>
                    <?php endif; ?>
                    
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-newspaper"></i>News & Announcements
                        </h3>
                        <?php if(is_admin()): ?>
                            <a href="<?= BASE_URL ?>/admin/news_crud.php">View All</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-content" id="newsContainer">
                        <?php if (!empty($news)): ?>
                            <?php foreach ($news as $index => $item): 
                                // Determine author display name
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

            <!-- RIGHT COLUMN: Ongoing + Completed Courses (stacked) -->
            <div class="courses-container">
                <!-- Ongoing Courses -->
                <div class="course-card-wrapper">
                    <div class="section-header">
                        <h3><i class="fas fa-play-circle"></i>Ongoing Courses</h3>
                    </div>
                    
                    <div class="card-content">
                        <?php if (!empty($ongoingCourses)): ?>
                            <?php foreach ($ongoingCourses as $c): 
                                // Calculate progress
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

                <!-- Completed Courses -->
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
                
                // Function to show scrollbar
                function showScrollbar() {
                    newsContainer.classList.add('scrollbar-visible');
                    newsContainer.classList.remove('scrollbar-hidden');
                }
                
                // Function to hide scrollbar after delay
                function hideScrollbar() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        newsContainer.classList.remove('scrollbar-visible');
                        newsContainer.classList.add('scrollbar-hidden');
                    }, 2000);
                }
                
                // Initially hidden
                newsContainer.classList.add('scrollbar-hidden');
                
                // Show scrollbar when scrolling
                newsContainer.addEventListener('scroll', showScrollbar);
                
                // Hide scrollbar after scrolling stops
                newsContainer.addEventListener('scroll', hideScrollbar);
                
                // Also show on mouse enter
                newsContainer.addEventListener('mouseenter', showScrollbar);
                
                // Hide on mouse leave after delay
                newsContainer.addEventListener('mouseleave', hideScrollbar);
            }
            
            // News items expandable/collapsible and mark as read
            const newsItems = document.querySelectorAll('.news-item');
            let unreadCounter = document.getElementById('unreadCounter');
            
            newsItems.forEach(item => {
                const newsId = item.dataset.newsId;
                const isRead = item.classList.contains('read');
                
                item.addEventListener('click', function(e) {
                    // Don't toggle if clicking on a link
                    if (e.target.tagName === 'A') return;
                    
                    // Toggle expanded class
                    this.classList.toggle('expanded');
                    
                    // If this news item was unread, mark it as read
                    if (!isRead && this.classList.contains('expanded')) {
                        markAsRead(newsId, this);
                    }
                    
                    // Optional: auto-scroll to keep expanded item in view
                    if (this.classList.contains('expanded')) {
                        setTimeout(() => {
                            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 100);
                    }
                });
            });
        });
        
        // Function to mark news as read
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
                    // Update the UI to show as read
                    newsElement.classList.remove('unread');
                    newsElement.classList.add('read');
                    
                    // Remove the red dot
                    const dot = newsElement.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    
                    // Update the unread counter
                    const counter = document.getElementById('unreadCounter');
                    if (counter) {
                        const currentCount = parseInt(counter.textContent);
                        if (currentCount > 1) {
                            counter.textContent = currentCount - 1;
                        } else {
                            // Remove the counter with animation
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