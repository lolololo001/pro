<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("Error: School ID not found in session. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

// Get school info
$school_info = [];
try {
    $stmt = $conn->prepare('SELECT name, logo, address, phone, email FROM schools WHERE id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $school_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching school info: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST'){

// Check if file was uploaded
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload failed: ';
    switch ($_FILES['logo']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage .= 'File is too large.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage .= 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage .= 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMessage .= 'Missing a temporary folder.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMessage .= 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMessage .= 'A PHP extension stopped the file upload.';
            break;
        default:
            $errorMessage .= 'Unknown error.';
    }
    
    $_SESSION['logo_error'] = $errorMessage;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$fileType = $_FILES['logo']['type'];

if (!in_array($fileType, $allowedTypes)) {
    $_SESSION['logo_error'] = 'Invalid file type. Only JPG, PNG, and GIF images are allowed.';
}

// Validate file size (max 2MB)
$maxSize = 2 * 1024 * 1024; // 2MB in bytes
if ($_FILES['logo']['size'] > $maxSize) {
    $_SESSION['logo_error'] = 'File is too large. Maximum size is 2MB.';
}

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/school_logos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
$uniqueFilename = 'school_' . $school_id . '_' . uniqid() . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file
if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
    $_SESSION['logo_error'] = 'Failed to save the uploaded file.';
}

// Update school record in database
try {
    $conn = getDbConnection();
    
    // Get the current logo path to delete the old file if it exists
    $stmt = $conn->prepare('SELECT logo FROM schools WHERE id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldLogo = $result->fetch_assoc()['logo'] ?? '';
    $stmt->close();
    
    // Update the logo path in the database
    $logoPath = 'uploads/school_logos/' . $uniqueFilename;
    $stmt = $conn->prepare('UPDATE schools SET logo = ? WHERE id = ?');
    $stmt->bind_param('si', $logoPath, $school_id);
    
    if ($stmt->execute()) {
        // Delete old logo file if it exists
        if (!empty($oldLogo) && file_exists('../' . $oldLogo)) {
            unlink('../' . $oldLogo);
        }
        
        $_SESSION['logo_success'] = 'School logo has been updated successfully.';
    } else {
        // If database update fails, delete the uploaded file
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        $_SESSION['logo_error'] = 'Failed to update school logo in the database.';
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    // If an exception occurs, delete the uploaded file
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    $_SESSION['logo_error'] = 'Database error: ' . $e->getMessage();
}

// Redirect back to logo page
header('Location: logo.php');
exit;
}