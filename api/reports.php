<?php
/**
 * Reports API
 * Handles report generation operations
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

// Require staff privileges
if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to access reports'
    ]);
    exit;
}

// Process request based on method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests
    $reportType = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : '';
    
    switch ($reportType) {
        case 'inventory':
            // Generate inventory report
            $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
            $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
            
            // Build query conditions
            $conditions = [];
            $params = [];
            $types = '';
            
            if (!empty($status)) {
                $conditions[] = "status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            if (!empty($category)) {
                $conditions[] = "category = ?";
                $params[] = $category;
                $types .= 's';
            }
            
            // Combine conditions
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get tools
            $query = "SELECT * FROM tools $whereClause ORDER BY name";
            $tools = $db->fetchAll($query, $types, $params);
            
            echo json_encode([
                'success' => true,
                'data' => $tools
            ]);
            break;
            
        case 'transactions':
            // Generate transactions report
            $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
            $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
            $userId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
            
            // Build query conditions
            $conditions = [];
            $params = [];
            $types = '';
            
            // Date range conditions
            $conditions[] = "(t.transaction_date BETWEEN ? AND ? OR t.return_date BETWEEN ? AND ?)";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
            $types .= 'ssss';
            
            if (!empty($status)) {
                if ($status == 'returned') {
                    $conditions[] = "t.return_date IS NOT NULL";
                } elseif ($status == 'borrowed') {
                    $conditions[] = "t.return_date IS NULL";
                } elseif ($status == 'overdue') {
                    $conditions[] = "t.return_date IS NULL AND t.expected_return_date < NOW()";
                }
            }
            
            if ($userId > 0) {
                $conditions[] = "t.user_id = ?";
                $params[] = $userId;
                $types .= 'i';
            }
            
            // Combine conditions
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get transactions
            $query = "SELECT t.*, tl.code as tool_code, tl.name as tool_name, 
                     u.username, CONCAT(u.first_name, ' ', u.last_name) AS borrower
                     FROM transactions t
                     JOIN tools tl ON t.tool_id = tl.id
                     JOIN users u ON t.user_id = u.id
                     $whereClause
                     ORDER BY t.transaction_date DESC";
            $transactions = $db->fetchAll($query, $types, $params);
            
            echo json_encode([
                'success' => true,
                'data' => $transactions
            ]);
            break;
            
        case 'overdue':
            // Generate overdue report
            $query = "SELECT t.*, tl.code as tool_code, tl.name as tool_name,
                     u.username, u.email, u.phone, CONCAT(u.first_name, ' ', u.last_name) AS borrower,
                     DATEDIFF(NOW(), t.expected_return_date) AS days_overdue
                     FROM transactions t
                     JOIN tools tl ON t.tool_id = tl.id
                     JOIN users u ON t.user_id = u.id
                     WHERE t.return_date IS NULL AND t.expected_return_date < NOW()
                     ORDER BY days_overdue DESC";
            $overdue = $db->fetchAll($query);
            
            echo json_encode([
                'success' => true,
                'data' => $overdue
            ]);
            break;
            
        case 'maintenance':
            // Generate maintenance report
            $query = "SELECT t.*, 
                     m.maintenance_date AS last_maintenance,
                     DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY) AS next_maintenance,
                     DATEDIFF(DATE_ADD(m.maintenance_date, INTERVAL t.maintenance_interval DAY), NOW()) AS days_until_due
                     FROM tools t
                     LEFT JOIN (
                         SELECT tool_id, MAX(maintenance_date) AS maintenance_date
                         FROM maintenance_logs
                         GROUP BY tool_id
                     ) AS m ON t.id = m.tool_id
                     WHERE t.maintenance_interval > 0
                     ORDER BY days_until_due ASC";
            $maintenance = $db->fetchAll($query);
            
            echo json_encode([
                'success' => true,
                'data' => $maintenance
            ]);
            break;
            
        case 'user_activity':
            // Generate user activity report
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
            
            // Build query
            $conditions = [];
            $params = [];
            $types = '';
            
            // Date range
            $conditions[] = "(t.transaction_date BETWEEN ? AND ? OR t.return_date BETWEEN ? AND ?)";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
            $types .= 'ssss';
            
            // Specific user if provided
            if ($userId > 0) {
                $conditions[] = "t.user_id = ?";
                $params[] = $userId;
                $types .= 'i';
            }
            
            // Combine conditions
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get user activity
            $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code,
                     u.username, CONCAT(u.first_name, ' ', u.last_name) AS borrower,
                     CASE 
                         WHEN t.return_date IS NULL AND t.expected_return_date < NOW() THEN 'overdue'
                         WHEN t.return_date IS NULL THEN 'borrowed'
                         ELSE 'returned'
                     END AS status
                     FROM transactions t
                     JOIN tools tl ON t.tool_id = tl.id
                     JOIN users u ON t.user_id = u.id
                     $whereClause
                     ORDER BY t.transaction_date DESC";
            $activity = $db->fetchAll($query, $types, $params);
            
            // Get activity summary
            $summaryQuery = "SELECT
                            u.id,
                            u.username,
                            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                            COUNT(t.id) AS total_transactions,
                            SUM(CASE WHEN t.return_date IS NULL THEN 1 ELSE 0 END) AS active_borrows,
                            SUM(CASE WHEN t.return_date IS NULL AND t.expected_return_date < NOW() THEN 1 ELSE 0 END) AS overdue_items,
                            AVG(CASE 
                                WHEN t.return_date IS NOT NULL 
                                THEN TIMESTAMPDIFF(HOUR, t.transaction_date, t.return_date) 
                                ELSE NULL 
                                END) / 24 AS avg_borrow_days
                            FROM users u
                            LEFT JOIN transactions t ON u.id = t.user_id
                            " . ($userId > 0 ? "WHERE u.id = $userId" : "") . "
                            GROUP BY u.id, u.username, full_name
                            ORDER BY total_transactions DESC";
            $summary = $db->fetchAll($summaryQuery);
            
            echo json_encode([
                'success' => true,
                'data' => $activity,
                'summary' => $summary
            ]);
            break;
            
        case 'tool_usage':
            // Generate tool usage report
            $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
            $startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-90 days'));
            $endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
            
            // Build query
            $conditions = ["t.transaction_date BETWEEN ? AND ?"];
            $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
            $types = 'ss';
            
            // Specific tool if provided
            if ($toolId > 0) {
                $conditions[] = "t.tool_id = ?";
                $params[] = $toolId;
                $types .= 'i';
            }
            
            // Combine conditions
            $whereClause = "WHERE " . implode(" AND ", $conditions);
            
            // Get tool usage
            $query = "SELECT 
                     tl.id, tl.name, tl.code, tl.category,
                     COUNT(t.id) AS borrow_count,
                     AVG(CASE 
                         WHEN t.return_date IS NOT NULL 
                         THEN TIMESTAMPDIFF(HOUR, t.transaction_date, t.return_date) 
                         ELSE NULL 
                         END) / 24 AS avg_borrow_days,
                     COUNT(CASE WHEN t.return_condition = 'damaged' THEN 1 ELSE NULL END) AS damage_reports,
                     COALESCE(MAX(m.maintenance_date), 'Never') AS last_maintenance
                     FROM tools tl
                     LEFT JOIN transactions t ON tl.id = t.tool_id $whereClause
                     LEFT JOIN (
                         SELECT tool_id, MAX(maintenance_date) AS maintenance_date
                         FROM maintenance_logs
                         GROUP BY tool_id
                     ) AS m ON tl.id = m.tool_id
                     " . ($toolId > 0 ? "WHERE tl.id = $toolId" : "") . "
                     GROUP BY tl.id, tl.name, tl.code, tl.category
                     ORDER BY borrow_count DESC";
            $toolUsage = $db->fetchAll($query, $types, $params);
            
            echo json_encode([
                'success' => true,
                'data' => $toolUsage
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
            break;
    }
} else {
    // Method not allowed
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET');
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

// Close database connection
$db->closeConnection();
?>
