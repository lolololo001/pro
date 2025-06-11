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

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['student_error'] = 'Student ID is required.';
    header('Location: students.php');
    exit;
}

$student_id = intval($_GET['id']);
$student = null;

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $admission_number = trim($_POST['admission_number'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $admission_date = trim($_POST['admission_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'active');
    // Note: reg_number is not included in POST data as it's auto-generated
    
    if (empty($first_name) || empty($last_name) || empty($admission_number) || empty($class) || empty($parent_name) || empty($parent_phone)) {
        $_SESSION['student_error'] = 'All required fields must be filled.';
    } else {
        // Validate email format if provided
        if (!empty($parent_email) && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['student_error'] = 'Please enter a valid parent email address.';
        } else {
            try {
                // Check if admission number already exists for another student
                $stmt = $conn->prepare("SELECT id FROM students WHERE admission_number = ? AND school_id = ? AND id != ?");
                $stmt->bind_param('sii', $admission_number, $school_id, $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['student_error'] = 'A student with this admission number already exists.';
                } else {
                    // Check if student has a registration number
                    $check_reg = $conn->prepare("SELECT reg_number FROM students WHERE id = ? AND school_id = ?");
                    $check_reg->bind_param('ii', $student_id, $school_id);
                    $check_reg->execute();
                    $reg_result = $check_reg->get_result();
                    $student_data = $reg_result->fetch_assoc();
                    $check_reg->close();
                    
                    // If reg_number is empty or NULL, generate one
                    if (empty($student_data['reg_number'])) {
                        $current_year = date('Y');
                        $reg_number = $current_year . '/' . str_pad($student_id, 3, '0', STR_PAD_LEFT);
                        
                        // Update student with all fields including the new reg_number
                        $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, admission_number = ?, class = ?, department_id = ?, gender = ?, dob = ?, parent_name = ?, parent_phone = ?, parent_email = ?, address = ?, admission_date = ?, status = ?, reg_number = ? WHERE id = ? AND school_id = ?");
                        $stmt->bind_param('ssssississsssssii', $first_name, $last_name, $admission_number, $class, $department_id, $gender, $dob, $parent_name, $parent_phone, $parent_email, $address, $admission_date, $status, $reg_number, $student_id, $school_id);
                    } else {
                        // Update student - preserve the existing reg_number field
                        $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, admission_number = ?, class = ?, department_id = ?, gender = ?, dob = ?, parent_name = ?, parent_phone = ?, parent_email = ?, address = ?, admission_date = ?, status = ? WHERE id = ? AND school_id = ?");
                        $stmt->bind_param('ssssississsssii', $first_name, $last_name, $admission_number, $class, $department_id, $gender, $dob, $parent_name, $parent_phone, $parent_email, $address, $admission_date, $status, $student_id, $school_id);
                    }
                    
                    if ($stmt->execute()) {
                        $_SESSION['student_success'] = 'Student updated successfully!';
                        header('Location: students.php');
                        exit;
                    } else {
                        $_SESSION['student_error'] = 'Failed to update student: ' . $conn->error;
                    }
                }
                
                $stmt->close();
                
            } catch (Exception $e) {
                $_SESSION['student_error'] = 'System error: ' . $e->getMessage();
            }
        }
    }
}

// Get student data
try {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $student_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['student_error'] = 'Student not found.';
        header('Location: students.php');
        exit;
    }
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get all departments for this school
    $departments = [];
    $stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    $_SESSION['student_error'] = 'System error: ' . $e->getMessage();
    header('Location: students.php');
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
    <title>Edit Student - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .student-form {
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
        <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
        <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
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
    <h1 class="page-title"><i class="fas fa-edit"></i> Edit Student</h1>
    
    <?php if (isset($_SESSION['student_error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['student_error']; 
            unset($_SESSION['student_error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="student-form">
        <h2 class="form-title">Edit Student Information</h2>
        <form action="edit_student.php?id=<?php echo $student_id; ?>" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name*</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admission_number">Admission Number*</label>
                    <input type="text" id="admission_number" name="admission_number" class="form-control" value="<?php echo htmlspecialchars($student['admission_number']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Class*</label>
                    <input type="text" id="class" name="class" class="form-control" value="<?php echo htmlspecialchars($student['class']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['dep_id']; ?>" <?php echo ($student['department_id'] == $department['dep_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($student['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($student['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="parent_name">Parent/Guardian Name*</label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($student['parent_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="parent_phone">Parent/Guardian Phone*</label>
                    <input type="tel" id="parent_phone" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="parent_email">Parent/Guardian Email</label>
                    <input type="email" id="parent_email" name="parent_email" class="form-control" value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="admission_date">Admission Date</label>
                    <input type="date" id="admission_date" name="admission_date" class="form-control" value="<?php echo htmlspecialchars($student['admission_date'] ?? date('Y-m-d')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo ($student['status'] == 'active' || empty($student['status'])) ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($student['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo ($student['status'] == 'graduated') ? 'selected' : ''; ?>>Graduated</option>
                        <option value="shifted" <?php echo ($student['status'] == 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <a href="students.php" class="btn-cancel"><i class="fas fa-times-circle"></i> Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Student</button>
            </div>
        </form>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_info['name'] ?? 'School Admin System'); ?>. All rights reserved.</p>
</footer>
</body>
</html>