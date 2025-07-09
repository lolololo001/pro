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
            <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?>
        </a>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php echo substr($teacher_info['name'] ?? 'T', 0, 1); ?>
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($teacher_info['name'] ?? 'Teacher'); ?></h3>
            <p>Teacher</p>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-heading">Main</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <a href="dashboard.php"><span>Dashboard</span></a>
        </div>
        
        <div class="menu-heading">Academic Management</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>">
            <i class="fas fa-school"></i>
            <a href="classes.php"><span>My Classes</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <a href="students.php"><span>Students</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'marks.php' ? 'active' : ''; ?>">
            <i class="fas fa-edit"></i>
            <a href="marks.php"><span>Manage Marks</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <a href="attendance.php"><span>Attendance</span></a>
        </div>
        
        <div class="menu-heading">Reports & Analytics</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <a href="reports.php"><span>Reports</span></a>
        </div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <a href="analytics.php"><span>Analytics</span></a>
        </div>
        
        <div class="menu-heading">Settings</div>
        <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-cog"></i>
            <a href="profile.php"><span>Profile</span></a>
        </div>
        <div class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <a href="../logout.php"><span>Logout</span></a>
        </div>
    </div>
</aside> 