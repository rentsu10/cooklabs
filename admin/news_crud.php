<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();
if (!is_admin() && !is_proponent() && !is_superadmin()) {
  echo 'Admin only';
  exit;
}

$act = $_GET['act'] ?? '';
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : null;

/**
 * Check if current user can edit/delete a news item
 */
function canModifyNews($news_id, $pdo) {
    if (is_admin() || is_superadmin()) {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT created_by FROM news WHERE id = ?");
    $stmt->execute([$news_id]);
    $news = $stmt->fetch();
    
    return $news && $news['created_by'] == $_SESSION['user']['id'];
}

// ADD NEWS
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $pdo->prepare(
    'INSERT INTO news (title, body, created_by, created_at, is_published)
     VALUES (?, ?, ?, ?, 1)'
  );
  $stmt->execute([
    $_POST['title'],
    $_POST['body'],
    $_SESSION['user']['id'],
    date('Y-m-d H:i:s')
  ]);
  header('Location: news_crud.php');
  exit;
}

// UPDATE NEWS
if ($act === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $news_id = (int)$_POST['id'];
  if (!canModifyNews($news_id, $pdo)) {
    $_SESSION['error'] = "You don't have permission to edit this news";
    header('Location: news_crud.php');
    exit;
  }
  
  $stmt = $pdo->prepare(
    'UPDATE news SET title = ?, body = ? WHERE id = ?'
  );
  $stmt->execute([
    $_POST['title'],
    $_POST['body'],
    $news_id
  ]);
  header('Location: news_crud.php');
  exit;
}

// DELETE NEWS
if ($act === 'delete' && isset($_GET['id'])) {
  $news_id = (int)$_GET['id'];
  
  if (!canModifyNews($news_id, $pdo)) {
    $_SESSION['error'] = "You don't have permission to delete this news";
    header('Location: news_crud.php');
    exit;
  }
  
  $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$news_id]);
  header('Location: news_crud.php');
  exit;
}

// LOAD ALL NEWS
$news = $pdo->query(
  'SELECT n.*, u.username, u.role
   FROM news n
   LEFT JOIN users u ON n.created_by = u.id
   ORDER BY n.created_at DESC'
)->fetchAll();

// GET SELECTED NEWS FOR DISPLAY
$selectedNews = null;
if ($selectedId) {
    foreach ($news as $n) {
        if ($n['id'] == $selectedId) {
            $selectedNews = $n;
            break;
        }
    }
}

// LOAD NEWS FOR EDIT FORM
$editNews = null;
if ($act === 'editform' && isset($_GET['id'])) {
  $news_id = (int)$_GET['id'];
  
  if (!canModifyNews($news_id, $pdo)) {
    $_SESSION['error'] = "You don't have permission to edit this news";
    header('Location: news_crud.php');
    exit;
  }
  
  $stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
  $stmt->execute([$news_id]);
  $editNews = $stmt->fetch();
  if (!$editNews) {
    die('News not found');
  }
}

// Helper functions for badges - Only roles that appear in news page
function get_role_badge($role) {
    $badges = [
        'superadmin' => 'badge-superadmin',
        'admin' => 'badge-admin',
        'proponent' => 'badge-proponent',
    ];
    $class = $badges[$role] ?? 'badge-proponent'; // default to proponent style if unknown
    
    // Change display text based on role
    $displayText = match($role) {
        'proponent' => 'Instructor',
        'admin' => 'Admin',
        'superadmin' => 'Super Admin',
        default => ucfirst($role)
    };
    
    return "<span class='role-badge $class'>" . $displayText . "</span>";
}

function get_mini_role_badge($role) {
    $badges = [
        'superadmin' => 'mini-superadmin',
        'admin' => 'mini-admin',
        'proponent' => 'mini-proponent',
    ];
    $class = $badges[$role] ?? 'mini-proponent'; // default to proponent style if unknown
    
    // Change display text based on role (first letter)
    $displayText = match($role) {
        'proponent' => 'I', // Instructor
        'admin' => 'A', // Admin
        'superadmin' => 'SA', // Super Admin
        default => strtoupper(substr($role, 0, 1))
    };
    
    // Use the global get_role_display_name function (defined in sidebar.php or functions.php)
    if (function_exists('get_role_display_name')) {
        $tooltip = get_role_display_name($role);
    } else {
        $tooltip = ucfirst($role);
    }
    
    return "<span class='mini-role-badge $class' title='" . $tooltip . "'>" . $displayText . "</span>";
}

// Note: get_role_display_name() is already defined in sidebar.php and functions.php
// We'll use that function instead of redeclaring it
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>News Management - CookLabs LMS</title>
  <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/news.css" rel="stylesheet">
</head>
<body>
<div class="lms-sidebar-container">
  <?php include __DIR__ . '/../inc/sidebar.php'; ?>
</div>

<div class="main-content-wrapper">
  
  <div class="page-header">
    <h4> News Management</h4>
    <a href="?act=addform" class="btn-add">
      <i class="fas fa-plus"></i> Add News
    </a>
  </div>

  <!-- Session Messages -->
  <?php if(isset($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle me-2"></i>
      <?= $_SESSION['success']; unset($_SESSION['success']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if(isset($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle me-2"></i>
      <?= $_SESSION['error']; unset($_SESSION['error']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if ($act === 'addform'): ?>

    <!-- ADD FORM -->
    <div class="form-container">
      <form method="post" action="?act=add">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" placeholder="Enter news title" required>
        
        <label class="form-label">Content</label>
        <textarea name="body" class="form-control" placeholder="Write your news content here..." required></textarea>
        
        <div>
          <button type="submit" class="btn-submit">
            <i class="fas fa-save"></i> Create News
          </button>
          <a href="news_crud.php" class="btn-cancel">
            <i class="fas fa-times"></i> Cancel
          </a>
        </div>
      </form>
    </div>

  <?php elseif ($act === 'editform' && $editNews): ?>

    <!-- EDIT FORM -->
    <div class="form-container">
      <form method="post" action="?act=edit">
        <input type="hidden" name="id" value="<?= $editNews['id'] ?>">
        
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" 
               value="<?= htmlspecialchars($editNews['title']) ?>" required>
        
        <label class="form-label">Content</label>
        <textarea name="body" class="form-control" required><?= htmlspecialchars($editNews['body']) ?></textarea>
        
        <div>
          <button type="submit" class="btn-submit">
            <i class="fas fa-save"></i> Update News
          </button>
          <a href="news_crud.php" class="btn-cancel">
            <i class="fas fa-times"></i> Cancel
          </a>
        </div>
      </form>
    </div>

  <?php else: ?>

    <!-- DUAL PANEL LAYOUT (30% / 70%) -->
    <div class="dual-panel-container">
        <!-- LEFT PANEL: News List (30%) -->
        <div class="news-list-panel">
            <!-- Panel Header with Filter Toggle and Add Button -->
            <div class="panel-header">
                <!-- Filter Toggle Group -->
                <div class="filter-group" id="filterGroup">
                    <div class="filter-option active" data-filter="all" id="filterAll">
                        <i class="fas fa-list-ul"></i> All News
                    </div>
                    <div class="filter-option" data-filter="mine" id="filterMine">
                        <i class="fas fa-user"></i> My News
                    </div>
                </div>
                
                <!-- Small Add Button -->
                <a href="?act=addform" class="btn-add-small">
                    <i class="fas fa-plus"></i> Add
                </a>
            </div>
            
            <!-- News List -->
            <div class="news-list" id="newsList">
                <?php foreach ($news as $n): ?>
                <div class="news-item-card <?= ($selectedNews && $selectedNews['id'] == $n['id']) ? 'selected' : '' ?>" 
                     data-news-id="<?= $n['id'] ?>"
                     data-created-by="<?= $n['created_by'] ?>"
                     onclick="window.location.href='?id=<?= $n['id'] ?>'">
                    <div class="news-item-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="news-item-meta">
                        <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($n['created_at'])) ?></span>
                        <span class="news-item-author">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($n['username'] ?? 'Unknown') ?>
                            <?= get_mini_role_badge($n['role'] ?? 'proponent') ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- RIGHT PANEL: Details (70%) -->
        <div class="news-details-panel" id="newsDetails">
            <?php if ($selectedNews): ?>
                <div class="selected-news-header">
                    <h2 class="selected-news-title"><?= htmlspecialchars($selectedNews['title']) ?></h2>
                    <div class="selected-news-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($selectedNews['username'] ?? 'Unknown') ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <?= date('F j, Y \a\t g:i A', strtotime($selectedNews['created_at'])) ?></span>
                        <span><?= get_role_badge($selectedNews['role'] ?? 'proponent') ?></span>
                    </div>
                </div>
                
                <div class="selected-news-content">
                    <?= nl2br(htmlspecialchars($selectedNews['body'])) ?>
                </div>
                
                <?php if (canModifyNews($selectedNews['id'], $pdo)): ?>
                <div class="news-actions">
                    <a href="?act=editform&id=<?= $selectedNews['id'] ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?act=delete&id=<?= $selectedNews['id'] ?>" class="btn-delete" 
                       onclick="return confirm('Delete this news item?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-selection">
                    <i class="fas fa-newspaper"></i>
                    <h3>Select a news article</h3>
                    <p>Click on any news item from the left panel to view its content</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

  <?php endif; ?>

  <!-- Kitchen accent line -->
  <div class="kitchen-accent">
    <i class="fas fa-cube"></i>
    <i class="fas fa-utensils"></i>
    <i class="fas fa-cube"></i>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 3000);

// Filter toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterAll = document.getElementById('filterAll');
    const filterMine = document.getElementById('filterMine');
    const newsItems = document.querySelectorAll('.news-item-card');
    const currentUserId = <?= $_SESSION['user']['id'] ?>;
    
    if (filterAll && filterMine) {
        // Show all news
        filterAll.addEventListener('click', function() {
            filterAll.classList.add('active');
            filterMine.classList.remove('active');
            
            newsItems.forEach(item => {
                item.style.display = 'block';
            });
        });
        
        // Show only my news
        filterMine.addEventListener('click', function() {
            filterMine.classList.add('active');
            filterAll.classList.remove('active');
            
            newsItems.forEach(item => {
                if (item.dataset.createdBy == currentUserId) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>
</body>
</html>