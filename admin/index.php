<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if(!is_admin() && !is_superadmin()){ echo 'SuperAdmin and Admin only'; exit; }

$userId = $_SESSION['user']['id'];


// Fetch pending user verifications - FIXED QUERY
try {
    $pendingStmt = $pdo->prepare("
        SELECT id, fname, lname, email, username 
        FROM users 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $pendingStmt->execute();
    $pendingUsers = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingUsers = [];
    error_log("Pending users fetch error: " . $e->getMessage());
}

// Count pending users for badge (optional)
$pending_count = count($pendingUsers);

// Fetch news/announcements
try {
    $newsStmt = $pdo->prepare("
        SELECT n.id, n.title, n.body AS content, n.created_at, u.username AS author 
        FROM news n 
        LEFT JOIN users u ON n.created_by = u.id 
        WHERE n.is_published = 1 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $newsStmt->execute();
    $news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $news = [];
    error_log("News fetch error: " . $e->getMessage());
}

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <p>Track your learning progress and stay updated with announcements</p>
        </div>

        <div class="content-section">
            <!-- News Section -->
            <div class="news-section">
                <div class="section-header">
                    <h3><i class="fas fa-newspaper me-2"></i>News & Announcements</h3>
                    <?php if(is_admin()): ?>
                        <a href="<?= BASE_URL ?>/admin/news_crud.php">View All</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($news)): ?>
                    <?php foreach ($news as $item): ?>
                        <div class="news-item">
                            <h5><?= htmlspecialchars($item['title']) ?></h5>
                            <p><?= htmlspecialchars(substr($item['content'], 0, 100)) ?>...</p>
                            <div class="news-meta">
                                <span><i class="fas fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                <span><i class="fas fa-user me-1"></i> <?= htmlspecialchars($item['author']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-newspaper"></i>
                        <h4>No announcements yet</h4>
                        <p>Check back later for updates</p>
                    </div>
                <?php endif; ?>
            </div>
        
            <!-- Pending Users Section - FIXED DISPLAY -->
            <div class="pending-users-section">
                <div class="section-header">
                    <h3><i class="fas fa-user-clock me-2"></i>Pending User Verifications</h3>
                    <?php if(!empty($pendingUsers)): ?>
                        <span class="badge bg-warning"><?= count($pendingUsers) ?> pending</span>
                    <?php endif; ?>
                    <?php if(is_admin()): ?>
                        <a href="<?= BASE_URL ?>/admin/users_crud.php">View All</a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pendingUsers)): ?>
                    <?php foreach ($pendingUsers as $user): ?>
                        <div class="pending-user-item">
                            <div class="user-info">
                                <strong><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></strong>
                                <span class="user-email">(<?= htmlspecialchars($user['email']) ?>)</span>
                                <span class="user-username">@<?= htmlspecialchars($user['username']) ?></span>
                            </div>
                            <div class="user-actions">
                                <a href="<?= BASE_URL ?>/admin/verify_user.php?id=<?= $user['id'] ?>" class="btn btn-success btn-sm me-2">
                                    <i class="fas fa-check"></i> Verify
                                </a>
                                <a href="<?= BASE_URL ?>/admin/reject_user.php?id=<?= $user['id'] ?>" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-check"></i>
                        <h4>No pending verifications</h4>
                        <p>All users are verified</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .pending-users-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .section-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .section-header h3 {
        margin: 0;
        font-size: 1.25rem;
        color: #333;
    }
    .section-header .badge {
        background-color: #ffc107;
        color: #000;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    .pending-user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        margin-bottom: 10px;
        background-color: #fff9e6;
    }
    .pending-user-item:last-child {
        margin-bottom: 0;
    }
    .user-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .user-info strong {
        font-size: 1.1rem;
        color: #333;
    }
    .user-email {
        color: #666;
        font-size: font-size: 0.9rem;
    }
    .user-username {
        color: #888;
        font-size: 0.85rem;
    }
    .user-actions {
        display: flex;
        gap: 5px;
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        color: #ddd;
    }
    .empty-state h4 {
        margin-bottom: 5px;
        color: #666;
    }
    .empty-state p {
        margin: 0;
        font-size: 0.9rem;
    }
    </style>

</body>
</html>
