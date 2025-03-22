<?php
/**
 * Tools Management Page
 * Lists all tools and provides management functionality
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/phpqrcode.php';

// Require login
requireLogin();

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Check for actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$toolId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$alertType = '';

// Handle tool actions
if ($action && $toolId && isStaff()) {
    switch ($action) {
        case 'maintenance':
            // Set tool to maintenance status
            $query = "UPDATE tools SET status = 'maintenance', updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "i", [$toolId]);
            
            if ($result) {
                $message = 'Tool has been set to maintenance status.';
                $alertType = 'success';
                
                // Log event
                logEvent("Tool set to maintenance", "info", "Tool ID: $toolId");
            } else {
                $message = 'Failed to update tool status.';
                $alertType = 'danger';
            }
            break;
            
        case 'available':
            // Set tool to available status
            $query = "UPDATE tools SET status = 'available', updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "i", [$toolId]);
            
            if ($result) {
                $message = 'Tool has been set to available status.';
                $alertType = 'success';
                
                // Log event
                logEvent("Tool set to available", "info", "Tool ID: $toolId");
            } else {
                $message = 'Failed to update tool status.';
                $alertType = 'danger';
            }
            break;
            
        case 'missing':
            // Set tool to missing status
            $query = "UPDATE tools SET status = 'missing', updated_at = NOW() WHERE id = ?";
            $result = $db->update($query, "i", [$toolId]);
            
            if ($result) {
                $message = 'Tool has been reported as missing.';
                $alertType = 'warning';
                
                // Log event
                logEvent("Tool marked as missing", "warning", "Tool ID: $toolId");
            } else {
                $message = 'Failed to update tool status.';
                $alertType = 'danger';
            }
            break;
    }
}

// Search filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR code LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($category)) {
    $conditions[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($status)) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

// Combine conditions
$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM tools $whereClause";
$countResult = $db->fetchSingle($countQuery, $types, $params);
$totalTools = $countResult ? $countResult['total'] : 0;
$totalPages = ceil($totalTools / $limit);

// Get tools with pagination
$query = "SELECT * FROM tools $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
$limitTypes = $types . 'ii';
$limitParams = array_merge($params, [$limit, $offset]);
$tools = $db->fetchAll($query, $limitTypes, $limitParams);

// Get categories for filter
$categoriesQuery = "SELECT DISTINCT category FROM tools ORDER BY category";
$categories = $db->fetchAll($categoriesQuery);

// Close database connection
$db->closeConnection();

$pageTitle = 'Tool Inventory';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Tool Inventory</h1>
        <?php if (isStaff()): ?>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToolModal">
                <i class="fas fa-plus me-2"></i> Add New Tool
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="Search tools...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category']; ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                                <?php echo $cat['category']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>>Available</option>
                        <option value="borrowed" <?php echo ($status == 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="maintenance" <?php echo ($status == 'maintenance') ? 'selected' : ''; ?>>In Maintenance</option>
                        <option value="missing" <?php echo ($status == 'missing') ? 'selected' : ''; ?>>Missing</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tools List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Tools (<?php echo $totalTools; ?>)</h6>
            <?php if (isStaff() && $totalTools > 0): ?>
                <a href="reports.php?type=inventory" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-download me-2"></i> Export Inventory
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($tools)): ?>
                <div class="text-center p-4">
                    <i class="fas fa-tools fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-0">No tools found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tools as $tool): ?>
                                <tr>
                                    <td><?php echo $tool['code']; ?></td>
                                    <td>
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#toolDetailsModal" 
                                           data-tool-id="<?php echo $tool['id']; ?>" 
                                           data-tool-name="<?php echo $tool['name']; ?>"
                                           data-tool-code="<?php echo $tool['code']; ?>"
                                           data-tool-category="<?php echo $tool['category']; ?>"
                                           data-tool-location="<?php echo $tool['storage_location']; ?>"
                                           data-tool-description="<?php echo $tool['description']; ?>"
                                           data-tool-status="<?php echo $tool['status']; ?>"
                                           class="tool-details-link">
                                            <?php echo $tool['name']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $tool['category']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusColorClass($tool['status']); ?>">
                                            <?php echo getStatusLabel($tool['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDateTime($tool['updated_at']); ?></td>
                                    <td>
                                        <?php if ($tool['status'] == 'available'): ?>
                                            <a href="borrow.php?tool_id=<?php echo $tool['id']; ?>" class="btn btn-sm btn-primary">Borrow</a>
                                        <?php endif; ?>
                                        
                                        <?php if (isStaff()): ?>
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                                    More
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                    <li>
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#qrCodeModal" data-tool-id="<?php echo $tool['id']; ?>" data-tool-name="<?php echo $tool['name']; ?>">
                                                            <i class="fas fa-qrcode me-2"></i> View QR Code
                                                        </a>
                                                    </li>
                                                    <?php if ($tool['status'] != 'available'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="tools.php?action=available&id=<?php echo $tool['id']; ?>">
                                                                <i class="fas fa-check-circle me-2"></i> Mark as Available
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($tool['status'] != 'maintenance'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="tools.php?action=maintenance&id=<?php echo $tool['id']; ?>">
                                                                <i class="fas fa-wrench me-2"></i> Set to Maintenance
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($tool['status'] != 'missing'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="tools.php?action=missing&id=<?php echo $tool['id']; ?>">
                                                                <i class="fas fa-question-circle me-2"></i> Report as Missing
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (isAdmin()): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item edit-tool" href="#" data-bs-toggle="modal" data-bs-target="#editToolModal" data-tool-id="<?php echo $tool['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i> Edit Tool
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">
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
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>" aria-label="Next">
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

<!-- Tool Details Modal -->
<div class="modal fade" id="toolDetailsModal" tabindex="-1" aria-labelledby="toolDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toolDetailsModalLabel">Tool Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-tools fa-4x text-primary mb-3"></i>
                    <h4 id="detailToolName"></h4>
                    <span class="badge bg-primary" id="detailToolCode"></span>
                </div>
                
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Category:</div>
                    <div class="col-8" id="detailToolCategory"></div>
                </div>
                
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Status:</div>
                    <div class="col-8">
                        <span class="badge" id="detailToolStatus"></span>
                    </div>
                </div>
                
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Location:</div>
                    <div class="col-8" id="detailToolLocation"></div>
                </div>
                
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Description:</div>
                    <div class="col-8" id="detailToolDescription"></div>
                </div>
                
                <div id="borrowActions" class="mt-4">
                    <!-- Dynamic content based on tool status -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="viewQrCodeBtn">View QR Code</a>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">QR Code for <span id="qrToolName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContainer" class="mb-3">
                    <!-- QR code will be loaded here -->
                </div>
                <p class="mb-0">Scan this QR code to borrow or return the tool.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="printQrCode">Print QR Code</a>
            </div>
        </div>
    </div>
</div>

<?php if (isStaff()): ?>
<!-- Add Tool Modal -->
<div class="modal fade" id="addToolModal" tabindex="-1" aria-labelledby="addToolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addToolModalLabel">Add New Tool</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addToolForm" method="post" action="/api/tools.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Tool Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="code" class="form-label">Tool Code</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="code" name="code" required>
                                <button class="btn btn-outline-secondary" type="button" id="generateCode">Generate</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category" list="categoryList" required>
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category']; ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="storage_location" class="form-label">Storage Location</label>
                            <input type="text" class="form-control" id="storage_location" name="storage_location" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="maintenance_interval" class="form-label">Maintenance Interval (days)</label>
                        <input type="number" class="form-control" id="maintenance_interval" name="maintenance_interval" min="0" value="90">
                        <div class="form-text">Set to 0 for no scheduled maintenance.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitAddTool">Add Tool</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Tool Modal -->
<div class="modal fade" id="editToolModal" tabindex="-1" aria-labelledby="editToolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editToolModalLabel">Edit Tool</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editToolForm" method="post" action="/api/tools.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tool_id" id="edit_tool_id">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Tool Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_code" class="form-label">Tool Code</label>
                            <input type="text" class="form-control" id="edit_code" name="code" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="edit_category" name="category" list="categoryList" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_storage_location" class="form-label">Storage Location</label>
                            <input type="text" class="form-control" id="edit_storage_location" name="storage_location" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="borrowed">Borrowed</option>
                                <option value="maintenance">In Maintenance</option>
                                <option value="missing">Missing</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_maintenance_interval" class="form-label">Maintenance Interval (days)</label>
                            <input type="number" class="form-control" id="edit_maintenance_interval" name="maintenance_interval" min="0">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteToolBtn">Delete Tool</button>
                <button type="button" class="btn btn-primary" id="submitEditTool">Save Changes</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Extra JavaScript for tool management -->
<?php 
$extraJS = <<<EOT
<script>
    // Tool Details Modal
    $(document).on('click', '.tool-details-link', function() {
        var toolId = $(this).data('tool-id');
        var toolName = $(this).data('tool-name');
        var toolCode = $(this).data('tool-code');
        var toolCategory = $(this).data('tool-category');
        var toolLocation = $(this).data('tool-location');
        var toolDescription = $(this).data('tool-description');
        var toolStatus = $(this).data('tool-status');
        
        $('#detailToolName').text(toolName);
        $('#detailToolCode').text(toolCode);
        $('#detailToolCategory').text(toolCategory);
        $('#detailToolLocation').text(toolLocation || 'Not specified');
        $('#detailToolDescription').text(toolDescription || 'No description available');
        
        // Set status badge color
        var statusClass = 'bg-secondary';
        if (toolStatus === 'available') statusClass = 'bg-success';
        if (toolStatus === 'borrowed') statusClass = 'bg-primary';
        if (toolStatus === 'maintenance') statusClass = 'bg-warning';
        if (toolStatus === 'missing') statusClass = 'bg-danger';
        
        $('#detailToolStatus').removeClass().addClass('badge ' + statusClass).text(
            toolStatus.charAt(0).toUpperCase() + toolStatus.slice(1)
        );
        
        // Set action buttons based on status
        var actionsHtml = '';
        if (toolStatus === 'available') {
            actionsHtml = '<a href="borrow.php?tool_id=' + toolId + '" class="btn btn-primary w-100">Borrow This Tool</a>';
        }
        $('#borrowActions').html(actionsHtml);
        
        // Set QR code button link
        $('#viewQrCodeBtn').attr('data-tool-id', toolId);
        $('#viewQrCodeBtn').attr('data-tool-name', toolName);
    });
    
    // QR Code Modal
    $(document).on('click', '#viewQrCodeBtn, [data-bs-target="#qrCodeModal"]', function() {
        var toolId = $(this).data('tool-id');
        var toolName = $(this).data('tool-name');
        
        $('#qrToolName').text(toolName);
        $('#qrCodeContainer').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');
        
        // Load QR code image
        $.ajax({
            url: '/api/tools.php',
            type: 'GET',
            data: {
                action: 'get_qr',
                tool_id: toolId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#qrCodeContainer').html('<img src="' + response.qr_url + '" alt="QR Code" class="img-fluid">');
                    $('#printQrCode').attr('href', response.qr_url);
                    
                    // Close the tool details modal and show QR code modal
                    $('#toolDetailsModal').modal('hide');
                    $('#qrCodeModal').modal('show');
                } else {
                    $('#qrCodeContainer').html('<div class="alert alert-danger">Failed to load QR code.</div>');
                }
            },
            error: function() {
                $('#qrCodeContainer').html('<div class="alert alert-danger">Error loading QR code.</div>');
            }
        });
    });
    
    // Generate random tool code
    $('#generateCode').click(function() {
        var prefix = 'TOOL';
        var random = Math.random().toString(36).substring(2, 8).toUpperCase();
        $('#code').val(prefix + '-' + random);
    });
    
    // Submit add tool form
    $('#submitAddTool').click(function() {
        $('#addToolForm').submit();
    });
    
    // Load tool data for editing
    $(document).on('click', '.edit-tool', function() {
        var toolId = $(this).data('tool-id');
        
        // Load tool data via AJAX
        $.ajax({
            url: '/api/tools.php',
            type: 'GET',
            data: {
                action: 'get',
                tool_id: toolId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var tool = response.tool;
                    
                    // Fill form fields
                    $('#edit_tool_id').val(tool.id);
                    $('#edit_name').val(tool.name);
                    $('#edit_code').val(tool.code);
                    $('#edit_category').val(tool.category);
                    $('#edit_storage_location').val(tool.storage_location);
                    $('#edit_description').val(tool.description);
                    $('#edit_status').val(tool.status);
                    $('#edit_maintenance_interval').val(tool.maintenance_interval);
                } else {
                    alert('Failed to load tool data: ' + response.message);
                }
            },
            error: function() {
                alert('Error loading tool data. Please try again.');
            }
        });
    });
    
    // Submit edit tool form
    $('#submitEditTool').click(function() {
        $('#editToolForm').submit();
    });
    
    // Delete tool
    $('#deleteToolBtn').click(function() {
        if (confirm('Are you sure you want to delete this tool? This action cannot be undone.')) {
            var toolId = $('#edit_tool_id').val();
            
            $.ajax({
                url: '/api/tools.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    tool_id: toolId,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Tool deleted successfully.');
                        window.location.reload();
                    } else {
                        alert('Failed to delete tool: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error deleting tool. Please try again.');
                }
            });
        }
    });
    
    // Print QR code
    $('#printQrCode').click(function(e) {
        e.preventDefault();
        var qrUrl = $(this).attr('href');
        var toolName = $('#qrToolName').text();
        
        var printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>QR Code - ${toolName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; }
                        .container { margin: 20px auto; max-width: 400px; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>${toolName}</h2>
                        <img src="${qrUrl}" alt="QR Code">
                        <p>Scan this QR code to borrow or return the tool.</p>
                    </div>
                    <script>
                        window.onload = function() { window.print(); }
                    </script>
                </body>
            </html>
        `);
        printWindow.document.close();
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>
