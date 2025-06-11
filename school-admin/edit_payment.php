    <?php
// Start session
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

// Check if payment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['payment_error'] = 'Payment ID is required.';
    header('Location: payments.php');
    exit;
}

$payment_id = intval($_GET['id']);
$payment = null;

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    
    // Validate input
    $errors = [];
    if ($student_id <= 0) {
        $errors[] = 'Please select a student.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    if (empty($payment_date)) {
        $errors[] = 'Payment date is required.';
    }
    if (!in_array($status, ['pending', 'completed', 'failed'])) {
        $errors[] = 'Invalid payment status.';
    }
    
    if (empty($errors)) {
        // Update payment
        $stmt = $conn->prepare("UPDATE payments SET student_id = ?, amount = ?, payment_date = ?, payment_method = ?, description = ?, status = ? WHERE id = ? AND school_id = ?");
        $stmt->bind_param('idssssii', $student_id, $amount, $payment_date, $payment_method, $description, $status, $payment_id, $school_id);
        
        if ($stmt->execute()) {
            $_SESSION['payment_success'] = 'Payment record updated successfully!';
            header('Location: payments.php');
            exit;
        } else {
            $_SESSION['payment_error'] = 'Failed to update payment record: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['payment_error'] = implode(' ', $errors);
    }
}

// Get payment details
try {
    $stmt = $conn->prepare("SELECT p.*, s.first_name, s.last_name, s.admission_number 
                          FROM payments p 
                          JOIN students s ON p.student_id = s.id 
                          WHERE p.id = ? AND p.school_id = ?");
    $stmt->bind_param('ii', $payment_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$payment) {
        $_SESSION['payment_error'] = 'Payment record not found.';
        header('Location: payments.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching payment: " . $e->getMessage());
    $_SESSION['payment_error'] = 'Error fetching payment record: ' . $e->getMessage();
    header('Location: payments.php');
    exit;
}

// Get all students for dropdown
$students = [];
try {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, admission_number FROM students WHERE school_id = ? ORDER BY first_name, last_name ASC");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['full_name'] = $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['admission_number'] . ')';
        $students[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// Get school info
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        /* Form Styles */
        .payment-form {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.1);
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background-color: var(--accent-color);
        }
        
        .btn-cancel {
            background-color: #999;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-left: 1rem;
        }
        
        .btn-cancel:hover {
            background-color: #777;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Edit Payment</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="payments.php">Payments</a>
                <span>/</span>
                <span>Edit Payment</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['payment_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['payment_error']; 
                unset($_SESSION['payment_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Payment Form -->
        <div class="payment-form">
            <h2 class="form-title">Edit Payment Record</h2>
            <form action="edit_payment.php?id=<?php echo $payment_id; ?>" method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student</label>
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($student['id'] == $payment['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" value="<?php echo $payment['amount']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo $payment['payment_date']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash" <?php echo ($payment['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo ($payment['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Credit Card" <?php echo ($payment['payment_method'] == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="Mobile Money" <?php echo ($payment['payment_method'] == 'Mobile Money') ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="Other" <?php echo ($payment['payment_method'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="pending" <?php echo ($payment['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo ($payment['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo ($payment['status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($payment['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1rem;">
                    <button type="submit" class="btn-submit">Update Payment</button>
                    <a href="payments.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>