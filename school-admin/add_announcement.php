<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die('School ID not found.');
}
// Get DB connection for sidebar
$conn = getDbConnection();
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
    <title>Add Announcement - School Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="enhanced-form-styles.css">
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
        }
        /* Sidebar Styles (copied from dashboard.php) */
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
            margin-right: 10px;
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
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
        .form-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            justify-content: center;
        }
        .card {
            background-color: var(--light-color);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(67,233,123,0.10);
            padding: 2.5rem 2rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { font-weight: 600; color: #222; margin-bottom: 0.5rem; display: block; }
        .form-control { width: 100%; padding: 0.9rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn { background: linear-gradient(135deg, var(--primary-color), #43e97b); color: #fff; border: none; border-radius: 8px; padding: 0.8rem 1.5rem; font-weight: 600; font-size: 1rem; cursor: pointer; box-shadow: 0 2px 8px rgba(67,233,123,0.10); transition: background 0.2s; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn:hover { background: linear-gradient(135deg, #43e97b, var(--primary-color)); }
        .btn.cancel { background: #eee; color: var(--primary-color); box-shadow: none; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 992px) {
            .sidebar { width: 70px; }
            .main-content { margin-left: 70px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .card { padding: 1.5rem 0.5rem; }
            .form-row { grid-template-columns: 1fr; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Add Announcement</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Home</a>
                    <span>/</span>
                    <a href="add_announcement.php">Add Announcement</a>
                </div>
            </div>
        </div>
        <div class="form-title"><i class="fas fa-bullhorn"></i> Announcement Form</div>
        <div class="card">
            <form action="process_announcement.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="announcement_title">Announcement Title <span class="required">*</span></label>
                    <input type="text" id="announcement_title" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="announcement_text">Announcement Content <span class="required">*</span></label>
                    <textarea id="announcement_text" name="content" class="form-control" rows="5" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="target_group">Target Group <span class="required">*</span></label>
                        <select id="target_group" name="target_group" class="form-control" required>
                            <option value="all">All (Everyone)</option>
                            <option value="parents">Parents Only</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                            <option value="staff">Staff Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="publish_date">Publish Date</label>
                        <input type="date" id="publish_date" name="publish_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="attachment">Attachment</label>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><i class="fas fa-bullhorn"></i> Publish Announcement</button>
                    <a href="dashboard.php" class="btn cancel"><i class="fas fa-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 