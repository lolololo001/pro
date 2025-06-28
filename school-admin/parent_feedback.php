<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$conn = getDbConnection();

// Function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Function to safely execute SQL
function executeSql($conn, $sql, $errorMsg = "") {
    try {
        if (!$conn->query($sql)) {
            throw new Exception($errorMsg . ": " . $conn->error);
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

// Create necessary tables if they don't exist
try {
    // Check if schools table exists
    $schools_check = $conn->query("SHOW TABLES LIKE 'schools'");
    if ($schools_check->num_rows === 0) {
        $create_schools_sql = "CREATE TABLE IF NOT EXISTS schools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        executeSql($conn, $create_schools_sql, "Error creating schools table");
    }

    // Check if parents table exists
    $parents_check = $conn->query("SHOW TABLES LIKE 'parents'");
    if ($parents_check->num_rows === 0) {
        $create_parents_sql = "CREATE TABLE IF NOT EXISTS parents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        executeSql($conn, $create_parents_sql, "Error creating parents table");
    }

    // Check if parent_feedback table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'parent_feedback'");
    if ($table_check->num_rows === 0) {
        $create_feedback_sql = "CREATE TABLE IF NOT EXISTS parent_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            school_id INT NOT NULL,
            feedback_text TEXT NOT NULL,
            feedback_type ENUM('Academic', 'Administrative', 'Facility', 'Teacher', 'Safety', 'Communication', 'Other') DEFAULT 'Other',
            status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
            admin_response TEXT,
            response_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sentiment_score DECIMAL(3,2) DEFAULT NULL,
            sentiment_label ENUM('positive', 'neutral', 'negative') DEFAULT 'neutral',
            subject VARCHAR(255),
            FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            INDEX idx_school_status (school_id, status),
            INDEX idx_created_at (created_at)
        )";
        executeSql($conn, $create_feedback_sql, "Error creating parent_feedback table");
    } else {
        // Check and add missing columns if needed
        if (!columnExists($conn, 'parent_feedback', 'sentiment_score')) {
            executeSql($conn, "ALTER TABLE parent_feedback ADD COLUMN sentiment_score DECIMAL(3,2) DEFAULT NULL", "Error adding sentiment_score column");
        }
        if (!columnExists($conn, 'parent_feedback', 'sentiment_label')) {
            executeSql($conn, "ALTER TABLE parent_feedback ADD COLUMN sentiment_label ENUM('positive', 'neutral', 'negative') DEFAULT 'neutral'", "Error adding sentiment_label column");
        }
        if (!columnExists($conn, 'parent_feedback', 'subject')) {
            executeSql($conn, "ALTER TABLE parent_feedback ADD COLUMN subject VARCHAR(255)", "Error adding subject column");
        }
    }
} catch (Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
    // Set a user-friendly error message
    $_SESSION['error_msg'] = "There was an issue setting up the database. Please contact support if this persists.";
}

// Handle feedback status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $feedback_id = intval($_POST['feedback_id']);
            $new_status = $_POST['status'];
            $response = $_POST['admin_response'] ?? '';
            
            // Validate status value
            $valid_statuses = ['pending', 'in_progress', 'resolved'];
            if (!in_array($new_status, $valid_statuses)) {
                throw new Exception("Invalid status value provided.");
            }
            
            $stmt = $conn->prepare("UPDATE parent_feedback SET status = ?, admin_response = ?, response_date = NOW() WHERE id = ? AND school_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare update statement: " . $conn->error);
            }
            
            $stmt->bind_param('ssii', $new_status, $response, $feedback_id, $school_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Feedback status updated successfully.";
            } else {
                throw new Exception("Failed to update feedback status: " . $stmt->error);
            }
        }
    } catch (Exception $e) {
        error_log("Error updating feedback: " . $e->getMessage());
        $_SESSION['error_msg'] = "Failed to update feedback. Please try again.";
    }
    
    header('Location: parent_feedback.php');
    exit;
}

// Get and validate feedback filters
$valid_statuses = ['all', 'pending', 'in_progress', 'resolved'];
$valid_types = ['all', 'Academic', 'Administrative', 'Facility', 'Teacher', 'Safety', 'Communication', 'Other'];
$valid_dates = ['all', 'today', 'week', 'month'];

$status_filter = in_array($_GET['status'] ?? 'all', $valid_statuses) ? $_GET['status'] : 'all';
$type_filter = in_array($_GET['type'] ?? 'all', $valid_types) ? $_GET['type'] : 'all';
$date_filter = in_array($_GET['date'] ?? 'all', $valid_dates) ? $_GET['date'] : 'all';

// Initialize feedback items as empty array in case of query failure
$feedback_items = [];

try {
    // Build the SQL query with filters
    $sql = "SELECT pf.id, pf.parent_id, pf.school_id, pf.feedback_text, 
            COALESCE(pf.feedback_type, 'Other') as feedback_type,
            COALESCE(pf.status, 'pending') as status,
            COALESCE(pf.admin_response, '') as admin_response,
            pf.response_date,
            pf.created_at,
            COALESCE(pf.sentiment_score, 0.00) as sentiment_score,
            COALESCE(pf.sentiment_label, 'neutral') as sentiment_label,
            COALESCE(pf.subject, '') as subject,
            COALESCE(CONCAT(p.first_name, ' ', p.last_name), 'Unknown Parent') as parent_name,
            COALESCE(p.email, 'No Email') as parent_email,
            DATE_FORMAT(pf.created_at, '%M %d, %Y %h:%i %p') as formatted_date
            FROM parent_feedback pf
            LEFT JOIN parents p ON pf.parent_id = p.id
            WHERE pf.school_id = ?";

    $params = [$school_id];
    $types = "i";

    if ($status_filter !== 'all') {
        $sql .= " AND pf.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    if ($type_filter !== 'all') {
        $sql .= " AND pf.feedback_type = ?";
        $params[] = $type_filter;
        $types .= "s";
    }

    if ($date_filter !== 'all') {
        switch ($date_filter) {
            case 'today':
                $sql .= " AND DATE(pf.created_at) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND pf.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $sql .= " AND pf.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }

    $sql .= " ORDER BY pf.created_at DESC LIMIT 1000"; // Add a reasonable limit

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Failed to get result: " . $stmt->error);
    }

    $feedback_items = $result->fetch_all(MYSQLI_ASSOC);

} 
catch (Exception $e) {
    error_log("Error fetching feedback: " . $e->getMessage());
    $_SESSION['error_msg'] = "There was an error loading the feedback. Please try refreshing the page.";

// Initialize statistics with default values
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'positive_sentiment' => 0,
    'negative_sentiment' => 0
];
}
try {
    // Get feedback statistics with sentiment analysis
    $stats_sql = "SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress,
        COALESCE(SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END), 0) as resolved,
        COALESCE(SUM(CASE WHEN sentiment_label = 'positive' THEN 1 ELSE 0 END), 0) as positive_sentiment,
        COALESCE(SUM(CASE WHEN sentiment_label = 'negative' THEN 1 ELSE 0 END), 0) as negative_sentiment
        FROM parent_feedback 
        WHERE school_id = ?";
    
    $stats_stmt = $conn->prepare($stats_sql);
    if (!$stats_stmt) {
        throw new Exception("Failed to prepare statistics query: " . $conn->error);
    }

    $stats_stmt->bind_param('i', $school_id);
    
    if (!$stats_stmt->execute()) {
        throw new Exception("Failed to execute statistics query: " . $stats_stmt->error);
    }

    $stats_result = $stats_stmt->get_result();
    if (!$stats_result) {
        throw new Exception("Failed to get statistics result: " . $stats_stmt->error);
    }

    $fetched_stats = $stats_result->fetch_assoc();
    if ($fetched_stats) {
        $stats = $fetched_stats;
    }

} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    // Keep using the default stats values initialized above
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Feedback Management - School Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00704A;
            --primary-dark: #006241;
            --primary-light: #D4E9D7;
            --accent: #FFB342;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --light: #f8f9fa;
            --border-color: #ddd;
        }

        .feedback-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            color: var(--primary);
        }

        .stat-card p {
            color: var(--dark);
            margin: 0.5rem 0 0;
        }

        .filters {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            appearance: none;
            background: url("data:image/svg+xml,...") no-repeat right 0.75rem center/8px 10px;
        }

        .feedback-grid {
            display: grid;
            gap: 1.5rem;
        }

        .feedback-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            position: relative;
            transition: transform 0.2s ease;
        }

        .feedback-card:hover {
            transform: translateY(-2px);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .feedback-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            background: var(--primary-light);
            color: var(--primary);
        }

        .feedback-date {
            color: var(--dark);
            font-size: 0.875rem;
        }

        .feedback-content {
            margin: 1rem 0;
        }

        .feedback-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Table Styles */
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .feedback-table th,
        .feedback-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .feedback-table th {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            white-space: nowrap;
        }

        .feedback-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .feedback-table td:last-child {
            white-space: nowrap;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: var(--warning);
            color: #856404;
        }

        .status-in_progress {
            background: var(--info);
            color: white;
        }

        .status-resolved {
            background: var(--success);
            color: white;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-respond {
            background: var(--primary);
        }

        .btn-progress {
            background: var(--info);
        }

        .btn-resolve {
            background: var(--success);
        }

        .empty-feedback {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-feedback i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .feedback-container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-group {
                flex: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <h1><i class="fas fa-comments"></i> Parent Feedback Management</h1>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Feedback</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['in_progress']; ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['resolved']; ?></h3>
                <p>Resolved</p>
            </div>
        </div>        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label for="status-filter">Status</label>
                <select id="status-filter" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="type-filter">Type</label>
                <select id="type-filter" onchange="applyFilters()">
                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="Academic" <?php echo $type_filter === 'Academic' ? 'selected' : ''; ?>>Academic</option>
                    <option value="Administrative" <?php echo $type_filter === 'Administrative' ? 'selected' : ''; ?>>Administrative</option>
                    <option value="Facility" <?php echo $type_filter === 'Facility' ? 'selected' : ''; ?>>Facility</option>
                    <option value="Teacher" <?php echo $type_filter === 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="Safety" <?php echo $type_filter === 'Safety' ? 'selected' : ''; ?>>Safety</option>
                    <option value="Communication" <?php echo $type_filter === 'Communication' ? 'selected' : ''; ?>>Communication</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="date-filter">Date</label>
                <select id="date-filter" onchange="applyFilters()">
                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>
        </div>        <!-- Feedback Table -->
        <?php if (empty($feedback_items)): ?>
            <div class="empty-feedback">
                <i class="fas fa-comments"></i>
                <p>No feedback found with the selected filters.</p>
            </div>
        <?php else: ?>
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Parent</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedback_items as $item): ?>
                        <tr>
                            <td>
                                <span class="feedback-type"><?php echo htmlspecialchars($item['feedback_type']); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['parent_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($item['parent_email']); ?></small>
                            </td>
                            <td style="max-width: 300px;"><?php echo htmlspecialchars($item['feedback_text']); ?></td>
                            <td><?php echo htmlspecialchars($item['formatted_date']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn btn-respond" onclick="showResponseModal(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-reply"></i> Respond
                                </button>
                                <?php if ($item['status'] === 'pending'): ?>
                                    <button class="action-btn btn-progress" onclick="updateStatus(<?php echo $item['id']; ?>, 'in_progress')">
                                        <i class="fas fa-clock"></i> Start
                                    </button>
                                <?php endif; ?>
                                <?php if ($item['status'] === 'in_progress'): ?>
                                    <button class="action-btn btn-resolve" onclick="updateStatus(<?php echo $item['id']; ?>, 'resolved')">
                                        <i class="fas fa-check"></i> Resolve
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
                    </div>
                    
                    <div class="feedback-meta">
                        <span class="meta-item">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($item['parent_name']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($item['parent_email']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i>
                            <?php echo $item['formatted_date']; ?>
                        </span>
                    </div>

                    <h3><?php echo htmlspecialchars($item['subject']); ?></h3>
                    
                    <div class="feedback-content">
                        <?php echo nl2br(htmlspecialchars($item['message'])); ?>
                    </div>
                    
                    <div class="feedback-actions">
                        <button class="btn btn-primary" onclick="showResponseModal(<?php echo $item['id']; ?>)">
                            <i class="fas fa-reply"></i> Respond
                        </button>
                        <?php if ($item['status'] === 'pending'): ?>
                            <button class="btn btn-warning" onclick="updateStatus(<?php echo $item['id']; ?>, 'in_progress')">
                                <i class="fas fa-hourglass-half"></i> Mark In Progress
                            </button>
                        <?php endif; ?>
                        <?php if ($item['status'] !== 'resolved'): ?>
                            <button class="btn btn-success" onclick="updateStatus(<?php echo $item['id']; ?>, 'resolved')">
                                <i class="fas fa-check"></i> Mark Resolved
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            
            <?php if (empty($feedback_items)): ?>
                <div class="feedback-card empty-feedback">
                    <i class="fas fa-inbox"></i>
                    <p>No feedback found matching your filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Feedback Table (for larger screens) -->
        <div class="feedback-table-container" style="display: none;">
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Parent Name</th>
                        <th>Parent Email</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedback_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['feedback_type']); ?></td>
                            <td><?php echo htmlspecialchars($item['parent_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['parent_email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo $item['formatted_date']; ?></td>
                            <td>
                                <button class="action-btn btn-respond" onclick="showResponseModal(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-reply"></i> Respond
                                </button>
                                <?php if ($item['status'] === 'pending'): ?>
                                    <button class="action-btn btn-progress" onclick="updateStatus(<?php echo $item['id']; ?>, 'in_progress')">
                                        <i class="fas fa-hourglass-half"></i> In Progress
                                    </button>
                                <?php endif; ?>
                                <?php if ($item['status'] !== 'resolved'): ?>
                                    <button class="action-btn btn-resolve" onclick="updateStatus(<?php echo $item['id']; ?>, 'resolved')">
                                        <i class="fas fa-check"></i> Resolve
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2><i class="fas fa-reply"></i> Respond to Feedback</h2>
            <form id="responseForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="feedback_id" id="feedback_id">
                <input type="hidden" name="status" value="in_progress">
                
                <div style="margin-bottom: 1rem;">
                    <label for="admin_response" style="display: block; margin-bottom: 0.5rem;">Your Response:</label>
                    <textarea name="admin_response" id="admin_response" rows="5" 
                            style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;"
                            required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Response
                </button>
            </form>
        </div>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('status-filter').value;
            const type = document.getElementById('type-filter').value;
            const date = document.getElementById('date-filter').value;
            
            window.location.href = `parent_feedback.php?status=${status}&type=${type}&date=${date}`;
        }

        function showResponseModal(feedbackId) {
            document.getElementById('feedback_id').value = feedbackId;
            document.getElementById('responseModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('responseModal').style.display = 'none';
        }

        function updateStatus(feedbackId, status) {
            if (confirm('Are you sure you want to update this feedback status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="feedback_id" value="${feedbackId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('responseModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Show success message if exists
        <?php if (isset($_SESSION['success_msg'])): ?>
            alert('<?php echo $_SESSION['success_msg']; ?>');
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        // Show error message if exists
        <?php if (isset($_SESSION['error_msg'])): ?>
            alert('<?php echo $_SESSION['error_msg']; ?>');
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    </script>
</body>
</html><?php
$conn->close();
?>