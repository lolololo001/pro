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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Logo - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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
            --sidebar-width: 250px;
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
        
        .sidebar-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
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
            
            .menu-item a {
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
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1>School Logo & Motto</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Logo</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['logo_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['logo_success']; 
                unset($_SESSION['logo_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['logo_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['logo_error']; 
                unset($_SESSION['logo_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- School Logo Upload -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-image"></i> School Logo Management</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 2rem;">
                    <div style="width: 200px; height: 200px; background-color: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if (!empty($school_info['logo'])): ?>
                            <img src="../<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" style="max-width: 100%; max-height: 100%;">
                        <?php else: ?>
                            <i class="fas fa-school" style="font-size: 4rem; color: #ccc;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 300px;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Current Logo</h3>
                        <?php if (!empty($school_info['logo'])): ?>
                            <p style="margin-bottom: 1rem;">Your school logo is currently set. You can update it using the form below.</p>
                        <?php else: ?>
                            <p style="margin-bottom: 1rem;">You haven't uploaded a logo yet. Please use the form below to upload your school logo.</p>
                        <?php endif; ?>
                        
                        <form action="upload_logo.php" method="post" enctype="multipart/form-data">
                            <div style="margin-bottom: 1.5rem;">
                                <label for="logo" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Upload New Logo</label>
                                <input type="file" name="logo" id="logo" accept="image/*" required style="border: 1px solid #ddd; padding: 0.8rem; border-radius: 4px; width: 100%;">
                                <small style="display: block; margin-top: 0.5rem; color: #666;">
                                    <ul style="list-style-type: none; padding-left: 0;">
                                        <li><i class="fas fa-info-circle"></i> Recommended size: 200x200 pixels</li>
                                        <li><i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, GIF</li>
                                        <li><i class="fas fa-info-circle"></i> Maximum file size: 2MB</li>
                                    </ul>
                                </small>
                            </div>
                            <button type="submit" style="background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 4px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-upload"></i> Upload Logo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- School Motto Management -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-quote-left"></i> School Motto Management</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 2rem;">
                    <div style="width: 200px; padding: 1.5rem; background-color: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-align: center;">
                        <?php 
                        // Get school motto
                        $motto = '';
                        try {
                            $stmt = $conn->prepare('SELECT motto FROM schools WHERE id = ?');
                            $stmt->bind_param('i', $school_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($row = $result->fetch_assoc()) {
                                $motto = $row['motto'];
                            }
                            $stmt->close();
                        } catch (Exception $e) {
                            error_log("Error fetching school motto: " . $e->getMessage());
                        }
                        ?>
                        <?php if (!empty($motto)): ?>
                            <p style="font-style: italic; font-weight: 500; color: var(--primary-color);">
                                "<?php echo htmlspecialchars($motto); ?>"
                            </p>
                        <?php else: ?>
                            <p style="color: #999; font-style: italic;">
                                No motto set
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 300px;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Current Motto</h3>
                        <?php if (!empty($motto)): ?>
                            <p style="margin-bottom: 1rem;">Your school motto is currently set. You can update it using the form below.</p>
                        <?php else: ?>
                            <p style="margin-bottom: 1rem;">You haven't set a motto yet. Please use the form below to add your school motto.</p>
                        <?php endif; ?>
                        
                        <form action="update_motto.php" method="post">
                            <div style="margin-bottom: 1.5rem;">
                                <label for="motto" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">School Motto</label>
                                <textarea name="motto" id="motto" rows="3" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px;"><?php echo htmlspecialchars($motto); ?></textarea>
                                <small style="display: block; margin-top: 0.5rem; color: #666;">
                                    Enter your school motto. This will be displayed on various school documents and the parent portal.
                                </small>
                            </div>
                            <button type="submit" style="background-color: var(--primary-color); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 4px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-save"></i> Save Motto
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>