<?php
/**
 * Tools API
 * Handles tool management operations
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
        case 'get':
            // Get a specific tool by ID
            $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            $query = "SELECT * FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'tool' => $tool
                ]);
            }
            break;
            
        case 'list':
            // Get a list of tools with optional filters
            $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
            $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
            $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
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
            
            if (!empty($search)) {
                $conditions[] = "(name LIKE ? OR code LIKE ? OR description LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'sss';
            }
            
            // Combine conditions
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Add limit and offset
            $limitTypes = $types . 'ii';
            $limitParams = array_merge($params, [$limit, $offset]);
            
            // Get tools count
            $countQuery = "SELECT COUNT(*) as total FROM tools $whereClause";
            $countResult = $db->fetchSingle($countQuery, $types, $params);
            $total = $countResult ? $countResult['total'] : 0;
            
            // Get tools list
            $query = "SELECT * FROM tools $whereClause ORDER BY name LIMIT ? OFFSET ?";
            $tools = $db->fetchAll($query, $limitTypes, $limitParams);
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'tools' => $tools,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'get_categories':
            // Get list of tool categories
            $query = "SELECT DISTINCT category FROM tools ORDER BY category";
            $categories = $db->fetchAll($query);
            
            $categoryList = [];
            foreach ($categories as $cat) {
                $categoryList[] = $cat['category'];
            }
            
            echo json_encode([
                'success' => true,
                'categories' => $categoryList
            ]);
            break;
            
        case 'get_qr':
            // Get QR code for a tool
            $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Check if tool exists
            $query = "SELECT id, name FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            // Get QR code URL
            $qrUrl = QRCodeGenerator::getToolQRUrl($toolId);
            
            echo json_encode([
                'success' => true,
                'tool_id' => $toolId,
                'tool_name' => $tool['name'],
                'qr_url' => $qrUrl
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
        case 'add':
            // Require staff privileges
            if (!isStaff()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to add tools'
                ]);
                break;
            }
            
            // Validate and sanitize inputs
            $name = sanitizeInput($_POST['name']);
            $code = sanitizeInput($_POST['code']);
            $category = sanitizeInput($_POST['category']);
            $storageLocation = sanitizeInput($_POST['storage_location']);
            $description = sanitizeInput($_POST['description'] ?? '');
            $maintenanceInterval = isset($_POST['maintenance_interval']) ? (int)$_POST['maintenance_interval'] : 0;
            
            // Validate required fields
            if (empty($name) || empty($code) || empty($category) || empty($storageLocation)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Name, code, category, and storage location are required'
                ]);
                break;
            }
            
            // Check if code already exists
            $query = "SELECT id FROM tools WHERE code = ?";
            $existingTool = $db->fetchSingle($query, "s", [$code]);
            
            if ($existingTool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'A tool with this code already exists'
                ]);
                break;
            }
            
            // Insert new tool
            $query = "INSERT INTO tools (name, code, category, storage_location, description, maintenance_interval, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 'available', NOW(), NOW())";
            $toolId = $db->insert($query, "sssssi", [$name, $code, $category, $storageLocation, $description, $maintenanceInterval]);
            
            if ($toolId) {
                // Generate QR code for the new tool
                $qrData = "Tool ID: " . $toolId;
                $filename = 'tool_' . $toolId;
                QRCodeGenerator::generateQRCode($qrData, $filename);
                
                // Log event
                logEvent("Tool added", "info", "Tool ID: $toolId, Name: $name");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool added successfully',
                    'tool_id' => $toolId,
                    'redirect' => 'tools.php'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add tool'
                ]);
            }
            break;
            
        case 'edit':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to edit tools'
                ]);
                break;
            }
            
            // Validate and sanitize inputs
            $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
            $name = sanitizeInput($_POST['name']);
            $code = sanitizeInput($_POST['code']);
            $category = sanitizeInput($_POST['category']);
            $storageLocation = sanitizeInput($_POST['storage_location']);
            $description = sanitizeInput($_POST['description'] ?? '');
            $status = sanitizeInput($_POST['status']);
            $maintenanceInterval = isset($_POST['maintenance_interval']) ? (int)$_POST['maintenance_interval'] : 0;
            
            // Validate required fields
            if ($toolId <= 0 || empty($name) || empty($code) || empty($category) || empty($storageLocation) || empty($status)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All required fields must be provided'
                ]);
                break;
            }
            
            // Check if tool exists
            $query = "SELECT id FROM tools WHERE id = ?";
            $existingTool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$existingTool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            // Check if code already exists for another tool
            $query = "SELECT id FROM tools WHERE code = ? AND id != ?";
            $duplicateCode = $db->fetchSingle($query, "si", [$code, $toolId]);
            
            if ($duplicateCode) {
                echo json_encode([
                    'success' => false,
                    'message' => 'A different tool with this code already exists'
                ]);
                break;
            }
            
            // Update tool
            $query = "UPDATE tools 
                      SET name = ?, code = ?, category = ?, storage_location = ?, 
                          description = ?, status = ?, maintenance_interval = ?, updated_at = NOW() 
                      WHERE id = ?";
            $result = $db->update($query, "ssssssii", [
                $name, $code, $category, $storageLocation, 
                $description, $status, $maintenanceInterval, $toolId
            ]);
            
            if ($result !== false) {
                // Log event
                logEvent("Tool updated", "info", "Tool ID: $toolId, Name: $name");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool updated successfully',
                    'redirect' => 'tools.php'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update tool'
                ]);
            }
            break;
            
        case 'delete':
            // Require admin privileges
            if (!isAdmin()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to delete tools'
                ]);
                break;
            }
            
            $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Check if tool exists
            $query = "SELECT name FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            // Check if tool has active transactions
            $query = "SELECT COUNT(*) as count FROM transactions WHERE tool_id = ? AND return_date IS NULL";
            $activeTrans = $db->fetchSingle($query, "i", [$toolId]);
            
            if ($activeTrans && $activeTrans['count'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Cannot delete tool: it has active borrowings'
                ]);
                break;
            }
            
            // Delete tool
            $query = "DELETE FROM tools WHERE id = ?";
            $result = $db->delete($query, "i", [$toolId]);
            
            if ($result !== false) {
                // Log event
                logEvent("Tool deleted", "warning", "Tool ID: $toolId, Name: " . $tool['name']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete tool'
                ]);
            }
            break;
            
        case 'schedule_maintenance':
            // Require staff privileges
            if (!isStaff()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to schedule maintenance'
                ]);
                break;
            }
            
            $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Check if tool exists
            $query = "SELECT name, status FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            // Check if tool is already in maintenance
            if ($tool['status'] === 'maintenance') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool is already in maintenance'
                ]);
                break;
            }
            
            // Check if tool is currently borrowed
            if ($tool['status'] === 'borrowed') {
                // Get transaction details
                $query = "SELECT t.*, u.email, u.first_name 
                          FROM transactions t
                          JOIN users u ON t.user_id = u.id
                          WHERE t.tool_id = ? AND t.return_date IS NULL";
                $transaction = $db->fetchSingle($query, "i", [$toolId]);
                
                if ($transaction) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Tool is currently borrowed and must be returned before maintenance'
                    ]);
                    break;
                }
            }
            
            // Update tool status
            $query = "UPDATE tools SET status = 'maintenance', updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "i", [$toolId]);
            
            if ($result !== false) {
                // Add maintenance record
                $query = "INSERT INTO maintenance_logs (tool_id, scheduled_date, status, created_by, created_at) 
                          VALUES (?, NOW(), 'scheduled', ?, NOW())";
                $db->insert($query, "ii", [$toolId, $_SESSION['user_id']]);
                
                // Log event
                logEvent("Tool set to maintenance", "info", "Tool ID: $toolId, Name: " . $tool['name']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tool scheduled for maintenance successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to schedule maintenance'
                ]);
            }
            break;
            
        case 'complete_maintenance':
            // Require staff privileges
            if (!isStaff()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to complete maintenance'
                ]);
                break;
            }
            
            $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
            $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
            
            if ($toolId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid tool ID'
                ]);
                break;
            }
            
            // Check if tool exists and is in maintenance
            $query = "SELECT name, status FROM tools WHERE id = ?";
            $tool = $db->fetchSingle($query, "i", [$toolId]);
            
            if (!$tool) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool not found'
                ]);
                break;
            }
            
            if ($tool['status'] !== 'maintenance') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tool is not currently in maintenance'
                ]);
                break;
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update tool status
                $query = "UPDATE tools SET status = 'available', updated_at = NOW() WHERE id = ?";
                $db->update($query, "i", [$toolId]);
                
                // Update maintenance record
                $query = "UPDATE maintenance_logs 
                          SET maintenance_date = NOW(), status = 'completed', notes = ?, completed_by = ? 
                          WHERE tool_id = ? AND status = 'scheduled' 
                          ORDER BY scheduled_date DESC 
                          LIMIT 1";
                $db->update($query, "sii", [$notes, $_SESSION['user_id'], $toolId]);
                
                // Commit transaction
                $conn->commit();
                
                // Log event
                logEvent("Maintenance completed", "info", "Tool ID: $toolId, Name: " . $tool['name']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Maintenance completed successfully'
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to complete maintenance: ' . $e->getMessage()
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
