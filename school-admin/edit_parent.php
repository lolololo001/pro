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

// Check if parent ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['parent_error'] = 'Parent ID is required.';
    header('Location: parents.php');
    exit;
}

$parent_id = intval($_GET['id']);
$parent = null;

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error = 'Parent name is required.';
    } elseif (empty($email)) {
        $error = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists for another parent in this school
        try {
            $stmt = $conn->prepare('SELECT id FROM parents WHERE email = ? AND school_id = ? AND id != ?');
            $stmt->bind_param('sii', $email, $school_id, $parent_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Another parent with this email already exists.';
            } else {
                // Update parent information
                $stmt = $conn->prepare('UPDATE parents SET name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND school_id = ?');
                $stmt->bind_param('ssssii', $name, $email, $phone, $address, $parent_id, $school_id);
                
                if ($stmt->execute()) {
                    $_SESSION['parent_success'] = 'Parent information has been updated successfully.';
                    header('Location: parents.php');
                    exit;
                } else {
                    $error = 'Failed to update parent information: ' . $stmt->error;
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch parent information
try {
    $stmt = $conn->prepare('SELECT * FROM parents WHERE id = ? AND school_id = ?');
    $stmt->bind_param('ii', $parent_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['parent_error'] = 'Parent not found.';
        header('Location: parents.php');
        exit;
    }
    
    $parent = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['parent_error'] = 'Database error: ' . $e->getMessage();
    header('Location: parents.php');
    exit;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Parent - School Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .edit-form {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
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
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Sidebar (Include your sidebar code here) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">SchoolComm<span>.</span></a>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h3>School Admin</h3>
                <p>Administrator</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-heading">Main</div>
            <div class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php"><span>Dashboard</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-user-graduate"></i>
                <a href="students.php"><span>Students</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-chalkboard-teacher"></i>
                <a href="teachers.php"><span>Teachers</span></a>
            </div>
            <div class="menu-item active">
                <i class="fas fa-users"></i>
                <a href="parents.php"><span>Parents</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-image"></i>
                <a href="logo.php"><span>School Logo</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <a href="bursars.php"><span>Bursars</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-school"></i>
                <a href="classes.php"><span>Classes</span></a>
            </div>
            
            <div class="menu-heading">Settings</div>
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <a href="settings.php"><span>Settings</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <a href="../logout.php"><span>Logout</span></a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Edit Parent</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="parents.php">Parents</a>
                <span>/</span>
                <span>Edit</span>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Edit Parent Form -->
        <div class="edit-form">
            <h2>Edit Parent Information</h2>
            <form action="edit_parent.php?id=<?php echo $parent_id; ?>" method="post">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($parent['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($parent['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($parent['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($parent['address'] ?? ''); ?></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <a href="parents.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Update Parent</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>