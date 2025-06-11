<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Parent Dashboard - Request Permission Feature
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


$parentId = $_SESSION['parent_id'];
$error = '';
$success = '';

// Handle permission request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permission_request'])) {
    $request_text = trim($_POST['request_text']);
    if (empty($request_text)) {
        $error = 'Please enter your permission request.';
    } else {
        try {
            $conn = getDbConnection();
            
            // Check if permission_requests table exists with the expected structure
            $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
            
            if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
                // Check if students and student_parent tables exist
                $studentTableCheck = $conn->query("SHOW TABLES LIKE 'students'");
                $studentParentTableCheck = $conn->query("SHOW TABLES LIKE 'student_parent'");
                
                if ($studentTableCheck && $studentTableCheck->num_rows > 0 && 
                    $studentParentTableCheck && $studentParentTableCheck->num_rows > 0) {
                    // Get the first student associated with this parent (for demo purposes)
                    // In a real app, you would have the student selected by the parent
                    $studentStmt = $conn->prepare('SELECT s.id FROM students s 
                                                  JOIN student_parent sp ON s.id = sp.student_id 
                                                  WHERE sp.parent_id = ? LIMIT 1');
                    if (!$studentStmt) {
                        throw new Exception("Failed to prepare student query: " . $conn->error);
                    }
                
                    $studentStmt->bind_param('i', $parentId);
                    $studentStmt->execute();
                    $studentResult = $studentStmt->get_result();
                    
                    if ($studentResult->num_rows > 0) {
                        $studentRow = $studentResult->fetch_assoc();
                        $studentId = $studentRow['id'];
                    
                        // Current date for start/end date defaults
                        $currentDate = date('Y-m-d H:i:s');
                        $tomorrowDate = date('Y-m-d H:i:s', strtotime('+1 day'));
                        
                        // Insert into permission_requests with required fields
                        $stmt = $conn->prepare('INSERT INTO permission_requests 
                                              (student_id, parent_id, request_type, start_date, end_date, reason, status, created_at) 
                                              VALUES (?, ?, "other", ?, ?, ?, "pending", NOW())');
                        
                        if (!$stmt) {
                            throw new Exception("Failed to prepare insert query: " . $conn->error);
                        }
                        
                        $stmt->bind_param('iisss', $studentId, $parentId, $currentDate, $tomorrowDate, $request_text);
                        
                        if ($stmt->execute()) {
                            $success = 'Your permission request has been submitted.';
                        } else {
                            $error = 'Failed to submit your request: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'No student associated with your account. Please contact the school administrator.';
                    }
                    $studentStmt->close();
                } else {
                    // No student or student_parent tables, use simplified approach
                }
            } else {
                // Table doesn't exist - create a simplified version for this demo
                $createTableSQL = "CREATE TABLE IF NOT EXISTS permission_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    parent_id INT NOT NULL,
                    request_text TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                try {
                    if ($conn->query($createTableSQL)) {
                        // Now insert with the simplified structure
                        $stmt = $conn->prepare('INSERT INTO permission_requests (parent_id, request_text, status, created_at) VALUES (?, ?, "pending", NOW())');
                        if (!$stmt) {
                            throw new Exception("Failed to prepare simplified insert query: " . $conn->error);
                        }
                        
                        $stmt->bind_param('is', $parentId, $request_text);
                        
                        if ($stmt->execute()) {
                            $success = 'Your permission request has been submitted.';
                        } else {
                            $error = 'Failed to submit your request: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'System error: Could not create required database table: ' . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = 'System error during table creation: ' . $e->getMessage();
                    error_log("Parent dashboard table creation error: " . $e->getMessage());
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
        }
    }
}

// Fetch children associated with this parent
try {
    $conn = getDbConnection();
    $children = [];
    
    // Check if student_parent table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'student_parent'");
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        // Get all children associated with this parent
        $stmt = $conn->prepare("SELECT sp.student_id, sp.is_primary FROM student_parent sp WHERE sp.parent_id = ?");
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Get student details for each associated child
            while ($row = $result->fetch_assoc()) {
                $student_id = $row['student_id'];
                $is_primary = $row['is_primary'];
                
                // Get student details
                $student_stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                             FROM students s 
                                             JOIN schools sc ON s.school_id = sc.id 
                                             WHERE s.id = ?");
                $student_stmt->bind_param('i', $student_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                
                if ($student_result->num_rows > 0) {
                    $student = $student_result->fetch_assoc();
                    $student['is_primary'] = $is_primary;
                    $children[] = $student;
                }
                
                $student_stmt->close();
            }
        }
        
        $stmt->close();
    }
    
    // Fetch previous requests
    $requests = [];
    
    // Check if permission_requests table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        // Table exists, check its structure
        $columnsResult = $conn->query("SHOW COLUMNS FROM permission_requests");
        if (!$columnsResult) {
            throw new Exception("Failed to get permission_requests table structure: " . $conn->error);
        }
        
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Check if we're using the standard schema or simplified version
        $stmt = null;
        
        if (in_array('reason', $columns) && in_array('student_id', $columns)) {
            // Using standard schema - check if students table exists
            $studentTableCheck = $conn->query("SHOW TABLES LIKE 'students'");
            if ($studentTableCheck && $studentTableCheck->num_rows > 0) {
                // Get student field names
                $studentColumnsResult = $conn->query("SHOW COLUMNS FROM students");
                $studentColumns = [];
                if ($studentColumnsResult) {
                    while ($row = $studentColumnsResult->fetch_assoc()) {
                        $studentColumns[] = $row['Field'];
                    }
                }
                
                // Determine which fields to use for student name and ID
                $nameFields = "''";
                $idField = "''";
                
                if (in_array('first_name', $studentColumns) && in_array('last_name', $studentColumns)) {
                    $nameFields = "CONCAT(s.first_name, ' ', s.last_name)";
                } else if (in_array('name', $studentColumns)) {
                    $nameFields = "s.name";
                }
                
                if (in_array('admission_number', $studentColumns)) {
                    $idField = "s.admission_number";
                } else if (in_array('registration_number', $studentColumns)) {
                    $idField = "s.registration_number";
                }
                
                // Using standard schema with student join
                $stmt = $conn->prepare("SELECT pr.id, pr.reason as request_text, pr.status, pr.created_at, 
                                      $nameFields as student_name, $idField as student_id 
                                      FROM permission_requests pr 
                                      LEFT JOIN students s ON pr.student_id = s.id 
                                      WHERE pr.parent_id = ? 
                                      ORDER BY pr.created_at DESC");
            } else {
                // Students table doesn't exist, use simplified query
                $stmt = $conn->prepare("SELECT id, reason as request_text, status, created_at 
                                      FROM permission_requests 
                                      WHERE parent_id = ? 
                                      ORDER BY created_at DESC");
            }
        } else {
            // Using simplified schema
            $stmt = $conn->prepare("SELECT id, request_text, status, created_at 
                                  FROM permission_requests 
                                  WHERE parent_id = ? 
                                  ORDER BY created_at DESC");
        }
        
        if (!$stmt) {
            throw new Exception("Failed to prepare permission requests query: " . $conn->error);
        }
        
        $stmt->bind_param('i', $parentId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute permission requests query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Failed to get result from permission requests query: " . $stmt->error);
        }
        
        $requests = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    // Log the error but show empty requests to the user
    error_log("Parent dashboard permission requests error: " . $e->getMessage());
    $requests = [];
}

// --- School and Student Search Feature ---
$search_error = '';
$search_result = null;
$schools = []; // This will be used for both student search and add child modal

try {
    $conn = getDbConnection();
    
    // Check if schools table exists
    $schoolTableCheck = $conn->query("SHOW TABLES LIKE 'schools'");
    if ($schoolTableCheck && $schoolTableCheck->num_rows > 0) {
        // Table exists, fetch schools
        $schoolRes = $conn->query('SELECT id, name FROM schools ORDER BY name');
        if ($schoolRes) {
            while ($row = $schoolRes->fetch_assoc()) {
                $schools[] = $row;
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_search'])) {
        $selected_school = intval($_POST['school_id'] ?? 0);
        $student_query = trim($_POST['student_query'] ?? '');
        
        if ($selected_school && $student_query) {
            // Check if students table exists and has the expected structure
            $studentTableCheck = $conn->query("SHOW TABLES LIKE 'students'");
            
            if ($studentTableCheck && $studentTableCheck->num_rows > 0) {
                // Check for column names to determine the correct query
                $columnsResult = $conn->query("SHOW COLUMNS FROM students");
                if (!$columnsResult) {
                    throw new Exception("Failed to get student table structure: " . $conn->error);
                }
                
                $columns = [];
                while ($row = $columnsResult->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
                
                // Determine which fields to use based on available columns
                $stmt = null;
                
                if (in_array('first_name', $columns) && in_array('last_name', $columns)) {
                    // Check if admission_number exists, otherwise use registration_number
                    $id_field = in_array('admission_number', $columns) ? 'admission_number' : 'registration_number';
                    
                    // Using first_name and last_name fields
                    $stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ? 
                                          AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.$id_field LIKE ?)");
                } else if (in_array('name', $columns)) {
                    // Check if admission_number exists, otherwise use registration_number
                    $id_field = in_array('admission_number', $columns) ? 'admission_number' : 'registration_number';
                    
                    // Using single name field
                    $stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ? 
                                          AND (s.name LIKE ? OR s.$id_field LIKE ?)");
                } else {
                    // Fallback to a more generic query
                    $stmt = $conn->prepare('SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ?');
                }
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare student search query: " . $conn->error);
                }
                
                $like_query = "%" . $student_query . "%";
                
                // Bind parameters based on the number of parameters in the prepared statement
                if ($stmt->param_count == 3) {
                    $stmt->bind_param('iss', $selected_school, $like_query, $student_query);
                } else if ($stmt->param_count == 1) {
                    $stmt->bind_param('i', $selected_school);
                } else {
                    throw new Exception("Unexpected number of parameters in prepared statement");
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute student search query: " . $stmt->error);
                }
                
                $res = $stmt->get_result();
                if (!$res) {
                    throw new Exception("Failed to get result from student search query: " . $stmt->error);
                }
                
                $search_result = $res->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                if (empty($search_result)) {
                    $search_error = 'No student found with that name or registration number in the selected school.';
                }
            } else {
                $search_error = 'Student information is not available in the system.';
            }
        } else {
            $search_error = 'Please select a school and enter a student name or registration number.';
        }
    }
    $conn->close();
} catch (Exception $e) {
    $search_error = 'System error: ' . $e->getMessage();
    // Log the error for debugging
    error_log("Parent dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo"><?php echo APP_NAME; ?><span>.</span></a>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($parentName); ?></h3>
                <p>Parent</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-heading">Navigation</div>
            
            <div class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php">Dashboard</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-user-graduate"></i>
                <a href="#students">My Children</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-clipboard-list"></i>
                <a href="#permissions">Permission Requests</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <a href="#fees">Fee Information</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-chart-line"></i>
                <a href="#academics">Academic Progress</a>
            </div>
            
            <div class="menu-heading">Account</div>
            
            <div class="menu-item">
                <i class="fas fa-user-cog"></i>
                <a href="profile.php">My Profile</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Parent Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['add_child_error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['add_child_error']; unset($_SESSION['add_child_error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['add_child_success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['add_child_success']; unset($_SESSION['add_child_success']); ?></div>
        <?php endif; ?>
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($search_result ?? []); ?></h3>
                    <p>My Children</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($requests ?? []); ?></h3>
                    <p>Permission Requests</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo date('M Y'); ?></h3>
                    <p>Current Term</p>
                </div>
            </div>
        </div>
        
        <!-- Student Information Cards -->
        <div class="card" id="students">
            <div class="card-header">
                <h2><i class="fas fa-user-graduate"></i> My Children</h2>
                <a href="#" data-toggle="modal" data-target="#addChildModal">Add Child</a>
            </div>
            <div class="card-body">
                <?php if ($children && count($children) > 0): ?>
                    <div class="student-cards">
                        <?php foreach ($children as $student): ?>
                            <div class="student-card">
                                <div class="student-card-header">
                                    <h3><?php echo htmlspecialchars(isset($student['name']) ? $student['name'] : (isset($student['first_name']) ? $student['first_name'] . ' ' . $student['last_name'] : 'N/A')); ?></h3>
                                    <?php if ($student['is_primary']): ?>
                                        <span class="badge primary-badge">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <div class="student-card-body">
                                    <div class="student-card-avatar">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="student-info">
                                        <p><strong><?php echo htmlspecialchars(isset($student['registration_number']) ? $student['registration_number'] : (isset($student['admission_number']) ? $student['admission_number'] : 'N/A')); ?></strong></p>
                                        <p><?php echo htmlspecialchars($student['school_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="student-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Class:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['class'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Admission Date:</span>
                                            <span class="detail-value"><?php echo isset($student['admission_date']) ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A'; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Status:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['status'] ?? 'Active'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>You don't have any children associated with your account yet. Use the "Add Child" button to add your children.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Permission Requests Section -->
        <div class="card" id="permissions">
            <div class="card-header">
                <h2><i class="fas fa-clipboard-list"></i> Permission Requests</h2>
                <a href="#" data-toggle="modal" data-target="#newRequestModal">New Request</a>
            </div>
            <div class="card-body">
                <!-- New Permission Request Form -->
                <form method="POST" class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
                    <h3><i class="fas fa-plus-circle"></i> New Permission Request</h3>
                    <div class="form-group">
                        <label for="request_type">Request Type</label>
                        <select name="request_type" id="request_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="leave">Leave of Absence</option>
                            <option value="medical">Medical Appointment</option>
                            <option value="event">School Event</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_select">Select Child</label>
                        <select name="student_select" id="student_select">
                            <?php if (!empty($children)): ?>
                                <?php foreach ($children as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars(isset($student['name']) ? $student['name'] : (isset($student['first_name']) ? $student['first_name'] . ' ' . $student['last_name'] : 'N/A')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No children associated</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="datetime-local" name="start_date" id="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="datetime-local" name="end_date" id="end_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="request_text">Permission Request Details</label>
                        <textarea name="request_text" id="request_text" required></textarea>
                    </div>
                    
                    <button type="submit" name="permission_request" class="btn">Submit Request</button>
                </form>
                
                <!-- Previous Requests Table -->
                <h3><i class="fas fa-history"></i> Your Previous Requests</h3>
                <?php if (empty($requests)): ?>
                    <div class="alert alert-warning">
                        <p>You haven't made any permission requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Request</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php if (isset($request['student_name']) && !empty($request['student_name'])): ?>
                                                <?php echo htmlspecialchars($request['student_name']); ?>
                                                <br><small><?php echo htmlspecialchars($request['student_id']); ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['request_text']); ?></td>
                                        <td>
                                            <?php if (isset($request['start_date']) && isset($request['end_date'])): ?>
                                                <?php echo date('M d, Y', strtotime($request['start_date'])); ?> to
                                                <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Fee Information Section -->
        <div class="card" id="fees">
            <div class="card-header">
                <h2><i class="fas fa-money-bill-wave"></i> Fee Information</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Fee information will be available soon. Please check back later.</p>
                </div>
            </div>
        </div>
        
        <!-- Academic Progress Section -->
        <div class="card" id="academics">
            <div class="card-header">
                <h2><i class="fas fa-chart-line"></i> Academic Progress</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Academic progress information will be available soon. Please check back later.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include the Add Child Modal -->
    <?php include 'add_child_modal.php'; ?>
    
    <script>
        // JavaScript for smooth scrolling to sections
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Get all elements with data-toggle="modal"
            const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
            
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modalId = this.getAttribute('data-target');
                    const modal = document.querySelector(modalId);
                    
                    if (modal) {
                        modal.style.display = 'block';
                    }
                });
            });
            
            // Close modal when clicking on close button or outside the modal
            const closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
            
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });
            
            // Close modal when clicking outside of it
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    e.target.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>