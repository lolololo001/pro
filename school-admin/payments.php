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

// Get database connection
$conn = getDbConnection();

// Check if payments table exists
$result = $conn->query("SHOW TABLES LIKE 'payments'");
if ($result->num_rows == 0) {
    // Create payments table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50),
        description TEXT,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    
    // Delete the payment
    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $payment_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['payment_success'] = 'Payment record deleted successfully!';
    } else {
        $_SESSION['payment_error'] = 'Failed to delete payment record: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: payments.php');
    exit;
}

// Handle status update action
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $payment_id = intval($_GET['id']);
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'completed', 'failed'])) {
        $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ? AND school_id = ?");
        $stmt->bind_param('sii', $status, $payment_id, $school_id);
        
        if ($stmt->execute()) {
            $_SESSION['payment_success'] = 'Payment status updated successfully!';
        } else {
            $_SESSION['payment_error'] = 'Failed to update payment status: ' . $conn->error;
        }
        
        $stmt->close();
    } else {
        $_SESSION['payment_error'] = 'Invalid payment status.';
    }
    
    header('Location: payments.php');
    exit;
}

// Get all payments for this school with student information
$payments = [];
try {
    $stmt = $conn->prepare("SELECT p.*, s.first_name, s.last_name, s.admission_number 
                          FROM payments p 
                          JOIN students s ON p.student_id = s.id 
                          WHERE p.school_id = ? 
                          ORDER BY p.payment_date DESC");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $payments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    $_SESSION['payment_error'] = 'Error fetching payment records: ' . $e->getMessage();
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

// Process form submission for adding a new payment
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
        // Add new payment
        $stmt = $conn->prepare("INSERT INTO payments (school_id, student_id, amount, payment_date, payment_method, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iidssss', $school_id, $student_id, $amount, $payment_date, $payment_method, $description, $status);
        
        if ($stmt->execute()) {
            $_SESSION['payment_success'] = 'Payment record added successfully!';
            header('Location: payments.php');
            exit;
        } else {
            $_SESSION['payment_error'] = 'Failed to add payment record: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['payment_error'] = implode(' ', $errors);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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
        
        .add-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .add-btn:hover {
            background-color: var(--accent-color);
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
        
        /* Table Styles */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .payments-table th,
        .payments-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .payments-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .payments-table tr:last-child td {
            border-bottom: none;
        }
        
        .payments-table tr:hover {
            background-color: var(--gray-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit,
        .btn-delete,
        .btn-status {
            padding: 0.5rem;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
        }
        
        .btn-delete {
            background-color: #f44336;
        }
        
        .btn-status {
            background-color: #2196f3;
        }
        
        .btn-edit:hover {
            background-color: var(--accent-color);
        }
        
        .btn-delete:hover {
            background-color: #d32f2f;
        }
        
        .btn-status:hover {
            background-color: #1976d2;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #999;
            margin-bottom: 1rem;
        }
        
        .empty-text {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .add-btn {
                margin-top: 1rem;
            }
            
            .payments-table {
                display: block;
                overflow-x: auto;
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
            <div>
                <h1>Manage Payments</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Home</a>
                    <span>/</span>
                    <span>Payments</span>
                </div>
            </div>
            <button class="add-btn" onclick="toggleForm()">
                <i class="fas fa-plus"></i> Add New Payment
            </button>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['payment_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['payment_success']; 
                unset($_SESSION['payment_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['payment_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['payment_error']; 
                unset($_SESSION['payment_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Payment Form -->
        <div class="payment-form" id="paymentForm" style="display: none;">
            <h2 class="form-title">Add New Payment</h2>
            <form action="payments.php" method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student</label>
                        <select name="student_id" id="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1rem;">
                    <button type="submit" class="btn-submit">Add Payment</button>
                    <button type="button" class="btn-submit" style="background-color: #999;" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Payments Table -->
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="empty-text">No payment records found</div>
                <button class="add-btn" onclick="toggleForm()">
                    <i class="fas fa-plus"></i> Add Your First Payment
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['student_name']); ?> (<?php echo htmlspecialchars($payment['admission_number']); ?>)</td>
                                <td><?php echo '$' . number_format($payment['amount'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_payment.php?id=<?php echo $payment['id']; ?>" class="btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($payment['status'] == 'pending'): ?>
                                            <a href="payments.php?action=update_status&id=<?php echo $payment['id']; ?>&status=completed" class="btn-status" title="Mark as Completed">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php elseif ($payment['status'] == 'completed'): ?>
                                            <a href="payments.php?action=update_status&id=<?php echo $payment['id']; ?>&status=pending" class="btn-status" title="Mark as Pending">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="payments.php?action=delete&id=<?php echo $payment['id']; ?>" class="btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this payment record?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleForm() {
            const form = document.getElementById('paymentForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>