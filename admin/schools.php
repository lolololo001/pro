<?php
session_start();
if (!isset($_SESSION['system_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize the application
require_once '../config/config.php';

// Check if system admin is logged in
if (!isLoggedIn('system_admin')) {
    redirect('../login.php');
}

// Get admin information
$adminId = getCurrentUserId('system_admin');
$adminName = $_SESSION['system_admin_name'];

// Initialize variables
$error = '';
$success = '';
$schools = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Handle school status updates
if (isset($_POST['action']) && isset($_POST['school_id'])) {
    $schoolId = (int)$_POST['school_id'];
    $action = $_POST['action'];
    
    // Connect to database
    $conn = getDbConnection();
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE schools SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $schoolId);
        
        if ($stmt->execute()) {
            $success = 'School has been approved successfully.';
            
            // Send notification email to school admin (implementation would go here)
        } else {
            $error = 'Failed to approve school: ' . $stmt->error;
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE schools SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $schoolId);
        
        if ($stmt->execute()) {
            $success = 'School has been rejected.';
        } else {
            $error = 'Failed to reject school: ' . $stmt->error;
        }
    } elseif ($action === 'delete') {
        // This would typically involve more checks and possibly archiving instead of deleting
        $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
        $stmt->bind_param("i", $schoolId);
        
        if ($stmt->execute()) {
            $success = 'School has been deleted.';
        } else {
            $error = 'Failed to delete school: ' . $stmt->error;
        }
    }
    
    $conn->close();
}

// Get schools based on filter
$conn = getDbConnection();

$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM students WHERE school_id = s.id) as student_count,
        (SELECT COUNT(DISTINCT p.id) FROM parents p 
         JOIN student_parent sp ON p.id = sp.parent_id 
         JOIN students st ON sp.student_id = st.id 
         WHERE st.school_id = s.id) as parent_count
        FROM schools s";

if ($filter === 'pending') {
    $sql .= " WHERE s.status = 'pending'";
} elseif ($filter === 'active') {
    $sql .= " WHERE s.status = 'active'";
} elseif ($filter === 'inactive') {
    $sql .= " WHERE s.status = 'inactive'";
}

$sql .= " ORDER BY s.registration_date DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - System Admin Dashboard</title>
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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
            width: 250px;
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 1.5rem 0;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .menu-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu ul li {
            margin-bottom: 0.2rem;
        }
        
        .sidebar-menu ul li a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: var(--light-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu ul li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu ul li a.active {
            background-color: var(--accent-color);
            font-weight: 500;
        }
        
        .sidebar-menu ul li a i {
            margin-right: 0.8rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            color: var(--primary-color);
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            background-color: var(--light-color);
            color: var(--dark-color);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: var(--light-color);
        }
        
        .filter-btn:hover:not(.active) {
            background-color: var(--border-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .school-card {
            background-color: var(--light-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .school-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .school-status {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .school-name {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .school-meta {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .school-meta i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }
        
        .school-body {
            padding: 1.5rem;
        }
        
        .school-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .school-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn i {
            margin-right: 0.5rem;
        }
        
        .approve-btn {
            background-color: var(--success-color);
            color: var(--light-color);
        }
        
        .reject-btn {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }
        
        .delete-btn {
            background-color: var(--danger-color);
            color: var(--light-color);
        }
        
        .view-btn {
            background-color: var(--info-color);
            color: var(--light-color);
        }
        
        .action-btn:hover {
            opacity: 0.9;
        }
        
        .no-schools {
            grid-column: 1 / -1;
            background-color: var(--light-color);
            padding: 2rem;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .no-schools i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .no-schools h3 {
            margin-bottom: 0.5rem;
            color: var (--primary-color);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .schools-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1><?php echo APP_NAME; ?></h1>
            <p>System Administration</p>
        </div>
        
        <div class="sidebar-menu">
            <p class="menu-title">Main Menu</p>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="schools.php" class="active"><i class="fas fa-school"></i> Manage Schools</a></li>
                <li><a href="admins.php"><i class="fas fa-user-shield"></i> School Admins</a></li>
                <li><a href="parents.php"><i class="fas fa-users"></i> Parents</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            
            <p class="menu-title">Account</p>
            <ul>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2>Manage Schools</h2>
            
            <div class="filter-controls">
                <a href="schools.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Schools</a>
                <a href="schools.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="schools.php?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
                <a href="schools.php?filter=inactive" class="filter-btn <?php echo $filter === 'inactive' ? 'active' : ''; ?>">Inactive</a>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="schools-grid">
            <?php if (empty($schools)): ?>
                <div class="no-schools">
                    <i class="fas fa-school"></i>
                    <h3>No schools found</h3>
                    <p>There are no schools matching your current filter criteria.</p>
                </div>
            <?php else: ?>
                <?php foreach ($schools as $school): ?>
                    <div class="school-card">
                        <div class="school-header">
                            <span class="school-status status-<?php echo $school['status']; ?>">
                                <?php echo ucfirst($school['status']); ?>
                            </span>
                            <h3 class="school-name"><?php echo htmlspecialchars($school['name']); ?></h3>
                            <div class="school-meta">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($school['address']); ?></span>
                            </div>
                            <div class="school-meta">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($school['email']); ?></span>
                            </div>
                            <div class="school-meta">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($school['phone']); ?></span>
                            </div>
                            <div class="school-meta">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Registered: <?php echo date('M d, Y', strtotime($school['registration_date'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="school-body">
                            <div class="school-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $school['student_count']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $school['parent_count']; ?></div>
                                    <div class="stat-label">Parents</div>
                                </div>
                            </div>
                            
                            <div class="school-actions">
                                <?php if ($school['status'] === 'pending'): ?>
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn approve-btn">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn reject-btn">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                <?php elseif ($school['status'] === 'active'): ?>
                                    <a href="../school.php?id=<?php echo $school['id']; ?>" target="_blank" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn reject-btn">
                                            <i class="fas fa-ban"></i> Deactivate
                                        </button>
                                    </form>
                                <?php elseif ($school['status'] === 'inactive'): ?>
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="action-btn approve-btn">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this school? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php require_once '../includes/footer_includes.php'; ?>
</body>
</html>