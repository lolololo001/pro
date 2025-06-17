<?php
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

// Check database connection
if (!$conn) {
    die("Database connection failed");
}

// Fetch recent announcements
$announcements = [];
try {
    // Create announcements table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        priority_level VARCHAR(20) NOT NULL DEFAULT 'normal',
        target_audience VARCHAR(50) NOT NULL,
        expiry_date DATE,
        attachment_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    )");

    // Fetch recent announcements
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE school_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - School Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="enhanced-form-styles.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Announcements</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Announcements</span>
            </div>
        </div>

        <?php if (isset($_SESSION['announcement_success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['announcement_success']; unset($_SESSION['announcement_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['announcement_error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['announcement_error']; unset($_SESSION['announcement_error']); ?>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2>New Announcement</h2>
            </div>
            <div class="card-body">
                <form action="process_announcement.php" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <div class="form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div class="form-group">
                                <label for="announcement_type">Announcement Type <span class="required">*</span></label>
                                <select id="announcement_type" name="announcement_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="general">General</option>
                                    <option value="academic">Academic</option>
                                    <option value="event">Event</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="target_audience">Target Audience <span class="required">*</span></label>
                                <select id="target_audience" name="target_audience" class="form-control" required>
                                    <option value="">Select Audience</option>
                                    <option value="all">All Parents</option>
                                    <option value="class">Specific Class</option>
                                    <option value="department">Specific Department</option>
                                    <option value="individual">Individual Parents</option>
                                </select>
                            </div>
                        </div>

                        <div id="targetSelectionContainer" class="form-group" style="display: none; margin-top: 1rem;"></div>

                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="announcement_title">Announcement Title <span class="required">*</span></label>
                            <input type="text" id="announcement_title" name="announcement_title" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="announcement_text">Announcement Content <span class="required">*</span></label>
                            <textarea id="announcement_text" name="announcement_text" class="form-control" rows="5" required></textarea>
                        </div>

                        <div class="form-grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div class="form-group">
                                <label for="priority_level">Priority Level</label>
                                <select id="priority_level" name="priority_level" class="form-control">
                                    <option value="normal">Normal</option>
                                    <option value="important">Important</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="attachment">Attachment (optional)</label>
                            <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="text-muted">Supported formats: PDF, Word, Images (Max size: 5MB)</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Announcement
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Announcements</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Target</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No announcements found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                        <td><?php echo htmlspecialchars($announcement['type']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $announcement['priority_level']; ?>">
                                                <?php echo ucfirst($announcement['priority_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($announcement['target_audience']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                                        <td>
                                            <a href="view_announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const targetAudience = document.getElementById('target_audience');
            const targetContainer = document.getElementById('targetSelectionContainer');

            targetAudience.addEventListener('change', function() {
                let html = '';
                switch(this.value) {
                    case 'class':
                        html = `
                            <label for="class_selection">Select Class(es) <span class="required">*</span></label>
                            <select id="class_selection" name="class_ids[]" class="form-control" multiple required>
                                <?php 
                                try {
                                    $stmt = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
                                    $stmt->bind_param('i', $school_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) {
                                        echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['class_name']).'</option>';
                                    }
                                } catch(Exception $e) {
                                    error_log("Error fetching classes: " . $e->getMessage());
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple classes</small>
                        `;
                        break;
                    case 'department':
                        html = `
                            <label for="department_selection">Select Department(s) <span class="required">*</span></label>
                            <select id="department_selection" name="department_ids[]" class="form-control" multiple required>
                                <?php 
                                try {
                                    $stmt = $conn->prepare("SELECT dep_id, department_name FROM departments WHERE school_id = ? ORDER BY department_name");
                                    $stmt->bind_param('i', $school_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) {
                                        echo '<option value="'.$row['dep_id'].'">'.htmlspecialchars($row['department_name']).'</option>';
                                    }
                                } catch(Exception $e) {
                                    error_log("Error fetching departments: " . $e->getMessage());
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple departments</small>
                        `;
                        break;
                    case 'individual':
                        html = `
                            <label for="parent_selection">Select Parent(s) <span class="required">*</span></label>
                            <select id="parent_selection" name="parent_ids[]" class="form-control" multiple required>
                                <?php 
                                try {
                                    $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email FROM parents WHERE school_id = ? ORDER BY first_name, last_name");
                                    $stmt->bind_param('i', $school_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) {
                                        echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['full_name']).' ('.htmlspecialchars($row['email']).')</option>';
                                    }
                                } catch(Exception $e) {
                                    error_log("Error fetching parents: " . $e->getMessage());
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple parents</small>
                        `;
                        break;
                    default:
                        html = '';
                }
                targetContainer.innerHTML = html;
                targetContainer.style.display = html ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
