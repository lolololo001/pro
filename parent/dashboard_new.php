<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';

session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get parent information
$parentId = $_SESSION['parent_id'];
$parentName = isset($_SESSION['parent_name']) ? $_SESSION['parent_name'] : 'Parent User';
$parentEmail = isset($_SESSION['parent_email']) ? $_SESSION['parent_email'] : '';

$error = '';
$success = '';
$feedbackError = '';
$feedbackSuccess = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $feedbackText = trim($_POST['message']);
    
    if (empty($feedbackText)) {
        $feedbackError = 'Please enter your feedback.';
    } else {
        try {
            $conn = getDbConnection();

            // Get the school_id first
            $schoolQuery = "SELECT DISTINCT s.school_id 
                          FROM students s 
                          INNER JOIN student_parent sp ON s.id = sp.student_id 
                          WHERE sp.parent_id = ? 
                          LIMIT 1";
            
            $stmt = $conn->prepare($schoolQuery);
            if (!$stmt) {
                throw new Exception("Failed to prepare school query: " . $conn->error);
            }
            
            $stmt->bind_param('i', $parentId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute school query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("No school found for this parent");
            }
            
            $schoolData = $result->fetch_assoc();
            $schoolId = $schoolData['school_id'];
            $stmt->close();

            // Insert feedback directly without recreating the table
            $insertSQL = "INSERT INTO parent_feedback (parent_id, message, school_id) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertSQL);
            if (!$insertStmt) {
                throw new Exception("Failed to prepare insert statement: " . $conn->error);
            }
            
            $insertStmt->bind_param('isi', $parentId, $feedbackText, $schoolId);
            if ($insertStmt->execute()) {
                $feedbackSuccess = "Thank you! Your feedback has been submitted successfully.";
            } else {
                throw new Exception("Failed to insert feedback: " . $insertStmt->error);
            }
            
            $insertStmt->close();
            $conn->close();
            
        } catch (Exception $e) {
            $feedbackError = "An error occurred while submitting feedback: " . $e->getMessage();
            error_log("Feedback submission error: " . $e->getMessage());
        }
    }
}

try {
    $conn = getDbConnection();
    $children = [];

    // Get all children associated with this parent
    $stmt = $conn->prepare("SELECT sp.student_id, sp.is_primary, s.first_name, s.last_name, s.admission_number, s.registration_number, s.class_name, s.grade_level
                           FROM student_parent sp
                           JOIN students s ON sp.student_id = s.id
                           WHERE sp.parent_id = ?
                           ORDER BY sp.is_primary DESC, s.first_name ASC");
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $children[] = $row;
    }
    $stmt->close();

    // Fetch permission requests with statistics
    $permission_stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'recent' => 0
    ];

    $requests = [];

    $stmt = $conn->prepare("SELECT pr.*, s.first_name, s.last_name, s.admission_number, s.registration_number
                           FROM permission_requests pr
                           JOIN students s ON pr.student_id = s.id
                           WHERE pr.parent_id = ?
                           ORDER BY pr.created_at DESC");
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
        $permission_stats['total']++;
        $permission_stats[$row['status']]++;
        
        // Count requests from the last 7 days as recent
        if (strtotime($row['created_at']) > strtotime('-7 days')) {
            $permission_stats['recent']++;
        }
    }
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $error = 'System error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="container mt-4">
                <!-- Children Section -->
                <div class="card mb-4" id="children">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> My Children</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($children)): ?>
                            <div class="alert alert-info">
                                <p>No children are currently associated with your account.</p>
                                <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#addChildModal">
                                    <i class="fas fa-plus"></i> Add Child
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Admission Number</th>
                                            <th>Registration Number</th>
                                            <th>Class</th>
                                            <th>Grade Level</th>
                                            <th>Primary Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($children as $child): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($child['admission_number']); ?></td>
                                                <td><?php echo htmlspecialchars($child['registration_number']); ?></td>
                                                <td><?php echo htmlspecialchars($child['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($child['grade_level']); ?></td>
                                                <td>
                                                    <?php if ($child['is_primary']): ?>
                                                        <span class="badge bg-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Feedback Section -->
                <div class="card mb-4" id="feedback">
                    <div class="card-header">
                        <h2><i class="fas fa-comment"></i> Provide Feedback</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($feedbackSuccess): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($feedbackSuccess); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($feedbackError): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($feedbackError); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="message" class="form-label">Your Feedback or Suggestion</label>
                                <textarea class="form-control" id="message" name="message" rows="4" 
                                        placeholder="Please share your thoughts, suggestions, or concerns about the school..." required></textarea>
                                <div class="form-text">Your feedback helps us improve our services and address any concerns you may have.</div>
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Feedback
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Permissions Section -->
                <div class="card mb-4" id="permissions">
                    <div class="card-header">
                        <h2><i class="fas fa-key"></i> Permission Requests</h2>
                    </div>
                    <div class="card-body">
                        <!-- Rest of the permissions section -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
