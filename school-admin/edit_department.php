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

// Check if department ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // If AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Department ID is required']);
        exit;
    } else {
        $_SESSION['department_error'] = 'Department ID is required.';
        header('Location: departments.php');
        exit;
    }
}

$department_id = intval($_GET['id']);

// Get database connection
$conn = getDbConnection();

// Get department details
$department_data = null;
$stmt = $conn->prepare("SELECT * FROM departments WHERE dep_id = ? AND school_id = ?");
$stmt->bind_param('ii', $department_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['department_error'] = 'Department not found!';
    header('Location: departments.php');
    exit;
}

$department_data = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Validate inputs
    $department_name = trim($_POST['department_name'] ?? '');
    
    if (empty($department_name)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Department name is required.']]);
            exit;
        } else {
            $_SESSION['department_error'] = 'Department name is required.';
            header("Location: edit_department.php?id=$department_id");
            exit;
        }
    }
    
    try {
        // Check if another department with the same name exists (excluding current department)
        $stmt = $conn->prepare("SELECT dep_id FROM departments WHERE department_name = ? AND school_id = ? AND dep_id != ?");
        $stmt->bind_param('sii', $department_name, $school_id, $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_msg = 'Another department with this name already exists.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => [$error_msg]]);
                exit;
            } else {
                $_SESSION['department_error'] = $error_msg;
                header("Location: edit_department.php?id=$department_id");
                exit;
            }
        }
        
        // Update department
        $stmt = $conn->prepare("UPDATE departments SET department_name = ? WHERE dep_id = ? AND school_id = ?");
        $stmt->bind_param('sii', $department_name, $department_id, $school_id);
        
        if ($stmt->execute()) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Department updated successfully!']);
                exit;
            } else {
                $_SESSION['department_success'] = 'Department updated successfully!';
                header('Location: departments.php');
                exit;
            }
        } else {
            $error_msg = 'Failed to update department: ' . $conn->error;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => [$error_msg]]);
                exit;
            } else {
                $_SESSION['department_error'] = $error_msg;
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
            $_SESSION['department_error'] = $error_msg;
        }
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
    <title>Edit Department - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .department-form {
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
        <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
        <li><a href="departments.php" class="active"><i class="fas fa-building"></i> Departments</a></li>
        <li><a href="parents.php"><i class="fas fa-users"></i> Parents</a></li>
        <li><a href="logo.php"><i class="fas fa-image"></i> Logo</a></li>
        <li><a href="bursars.php"><i class="fas fa-money-bill-wave"></i> Bursars</a></li>
        <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<main>
    <h1 class="page-title"><i class="fas fa-edit"></i> Edit Department</h1>
    
    <?php if (isset($_SESSION['department_error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['department_error']; 
            unset($_SESSION['department_error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="department-form">
        <h2 class="form-title">Update Department Information</h2>
        <form action="edit_department.php?id=<?php echo $department_id; ?>" method="post">
            <div class="form-group">
                <label for="department_name">Department Name*</label>
                <input type="text" id="department_name" name="department_name" class="form-control" value="<?php echo htmlspecialchars($department_data['department_name']); ?>" required>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <a href="departments.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Department</button>
            </div>
        </form>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_info['name'] ?? 'School Admin System'); ?>. All rights reserved.</p>
</footer>
</body>
</html>