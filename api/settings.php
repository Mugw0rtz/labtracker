<?php
/**
 * Settings API
 * Handles settings and user management operations
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Require login for all operations
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

// Process request based on method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests
    $action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
    
    switch ($action) {
        case 'get_categories':
            // Get tool categories with tool count
            $query = "SELECT 
                     c.id, c.name, c.description,
                     COUNT(t.id) AS tool_count
                     FROM (
                         SELECT DISTINCT category AS name, NULL AS description, 0 AS id
                         FROM tools
                     ) AS c
                     LEFT JOIN tools t ON c.name = t.category
                     GROUP BY c.id, c.name, c.description
                     ORDER BY c.name";
            $categories = $db->fetchAll($query);
            
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        case 'get_settings':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to access settings'
                ]);
                break;
            }
            
            $group = isset($_GET['group']) ? sanitizeInput($_GET['group']) : '';
            
            // Build query condition
            $whereClause = !empty($group) ? "WHERE setting_group = ?" : "";
            $params = !empty($group) ? [$group] : [];
            $types = !empty($group) ? 's' : '';
            
            // Get settings
            $query = "SELECT * FROM settings $whereClause ORDER BY setting_group, setting_name";
            $settings = $db->fetchAll($query, $types, $params);
            
            // Group settings by category
            $groupedSettings = [];
            foreach ($settings as $setting) {
                $groupedSettings[$setting['setting_group']][$setting['setting_name']] = $setting['setting_value'];
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $groupedSettings
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST requests
    $action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
    
    // Check CSRF token for all POST requests
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please reload the page and try again.'
        ]);
        exit;
    }
    
    switch ($action) {
        case 'update_profile':
            // Update user profile
            $firstName = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
            $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
            $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
            
            // Validate inputs
            if (empty($firstName) || empty($lastName) || empty($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'First name, last name, and email are required'
                ]);
                break;
            }
            
            if (!isValidEmail($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid email address'
                ]);
                break;
            }
            
            if (!empty($phone) && !isValidPhone($phone)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid phone number'
                ]);
                break;
            }
            
            // Get current user data
            $query = "SELECT * FROM users WHERE id = ?";
            $user = $db->fetchSingle($query, "i", [$_SESSION['user_id']]);
            
            if (!$user) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                break;
            }
            
            // Check if email is already in use by another user
            $query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $existingUser = $db->fetchSingle($query, "si", [$email, $_SESSION['user_id']]);
            
            if ($existingUser) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This email is already in use by another user'
                ]);
                break;
            }
            
            // Handle password change if requested
            $updatePassword = false;
            $hashedPassword = '';
            
            if (!empty($newPassword)) {
                // Verify current password
                if (empty($currentPassword) || !verifyPassword($currentPassword, $user['password'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ]);
                    break;
                }
                
                // Validate new password
                if (strlen($newPassword) < 8) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'New password must be at least 8 characters long'
                    ]);
                    break;
                }
                
                if ($newPassword !== $confirmPassword) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'New password and confirmation do not match'
                    ]);
                    break;
                }
                
                $updatePassword = true;
                $hashedPassword = hashPassword($newPassword);
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update user profile
                if ($updatePassword) {
                    $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                             password = ?, email_notifications = ?, updated_at = NOW() 
                             WHERE id = ?";
                    $db->update($query, "sssssii", [
                        $firstName, $lastName, $email, $phone, 
                        $hashedPassword, $emailNotifications, $_SESSION['user_id']
                    ]);
                } else {
                    $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                             email_notifications = ?, updated_at = NOW() 
                             WHERE id = ?";
                    $db->update($query, "sssiii", [
                        $firstName, $lastName, $email, $phone, 
                        $emailNotifications, $_SESSION['user_id']
                    ]);
                }
                
                // Commit transaction
                $conn->commit();
                
                // Update session data
                $_SESSION['user_email'] = $email;
                
                // Log event
                logEvent("User profile updated", "info", "User ID: " . $_SESSION['user_id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update profile: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'update_settings':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to update settings'
                ]);
                break;
            }
            
            $settingGroup = isset($_POST['setting_group']) ? sanitizeInput($_POST['setting_group']) : '';
            $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
            
            if (empty($settingGroup) || empty($settings) || !is_array($settings)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid settings data'
                ]);
                break;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                $updateCount = 0;
                
                foreach ($settings as $name => $value) {
                    // Sanitize
                    $settingName = sanitizeInput($name);
                    $settingValue = sanitizeInput($value);
                    
                    // Check if setting exists
                    $query = "SELECT id FROM settings WHERE setting_group = ? AND setting_name = ?";
                    $existingSetting = $db->fetchSingle($query, "ss", [$settingGroup, $settingName]);
                    
                    if ($existingSetting) {
                        // Update existing setting
                        $query = "UPDATE settings SET setting_value = ?, updated_at = NOW() 
                                 WHERE setting_group = ? AND setting_name = ?";
                        $db->update($query, "sss", [$settingValue, $settingGroup, $settingName]);
                    } else {
                        // Insert new setting
                        $query = "INSERT INTO settings (setting_group, setting_name, setting_value, created_at, updated_at) 
                                 VALUES (?, ?, ?, NOW(), NOW())";
                        $db->insert($query, "sss", [$settingGroup, $settingName, $settingValue]);
                    }
                    
                    $updateCount++;
                }
                
                // Commit transaction
                $conn->commit();
                
                // Log event
                logEvent("Settings updated", "info", "Group: $settingGroup, Count: $updateCount");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings updated successfully',
                    'count' => $updateCount
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update settings: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'add_user':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to add users'
                ]);
                break;
            }
            
            // Get and sanitize inputs
            $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
            $firstName = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
            $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
            $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : 'user';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            $sendWelcome = isset($_POST['send_welcome']) && $_POST['send_welcome'] === 'on';
            
            // Validate inputs
            if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All required fields must be filled'
                ]);
                break;
            }
            
            if (!isValidEmail($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid email address'
                ]);
                break;
            }
            
            if (!empty($phone) && !isValidPhone($phone)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid phone number'
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
            
            if (strlen($password) < 8) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Password must be at least 8 characters long'
                ]);
                break;
            }
            
            if (!in_array($role, ['user', 'lab_tech', 'admin'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid role'
                ]);
                break;
            }
            
            // Check if username or email already exists
            $query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $existingUser = $db->fetchSingle($query, "ss", [$username, $email]);
            
            if ($existingUser) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Username or email already exists'
                ]);
                break;
            }
            
            // Hash password
            $hashedPassword = hashPassword($password);
            
            // Insert new user
            $query = "INSERT INTO users (username, first_name, last_name, email, phone, password, role, status, email_notifications, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())";
            $userId = $db->insert($query, "ssssss", [
                $username, $firstName, $lastName, $email, $phone, $hashedPassword, $role
            ]);
            
            if ($userId) {
                // Send welcome email if requested
                if ($sendWelcome) {
                    $subject = "Welcome to " . APP_NAME;
                    $message = "
                        <html>
                        <head>
                            <title>Welcome to " . APP_NAME . "</title>
                        </head>
                        <body>
                            <h2>Welcome to " . APP_NAME . "!</h2>
                            <p>Hello $firstName,</p>
                            <p>Your account has been created successfully.</p>
                            <p><strong>Username:</strong> $username</p>
                            <p>You can now log in to the system and start using the laboratory tool management features.</p>
                            <p>Regards,<br>" . APP_NAME . " Team</p>
                        </body>
                        </html>
                    ";
                    
                    sendEmail($email, $subject, $message);
                }
                
                // Log event
                logEvent("User added", "info", "User ID: $userId, Username: $username, Role: $role");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'User added successfully',
                    'user_id' => $userId
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add user'
                ]);
            }
            break;
            
        case 'edit_user':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to edit users'
                ]);
                break;
            }
            
            // Get and sanitize inputs
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
            $firstName = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
            $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
            $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : '';
            $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
            
            // Validate inputs
            if ($userId <= 0 || empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($role) || empty($status)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All required fields must be filled'
                ]);
                break;
            }
            
            if (!isValidEmail($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid email address'
                ]);
                break;
            }
            
            if (!empty($phone) && !isValidPhone($phone)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter a valid phone number'
                ]);
                break;
            }
            
            if (!empty($password) && $password !== $confirmPassword) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Passwords do not match'
                ]);
                break;
            }
            
            if (!empty($password) && strlen($password) < 8) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Password must be at least 8 characters long'
                ]);
                break;
            }
            
            if (!in_array($role, ['user', 'lab_tech', 'admin'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid role'
                ]);
                break;
            }
            
            if (!in_array($status, ['active', 'inactive'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid status'
                ]);
                break;
            }
            
            // Check if user exists
            $query = "SELECT * FROM users WHERE id = ?";
            $user = $db->fetchSingle($query, "i", [$userId]);
            
            if (!$user) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                break;
            }
            
            // Check if username or email already exists for another user
            $query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
            $existingUser = $db->fetchSingle($query, "ssi", [$username, $email, $userId]);
            
            if ($existingUser) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Username or email already exists for another user'
                ]);
                break;
            }
            
            // Update user
            if (!empty($password)) {
                // With password change
                $hashedPassword = hashPassword($password);
                $query = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, 
                         phone = ?, password = ?, role = ?, status = ?, updated_at = NOW() 
                         WHERE id = ?";
                $result = $db->update($query, "ssssssssi", [
                    $username, $firstName, $lastName, $email, 
                    $phone, $hashedPassword, $role, $status, $userId
                ]);
            } else {
                // Without password change
                $query = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, 
                         phone = ?, role = ?, status = ?, updated_at = NOW() 
                         WHERE id = ?";
                $result = $db->update($query, "sssssssi", [
                    $username, $firstName, $lastName, $email, 
                    $phone, $role, $status, $userId
                ]);
            }
            
            if ($result !== false) {
                // Log event
                logEvent("User updated", "info", "User ID: $userId, Username: $username, Role: $role, Status: $status");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update user'
                ]);
            }
            break;
            
        case 'delete_user':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to delete users'
                ]);
                break;
            }
            
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            
            if ($userId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid user ID'
                ]);
                break;
            }
            
            // Check if user exists
            $query = "SELECT * FROM users WHERE id = ?";
            $user = $db->fetchSingle($query, "i", [$userId]);
            
            if (!$user) {
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                break;
            }
            
            // Prevent deleting the current user
            if ($userId == $_SESSION['user_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ]);
                break;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete user's notifications
                $query = "DELETE FROM notifications WHERE user_id = ?";
                $db->delete($query, "i", [$userId]);
                
                // Delete user's transactions
                $query = "DELETE FROM transactions WHERE user_id = ?";
                $db->delete($query, "i", [$userId]);
                
                // Delete user
                $query = "DELETE FROM users WHERE id = ?";
                $db->delete($query, "i", [$userId]);
                
                // Commit transaction
                $conn->commit();
                
                // Log event
                logEvent("User deleted", "warning", "User ID: $userId, Username: {$user['username']}");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete user: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'add_template':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to add notification templates'
                ]);
                break;
            }
            
            $type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : '';
            $subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
            $content = isset($_POST['content']) ? sanitizeInput($_POST['content']) : '';
            
            if (empty($type) || empty($subject) || empty($content)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All fields are required'
                ]);
                break;
            }
            
            // Insert template
            $query = "INSERT INTO notification_templates (type, subject, content, created_at, updated_at) 
                     VALUES (?, ?, ?, NOW(), NOW())";
            $templateId = $db->insert($query, "sss", [$type, $subject, $content]);
            
            if ($templateId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification template added successfully',
                    'template_id' => $templateId
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add notification template'
                ]);
            }
            break;
            
        case 'edit_template':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to edit notification templates'
                ]);
                break;
            }
            
            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            $type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : '';
            $subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
            $content = isset($_POST['content']) ? sanitizeInput($_POST['content']) : '';
            
            if ($templateId <= 0 || empty($type) || empty($subject) || empty($content)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All fields are required'
                ]);
                break;
            }
            
            // Update template
            $query = "UPDATE notification_templates SET type = ?, subject = ?, content = ?, updated_at = NOW() 
                     WHERE id = ?";
            $result = $db->update($query, "sssi", [$type, $subject, $content, $templateId]);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification template updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update notification template'
                ]);
            }
            break;
            
        case 'delete_template':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to delete notification templates'
                ]);
                break;
            }
            
            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            
            if ($templateId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid template ID'
                ]);
                break;
            }
            
            // Delete template
            $query = "DELETE FROM notification_templates WHERE id = ?";
            $result = $db->delete($query, "i", [$templateId]);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification template deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete notification template'
                ]);
            }
            break;
            
        case 'add_category':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to add categories'
                ]);
                break;
            }
            
            $name = isset($_POST['category_name']) ? sanitizeInput($_POST['category_name']) : '';
            $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
            
            if (empty($name)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Category name is required'
                ]);
                break;
            }
            
            // Check if category already exists
            $query = "SELECT COUNT(*) as count FROM tools WHERE category = ?";
            $existingCategory = $db->fetchSingle($query, "s", [$name]);
            
            if ($existingCategory && $existingCategory['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'A category with this name already exists'
                ]);
                break;
            }
            
            // Insert a dummy tool with this category to make it available
            $code = generateToolCode("CAT-DUMMY");
            $query = "INSERT INTO tools (name, code, category, storage_location, description, status, created_at, updated_at) 
                     VALUES ('Category Placeholder', ?, ?, 'Storage', ?, 'inactive', NOW(), NOW())";
            $result = $db->insert($query, "sss", [$code, $name, $description]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Category added successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add category'
                ]);
            }
            break;
            
        case 'delete_category':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to delete categories'
                ]);
                break;
            }
            
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            
            if ($categoryId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid category ID'
                ]);
                break;
            }
            
            // Get the category name
            $query = "SELECT name FROM categories WHERE id = ?";
            $category = $db->fetchSingle($query, "i", [$categoryId]);
            
            if (!$category) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Category not found'
                ]);
                break;
            }
            
            $categoryName = $category['name'];
            
            // Check if any active tools use this category
            $query = "SELECT COUNT(*) as count FROM tools WHERE category = ? AND status != 'inactive'";
            $activeTools = $db->fetchSingle($query, "s", [$categoryName]);
            
            if ($activeTools && $activeTools['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete category: it is being used by active tools'
                ]);
                break;
            }
            
            // Delete inactive tools in this category
            $query = "DELETE FROM tools WHERE category = ? AND status = 'inactive'";
            $db->delete($query, "s", [$categoryName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} else {
    // Method not allowed
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET, POST');
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

// Close database connection
$db->closeConnection();
?>
