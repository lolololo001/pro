<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Require autoloader
require '../vendor/autoload.php';

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "schoolcomm";

try {
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Initialize variables
$error = '';
$success = '';
$school_id = $_SESSION['school_admin_school_id'] ?? 0;

// Get departments for the school
$departments = [];
try {
    $dept_stmt = $conn->prepare("SELECT dep_id, department_name FROM departments WHERE school_id = ?");
    $dept_stmt->bind_param("i", $school_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
    $dept_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Get classes for the school
$classes = [];
try {
    $class_stmt = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ?");
    $class_stmt->bind_param("i", $school_id);
    $class_stmt->execute();
    $class_result = $class_stmt->get_result();
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
    $class_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

// Get school information
$school_name = '';
try {
    $stmt = $conn->prepare("SELECT name FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $school_name = $row['name'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching school info: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($parent_name) || 
        empty($parent_phone) || empty($department_id) || empty($class_id)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Generate registration number (S-YYYY/XXX format where S is school_id)
            $year = date('Y');
            
            // Get the highest registration number for this specific school and year
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(reg_number, '/', -1) AS UNSIGNED)) as max_count 
                                  FROM students 
                                  WHERE school_id = ? 
                                  AND reg_number LIKE ?");
            
            $reg_prefix = $school_id . '-' . $year;
            $reg_pattern = $reg_prefix . '/%';
            $stmt->bind_param("is", $school_id, $reg_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            // If no existing registration numbers found, start from 1, otherwise increment the highest number
            $count = ($row['max_count'] !== null && $row['max_count'] > 0) ? intval($row['max_count']) + 1 : 1;
            
            // Create registration number in format: SCHOOLID-YYYY/XXX
            $reg_number = $reg_prefix . '/' . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // Double-check that this registration number doesn't exist
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE school_id = ? AND reg_number = ?");
            $check_stmt->bind_param("is", $school_id, $reg_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            
            if ($check_row['count'] > 0) {
                throw new Exception("Failed to generate unique registration number. Please try again.");
            }

            // Insert student data with all fields
            $stmt = $conn->prepare("INSERT INTO students (
                school_id, reg_number, first_name, last_name, dob, gender, 
                department_id, class_id, parent_name, parent_email, 
                parent_phone, address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("isssssiissss", 
                $school_id, $reg_number, $first_name, $last_name, $dob, 
                $gender, $department_id, $class_id, $parent_name, 
                $parent_email, $parent_phone, $address
            );                if ($stmt->execute()) {
                    // Get the class name for the email
                    $class_name = '';
                    foreach ($classes as $class) {
                        if ($class['id'] == $class_id) {
                            $class_name = $class['class_name'];
                            break;
                        }
                    }
                    // Get the department name for the email
                    $department_name = '';
                    foreach ($departments as $department) {
                        if ($department['dep_id'] == $department_id) {
                            $department_name = $department['department_name'];
                            break;
                        }
                    }

                    // Send email if parent email is provided
                    if (!empty($parent_email)) {
                        $mail = new PHPMailer(true);
                        
                        try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'schoolcomm001@gmail.com'; // Your Gmail address
                        $mail->Password = 'nuos orzj keap bszp'; // Your app password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port = 465;

                        // Recipients
                        $mail->setFrom('schoolcomm001@gmail.com', $school_name);
                        $mail->addAddress($parent_email, $parent_name);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Student Registration Confirmation - ' . $school_name;
                        
                        // Email body
                        $mail->Body = "
                            <html>
                            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                    <h2 style='color: #4a90e2;'>Registration Confirmation</h2>
                                    <p>Dear {$parent_name},</p>
                                    <p>We are pleased to confirm that <strong>{$first_name} {$last_name}</strong> has been successfully registered at {$school_name}.</p>
                                    
                                    <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                        <h3 style='color: #2c3e50; margin-top: 0;'>Registration Details:</h3>
                                        <p><strong>Registration Number:</strong> {$reg_number}</p>
                                        <p><strong>Student Name:</strong> {$first_name} {$last_name}</p>
                                        <p><strong>Class:</strong> {$class_name}</p>
                                         <p><strong>Department:</strong> {$department_name}</p>
                                    </div>
                                    
                                    <p>Please keep this registration number for future reference.</p>
                                    <p>If you have any questions, please don't hesitate to contact us.</p>
                                    
                                    <p style='margin-top: 30px;'>Best regards,<br>{$school_name}</p>
                                </div>
                            </body>
                            </html>
                        ";

                        // Plain text version
                        $mail->AltBody = "
                            Registration Confirmation
                            
                            Dear {$parent_name},
                            
                            We are pleased to confirm that {$first_name} {$last_name} has been successfully registered at {$school_name}.
                            
                            Registration Details:
                            Registration Number: {$reg_number}
                            Student Name: {$first_name} {$last_name}
                            Class: {$class_name}
                            department: {$department_name}
                            Please keep this registration number for future reference.
                            
                            If you have any questions, please don't hesitate to contact us.
                            
                            Best regards,
                            {$school_name}
                        ";

                        $mail->send();
                        $success = "Student registered successfully and confirmation email sent to parent.";
                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $e->getMessage());
                        $success = "Student registered successfully but failed to send confirmation email.";
                    }
                } else {
                    $success = "Student registered successfully.";
                }
            } else {
                throw new Exception("Error registering student");
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "An error occurred during registration. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #00704a;
            --accent-color: #00704a;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --radius-md: 12px;
            --radius-sm: 6px;
            --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
            color: var(--dark-color);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .registration-container {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .registration-card {
            background: var(--light-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .registration-header {
            background: linear-gradient(135deg, var(--primary-color), #009e60);
            color: white;
            padding: 2rem 2rem 1.2rem 2rem;
            text-align: center;
        }
        .registration-header h1 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .registration-header p {
            margin: 0.5rem 0 0;
            font-size: 1.1rem;
            opacity: 0.95;
        }
        .form-section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 2rem 0 1rem 0;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        .registration-form {
            padding: 2rem 2.5rem;
            background: var(--light-color);
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem 2rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
        }
        .required {
            color: var(--danger-color);
            margin-left: 3px;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            background: #fafbfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,112,74,0.08);
            background: #fff;
        }
        .form-control::placeholder {
            color: #b0b0b0;
        }
        select.form-control {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="gray" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        .btn-container {
            text-align: center;
            margin-top: 2.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #009e60);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,112,74,0.10);
            transition: background 0.2s, transform 0.2s;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #005a37, #009e60);
            transform: translateY(-2px);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            font-size: 1rem;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        @media (max-width: 1100px) {
            .registration-card {
                max-width: 99vw;
            }
        }
        @media (max-width: 900px) {
            .registration-form {
                padding: 1.2rem;
            }
            .registration-header {
                padding: 1.2rem 1.2rem 1rem 1.2rem;
            }
        }
        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 700px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1.2rem 0;
            }
            .registration-card {
                max-width: 100vw;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="registration-card">
            <div class="registration-header">
                <h1><i class="fas fa-user-graduate"></i> Student Registration</h1>
                <p>Register a new student and send confirmation to parent/guardian</p>
            </div>
            <div class="registration-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-section-title"><i class="fas fa-info-circle"></i> Student Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="first_name">
                                <i class="fas fa-user"></i> First Name <span class="required">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name" required class="form-control" placeholder="Enter first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">
                                <i class="fas fa-user"></i> Last Name <span class="required">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name" required class="form-control" placeholder="Enter last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="gender">
                                <i class="fas fa-venus-mars"></i> Gender <span class="required">*</span>
                            </label>
                            <select id="gender" name="gender" required class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php if(($_POST['gender'] ?? '')==='Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if(($_POST['gender'] ?? '')==='Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dob">
                                <i class="fas fa-calendar-alt"></i> Date of Birth <span class="required">*</span>
                            </label>
                            <input type="date" id="dob" name="dob" required class="form-control" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="department_id">
                                <i class="fas fa-building"></i> Department <span class="required">*</span>
                            </label>
                            <select id="department_id" name="department_id" required class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dep_id']); ?>" <?php if(($_POST['department_id'] ?? '') == $dept['dep_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="class_id">
                                <i class="fas fa-chalkboard"></i> Class <span class="required">*</span>
                            </label>
                            <select id="class_id" name="class_id" required class="form-control">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php if(($_POST['class_id'] ?? '') == $class['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-section-title"><i class="fas fa-users"></i> Parent/Guardian Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="parent_name">
                                <i class="fas fa-user-tie"></i> Parent/Guardian Name <span class="required">*</span>
                            </label>
                            <input type="text" id="parent_name" name="parent_name" required class="form-control" placeholder="Enter parent/guardian name" value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="parent_email">
                                <i class="fas fa-envelope"></i> Parent/Guardian Email
                            </label>
                            <input type="email" id="parent_email" name="parent_email" class="form-control" placeholder="Enter email address" value="<?php echo htmlspecialchars($_POST['parent_email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="parent_phone">
                                <i class="fas fa-phone"></i> Parent/Guardian Phone <span class="required">*</span>
                            </label>
                            <input type="tel" id="parent_phone" name="parent_phone" required class="form-control" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['parent_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="address">
                                <i class="fas fa-home"></i> Address <span class="required">*</span>
                            </label>
                            <textarea id="address" name="address" required class="form-control" rows="3" placeholder="Enter full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>