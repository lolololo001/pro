<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load Composer's autoloader
require_once '../vendor/autoload.php';
require_once '../config/config.php';

session_start();
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Initialize database connection
try {
    $conn = getDbConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// Start output buffering
ob_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Add notification divs at the top of the page
?>
<div id="notification" class="notification" style="display: none;"></div>
<style>
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 4px;
    color: white;
    font-weight: 500;
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
    max-width: 400px;
}

.notification.success {
    background-color: #4CAF50;
}

.notification.error {
    background-color: #f44336;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.confirmation-dialog {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
}

.confirmation-dialog .buttons {
    margin-top: 20px;
    text-align: right;
}

.confirmation-dialog button {
    margin-left: 10px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.confirmation-dialog .confirm {
    background: #4CAF50;
    color: white;
}

.confirmation-dialog .cancel {
    background: #f44336;
    color: white;
}

.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}
</style>

<div id="confirmationDialog" class="confirmation-dialog">
    <h3>Confirm Action</h3>
    <p id="confirmationMessage"></p>
    <div class="buttons">
        <button class="cancel" onclick="hideConfirmation()">Cancel</button>
        <button class="confirm" id="confirmButton">Confirm</button>
    </div>
</div>
<div id="overlay" class="overlay"></div>

<?php
$school_id = $_SESSION['school_admin_school_id'];
$admin_id = $_SESSION['school_admin_id'];
$error = '';
$success = '';

// Handle permission request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    // Ensure no output has been sent before
    if (!headers_sent()) {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        // Set proper content type for JSON response
        header('Content-Type: application/json');
    }
    
    // Disable error output
    ini_set('display_errors', 0);
    error_reporting(0);
    
    // Start output buffering
    ob_start();
    
    try {
        // Validate request is AJAX
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            throw new Exception('Invalid request type');
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token');
        }
          // Validate request ID
        $requestId = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
        if (!$requestId) {
            throw new Exception('Invalid request ID');
        }
        
        // Validate CSRF token again (double check)
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Security token validation failed');
        }
        
        // Validate action
        $action = $_POST['action'];
        if (!in_array($action, ['approve', 'reject'])) {
            throw new Exception('Invalid action');
        }
          // Get comment
        $comment = trim($_POST['response_comment'] ?? '');
        
        // If rejecting, comment is required
        if ($action === 'reject' && empty($comment)) {
            throw new Exception('A reason is required for rejection');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update permission request status
            $stmt = $conn->prepare("                UPDATE permission_requests 
                SET status = ?, 
                    response_comment = ?,
                    responded_by = ?,
                    updated_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ");
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('ssii', $action, $comment, $_SESSION['school_admin_id'], $requestId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Request not found or already processed');
            }
              // Get request details for notification
            $stmt = $conn->prepare("
                SELECT pr.*, 
                       s.first_name as student_first_name, 
                       s.last_name as student_last_name,
                       CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       s.reg_number,
                       p.email as parent_email, 
                       p.first_name as parent_first_name,
                       p.last_name as parent_last_name,
                       sc.name as school_name
                FROM permission_requests pr
                JOIN students s ON pr.student_id = s.id
                JOIN parents p ON pr.parent_id = p.id
                JOIN schools sc ON s.school_id = sc.id
                WHERE pr.id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            
            if (!$request) {
                throw new Exception('Could not fetch request details');
            }
            
            // Create and configure PHPMailer instance
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->SMTPDebug = 0; // Disable debug output
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'schoolcomm001@gmail.com';
                $mail->Password = 'nuos orzj keap bszp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
                
                // Set high priority
                $mail->addCustomHeader('X-Priority', '1');
                $mail->addCustomHeader('X-MSMail-Priority', 'High');
                $mail->addCustomHeader('Importance', 'High');
                
                // Email configuration
                $mail->setFrom('schoolcomm001@gmail.com', $request['school_name']);
                $mail->addAddress($request['parent_email'], $request['parent_first_name'] . ' ' . $request['parent_last_name']);
                
                // Generate email content
                $statusText = $action === 'approve' ? 'Approved' : 'Rejected';
                $actionColor = $action === 'approve' ? '#4CAF50' : '#f44336';
                
                $body = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #00704a; margin-bottom: 20px;'>Permission Request {$statusText}</h2>
                            <p><strong>Dear {$request['parent_first_name']},</strong></p>
                            <p>Your permission request for {$request['student_name']} has been <span style='color: {$actionColor}; font-weight: bold;'>{$action}d</span>.</p>
                            " . ($comment ? "<div style='background: #f5f5f5; padding: 15px; border-left: 4px solid {$actionColor}; margin: 15px 0;'>
                                <strong>Response:</strong><br>{$comment}
                            </div>" : "") . "
                            <p>Request Details:</p>
                            <ul style='background: #f9f9f9; padding: 15px;'>
                                <li><strong>Student:</strong> {$request['student_name']}" . 
                                    ($request['reg_number'] ? " (ID: " . $request['reg_number'] . ")" : "") . "</li>
                                <li><strong>Request Date:</strong> " . date('F j, Y', strtotime($request['created_at'])) . "</li>
                                <li><strong>Status:</strong> <span style='color: {$actionColor};'>{$statusText}</span></li>
                            </ul>
                            <p>If you have any questions, please don't hesitate to contact us.</p>
                            <br>
                            <p>Best regards,<br>{$request['school_name']}</p>
                        </div>
                    </body>
                    </html>
                ";
                
                $mail->isHTML(true);
                $mail->Subject = "Permission Request {$statusText} - {$request['school_name']}";
                $mail->Body = $body;
                $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));
                
                $mail->send();
                
                // Commit transaction
                $conn->commit();
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Request has been ' . $action . 'd successfully and notification sent',
                    'request_id' => $requestId,
                    'action' => $action
                ]);
                exit;
                
            } catch (PHPMailerException $e) {
                error_log("Failed to send email notification: " . $e->getMessage());
                // Continue with success response even if email fails
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Request has been ' . $action . 'd successfully, but notification email could not be sent',
                    'request_id' => $requestId,
                    'action' => $action
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
          } catch (Exception $e) {
        error_log("Permission request error: " . $e->getMessage());
        // Clean any previous output
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Only proceed with HTML output if it's not an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {

// Calculate statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

// Fetch all permission requests for this school
$requests = [];
try {
    $conn = getDbConnection();
    
    // Join with students, parents tables to get names
    $query = 'SELECT pr.*, 
              s.first_name AS student_first_name, s.last_name AS student_last_name, 
              s.reg_number, 
              p.first_name AS parent_first_name, p.last_name AS parent_last_name,
              p.email AS parent_email, p.phone AS parent_phone,
              sa.full_name AS admin_name
              FROM permission_requests pr
              JOIN students s ON pr.student_id = s.id
              JOIN parents p ON pr.parent_id = p.id
              LEFT JOIN school_admins sa ON pr.responded_by = sa.id
              WHERE s.school_id = ?
              ORDER BY pr.created_at DESC';
      $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
        
        // Update stats
        $stats['total']++;
        switch ($row['status']) {
            case 'pending':
                $stats['pending']++;
                break;
            case 'approved':
                $stats['approved']++;
                break;
            case 'rejected':
                $stats['rejected']++;
                break;
        }
    }
    
    $stmt->close();
} 
catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    error_log("Permission fetch error: " . $e->getMessage());
}

// Get school info for sidebar
$school_info = [];
try {
    $conn = getDbConnection();
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Requests - School Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR ?? '#00704a'; ?>;
            --footer-color: <?php echo FOOTER_COLOR ?? '#f8c301'; ?>;
            --accent-color: <?php echo ACCENT_COLOR ?? '#00704a'; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --info-color: #2196f3;
            --sidebar-width: 250px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --transition: all 0.3s ease;
            --header-height: 60px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--gray-color);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--primary-color);
            padding: 1.5rem;
            overflow-y: auto;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            z-index: 1000;
            color: var(--light-color);
        }

        .sidebar-header {
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
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

        .school-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .school-logo, .school-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .school-logo-placeholder i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .sidebar-logo span {
            color: var(--footer-color);
        }

        .sidebar-user {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin: 1rem -1.5rem;
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
        }        .menu-item {
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
            color: var(--light-color);
        }        .menu-item a {
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
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        /* Card Styles */
        .card {
            background-color: var(--light-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            font-weight: 600;
            background-color: var(--gray-color);
            color: var(--primary-color);
        }

        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 1rem;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var,--light-color;
        }

        .btn-primary:hover {
            background-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var,--light-color;
        }

        .btn-danger:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
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

        /* Stats Grid Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.2s;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
            opacity: 0;
            transition: var(--transition);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            background: var(--primary-color);
            color: var,--light-color;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-info p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Filter Bar Styles */        .filter-bar {
            background: linear-gradient(135deg, var(--light-color) 0%, #f8f9fa 100%);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .filter-bar:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--light-color);
            color: var(--dark-color);
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 1rem) center;
            padding-right: 2.5rem;
        }

        .filter-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 112, 74, 0.1);
            outline: none;
        }

        .filter-summary {
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: var,--light-color;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Permission Cards */
        .permissions-container {
            display: grid;
            gap: 1.5rem;
        }        .permission-card {
            background: var(--light-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
            position: relative;
        }
        
        .permission-card[data-status="approved"] {
            border-left: 4px solid var(--success-color);
        }
        
        .permission-card[data-status="rejected"] {
            border-left: 4px solid var(--danger-color);
        }
        
        .permission-card[data-status="pending"] {
            border-left: 4px solid var(--warning-color);
        }.permission-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px) translateZ(0);
        }

        .permission-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: var,--light-color;
            position: relative;
            overflow: hidden;
        }

        .permission-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255,255,255,0.1), transparent);
            pointer-events: none;
        }

        .permission-title {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .permission-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }        .permission-details {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            position: relative;
        }

        .student-info, .parent-info {
            padding: 1.5rem;
            background: linear-gradient(to bottom, var(--light-color), #f8f9fa);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            height: 100%;
            transition: all 0.3s ease;
        }

        .student-info:hover, .parent-info:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .info-header i {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .info-header h4 {
            font-size: 1.1rem;
            color: var(--primary-color);
            margin: 0;
        }

        .info-content {
            display: grid;
            gap: 0.8rem;
        }

        .info-item {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            min-width: 100px;
        }

        .info-value {
            color: #666;
        }

        .date-range {
            display: flex;
            gap: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
        }

        .permission-reason {
            padding: 1rem;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }

        .permission-actions {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            background: #f8f9fa;
        }

        .btn-approve, .btn-reject {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-approve {
            background: var(--success-color);
            color: var,--light-color;
        }

        .btn-reject {
            background: var(--danger-color);
            color: var,--light-color;
        }

        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }        .response-form {
            display: none;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--radius-lg);
            margin-top: 1rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            transform: translateY(-10px);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 10;
        }

        .response-form[data-open="true"] {
            opacity: 1;
            transform: translateY(0);
        }

        .response-form-inner {
            background: #fff;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 2px solid;
            background: #f8f9fa;
        }

        .response-form[data-action="approve"] .response-header {
            border-bottom-color: var(--success-color);
        }

        .response-form[data-action="reject"] .response-header {
            border-bottom-color: var(--danger-color);
        }

        .response-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            color: #2c3e50;
        }

        .response-header .close-btn {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            font-size: 1.2rem;
            color: #64748b;
            transition: color 0.2s;
        }

        .response-header .close-btn:hover {
            color: #475569;
        }

        .response-form form {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .response-label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
        }

        .required-indicator {
            color: var(--danger-color);
            font-weight: bold;
        }

        .response-textarea {
            width: 100%;
            min-height: 120px;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            resize: vertical;
            font-size: 0.95rem;
            line-height: 1.5;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .response-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            outline: none;
        }

        .comment-help {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .comment-help.required {
            color: var(--danger-color);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn-cancel {
            padding: 0.6rem 1.2rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }

        .btn-submit {
            padding: 0.6rem 1.5rem;
            border: none;
            color: #fff;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit.btn-approve {
            background: var(--success-color);
        }

        .btn-submit.btn-reject {
            background: var(--danger-color);
        }

        .btn-submit:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Response comment display */
        .response-comment {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-left: 3px solid var(--primary-color);
            border-radius: var(--radius-sm);
        }
        
        .response-comment p:first-child {
            margin-top: 0;
            color: #475569;
            font-weight: 500;
        }
        
        .response-comment p:last-child {
            margin-bottom: 0;
            color: #334155;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
      <div class="main-content">
        <div class="page-header">
            <h1>Permission Requests</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Permissions</span>
            </div>
        </div>
        
        <!-- Original permission management content starts here -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filterRequests('pending')">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filterRequests('approved')">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filterRequests('rejected')">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
          <div class="filter-bar">
            <div class="filter-group">
                <select id="statusFilter">
                    <option value="all">All Requests</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                <select id="typeFilter">
                    <option value="all">All Types</option>
                    <option value="leave">Leave of Absence</option>
                    <option value="medical">Medical</option>
                    <option value="event">Event</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="filter-summary">
                <span id="requestCount" class="badge badge-primary">0 requests</span>
            </div>
        </div>
        
        <div class="permissions-container">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">
                    <p>No permission requests found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="permission-card" data-status="<?php echo htmlspecialchars($request['status']); ?>" data-type="<?php echo htmlspecialchars($request['request_type']); ?>" id="request-<?php echo $request['id']; ?>">
                        <div class="permission-header">
                            <div class="permission-title">
                                <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?> Request
                                <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                </span>
                            </div>
                            <div class="permission-date">
                                Submitted: <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="permission-details">
                            <div class="student-info">
                                <p><i class="fas fa-user-graduate"></i> <strong>Student:</strong> 
                                    <?php echo htmlspecialchars($request['student_first_name'] . ' ' . $request['student_last_name']); ?>
                                    <small>(<?php echo htmlspecialchars($request['admission_number']); ?>)</small>
                                </p>
                            </div>
                            
                            <div class="parent-info">
                                <p><i class="fas fa-user"></i> <strong>Parent:</strong> 
                                    <?php echo htmlspecialchars($request['parent_first_name'] . ' ' . $request['parent_last_name']); ?>
                                </p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['parent_email']); ?></p>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['parent_phone']); ?></p>
                            </div>
                            
                            <div class="date-range">
                                <span><i class="fas fa-calendar-alt"></i> <strong>From:</strong> <?php echo date('M d, Y h:i A', strtotime($request['start_date'])); ?></span>
                                <span><i class="fas fa-calendar-alt"></i> <strong>To:</strong> <?php echo date('M d, Y h:i A', strtotime($request['end_date'])); ?></span>
                            </div>
                            
                            <div class="permission-reason">
                                <p><strong>Reason:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                            </div>
                            
                            <?php if (!empty($request['response_comment']) && ($request['status'] === 'approved' || $request['status'] === 'rejected')): ?>
                                <div class="response-comment">
                                    <p><strong>Response from <?php echo htmlspecialchars($request['admin_name']); ?>:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($request['response_comment'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>                            <?php if ($request['status'] === 'pending'): ?>
                            <div class="permission-actions">
                                <button type="button" class="btn-approve" onclick="showResponseForm(this, 'approve', <?php echo $request['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn-reject" onclick="showResponseForm(this, 'reject', <?php echo $request['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>

                            <!-- Enhanced Response Form -->
                            <div class="response-form" id="response-form-<?php echo $request['id']; ?>" style="display: none;">
                                <div class="response-form-inner">
                                    <div class="response-header">
                                        <h3 id="response-title-<?php echo $request['id']; ?>"></h3>
                                        <button type="button" class="close-btn" onclick="hideResponseForm(<?php echo $request['id']; ?>)" title="Close form">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    
                                    <form method="POST" onsubmit="return handleSubmit(event, <?php echo $request['id']; ?>)">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="action" id="action-<?php echo $request['id']; ?>" value="">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        
                                        <div class="response-summary">
                                            <div class="request-meta">
                                                <p><strong>Student:</strong> <?php echo htmlspecialchars($request['student_first_name'] . ' ' . $request['student_last_name']); ?></p>
                                                <p><strong>Parent:</strong> <?php echo htmlspecialchars($request['parent_first_name'] . ' ' . $request['parent_last_name']); ?></p>
                                                <p><strong>Type:</strong> <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="response-<?php echo $request['id']; ?>" class="response-label">
                                                <span id="required-indicator-<?php echo $request['id']; ?>" class="required-indicator" style="display: none;">*</span>
                                                Response Message
                                            </label>
                                            <textarea 
                                                id="response-<?php echo $request['id']; ?>"
                                                name="response_comment" 
                                                class="response-textarea"
                                                rows="4"
                                                placeholder=""
                                                aria-required="false"
                                            ></textarea>
                                            <small id="comment-help-<?php echo $request['id']; ?>" class="comment-help"></small>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" class="btn-cancel" onclick="hideResponseForm(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-arrow-left"></i> Cancel
                                            </button>
                                            <button type="submit" id="submit-btn-<?php echo $request['id']; ?>" class="btn-submit">
                                                <i class="fas fa-paper-plane"></i> Send Response
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>    <script>
document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const requestCount = document.getElementById('requestCount');

    // Show notification function
    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    }

    // Show confirmation dialog
    function showConfirmation(message, callback) {
        const dialog = document.getElementById('confirmationDialog');
        const overlay = document.getElementById('overlay');
        const confirmBtn = document.getElementById('confirmButton');
        const messageElement = document.getElementById('confirmationMessage');
        
        messageElement.textContent = message;
        dialog.style.display = 'block';
        overlay.style.display = 'block';
        
        const handleConfirm = () => {
            hideConfirmation();
            callback();
            confirmBtn.removeEventListener('click', handleConfirm);
        };
        
        confirmBtn.addEventListener('click', handleConfirm);
    }

    // Hide confirmation dialog
    function hideConfirmation() {
        document.getElementById('confirmationDialog').style.display = 'none';
        document.getElementById('overlay').style.display = 'none';
    }

    // Process form submission
    function processAction(action, requestId, comment) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('request_id', requestId);
        formData.append('comment', comment);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification(result.message || 'An error occurred', 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while processing your request', 'error');
            console.error('Error:', error);
        });
    }

    // Show response form
    window.showResponseForm = function(button, action, requestId) {
        // Hide any other open forms first
        document.querySelectorAll('.response-form').forEach(form => {
            if (form.id !== `response-form-${requestId}` && form.style.display !== 'none') {
                hideResponseForm(form.id.replace('response-form-', ''));
            }
        });

        const formContainer = document.getElementById(`response-form-${requestId}`);
        const form = formContainer.querySelector('form');
        const title = document.getElementById(`response-title-${requestId}`);
        const commentArea = document.getElementById(`response-${requestId}`);
        const helpText = document.getElementById(`comment-help-${requestId}`);
        const requiredIndicator = document.getElementById(`required-indicator-${requestId}`);
        const submitBtn = document.getElementById(`submit-btn-${requestId}`);
        const actionInput = document.getElementById(`action-${requestId}`);
        
        // Reset form state
        form.reset();
        actionInput.value = action;
        
        // Update UI elements based on action type
        title.innerHTML = action === 'approve' ? 
            '<i class="fas fa-check-circle"></i> Approve Request' : 
            '<i class="fas fa-times-circle"></i> Reject Request';
        
        commentArea.placeholder = action === 'approve' 
            ? 'Add an optional message for the parent... (e.g., "Request approved. Have a great day!")' 
            : 'Please provide a clear reason for rejecting this request... (Required)';
        
        helpText.textContent = action === 'approve'
            ? 'Optional: Add a friendly message to the parent'
            : 'Required: Please explain why this request is being rejected';
        
        helpText.className = action === 'approve' ? 'comment-help' : 'comment-help required';
        requiredIndicator.style.display = action === 'reject' ? 'inline' : 'none';
        
        // Update button styles
        submitBtn.className = 'btn-submit ' + (action === 'approve' ? 'btn-approve' : 'btn-reject');
        submitBtn.innerHTML = '<i class="fas fa-' + (action === 'approve' ? 'check' : 'times') + '"></i> ' +
            (action === 'approve' ? 'Approve' : 'Reject') + ' Request';
        
        // Show form with animation
        formContainer.style.display = 'block';
        formContainer.dataset.action = action;
        formContainer.dataset.open = 'true';
        
        requestAnimationFrame(() => {
            formContainer.style.opacity = '1';
            formContainer.style.transform = 'translateY(0)';
            commentArea.focus();
        });
    };

    // Handle form submission
    window.handleSubmit = function(event, requestId) {
        event.preventDefault();
        var form = event.target;
        var actionInput = document.getElementById('action-' + requestId);
        var commentArea = document.getElementById('response-' + requestId);
        var action = actionInput.value;
        var comment = commentArea.value.trim();
        
        // Enhanced validation for rejections
        if (action === 'reject') {
            if (!comment) {
                showNotification('Please provide a reason for rejection', 'error');
                commentArea.focus();
                return false;
            }
            if (comment.length < 10) {
                showNotification('Please provide a more detailed reason for rejection (at least 10 characters)', 'error');
                commentArea.focus();
                return false;
            }
        }
          // Show confirmation dialog with appropriate message
        var message = action === 'approve' 
            ? (comment ? 'Approve this request with the provided comment?' : 'Approve this request?')
            : 'Reject this request with the following reason?\n\n"' + comment + '"';
        
        showConfirmation(message, function() {
            var formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', action);
            formData.append('response_comment', comment);
            formData.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json(); 
            })
            .then(function(result) {
                if (result.success) {
                    showNotification(result.message, 'success');
                    hideResponseForm(requestId);
                    
                    // Update UI
                    var card = document.getElementById('request-' + requestId);
                    if (card) {
                        // Update status badge
                        var statusBadge = card.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.className = 'status-badge status-' + action;
                            statusBadge.innerHTML = action === 'approve' 
                                ? '<i class="fas fa-check-circle"></i> Approved'
                                : '<i class="fas fa-times-circle"></i> Rejected';
                        }
                        
                        // Update card status
                        card.dataset.status = action;
                        
                        // Hide action buttons
                        var actionButtons = card.querySelector('.permission-actions');
                        if (actionButtons) {
                            actionButtons.remove();
                        }
                        
                        // Add response comment if provided
                        if (comment) {
                            var commentSection = document.createElement('div');
                            commentSection.className = 'response-comment';
                            commentSection.innerHTML = 
                                '<p><strong>Response:</strong></p>' +
                                '<p>' + comment + '</p>';
                            card.querySelector('.permission-details').appendChild(commentSection);
                        }
                        
                        // Update stats
                        document.querySelectorAll('.stat-card').forEach(function(stat) {
                            var count = stat.querySelector('h3');
                            if (count) {
                                if (stat.textContent.toLowerCase().includes('pending')) {
                                    count.textContent = parseInt(count.textContent) - 1;
                                } else if (stat.textContent.toLowerCase().includes(action)) {
                                    count.textContent = parseInt(count.textContent) + 1;
                                }
                            }
                        });
                    }

                    // Reload page after a short delay to ensure everything is up to date
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(result.message || 'An error occurred while processing the request', 'error');
                    console.error('Server error:', result);
                }
            })
            .catch(function(error) {
                console.error('Error details:', error);
                showNotification('An error occurred: ' + error.message, 'error');
            });
        });
        
        return false;
    };

    // Hide response form
    window.hideResponseForm = function(requestId) {
        var form = document.getElementById('response-form-' + requestId);
        if (!form) return;
        
        form.style.opacity = '0';
        form.style.transform = 'translateY(-10px)';
        form.dataset.open = 'false';
        
        setTimeout(function() {
            form.style.display = 'none';
            form.querySelector('form').reset();
        }, 300);
    };
});
    </script>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile && !event.target.closest('.sidebar') && !event.target.closest('.sidebar-toggle')) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>