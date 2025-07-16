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

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $teacher_email = trim($_POST['teacher_email'] ?? '');
    $teacher_phone = trim($_POST['teacher_phone'] ?? '');
    $teacher_subject = trim($_POST['teacher_subject'] ?? '');
    $teacher_qualification = trim($_POST['teacher_qualification'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    
    if (empty($teacher_name) || empty($teacher_email) || empty($teacher_phone) || empty($department_id)) {
        $_SESSION['teacher_error'] = 'All required fields must be filled.';
        header('Location: teachers.php');
        exit;
    }
    
    // Validate email format
    if (!filter_var($teacher_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['teacher_error'] = 'Please enter a valid email address.';
        header('Location: teachers.php');
        exit;
    }
    
    try {
        // Get database connection
        $conn = getDbConnection();
        
        // Check if teachers table exists and add login fields if needed
        $result = $conn->query("SHOW TABLES LIKE 'teachers'");
        if ($result->num_rows == 0) {
            // Create teachers table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS teachers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                subject VARCHAR(50),
                qualification VARCHAR(255),
                department_id INT,
                username VARCHAR(50) UNIQUE,
                password VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL
            )");
        } else {
            // Check if username and password columns exist
            $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'username'");
            if ($result->num_rows == 0) {
                $conn->query("ALTER TABLE teachers ADD COLUMN username VARCHAR(50) UNIQUE");
            }
            
            $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'password'");
            if ($result->num_rows == 0) {
                $conn->query("ALTER TABLE teachers ADD COLUMN password VARCHAR(255)");
            }
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? AND school_id = ?");
        $stmt->bind_param('si', $teacher_email, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['teacher_error'] = 'A teacher with this email already exists.';
            header('Location: teachers.php');
            exit;
        }
        
        // Generate username and password
        $username = generateTeacherUsername($teacher_name, $conn);
        $password = generateSecurePassword();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Get school info for email
        $school_stmt = $conn->prepare("SELECT name, phone, email FROM schools WHERE id = ?");
        $school_stmt->bind_param('i', $school_id);
        $school_stmt->execute();
        $school_info = $school_stmt->get_result()->fetch_assoc();
        $school_stmt->close();
        
        // Get department name
        $dept_stmt = $conn->prepare("SELECT department_name FROM departments WHERE dep_id = ?");
        $dept_stmt->bind_param('i', $department_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        $department_name = $dept_result->fetch_assoc()['department_name'] ?? '';
        $dept_stmt->close();
        
        // Insert new teacher
        $stmt = $conn->prepare("INSERT INTO teachers (school_id, name, email, phone, subject, qualification, department_id, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssss', $school_id, $teacher_name, $teacher_email, $teacher_phone, $teacher_subject, $teacher_qualification, $department_id, $username, $hashed_password);
        
        if ($stmt->execute()) {
            // Send credentials email
            $email_sent = sendTeacherCredentialsEmail($teacher_email, $teacher_name, $username, $password, $school_info, $department_name);
            
            if ($email_sent) {
                $_SESSION['teacher_success'] = 'Teacher added successfully! Login credentials have been sent to their email.';
            } else {
                $_SESSION['teacher_success'] = 'Teacher added successfully! However, there was an issue sending the credentials email. Username: ' . $username . ', Password: ' . $password;
            }
            
            header('Location: teachers.php');
            exit;
        } else {
            $_SESSION['teacher_error'] = 'Failed to add teacher: ' . $conn->error;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $_SESSION['teacher_error'] = 'System error: ' . $e->getMessage();
    }
    
    // Redirect back to teachers page
    header('Location: teachers.php');
    exit;
} else {
    // Not a POST request, redirect to teachers page
    header('Location: teachers.php');
    exit;
}

// Function to generate unique username
function generateTeacherUsername($teacher_name, $conn) {
    // Clean the name and create base username
    $name_parts = explode(' ', trim($teacher_name));
    $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0]));
    $last_name = isset($name_parts[1]) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[1])) : '';
    
    $base_username = $first_name . ($last_name ? '.' . $last_name : '');
    
    // Check if username exists and generate unique one
    $username = $base_username;
    $counter = 1;
    
    do {
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $username = $base_username . $counter;
            $counter++;
        } else {
            break;
        }
        $stmt->close();
    } while (true);
    
    return $username;
}

// Function to generate secure password
function generateSecurePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    // Ensure at least one character from each category
    $password .= 'abcdefghijklmnopqrstuvwxyz'[rand(0, 25)]; // lowercase
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[rand(0, 25)]; // uppercase
    $password .= '0123456789'[rand(0, 9)]; // digit
    $password .= '!@#$%^&*'[rand(0, 7)]; // special character
    
    // Fill the rest randomly
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

// Function to send teacher credentials email
function sendTeacherCredentialsEmail($teacher_email, $teacher_name, $username, $password, $school_info, $department_name) {
    try {
        // Load Composer's autoloader
        require_once __DIR__ . '/../vendor/autoload.php';

        // Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'schoolcomm001@gmail.com';
        $mail->Password = 'nuos orzj keap bszp';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Recipients
        $mail->setFrom('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');
        $mail->addAddress($teacher_email, $teacher_name);
        $mail->addReplyTo('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');

        // Create HTML body
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #00704a;'>Welcome to " . htmlspecialchars($school_info['name'] ?? 'our school') . "!</h2>
                    <p><strong>Dear {$teacher_name},</strong></p>
                    <p>Welcome to " . htmlspecialchars($school_info['name'] ?? 'our school') . "! Your teacher account has been successfully created.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #00704a;'>
                        <h3 style='color: #00704a; margin-top: 0;'>Your Login Credentials:</h3>
                        <p><strong>Username:</strong> <span style='background-color: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$username}</span></p>
                        <p><strong>Password:</strong> <span style='background-color: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$password}</span></p>
                        " . (!empty($department_name) ? "<p><strong>Department:</strong> {$department_name}</p>" : "") . "
                    </div>
                    
                    <div style='background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;'>
                        <h4 style='color: #856404; margin-top: 0;'>Important Information:</h4>
                        <ul style='color: #856404; margin: 10px 0; padding-left: 20px;'>
                            <li>Please change your password after your first login for security</li>
                            <li>Keep your credentials safe and don't share them with others</li>
                            <li>You can access your account at: <a href='http://localhost/pro/login.php' style='color: #00704a;'>SchoolComm Login Portal</a></li>
                        </ul>
                    </div>
                    
                    <p>You can now:</p>
                    <ul>
                        <li>Access your teacher dashboard</li>
                        <li>View and manage your classes</li>
                        <li>Record student attendance</li>
                        <li>Update student marks</li>
                        <li>Communicate with parents</li>
                    </ul>
                    
                    <p>For any technical support, please contact us at:</p>
                    <p>Phone: " . htmlspecialchars($school_info['phone'] ?? 'N/A') . "<br>
                       Email: " . htmlspecialchars($school_info['email'] ?? 'N/A') . "</p>
                    
                    <p style='margin-top: 30px;'>Best regards,<br>
                    " . htmlspecialchars($school_info['name'] ?? 'School') . " Administration</p>
                </div>
            </body>
            </html>
        ";

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Teacher Account Created - ' . $school_info['name'];
        $mail->Body = $body;
        $mail->AltBody = "Dear {$teacher_name},\n\n" .
            "Welcome to " . ($school_info['name'] ?? 'our school') . "! Your teacher account has been successfully created.\n\n" .
            "Your Login Credentials:\n" .
            "Username: {$username}\n" .
            "Password: {$password}\n" .
            (!empty($department_name) ? "Department: {$department_name}\n" : "") .
            "\nPlease change your password after your first login for security.\n" .
            "You can access your account at: http://localhost/pro/login.php\n\n" .
            "For any technical support, contact us at: Phone: " . ($school_info['phone'] ?? 'N/A') . ", Email: " . ($school_info['email'] ?? 'N/A') . "\n\n" .
            "Best regards,\n" . ($school_info['name'] ?? 'School') . " Administration";

        $mail->send();
        error_log("Teacher credentials email sent successfully to: " . $teacher_email);
        return true;
    } catch (Exception $e) {
        $error_message = "Failed to send teacher credentials email: " . $e->getMessage();
        error_log($error_message);
        return false;
    }
}
?>