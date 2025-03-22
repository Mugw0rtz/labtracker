<?php
/**
 * Return Tool Page
 * Allows users to return borrowed tools by scanning QR codes or selecting from their borrowed tools
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

// Initialize variables
$toolId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$transactionId = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;
$selectedTransaction = null;
$borrowedTools = [];

// If transaction ID is provided, get transaction details
if ($transactionId > 0) {
    $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code, tl.id as tool_id
              FROM transactions t
              JOIN tools tl ON t.tool_id = tl.id
              WHERE t.id = ? AND t.user_id = ? AND t.return_date IS NULL";
    $selectedTransaction = $db->fetchSingle($query, "ii", [$transactionId, $_SESSION['user_id']]);
    
    if (!$selectedTransaction) {
        // Transaction not found or already returned
        $_SESSION['alert_message'] = 'The selected transaction was not found or the tool has already been returned.';
        $_SESSION['alert_type'] = 'danger';
        
        // Redirect to the dashboard
        header('Location: dashboard.php');
        exit;
    }
}
// If tool ID is provided, get the related transaction
else if ($toolId > 0) {
    $query = "SELECT t.*, tl.name as tool_name, tl.code as tool_code, tl.id as tool_id
              FROM transactions t
              JOIN tools tl ON t.tool_id = tl.id
              WHERE t.tool_id = ? AND t.user_id = ? AND t.return_date IS NULL";
    $selectedTransaction = $db->fetchSingle($query, "ii", [$toolId, $_SESSION['user_id']]);
    
    if (!$selectedTransaction) {
        // No active borrowing found for this tool
        $_SESSION['alert_message'] = 'You do not have an active borrowing for this tool.';
        $_SESSION['alert_type'] = 'danger';
        
        // Redirect to the dashboard
        header('Location: dashboard.php');
        exit;
    }
}

// Get list of user's borrowed tools for dropdown
$query = "SELECT t.id as transaction_id, t.transaction_date, t.expected_return_date,
          tl.id as tool_id, tl.name as tool_name, tl.code as tool_code,
          DATEDIFF(t.expected_return_date, NOW()) as days_remaining
          FROM transactions t
          JOIN tools tl ON t.tool_id = tl.id
          WHERE t.user_id = ? AND t.return_date IS NULL
          ORDER BY t.expected_return_date ASC";
$borrowedTools = $db->fetchAll($query, "i", [$_SESSION['user_id']]);

// Close database connection
$db->closeConnection();

$pageTitle = 'Return Tool';
// Additional CSS for QR scanner
$extraCSS = '
<style>
    #qr-reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .scanner-container {
        position: relative;
        overflow: hidden;
        border-radius: 10px;
    }
    
    .scanner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.3);
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    
    .scanner-frame {
        width: 80%;
        height: 80%;
        border: 2px solid #ffffff;
        border-radius: 10px;
        box-shadow: 0 0 0 100vw rgba(0, 0, 0, 0.5);
    }
    
    .scanner-line {
        position: absolute;
        width: 100%;
        height: 2px;
        background: #007bff;
        animation: scan 2s linear infinite;
    }
    
    @keyframes scan {
        0% { top: 10%; }
        50% { top: 90%; }
        100% { top: 10%; }
    }
</style>';

require_once 'includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-3">Return Tool</h1>
            <p class="lead">Scan a QR code or select a borrowed tool to return it.</p>
        </div>
    </div>
    
    <div class="row">
        <!-- QR Scanner Section -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i> Scan QR Code
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="scanner-container mb-3">
                        <div id="qr-reader"></div>
                        <div class="scanner-overlay">
                            <div class="scanner-frame">
                                <div class="scanner-line"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="scan-result"></div>
                    
                    <div class="d-grid mt-3">
                        <button id="startScanBtn" class="btn btn-primary">
                            <i class="fas fa-camera me-2"></i> Start Scanner
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3 text-start">
                        <i class="fas fa-info-circle me-2"></i> Scan the QR code associated with the borrowed tool or transaction.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manual Return Section -->
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-undo-alt me-2"></i> Return Borrowed Tool
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($borrowedTools)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-info-circle fa-3x text-secondary mb-3"></i>
                            <p>You don't have any borrowed tools to return.</p>
                            <a href="borrow.php" class="btn btn-primary mt-2">Borrow a Tool</a>
                        </div>
                    <?php else: ?>
                        <form id="returnForm" method="post" action="/api/return.php">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="transaction_id" class="form-label">Select Borrowed Tool</label>
                                <select class="form-select" id="transaction_id" name="transaction_id" required>
                                    <option value="">-- Select a borrowed tool --</option>
                                    <?php foreach ($borrowedTools as $tool): ?>
                                        <?php
                                        $daysText = 'days left';
                                        if ($tool['days_remaining'] < 0) {
                                            $daysText = 'days overdue';
                                        }
                                        ?>
                                        <option value="<?php echo $tool['transaction_id']; ?>" <?php echo ($selectedTransaction && $selectedTransaction['id'] == $tool['transaction_id']) ? 'selected' : ''; ?> 
                                                data-tool-name="<?php echo $tool['tool_name']; ?>"
                                                data-tool-code="<?php echo $tool['tool_code']; ?>"
                                                data-borrow-date="<?php echo $tool['transaction_date']; ?>"
                                                data-return-date="<?php echo $tool['expected_return_date']; ?>"
                                                data-days="<?php echo abs($tool['days_remaining']); ?>"
                                                data-status="<?php echo $tool['days_remaining'] < 0 ? 'overdue' : 'on-time'; ?>">
                                            <?php echo $tool['tool_name'] . ' (' . $tool['tool_code'] . ') - ' . abs($tool['days_remaining']) . ' ' . $daysText; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="condition" class="form-label">Tool Condition</label>
                                <select class="form-select" id="condition" name="condition" required>
                                    <option value="good">Good - No issues</option>
                                    <option value="fair">Fair - Minor wear</option>
                                    <option value="poor">Poor - Needs maintenance</option>
                                    <option value="damaged">Damaged - Requires repair</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Optional notes about the tool condition"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-undo-alt me-2"></i> Return Tool
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Transaction Preview -->
            <div id="transactionPreview" class="card shadow mt-4 <?php echo $selectedTransaction ? '' : 'd-none'; ?>">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Borrowing Details</h5>
                </div>
                <div class="card-body">
                    <?php if ($selectedTransaction): ?>
                        <div class="text-center mb-3">
                            <i class="fas fa-handshake fa-3x text-primary"></i>
                        </div>
                        <h4 class="text-center"><?php echo $selectedTransaction['tool_name']; ?></h4>
                        <p class="text-center">
                            <span class="badge bg-primary"><?php echo $selectedTransaction['tool_code']; ?></span>
                        </p>
                        <hr>
                        <div class="row mb-2">
                            <div class="col-4 fw-bold">Borrowed On:</div>
                            <div class="col-8"><?php echo formatDate($selectedTransaction['transaction_date']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 fw-bold">Due Date:</div>
                            <div class="col-8"><?php echo formatDate($selectedTransaction['expected_return_date']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 fw-bold">Purpose:</div>
                            <div class="col-8"><?php echo $selectedTransaction['purpose']; ?></div>
                        </div>
                        <?php
                        $daysRemaining = (new DateTime($selectedTransaction['expected_return_date']))->diff(new DateTime())->format("%r%a");
                        $statusClass = $daysRemaining < 0 ? 'bg-danger' : 'bg-success';
                        $statusText = $daysRemaining < 0 ? 'Overdue by ' . abs($daysRemaining) . ' days' : 'On time';
                        ?>
                        <div class="row">
                            <div class="col-4 fw-bold">Status:</div>
                            <div class="col-8">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="noTransactionSelected" class="text-center p-4">
                            <i class="fas fa-hand-pointer fa-3x text-secondary mb-3"></i>
                            <p>Select a borrowed tool to see details</p>
                        </div>
                        <div id="transactionDetails" class="d-none">
                            <div class="text-center mb-3">
                                <i class="fas fa-handshake fa-3x text-primary"></i>
                            </div>
                            <h4 class="text-center" id="previewToolName"></h4>
                            <p class="text-center">
                                <span class="badge bg-primary" id="previewToolCode"></span>
                            </p>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Borrowed On:</div>
                                <div class="col-8" id="previewBorrowDate"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Due Date:</div>
                                <div class="col-8" id="previewReturnDate"></div>
                            </div>
                            <div class="row">
                                <div class="col-4 fw-bold">Status:</div>
                                <div class="col-8">
                                    <span class="badge" id="previewStatus"></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Return History Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Your Return History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="returnHistoryTable">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th>Borrowed Date</th>
                                    <th>Returned Date</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center">Loading return history...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel">Tool Returned Successfully</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="py-4">
                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <h3>Thank You!</h3>
                    <p id="successMessage" class="lead">You have successfully returned the tool.</p>
                    <div id="returnCondition" class="alert alert-info">
                        <!-- Condition info will be displayed here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Extra JavaScript for QR scanning and transaction preview -->
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
        $('#scan-result').html('<div class="alert alert-info mt-3">Scanner active. Looking for QR code...</div>');
    });
    
    // Handle QR code scan result
    function handleScanResult(result) {
        if (scanner) {
            scanner.stop();
        }
        
        document.getElementById('startScanBtn').textContent = 'Start Scanner';
        document.getElementById('startScanBtn').disabled = false;
        
        // Process the result
        if (result) {
            // Check if it's a tool QR code
            if (result.includes('Tool ID:')) {
                const toolId = result.split('Tool ID:')[1].trim();
                $('#scan-result').html('<div class="alert alert-success mt-3">QR Code detected! Checking borrowed tool...</div>');
                
                // Find transaction associated with this tool
                $.ajax({
                    url: '/api/return.php',
                    type: 'GET',
                    data: {
                        action: 'check_tool',
                        tool_id: toolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Select transaction in dropdown if it exists
                            if (response.transaction_id) {
                                $('#transaction_id').val(response.transaction_id).trigger('change');
                                $('#scan-result').html('<div class="alert alert-success mt-3">Borrowing found for: ' + response.tool_name + '</div>');
                            } else {
                                $('#scan-result').html('<div class="alert alert-warning mt-3">You have not borrowed this tool.</div>');
                            }
                        } else {
                            $('#scan-result').html('<div class="alert alert-danger mt-3">Error: ' + response.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#scan-result').html('<div class="alert alert-danger mt-3">Server error. Please try again.</div>');
                    }
                });
            } 
            // Check if it's a transaction QR code
            else if (result.includes('Transaction ID:')) {
                const transactionId = result.split('Transaction ID:')[1].trim();
                $('#scan-result').html('<div class="alert alert-success mt-3">QR Code detected! Checking transaction...</div>');
                
                // Verify transaction belongs to current user
                $.ajax({
                    url: '/api/return.php',
                    type: 'GET',
                    data: {
                        action: 'check_transaction',
                        transaction_id: transactionId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Select transaction in dropdown
                            $('#transaction_id').val(transactionId).trigger('change');
                            $('#scan-result').html('<div class="alert alert-success mt-3">Transaction found for: ' + response.tool_name + '</div>');
                        } else {
                            $('#scan-result').html('<div class="alert alert-danger mt-3">Error: ' + response.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#scan-result').html('<div class="alert alert-danger mt-3">Server error. Please try again.</div>');
                    }
                });
            }
            // Not a valid QR code
            else {
                $('#scan-result').html('<div class="alert alert-danger mt-3">Invalid QR code format. Please scan a valid tool or transaction QR code.</div>');
            }
        }
    }
    
    // Transaction preview when selecting from dropdown
    $('#transaction_id').change(function() {
        var transactionId = $(this).val();
        
        if (transactionId) {
            // Get selected option data attributes
            var selectedOption = $('#transaction_id option:selected');
            var toolName = selectedOption.data('tool-name');
            var toolCode = selectedOption.data('tool-code');
            var borrowDate = new Date(selectedOption.data('borrow-date'));
            var returnDate = new Date(selectedOption.data('return-date'));
            var days = selectedOption.data('days');
            var status = selectedOption.data('status');
            
            // Format dates
            var borrowDateFormatted = borrowDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            var returnDateFormatted = returnDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            
            // Update preview
            $('#previewToolName').text(toolName);
            $('#previewToolCode').text(toolCode);
            $('#previewBorrowDate').text(borrowDateFormatted);
            $('#previewReturnDate').text(returnDateFormatted);
            
            // Set status badge class and text
            var statusClass = status === 'overdue' ? 'bg-danger' : 'bg-success';
            var statusText = status === 'overdue' ? 'Overdue by ' + days + ' days' : 'On time';
            $('#previewStatus').removeClass().addClass('badge ' + statusClass).text(statusText);
            
            // Show transaction details
            $('#noTransactionSelected').addClass('d-none');
            $('#transactionDetails').removeClass('d-none');
            $('#transactionPreview').removeClass('d-none');
        } else {
            // Hide preview if no transaction selected
            $('#transactionPreview').addClass('d-none');
        }
    });
    
    // Handle form submission
    $('#returnForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: '/api/return.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    $('#successMessage').text('You have successfully returned: ' + response.tool_name);
                    
                    // Show condition info based on the reported condition
                    var conditionText = '';
                    switch(response.condition) {
                        case 'good':
                            conditionText = 'The tool has been reported in good condition. Thank you for taking care of it!';
                            break;
                        case 'fair':
                            conditionText = 'The tool has been reported with minor wear. It will be checked by staff.';
                            break;
                        case 'poor':
                            conditionText = 'The tool has been reported to need maintenance. It will be serviced before becoming available again.';
                            break;
                        case 'damaged':
                            conditionText = 'The tool has been reported as damaged. Our staff will assess the damage and schedule repairs.';
                            break;
                        default:
                            conditionText = 'Tool condition has been recorded.';
                    }
                    $('#returnCondition').text(conditionText);
                    
                    // Show modal
                    $('#successModal').modal('show');
                    
                    // Reset form
                    $('#returnForm')[0].reset();
                    $('#transaction_id').val('');
                    $('#transactionPreview').addClass('d-none');
                    
                    // Update return history
                    loadReturnHistory();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });
    
    // Load return history
    function loadReturnHistory() {
        $.ajax({
            url: '/api/return.php',
            type: 'GET',
            data: {
                action: 'history'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var html = '';
                    
                    if (response.history.length === 0) {
                        html = '<tr><td colspan="4" class="text-center">You have no return history yet.</td></tr>';
                    } else {
                        response.history.forEach(function(item) {
                            // Determine badge class based on condition
                            var conditionClass = 'bg-secondary';
                            if (item.condition === 'good') conditionClass = 'bg-success';
                            if (item.condition === 'fair') conditionClass = 'bg-info';
                            if (item.condition === 'poor') conditionClass = 'bg-warning';
                            if (item.condition === 'damaged') conditionClass = 'bg-danger';
                            
                            // Format condition text
                            var conditionText = item.condition.charAt(0).toUpperCase() + item.condition.slice(1);
                            
                            html += '<tr>' +
                                '<td>' + item.tool_name + ' <small class="text-muted">(' + item.tool_code + ')</small></td>' +
                                '<td>' + formatDate(item.transaction_date) + '</td>' +
                                '<td>' + formatDate(item.return_date) + '</td>' +
                                '<td><span class="badge ' + conditionClass + '">' + conditionText + '</span></td>' +
                                '</tr>';
                        });
                    }
                    
                    $('#returnHistoryTable tbody').html(html);
                }
            },
            error: function() {
                $('#returnHistoryTable tbody').html('<tr><td colspan="4" class="text-center text-danger">Error loading return history.</td></tr>');
            }
        });
    }
    
    // Format date helper function
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    // Load return history on page load
    $(document).ready(function() {
        loadReturnHistory();
    });
    
    // Clean up when page is unloaded
    $(window).on('beforeunload', function() {
        if (scanner) {
            scanner.stop();
        }
    });
</script>
EOT;
?>

<?php require_once 'includes/footer.php'; ?>
