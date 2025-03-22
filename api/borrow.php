<?php
/**
 * Borrow API
 * Handles tool borrowing operations
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
        case 'recent':
            // Get user's recent borrowings
            $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code,
                      DATEDIFF(t.expected_return_date, NOW()) as days_remaining
                      FROM transactions t
                      JOIN tools tl ON t.tool_id = tl.id
                      WHERE t.user_id = ? AND t.return_date IS NULL
                      ORDER BY t.expected_return_date ASC
                      LIMIT 5";
            $borrowings = $db->fetchAll($query, "i", [$_SESSION['user_id']]);
            
            echo json_encode([
                'success' => true,
                'borrowings' => $borrowings
            ]);
            break;
            
        case 'check':
            // Check if a tool is available for borrowing
            $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Check if tool exists and is available
            $query = "SELECT * FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            if ($tool['status'] != 'available') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool is not available for borrowing',
                    'status' => $tool['status']
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tool is available for borrowing',
                'tool' => $tool
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
        case 'borrow':
            // Process tool borrowing
            $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
            $purpose = isset($_POST['purpose']) ? sanitizeInput($_POST['purpose']) : '';
            $expectedReturnDate = isset($_POST['expected_return_date']) ? sanitizeInput($_POST['expected_return_date']) : '';
            $agreeTerms = isset($_POST['agree_terms']) && $_POST['agree_terms'] === 'on';
            
            // Validate inputs
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            if (empty($purpose)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Purpose is required'
                ]);
                break;
            }
            
            if (!isValidDate($expectedReturnDate)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid return date'
                ]);
                break;
            }
            
            // Check if return date is in the future
            $returnDateTime = new DateTime($expectedReturnDate);
            $now = new DateTime();
            
            if ($returnDateTime <= $now) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Return date must be in the future'
                ]);
                break;
            }
            
            // Get system settings for max borrow days
            $query = "SELECT setting_value FROM settings WHERE setting_name = 'max_borrow_days' AND setting_group = 'general'";
            $maxBorrowDays = $db->fetchSingle($query);
            $maxDays = ($maxBorrowDays && isset($maxBorrowDays['setting_value'])) ? (int)$maxBorrowDays['setting_value'] : 14;
            
            // Check if return date is within allowed limits
            $maxReturnDate = (new DateTime())->modify("+$maxDays days");
            
            if ($returnDateTime > $maxReturnDate) {
                echo json_encode([
                    'success' => false,
                    'message' => "Return date cannot be more than $maxDays days in the future"
                ]);
                break;
            }
            
            if (!$agreeTerms) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You must agree to the terms'
                ]);
                break;
            }
            
            // Check if tool exists and is available
            $query = "SELECT * FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            if ($tool['status'] != 'available') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool is not available for borrowing',
                    'status' => $tool['status']
                ]);
                break;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert transaction record
                $query = "INSERT INTO transactions (tool_id, user_id, purpose, transaction_date, expected_return_date, created_at) 
                          VALUES (?, ?, ?, NOW(), ?, NOW())";
                $transactionId = $db->insert($query, "iiss", [$toolId, $_SESSION['user_id'], $purpose, $expectedReturnDate]);
                
                // Update tool status
                $query = "UPDATE tools SET status = 'borrowed', updated_at = NOW() WHERE id = ?";
                $db->update($query, "i", [$toolId]);
                
                // Generate QR code for transaction
                $qrData = "Transaction ID: " . $transactionId;
                $filename = 'transaction_' . $transactionId;
                QRCodeGenerator::generateQRCode($qrData, $filename);
                
                // Get QR code URL
                $qrUrl = QRCodeGenerator::getTransactionQRUrl($transactionId);
                
                // Commit transaction
                $conn->commit();
                
                // Create notification
                $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (?, 'system', ?, ?, NOW())";
                $notificationTitle = "Tool Borrowed: " . $tool['name'];
                $notificationMessage = "You have borrowed " . $tool['name'] . " (" . $tool['code'] . "). Expected return date: " . formatDate($expectedReturnDate);
                $db->insert($query, "iss", [$_SESSION['user_id'], $notificationTitle, $notificationMessage]);
                
                // Log event
                logEvent("Tool borrowed", "info", "Tool ID: $toolId, Transaction ID: $transactionId, User ID: " . $_SESSION['user_id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool borrowed successfully',
                    'transaction_id' => $transactionId,
                    'tool_name' => $tool['name'],
                    'qr_url' => $qrUrl
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to borrow tool: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'extend':
            // Process borrowing extension
            $transactionId = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
            $newReturnDate = isset($_POST['new_return_date']) ? sanitizeInput($_POST['new_return_date']) : '';
            $reason = isset($_POST['reason']) ? sanitizeInput($_POST['reason']) : '';
            
            // Validate inputs
            if ($transactionId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid transaction ID'
                ]);
                break;
            }
            
            if (!isValidDate($newReturnDate)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid return date'
                ]);
                break;
            }
            
            if (empty($reason)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Reason for extension is required'
                ]);
                break;
            }
            
            // Check if return date is in the future
            $returnDateTime = new DateTime($newReturnDate);
            $now = new DateTime();
            
            if ($returnDateTime <= $now) {
                echo json_encode([
                    'success' => false,
                    'message' => 'New return date must be in the future'
                ]);
                break;
            }
            
            // Check if transaction exists and belongs to current user
            $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code 
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
            
            // Check if new date is after current expected return date
            $currentReturnDate = new DateTime($transaction['expected_return_date']);
            
            if ($returnDateTime <= $currentReturnDate) {
                echo json_encode([
                    'success' => false,
                    'message' => 'New return date must be after the current expected return date'
                ]);
                break;
            }
            
            // Get system settings for max extension days
            $query = "SELECT setting_value FROM settings WHERE setting_name = 'max_extension_days' AND setting_group = 'general'";
            $maxExtensionDays = $db->fetchSingle($query);
            $maxDays = ($maxExtensionDays && isset($maxExtensionDays['setting_value'])) ? (int)$maxExtensionDays['setting_value'] : 7;
            
            // Calculate maximum allowed extension date
            $maxExtensionDate = clone $currentReturnDate;
            $maxExtensionDate->modify("+$maxDays days");
            
            if ($returnDateTime > $maxExtensionDate) {
                echo json_encode([
                    'success' => false,
                    'message' => "Extension cannot be more than $maxDays days beyond the current return date"
                ]);
                break;
            }
            
            // Update transaction
            $query = "UPDATE transactions SET expected_return_date = ?, extension_reason = ?, updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "ssi", [$newReturnDate, $reason, $transactionId]);
            
            if ($result !== false) {
                // Log extension
                $query = "INSERT INTO transaction_logs (transaction_id, action, details, user_id, created_at) 
                          VALUES (?, 'extension', ?, ?, NOW())";
                $details = "Extended from " . $transaction['expected_return_date'] . " to " . $newReturnDate . ". Reason: " . $reason;
                $db->insert($query, "isi", [$transactionId, $details, $_SESSION['user_id']]);
                
                // Create notification
                $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                          VALUES (?, 'system', ?, ?, NOW())";
                $notificationTitle = "Borrowing Extended: " . $transaction['tool_name'];
                $notificationMessage = "Your borrowing of " . $transaction['tool_name'] . " has been extended to " . formatDate($newReturnDate);
                $db->insert($query, "iss", [$_SESSION['user_id'], $notificationTitle, $notificationMessage]);
                
                // Log event
                logEvent("Borrowing extended", "info", "Transaction ID: $transactionId, New date: $newReturnDate");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Borrowing extended successfully',
                    'new_return_date' => formatDate($newReturnDate)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to extend borrowing'
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
