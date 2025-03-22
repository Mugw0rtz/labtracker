<?php
/**
 * Common Functions
 * Contains utility functions used throughout the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool Returns true if email is valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password using bcrypt
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hashed value
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool Returns true if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random string
 * @param int $length Length of the random string
 * @return string Random string
 */
function generateRandomString($length = 16) {
    $bytes = random_bytes(ceil($length / 2));
    return substr(bin2hex($bytes), 0, $length);
}

/**
 * Generate a unique tool code
 * @param string $prefix Optional prefix for the code
 * @return string Unique tool code
 */
function generateToolCode($prefix = 'TOOL') {
    return $prefix . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

/**
 * Format date in a human-readable format
 * @param string $date Date string
 * @param string $format Optional date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M d, Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format date and time in a human-readable format
 * @param string $datetime Date and time string
 * @param string $format Optional date and time format
 * @return string Formatted date and time
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Calculate time remaining until a due date
 * @param string $dueDate Due date string
 * @return array Array containing days, hours, and status
 */
function getTimeRemaining($dueDate) {
    $now = time();
    $due = strtotime($dueDate);
    $diff = $due - $now;
    
    // If due date has passed
    if ($diff <= 0) {
        return [
            'days' => 0,
            'hours' => 0,
            'status' => 'overdue',
            'seconds' => abs($diff)  // Seconds overdue
        ];
    }
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    
    $status = 'normal';
    
    // If less than 24 hours remaining
    if ($days == 0) {
        $status = 'urgent';
    }
    // If less than 3 days remaining
    else if ($days < 3) {
        $status = 'warning';
    }
    
    return [
        'days' => $days,
        'hours' => $hours,
        'status' => $status,
        'seconds' => $diff  // Seconds remaining
    ];
}

/**
 * Get user-friendly status label
 * @param string $status Status code
 * @return string User-friendly status label
 */
function getStatusLabel($status) {
    $labels = [
        'available' => 'Available',
        'borrowed' => 'Borrowed',
        'maintenance' => 'In Maintenance',
        'missing' => 'Missing',
        'inactive' => 'Inactive',
        'active' => 'Active',
        'damaged' => 'Damaged',
        'archived' => 'Archived',
        'overdue' => 'Overdue',
        'reserved' => 'Reserved'
    ];
    
    return $labels[$status] ?? ucfirst($status);
}

/**
 * Get status color class for CSS
 * @param string $status Status code
 * @return string CSS class name
 */
function getStatusColorClass($status) {
    $classes = [
        'available' => 'success',
        'borrowed' => 'primary',
        'maintenance' => 'warning',
        'missing' => 'danger',
        'inactive' => 'secondary',
        'active' => 'success',
        'damaged' => 'danger',
        'archived' => 'dark',
        'overdue' => 'danger',
        'reserved' => 'info'
    ];
    
    return $classes[$status] ?? 'secondary';
}

/**
 * Convert object to array
 * @param object $object Object to convert
 * @return array Associative array
 */
function objectToArray($object) {
    return json_decode(json_encode($object), true);
}

/**
 * Send email using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text alternative
 * @return bool Returns true on success, false on failure
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/config.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD_ENV;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Log application events
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 * @param string $context Additional context
 */
function logEvent($message, $level = 'info', $context = '') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] [%s] [User: %s] [IP: %s] %s %s\n",
        $timestamp,
        strtoupper($level),
        $userId,
        $ip,
        $message,
        $context ? "Context: $context" : ""
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate a date in Y-m-d format
 * @param string $date Date string
 * @return bool Returns true if date is valid
 */
function isValidDate($date) {
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    return $dateTime && $dateTime->format('Y-m-d') === $date;
}

/**
 * Check if a string is a valid phone number
 * @param string $phone Phone number to validate
 * @return bool Returns true if phone is valid
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $phone));
}

/**
 * Get alert HTML based on type
 * @param string $message Alert message
 * @param string $type Alert type (success, danger, warning, info)
 * @return string Alert HTML
 */
function getAlert($message, $type = 'info') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

/**
 * Truncate text to a specific length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append String to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 50, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $append;
}

/**
 * Get pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern with {:page} placeholder
 * @return string Pagination HTML
 */
function getPagination($currentPage, $totalPages, $urlPattern) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    $prevClass = $currentPage <= 1 ? ' disabled' : '';
    $prevUrl = str_replace('{:page}', max(1, $currentPage - 1), $urlPattern);
    $html .= '<li class="page-item' . $prevClass . '"><a class="page-link" href="' . $prevUrl . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);
    
    if ($endPage - $startPage < 4) {
        $startPage = max(1, $endPage - 4);
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $activeClass = $i == $currentPage ? ' active' : '';
        $pageUrl = str_replace('{:page}', $i, $urlPattern);
        $html .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
    }
    
    // Next button
    $nextClass = $currentPage >= $totalPages ? ' disabled' : '';
    $nextUrl = str_replace('{:page}', min($totalPages, $currentPage + 1), $urlPattern);
    $html .= '<li class="page-item' . $nextClass . '"><a class="page-link" href="' . $nextUrl . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}
?>
