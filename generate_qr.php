<?php
/**
 * QR Code Generator
 * Generates and outputs QR codes for tools and transactions
 */

require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/phpqrcode.php';

// Require login
requireLogin();

// Get parameters
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$download = isset($_GET['download']) && $_GET['download'] == 'true';

// Validate parameters
if (empty($type) || $id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing or invalid parameters';
    exit;
}

// Generate QR code based on type
$qrCodePath = '';
$filename = '';

switch ($type) {
    case 'tool':
        // Generate QR code for tool
        $data = "Tool ID: " . $id;
        $filename = 'tool_' . $id;
        $qrCodePath = QRCodeGenerator::generateQRCode($data, $filename);
        break;
        
    case 'transaction':
        // Generate QR code for transaction
        $data = "Transaction ID: " . $id;
        $filename = 'transaction_' . $id;
        $qrCodePath = QRCodeGenerator::generateQRCode($data, $filename);
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid QR code type';
        exit;
}

// Check if QR code was generated successfully
if (empty($qrCodePath) || !file_exists($qrCodePath)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Failed to generate QR code';
    exit;
}

// Set appropriate headers for SVG file
header('Content-Type: image/svg+xml');
header('Cache-Control: max-age=86400, public');

// If download flag is set, force download
if ($download) {
    $downloadFilename = $filename . '.svg';
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
}

// Output the QR code
readfile($qrCodePath);
exit;
?>
