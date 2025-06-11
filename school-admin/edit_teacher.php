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
    // If AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authorized']);
        exit;
    } else {
        header('Location: ../login.php');
        exit;
    }
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("Error: School ID not found in session. Please log in again.");
}

// Check if teacher ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // If AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Teacher ID is required']);
        exit;
    } else {
        $_SESSION['teacher_error'] = 'Teacher ID is required.';
        header('Location: teachers.php');
        exit;
    }
}

$teacher_id = intval($_GET['id']);
$teacher = null;

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Validate inputs
    $teacher_name = trim($_POST['name'] ?? $_POST['teacher_name'] ?? '');
    $teacher_email = trim($_POST['email'] ?? $_POST['teacher_email'] ?? '');
    $teacher_phone = trim($_POST['phone'] ?? $_POST['teacher_phone'] ?? '');
    $teacher_subject = trim($_POST['subject'] ?? $_POST['teacher_subject'] ?? '');
    $teacher_qualification = trim($_POST['qualification'] ?? $_POST['teacher_qualification'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    
    $errors = [];
    
    if (empty($teacher_name)) {
        $errors[] = 'Teacher name is required.';
    }
    
    if (empty($teacher_email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($teacher_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($teacher_phone)) {
        $errors[] = 'Phone number is required.';
    }
    
    if (count($errors) > 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        } else {
            $_SESSION['teacher_error'] = implode('<br>', $errors);
        }
    } else {
        try {
            // Check if email already exists for another teacher
            $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? AND school_id = ? AND id != ?");
            $stmt->bind_param('sii', $teacher_email, $school_id, $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_msg = 'A teacher with this email already exists.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'errors' => [$error_msg]]);
                    exit;
                } else {
                    $_SESSION['teacher_error'] = $error_msg;
                }
            } else {
                // Update teacher
                $stmt = $conn->prepare("UPDATE teachers SET name = ?, email = ?, phone = ?, subject = ?, qualification = ?, department_id = ? WHERE id = ? AND school_id = ?");
                $stmt->bind_param('sssssiii', $teacher_name, $teacher_email, $teacher_phone, $teacher_subject, $teacher_qualification, $department_id, $teacher_id, $school_id);
                
                if ($stmt->execute()) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Teacher updated successfully!']);
                        exit;
                    } else {
                        $_SESSION['teacher_success'] = 'Teacher updated successfully!';
                        header('Location: teachers.php');
                        exit;
                    }
                } else {
                    $error_msg = 'Failed to update teacher: ' . $conn->error;
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'errors' => [$error_msg]]);
                        exit;
                    } else {
                        $_SESSION['teacher_error'] = $error_msg;
                    }
                }
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error_msg = 'System error: ' . $e->getMessage();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => [$error_msg]]);
                exit;
            } else {
                $_SESSION['teacher_error'] = $error_msg;
            }
        }
    }
}

// Get teacher data
try {
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $teacher_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['teacher_error'] = 'Teacher not found.';
        header('Location: teachers.php');
        exit;
    }
    
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $_SESSION['teacher_error'] = 'System error: ' . $e->getMessage();
    header('Location: teachers.php');
    exit;
}

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .teacher-form {
            background-color: white;
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-submit:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-cancel {
            background-color: #757575;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
        }
        
        .btn-cancel:hover {
            background-color: #616161;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
    </style>
</head>
<body>
<header>
    <div class="school-brand">
        <?php if (!empty($school_info['logo'])): ?>
            <img src="<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" class="school-logo">
        <?php else: ?>
            <div class="school-logo">
                <i class="fas fa-school"></i>
            </div>
        <?php endif; ?>
        <div>
            <div class="school-name"><?php echo htmlspecialchars($school_info['name'] ?? 'School Admin'); ?></div>
            <div style="font-size: 0.9rem;">Admin Dashboard</div>
        </div>
    </div>
    <div class="user-info">
        <div class="user-avatar"><?php echo substr($_SESSION['school_admin_name'] ?? 'A', 0, 1); ?></div>
        <div>
            <div style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['school_admin_name'] ?? 'Admin'); ?></div>
            <div style="font-size: 0.8rem;">School Administrator</div>
        </div>
    </div>
</header>

<nav>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
        <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
        <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
        <li><a href="departments.php"><i class="fas fa-building"></i> Departments</a></li>
        <li><a href="parents.php"><i class="fas fa-users"></i> Parents</a></li>
        <li><a href="logo.php"><i class="fas fa-image"></i> Logo</a></li>
        <li><a href="bursars.php"><i class="fas fa-money-bill-wave"></i> Bursars</a></li>
        <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<main>
    <h1 class="page-title"><i class="fas fa-edit"></i> Edit Teacher</h1>
    
    <?php if (isset($_SESSION['teacher_error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['teacher_error']; 
            unset($_SESSION['teacher_error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="teacher-form">
        <h2 class="form-title">Edit Teacher Information</h2>
        <form action="edit_teacher.php?id=<?php echo $teacher_id; ?>" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="teacher_name">Full Name*</label>
                    <input type="text" id="teacher_name" name="teacher_name" class="form-control" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="teacher_email">Email Address*</label>
                    <input type="email" id="teacher_email" name="teacher_email" class="form-control" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="teacher_phone">Phone Number*</label>
                    <input type="tel" id="teacher_phone" name="teacher_phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="teacher_subject">Subject*</label>
                    <input type="text" id="teacher_subject" name="teacher_subject" class="form-control" value="<?php echo htmlspecialchars($teacher['subject'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department <span class="required">*</span></label>
                    <select id="department_id" name="department_id" class="form-control" required>
                        <option value="">Select Department</option>
                        <?php
                        // Get all departments for this school
                        try {
                            $dept_stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
                            $dept_stmt->bind_param('i', $school_id);
                            $dept_stmt->execute();
                            $dept_result = $dept_stmt->get_result();
                            while ($dept_row = $dept_result->fetch_assoc()) {
                                $selected = ($dept_row['dep_id'] == $teacher['department_id']) ? 'selected' : '';
                                echo '<option value="' . $dept_row['dep_id'] . '" ' . $selected . '>' . htmlspecialchars($dept_row['department_name']) . '</option>';
                            }
                            $dept_stmt->close();
                        } catch (Exception $e) {
                            error_log("Error fetching departments: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="teacher_qualification">Qualification</label>
                    <input type="text" id="teacher_qualification" name="teacher_qualification" class="form-control" value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>">
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <a href="teachers.php" class="btn-cancel"><i class="fas fa-times-circle"></i> Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Teacher</button>
 