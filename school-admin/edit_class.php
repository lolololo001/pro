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

// Check if class ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: classes.php');
    exit;
}

$class_id = intval($_GET['id']);

// Get database connection
$conn = getDbConnection();

// Get class details
$class_data = null;
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ? AND school_id = ?");
$stmt->bind_param('ii', $class_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['class_error'] = 'Class not found!';
    header('Location: classes.php');
    exit;
}

$class_data = $result->fetch_assoc();
$stmt->close();

// Get all teachers for dropdown
$teachers = [];
$stmt = $conn->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $class_name = trim($_POST['class_name'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
    
    
    if (empty($class_name) || empty($grade_level)) {
        $_SESSION['class_error'] = 'Class name and grade level are required fields.';
        header("Location: edit_class.php?id=$class_id");
        exit;
    }
    
    try {
        // Check if another class with the same name and grade level exists (excluding current class)
        $stmt = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND grade_level = ? AND school_id = ? AND id != ?");
        $stmt->bind_param('ssii', $class_name, $grade_level, $school_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['class_error'] = 'Another class with this name and grade level already exists.';
            header("Location: edit_class.php?id=$class_id");
            exit;
        }
        
        // Update class
        if ($teacher_id) {
            $stmt = $conn->prepare("UPDATE classes SET class_name = ?, grade_level = ?, teacher_id = ? WHERE id = ? AND school_id = ?");
            $stmt->bind_param('ssiii', $class_name, $grade_level, $teacher_id, $class_id, $school_id);
        } else {
            $stmt = $conn->prepare("UPDATE classes SET class_name = ?, grade_level = ?, teacher_id = NULL WHERE id = ? AND school_id = ?");
            $stmt->bind_param('ssii', $class_name, $grade_level, $class_id, $school_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['class_success'] = 'Class updated successfully!';
            header('Location: classes.php');
            exit;
        } else {
            $_SESSION['class_error'] = 'Failed to update class: ' . $conn->error;
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['class_error'] = 'System error: ' . $e->getMessage();
    }
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
    <title>Edit Class - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .class-form {
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
            background-color: var(--gray-color);
            color: var(--text-color);
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
            background-color: var(--gray-dark);
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
        <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
        <li><a href="classes.php" class="active"><i class="fas fa-school"></i> Classes</a></li>
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
    <h1 class="page-title"><i class="fas fa-edit"></i> Edit Class</h1>
    
    <?php if (isset($_SESSION['class_error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['class_error']; 
            unset($_SESSION['class_error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="class-form">
        <h2 class="form-title">Update Class Information</h2>
        <form action="edit_class.php?id=<?php echo $class_id; ?>" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="class_name">Class Name*</label>
                    <input type="text" id="class_name" name="class_name" class="form-control" value="<?php echo htmlspecialchars($class_data['class_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="grade_level">Grade Level*</label>
                    <input type="text" id="grade_level" name="grade_level" class="form-control" value="<?php echo htmlspecialchars($class_data['grade_level']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="teacher_id">Class Teacher</label>
                    <select id="teacher_id" name="teacher_id" class="form-control">
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($class_data['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
        
            
            <div style="margin-top: 1.5rem;">
                <a href="classes.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Class</button>
            </div>
        </form>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_info['name'] ?? 'School Admin System'); ?>. All rights reserved.</p>
</footer>
</body>
</html>