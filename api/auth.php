<?php
/**
 * Authentication API
 * Handles login, logout, and password reset functionality
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Allow only POST requests for most actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($_GET['action'] ?? '', ['check_session'])) {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Get the requested action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Process actions
switch ($action) {
    case 'login':
        // Validate CSRF token for login form
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid security token. Please reload the page and try again.'
            ]);
            break;
        }
        
        // Sanitize inputs
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // Don't sanitize password
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';
        
        // Validate inputs
        if (empty($username) || empty($password)) {
            echo json_encode([
                'success' => false,
                'message' => 'Username and password are required'
            ]);
            break;
        }
        
        // Check if user exists
        $query = "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1";
        $user = $db->fetchSingle($query, "ss", [$username, $username]);
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Check if account is active
            if ($user['status'] !== 'active') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Your account is not active. Please contact an administrator.'
                ]);
                
                // Log failed login
                logEvent("Login failed - inactive account", "warning", "Username: " . $username);
                break;
            }
            
            // Update last login time
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $db->update($updateQuery, "i", [$user['id']]);
            
            // Create user session
            createUserSession($user);
            
            // Handle remember me functionality
            if ($rememberMe) {
                // Generate unique token
                $token = bin2hex(random_bytes(32));
                $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                
                // Store token in database
                $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days
                $query = "INSERT INTO remember_tokens (user_id, token_hash, expires) VALUES (?, ?, ?)";
                $db->insert($query, "iss", [$user['id'], $tokenHash, $expires]);
                
                // Set cookie
                setcookie('remember_token', $token, time() + 2592000, '/', '', true, true);
            }
            
            // Log successful login
            logEvent("User logged in successfully", "info", "User ID: " . $user['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
            
            // Log failed login attempt
            logEvent("Failed login attempt", "warning", "Username: " . $username);
        }
        break;
    
    case 'logout':
        // Log the logout event if user was logged in
        if (isLoggedIn()) {
            logEvent("User logged out", "info", "User ID: " . $_SESSION['user_id']);
            
            // Remove remember me token if exists
            if (isset($_COOKIE['remember_token'])) {
                $token = $_COOKIE['remember_token'];
                $query = "DELETE FROM remember_tokens WHERE user_id = ?";
                $db->delete($query, "i", [$_SESSION['user_id']]);
                
                // Delete cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        }
        
        // Destroy the session
        logout();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully',
            'redirect' => 'login.php'
        ]);
        break;
    
    case 'forgot_password':
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid security token. Please reload the page and try again.'
            ]);
            break;
        }
        
        // Sanitize email
        $email = sanitizeInput($_POST['email']);
        
        // Validate email
        if (empty($email) || !isValidEmail($email)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter a valid email address'
            ]);
            break;
        }
        
        // Check if user exists
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $user = $db->fetchSingle($query, "s", [$email]);
        
        if (!$user) {
            // Don't reveal if email exists or not (security)
            echo json_encode([
                'success' => true,
                'message' => 'If your email exists in our system, you will receive password reset instructions.'
            ]);
            break;
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Store token in database
        $query = "INSERT INTO password_reset_tokens (user_id, token_hash, expires) VALUES (?, ?, ?)";
        $db->insert($query, "iss", [$user['id'], $tokenHash, $expires]);
        
        // Send reset email
        $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $token;
        $subject = APP_NAME . " - Password Reset";
        $message = "
            <html>
            <head>
                <title>Password Reset</title>
            </head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hello {$user['first_name']},</p>
                <p>You recently requested to reset your password for your " . APP_NAME . " account. Click the button below to reset it.</p>
                <p><a href=\"{$resetUrl}\" style=\"background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block;\">Reset Your Password</a></p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have questions.</p>
                <p>This link is valid for 1 hour.</p>
                <p>Regards,<br>" . APP_NAME . " Team</p>
            </body>
            </html>
        ";
        
        $mailSent = sendEmail($email, $subject, $message);
        
        // Log password reset request
        if ($mailSent) {
            logEvent("Password reset email sent", "info", "User ID: " . $user['id']);
        } else {
            logEvent("Failed to send password reset email", "error", "User ID: " . $user['id']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'If your email exists in our system, you will receive password reset instructions.'
        ]);
        break;
    
    case 'reset_password':
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid security token. Please reload the page and try again.'
            ]);
            break;
        }
        
        // Sanitize and validate token
        $token = sanitizeInput($_POST['token']);
        $password = $_POST['password']; // Don't sanitize password
        $confirmPassword = $_POST['confirm_password']; // Don't sanitize password
        
        // Validate inputs
        if (empty($token)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid reset token'
            ]);
            break;
        }
        
        if (empty($password) || strlen($password) < 8) {
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 8 characters long'
            ]);
            break;
        }
        
        if ($password !== $confirmPassword) {
            echo json_encode([
                'success' => false,
                'message' => 'Passwords do not match'
            ]);
            break;
        }
        
        // Find token in database
        $query = "SELECT prt.*, u.id as user_id 
                  FROM password_reset_tokens prt
                  JOIN users u ON prt.user_id = u.id
                  WHERE prt.expires > NOW()
                  ORDER BY prt.created_at DESC
                  LIMIT 20";
        $tokens = $db->fetchAll($query);
        
        $validToken = false;
        $userId = null;
        
        // Verify token (loop through to find matching token)
        foreach ($tokens as $t) {
            if (password_verify($token, $t['token_hash'])) {
                $validToken = true;
                $userId = $t['user_id'];
                break;
            }
        }
        
        if (!$validToken || !$userId) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or expired token. Please request a new password reset.'
            ]);
            break;
        }
        
        // Update password
        $hashedPassword = hashPassword($password);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $result = $db->update($query, "si", [$hashedPassword, $userId]);
        
        if ($result) {
            // Delete all reset tokens for this user
            $query = "DELETE FROM password_reset_tokens WHERE user_id = ?";
            $db->delete($query, "i", [$userId]);
            
            // Log password reset
            logEvent("Password reset successful", "info", "User ID: " . $userId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Your password has been reset successfully. You can now login with your new password.',
                'redirect' => 'login.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update password. Please try again.'
            ]);
        }
        break;
    
    case 'check_session':
        // Check if user is logged in
        echo json_encode([
            'success' => true,
            'logged_in' => isLoggedIn(),
            'is_admin' => isAdmin(),
            'is_staff' => isStaff()
        ]);
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

// Close database connection
$db->closeConnection();
?>
