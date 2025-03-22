<?php
/**
 * Session Management
 * Handles user session operations and security
 */

// Start session with secure settings
function secureSessionStart() {
    require_once __DIR__ . '/../config/config.php';
    
    // Set session cookie parameters
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        SESSION_LIFETIME,
        $cookieParams["path"], 
        $cookieParams["domain"],
        true,  // Secure (HTTPS only)
        true   // HttpOnly flag
    );
    
    // Set session name
    session_name(SESSION_NAME);
    
    // Start the session
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically to prevent fixation attacks
    if (!isset($_SESSION['last_regeneration'])) {
        regenerateSession();
    } else {
        // Regenerate session ID every 30 minutes
        $interval = 1800;
        if ($_SESSION['last_regeneration'] < (time() - $interval)) {
            regenerateSession();
        }
    }
}

/**
 * Regenerate session ID
 */
function regenerateSession() {
    // Save old session data
    $old_session = $_SESSION;
    
    // Generate new session ID and delete old session
    session_regenerate_id(true);
    
    // Restore session data
    $_SESSION = $old_session;
    
    // Update regeneration time
    $_SESSION['last_regeneration'] = time();
}

/**
 * Create a new user session after successful login
 * @param array $user User data from database
 */
function createUserSession($user) {
    // Remove sensitive info
    unset($user['password']);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['is_logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Set CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Check if a user is logged in
 * @return bool Returns true if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
}

/**
 * Check if a user has admin rights
 * @return bool Returns true if user has admin role
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if a user has staff rights (admin or lab technician)
 * @return bool Returns true if user is staff
 */
function isStaff() {
    return isLoggedIn() && 
          ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'lab_tech');
}

/**
 * Validate CSRF token to prevent cross-site request forgery
 * @param string $token CSRF token from the form
 * @return bool Returns true if token is valid
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token
 * @return string Returns the current CSRF token
 */
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Destroys the current session and logs out the user
 */
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Redirect user if not logged in
 * @param string $location Optional redirect location
 */
function requireLogin($location = '/login.php') {
    if (!isLoggedIn()) {
        header("Location: $location");
        exit;
    }
}

/**
 * Redirect user if not an administrator
 * @param string $location Optional redirect location
 */
function requireAdmin($location = '/index.php') {
    if (!isAdmin()) {
        header("Location: $location");
        exit;
    }
}

/**
 * Redirect user if not staff
 * @param string $location Optional redirect location
 */
function requireStaff($location = '/index.php') {
    if (!isStaff()) {
        header("Location: $location");
        exit;
    }
}

// Initialize secure session
secureSessionStart();
?>
