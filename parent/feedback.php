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

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $feedbackText = trim($_POST['feedback_text']);
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

    if (empty($feedbackText)) {
        $error = 'Please enter your feedback.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT school_id FROM students WHERE parent_id = ? LIMIT 1");
            $stmt->bind_param("i", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $schoolData = $result->fetch_assoc();
            $schoolId = $schoolData['school_id'];

            // Check if parent_feedback table exists, if not create it
            $conn->query("CREATE TABLE IF NOT EXISTS parent_feedback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT NOT NULL,
                school_id INT NOT NULL,
                feedback_text TEXT NOT NULL,
                rating INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES parents(id),
                FOREIGN KEY (school_id) REFERENCES schools(id)
            )");

            $stmt = $conn->prepare("INSERT INTO parent_feedback (parent_id, school_id, feedback_text, rating) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $parentId, $schoolId, $feedbackText, $rating);
            
            if ($stmt->execute()) {
                $success = 'Your feedback has been submitted successfully!';
            } else {
                $error = 'Failed to submit feedback. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Feedback - School Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="dashboard-content">
        <div class="container mt-4">
            <h2>Submit Feedback</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback_text" rows="5" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating">
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>">
                                <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                    </form>
                </div>
            </div>
        </div>        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            padding: 5px;
            color: #ddd;
        }
        .rating input:checked ~ label {
            color: #ffd700;
        }
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
        }
        .card-body {
            padding: 2rem;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>
