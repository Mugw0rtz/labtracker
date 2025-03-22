<?php
/**
 * Main Configuration File
 * Contains application-wide settings and constants
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('QR_TEMP_PATH', ROOT_PATH . '/uploads/qrcodes');

// Application settings
define('APP_NAME', 'Laboratory Tool Management System');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'UTC');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Session configuration
define('SESSION_NAME', 'lab_tools_session');
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

// QR Code settings
define('QR_SIZE', 300); // Size of QR code image

// Email settings
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@example.com');
define('MAIL_PASSWORD', ''); // Will be loaded from environment variables
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', APP_NAME);

// Notification settings
define('NOTIFICATION_DAYS_BEFORE_DUE', 1); // Notify users 1 day before tool is due
define('NOTIFICATION_OVERDUE_INTERVAL', 86400); // Send overdue notifications every 24 hours

// Load environment variables or use defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'lab_tools');
define('MAIL_PASSWORD_ENV', getenv('MAIL_PASSWORD') ?: '');

// Create directories if they don't exist
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
}

if (!file_exists(QR_TEMP_PATH)) {
    mkdir(QR_TEMP_PATH, 0755, true);
}
?>
