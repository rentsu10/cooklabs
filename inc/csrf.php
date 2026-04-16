<?php
/**
 * CSRF Protection Helper
 * Prevents Cross-Site Request Forgery attacks
 */

// Session is already started by config.php, so no session_start() here

/**
 * Generate a CSRF token and store it in session
 * @return string The generated token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get the current CSRF token
 * @return string|null The token or null if not set
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Output a hidden input field with CSRF token
 * Use this inside your forms
 */
function csrfField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate the CSRF token from POST request
 * @param string $token The token to validate (usually from $_POST['csrf_token'])
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token = null) {
    // Get token from parameter or POST
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    
    // Check if token exists in session and matches
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token and exit with error if invalid
 * Use this at the beginning of POST handlers
 */
function requireValidCSRFToken() {
    if (!validateCSRFToken()) {
        // Log the attack attempt
        error_log('CSRF validation failed for IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Handle AJAX requests differently
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'CSRF validation failed']);
            exit;
        }
        
        // Regular form submission
        $_SESSION['error'] = 'Security validation failed. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }
}

/**
 * Regenerate CSRF token (useful after login)
 */
function regenerateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
?>