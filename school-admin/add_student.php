<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config and email helper
require_once '../config/config.php';
require_once '../includes/email_helper.php';

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

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'reg_number' => ''
];

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';


// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    // Convert empty date to NULL for database
    $dob = !empty($dob) ? $dob : null;
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Log input values for debugging
    error_log("Input values: department_id=" . var_export($department_id, true) . ", class_id=" . var_export($class_id, true) . ", dob=" . var_export($dob, true));
    
    if (empty($first_name) || empty($last_name) || empty($parent_name) || empty($parent_phone)) {
        $_SESSION['student_error'] = 'All required fields must be filled.';
        header('Location: add_student_form.php');
        exit;
    }
    
    // Validate email format if provided
    if (!empty($parent_email) && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['student_error'] = 'Please enter a valid parent email address.';
        header('Location: add_student_form.php');
        exit;
    }
    
    try {
        // Get database connection
        $conn = getDbConnection();
        
        // Check if students table exists
        $result = $conn->query("SHOW TABLES LIKE 'students'");
        if ($result->num_rows == 0) {
            // Create students table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                department_id INT,
                class_id INT,
                gender VARCHAR(10),
                dob DATE,
                parent_name VARCHAR(100) NOT NULL,
                parent_phone VARCHAR(20) NOT NULL,
                parent_email VARCHAR(100),
                address TEXT,
                reg_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (school_id),
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL,
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
            )");
        } else {
            // Check if department_id column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'department_id'");
            if ($column_check->num_rows == 0) {
                // Add department_id column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN department_id INT, ADD FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL");
            }
            
            // Check if class_id column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'class_id'");
            if ($column_check->num_rows == 0) {
                // Add class_id column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN class_id INT, ADD FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL");
            }
            
            // Check if parent_name column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_name'");
            if ($column_check->num_rows == 0) {
                // Add parent_name column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN parent_name VARCHAR(100) NOT NULL DEFAULT 'Parent'");
            }
            
            // Check if parent_phone column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_phone'");
            if ($column_check->num_rows == 0) {
                // Add parent_phone column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN parent_phone VARCHAR(20) NOT NULL DEFAULT '0000000000'");
            }
            
            // Check if parent_email column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'parent_email'");
            if ($column_check->num_rows == 0) {
                // Add parent_email column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN parent_email VARCHAR(100)");
            }
            
            // Check if address column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'address'");
            if ($column_check->num_rows == 0) {
                // Add address column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN address TEXT");
            }
            
            // Check if reg_number column exists
            $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'reg_number'");
            if ($column_check->num_rows == 0) {
                // Add reg_number column if it doesn't exist
                $conn->query("ALTER TABLE students ADD COLUMN reg_number VARCHAR(20)");
            }
        }
        
        // Generate registration number (current year/formatted ID)
        $current_year = date('Y');
        
        // First, check if we need to generate a unique registration number
        // Get the highest student ID for this school to ensure uniqueness
        $max_id_query = "SELECT MAX(id) as max_id FROM students WHERE school_id = ?";
        $max_stmt = $conn->prepare($max_id_query);
        $max_stmt->bind_param('i', $school_id);
        $max_stmt->execute();
        $max_result = $max_stmt->get_result();
        $max_row = $max_result->fetch_assoc();
        $next_id = ($max_row['max_id'] ?? 0) + 1;
        $max_stmt->close();
        
        // Generate registration number format: YYYY/001 (padded with leading zeros)
        $reg_number = $current_year . '/' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        
        // Insert new student with registration number
        $sql = "INSERT INTO students (school_id, first_name, last_name, department_id, class_id, gender, dob, parent_name, parent_phone, parent_email, address, reg_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 
        $stmt = $conn->prepare($sql);
        ?>
        // hano hazajya php mailer
        
        <?php
        // Log the SQL query for debugging
        error_log("SQL Query: " . $sql);
        error_log("Before bind_param: " . $conn->error);
        
        // Bind parameters - mysqli will handle NULL values correctly
        $stmt->bind_param('issiisssssss', $school_id, $first_name, $last_name, $department_id, $class_id, $gender, $dob, $parent_name, $parent_phone, $parent_email, $address, $reg_number);
        
        // Log any errors after bind_param
        error_log("After bind_param: " . $stmt->error);
        
        // Execute the statement and log any errors
        $execute_result = $stmt->execute();
        error_log("Execute result: " . ($execute_result ? 'success' : 'failed'));
        
        if ($execute_result) {
            // Get the inserted student ID
            $student_id = $stmt->insert_id;
            error_log("Student added successfully with ID: " . $student_id . " and registration number: " . $reg_number);
            
            // Get class name for email
            $class_name = '';
            if ($class_id) {
                $class_query = "SELECT name FROM classes WHERE id = ?";
                $class_stmt = $conn->prepare($class_query);
                $class_stmt->bind_param('i', $class_id);
                $class_stmt->execute();
                $class_result = $class_stmt->get_result();
                if ($class_row = $class_result->fetch_assoc()) {
                    $class_name = $class_row['name'];
                }
                $class_stmt->close();
            }

            // Get department name for email
            $department_name = '';
            if ($department_id) {
                $dept_query = "SELECT name FROM departments WHERE dep_id = ?";
                $dept_stmt = $conn->prepare($dept_query);
                $dept_stmt->bind_param('i', $department_id);
                $dept_stmt->execute();
                $dept_result = $dept_stmt->get_result();
                if ($dept_row = $dept_result->fetch_assoc()) {
                    $department_name = $dept_row['name'];
                }
                $dept_stmt->close();
            }

            // Get school information for email
            $school_query = "SELECT name, email, phone FROM schools WHERE id = ?";
            $school_stmt = $conn->prepare($school_query);
            $school_stmt->bind_param('i', $school_id);
            $school_stmt->execute();
            $school_result = $school_stmt->get_result();
            $school_info = $school_result->fetch_assoc();
            $school_stmt->close();

            // Prepare student data for email
            $student_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'reg_number' => $reg_number,
                'class_name' => $class_name,
                'department_name' => $department_name
            ];

            // Send registration email if parent email is provided
            if (!empty($parent_email)) {
                $email_sent = sendStudentRegistrationEmail($parent_email, $parent_name, $student_data, $school_info);
                if ($email_sent) {
                    $_SESSION['student_success'] .= ' Registration confirmation email sent.';
                } else {
                    $_SESSION['student_success'] .= ' However, failed to send registration email.';
                }
            }
            
            // Set response for AJAX requests
            $response['success'] = true;
            $response['message'] = 'Student added successfully';
            $response['reg_number'] = $reg_number;
        } else {
            $error_message = 'Failed to add student: ' . $stmt->error;
            $_SESSION['student_error'] = $error_message;
            error_log("SQL Error: " . $error_message);
            
            // Set response for AJAX requests
            $response['success'] = false;
            $response['message'] = $error_message;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $_SESSION['student_error'] = 'System error: ' . $e->getMessage();
    }
    
    // If this is an AJAX request, return JSON response
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // For regular form submissions, redirect to the specified page
        $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'students.php';
        header('Location: ' . $redirect_to);
        exit;
    }
} else {
    // Not a POST request, redirect to student form
    header('Location: add_student_form.php');
    exit;
}