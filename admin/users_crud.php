<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailerconfigadmin.php';
// require_login();

// // Only admin can access this page
// if (!is_admin() && !is_superadmin()) {
// echo 'Admin only';
// exit;
// }

$act = $_GET['act'] ?? '';

// ADD USER WITH EMAIL NOTIFICATION
if ($act === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
session_start();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fname    = trim($_POST['fname'] ?? '');
$lname    = trim($_POST['lname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = $_POST['role'] ?? 'user';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
$_SESSION['error'] = "Invalid email format";
header('Location: users_crud.php?act=addform');
exit;
}

// FIXED: Check if username OR email already exists - separate checks for better error message
$checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$checkUsername->execute([$username]);
if ($checkUsername->fetch()) {
$_SESSION['error'] = "Username already exists";
header('Location: users_crud.php?act=addform');
exit;
}

$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$checkEmail->execute([$email]);
if ($checkEmail->fetch()) {
$_SESSION['error'] = "Email already exists";
header('Location: users_crud.php?act=addform');
exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
// Start transaction
$pdo->beginTransaction();

// Insert user
$stmt = $pdo->prepare(
"INSERT INTO users (username, password, fname, lname, email, role, status, created_at)
VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())"
);
$stmt->execute([$username, $hash, $fname, $lname, $email, $role]);

// Get the new user ID
$newUserId = $pdo->lastInsertId();

// Prepare recipient name
$recipientName = !empty($fname) ? $fname : $username;
if (!empty($lname)) {
$recipientName .= ' ' . $lname;
}

// SEND WELCOME EMAIL
if (function_exists('sendConfirmationEmail')) {
$emailResult = sendConfirmationEmail($email, $recipientName, $username, $password);

if ($emailResult['success']) {
$pdo->commit();
$_SESSION['success'] = "User added successfully and welcome email sent to $email";
} else {
$pdo->commit();
$_SESSION['warning'] = "User added but email failed: " . $emailResult['message'];
}
} else {
$pdo->commit();
$_SESSION['success'] = "User added successfully";
}

} catch (Exception $e) {
$pdo->rollBack();
$_SESSION['error'] = "Failed to add user: " . $e->getMessage();
error_log("Add user error: " . $e->getMessage());
}

header('Location: users_crud.php');
exit;
}

// UPDATE USER
if ($act === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
session_start();

$id       = (int)$_POST['id'];
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$fname    = trim($_POST['fname'] ?? '');
$lname    = trim($_POST['lname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = $_POST['role'] ?? 'user';

// FIXED: Check if email already exists for OTHER users
$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$checkEmail->execute([$email, $id]);
if ($checkEmail->fetch()) {
$_SESSION['error'] = "Email already exists for another user";
header('Location: users_crud.php?act=edit&id=' . $id);
exit;
}

// Check if username already exists for OTHER users
$checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$checkUsername->execute([$username, $id]);
if ($checkUsername->fetch()) {
$_SESSION['error'] = "Username already exists for another user";
header('Location: users_crud.php?act=edit&id=' . $id);
exit;
}

try {
$pdo->beginTransaction();

if (!empty($password)) {
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "UPDATE users
SET username=?, fname=?, lname=?, email=?, role=?, password=?
WHERE id=?";
$params = [$username, $fname, $lname, $email, $role, $hash, $id];
} else {
$sql = "UPDATE users
SET username=?, fname=?, lname=?, email=?, role=?
WHERE id=?";
$params = [$username, $fname, $lname, $email, $role, $id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$pdo->commit();
$_SESSION['success'] = "User updated successfully";

} catch (Exception $e) {
$pdo->rollBack();
$_SESSION['error'] = "Failed to update user: " . $e->getMessage();
}

header('Location: users_crud.php');
exit;
}

// DELETE USER
if ($act === 'delete' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];

try {
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
$_SESSION['success'] = "User deleted successfully";
} catch (Exception $e) {
$_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
}

header('Location: users_crud.php');
exit;
}

// FETCH USER FOR EDIT
if ($act === 'edit' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
$id = (int)$_GET['id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
exit('User not found');
}
}

// CONFIRM USER STATUS - UPDATED TO SEND APPROVAL NOTIFICATION
if (isset($_GET['act']) && $_GET['act'] === 'confirm' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];

// Get user details before confirming
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
if ($user['status'] !== 'confirmed') {
// Update user status
$update = $pdo->prepare("UPDATE users SET status = 'confirmed' WHERE id = ?");
$update->execute([$id]);
$_SESSION['success'] = "User confirmed successfully";
        
        // Prepare recipient name
        $recipientName = !empty($user['fname']) ? $user['fname'] : $user['username'];
        if (!empty($user['lname'])) {
            $recipientName .= ' ' . $user['lname'];
        }
        
        // SEND APPROVAL NOTIFICATION EMAIL
        if (function_exists('sendApprovalNotification')) {
            $emailResult = sendApprovalNotification($user['email'], $recipientName);
            
            if ($emailResult['success']) {
                $_SESSION['success'] .= " and notification email sent to " . $user['email'];
            } else {
                $_SESSION['warning'] = "User confirmed but notification email failed: " . $emailResult['message'];
            }
        }
} else {
$_SESSION['info'] = "User already confirmed";
}
} else {
$_SESSION['error'] = "User not found";
}

header('Location: users_crud.php');
exit;
}

// REJECT USER (Delete pending user)
if (isset($_GET['act']) && $_GET['act'] === 'reject' && isset($_GET['id'])) {
session_start();
$id = (int)$_GET['id'];

// Get user details before deleting
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Prepare recipient name
    $recipientName = !empty($user['fname']) ? $user['fname'] : $user['username'];
    if (!empty($user['lname'])) {
        $recipientName .= ' ' . $user['lname'];
    }
    
    // Delete the user
    $pdo->prepare('DELETE FROM users WHERE id = ? AND status = "pending"')->execute([$id]);
    $_SESSION['success'] = "User rejected and deleted";
    
    // SEND REJECTION EMAIL
    if (function_exists('sendRejectionNotification')) {
        $emailResult = sendRejectionNotification($user['email'], $recipientName);
        
        if ($emailResult['success']) {
            $_SESSION['success'] .= " and notification email sent to " . $user['email'];
        } else {
            $_SESSION['warning'] = "User rejected but notification email failed: " . $emailResult['message'];
        }
    }
} else {
    $_SESSION['error'] = "User not found or already processed";
}

header('Location: users_crud.php');
exit;
}

// Get all users
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch confirmed users
$confirmedUsers = $pdo->query("
SELECT * FROM users 
WHERE status = 'confirmed'
ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending users
$pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$totalUsers = count($allUsers);
$totalConfirmed = count($confirmedUsers);
$totalPending = count($pendingUsers);

// Helper function for role badges (ROUNDED - no edges)
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

?>

<!doctype html>
<html lang="en">

<head>
<meta charset="utf-8">
<title>User Management - CookLabs LMS</title>
<link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<!-- Link to external CSS file -->
<link href="<?= BASE_URL ?>/assets/css/users.css" rel="stylesheet">
<style>
/* Table header with search - added inline in case external CSS hasn't been updated */
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-search {
    position: relative;
    min-width: 250px;
}

.search-input {
    width: 100%;
    padding: 8px 15px 8px 40px;
    border: 1px solid #e0e7ed;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    background-color: white;
}

.search-input:focus {
    outline: none;
    border-color: #0a66c2;
    box-shadow: 0 0 0 3px rgba(10, 102, 194, 0.1);
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #8a9bb5;
    font-size: 0.9rem;
    pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-search {
        width: 100%;
    }
}
</style>
</head>

<body>

    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

<div class="main-content-wrapper">
<div class="container">

    <!-- Page Header -->
    <div class="page-header">
        <h3><i class="fas fa-users"></i> User Management</h3>
        <?php if($act !== 'addform'): ?>
        <a href="?act=addform" class="btn-add">
            <i class="fas fa-plus"></i> Add New User
        </a>
        <?php endif; ?>
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

    <?php if(isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['info'])): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <i class="fas fa-info-circle me-2"></i>
        <?= $_SESSION['info']; unset($_SESSION['info']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards - SMALLER -->
    <div class="row mb-3 justify-content-start">
        <div class="col-auto">
            <div class="stats-card">
                <span class="stats-label">Total:</span>
                <span class="stats-number"><?= $totalUsers ?></span>
            </div>
        </div>

        <div class="col-auto">
            <div class="stats-card">
                <span class="stats-label">Confirmed:</span>
                <span class="stats-number"><?= $totalConfirmed ?></span>
            </div>
        </div>

        <div class="col-auto">
            <div class="stats-card">
                <span class="stats-label">Pending:</span>
                <span class="stats-number"><?= $totalPending ?></span>
            </div>
        </div>
    </div>

    <!-- Add User Form -->
    <?php if ($act === 'addform'): ?>
    <div class="form-card">
        <h5><i class="fas fa-user-plus"></i> Add New User</h5>
        <form method="post" action="?act=add">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name</label>
                    <input name="fname" class="form-control" placeholder="First Name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input name="lname" class="form-control" placeholder="Last Name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input name="email" type="email" class="form-control" placeholder="Email" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="user">Student</option>
                        <option value="proponent">Instructor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Create User
                </button>
                <a href="users_crud.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Edit User Form -->
    <?php if ($act === 'edit' && isset($user)): ?>
    <div class="form-card">
        <h5><i class="fas fa-edit"></i> Edit User - <?= htmlspecialchars($user['username']) ?></h5>
        <form method="post" action="?act=edit">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">New Password (leave empty to keep current)</label>
                    <div class="input-group">
                        <input class="form-control" type="password" name="password" id="passwordField" placeholder="Enter new password" disabled>
                        <button type="button" class="btn-outline-secondary" onclick="enablePassword()">Change</button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name</label>
                    <input class="form-control" name="fname" value="<?= htmlspecialchars($user['fname']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input class="form-control" name="lname" value="<?= htmlspecialchars($user['lname']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Student</option>
                        <option value="proponent" <?= $user['role'] === 'proponent' ? 'selected' : '' ?>>Instructor</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
                <a href="users_crud.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Pending Users Table -->
    <div class="table-card">
        <div class="table-header">
            <h5>
                <span class="status-indicator status-pending"></span> 
                Pending Confirmation (<?= count($pendingUsers) ?>)
            </h5>
            <div class="table-search">
                <input type="text" id="pendingSearch" class="search-input" placeholder="Search pending users...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table pending-table" id="pendingTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingUsers)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3" style="color: #5f6f82;">
                            <i class="fas fa-check-circle" style="font-size: 1.5rem; color: #b8d6f5; margin-bottom: 0.3rem;"></i><br>
                            No pending users found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($pendingUsers as $u): ?>
                    <tr>
                        <td><span class="id-text">#<?= $u['id'] ?></span></td>
                        <td title="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></td>
                        <td title="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
                        <td title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <span class="badge-pending">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        </td>
                        <td class="table-actions">
                            <a href="?act=confirm&id=<?= $u['id'] ?>" 
                                onclick="return confirm('Confirm <?= htmlspecialchars($u['username']) ?>?')" 
                                class="btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="?act=reject&id=<?= $u['id'] ?>" 
                                onclick="return confirm('Reject and delete <?= htmlspecialchars($u['username']) ?>?')" 
                                class="btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmed Users Table - with confirmed-table class for fixed widths -->
    <div class="table-card">
        <div class="table-header">
            <h5>
                <span class="status-indicator status-confirmed"></span> 
                Confirmed Users (<?= count($confirmedUsers) ?>)
            </h5>
            <div class="table-search">
                <input type="text" id="confirmedSearch" class="search-input" placeholder="Search confirmed users...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table confirmed-table" id="confirmedTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($confirmedUsers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-3" style="color: #5f6f82;">
                            <i class="fas fa-users" style="font-size: 1.5rem; color: #b8d6f5; margin-bottom: 0.3rem;"></i><br>
                            No confirmed users yet
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($confirmedUsers as $u): ?>
                    <tr>
                        <td><span class="id-text">#<?= $u['id'] ?></span></td>
                        <td title="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></td>
                        <td title="<?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?>"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
                        <td title="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?= get_role_badge($u['role']) ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <span class="badge-confirmed">
                                <i class="fas fa-check-circle"></i> Confirmed
                            </span>
                        </td>
                        <td class="table-actions">
                            <a href="?act=edit&id=<?= $u['id'] ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?act=delete&id=<?= $u['id'] ?>" 
                                onclick="return confirm('Are you sure you want to delete user <?= htmlspecialchars($u['username']) ?>? This action cannot be undone.')" 
                                class="btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Kitchen accent -->
    <div class="kitchen-accent">
        <i class="fas fa-cube"></i>
        <i class="fas fa-utensils"></i>
        <i class="fas fa-cube"></i>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function enablePassword() {
    document.getElementById('passwordField').disabled = false;
    document.getElementById('passwordField').focus();
}

setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 3000);

// Search functionality for both tables
function setupTableSearch(searchInputId, tableId) {
    const searchInput = document.getElementById(searchInputId);
    const table = document.getElementById(tableId);
    
    if (!searchInput || !table) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tbody = table.getElementsByTagName('tbody')[0];
        if (!tbody) return;
        
        const rows = tbody.getElementsByTagName('tr');
        
        // Skip if no rows or if it's the empty state message
        if (rows.length === 0) return;
        
        // Check if the first row has a colspan (empty state message)
        const firstRow = rows[0];
        const firstCell = firstRow.getElementsByTagName('td')[0];
        if (firstCell && firstCell.hasAttribute('colspan')) {
            // This is the empty state message, don't filter
            return;
        }
        
        for (let row of rows) {
            let found = false;
            const cells = row.getElementsByTagName('td');
            
            // Search through relevant columns (skip actions column which is last)
            for (let i = 0; i < cells.length - 1; i++) {
                const cellText = cells[i].textContent || cells[i].innerText;
                if (cellText.toLowerCase().indexOf(searchTerm) > -1) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
    });
}

// Initialize search when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupTableSearch('pendingSearch', 'pendingTable');
    setupTableSearch('confirmedSearch', 'confirmedTable');
});
</script>

</body>
</html>