<?php
/**
 * Notifications API
 * Handles notification operations
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
        case 'list':
            // Get user notifications with pagination
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
            $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
            
            // Build query conditions
            $conditions = ["(user_id = ? OR user_id = 0)"];
            $params = [$_SESSION['user_id']];
            $types = "i";
            
            if (!empty($type)) {
                $conditions[] = "type = ?";
                $params[] = $type;
                $types .= "s";
            }
            
            if ($unreadOnly) {
                $conditions[] = "is_read = 0";
            }
            
            // Combine conditions
            $whereClause = implode(" AND ", $conditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE $whereClause";
            $countResult = $db->fetchSingle($countQuery, $types, $params);
            $total = $countResult ? $countResult['total'] : 0;
            
            // Add limit and offset params
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            // Get notifications
            $query = "SELECT * FROM notifications WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $notifications = $db->fetchAll($query, $types, $params);
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'notifications' => $notifications,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'count':
            // Get unread notification count
            $query = "SELECT COUNT(*) as count FROM notifications 
                      WHERE (user_id = ? OR user_id = 0) AND is_read = 0";
            $result = $db->fetchSingle($query, "i", [$_SESSION['user_id']]);
            $count = $result ? $result['count'] : 0;
            
            echo json_encode([
                'success' => true,
                'count' => $count
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
        case 'mark_read':
            // Mark notification as read
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            
            if ($notificationId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification ID'
                ]);
                break;
            }
            
            // Check if notification belongs to current user or is a system notification
            $query = "SELECT * FROM notifications WHERE id = ? AND (user_id = ? OR user_id = 0)";
            $notification = $db->fetchSingle($query, "ii", [$notificationId, $_SESSION['user_id']]);
            
            if (!$notification) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Notification not found or access denied'
                ]);
                break;
            }
            
            // Update notification
            $query = "UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "i", [$notificationId]);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification marked as read'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to mark notification as read'
                ]);
            }
            break;
            
        case 'mark_read_batch':
            // Mark multiple notifications as read
            $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : '';
            
            if (empty($notificationIds)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No notification IDs provided'
                ]);
                break;
            }
            
            // Convert comma-separated IDs to array
            $ids = array_map('intval', explode(',', $notificationIds));
            
            if (empty($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification IDs'
                ]);
                break;
            }
            
            // Create placeholders for query
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            
            // Only allow updating notifications that belong to current user or are system notifications
            $query = "UPDATE notifications SET is_read = 1, updated_at = NOW() 
                      WHERE id IN ($placeholders) AND (user_id = ? OR user_id = 0)";
            $params = array_merge($ids, [$_SESSION['user_id']]);
            $types .= 'i';
            
            $result = $db->update($query, $types, $params);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notifications marked as read',
                    'count' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to mark notifications as read'
                ]);
            }
            break;
            
        case 'mark_all_read':
            // Mark all user's notifications as read
            $query = "UPDATE notifications SET is_read = 1, updated_at = NOW() 
                      WHERE (user_id = ? OR user_id = 0) AND is_read = 0";
            $result = $db->update($query, "i", [$_SESSION['user_id']]);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'All notifications marked as read',
                    'count' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to mark notifications as read'
                ]);
            }
            break;
            
        case 'delete':
            // Delete notification
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            
            if ($notificationId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification ID'
                ]);
                break;
            }
            
            // Check if notification belongs to current user or user is admin
            if (!isAdmin()) {
                $query = "SELECT * FROM notifications WHERE id = ? AND user_id = ?";
                $notification = $db->fetchSingle($query, "ii", [$notificationId, $_SESSION['user_id']]);
                
                if (!$notification) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Notification not found or access denied'
                    ]);
                    break;
                }
            }
            
            // Delete notification
            $query = "DELETE FROM notifications WHERE id = ?";
            $result = $db->delete($query, "i", [$notificationId]);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification deleted'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete notification'
                ]);
            }
            break;
            
        case 'create':
            // Create new notification (admin only)
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to create notifications'
                ]);
                break;
            }
            
            $title = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
            $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
            $type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : 'system';
            $target = isset($_POST['target']) ? sanitizeInput($_POST['target']) : 'all';
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === 'on';
            
            // Validate inputs
            if (empty($title) || empty($message)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Title and message are required'
                ]);
                break;
            }
            
            if (!in_array($type, ['system', 'alert', 'maintenance', 'due_date', 'overdue'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification type'
                ]);
                break;
            }
            
            // Determine target user ID(s)
            $targetUserIds = [];
            
            if ($target === 'specific' && $userId > 0) {
                // Specific user
                $targetUserIds = [$userId];
            } else if ($target === 'admins' || $target === 'staff') {
                // Get all admin or staff user IDs
                $roleCondition = ($target === 'admins') ? "role = 'admin'" : "role IN ('admin', 'lab_tech')";
                $query = "SELECT id FROM users WHERE $roleCondition AND status = 'active'";
                $users = $db->fetchAll($query);
                
                foreach ($users as $user) {
                    $targetUserIds[] = $user['id'];
                }
            } else {
                // All users (system notification)
                $targetUserIds = [0]; // User ID 0 means system-wide notification
            }
            
            // Create notification(s)
            $successCount = 0;
            foreach ($targetUserIds as $targetUserId) {
                $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
                $result = $db->insert($query, "isss", [$targetUserId, $type, $title, $message]);
                
                if ($result) {
                    $successCount++;
                    
                    // Send email if requested
                    if ($sendEmail && $targetUserId > 0) {
                        // Get user email
                        $query = "SELECT email, first_name, email_notifications FROM users WHERE id = ?";
                        $user = $db->fetchSingle($query, "i", [$targetUserId]);
                        
                        if ($user && $user['email_notifications'] == 1) {
                            $emailBody = "
                                <html>
                                <head>
                                    <title>$title</title>
                                </head>
                                <body>
                                    <h2>$title</h2>
                                    <p>Hello {$user['first_name']},</p>
                                    <p>$message</p>
                                    <p>Login to your account to see more details.</p>
                                    <p>Regards,<br>" . APP_NAME . " Team</p>
                                </body>
                                </html>
                            ";
                            
                            sendEmail($user['email'], $title, $emailBody);
                        }
                    }
                }
            }
            
            if ($successCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification(s) created successfully',
                    'count' => $successCount
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create notification'
                ]);
            }
            break;
            
        case 'send_reminder':
            // Send reminder for overdue tool (staff only)
            if (!isStaff()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to send reminders'
                ]);
                break;
            }
            
            $transactionId = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
            
            if ($transactionId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid transaction ID'
                ]);
                break;
            }
            
            // Get transaction details with user info
            $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code, 
                      u.id as user_id, u.first_name, u.email, u.email_notifications
                      FROM transactions t
                      JOIN tools tl ON t.tool_id = tl.id
                      JOIN users u ON t.user_id = u.id
                      WHERE t.id = ? AND t.return_date IS NULL AND t.expected_return_date < NOW()";
            $transaction = $db->fetchSingle($query, "i", [$transactionId]);
            
            if (!$transaction) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction not found or not overdue'
                ]);
                break;
            }
            
            // Create notification
            $title = "Overdue Tool Reminder: " . $transaction['tool_name'];
            $message = "Please return " . $transaction['tool_name'] . " (" . $transaction['tool_code'] . ") as soon as possible. It was due on " . formatDate($transaction['expected_return_date']) . ".";
            
            $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                      VALUES (?, 'overdue', ?, ?, NOW())";
            $notificationResult = $db->insert($query, "iss", [$transaction['user_id'], $title, $message]);
            
            // Send email if user has email notifications enabled
            $emailSent = false;
            if ($transaction['email_notifications'] == 1) {
                $emailBody = "
                    <html>
                    <head>
                        <title>$title</title>
                    </head>
                    <body>
                        <h2>$title</h2>
                        <p>Hello {$transaction['first_name']},</p>
                        <p>$message</p>
                        <p>Please return this tool to the laboratory as soon as possible to avoid further penalties.</p>
                        <p>Regards,<br>" . APP_NAME . " Team</p>
                    </body>
                    </html>
                ";
                
                $emailSent = sendEmail($transaction['email'], $title, $emailBody);
            }
            
            // Log reminder
            $query = "INSERT INTO transaction_logs (transaction_id, action, details, user_id, created_at) 
                      VALUES (?, 'reminder', ?, ?, NOW())";
            $details = "Overdue reminder sent" . ($emailSent ? " with email" : "");
            $db->insert($query, "isi", [$transactionId, $details, $_SESSION['user_id']]);
            
            if ($notificationResult) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reminder sent successfully',
                    'email_sent' => $emailSent
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to send reminder'
                ]);
            }
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
