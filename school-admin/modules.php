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

// Get database connection
$conn = getDbConnection();

// Check if modules table exists
$result = $conn->query("SHOW TABLES LIKE 'modules'");
if ($result->num_rows == 0) {
    // Create modules table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS modules (
        module_id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        module_name VARCHAR(100) NOT NULL,
        module_code VARCHAR(20) NOT NULL,
        description TEXT,
        department_id INT,
        credits INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL
    )");
    
    // Add unique constraint for module code per school
    $conn->query("ALTER TABLE modules ADD CONSTRAINT unique_module_code_per_school UNIQUE (school_id, module_code)");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $module_id = intval($_GET['id']);
    
    // Delete the module
    $stmt = $conn->prepare("DELETE FROM modules WHERE module_id = ? AND school_id = ?");
    $stmt->bind_param('ii', $module_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['module_success'] = 'Module deleted successfully!';
    } else {
        $_SESSION['module_error'] = 'Failed to delete module: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: modules.php');
    exit;
}

// Process form submission for adding/editing a module
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_name = trim($_POST['module_name'] ?? '');
    $module_code = trim($_POST['module_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $credits = intval($_POST['credits'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $module_id = $_POST['module_id'] ?? null; // For editing
    
    if (empty($module_name) || empty($module_code)) {
        $_SESSION['module_error'] = 'Module name and code are required.';
        header('Location: modules.php');
        exit;
    } else {
        // Check if module code already exists for this school (excluding current module if editing)
        $check_query = "SELECT module_id FROM modules WHERE school_id = ? AND module_code = ?";
        $check_params = [$school_id, $module_code];
        
        if ($module_id) {
            $check_query .= " AND module_id != ?";
            $check_params[] = $module_id;
        }
        
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param(str_repeat('s', count($check_params)), ...$check_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['module_error'] = 'A module with this code already exists.';
            header('Location: modules.php');
            exit;
        } else {
            if ($module_id) {
                // Update existing module
                $stmt = $conn->prepare("UPDATE modules SET module_name = ?, module_code = ?, description = ?, department_id = ?, credits = ?, status = ? WHERE module_id = ? AND school_id = ?");
                $stmt->bind_param('sssissii', $module_name, $module_code, $description, $department_id, $credits, $status, $module_id, $school_id);
            } else {
                // Add new module
                $stmt = $conn->prepare("INSERT INTO modules (school_id, module_name, module_code, description, department_id, credits, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssiis', $school_id, $module_name, $module_code, $description, $department_id, $credits, $status);
            }
            
            if ($stmt->execute()) {
                $_SESSION['module_success'] = $module_id ? 'Module updated successfully!' : 'Module added successfully!';
                header('Location: modules.php');
                exit;
            } else {
                $_SESSION['module_error'] = 'Failed to ' . ($module_id ? 'update' : 'add') . ' module: ' . $conn->error;
                header('Location: modules.php');
                exit;
            }
        }
        $stmt->close();
    }
}

// Get all modules for this school with department information
$modules = [];
try {
    $stmt = $conn->prepare("SELECT m.*, d.department_name 
                           FROM modules m 
                           LEFT JOIN departments d ON m.department_id = d.dep_id 
                           WHERE m.school_id = ? 
                           ORDER BY m.module_name ASC");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching modules: " . $e->getMessage());
}

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
    <title>Manage Modules - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR ?? '#00704a'; ?>;
            --footer-color: <?php echo FOOTER_COLOR ?? '#f8c301'; ?>;
            --accent-color: <?php echo ACCENT_COLOR ?? '#00704a'; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --sidebar-width: 250px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --radius-sm: 4px;
            --radius-md: 8px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.5rem 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .search-icon {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }
        
        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 0.25rem 0;
        }
        
        .search-clear {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .search-clear:hover {
            color: var(--danger-color);
        }
        
        .empty-search-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-search-results i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--light-color);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--light-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .school-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .school-logo, .school-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        
        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .school-logo-placeholder i {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .sidebar-logo span {
            color: var(--footer-color);
        }
        
        .sidebar-user {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
            color: white;
            font-weight: bold;
        }
        
        .user-info h3 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .user-info p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-heading {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }
        
        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .menu-item:hover, .menu-item.active {
            background-color: var(--accent-color);
        }
        
        .menu-item i {
            margin-right: 0.8rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .menu-item a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            flex: 1;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            width: 100vw;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 0.5rem;
            color: #999;
        }
        
        /* Card Styles */
        .card {
            background-color: var(--light-color);
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            width: 100%;
            min-width: 0;
            border: 1px solid rgba(0, 112, 74, 0.1);
        }
        
        .card-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 1.2rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Form Styles */
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
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #f57c00;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: var(--success-color);
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border-color: var(--danger-color);
            color: #c62828;
        }
        
        /* Table Styles */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            table-layout: auto;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(0, 112, 74, 0.05);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            text-decoration: none;
        }
        
        .btn-icon.edit {
            background-color: var(--primary-color);
        }
        
        .btn-icon.delete {
            background-color: var(--danger-color);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .empty-text {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .close {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #f8c301;
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .main-content {
                width: 100vw;
                max-width: 100vw;
                margin-left: 0;
                padding: 1rem;
            }
            .card {
                width: 100vw;
                max-width: 100vw;
            }
            .table-responsive {
                width: 100vw;
                max-width: 100vw;
            }
            .data-table {
                min-width: 700px;
            }
        }
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            .sidebar-header, .sidebar-user, .menu-heading {
                display: none;
            }
            .menu-item {
                padding: 1rem 0;
                justify-content: center;
            }
            .menu-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            .menu-item a span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
        }
        @media (max-width: 768px) {
            .main-content {
                width: 100vw;
                max-width: 100vw;
                margin-left: 0;
                padding: 0.5rem;
            }
            .card {
                width: 100vw;
                max-width: 100vw;
            }
            .table-responsive {
                width: 100vw;
                max-width: 100vw;
            }
            .data-table {
                min-width: 500px;
            }
            .data-table th,
            .data-table td {
                padding: 0.5rem;
                font-size: 0.95rem;
                word-break: break-word;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 480px) {
            .main-content {
                margin-left: 0;
                padding: 0.5rem;
            }
            .sidebar {
                transform: translateX(-100%);
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Manage Modules</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Modules</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['module_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['module_success']; 
                unset($_SESSION['module_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['module_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['module_error']; 
                unset($_SESSION['module_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Module Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus"></i> Add New Module</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="module_name">Module Name <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="module_name" name="module_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="module_code">Module Code <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="module_code" name="module_code" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id" class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['dep_id']; ?>">
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="credits">Credits</label>
                            <input type="number" id="credits" name="credits" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Enter module description..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Module
                    </button>
                </form>
            </div>
        </div>

        <!-- Modules List Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Modules</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportModuleData()" class="btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                        <i class="fas fa-download"></i> Export List
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($modules) > 0): ?>
                    <!-- Search Box -->
                    <div class="search-container" data-table="modules-table">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search modules...">
                            <button type="button" class="search-clear" onclick="clearSearch('modules-table')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table class="data-table" id="modules-table">
                            <thead>
                                <tr>
                                    <th>Module Name</th>
                                    <th>Module Code</th>
                                    <th>Department</th>
                                    <th>Credits</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($module['module_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($module['module_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($module['department_name'] ?? 'Not assigned'); ?></td>
                                        <td><?php echo $module['credits']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $module['status']; ?>">
                                                <?php echo ucfirst($module['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($module['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($module['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="javascript:void(0)"
                                                   class="btn-icon edit"
                                                   title="Edit <?php echo htmlspecialchars($module['module_name']); ?>"
                                                   onclick="editModule(<?php echo $module['module_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)"
                                                   class="btn-icon delete"
                                                   title="Delete <?php echo htmlspecialchars($module['module_name']); ?>"
                                                   onclick="deleteModule(<?php echo $module['module_id']; ?>, '<?php echo htmlspecialchars($module['module_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Empty search results message -->
                        <div id="modules-table-empty-search" class="empty-search-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <p>No modules found matching your search.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-book"></i></div>
                        <div class="empty-text">No modules found</div>
                        <p>Start by adding a new module using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Module Modal -->
    <div id="editModuleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Module</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editModuleForm" method="POST" action="">
                    <input type="hidden" id="edit_module_id" name="module_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_module_name">Module Name <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="edit_module_name" name="module_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_module_code">Module Code <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="edit_module_code" name="module_code" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_department_id">Department</label>
                            <select id="edit_department_id" name="department_id" class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['dep_id']; ?>">
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_credits">Credits</label>
                            <input type="number" id="edit_credits" name="credits" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <select id="edit_status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="button" class="btn" onclick="closeEditModal()" style="background-color: #6c757d; color: white;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Module
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModuleModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the module "<span id="deleteModuleName"></span>"?</p>
                <p style="color: #dc3545; font-size: 0.9rem;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeDeleteModal()" style="background-color: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmDeleteModule()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include search.js for search functionality -->
    <script src="js/search.js"></script>
    
    <script>
        let currentModuleId = null;
        
        // Edit module function
        function editModule(moduleId) {
            currentModuleId = moduleId;
            
            // Fetch module data via AJAX
            fetch(`get_module_data.php?id=${moduleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const module = data.module;
                        
                        // Populate form fields
                        document.getElementById('edit_module_id').value = module.module_id;
                        document.getElementById('edit_module_name').value = module.module_name;
                        document.getElementById('edit_module_code').value = module.module_code;
                        document.getElementById('edit_department_id').value = module.department_id || '';
                        document.getElementById('edit_credits').value = module.credits;
                        document.getElementById('edit_status').value = module.status;
                        document.getElementById('edit_description').value = module.description || '';
                        
                        // Show modal
                        document.getElementById('editModuleModal').style.display = 'block';
                    } else {
                        alert('Error loading module data: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading module data: ' + error.message);
                });
        }
        
        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModuleModal').style.display = 'none';
            currentModuleId = null;
        }
        
        // Delete module function
        function deleteModule(moduleId, moduleName) {
            currentModuleId = moduleId;
            document.getElementById('deleteModuleName').textContent = moduleName;
            document.getElementById('deleteModuleModal').style.display = 'block';
        }
        
        // Confirm delete module
        function confirmDeleteModule() {
            if (currentModuleId) {
                window.location.href = `modules.php?action=delete&id=${currentModuleId}`;
            }
        }
        
        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModuleModal').style.display = 'none';
            currentModuleId = null;
        }
        
        // Export module data function
        function exportModuleData() {
            const table = document.getElementById('modules-table');
            const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');

            if (rows.length === 0) {
                alert('No modules to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Module Name,Module Code,Department,Credits,Status,Description,Created\n";

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].textContent.trim()
                ];
                csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'modules_list.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModuleModal');
            const deleteModal = document.getElementById('deleteModuleModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html> 