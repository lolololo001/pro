<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="logo-container">
        <h3>Parent Portal</h3>
    </div>
    <nav>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'children.php' ? 'active' : ''; ?>" href="children.php">
                    <i class="fas fa-users"></i> My Children
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'academics.php' ? 'active' : ''; ?>" href="academics.php">
                    <i class="fas fa-book"></i> Academics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'fees.php' ? 'active' : ''; ?>" href="fees.php">
                    <i class="fas fa-money-bill"></i> Fees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'permissions.php' ? 'active' : ''; ?>" href="permissions.php">
                    <i class="fas fa-key"></i> Permissions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'feedback.php' ? 'active' : ''; ?>" href="feedback.php">
                    <i class="fas fa-comment"></i> Feedback
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user"></i> Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background-color: #343a40;
    position: fixed;
    left: 0;
    top: 0;
    padding: 20px 0;
    color: white;
}

.logo-container {
    text-align: center;
    padding: 10px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 20px;
}

.nav-link {
    color: rgba(255,255,255,0.8);
    padding: 10px 20px;
    display: flex;
    align-items: center;
    transition: 0.3s;
}

.nav-link:hover {
    color: white;
    background-color: rgba(255,255,255,0.1);
}

.nav-link.active {
    color: white;
    background-color: rgba(255,255,255,0.2);
}

.nav-link i {
    margin-right: 10px;
    width: 20px;
}

.content {
    margin-left: 250px;
    padding: 20px;
}
</style>
