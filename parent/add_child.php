<?php
session_start();
require_once '../config/config.php';

// Check if parent is logged in
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get parent information
$parent_id = $_SESSION['parent_id'];
$error = '';
$success = '';

// Handle add child form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_child'])) {
    // Validate and sanitize input
    $student_id_number = trim($_POST['student_id_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $school_id = intval($_POST['school_id'] ?? 0);
    
    // Validate required fields
    if (empty($student_id_number) || empty($full_name) || empty($school_id)) {
        $_SESSION['add_child_error'] = 'All fields are required';
        header('Location: dashboard.php');
        exit;
    }
    
    try {
        $conn = getDbConnection();
        
        // Check if the student exists in the database
        // First, determine which field to use (admission_number or registration_number)
        $columnsResult = $conn->query("SHOW COLUMNS FROM students");
        if (!$columnsResult) {
            throw new Exception("Failed to get students table structure: " . $conn->error);
        }
        
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Determine which fields to use for student identification
        $id_field = in_array('admission_number', $columns) ? 'admission_number' : 'registration_number';
        
        // Check if we have first_name and last_name or just name
        $name_condition = "";
        if (in_array('first_name', $columns) && in_array('last_name', $columns)) {
            $name_condition = "AND (CONCAT(first_name, ' ', last_name) = ? OR CONCAT(last_name, ' ', first_name) = ?)"; 
        } else if (in_array('name', $columns)) {
            $name_condition = "AND name = ?";
        }
        
        // Prepare the query to find the student
        $query = "SELECT id FROM students WHERE $id_field = ? AND school_id = ? $name_condition";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare student search query: " . $conn->error);
        }
        
        // Bind parameters based on the name fields available
        if (strpos($name_condition, 'CONCAT') !== false) {
            $stmt->bind_param('siss', $student_id_number, $school_id, $full_name, $full_name);
        } else if (!empty($name_condition)) {
            $stmt->bind_param('sis', $student_id_number, $school_id, $full_name);
        } else {
            $stmt->bind_param('si', $student_id_number, $school_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['add_child_error'] = 'No student found with the provided information. Please check and try again.';
            header('Location: dashboard.php');
            exit;
        }
        
        $student = $result->fetch_assoc();
        $student_id = $student['id'];
        
        // Check if this student is already associated with this parent
        $check_stmt = $conn->prepare("SELECT id FROM student_parent WHERE student_id = ? AND parent_id = ?");
        $check_stmt->bind_param('ii', $student_id, $parent_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['add_child_error'] = 'This child is already associated with your account.';
            header('Location: dashboard.php');
            exit;
        }
        
        // Associate the student with the parent
        $insert_stmt = $conn->prepare("INSERT INTO student_parent (student_id, parent_id, is_primary) VALUES (?, ?, ?)");
        $is_primary = 0; // Set to 1 if this is the first child
        
        // Check if this is the first child for this parent
        $primary_check = $conn->prepare("SELECT id FROM student_parent WHERE parent_id = ?");
        $primary_check->bind_param('i', $parent_id);
        $primary_check->execute();
        $primary_result = $primary_check->get_result();
        
        if ($primary_result->num_rows === 0) {
            $is_primary = 1; // This is the first child, set as primary
        }
        
        $insert_stmt->bind_param('iii', $student_id, $parent_id, $is_primary);
        
        if ($insert_stmt->execute()) {
            $_SESSION['add_child_success'] = 'Child successfully added to your account!';
        } else {
            $_SESSION['add_child_error'] = 'Failed to associate child with your account: ' . $insert_stmt->error;
        }
        
        $conn->close();
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['add_child_error'] = 'System error: ' . $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
}

// If not a POST request, redirect back to dashboard
header('Location: dashboard.php');
exit;