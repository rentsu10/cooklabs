<?php
// Make sure we can see errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config - this should load everything including CSRF
require_once __DIR__ . '/../inc/config.php';

echo '<h1>CSRF Test</h1>';

// Generate and display token
$token = getCSRFToken();
echo '<p><strong>Current CSRF Token:</strong> ' . htmlspecialchars($token) . '</p>';

// Display form
echo '<form method="POST" action="">';
csrfField();
echo '<button type="submit">Submit</button>';
echo '</form>';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<hr>';
    if (validateCSRFToken()) {
        echo '<p style="color: green; font-weight: bold;">✓ CSRF token is VALID!</p>';
    } else {
        echo '<p style="color: red; font-weight: bold;">✗ CSRF token is INVALID!</p>';
    }
}

// Debug info
echo '<hr>';
echo '<h3>Debug Info:</h3>';
echo 'Session ID: ' . session_id() . '<br>';
echo 'POST data: <pre>';
print_r($_POST);
echo '</pre>';
echo 'Session CSRF token: ' . ($_SESSION['csrf_token'] ?? 'NOT SET');
?>