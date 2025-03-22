<?php
/**
 * Reports Page
 * Generates various reports about tools, borrowings, and returns
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Require login
requireLogin();

// Require staff privileges
requireStaff();

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Get report type and parameters
$reportType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'inventory';
$startDate = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$user = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Get download flag
$download = isset($_GET['download']) && $_GET['download'] == 'true';

// If download requested, generate file
if ($download) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $reportType . '_report_' . date('Y-m-d') . '.csv');
    
    // Create output handle
    $output = fopen('php://output', 'w');
    
    // Generate appropriate report
    switch ($reportType) {
        case 'inventory':
            generateInventoryCSV($db, $output, $status, $category);
            break;
        case 'transactions':
            generateTransactionsCSV($db, $output, $startDate, $endDate, $status, $user);
            break;
        case 'overdue':
            generateOverdueCSV($db, $output);
            break;
        case 'maintenance':
            generateMaintenanceCSV($db, $output);
            break;
    }
    
    fclose($output);
    exit;
}

// Get list of tool categories for filter
$categoriesQuery = "SELECT DISTINCT category FROM tools ORDER BY category";
$categories = $db->fetchAll($categoriesQuery);

// Get list of users for filter
$usersQuery = "SELECT id, username, first_name, last_name FROM users ORDER BY username";
$users = $db->fetchAll($usersQuery);

// Close database connection
$db->closeConnection();

$pageTitle = 'Reports';
require_once 'includes/header.php';

/**
 * Generate Inventory CSV
 * 
 * @param Database $db Database connection
 * @param resource $output Output handle
 * @param string $status Filter by status
 * @param string $category Filter by category
 */
function generateInventoryCSV($db, $output, $status, $category) {
    // Write CSV header
    fputcsv($output, [
        'ID', 'Code', 'Name', 'Category', 'Location', 'Status', 
        'Description', 'Last Updated', 'Maintenance Interval (days)'
    ]);
    
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
    
    // Write tool data
    foreach ($tools as $tool) {
        fputcsv($output, [
            $tool['id'],
            $tool['code'],
            $tool['name'],
            $tool['category'],
            $tool['storage_location'],
            $tool['status'],
            $tool['description'],
            $tool['updated_at'],
            $tool['maintenance_interval']
        ]);
    }
}

/**
 * Generate Transactions CSV
 * 
 * @param Database $db Database connection
 * @param resource $output Output handle
 * @param string $startDate Start date
 * @param string $endDate End date
 * @param string $status Transaction status
 * @param int $user Filter by user ID
 */
function generateTransactionsCSV($db, $output, $startDate, $endDate, $status, $user) {
    // Write CSV header
    fputcsv($output, [
        'Transaction ID', 'Tool Code', 'Tool Name', 'Borrower', 'Borrowed Date',
        'Expected Return Date', 'Actual Return Date', 'Status', 'Return Condition', 'Notes'
    ]);
    
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
    
    if ($user > 0) {
        $conditions[] = "t.user_id = ?";
        $params[] = $user;
        $types .= 'i';
    }
    
    // Combine conditions
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get transactions
    $query = "SELECT t.*, tl.code, tl.name, u.username, 
              CONCAT(u.first_name, ' ', u.last_name) AS full_name
              FROM transactions t
              JOIN tools tl ON t.tool_id = tl.id
              JOIN users u ON t.user_id = u.id
              $whereClause
              ORDER BY t.transaction_date DESC";
    $transactions = $db->fetchAll($query, $types, $params);
    
    // Write transaction data
    foreach ($transactions as $transaction) {
        // Determine status
        $status = 'Borrowed';
        if ($transaction['return_date']) {
            $status = 'Returned';
        } elseif (strtotime($transaction['expected_return_date']) < time()) {
            $status = 'Overdue';
        }
        
        fputcsv($output, [
            $transaction['id'],
            $transaction['code'],
            $transaction['name'],
            $transaction['full_name'] . ' (' . $transaction['username'] . ')',
            $transaction['transaction_date'],
            $transaction['expected_return_date'],
            $transaction['return_date'] ?: 'Not returned',
            $status,
            $transaction['return_condition'] ?: 'N/A',
            $transaction['notes'] ?: ''
        ]);
    }
}

/**
 * Generate Overdue CSV
 * 
 * @param Database $db Database connection
 * @param resource $output Output handle
 */
function generateOverdueCSV($db, $output) {
    // Write CSV header
    fputcsv($output, [
        'Transaction ID', 'Tool Code', 'Tool Name', 'Borrower', 'Email',
        'Borrowed Date', 'Expected Return Date', 'Days Overdue', 'Phone'
    ]);
    
    // Get overdue transactions
    $query = "SELECT t.*, tl.code, tl.name, 
              u.username, u.email, u.phone, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
              DATEDIFF(NOW(), t.expected_return_date) AS days_overdue
              FROM transactions t
              JOIN tools tl ON t.tool_id = tl.id
              JOIN users u ON t.user_id = u.id
              WHERE t.return_date IS NULL AND t.expected_return_date < NOW()
              ORDER BY days_overdue DESC";
    $overdue = $db->fetchAll($query);
    
    // Write overdue data
    foreach ($overdue as $item) {
        fputcsv($output, [
            $item['id'],
            $item['code'],
            $item['name'],
            $item['full_name'] . ' (' . $item['username'] . ')',
            $item['email'],
            $item['transaction_date'],
            $item['expected_return_date'],
            $item['days_overdue'],
            $item['phone'] ?: 'N/A'
        ]);
    }
}

/**
 * Generate Maintenance CSV
 * 
 * @param Database $db Database connection
 * @param resource $output Output handle
 */
function generateMaintenanceCSV($db, $output) {
    // Write CSV header
    fputcsv($output, [
        'Tool ID', 'Code', 'Name', 'Category', 'Last Maintenance',
        'Next Maintenance Due', 'Days Until Due', 'Status', 'Location'
    ]);
    
    // Get tools with maintenance info
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
    
    // Write maintenance data
    foreach ($maintenance as $item) {
        fputcsv($output, [
            $item['id'],
            $item['code'],
            $item['name'],
            $item['category'],
            $item['last_maintenance'] ?: 'Never',
            $item['next_maintenance'] ?: 'ASAP',
            $item['days_until_due'] ?: 'Due Now',
            $item['status'],
            $item['storage_location']
        ]);
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Reports</h1>
    </div>
    
    <!-- Report Types Tabs -->
    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($reportType == 'inventory') ? 'active' : ''; ?>" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab" aria-controls="inventory" aria-selected="<?php echo ($reportType == 'inventory') ? 'true' : 'false'; ?>">
                <i class="fas fa-clipboard-list me-2"></i> Inventory
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($reportType == 'transactions') ? 'active' : ''; ?>" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="<?php echo ($reportType == 'transactions') ? 'true' : 'false'; ?>">
                <i class="fas fa-exchange-alt me-2"></i> Transactions
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($reportType == 'overdue') ? 'active' : ''; ?>" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#overdue" type="button" role="tab" aria-controls="overdue" aria-selected="<?php echo ($reportType == 'overdue') ? 'true' : 'false'; ?>">
                <i class="fas fa-exclamation-triangle me-2"></i> Overdue
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo ($reportType == 'maintenance') ? 'active' : ''; ?>" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab" aria-controls="maintenance" aria-selected="<?php echo ($reportType == 'maintenance') ? 'true' : 'false'; ?>">
                <i class="fas fa-wrench me-2"></i> Maintenance
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="reportTabsContent">
        <!-- Inventory Report Tab -->
        <div class="tab-pane fade <?php echo ($reportType == 'inventory') ? 'show active' : ''; ?>" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Inventory Report</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="reports.php" class="row g-3 mb-4">
                        <input type="hidden" name="type" value="inventory">
                        
                        <div class="col-md-4">
                            <label for="inventory_status" class="form-label">Tool Status</label>
                            <select class="form-select" id="inventory_status" name="status">
                                <option value="">All Statuses</option>
                                <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>>Available</option>
                                <option value="borrowed" <?php echo ($status == 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                                <option value="maintenance" <?php echo ($status == 'maintenance') ? 'selected' : ''; ?>>In Maintenance</option>
                                <option value="missing" <?php echo ($status == 'missing') ? 'selected' : ''; ?>>Missing</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="inventory_category" class="form-label">Category</label>
                            <select class="form-select" id="inventory_category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category']; ?>" <?php echo ($category == $cat['category']) ? 'selected' : ''; ?>>
                                        <?php echo $cat['category']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Generate Report</button>
                            <button type="button" class="btn btn-success" onclick="downloadReport('inventory')">
                                <i class="fas fa-download me-2"></i> Download CSV
                            </button>
                        </div>
                    </form>
                    
                    <div class="table-responsive" id="inventoryReportTable">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">Loading inventory data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transactions Report Tab -->
        <div class="tab-pane fade <?php echo ($reportType == 'transactions') ? 'show active' : ''; ?>" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Transaction History Report</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="reports.php" class="row g-3 mb-4">
                        <input type="hidden" name="type" value="transactions">
                        
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="transaction_status" class="form-label">Status</label>
                            <select class="form-select" id="transaction_status" name="status">
                                <option value="">All</option>
                                <option value="borrowed" <?php echo ($status == 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                                <option value="returned" <?php echo ($status == 'returned') ? 'selected' : ''; ?>>Returned</option>
                                <option value="overdue" <?php echo ($status == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user">
                                <option value="0">All Users</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo ($user == $u['id']) ? 'selected' : ''; ?>>
                                        <?php echo $u['username']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Generate</button>
                            <button type="button" class="btn btn-success" onclick="downloadReport('transactions')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </form>
                    
                    <div class="table-responsive" id="transactionsReportTable">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tool</th>
                                    <th>Borrower</th>
                                    <th>Borrowed Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading transaction data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overdue Report Tab -->
        <div class="tab-pane fade <?php echo ($reportType == 'overdue') ? 'show active' : ''; ?>" id="overdue" role="tabpanel" aria-labelledby="overdue-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Overdue Tools Report</h6>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadReport('overdue')">
                        <i class="fas fa-download me-2"></i> Download CSV
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="overdueReportTable">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tool</th>
                                    <th>Borrower</th>
                                    <th>Email</th>
                                    <th>Borrowed Date</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading overdue data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Report Tab -->
        <div class="tab-pane fade <?php echo ($reportType == 'maintenance') ? 'show active' : ''; ?>" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Maintenance Schedule Report</h6>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadReport('maintenance')">
                        <i class="fas fa-download me-2"></i> Download CSV
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="maintenanceReportTable">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tool</th>
                                    <th>Category</th>
                                    <th>Last Maintenance</th>
                                    <th>Next Due</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading maintenance data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Extra JavaScript for reports -->
<?php 
$extraJS = <<<EOT
<script>
    // Load report data when tab is shown
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var targetTab = $(e.target).attr('id');
        var reportType = targetTab.split('-')[0];
        
        loadReportData(reportType);
    });
    
    // Load report data
    function loadReportData(reportType) {
        var url = '/api/reports.php';
        var params = {
            report_type: reportType
        };
        
        // Add additional parameters based on report type
        if (reportType === 'inventory') {
            params.status = $('#inventory_status').val();
            params.category = $('#inventory_category').val();
        }
        else if (reportType === 'transactions') {
            params.start_date = $('#start_date').val();
            params.end_date = $('#end_date').val();
            params.status = $('#transaction_status').val();
            params.user = $('#user_id').val();
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateReportTable(reportType, response.data);
                } else {
                    alert('Error loading report: ' + response.message);
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    }
    
    // Update report table with data
    function updateReportTable(reportType, data) {
        var tableId = '#' + reportType + 'ReportTable tbody';
        var html = '';
        
        switch(reportType) {
            case 'inventory':
                if (data.length === 0) {
                    html = '<tr><td colspan="6" class="text-center">No inventory data found.</td></tr>';
                } else {
                    data.forEach(function(item) {
                        var statusClass = 'bg-secondary';
                        if (item.status === 'available') statusClass = 'bg-success';
                        if (item.status === 'borrowed') statusClass = 'bg-primary';
                        if (item.status === 'maintenance') statusClass = 'bg-warning';
                        if (item.status === 'missing') statusClass = 'bg-danger';
                        
                        html += '<tr>' +
                            '<td>' + item.code + '</td>' +
                            '<td>' + item.name + '</td>' +
                            '<td>' + item.category + '</td>' +
                            '<td><span class="badge ' + statusClass + '">' + 
                                (item.status.charAt(0).toUpperCase() + item.status.slice(1)) + '</span></td>' +
                            '<td>' + (item.storage_location || 'Not specified') + '</td>' +
                            '<td>' + formatDateTime(item.updated_at) + '</td>' +
                            '</tr>';
                    });
                }
                break;
                
            case 'transactions':
                if (data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center">No transaction data found.</td></tr>';
                } else {
                    data.forEach(function(item) {
                        var statusClass = 'bg-primary';
                        var statusText = 'Borrowed';
                        
                        if (item.return_date) {
                            statusClass = 'bg-success';
                            statusText = 'Returned';
                        } else if (new Date(item.expected_return_date) < new Date()) {
                            statusClass = 'bg-danger';
                            statusText = 'Overdue';
                        }
                        
                        var conditionHtml = 'N/A';
                        if (item.return_condition) {
                            var conditionClass = 'bg-secondary';
                            if (item.return_condition === 'good') conditionClass = 'bg-success';
                            if (item.return_condition === 'fair') conditionClass = 'bg-info';
                            if (item.return_condition === 'poor') conditionClass = 'bg-warning';
                            if (item.return_condition === 'damaged') conditionClass = 'bg-danger';
                            
                            conditionHtml = '<span class="badge ' + conditionClass + '">' + 
                                (item.return_condition.charAt(0).toUpperCase() + item.return_condition.slice(1)) + '</span>';
                        }
                        
                        html += '<tr>' +
                            '<td>' + item.tool_name + ' <small class="text-muted">(' + item.tool_code + ')</small></td>' +
                            '<td>' + item.borrower + '</td>' +
                            '<td>' + formatDateTime(item.transaction_date) + '</td>' +
                            '<td>' + formatDate(item.expected_return_date) + '</td>' +
                            '<td>' + (item.return_date ? formatDateTime(item.return_date) : 'Not returned') + '</td>' +
                            '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>' +
                            '<td>' + conditionHtml + '</td>' +
                            '</tr>';
                    });
                }
                break;
                
            case 'overdue':
                if (data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center">No overdue tools found.</td></tr>';
                } else {
                    data.forEach(function(item) {
                        html += '<tr>' +
                            '<td>' + item.tool_name + ' <small class="text-muted">(' + item.tool_code + ')</small></td>' +
                            '<td>' + item.borrower + '</td>' +
                            '<td><a href="mailto:' + item.email + '">' + item.email + '</a></td>' +
                            '<td>' + formatDate(item.transaction_date) + '</td>' +
                            '<td>' + formatDate(item.expected_return_date) + '</td>' +
                            '<td><span class="badge bg-danger">' + item.days_overdue + ' days</span></td>' +
                            '<td>' +
                                '<button class="btn btn-sm btn-outline-primary send-reminder" data-transaction="' + item.id + '">' +
                                    '<i class="fas fa-envelope me-1"></i> Send Reminder' +
                                '</button>' +
                            '</td>' +
                            '</tr>';
                    });
                }
                break;
                
            case 'maintenance':
                if (data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center">No maintenance data found.</td></tr>';
                } else {
                    data.forEach(function(item) {
                        var daysLeftClass = 'bg-success';
                        if (item.days_until_due <= 0) {
                            daysLeftClass = 'bg-danger';
                        } else if (item.days_until_due <= 7) {
                            daysLeftClass = 'bg-warning';
                        }
                        
                        var daysText = item.days_until_due + ' days';
                        if (item.days_until_due <= 0) {
                            daysText = 'Overdue';
                        }
                        
                        var statusClass = 'bg-secondary';
                        if (item.status === 'available') statusClass = 'bg-success';
                        if (item.status === 'borrowed') statusClass = 'bg-primary';
                        if (item.status === 'maintenance') statusClass = 'bg-warning';
                        if (item.status === 'missing') statusClass = 'bg-danger';
                        
                        var actionButton = '<button class="btn btn-sm btn-outline-primary schedule-maintenance" ' +
                            'data-tool="' + item.id + '" data-name="' + item.name + '">' +
                            '<i class="fas fa-wrench me-1"></i> Schedule' +
                            '</button>';
                            
                        if (item.status === 'maintenance') {
                            actionButton = '<button class="btn btn-sm btn-outline-success complete-maintenance" ' +
                                'data-tool="' + item.id + '" data-name="' + item.name + '">' +
                                '<i class="fas fa-check me-1"></i> Complete' +
                                '</button>';
                        }
                        
                        html += '<tr>' +
                            '<td>' + item.name + ' <small class="text-muted">(' + item.code + ')</small></td>' +
                            '<td>' + item.category + '</td>' +
                            '<td>' + (item.last_maintenance ? formatDate(item.last_maintenance) : 'Never') + '</td>' +
                            '<td>' + (item.next_maintenance ? formatDate(item.next_maintenance) : 'ASAP') + '</td>' +
                            '<td><span class="badge ' + daysLeftClass + '">' + daysText + '</span></td>' +
                            '<td><span class="badge ' + statusClass + '">' + 
                                (item.status.charAt(0).toUpperCase() + item.status.slice(1)) + '</span></td>' +
                            '<td>' + actionButton + '</td>' +
                            '</tr>';
                    });
                }
                break;
        }
        
        $(tableId).html(html);
        
        // Initialize buttons after updating table
        initializeButtons();
    }
    
    // Initialize interactive buttons in reports
    function initializeButtons() {
        // Send reminder to overdue users
        $('.send-reminder').on('click', function() {
            var transactionId = $(this).data('transaction');
            
            if (confirm('Send a reminder notification to this user?')) {
                $.ajax({
                    url: '/api/notifications.php',
                    type: 'POST',
                    data: {
                        action: 'send_reminder',
                        transaction_id: transactionId,
                        csrf_token: '<?php echo getCSRFToken(); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Reminder sent successfully!');
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
        
        // Schedule maintenance for a tool
        $('.schedule-maintenance').on('click', function() {
            var toolId = $(this).data('tool');
            var toolName = $(this).data('name');
            
            if (confirm('Schedule maintenance for ' + toolName + '?')) {
                $.ajax({
                    url: '/api/tools.php',
                    type: 'POST',
                    data: {
                        action: 'schedule_maintenance',
                        tool_id: toolId,
                        csrf_token: '<?php echo getCSRFToken(); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Maintenance scheduled!');
                            loadReportData('maintenance');
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
        
        // Complete maintenance for a tool
        $('.complete-maintenance').on('click', function() {
            var toolId = $(this).data('tool');
            var toolName = $(this).data('name');
            
            if (confirm('Mark maintenance as complete for ' + toolName + '?')) {
                $.ajax({
                    url: '/api/tools.php',
                    type: 'POST',
                    data: {
                        action: 'complete_maintenance',
                        tool_id: toolId,
                        csrf_token: '<?php echo getCSRFToken(); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Maintenance completed!');
                            loadReportData('maintenance');
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
    }
    
    // Download report as CSV
    function downloadReport(reportType) {
        // Build query string for download
        var params = 'type=' + reportType + '&download=true';
        
        // Add additional parameters based on report type
        if (reportType === 'inventory') {
            params += '&status=' + $('#inventory_status').val();
            params += '&category=' + $('#inventory_category').val();
        }
        else if (reportType === 'transactions') {
            params += '&start_date=' + $('#start_date').val();
            params += '&end_date=' + $('#end_date').val();
            params += '&status=' + $('#transaction_status').val();
            params += '&user=' + $('#user_id').val();
        }
        
        // Redirect to download URL
        window.location.href = 'reports.php?' + params;
    }
    
    // Format date helper function
    function formatDate(dateString) {
        if (!dateString) return '';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    // Format date with time helper function
    function formatDateTime(dateString) {
        if (!dateString) return '';
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    // Load the initial report data when page loads
    $(document).ready(function() {
        // Determine which tab is active
        var activeTab = $('.nav-link.active').attr('id');
        var reportType = activeTab.split('-')[0];
        
        loadReportData(reportType);
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>
