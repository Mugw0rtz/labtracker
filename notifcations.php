<?php
/**
 * Notifications Page
 * Manages and displays system notifications
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Require login
requireLogin();

// Check if admin or staff privileges required
requireStaff();

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Get notification type filter
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

// Process actions
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action && $notificationId) {
    switch ($action) {
        case 'mark_read':
            // Mark notification as read
            $query = "UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ?";
            $db->update($query, "i", [$notificationId]);
            break;
            
        case 'delete':
            // Delete notification
            $query = "DELETE FROM notifications WHERE id = ?";
            $db->delete($query, "i", [$notificationId]);
            break;
    }
    
    // Redirect to remove action from URL
    header("Location: notifications.php" . ($type ? "?type=$type" : ""));
    exit;
}

// Mark all as read
if ($action == 'mark_all_read') {
    $query = "UPDATE notifications SET is_read = 1, updated_at = NOW() 
              WHERE (user_id = ? OR user_id = 0) AND is_read = 0";
    $db->update($query, "i", [$_SESSION['user_id']]);
    
    // Redirect to remove action from URL
    header("Location: notifications.php" . ($type ? "?type=$type" : ""));
    exit;
}

// Build query conditions
$conditions = ["(user_id = ? OR user_id = 0)"];
$params = [$_SESSION['user_id']];
$types = "i";

if (!empty($type)) {
    $conditions[] = "type = ?";
    $params[] = $type;
    $types .= "s";
}

// Combine conditions
$whereClause = implode(" AND ", $conditions);

// Get notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM notifications WHERE $whereClause";
$countResult = $db->fetchSingle($countQuery, $types, $params);
$totalNotifications = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalNotifications / $limit);

// Get notifications
$query = "SELECT * FROM notifications WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$limitTypes = $types . "ii";
$limitParams = array_merge($params, [$limit, $offset]);
$notifications = $db->fetchAll($query, $limitTypes, $limitParams);

// Get unread count
$unreadQuery = "SELECT COUNT(*) as count FROM notifications 
                WHERE (user_id = ? OR user_id = 0) AND is_read = 0";
$unreadResult = $db->fetchSingle($unreadQuery, "i", [$_SESSION['user_id']]);
$unreadCount = $unreadResult ? $unreadResult['count'] : 0;

// Close database connection
$db->closeConnection();

$pageTitle = 'Notifications';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Notifications</h1>
        <div>
            <?php if ($unreadCount > 0): ?>
                <a href="notifications.php?action=mark_all_read<?php echo $type ? "&type=$type" : ""; ?>" class="btn btn-outline-primary me-2">
                    <i class="fas fa-check-double me-2"></i> Mark All as Read
                </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNotificationModal">
                    <i class="fas fa-plus me-2"></i> New Notification
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="list-group shadow-sm">
                <a href="notifications.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo empty($type) ? 'active' : ''; ?>">
                    <div>
                        <i class="fas fa-bell me-2"></i> All Notifications
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-primary rounded-pill"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="notifications.php?type=system" class="list-group-item list-group-item-action <?php echo $type == 'system' ? 'active' : ''; ?>">
                    <i class="fas fa-cog me-2"></i> System
                </a>
                <a href="notifications.php?type=due_date" class="list-group-item list-group-item-action <?php echo $type == 'due_date' ? 'active' : ''; ?>">
                    <i class="fas fa-clock me-2"></i> Due Date Reminders
                </a>
                <a href="notifications.php?type=overdue" class="list-group-item list-group-item-action <?php echo $type == 'overdue' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle me-2"></i> Overdue
                </a>
                <a href="notifications.php?type=maintenance" class="list-group-item list-group-item-action <?php echo $type == 'maintenance' ? 'active' : ''; ?>">
                    <i class="fas fa-wrench me-2"></i> Maintenance
                </a>
                <?php if (isAdmin()): ?>
                    <a href="notifications.php?type=alert" class="list-group-item list-group-item-action <?php echo $type == 'alert' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn me-2"></i> Alerts
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (isAdmin()): ?>
                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fs-6">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text small">Configure how and when notifications are sent to users.</p>
                        <a href="settings.php?tab=notifications" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-cog me-2"></i> Configure
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fs-6">
                        <?php 
                        if ($type) {
                            echo ucfirst($type) . " Notifications";
                        } else {
                            echo "All Notifications";
                        }
                        ?>
                        <?php if ($totalNotifications > 0): ?>
                            <span class="badge bg-secondary ms-2"><?php echo $totalNotifications; ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-bell-slash fa-3x text-secondary mb-3"></i>
                            <p>No notifications found.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                // Set icon based on notification type
                                $icon = 'fas fa-bell';
                                $bgClass = '';
                                
                                if ($notification['type'] == 'system') {
                                    $icon = 'fas fa-cog';
                                } elseif ($notification['type'] == 'due_date') {
                                    $icon = 'fas fa-clock';
                                    $bgClass = 'list-group-item-warning';
                                } elseif ($notification['type'] == 'overdue') {
                                    $icon = 'fas fa-exclamation-triangle';
                                    $bgClass = 'list-group-item-danger';
                                } elseif ($notification['type'] == 'maintenance') {
                                    $icon = 'fas fa-wrench';
                                    $bgClass = 'list-group-item-info';
                                } elseif ($notification['type'] == 'alert') {
                                    $icon = 'fas fa-bullhorn';
                                    $bgClass = 'list-group-item-primary';
                                }
                                
                                // Add unread class if notification is unread
                                if (!$notification['is_read']) {
                                    $bgClass .= ' list-group-item-unread';
                                }
                                ?>
                                
                                <div class="list-group-item notification-item <?php echo $bgClass; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="notification-icon">
                                                <i class="<?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="mb-1"><?php echo $notification['title']; ?></h6>
                                                <p class="mb-1"><?php echo $notification['message']; ?></p>
                                                <small class="text-muted">
                                                    <?php echo formatDateTime($notification['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <a href="notifications.php?action=mark_read&id=<?php echo $notification['id']; ?><?php echo $type ? "&type=$type" : ""; ?>" class="btn btn-sm btn-outline-primary me-1" title="Mark as Read">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (isAdmin() || $notification['user_id'] == $_SESSION['user_id']): ?>
                                                <a href="notifications.php?action=delete&id=<?php echo $notification['id']; ?><?php echo $type ? "&type=$type" : ""; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-container p-3">
                                <nav aria-label="Notification pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $type ? "&type=$type" : ""; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $type ? "&type=$type" : ""; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $type ? "&type=$type" : ""; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Create Notification Modal -->
<div class="modal fade" id="createNotificationModal" tabindex="-1" aria-labelledby="createNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createNotificationModalLabel">Create New Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="notificationForm" method="post" action="/api/notifications.php">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="notification_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="notification_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notification_message" class="form-label">Message</label>
                        <textarea class="form-control" id="notification_message" name="message" rows="3" required></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="notification_type" class="form-label">Type</label>
                            <select class="form-select" id="notification_type" name="type" required>
                                <option value="system">System</option>
                                <option value="alert">Alert</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="due_date">Due Date</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="notification_target" class="form-label">Send To</label>
                            <select class="form-select" id="notification_target" name="target" required>
                                <option value="all">All Users</option>
                                <option value="admins">Administrators Only</option>
                                <option value="staff">Staff Only</option>
                                <option value="specific">Specific User</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="specificUserField" style="display: none;">
                        <label for="user_id" class="form-label">Select User</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">-- Select a user --</option>
                            <?php
                            // Connect to database again to get users list
                            $db = new Database();
                            $conn = $db->getConnection();
                            $users = $db->fetchAll("SELECT id, username, email FROM users ORDER BY username");
                            $db->closeConnection();
                            
                            foreach ($users as $user): 
                            ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo $user['username'] . ' (' . $user['email'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="send_email" name="send_email">
                        <label class="form-check-label" for="send_email">Also send as email</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitNotification">Send Notification</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Extra JavaScript for notifications page -->
<?php 
$extraJS = <<<EOT
<script>
    $(document).ready(function() {
        // Toggle specific user field based on selection
        $('#notification_target').change(function() {
            if ($(this).val() === 'specific') {
                $('#specificUserField').show();
                $('#user_id').prop('required', true);
            } else {
                $('#specificUserField').hide();
                $('#user_id').prop('required', false);
            }
        });
        
        // Submit notification form
        $('#submitNotification').click(function() {
            $('#notificationForm').submit();
        });
        
        // Submit handler for notification form
        $('#notificationForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: '/api/notifications.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Notification sent successfully!');
                        $('#createNotificationModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        });
        
        // Mark notifications as read when viewing
        setTimeout(function() {
            var unreadNotifications = $('.notification-item.list-group-item-unread');
            if (unreadNotifications.length > 0) {
                var notificationIds = [];
                
                unreadNotifications.each(function() {
                    var href = $(this).find('.notification-actions a:first').attr('href');
                    if (href) {
                        var idMatch = href.match(/id=(\d+)/);
                        if (idMatch && idMatch[1]) {
                            notificationIds.push(idMatch[1]);
                        }
                    }
                });
                
                if (notificationIds.length > 0) {
                    $.ajax({
                        url: '/api/notifications.php',
                        type: 'POST',
                        data: {
                            action: 'mark_read_batch',
                            notification_ids: notificationIds.join(','),
                            csrf_token: '<?php echo getCSRFToken(); ?>'
                        },
                        dataType: 'json'
                    });
                }
            }
        }, 5000);
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>