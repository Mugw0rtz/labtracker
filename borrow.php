<?php
/**
 * Borrow Tool Page
 * Allows users to borrow tools by scanning QR codes or selecting from available tools
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
$toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
$selectedTool = null;
$availableTools = [];

// Check if specific tool ID was provided
if ($toolId > 0) {
    // Get tool details
    $query = "SELECT * FROM tools WHERE id = ? AND status = 'available'";
    $selectedTool = $db->fetchSingle($query, "i", [$toolId]);
    
    if (!$selectedTool) {
        // Tool not found or not available
        $_SESSION['alert_message'] = 'The selected tool is not available for borrowing.';
        $_SESSION['alert_type'] = 'danger';
        
        // Redirect to the tools page
        header('Location: tools.php');
        exit;
    }
}

// Get list of available tools for dropdown
$query = "SELECT id, name, code, category FROM tools WHERE status = 'available' ORDER BY name";
$availableTools = $db->fetchAll($query);

// Get current user details
$query = "SELECT * FROM users WHERE id = ?";
$user = $db->fetchSingle($query, "i", [$_SESSION['user_id']]);

// Close database connection
$db->closeConnection();

$pageTitle = 'Borrow Tool';
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
            <h1 class="mb-3">Borrow Tool</h1>
            <p class="lead">Scan a QR code or select a tool from the list to borrow it.</p>
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
                        <i class="fas fa-info-circle me-2"></i> Position the QR code within the scanner area. Make sure you have sufficient lighting.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manual Selection Section -->
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding me-2"></i> Manual Selection
                    </h5>
                </div>
                <div class="card-body">
                    <form id="borrowForm" method="post" action="/api/borrow.php">
                        <input type="hidden" name="action" value="borrow">
                        <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="tool_id" class="form-label">Select Tool</label>
                            <select class="form-select" id="tool_id" name="tool_id" required>
                                <option value="">-- Select a tool --</option>
                                <?php foreach ($availableTools as $tool): ?>
                                    <option value="<?php echo $tool['id']; ?>" <?php echo ($selectedTool && $selectedTool['id'] == $tool['id']) ? 'selected' : ''; ?>>
                                        <?php echo $tool['name'] . ' (' . $tool['code'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="2" required></textarea>
                            <div class="form-text">Briefly describe why you need this tool.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expected_return_date" class="form-label">Expected Return Date</label>
                            <input type="date" class="form-control" id="expected_return_date" name="expected_return_date" required>
                            <div class="form-text">When do you plan to return this tool?</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to handle the tool with care and return it in good condition by the specified date.
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-hand-holding me-2"></i> Borrow Tool
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tool Preview -->
            <div id="toolPreview" class="card shadow mt-4 <?php echo $selectedTool ? '' : 'd-none'; ?>">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Tool Preview</h5>
                </div>
                <div class="card-body">
                    <?php if ($selectedTool): ?>
                        <div class="text-center mb-3">
                            <i class="fas fa-tools fa-3x text-primary"></i>
                        </div>
                        <h4 class="text-center"><?php echo $selectedTool['name']; ?></h4>
                        <p class="text-center">
                            <span class="badge bg-primary"><?php echo $selectedTool['code']; ?></span>
                        </p>
                        <hr>
                        <div class="row mb-2">
                            <div class="col-4 fw-bold">Category:</div>
                            <div class="col-8"><?php echo $selectedTool['category']; ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 fw-bold">Location:</div>
                            <div class="col-8"><?php echo $selectedTool['storage_location']; ?></div>
                        </div>
                        <div class="row">
                            <div class="col-4 fw-bold">Description:</div>
                            <div class="col-8"><?php echo $selectedTool['description'] ?: 'No description available'; ?></div>
                        </div>
                    <?php else: ?>
                        <div id="noToolSelected" class="text-center p-4">
                            <i class="fas fa-hand-pointer fa-3x text-secondary mb-3"></i>
                            <p>Select a tool to see details</p>
                        </div>
                        <div id="toolDetails" class="d-none">
                            <div class="text-center mb-3">
                                <i class="fas fa-tools fa-3x text-primary"></i>
                            </div>
                            <h4 class="text-center" id="previewToolName"></h4>
                            <p class="text-center">
                                <span class="badge bg-primary" id="previewToolCode"></span>
                            </p>
                            <hr>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Category:</div>
                                <div class="col-8" id="previewToolCategory"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-4 fw-bold">Location:</div>
                                <div class="col-8" id="previewToolLocation"></div>
                            </div>
                            <div class="row">
                                <div class="col-4 fw-bold">Description:</div>
                                <div class="col-8" id="previewToolDescription"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Borrowings Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Your Recent Borrowings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="recentBorrowingsTable">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th>Borrowed Date</th>
                                    <th>Expected Return</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center">Loading recent borrowings...</td>
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
                <h5 class="modal-title" id="successModalLabel">Tool Borrowed Successfully</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="py-4">
                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <h3>Thank You!</h3>
                    <p id="successMessage" class="lead">You have successfully borrowed the tool.</p>
                    <div id="borrowQrCode" class="mb-3">
                        <!-- QR code will be loaded here -->
                    </div>
                    <p class="small text-muted">You can use this QR code when returning the tool.</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Extra JavaScript for QR scanning and tool preview -->
<?php 
$extraJS = <<<EOT
<script>
    // Set minimum date for expected return date to tomorrow
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    var tomorrowFormatted = tomorrow.toISOString().split('T')[0];
    document.getElementById('expected_return_date').setAttribute('min', tomorrowFormatted);
    document.getElementById('expected_return_date').value = tomorrowFormatted;
    
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
                $('#scan-result').html('<div class="alert alert-success mt-3">QR Code detected! Loading tool information...</div>');
                
                // Get tool info via AJAX
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
                            
                            // Check if tool is available
                            if (tool.status === 'available') {
                                // Select tool in dropdown
                                $('#tool_id').val(tool.id).trigger('change');
                                
                                $('#scan-result').html('<div class="alert alert-success mt-3">Tool found and selected: ' + tool.name + '</div>');
                            } else {
                                $('#scan-result').html('<div class="alert alert-warning mt-3">This tool is not available for borrowing (Status: ' + 
                                    getStatusLabel(tool.status) + ')</div>');
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
            // Not a valid tool QR code
            else {
                $('#scan-result').html('<div class="alert alert-danger mt-3">Invalid QR code format. Please scan a valid tool QR code.</div>');
            }
        }
    }
    
    // Tool preview when selecting from dropdown
    $('#tool_id').change(function() {
        var toolId = $(this).val();
        
        if (toolId) {
            // Get tool info via AJAX
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
                        
                        // Update preview
                        $('#previewToolName').text(tool.name);
                        $('#previewToolCode').text(tool.code);
                        $('#previewToolCategory').text(tool.category);
                        $('#previewToolLocation').text(tool.storage_location || 'Not specified');
                        $('#previewToolDescription').text(tool.description || 'No description available');
                        
                        // Show tool details
                        $('#noToolSelected').addClass('d-none');
                        $('#toolDetails').removeClass('d-none');
                        $('#toolPreview').removeClass('d-none');
                    }
                }
            });
        } else {
            // Hide preview if no tool selected
            $('#toolPreview').addClass('d-none');
        }
    });
    
    // Handle form submission
    $('#borrowForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: '/api/borrow.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    $('#successMessage').text('You have successfully borrowed: ' + response.tool_name);
                    
                    // Load transaction QR code if available
                    if (response.qr_url) {
                        $('#borrowQrCode').html('<img src="' + response.qr_url + '" alt="Transaction QR Code" class="img-fluid" style="max-width: 200px;">');
                    }
                    
                    // Show modal
                    $('#successModal').modal('show');
                    
                    // Reset form
                    $('#borrowForm')[0].reset();
                    $('#tool_id').val('');
                    $('#toolPreview').addClass('d-none');
                    
                    // Update recent borrowings
                    loadRecentBorrowings();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });
    
    // Status label helper function
    function getStatusLabel(status) {
        const labels = {
            'available': 'Available',
            'borrowed': 'Borrowed',
            'maintenance': 'In Maintenance',
            'missing': 'Missing'
        };
        return labels[status] || status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    // Load recent borrowings
    function loadRecentBorrowings() {
        $.ajax({
            url: '/api/borrow.php',
            type: 'GET',
            data: {
                action: 'recent'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var html = '';
                    
                    if (response.borrowings.length === 0) {
                        html = '<tr><td colspan="4" class="text-center">You have no recent borrowings.</td></tr>';
                    } else {
                        response.borrowings.forEach(function(item) {
                            var statusClass = 'bg-primary';
                            if (item.days_remaining < 0) {
                                statusClass = 'bg-danger';
                            } else if (item.days_remaining <= 1) {
                                statusClass = 'bg-warning';
                            }
                            
                            var statusText = item.days_remaining + ' days left';
                            if (item.days_remaining < 0) {
                                statusText = 'Overdue by ' + Math.abs(item.days_remaining) + ' days';
                            } else if (item.days_remaining === 0) {
                                statusText = 'Due today';
                            } else if (item.days_remaining === 1) {
                                statusText = 'Due tomorrow';
                            }
                            
                            html += '<tr>' +
                                '<td>' + item.tool_name + ' <small class="text-muted">(' + item.tool_code + ')</small></td>' +
                                '<td>' + formatDate(item.transaction_date) + '</td>' +
                                '<td>' + formatDate(item.expected_return_date) + '</td>' +
                                '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>' +
                                '</tr>';
                        });
                    }
                    
                    $('#recentBorrowingsTable tbody').html(html);
                }
            },
            error: function() {
                $('#recentBorrowingsTable tbody').html('<tr><td colspan="4" class="text-center text-danger">Error loading recent borrowings.</td></tr>');
            }
        });
    }
    
    // Format date helper function
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    // Load recent borrowings on page load
    $(document).ready(function() {
        loadRecentBorrowings();
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
