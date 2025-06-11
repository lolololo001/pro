<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$school_id = $_SESSION['school_admin_school_id'] ?? 0;
$class_id = intval($_GET['id'] ?? 0);

if ($class_id > 0 && $school_id > 0) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $class_id, $school_id);
    if ($stmt->execute()) {
        $_SESSION['class_success'] = "Class deleted successfully.";
    } else {
        $_SESSION['class_error'] = "Failed to delete class.";
    }
    $stmt->close();
} else {
    $_SESSION['class_error'] = "Invalid class ID.";
}

header('Location: dashboard.php');
exit;