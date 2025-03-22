<?php
/**
 * Dashboard Page
 * Main control panel after login
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Require login
requireLogin();

// Connect to database
$db = new Database();
$conn = $db->getConnection();

// Get counts and statistics
$stats = [
    'total_tools' => 0,
    'available_tools' => 0,
    'borrowed_tools' => 0,
    'maintenance_tools' => 0,
    'overdue_tools' => 0,
    'pending_returns' => 0,
    'recent_transactions' => [],
    'popular_tools' => []
];

// Total tools
$query = "SELECT COUNT(*) as count FROM tools";
$result = $db->fetchSingle($query);
$stats['total_tools'] = $result ? $result['count'] : 0;

// Available tools
$query = "SELECT COUNT(*) as count FROM tools WHERE status = 'available'";
$result = $db->fetchSingle($query);
$stats['available_tools'] = $result ? $result['count'] : 0;

// Borrowed tools
$query = "SELECT COUNT(*) as count FROM tools WHERE status = 'borrowed'";
$result = $db->fetchSingle($query);
$stats['borrowed_tools'] = $result ? $result['count'] : 0;

// Maintenance tools
$query = "SELECT COUNT(*) as count FROM tools WHERE status = 'maintenance'";
$result = $db->fetchSingle($query);
$stats['maintenance_tools'] = $result ? $result['count'] : 0;

// Overdue tools
$query = "SELECT COUNT(*) as count FROM transactions 
          WHERE return_date IS NULL AND expected_return_date < NOW()";
$result = $db->fetchSingle($query);
$stats['overdue_tools'] = $result ? $result['count'] : 0;

// Tools borrowed by current user
$query = "SELECT COUNT(*) as count FROM transactions 
          WHERE user_id = ? AND return_date IS NULL";
$result = $db->fetchSingle($query, "i", [$_SESSION['user_id']]);
$stats['pending_returns'] = $result ? $result['count'] : 0;

// Recent transactions
$query = "SELECT t.*, tl.name as tool_name, u.username 
          FROM transactions t
          JOIN tools tl ON t.tool_id = tl.id
          JOIN users u ON t.user_id = u.id
          ORDER BY t.transaction_date DESC
          LIMIT 5";
$stats['recent_transactions'] = $db->fetchAll($query);

// Popular tools
$query = "SELECT t.id, t.name, t.status, COUNT(tr.id) as borrow_count
          FROM tools t
          LEFT JOIN transactions tr ON t.id = tr.tool_id
          GROUP BY t.id
          ORDER BY borrow_count DESC
          LIMIT 5";
$stats['popular_tools'] = $db->fetchAll($query);

// User's borrowed tools
$query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code,
          DATEDIFF(t.expected_return_date, NOW()) as days_remaining
          FROM transactions t
          JOIN tools tl ON t.tool_id = tl.id
          WHERE t.user_id = ? AND t.return_date IS NULL
          ORDER BY t.expected_return_date ASC";
$borrowedTools = $db->fetchAll($query, "i", [$_SESSION['user_id']]);

// Get user notifications
$query = "SELECT * FROM notifications
          WHERE user_id = ? OR user_id = 0
          ORDER BY created_at DESC
          LIMIT 5";
$notifications = $db->fetchAll($query, "i", [$_SESSION['user_id']]);

// Close database connection
$db->closeConnection();

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Dashboard</h1>
        <div>
            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#scanModal">
                <i class="fas fa-qrcode me-2"></i> Quick Scan
            </button>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Tools</div>
                            <div class="h3 mb-0 font-weight-bold"><?php echo $stats['total_tools']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tools fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Tools</div>
                            <div class="h3 mb-0 font-weight-bold"><?php echo $stats['available_tools']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Borrowed Tools</div>
                            <div class="h3 mb-0 font-weight-bold"><?php echo $stats['borrowed_tools']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Overdue Returns</div>
                            <div class="h3 mb-0 font-weight-bold"><?php echo $stats['overdue_tools']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Your Borrowed Tools -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Your Borrowed Tools</h6>
                    <a href="borrow.php" class="btn btn-sm btn-primary">Borrow Tools</a>
                </div>
                <div class="card-body">
                    <?php if (empty($borrowedTools)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-tools fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">You don't have any borrowed tools at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Borrowed On</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowedTools as $tool): ?>
                                        <?php
                                        $daysRemaining = $tool['days_remaining'];
                                        $statusClass = 'bg-success';
                                        $statusText = "$daysRemaining days left";
                                        
                                        if ($daysRemaining < 0) {
                                            $statusClass = 'bg-danger';
                                            $statusText = "Overdue by " . abs($daysRemaining) . " days";
                                        } elseif ($daysRemaining <= 1) {
                                            $statusClass = 'bg-warning';
                                            $statusText = "Due today";
                                            if ($daysRemaining == 1) {
                                                $statusText = "Due tomorrow";
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $tool['tool_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $tool['tool_code']; ?></small>
                                            </td>
                                            <td><?php echo formatDate($tool['transaction_date']); ?></td>
                                            <td><?php echo formatDate($tool['expected_return_date']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td>
                                                <a href="return.php?id=<?php echo $tool['tool_id']; ?>" class="btn btn-sm btn-primary">Return</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Notifications</h6>
                    <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-bell-slash fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No notifications at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item">
                                    <div class="d-flex align-items-center">
                                        <?php if ($notification['type'] == 'due_date'): ?>
                                            <div class="notification-icon bg-warning">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        <?php elseif ($notification['type'] == 'overdue'): ?>
                                            <div class="notification-icon bg-danger">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                        <?php elseif ($notification['type'] == 'maintenance'): ?>
                                            <div class="notification-icon bg-info">
                                                <i class="fas fa-wrench"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="notification-icon bg-primary">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ms-3">
                                            <h6 class="mb-1"><?php echo $notification['title']; ?></h6>
                                            <p class="mb-0 small"><?php echo $notification['message']; ?></p>
                                            <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities and Popular Tools -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['recent_transactions'])): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No recent activities to display.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent_transactions'] as $transaction): ?>
                                        <tr>
                                            <td><?php echo $transaction['tool_name']; ?></td>
                                            <td><?php echo $transaction['username']; ?></td>
                                            <td>
                                                <?php if (empty($transaction['return_date'])): ?>
                                                    <span class="badge bg-primary">Borrowed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Returned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (empty($transaction['return_date'])): ?>
                                                    <?php echo formatDateTime($transaction['transaction_date']); ?>
                                                <?php else: ?>
                                                    <?php echo formatDateTime($transaction['return_date']); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Popular Tools</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['popular_tools'])): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                            <p class="mb-0">No tool usage data available.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($stats['popular_tools'] as $tool): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo $tool['name']; ?></h6>
                                        <small>
                                            Status: 
                                            <span class="badge bg-<?php echo getStatusColorClass($tool['status']); ?>">
                                                <?php echo getStatusLabel($tool['status']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo $tool['borrow_count']; ?> uses</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanModalLabel">Scan QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div id="qr-reader" style="width: 100%; max-width: 400px; margin: 0 auto;"></div>
                    <div id="qr-reader-results"></div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Position the QR code within the scanner area.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="startScanBtn">Start Scanning</button>
            </div>
        </div>
    </div>
</div>

<!-- Extra JavaScript for QR scanning -->
<?php 
$extraJS = <<<EOT
<script>
    // QR Scanner functionality
    let scanner;
    
    document.getElementById('startScanBtn').addEventListener('click', function() {
        // Initialize scanner
        if (!scanner) {
            scanner = new QrScanner(
                document.getElementById('qr-reader'),
                result => {
                    handleScanResult(result);
                },
                {
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                }
            );
        }
        
        scanner.start();
        this.textContent = 'Scanning...';
        this.disabled = true;
    });
    
    // Handle QR code scan result
    function handleScanResult(result) {
        document.getElementById('qr-reader-results').innerHTML = 
            '<div class="alert alert-success mt-3">QR Code detected!</div>';
        
        if (scanner) {
            scanner.stop();
        }
        
        document.getElementById('startScanBtn').textContent = 'Start Scanning';
        document.getElementById('startScanBtn').disabled = false;
        
        // Process the result
        if (result) {
            // Check if it's a tool QR code
            if (result.includes('Tool ID:')) {
                const toolId = result.split('Tool ID:')[1].trim();
                // Redirect to borrow page with tool ID
                window.location.href = 'borrow.php?tool_id=' + toolId;
            } 
            // Check if it's a transaction QR code
            else if (result.includes('Transaction ID:')) {
                const transactionId = result.split('Transaction ID:')[1].trim();
                // Redirect to return page with transaction ID
                window.location.href = 'return.php?transaction_id=' + transactionId;
            }
            // Unknown QR code format
            else {
                document.getElementById('qr-reader-results').innerHTML = 
                    '<div class="alert alert-danger mt-3">Invalid QR code format!</div>';
            }
        }
    }
    
    // Clean up when modal is closed
    $('#scanModal').on('hidden.bs.modal', function() {
        if (scanner) {
            scanner.stop();
        }
        document.getElementById('startScanBtn').textContent = 'Start Scanning';
        document.getElementById('startScanBtn').disabled = false;
        document.getElementById('qr-reader-results').innerHTML = '';
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>
