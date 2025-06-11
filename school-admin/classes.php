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

// Check if classes table exists
$result = $conn->query("SHOW TABLES LIKE 'classes'");
if ($result->num_rows == 0) {
    // Create classes table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        class_name VARCHAR(50) NOT NULL,
        grade_level VARCHAR(20) NOT NULL,
        teacher_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $class_id = intval($_GET['id']);
    
    // Delete the class
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $class_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['class_success'] = 'Class deleted successfully!';
    } else {
        $_SESSION['class_error'] = 'Failed to delete class: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: classes.php');
    exit;
}

// Get all classes for this school
$classes = [];
$stmt = $conn->prepare("SELECT c.*, t.name as teacher_name 
                       FROM classes c 
                       LEFT JOIN teachers t ON c.teacher_id = t.id 
                       WHERE c.school_id = ? 
                       ORDER BY c.grade_level ASC, c.class_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
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
    <title>Manage Classes - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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
            --sidebar-width: 250px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --radius-sm: 4px;
            --radius-md: 8px;
            --transition: all 0.3s ease;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            padding: 2rem;
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
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
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
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
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
        
        /* Responsive Styles */
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
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
            <h1><i class="fas fa-school"></i> Manage Classes</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Classes</span>
            </div>
        </div>
        

        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['class_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['class_success']; 
                unset($_SESSION['class_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['class_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['class_error']; 
                unset($_SESSION['class_error']);
                ?>
            </div>
        <?php endif; ?>
        

        <!-- Classes List Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Classes</h2>
                <!-- Action buttons positioned at the right -->
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportClassData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                        <i class="fas fa-download"></i> Export List
                    </button>
                    <a href="#" onclick="openModal('addClassModal')" class="btn btn-primary" style="font-size:0.9rem;"><i class="fas fa-plus"></i> Add Class</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($classes) > 0): ?>
                    <!-- Search Box -->
                    <div class="search-container" data-table="classes-table">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search classes...">
                            <button type="button" class="search-clear" onclick="clearSearch('classes-table')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table class="data-table" id="classes-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Grade Level</th>
                                    <th>Teacher</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['grade_level']); ?></td>
                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($class['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="javascript:void(0)" class="btn-icon edit" title="Edit" data-id="<?php echo $class['id']; ?>"><i class="fas fa-edit"></i></a>
                                                <a href="javascript:void(0)" class="btn-icon delete" title="Delete" data-id="<?php echo $class['id']; ?>"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                        <!-- Empty search results message -->
                        <div id="classes-table-empty-search" class="empty-search-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <p>No classes found matching your search.</p>
                        </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-school"></i></div>
                        <div class="empty-text">No classes found</div>
                        <p>Start by adding a new class using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        
    </div>
    
    <!-- Class Edit Modal -->
    <div id="classEditModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Class</h2>
                <span class="close" onclick="closeClassEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="classEditFormContainer"></div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-question-circle"></i> Confirmation</h2>
                <span class="close" onclick="closeConfirmationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage">Are you sure you want to perform this action?</p>
                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmButton">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
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
            max-width: 800px;
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
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .loading-spinner i {
            margin-right: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .modal-form .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
    
    <!-- Include search.js for search functionality -->
    <script src="js/search.js"></script>
    
    <script>
        // Global variables
        let currentClassId = null;
        let confirmCallback = null;
        
        // Open class edit form
        function openClassEditForm(classId) {
            currentClassId = classId;
            const modal = document.getElementById('classEditModal');
            const formContainer = document.getElementById('classEditFormContainer');
            
            // Show loading spinner
            formContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.style.display = 'block';
            
            // Fetch class data
            fetch(`get_edit_form.php?type=class&id=${classId}`)
                .then(response => response.text())
                .then(html => {
                    formContainer.innerHTML = html;
                    
                    // Add event listener to the form
                    const form = document.getElementById('editForm');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            confirmUpdateClass(new FormData(form));
                        });
                    }
                })
                .catch(error => {
                    formContainer.innerHTML = `<div class="alert alert-danger">Error loading form: ${error.message}</div>`;
                });
        }
        
        // Close class edit modal
        function closeClassEditModal() {
            document.getElementById('classEditModal').style.display = 'none';
            currentClassId = null;
        }
        
        // Confirm class update
        function confirmUpdateClass(formData) {
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            message.innerHTML = 'Are you sure you want to update this class information?';
            modal.style.display = 'block';
            
            // Set up the confirm button action
            confirmCallback = function() {
                updateClass(formData);
            };
            
            confirmBtn.onclick = function() {
                closeConfirmationModal();
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Update class
        function updateClass(formData) {
            if (!currentClassId) return;
            
            // Add class ID to form data
            formData.append('id', currentClassId);
            
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'alert alert-info';
            loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating class...';
            document.querySelector('.main-content').insertBefore(loadingIndicator, document.querySelector('.card'));
            
            // Send update request
            fetch('edit_class.php?id=' + currentClassId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Close the edit modal
                closeClassEditModal();
                
                // Show success message
                loadingIndicator.className = 'alert alert-success';
                loadingIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Class updated successfully!';
                
                // Reload the page after a short delay to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                loadingIndicator.className = 'alert alert-danger';
                loadingIndicator.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error updating class: ${error.message}`;
            });
        }
        
        // Confirm delete class
        function confirmDeleteClass(classId) {
            currentClassId = classId;
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            message.innerHTML = 'Are you sure you want to delete this class? This action cannot be undone.';
            modal.style.display = 'block';
            
            // Set up the confirm button action
            confirmCallback = function() {
                // Show loading indicator
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'alert alert-info';
                loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting class...';
                document.querySelector('.main-content').insertBefore(loadingIndicator, document.querySelector('.card'));
                
                // Send delete request via fetch instead of redirecting
                fetch(`classes.php?action=delete&id=${classId}`)
                    .then(response => {
                        loadingIndicator.className = 'alert alert-success';
                        loadingIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Class deleted successfully!';
                        
                        // Reload the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    })
                    .catch(error => {
                        loadingIndicator.className = 'alert alert-danger';
                        loadingIndicator.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error deleting class: ${error.message}`;
                    });
            };
            
            confirmBtn.onclick = function() {
                closeConfirmationModal();
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            confirmCallback = null;
        }
        
        // Initialize edit buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to edit buttons
            const editButtons = document.querySelectorAll('a.btn-icon.edit');
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const classId = this.getAttribute('data-id');
                    openClassEditForm(classId);
                });
            });
            
            // Add event listeners to delete buttons
            const deleteButtons = document.querySelectorAll('a.btn-icon.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const classId = this.getAttribute('data-id');
                    confirmDeleteClass(classId);
                });
            });
            
            // The search functionality is handled by search.js
        });

        // Export class data function
        function exportClassData() {
            const table = document.getElementById('classes-table');
            const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');

            if (rows.length === 0) {
                alert('No classes to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Class Name,Grade Level,Teacher,Date Added\n";

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim()
                ];
                csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'classes_list.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Function to open modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        // Function to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    </script>

    <!-- Add Class Modal from Dashboard -->
    <div id="addClassModal" class="modal">
        <div class="modal-content" style="max-width: 700px; border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #00704a, #2563eb); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;"><i class="fas fa-door-open"></i> Add New Class</h2>
                <span class="close-modal" onclick="closeModal('addClassModal')" style="color: white; font-size: 1.5rem; cursor: pointer; background: none; border: none;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 2rem; background: white;">
                <?php if (isset($_SESSION['class_error'])): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['class_error']; unset($_SESSION['class_error']); ?>
                    </div>
                <?php endif; ?>

                <form action="add_class.php" method="POST" class="modal-form" id="classForm">
                    <div class="form-section" style="margin-bottom: 2rem;">
                        <div class="section-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="width: 40px; height: 40px; background: #00704a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-school"></i>
                            </div>
                            <h3 style="margin: 0; color: #00704a; font-size: 1.1rem;">Class Information</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div style="margin-bottom: 1rem;">
                                <label for="class_name" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Class Name <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" id="class_name" name="class_name" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="grade_level" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Grade Level <span style="color: #dc3545;">*</span>
                                </label>
                                <select id="grade_level" name="grade_level" class="form-control"
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                                    <option value="">Select Grade Level</option>
                                    <option value="Ordinary Level">Ordinary Level</option>
                                    <option value="Advanced Level">Advanced Level</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 1rem; grid-column: 1 / -1;">
                                <label for="teacher_id" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Class Teacher
                                </label>
                                <select id="teacher_id" name="teacher_id" class="form-control"
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;">
                                    <option value="">Select Teacher</option>
                                    <?php
                                    // Get all teachers for dropdown
                                    $teachers = [];
                                    $conn = getDbConnection();
                                    try {
                                        $teacher_stmt = $conn->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name ASC");
                                        $teacher_stmt->bind_param('i', $school_id);
                                        $teacher_stmt->execute();
                                        $teacher_result = $teacher_stmt->get_result();
                                        while ($row = $teacher_result->fetch_assoc()) {
                                            $teachers[] = $row;
                                        }
                                        $teacher_stmt->close();
                                    } catch (Exception $e) {
                                        error_log("Error fetching teachers: " . $e->getMessage());
                                    }
                                    $conn->close();

                                    foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-start; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef;">
                        <button type="submit" id="saveClassBtn"
                                style="background: #00704a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background-color 0.3s ease;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#00704a'">
                            <i class="fas fa-save"></i> Save Class
                        </button>
                        <button type="button" onclick="closeModal('addClassModal')"
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>