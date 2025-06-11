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

// Check if bursar ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['bursar_error'] = 'Bursar ID is required.';
    header('Location: bursars.php');
    exit;
}

$bursar_id = intval($_GET['id']);
$bursar = null;

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $bursar_name = trim($_POST['bursar_name'] ?? '');
    $bursar_email = trim($_POST['bursar_email'] ?? '');
    $bursar_phone = trim($_POST['bursar_phone'] ?? '');
    
    if (empty($bursar_name) || empty($bursar_email) || empty($bursar_phone)) {
        $_SESSION['bursar_error'] = 'All fields are required.';
    } else {
        // Validate email format
        if (!filter_var($bursar_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['bursar_error'] = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email already exists for another bursar
                $stmt = $conn->prepare("SELECT id FROM bursars WHERE email = ? AND school_id = ? AND id != ?");
                $stmt->bind_param('sii', $bursar_email, $school_id, $bursar_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['bursar_error'] = 'A bursar with this email already exists.';
                } else {
                    // Update bursar
                    $stmt = $conn->prepare("UPDATE bursars SET name = ?, email = ?, phone = ? WHERE id = ? AND school_id = ?");
                    $stmt->bind_param('sssii', $bursar_name, $bursar_email, $bursar_phone, $bursar_id, $school_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['bursar_success'] = 'Bursar updated successfully!';
                        header('Location: bursars.php');
                        exit;
                    } else {
                        $_SESSION['bursar_error'] = 'Failed to update bursar: ' . $conn->error;
                    }
                }
                
                $stmt->close();
                
            } catch (Exception $e) {
                $_SESSION['bursar_error'] = 'System error: ' . $e->getMessage();
            }
        }
    }
}

// Get bursar data
try {
    $stmt = $conn->prepare("SELECT * FROM bursars WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $bursar_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['bursar_error'] = 'Bursar not found.';
        header('Location: bursars.php');
        exit;
    }
    
    $bursar = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $_SESSION['bursar_error'] = 'System error: ' . $e->getMessage();
    header('Location: bursars.php');
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
    <title>Edit Bursar - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .bursar-form {
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
        <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
        <li><a href="classes.php"><i class="fas fa-school"></i> Classes</a></li>
        <li><a href="departments.php"><i class="fas fa-building"></i> Departments</a></li>
        <li><a href="parents.php"><i class="fas fa-users"></i> Parents</a></li>
        <li><a href="logo.php"><i class="fas fa-image"></i> Logo</a></li>
        <li><a href="bursars.php" class="active"><i class="fas fa-money-bill-wave"></i> Bursars</a></li>
        <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<main>
    <h1 class="page-title"><i class="fas fa-edit"></i> Edit Bursar</h1>
    
    <?php if (isset($_SESSION['bursar_error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['bursar_error']; 
            unset($_SESSION['bursar_error']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="bursar-form">
        <h2 class="form-title">Edit Bursar Information</h2>
        <form action="edit_bursar.php?id=<?php echo $bursar_id; ?>" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="bursar_name">Full Name*</label>
                    <input type="text" id="bursar_name" name="bursar_name" class="form-control" value="<?php echo htmlspecialchars($bursar['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bursar_email">Email Address*</label>
                    <input type="email" id="bursar_email" name="bursar_email" class="form-control" value="<?php echo htmlspecialchars($bursar['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="bursar_phone">Phone Number*</label>
                    <input type="tel" id="bursar_phone" name="bursar_phone" class="form-control" value="<?php echo htmlspecialchars($bursar['phone']); ?>" required>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <a href="bursars.php" class="btn-cancel"><i class="fas fa-times-circle"></i> Cancel</a>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Bursar</button>
            </div>
        </form>
    </div>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_info['name'] ?? 'School Admin System'); ?>. All rights reserved.</p>
</footer>
</body>
</html>