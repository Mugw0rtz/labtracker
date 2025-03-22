<?php
/**
 * Settings Page
 * Allows administrators to configure system settings
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Require login
requireLogin();

// Default to profile tab for regular users, otherwise allow admin tabs
$defaultTab = isAdmin() ? 'general' : 'profile';
$tab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : $defaultTab;

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$user = $db->fetchSingle($query, "i", [$_SESSION['user_id']]);

// Get system settings if admin
$systemSettings = [];
if (isAdmin()) {
    $query = "SELECT * FROM settings ORDER BY setting_group, setting_name";
    $settings = $db->fetchAll($query);
    
    // Group settings by category
    foreach ($settings as $setting) {
        $systemSettings[$setting['setting_group']][$setting['setting_name']] = $setting['setting_value'];
    }
}

// Get notification settings if admin
$notificationSettings = [];
if (isAdmin() && ($tab == 'notifications')) {
    $query = "SELECT * FROM notification_templates ORDER BY type";
    $notificationSettings = $db->fetchAll($query);
}

// Get user list if admin
$usersList = [];
if (isAdmin() && ($tab == 'users')) {
    $query = "SELECT * FROM users ORDER BY username";
    $usersList = $db->fetchAll($query);
}

// Close database connection
$db->closeConnection();

$pageTitle = 'Settings';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 fs-6">Settings</h5>
                </div>
                <div class="list-group list-group-flush">
                    <!-- Profile Settings - Available to all users -->
                    <a href="settings.php?tab=profile" class="list-group-item list-group-item-action <?php echo ($tab == 'profile') ? 'active' : ''; ?>">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                    
                    <!-- Admin-only settings -->
                    <?php if (isAdmin()): ?>
                        <a href="settings.php?tab=general" class="list-group-item list-group-item-action <?php echo ($tab == 'general') ? 'active' : ''; ?>">
                            <i class="fas fa-cog me-2"></i> General Settings
                        </a>
                        <a href="settings.php?tab=notifications" class="list-group-item list-group-item-action <?php echo ($tab == 'notifications') ? 'active' : ''; ?>">
                            <i class="fas fa-bell me-2"></i> Notification Settings
                        </a>
                        <a href="settings.php?tab=users" class="list-group-item list-group-item-action <?php echo ($tab == 'users') ? 'active' : ''; ?>">
                            <i class="fas fa-users me-2"></i> User Management
                        </a>
                        <a href="settings.php?tab=categories" class="list-group-item list-group-item-action <?php echo ($tab == 'categories') ? 'active' : ''; ?>">
                            <i class="fas fa-tags me-2"></i> Tool Categories
                        </a>
                        <a href="settings.php?tab=backup" class="list-group-item list-group-item-action <?php echo ($tab == 'backup') ? 'active' : ''; ?>">
                            <i class="fas fa-database me-2"></i> Backup & Restore
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- Profile Settings -->
            <?php if ($tab == 'profile'): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Profile Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" method="post" action="/api/settings.php">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>">
                            </div>
                            
                            <hr class="my-4">
                            <h5>Change Password</h5>
                            <p class="text-muted small">Leave blank if you don't want to change your password</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notification_preferences" class="form-label">Notification Preferences</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo ($user['email_notifications'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Receive email notifications
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            
            <!-- General Settings (Admin only) -->
            <?php elseif ($tab == 'general' && isAdmin()): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">General Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="generalSettingsForm" method="post" action="/api/settings.php">
                            <input type="hidden" name="action" value="update_settings">
                            <input type="hidden" name="setting_group" value="general">
                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="system_name" class="form-label">System Name</label>
                                <input type="text" class="form-control" id="system_name" name="settings[system_name]" 
                                       value="<?php echo $systemSettings['general']['system_name'] ?? APP_NAME; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="max_borrow_days" class="form-label">Maximum Borrow Days</label>
                                <input type="number" class="form-control" id="max_borrow_days" name="settings[max_borrow_days]" 
                                       value="<?php echo $systemSettings['general']['max_borrow_days'] ?? '14'; ?>" required>
                                <div class="form-text">Default maximum number of days a tool can be borrowed</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="default_maintenance_interval" class="form-label">Default Maintenance Interval (days)</label>
                                <input type="number" class="form-control" id="default_maintenance_interval" name="settings[default_maintenance_interval]" 
                                       value="<?php echo $systemSettings['general']['default_maintenance_interval'] ?? '90'; ?>" required>
                                <div class="form-text">Default maintenance interval for tools in days</div>
                            </div>
                            
                            <h5 class="mt-4">Email Settings</h5>
                            <div class="mb-3">
                                <label for="email_from" class="form-label">From Email Address</label>
                                <input type="email" class="form-control" id="email_from" name="settings[email_from]" 
                                       value="<?php echo $systemSettings['email']['email_from'] ?? MAIL_FROM_ADDRESS; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_from_name" class="form-label">From Name</label>
                                <input type="text" class="form-control" id="email_from_name" name="settings[email_from_name]" 
                                       value="<?php echo $systemSettings['email']['email_from_name'] ?? MAIL_FROM_NAME; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                </div>
            
            <!-- Notification Settings (Admin only) -->
            <?php elseif ($tab == 'notifications' && isAdmin()): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Notification Settings</h5>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                            <i class="fas fa-plus me-2"></i> Add Template
                        </button>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="notificationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                                    Templates
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                                    Schedule
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="notificationTabsContent">
                            <!-- Notification Templates Tab -->
                            <div class="tab-pane fade show active" id="templates" role="tabpanel" aria-labelledby="templates-tab">
                                <?php if (empty($notificationSettings)): ?>
                                    <div class="alert alert-info">
                                        No notification templates found. Create your first template using the "Add Template" button.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Subject</th>
                                                    <th>Last Updated</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($notificationSettings as $template): ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                            $typeName = ucfirst(str_replace('_', ' ', $template['type']));
                                                            $typeClass = 'bg-secondary';
                                                            
                                                            if ($template['type'] == 'due_date') {
                                                                $typeClass = 'bg-warning';
                                                            } elseif ($template['type'] == 'overdue') {
                                                                $typeClass = 'bg-danger';
                                                            } elseif ($template['type'] == 'maintenance') {
                                                                $typeClass = 'bg-info';
                                                            } elseif ($template['type'] == 'system') {
                                                                $typeClass = 'bg-primary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $typeClass; ?>"><?php echo $typeName; ?></span>
                                                        </td>
                                                        <td><?php echo $template['subject']; ?></td>
                                                        <td><?php echo formatDateTime($template['updated_at']); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary edit-template" 
                                                                    data-id="<?php echo $template['id']; ?>"
                                                                    data-type="<?php echo $template['type']; ?>"
                                                                    data-subject="<?php echo $template['subject']; ?>"
                                                                    data-content="<?php echo htmlspecialchars($template['content']); ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#editTemplateModal">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger delete-template" 
                                                                    data-id="<?php echo $template['id']; ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteTemplateModal">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Notification Schedule Tab -->
                            <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                                <form id="notificationScheduleForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="update_notification_schedule">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="due_date_reminder_days" class="form-label">Due Date Reminder (days before)</label>
                                        <input type="number" class="form-control" id="due_date_reminder_days" name="settings[due_date_reminder_days]" 
                                               value="<?php echo $systemSettings['notifications']['due_date_reminder_days'] ?? '1'; ?>" required>
                                        <div class="form-text">Send reminder X days before the tool is due</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="overdue_reminder_interval" class="form-label">Overdue Reminder Interval (hours)</label>
                                        <input type="number" class="form-control" id="overdue_reminder_interval" name="settings[overdue_reminder_interval]" 
                                               value="<?php echo $systemSettings['notifications']['overdue_reminder_interval'] ?? '24'; ?>" required>
                                        <div class="form-text">Send overdue reminder every X hours</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="maintenance_reminder_days" class="form-label">Maintenance Reminder (days before)</label>
                                        <input type="number" class="form-control" id="maintenance_reminder_days" name="settings[maintenance_reminder_days]" 
                                               value="<?php echo $systemSettings['notifications']['maintenance_reminder_days'] ?? '7'; ?>" required>
                                        <div class="form-text">Send reminder X days before scheduled maintenance</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="enable_email_notifications" name="settings[enable_email_notifications]" value="1"
                                               <?php echo ($systemSettings['notifications']['enable_email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_email_notifications">Enable email notifications</label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add Template Modal -->
                <div class="modal fade" id="addTemplateModal" tabindex="-1" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addTemplateModalLabel">Add Notification Template</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addTemplateForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="add_template">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="add_type" class="form-label">Notification Type</label>
                                        <select class="form-select" id="add_type" name="type" required>
                                            <option value="due_date">Due Date Reminder</option>
                                            <option value="overdue">Overdue Notice</option>
                                            <option value="maintenance">Maintenance Alert</option>
                                            <option value="system">System Notification</option>
                                            <option value="welcome">Welcome Message</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_subject" class="form-label">Subject/Title</label>
                                        <input type="text" class="form-control" id="add_subject" name="subject" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="add_content" name="content" rows="5" required></textarea>
                                        <div class="form-text">
                                            Available variables: {user_name}, {tool_name}, {due_date}, {days_remaining}, {borrow_date}, etc.
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="submitAddTemplate">Add Template</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Template Modal -->
                <div class="modal fade" id="editTemplateModal" tabindex="-1" aria-labelledby="editTemplateModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editTemplateModalLabel">Edit Notification Template</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editTemplateForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="edit_template">
                                    <input type="hidden" name="template_id" id="edit_template_id">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="edit_type" class="form-label">Notification Type</label>
                                        <select class="form-select" id="edit_type" name="type" required>
                                            <option value="due_date">Due Date Reminder</option>
                                            <option value="overdue">Overdue Notice</option>
                                            <option value="maintenance">Maintenance Alert</option>
                                            <option value="system">System Notification</option>
                                            <option value="welcome">Welcome Message</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_subject" class="form-label">Subject/Title</label>
                                        <input type="text" class="form-control" id="edit_subject" name="subject" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                                        <div class="form-text">
                                            Available variables: {user_name}, {tool_name}, {due_date}, {days_remaining}, {borrow_date}, etc.
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="submitEditTemplate">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Template Modal -->
                <div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-labelledby="deleteTemplateModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteTemplateModalLabel">Delete Notification Template</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this notification template? This action cannot be undone.</p>
                                <form id="deleteTemplateForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" id="delete_template_id">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDeleteTemplate">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- User Management (Admin only) -->
            <?php elseif ($tab == 'users' && isAdmin()): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">User Management</h5>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i> Add User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usersList as $u): ?>
                                        <tr>
                                            <td><?php echo $u['username']; ?></td>
                                            <td><?php echo $u['first_name'] . ' ' . $u['last_name']; ?></td>
                                            <td><?php echo $u['email']; ?></td>
                                            <td>
                                                <?php 
                                                $roleClass = 'bg-secondary';
                                                if ($u['role'] == 'admin') {
                                                    $roleClass = 'bg-danger';
                                                } elseif ($u['role'] == 'lab_tech') {
                                                    $roleClass = 'bg-primary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $roleClass; ?>">
                                                    <?php echo ucfirst($u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($u['status'] == 'active') ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($u['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $u['last_login'] ? formatDateTime($u['last_login']) : 'Never'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-user" 
                                                        data-id="<?php echo $u['id']; ?>"
                                                        data-username="<?php echo $u['username']; ?>"
                                                        data-firstname="<?php echo $u['first_name']; ?>"
                                                        data-lastname="<?php echo $u['last_name']; ?>"
                                                        data-email="<?php echo $u['email']; ?>"
                                                        data-phone="<?php echo $u['phone']; ?>"
                                                        data-role="<?php echo $u['role']; ?>"
                                                        data-status="<?php echo $u['status']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($_SESSION['user_id'] != $u['id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger delete-user" 
                                                            data-id="<?php echo $u['id']; ?>"
                                                            data-username="<?php echo $u['username']; ?>"
                                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Add User Modal -->
                <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addUserForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="add_user">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="add_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="add_username" name="username" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="add_firstname" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="add_firstname" name="first_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="add_lastname" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="add_lastname" name="last_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="add_email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_phone" class="form-label">Phone (optional)</label>
                                        <input type="text" class="form-control" id="add_phone" name="phone">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_role" class="form-label">Role</label>
                                        <select class="form-select" id="add_role" name="role" required>
                                            <option value="user">Regular User</option>
                                            <option value="lab_tech">Lab Technician</option>
                                            <option value="admin">Administrator</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="add_password" name="password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="add_confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="add_send_welcome" name="send_welcome" checked>
                                        <label class="form-check-label" for="add_send_welcome">Send welcome email</label>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="submitAddUser">Add User</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit User Modal -->
                <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editUserForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="edit_user">
                                    <input type="hidden" name="user_id" id="edit_user_id">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="edit_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="edit_username" name="username" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="edit_firstname" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="edit_firstname" name="first_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_lastname" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="edit_lastname" name="last_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="edit_email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_phone" class="form-label">Phone (optional)</label>
                                        <input type="text" class="form-control" id="edit_phone" name="phone">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_role" class="form-label">Role</label>
                                        <select class="form-select" id="edit_role" name="role" required>
                                            <option value="user">Regular User</option>
                                            <option value="lab_tech">Lab Technician</option>
                                            <option value="admin">Administrator</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_status" class="form-label">Status</label>
                                        <select class="form-select" id="edit_status" name="status" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                                        <input type="password" class="form-control" id="edit_password" name="password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="submitEditUser">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Delete User Modal -->
                <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete user <strong id="delete_username"></strong>? This action cannot be undone.</p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Deleting this user will also remove all their borrowing history.
                                </div>
                                <form id="deleteUserForm" method="post" action="/api/settings.php">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" id="delete_user_id">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDeleteUser">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- Tool Categories (Admin only) -->
            <?php elseif ($tab == 'categories' && isAdmin()): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Tool Categories</h5>
                        <button class="btn btn-sm btn-light" id="addCategoryBtn">
                            <i class="fas fa-plus me-2"></i> Add Category
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row" id="categoriesList">
                            <div class="col-12 text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add Category Form -->
                        <div class="row mt-4" id="addCategoryForm" style="display: none;">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Add New Category</h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="categoryForm" method="post" action="/api/settings.php">
                                            <input type="hidden" name="action" value="add_category">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                            
                                            <div class="mb-3">
                                                <label for="category_name" class="form-label">Category Name</label>
                                                <input type="text" class="form-control" id="category_name" name="category_name" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="category_description" class="form-label">Description (optional)</label>
                                                <textarea class="form-control" id="category_description" name="description" rows="2"></textarea>
                                            </div>
                                            
                                            <div class="text-end">
                                                <button type="button" class="btn btn-secondary me-2" id="cancelAddCategory">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Add Category</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- Backup & Restore (Admin only) -->
            <?php elseif ($tab == 'backup' && isAdmin()): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Backup & Restore</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Create Backup</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Generate a backup of your database and system settings.</p>
                                        <form id="backupForm" method="post" action="/api/settings.php">
                                            <input type="hidden" name="action" value="create_backup">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="include_transactions" name="include_transactions" checked>
                                                <label class="form-check-label" for="include_transactions">Include transaction history</label>
                                            </div>
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="include_notifications" name="include_notifications" checked>
                                                <label class="form-check-label" for="include_notifications">Include notifications</label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-download me-2"></i> Generate Backup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Restore Backup</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Restore your database from a previous backup.</p>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Restoring a backup will overwrite your current data.
                                        </div>
                                        <form id="restoreForm" method="post" action="/api/settings.php" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                            
                                            <div class="mb-3">
                                                <label for="backup_file" class="form-label">Backup File</label>
                                                <input type="file" class="form-control" id="backup_file" name="backup_file" required>
                                            </div>
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" id="confirm_restore" name="confirm_restore" required>
                                                <label class="form-check-label" for="confirm_restore">I confirm that I want to restore this backup</label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-upload me-2"></i> Restore Backup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- Unknown Tab -->
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> Invalid settings tab.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Extra JavaScript for settings page -->
<?php
$extraJS = <<<EOT
<script>
    $(document).ready(function() {
        // Profile form submission
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            
            // Password validation
            var newPassword = $('#new_password').val();
            var confirmPassword = $('#confirm_password').val();
            
            if (newPassword && newPassword !== confirmPassword) {
                alert('New password and confirmation do not match!');
                return false;
            }
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: '/api/settings.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Profile updated successfully!');
                        // Reset password fields
                        $('#current_password, #new_password, #confirm_password').val('');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        });
        
        // Submit notification template forms
        $('#submitAddTemplate').click(function() {
            $('#addTemplateForm').submit();
        });
        
        $('#submitEditTemplate').click(function() {
            $('#editTemplateForm').submit();
        });
        
        $('#confirmDeleteTemplate').click(function() {
            $('#deleteTemplateForm').submit();
        });
        
        // Load template data for editing
        $(document).on('click', '.edit-template', function() {
            var id = $(this).data('id');
            var type = $(this).data('type');
            var subject = $(this).data('subject');
            var content = $(this).data('content');
            
            $('#edit_template_id').val(id);
            $('#edit_type').val(type);
            $('#edit_subject').val(subject);
            $('#edit_content').val(content);
        });
        
        // Set template ID for deletion
        $(document).on('click', '.delete-template', function() {
            var id = $(this).data('id');
            $('#delete_template_id').val(id);
        });
        
        // User management
        $('#submitAddUser').click(function() {
            // Validate password
            var password = $('#add_password').val();
            var confirmPassword = $('#add_confirm_password').val();
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            $('#addUserForm').submit();
        });
        
        $('#submitEditUser').click(function() {
            // Validate password if provided
            var password = $('#edit_password').val();
            var confirmPassword = $('#edit_confirm_password').val();
            
            if (password && password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            $('#editUserForm').submit();
        });
        
        $('#confirmDeleteUser').click(function() {
            $('#deleteUserForm').submit();
        });
        
        // Load user data for editing
        $(document).on('click', '.edit-user', function() {
            var id = $(this).data('id');
            var username = $(this).data('username');
            var firstname = $(this).data('firstname');
            var lastname = $(this).data('lastname');
            var email = $(this).data('email');
            var phone = $(this).data('phone');
            var role = $(this).data('role');
            var status = $(this).data('status');
            
            $('#edit_user_id').val(id);
            $('#edit_username').val(username);
            $('#edit_firstname').val(firstname);
            $('#edit_lastname').val(lastname);
            $('#edit_email').val(email);
            $('#edit_phone').val(phone);
            $('#edit_role').val(role);
            $('#edit_status').val(status);
            
            // Clear password fields
            $('#edit_password, #edit_confirm_password').val('');
        });
        
        // Set user data for deletion
        $(document).on('click', '.delete-user', function() {
            var id = $(this).data('id');
            var username = $(this).data('username');
            
            $('#delete_user_id').val(id);
            $('#delete_username').text(username);
        });
        
        // Tool Categories
        function loadCategories() {
            $.ajax({
                url: '/api/settings.php',
                type: 'GET',
                data: {
                    action: 'get_categories'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var html = '';
                        
                        if (response.categories.length === 0) {
                            html = '<div class="col-12"><div class="alert alert-info">No categories found. Add your first category.</div></div>';
                        } else {
                            response.categories.forEach(function(category) {
                                html += '<div class="col-md-6 col-lg-4 mb-3">' +
                                    '<div class="card h-100">' +
                                    '<div class="card-body">' +
                                    '<h5 class="card-title">' + category.name + '</h5>' +
                                    '<p class="card-text small">' + (category.description || 'No description') + '</p>' +
                                    '<div class="d-flex justify-content-between">' +
                                    '<span class="badge bg-primary">' + category.tool_count + ' tools</span>' +
                                    '<div>' +
                                    '<button class="btn btn-sm btn-outline-primary edit-category me-1" data-id="' + category.id + '" data-name="' + category.name + '" data-description="' + category.description + '">' +
                                    '<i class="fas fa-edit"></i>' +
                                    '</button>' +
                                    '<button class="btn btn-sm btn-outline-danger delete-category" data-id="' + category.id + '" data-name="' + category.name + '" data-count="' + category.tool_count + '">' +
                                    '<i class="fas fa-trash"></i>' +
                                    '</button>' +
                                    '</div>' +
                                    '</div>' +
                                    '</div>' +
                                    '</div>' +
                                    '</div>';
                            });
                        }
                        
                        $('#categoriesList').html(html);
                    } else {
                        $('#categoriesList').html('<div class="col-12"><div class="alert alert-danger">Error: ' + response.message + '</div></div>');
                    }
                },
                error: function() {
                    $('#categoriesList').html('<div class="col-12"><div class="alert alert-danger">Server error. Please try again.</div></div>');
                }
            });
        }
        
        // Load categories on page load if on categories tab
        if ('$tab' === 'categories') {
            loadCategories();
        }
        
        // Show/hide add category form
        $('#addCategoryBtn').click(function() {
            $('#addCategoryForm').toggle();
            $(this).toggleClass('btn-light btn-secondary');
            
            if ($(this).hasClass('btn-secondary')) {
                $(this).html('<i class="fas fa-times me-2"></i> Cancel');
            } else {
                $(this).html('<i class="fas fa-plus me-2"></i> Add Category');
            }
        });
        
        $('#cancelAddCategory').click(function() {
            $('#addCategoryForm').hide();
            $('#addCategoryBtn').removeClass('btn-secondary').addClass('btn-light').html('<i class="fas fa-plus me-2"></i> Add Category');
        });
        
        // Submit category form
        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: '/api/settings.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Category added successfully!');
                        // Reset form
                        $('#categoryForm')[0].reset();
                        // Hide form
                        $('#addCategoryForm').hide();
                        $('#addCategoryBtn').removeClass('btn-secondary').addClass('btn-light').html('<i class="fas fa-plus me-2"></i> Add Category');
                        // Reload categories
                        loadCategories();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        });
        
        // Handle category edit and delete
        $(document).on('click', '.edit-category', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var description = $(this).data('description');
            
            // Show edit form (could be implemented as modal or inline form)
            alert('Edit category feature will be implemented in a future update.');
        });
        
        $(document).on('click', '.delete-category', function() {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var count = $(this).data('count');
            
            if (count > 0) {
                alert('Cannot delete category "' + name + '" because it has ' + count + ' tools assigned to it.');
                return;
            }
            
            if (confirm('Are you sure you want to delete the category "' + name + '"?')) {
                $.ajax({
                    url: '/api/settings.php',
                    type: 'POST',
                    data: {
                        action: 'delete_category',
                        category_id: id,
                        csrf_token: '<?php echo getCSRFToken(); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Category deleted successfully!');
                            // Reload categories
                            loadCategories();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Server error. Please try again.');
                    }
                });
            }
        });
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>
