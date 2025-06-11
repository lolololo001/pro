<?php
/**
 * Configuration file for SchoolComm application
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', ''); // Change in production
define('DB_NAME', 'schoolcomm');

// Application configuration
define('APP_NAME', 'SchoolComm');
define('APP_URL', 'http://localhost/pro');
define('APP_ROOT', dirname(__DIR__));

// Color scheme
define('PRIMARY_COLOR', '#00704A'); // Starbucks green
define('FOOTER_COLOR', '#D4E9D7'); // Tea green
define('ACCENT_COLOR', '#006241'); // Darker green for accents

// Session configuration
define('SESSION_NAME', 'schoolcomm_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// File upload configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx');
define('UPLOAD_PATH', APP_ROOT . '/uploads');

// Email configuration (for production)
define('MAIL_HOST', '');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_ADDRESS', 'noreply@schoolcomm.com');
define('MAIL_FROM_NAME', 'SchoolComm');

// Python sentiment analysis configuration
define('PYTHON_PATH', '/usr/bin/python3'); // Update based on server configuration
define('SENTIMENT_SCRIPT_PATH', APP_ROOT . '/python/sentiment_analysis.py');

// Error reporting
ini_set('display_errors', 0); // Set to 1 during development
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/logs/error.log');

// Time zone
date_default_timezone_set('UTC'); // Change based on location

// Create database connection
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Load helper functions
require_once APP_ROOT . '/includes/helpers.php';