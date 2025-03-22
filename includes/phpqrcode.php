<?php
/**
 * QR Code Generator 
 * A wrapper class for PHP QR Code library
 */

// Include the QR Code library or define a custom implementation
require_once __DIR__ . '/../config/config.php';

/**
 * QRCodeGenerator class
 * Handles generation of QR codes for tools
 */
class QRCodeGenerator {
    /**
     * Generate QR code for a tool or transaction
     * 
     * @param string $data The data to encode in the QR code
     * @param string $filename Filename to save the QR code (without extension)
     * @param int $size Size of the QR code
     * @return string Path to the generated QR code image
     */
    public static function generateQRCode($data, $filename = null, $size = null) {
        // Create temporary directory if it doesn't exist
        if (!file_exists(QR_TEMP_PATH)) {
            mkdir(QR_TEMP_PATH, 0755, true);
        }
        
        // Set default values
        $size = $size ?: QR_SIZE;
        $filename = $filename ?: 'qrcode_' . md5($data . time());
        $filePath = QR_TEMP_PATH . '/' . $filename . '.png';
        
        // Implementation using SVG instead of PNG
        $svgCode = self::generateQRSVG($data, $size);
        $svgFilePath = QR_TEMP_PATH . '/' . $filename . '.svg';
        
        file_put_contents($svgFilePath, $svgCode);
        
        return $svgFilePath;
    }
    
    /**
     * Generate a QR code in SVG format
     * 
     * @param string $data The data to encode
     * @param int $size Size of the QR code
     * @return string SVG code
     */
    private static function generateQRSVG($data, $size) {
        // Simple QR code matrix generator
        $matrix = self::generateQRMatrix($data);
        
        // Matrix dimensions
        $matrixSize = count($matrix);
        
        // Calculate the square size
        $squareSize = $size / $matrixSize;
        
        // Start SVG
        $svg = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
        $svg .= '<rect width="100%" height="100%" fill="white"/>';
        
        // Create squares
        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix[$y][$x]) {
                    $svg .= '<rect x="' . ($x * $squareSize) . '" y="' . ($y * $squareSize) . '" width="' . $squareSize . '" height="' . $squareSize . '" fill="black"/>';
                }
            }
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Generate a QR matrix
     * Note: This is a simplified implementation.
     * For real-world use, include a proper QR code library.
     * 
     * @param string $data The data to encode
     * @return array 2D array representing QR code matrix
     */
    private static function generateQRMatrix($data) {
        // This is a simplified placeholder implementation
        // In a real implementation, you would use a proper QR code library
        
        // Generate a simple checksum to create a unique pattern
        $checksum = md5($data);
        $matrixSize = 29; // A common size for QR codes
        
        // Create an empty matrix
        $matrix = array_fill(0, $matrixSize, array_fill(0, $matrixSize, 0));
        
        // Add finder patterns (three large squares in corners)
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                // Top-left finder pattern
                $matrix[$i][$j] = (($i == 0 || $i == 6 || $j == 0 || $j == 6) || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) ? 1 : 0;
                
                // Top-right finder pattern
                $matrix[$i][$matrixSize - $j - 1] = (($i == 0 || $i == 6 || $j == 0 || $j == 6) || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) ? 1 : 0;
                
                // Bottom-left finder pattern
                $matrix[$matrixSize - $i - 1][$j] = (($i == 0 || $i == 6 || $j == 0 || $j == 6) || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) ? 1 : 0;
            }
        }
        
        // Use the checksum to fill the rest of the matrix
        for ($i = 0; $i < $matrixSize; $i++) {
            for ($j = 0; $j < $matrixSize; $j++) {
                // Skip finder patterns
                if (($i < 7 && $j < 7) || 
                    ($i < 7 && $j >= $matrixSize - 7) || 
                    ($i >= $matrixSize - 7 && $j < 7)) {
                    continue;
                }
                
                // Use checksum to determine cell value
                $check = ord($checksum[($i * $matrixSize + $j) % strlen($checksum)]);
                $matrix[$i][$j] = ($check & (1 << (($i + $j) % 8))) ? 1 : 0;
            }
        }
        
        return $matrix;
    }
    
    /**
     * Get the URL for a QR code image
     * 
     * @param int $toolId Tool ID
     * @return string URL to QR code
     */
    public static function getToolQRUrl($toolId) {
        $filename = 'tool_' . $toolId;
        $svgFilePath = QR_TEMP_PATH . '/' . $filename . '.svg';
        
        // If QR code doesn't exist, generate it
        if (!file_exists($svgFilePath)) {
            // Generated data format: "Tool ID: {id}"
            $data = "Tool ID: " . $toolId;
            self::generateQRCode($data, $filename);
        }
        
        return '/uploads/qrcodes/' . $filename . '.svg';
    }
    
    /**
     * Get the URL for a transaction QR code
     * 
     * @param int $transactionId Transaction ID
     * @return string URL to QR code
     */
    public static function getTransactionQRUrl($transactionId) {
        $filename = 'transaction_' . $transactionId;
        $svgFilePath = QR_TEMP_PATH . '/' . $filename . '.svg';
        
        // If QR code doesn't exist, generate it
        if (!file_exists($svgFilePath)) {
            // Generated data format: "Transaction ID: {id}"
            $data = "Transaction ID: " . $transactionId;
            self::generateQRCode($data, $filename);
        }
        
        return '/uploads/qrcodes/' . $filename . '.svg';
    }
}
?>
