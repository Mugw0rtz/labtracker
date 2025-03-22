<?php
/**
 * Return API
 * Handles tool return operations
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/phpqrcode.php';

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
        case 'history':
            // Get user's return history
            $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code
                      FROM transactions t
                      JOIN tools tl ON t.tool_id = tl.id
                      WHERE t.user_id = ? AND t.return_date IS NOT NULL
                      ORDER BY t.return_date DESC
                      LIMIT 10";
            $history = $db->fetchAll($query, "i", [$_SESSION['user_id']]);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;
            
        case 'check_tool':
            // Check if user has borrowed a specific tool
            $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Get tool details
            $query = "SELECT name FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            // Check if user has an active borrowing for this tool
            $query = "SELECT id FROM transactions 
                      WHERE tool_id = ? AND user_id = ? AND return_date IS NULL";
            $transaction = $db->fetchSingle($query, "ii", [$toolId, $_SESSION['user_id']]);
            
            if ($transaction) {
                echo json_encode([
                    'success' => true,
                    'message' => 'You have borrowed this tool',
                    'transaction_id' => $transaction['id'],
                    'tool_name' => $tool['name']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'You have not borrowed this tool',
                    'transaction_id' => null,
                    'tool_name' => $tool['name']
                ]);
            }
            break;
            
        case 'check_transaction':
            // Verify transaction belongs to current user
            $transactionId = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;
            
            if ($transactionId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid transaction ID'
                ]);
                break;
            }
            
            // Get transaction details
            $query = "SELECT t.*, tl.name as tool_name 
                      FROM transactions t
                      JOIN tools tl ON t.tool_id = tl.id
                      WHERE t.id = ?";
            $transaction = $db->fetchSingle($query, "i", [$transactionId]);
            
            if (!$transaction) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction not found'
                ]);
                break;
            }
            
            if ($transaction['user_id'] != $_SESSION['user_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This transaction does not belong to you'
                ]);
                break;
            }
            
            if ($transaction['return_date'] !== null) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This tool has already been returned'
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Transaction verified',
                'tool_name' => $transaction['tool_name']
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
        case 'return':
            // Process tool return
            $transactionId = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
            $condition = isset($_POST['condition']) ? sanitizeInput($_POST['condition']) : '';
            $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
            
            // Validate inputs
            if ($transactionId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid transaction ID'
                ]);
                break;
            }
            
            if (empty($condition) || !in_array($condition, ['good', 'fair', 'poor', 'damaged'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Valid condition is required'
                ]);
                break;
            }
            
            // Check if transaction exists and belongs to current user
            $query = "SELECT t.*, tl.id as tool_id, tl.name as tool_name, tl.code as tool_code 
                      FROM transactions t
                      JOIN tools tl ON t.tool_id = tl.id
                      WHERE t.id = ? AND t.user_id = ? AND t.return_date IS NULL";
            $transaction = $db->fetchSingle($query, "ii", [$transactionId, $_SESSION['user_id']]);
            
            if (!$transaction) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction not found or not active'
                ]);
                break;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update transaction record
                $query = "UPDATE transactions 
                          SET return_date = NOW(), return_condition = ?, notes = ?, updated_at = NOW() 
                          WHERE id = ?";
                $db->update($query, "ssi", [$condition, $notes, $transactionId]);
                
                // Update tool status based on condition
                $newStatus = 'available';
                if ($condition === 'damaged' || $condition === 'poor') {
                    $newStatus = 'maintenance';
                    
                    // Create maintenance record
                    $query = "INSERT INTO maintenance_logs (tool_id, scheduled_date, status, notes, created_by, created_at) 
                              VALUES (?, NOW(), 'scheduled', ?, ?, NOW())";
                    $maintenanceNotes = "Maintenance required after return. Return condition: $condition. Notes: $notes";
                    $db->insert($query, "isi", [$transaction['tool_id'], $maintenanceNotes, $_SESSION['user_id']]);
                }
                
                $query = "UPDATE tools SET status = ?, updated_at = NOW() WHERE id = ?";
                $db->update($query, "si", [$newStatus, $transaction['tool_id']]);
                
                // Add to transaction log
                $query = "INSERT INTO transaction_logs (transaction_id, action, details, user_id, created_at) 
                          VALUES (?, 'return', ?, ?, NOW())";
                $details = "Tool returned in $condition condition. " . ($notes ? "Notes: $notes" : "");
                $db->insert($query, "isi", [$transactionId, $details, $_SESSION['user_id']]);
                
                // Commit transaction
                $conn->commit();
                
                // Create notification
                $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (?, 'system', ?, ?, NOW())";
                $notificationTitle = "Tool Returned: " . $transaction['tool_name'];
                $notificationMessage = "You have returned " . $transaction['tool_name'] . " (" . $transaction['tool_code'] . ") in $condition condition.";
                $db->insert($query, "iss", [$_SESSION['user_id'], $notificationTitle, $notificationMessage]);
                
                // Create notification for admin if tool needs maintenance
                if ($newStatus === 'maintenance') {
                    $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                              VALUES (0, 'maintenance', ?, ?, NOW())";
                    $adminTitle = "Tool Requires Maintenance: " . $transaction['tool_name'];
                    $adminMessage = "Tool " . $transaction['tool_name'] . " (" . $transaction['tool_code'] . ") has been returned in $condition condition and requires maintenance.";
                    $db->insert($query, "ss", [$adminTitle, $adminMessage]);
                }
                
                // Log event
                logEvent("Tool returned", "info", "Tool ID: {$transaction['tool_id']}, Transaction ID: $transactionId, Condition: $condition");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool returned successfully',
                    'tool_name' => $transaction['tool_name'],
                    'condition' => $condition,
                    'needs_maintenance' => ($newStatus === 'maintenance')
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to return tool: ' . $e->getMessage()
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
