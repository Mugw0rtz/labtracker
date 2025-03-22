<?php
/**
 * QR Code Scanning Page
 * Standalone page for scanning QR codes
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$pageTitle = 'Scan QR Code';
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
    
    .full-screen-container {
        min-height: calc(100vh - 200px);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
</style>';

require_once 'includes/header.php';
?>

<div class="container full-screen-container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 text-center">
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
                    
                    <div id="scan-result" class="mt-3"></div>
                    
                    <div class="d-grid mt-3">
                        <button id="startScanBtn" class="btn btn-primary">
                            <i class="fas fa-camera me-2"></i> Start Scanner
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3 text-start">
                        <i class="fas fa-info-circle me-2"></i> Position the QR code within the scanner area for best results. Make sure you have sufficient lighting.
                    </div>
                    
                    <div class="mt-4">
                        <h6>What would you like to do?</h6>
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <a href="borrow.php" class="btn btn-outline-primary">
                                <i class="fas fa-hand-holding me-2"></i> Borrow Tool
                            </a>
                            <a href="return.php" class="btn btn-outline-success">
                                <i class="fas fa-undo-alt me-2"></i> Return Tool
                            </a>
                        </div>
                    </div>
                </div>
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
        $('#scan-result').html('<div class="alert alert-info">Scanner active. Looking for QR code...</div>');
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
            $('#scan-result').html('<div class="alert alert-success">QR Code detected! Processing...</div>');
            
            // Check if it's a tool QR code
            if (result.includes('Tool ID:')) {
                const toolId = result.split('Tool ID:')[1].trim();
                
                // Show action buttons
                $('#scan-result').html(
                    '<div class="alert alert-success">' +
                    '<p><strong>Tool QR Code Detected!</strong></p>' +
                    '<div class="d-flex justify-content-center gap-2 mt-2">' +
                    '<a href="borrow.php?tool_id=' + toolId + '" class="btn btn-primary">Borrow This Tool</a>' +
                    '<a href="return.php?id=' + toolId + '" class="btn btn-success">Return This Tool</a>' +
                    '</div>' +
                    '</div>'
                );
            } 
            // Check if it's a transaction QR code
            else if (result.includes('Transaction ID:')) {
                const transactionId = result.split('Transaction ID:')[1].trim();
                
                // Direct to return page for transaction
                $('#scan-result').html(
                    '<div class="alert alert-success">' +
                    '<p><strong>Transaction QR Code Detected!</strong></p>' +
                    '<div class="text-center mt-2">' +
                    '<a href="return.php?transaction_id=' + transactionId + '" class="btn btn-success">Process Return</a>' +
                    '</div>' +
                    '</div>'
                );
            }
            // Unknown QR code format
            else {
                $('#scan-result').html('<div class="alert alert-danger">Invalid QR code format! Please scan a valid tool or transaction QR code.</div>');
            }
        } else {
            $('#scan-result').html('<div class="alert alert-danger">Failed to read QR code. Please try again.</div>');
        }
    }
    
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
