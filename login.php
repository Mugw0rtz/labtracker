<?php
/**
 * Login Page
 * Handles user authentication
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$username = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt to login
    if (empty($errors)) {
        // Connect to database
        $db = new Database();
        $conn = $db->getConnection();
        
        // Prepare query to get user
        $query = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
        $user = $db->fetchSingle($query, "ss", [$username, $username]);
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Check if account is active
            if ($user['status'] !== 'active') {
                $errors[] = 'Your account is not active. Please contact an administrator.';
            } else {
                // Update last login time
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $db->update($updateQuery, "i", [$user['id']]);
                
                // Create user session
                createUserSession($user);
                
                // Log successful login
                logEvent("User logged in successfully", "info", "User ID: " . $user['id']);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid username or password';
            
            // Log failed login attempt
            logEvent("Failed login attempt", "warning", "Username: " . $username);
        }
        
        $db->closeConnection();
    }
}

$pageTitle = 'Login';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header bg-primary text-white">
                    <h3 class="text-center font-weight-light my-2">Login</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Extra JavaScript for password toggle -->
<?php 
$extraJS = <<<EOT
<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>