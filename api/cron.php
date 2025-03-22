<?php
/**
 * Cron Job Endpoint
 * Handles automated tasks such as notifications and maintenance reminders
 * This script should be run periodically via cron job
 */

// Set max execution time to 5 minutes for large operations
ini_set('max_execution_time', 300);

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Security check: Only allow execution from CLI or with proper API key
$apiKey = getenv('CRON_API_KEY') ?: 'default_key';
$providedKey = isset($_GET['key']) ? $_GET['key'] : '';

if (php_sapi_name() !== 'cli' && $providedKey !== $apiKey) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Get action to perform
$action = isset($_GET['action']) ? $_GET['action'] : (isset($argv[1]) ? $argv[1] : 'all');

// Initialize counters for reporting
$stats = [
    'due_date_notifications' => 0,
    'overdue_notifications' => 0,
    'maintenance_notifications' => 0,
    'errors' => 0
];

// Get notification settings from database
$query = "SELECT setting_name, setting_value FROM settings WHERE setting_group = 'notifications'";
$settingsResult = $db->fetchAll($query);

$settings = [
    'due_date_reminder_days' => 1,
    'overdue_reminder_interval' => 24,
    'maintenance_reminder_days' => 7,
    'enable_email_notifications' => true
];

foreach ($settingsResult as $setting) {
    $settings[$setting['setting_name']] = $setting['setting_value'];
}

// Convert hours to seconds for interval
$overdueInterval = (int)$settings['overdue_reminder_interval'] * 3600;

// Process different types of notifications
if ($action === 'all' || $action === 'due_date') {
    // Due date reminders
    try {
        // Get tools that are due in X days
        $daysBeforeDue = (int)$settings['due_date_reminder_days'];
        $dueDateQuery = "SELECT t.id as transaction_id, t.user_id, t.expected_return_date, 
                         tl.id as tool_id, tl.name as tool_name, tl.code as tool_code,
                         u.first_name, u.email, u.email_notifications
                         FROM transactions t
                         JOIN tools tl ON t.tool_id = tl.id
                         JOIN users u ON t.user_id = u.id
                         WHERE t.return_date IS NULL 
                         AND DATE(t.expected_return_date) = DATE(DATE_ADD(NOW(), INTERVAL ? DAY))
                         AND NOT EXISTS (
                             SELECT 1 FROM notifications n 
                             WHERE n.type = 'due_date' 
                             AND n.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
                             AND n.message LIKE CONCAT('%', tl.name, '%', DATE_FORMAT(t.expected_return_date, '%Y-%m-%d'), '%')
                         )";
        $dueItems = $db->fetchAll($dueDateQuery, "i", [$daysBeforeDue]);
        
        foreach ($dueItems as $item) {
            // Create notification
            $title = "Tool Due Soon: " . $item['tool_name'];
            $message = "The tool " . $item['tool_name'] . " (" . $item['tool_code'] . ") is due for return ";
            
            if ($daysBeforeDue <= 0) {
                $message .= "today.";
            } else if ($daysBeforeDue == 1) {
                $message .= "tomorrow.";
            } else {
                $message .= "in " . $daysBeforeDue . " days.";
            }
            
            // Insert notification
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (?, 'due_date', ?, ?, NOW())";
            $notifResult = $db->insert($notifQuery, "iss", [$item['user_id'], $title, $message]);
            
            // Send email if enabled
            if ($settings['enable_email_notifications'] && $item['email_notifications'] == 1) {
                $formattedDate = formatDate($item['expected_return_date']);
                $emailBody = "
                    <html>
                    <head>
                        <title>$title</title>
                    </head>
                    <body>
                        <h2>$title</h2>
                        <p>Hello {$item['first_name']},</p>
                        <p>$message</p>
                        <p>Please return this tool by $formattedDate to avoid it becoming overdue.</p>
                        <p>Regards,<br>" . APP_NAME . " Team</p>
                    </body>
                    </html>
                ";
                
                sendEmail($item['email'], $title, $emailBody);
            }
            
            if ($notifResult) {
                $stats['due_date_notifications']++;
            }
        }
    } catch (Exception $e) {
        logEvent("Cron error: due date notifications", "error", $e->getMessage());
        $stats['errors']++;
    }
}

if ($action === 'all' || $action === 'overdue') {
    // Overdue reminders
    try {
        // Get tools that are overdue
        $overdueQuery = "SELECT t.id as transaction_id, t.user_id, t.expected_return_date, 
                         DATEDIFF(NOW(), t.expected_return_date) as days_overdue,
                         tl.id as tool_id, tl.name as tool_name, tl.code as tool_code,
                         u.first_name, u.email, u.email_notifications,
                         (SELECT MAX(created_at) FROM notifications 
                          WHERE user_id = t.user_id AND type = 'overdue' 
                          AND message LIKE CONCAT('%', tl.name, '%')) as last_notification
                         FROM transactions t
                         JOIN tools tl ON t.tool_id = tl.id
                         JOIN users u ON t.user_id = u.id
                         WHERE t.return_date IS NULL 
                         AND t.expected_return_date < NOW()";
        $overdueItems = $db->fetchAll($overdueQuery);
        
        foreach ($overdueItems as $item) {
            // Check if we should send a notification based on the interval
            $shouldNotify = true;
            if (!empty($item['last_notification'])) {
                $lastNotifTime = strtotime($item['last_notification']);
                $timeSince = time() - $lastNotifTime;
                
                if ($timeSince < $overdueInterval) {
                    $shouldNotify = false;
                }
            }
            
            if ($shouldNotify) {
                // Create notification
                $title = "Overdue Tool: " . $item['tool_name'];
                $message = "The tool " . $item['tool_name'] . " (" . $item['tool_code'] . ") is overdue by " . 
                           $item['days_overdue'] . " day" . ($item['days_overdue'] > 1 ? "s" : "") . ". " . 
                           "Please return it as soon as possible.";
                
                // Insert notification
                $notifQuery = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                              VALUES (?, 'overdue', ?, ?, NOW())";
                $notifResult = $db->insert($notifQuery, "iss", [$item['user_id'], $title, $message]);
                
                // Send email if enabled
                if ($settings['enable_email_notifications'] && $item['email_notifications'] == 1) {
                    $formattedDate = formatDate($item['expected_return_date']);
                    $emailBody = "
                        <html>
                        <head>
                            <title>$title</title>
                        </head>
                        <body>
                            <h2>$title</h2>
                            <p>Hello {$item['first_name']},</p>
                            <p>$message</p>
                            <p>This tool was due on $formattedDate. Please return it to the laboratory as soon as possible.</p>
                            <p>Regards,<br>" . APP_NAME . " Team</p>
                        </body>
                        </html>
                    ";
                    
                    sendEmail($item['email'], $title, $emailBody);
                }
                
                // Log to transactions
                $logQuery = "INSERT INTO transaction_logs (transaction_id, action, details, user_id, created_at) 
                            VALUES (?, 'reminder', ?, 0, NOW())";
                $logDetails = "Automated overdue reminder sent. Days overdue: " . $item['days_overdue'];
                $db->insert($logQuery, "is", [$item['transaction_id'], $logDetails]);
                
                if ($notifResult) {
                    $stats['overdue_notifications']++;
                }
            }
        }
    } catch (Exception $e) {
        logEvent("Cron error: overdue notifications", "error", $e->getMessage());
        $stats['errors']++;
    }
}

if ($action === 'all' || $action === 'maintenance') {
    // Maintenance reminders
    try {
        // Get tools due for maintenance
        $maintenanceDays = (int)$settings['maintenance_reminder_days'];
        $maintenanceQuery = "SELECT t.id, t.name, t.code, t.maintenance_interval,
                            m.maintenance_date,
                            DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY) as next_maintenance,
                            DATEDIFF(DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY), NOW()) as days_until_due
                            FROM tools t
                            JOIN (
                                SELECT tool_id, MAX(maintenance_date) as maintenance_date
                                FROM maintenance_logs
                                GROUP BY tool_id
                            ) as m ON t.id = m.tool_id
                            WHERE t.maintenance_interval > 0
                            AND t.status != 'maintenance'
                            AND DATEDIFF(DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY), NOW()) BETWEEN 0 AND ?
                            AND NOT EXISTS (
                                SELECT 1 FROM notifications 
                                WHERE type = 'maintenance' 
                                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                AND message LIKE CONCAT('%', t.name, '%', DATE_FORMAT(DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY), '%Y-%m-%d'), '%')
                            )";
        $maintenanceItems = $db->fetchAll($maintenanceQuery, "i", [$maintenanceDays]);
        
        foreach ($maintenanceItems as $item) {
            // Create notification for admins/staff
            $title = "Maintenance Due: " . $item['name'];
            $formattedDate = formatDate($item['next_maintenance']);
            $message = "The tool " . $item['name'] . " (" . $item['code'] . ") is due for maintenance ";
            
            if ($item['days_until_due'] <= 0) {
                $message .= "today.";
            } else if ($item['days_until_due'] == 1) {
                $message .= "tomorrow.";
            } else {
                $message .= "in " . $item['days_until_due'] . " days.";
            }
            
            $message .= " Next maintenance date: " . $formattedDate;
            
            // Insert notification (user_id 0 = system/admin notification)
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (0, 'maintenance', ?, ?, NOW())";
            $notifResult = $db->insert($notifQuery, "ss", [$title, $message]);
            
            // Send email to admins if needed - would require additional logic to get admin emails
            
            if ($notifResult) {
                $stats['maintenance_notifications']++;
            }
        }
        
        // Also check for tools that never had maintenance but have interval set
        $neverMaintainedQuery = "SELECT t.id, t.name, t.code, t.created_at, t.maintenance_interval
                                FROM tools t
                                WHERE t.maintenance_interval > 0
                                AND t.status != 'maintenance'
                                AND NOT EXISTS (
                                    SELECT 1 FROM maintenance_logs WHERE tool_id = t.id
                                )
                                AND DATEDIFF(NOW(), t.created_at) > ?
                                AND NOT EXISTS (
                                    SELECT 1 FROM notifications 
                                    WHERE type = 'maintenance' 
                                    AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    AND message LIKE CONCAT('%', t.name, '%never been maintained%')
                                )";
        $neverMaintainedItems = $db->fetchAll($neverMaintainedQuery, "i", [$maintenanceDays]);
        
        foreach ($neverMaintainedItems as $item) {
            // Create notification for admins/staff
            $title = "Maintenance Needed: " . $item['name'];
            $daysSinceCreation = round((time() - strtotime($item['created_at'])) / 86400);
            $message = "The tool " . $item['name'] . " (" . $item['code'] . ") has never been maintained and was added " . 
                       $daysSinceCreation . " days ago. The recommended maintenance interval is " . $item['maintenance_interval'] . " days.";
            
            // Insert notification
            $notifQuery = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (0, 'maintenance', ?, ?, NOW())";
            $notifResult = $db->insert($notifQuery, "ss", [$title, $message]);
            
            if ($notifResult) {
                $stats['maintenance_notifications']++;
            }
        }
    } catch (Exception $e) {
        logEvent("Cron error: maintenance notifications", "error", $e->getMessage());
        $stats['errors']++;
    }
}

// If running from CLI, output stats
if (php_sapi_name() === 'cli') {
    echo "Cron job completed.\n";
    echo "Due date notifications: " . $stats['due_date_notifications'] . "\n";
    echo "Overdue notifications: " . $stats['overdue_notifications'] . "\n";
    echo "Maintenance notifications: " . $stats['maintenance_notifications'] . "\n";
    echo "Errors: " . $stats['errors'] . "\n";
} else {
    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => 'Cron job completed',
        'stats' => $stats
    ]);
}

// Log completion
logEvent("Cron job completed", "info", json_encode($stats));

// Close database connection
$db->closeConnection();
?>
