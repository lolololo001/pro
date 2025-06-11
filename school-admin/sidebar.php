<?php
// Get school info if not already fetched
if (empty($school_info)) {
    try {
        $stmt = $conn->prepare('SELECT name, logo, address, phone, email FROM schools WHERE id = ?');
        $stmt->bind_param('i', $school_id);
        $stmt->execute();
        $school_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching school info: " . $e->getMessage());
    }
}
?>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="school-logo-container">
            <?php if (!empty($school_info['logo'])): ?>
                <img src="../<?php echo htmlspecialchars($school_info['logo']); ?>" alt="School Logo" class="school-logo">
            <?php else: ?>
                <div class="school-logo-placeholder">
                    <i class="fas fa-school"></i>
                </div>
            <?php endif; ?>
        </div>
        <a href="dashboard.php" class="sidebar-logo">
            <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?><span>.</span>
        </a>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php echo substr($_SESSION['school_admin_name'] ?? 'A', 0, 1); ?>
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($_SESSION['school_admin_name'] ?? 'Admin'); ?></h3>
            <p>School Administrator</p>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-heading">Main</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <a href="dashboard.php"><span>Dashboard</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <a href="students.php"><span>Students</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i>
            <a href="teachers.php"><span>Teachers</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>">
            <i class="fas fa-school"></i>
            <a href="classes.php"><span>Classes</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <a href="departments.php"><span>Departments</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'parents.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <a href="parents.php"><span>Parents</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'permissions.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <a href="permissions.php"><span>Permissions</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'logo.php' ? 'active' : ''; ?>">
            <i class="fas fa-image"></i>
            <a href="logo.php"><span>Logo & Motto</span></a>
        </div>
        
        <div class="menu-heading">Finance</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'bursars.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <a href="bursars.php"><span>Bursars</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <a href="payments.php"><span>Payments</span></a>
        </div>
        
        <div class="menu-heading">Settings</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <a href="settings.php"><span>Settings</span></a>
        </div>
        <div class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <a href="../logout.php" class="logout-link"><span>Logout</span></a>
        </div>
    </div>
</aside>