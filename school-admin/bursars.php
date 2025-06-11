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

// Check if bursars table exists
$result = $conn->query("SHOW TABLES LIKE 'bursars'");
if ($result->num_rows == 0) {
    // Create bursars table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS bursars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    )");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $bursar_id = intval($_GET['id']);
    
    // Delete the bursar
    $stmt = $conn->prepare("DELETE FROM bursars WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $bursar_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['bursar_success'] = 'Bursar deleted successfully!';
    } else {
        $_SESSION['bursar_error'] = 'Failed to delete bursar: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: bursars.php');
    exit;
}

// Get all bursars for this school
$bursars = [];
$stmt = $conn->prepare("SELECT * FROM bursars WHERE school_id = ? ORDER BY name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bursars[] = $row;
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
    <title>Manage Bursars - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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
        /* Search Box Styles */
        .search-container {
            margin-bottom: 1.5rem;
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
            <h1><i class="fas fa-money-bill-wave"></i> Manage Bursars</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Bursars</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['bursar_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['bursar_success']; 
                unset($_SESSION['bursar_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['bursar_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['bursar_error']; 
                unset($_SESSION['bursar_error']);
                ?>
            </div>
        <?php endif; ?>
        

        <!-- Bursars List Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Bursars</h2>
                <!-- Action buttons positioned at the right -->
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportBursarData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                        <i class="fas fa-download"></i> Export List
                    </button>
                    <a href="#" onclick="openModal('addBursarModal')" class="btn btn-primary" style="font-size:0.9rem;"><i class="fas fa-plus"></i> Add Bursar</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($bursars) > 0): ?>
                    <!-- Search Box -->
                    <div class="search-container" data-table="bursars-table">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search bursar...">
                            <button type="button" class="search-clear" onclick="clearSearch('bursars-table')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table" id="bursars-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bursars as $bursar): ?>
                                    <tr data-id="<?php echo $bursar['id']; ?>">
                                        <td><?php echo htmlspecialchars($bursar['name']); ?></td>
                                        <td><?php echo htmlspecialchars($bursar['email']); ?></td>
                                        <td><?php echo htmlspecialchars($bursar['phone']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($bursar['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="#" class="btn-icon edit" title="Edit" data-id="<?php echo $bursar['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" class="btn-icon delete" title="Delete" data-id="<?php echo $bursar['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                        <!-- Empty search results message -->
                        <div id="bursars-table-empty-search" class="empty-search-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <p>No bursars found matching your search.</p>
                        </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="empty-text">No bursars found</div>
                        <p>Start by adding a new bursar using the Add Bursar button above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Bursar Modal -->
    <div id="addBursarModal" class="modal">
        <div class="modal-content" style="max-width: 600px; border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #00704a, #2563eb); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;"><i class="fas fa-money-bill-wave"></i> Add New Bursar</h2>
                <span class="close-modal" onclick="closeModal('addBursarModal')" style="color: white; font-size: 1.5rem; cursor: pointer; background: none; border: none;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 2rem; background: white;">
                <?php if (isset($_SESSION['bursar_error'])): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['bursar_error']; unset($_SESSION['bursar_error']); ?>
                    </div>
                <?php endif; ?>

                <form action="add_bursar.php" method="POST" class="modal-form" id="bursarForm">
                    <div class="form-section" style="margin-bottom: 2rem;">
                        <div class="section-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="width: 40px; height: 40px; background: #00704a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 style="margin: 0; color: #00704a; font-size: 1.1rem;">Bursar Information</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div style="margin-bottom: 1rem;">
                                <label for="bursar_name" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Full Name <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" id="bursar_name" name="bursar_name" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="bursar_email" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Email Address <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="email" id="bursar_email" name="bursar_email" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem; grid-column: 1 / -1;">
                                <label for="bursar_phone" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Phone Number <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="tel" id="bursar_phone" name="bursar_phone" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-start; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef;">
                        <button type="submit" id="saveBursarBtn"
                                style="background: #00704a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background-color 0.3s ease;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#00704a'">
                            <i class="fas fa-save"></i> Save Bursar
                        </button>
                        <button type="button" onclick="closeModal('addBursarModal')"
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
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
            position: relative;
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 800px;
            animation: modalFadeIn 0.3s;
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
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
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .export-btn:hover {
            background-color: #e9ecef !important;
            border-color: #adb5bd !important;
        }
    </style>

    <!-- Include search.js for search functionality -->
    <script src="js/search.js"></script>

    <script>
        // Export bursar data function
        function exportBursarData() {
            const table = document.getElementById('bursars-table');
            const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');

            if (rows.length === 0) {
                alert('No bursars to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Name,Email,Phone,Date Added\n";

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
            link.setAttribute('download', 'bursars_list.csv');
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
        
        // Global variables
        let currentBursarId = null;
        let confirmCallback = null;
        
        // Open bursar edit form
        function openBursarEditForm(bursarId) {
            currentBursarId = bursarId;
            const modal = document.getElementById('bursarEditModal');
            const formContainer = document.getElementById('bursarEditFormContainer');
            
            // Show loading spinner
            formContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.style.display = 'block';
            
            // Fetch bursar data
            fetch(`get_edit_form.php?type=bursar&id=${bursarId}`)
                .then(response => response.text())
                .then(html => {
                    formContainer.innerHTML = html;
                    
                    // Add event listener to the form
                    const form = document.getElementById('editForm');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            confirmUpdateBursar(new FormData(form));
                        });
                    }
                })
                .catch(error => {
                    formContainer.innerHTML = `<div class="alert alert-danger">Error loading form: ${error.message}</div>`;
                });
        }
        
        // Close bursar edit modal
        function closeBursarEditModal() {
            document.getElementById('bursarEditModal').style.display = 'none';
            currentBursarId = null;
        }
        
        // Confirm bursar update
        function confirmUpdateBursar(formData) {
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            message.innerHTML = 'Are you sure you want to update this bursar information?';
            modal.style.display = 'block';
            
            // Set up the confirm button action
            confirmCallback = function() {
                updateBursar(formData);
            };
            
            confirmBtn.onclick = function() {
                closeConfirmationModal();
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Update bursar
        function updateBursar(formData) {
            if (!currentBursarId) return;
            
            // Add bursar ID to form data
            formData.append('id', currentBursarId);
            formData.append('type', 'bursar');
            
            // Send update request
            fetch('update_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Close the edit modal
                closeBursarEditModal();
                
                // Show success or error message
                if (data.success) {
                    showAlert('success', data.message);
                    // Reload the page after a short delay
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('danger', data.message || 'Error updating bursar');
                }
            })
            .catch(error => {
                showAlert('danger', 'Error updating bursar: ' + error.message);
            });
        }
        
        // Confirm delete bursar
        function confirmDeleteBursar(bursarId) {
            currentBursarId = bursarId;
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            message.innerHTML = 'Are you sure you want to delete this bursar? This action cannot be undone.';
            modal.style.display = 'block';
            
            // Set up the confirm button action
            confirmCallback = function() {
                deleteBursar(bursarId);
            };
            
            confirmBtn.onclick = function() {
                closeConfirmationModal();
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Delete bursar
        function deleteBursar(bursarId) {
            if (!bursarId) return;
            
            // Create form data
            const formData = new FormData();
            formData.append('id', bursarId);
            formData.append('type', 'bursar');
            
            // Send delete request
            fetch('delete_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-id="${bursarId}"]`);
                    if (row) {
                        row.style.backgroundColor = '#ffcccc';
                        setTimeout(() => {
                            row.style.transition = 'opacity 0.5s ease';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 500);
                        }, 300);
                    }
                    // Check if table is empty
                    setTimeout(() => {
                        const rows = document.querySelectorAll('#bursars-table tbody tr');
                        if (rows.length === 0) {
                            document.querySelector('#bursars-table tbody').innerHTML = 
                                '<tr><td colspan="5" class="text-center">No bursars found</td></tr>';
                        }
                    }, 1000);
                } else {
                    showAlert('danger', data.message || 'Error deleting bursar');
                }
            })
            .catch(error => {
                showAlert('danger', 'Error deleting bursar: ' + error.message);
            });
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            confirmCallback = null;
        }
        
        // Show alert message
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = message;
            
            // Add the alert to the page
            const container = document.querySelector('.main-content');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 500);
            }, 5000);
        }
        
        // Include the universal modals
        include 'includes/universal-modals.php';
        
        // Initialize edit and delete buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to edit buttons
            const editButtons = document.querySelectorAll('a.btn-icon.edit');
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const bursarId = this.getAttribute('data-id');
                    showEditModal('bursar', bursarId);
                });
            });
            
            // Add event listeners to delete buttons
            const deleteButtons = document.querySelectorAll('a.btn-icon.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const bursarId = this.getAttribute('data-id');
                    const bursarName = this.closest('tr').querySelector('td:first-child').textContent;
                    showDeleteConfirmation('bursar', bursarId, null, null, bursarName);
                });
            });
        });
    </script>
    <!-- Bursar Edit Modal -->
    <div id="bursarEditModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Bursar</h2>
                <span class="close" onclick="closeBursarEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="bursarEditFormContainer">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirm Action</h2>
                <span class="close" onclick="closeConfirmationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="confirmationMessage" style="margin-bottom: 1.5rem;"></div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" id="confirmButton" class="btn btn-danger">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Include universal confirmation script -->
    <script src="js/universal-confirmation.js"></script>
</body>
</html>