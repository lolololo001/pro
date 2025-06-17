<?php
// Initialize the application
require_once '../config/config.php';

session_start();
if (!isset($_SESSION['system_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if system admin is logged in
if (!isLoggedIn('system_admin')) {
    redirect('../login.php');
}

// Get admin information
$adminId = getCurrentUserId('system_admin');
$adminName = $_SESSION['system_admin_name'];

// Connect to database
$conn = getDbConnection();

// Get statistics
$stats = [
    'total_schools' => 0,
    'active_schools' => 0,
    'pending_schools' => 0,
    'total_parents' => 0,
    'total_students' => 0
];

// Get total schools
$result = $conn->query("SELECT COUNT(*) as count FROM schools");
if ($result) {
    $stats['total_schools'] = $result->fetch_assoc()['count'];
}

// Get active schools
$result = $conn->query("SELECT COUNT(*) as count FROM schools WHERE status = 'active'");
if ($result) {
    $stats['active_schools'] = $result->fetch_assoc()['count'];
}

// Get pending schools
$result = $conn->query("SELECT COUNT(*) as count FROM schools WHERE status = 'pending'");
if ($result) {
    $stats['pending_schools'] = $result->fetch_assoc()['count'];
}

// Get total parents
$result = $conn->query("SELECT COUNT(*) as count FROM parents");
if ($result) {
    $stats['total_parents'] = $result->fetch_assoc()['count'];
}

// Get total students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($result) {
    $stats['total_students'] = $result->fetch_assoc()['count'];
}

// Get recent school registrations
$recentSchools = [];
$result = $conn->query("SELECT id, name, email, phone, status, registration_date FROM schools ORDER BY registration_date DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentSchools[] = $row;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --footer-color: <?php echo FOOTER_COLOR; ?>;
            --accent-color: <?php echo ACCENT_COLOR; ?>;
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
        }
        
        .user-avatar i {
            font-size: 1.2rem;
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background-color: var(--footer-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .stat-icon i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            color: var(--primary-color);
        }
        
        .stat-info p {
            font-size: 0.9rem;
            color: #777;
        }
        
        /* Recent Schools */
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
        }
        
        .card-header a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .card-header a:hover {
            color: var(--accent-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.8rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(0, 112, 74, 0.05);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            background-color: var(--footer-color);
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 0.5rem;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background-color: var(--primary-color);
            color: var(--light-color);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            transition: opacity 0.3s ease-in-out;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 400px;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translate(-50%, -60%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        .modal-header {
            background: var(--primary-color);
            padding: 1.2rem 1.5rem;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            color: white;
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-modal {
            color: white;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .close-modal:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-body p {
            text-align: center;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        .btn:active {
            transform: translateY(0);
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
            
            .menu-item span {
                display: none;
            }
            
            .menu-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo"><?php echo APP_NAME; ?><span>.</span></a>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($adminName); ?></h3>
                <p>System Administrator</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-heading">Main</div>
            <div class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php"><span>Dashboard</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-school"></i>
                <a href="schools.php"><span>Manage Schools</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-user-shield"></i>
                <a href="admins.php"><span>System Admins</span></a>
            </div>
            
            <div class="menu-heading">Settings</div>
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <a href="settings.php"><span>System Settings</span></a>
            </div>
            <div class="menu-item">
                <i class="fas fa-user-cog"></i>
                <a href="profile.php"><span>My Profile</span></a>
            </div>            <div class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <a href="#" class="logout-link"><span>Logout</span></a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>System Admin Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-school"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_schools']; ?></h3>
                    <p>Total Schools</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_schools']; ?></h3>
                    <p>Active Schools</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending_schools']; ?></h3>
                    <p>Pending Approvals</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_parents']; ?></h3>
                    <p>Total Parents</p>
                </div>
            </div>
        </div>
        
        <!-- Recent Schools -->
        <div class="card">
            <div class="card-header">
                <h2>Recent School Registrations</h2>
                <a href="schools.php">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>School Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSchools)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No schools found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentSchools as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['name']); ?></td>
                                        <td><?php echo htmlspecialchars($school['email']); ?></td>
                                        <td><?php echo htmlspecialchars($school['phone']); ?></td>
                                        <td><?php echo formatDate($school['registration_date']); ?></td>
                                        <td>
                                            <?php if ($school['status'] === 'active'): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php elseif ($school['status'] === 'pending'): ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php require_once '../includes/footer_includes.php'; ?>
      <!-- Logout Modal -->
    <?php require_once '../includes/logout_modal.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle logout confirmation
            const logoutLinks = document.querySelectorAll('.logout-link, a[href="../logout.php"]');
            logoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('logoutConfirmModal').style.display = 'block';
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                });
            });

            // Function to close modals
            window.closeModal = function(modalId) {
                document.getElementById(modalId).style.display = 'none';
                document.body.style.overflow = ''; // Restore scrolling
            };

            // Function to handle logout
            window.handleLogout = function() {
                closeModal('logoutConfirmModal');
                window.location.href = '../logout.php';
            };

            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target.id);
                }
            });

            // Handle escape key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const visibleModal = document.querySelector('.modal[style*="display: block"]');
                    if (visibleModal) {
                        closeModal(visibleModal.id);
                    }
                }
            });
        });
    </script>
</body>
</html>