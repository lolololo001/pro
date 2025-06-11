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

// Handle permission request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permission_request'])) {
    $request_text = trim($_POST['request_text']);
    $student_id = intval($_POST['student_id'] ?? 0);
    $request_type = $_POST['request_type'] ?? 'other';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+1 day'));

    if (empty($request_text)) {
        $error = 'Please enter your permission request.';
    } elseif ($student_id == 0) {
        $error = 'Please select a student.';
    } else {
        try {
            $conn = getDbConnection();

            // Insert permission request
            $stmt = $conn->prepare('INSERT INTO permission_requests
                                  (student_id, parent_id, request_type, start_date, end_date, reason, status, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())');

            if (!$stmt) {
                throw new Exception("Failed to prepare insert query: " . $conn->error);
            }

            $stmt->bind_param('iissss', $student_id, $parentId, $request_type, $start_date, $end_date, $request_text);

            if ($stmt->execute()) {
                $success = 'Your permission request has been submitted successfully.';
            } else {
                $error = 'Failed to submit your request: ' . $stmt->error;
            }
            $stmt->close();
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

        // Count recent requests (last 7 days)
        if (strtotime($row['created_at']) >= strtotime('-7 days')) {
            $permission_stats['recent']++;
        }
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Permission requests error: " . $e->getMessage());
    $children = [];
    $requests = [];
    $permission_stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'recent' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Requests - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Professional Stats Cards - Matching Students.php Design */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
            width: 100%;
            overflow-x: auto;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            transition: opacity 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .stat-card:hover::before {
            opacity: 0.15;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        /* Card variants for permissions */
        .all-requests {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .all-requests .stat-icon {
            color: #00704a;
            background: rgba(0, 112, 74, 0.1);
        }

        .pending-requests {
            background: linear-gradient(135deg, #ffffff 0%, #fff3e0 100%);
        }

        .pending-requests .stat-icon {
            color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }

        .approved-requests {
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
        }

        .approved-requests .stat-icon {
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .rejected-requests {
            background: linear-gradient(135deg, #ffffff 0%, #ffebee 100%);
        }

        .rejected-requests .stat-icon {
            color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }

        .recent-requests {
            background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
        }

        .recent-requests .stat-icon {
            color: #2196f3;
            background: rgba(33, 150, 243, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Content sections */
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-header {
            margin-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
        }

        .section-header h2 {
            color: #333;
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .section-header p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }

        /* Form styles */
        .form-container {
            max-width: 800px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .required {
            color: #f44336;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00704a;
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #00704a;
            color: white;
        }

        .btn-primary:hover {
            background: #005a3c;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Request cards */
        .requests-container {
            display: grid;
            gap: 1.5rem;
        }

        .request-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s;
        }

        .request-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .request-student {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: #00704a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .student-info h4 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .student-info p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-rejected {
            background: #ffebee;
            color: #c62828;
        }

        .request-details {
            display: grid;
            gap: 0.75rem;
        }

        .request-details > div {
            font-size: 0.9rem;
        }

        .request-reason p {
            margin: 0.5rem 0 0 0;
            color: #555;
            line-height: 1.5;
        }

        .request-meta {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        .request-meta small {
            color: #666;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .empty-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
        }

        /* Responsive form */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
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

            <div class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php">Dashboard</a>
            </div>

            <div class="menu-item">
                <i class="fas fa-user-graduate"></i>
                <a href="children.php">My Children</a>
            </div>

            <div class="menu-item active">
                <i class="fas fa-clipboard-list"></i>
                <a href="permissions.php">Permission Requests</a>
            </div>

            <div class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <a href="fees.php">Fee Information</a>
            </div>

            <div class="menu-item">
                <i class="fas fa-chart-line"></i>
                <a href="academics.php">Academic Progress</a>
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
            <h1>Permission Requests</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="permissions.php">Permission Requests</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Permission Request Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card all-requests" onclick="filterRequests('all')">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $permission_stats['total']; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>

            <div class="stat-card pending-requests" onclick="filterRequests('pending')">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $permission_stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <div class="stat-card approved-requests" onclick="filterRequests('approved')">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $permission_stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="stat-card rejected-requests" onclick="filterRequests('rejected')">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $permission_stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>

            <div class="stat-card recent-requests" onclick="filterRequests('recent')">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $permission_stats['recent']; ?></h3>
                    <p>This Week</p>
                </div>
            </div>
        </div>

        <!-- Permission Request Form -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-plus-circle"></i> Submit New Permission Request</h2>
                <p>Request permission for your child's absence or special circumstances</p>
            </div>

            <div class="form-container">
                <form method="POST" action="permissions.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="student_id">Select Student <span class="required">*</span></label>
                            <select name="student_id" id="student_id" required>
                                <option value="">Choose a student...</option>
                                <?php foreach ($children as $child): ?>
                                    <option value="<?php echo $child['student_id']; ?>">
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                        <?php if ($child['is_primary']): ?>
                                            <span class="primary-indicator">(Primary)</span>
                                        <?php endif; ?>
                                        - <?php echo htmlspecialchars($child['admission_number'] ?? $child['registration_number'] ?? 'No ID'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="request_type">Request Type</label>
                            <select name="request_type" id="request_type">
                                <option value="absence">Absence Request</option>
                                <option value="early_leave">Early Leave</option>
                                <option value="late_arrival">Late Arrival</option>
                                <option value="medical">Medical Leave</option>
                                <option value="family_emergency">Family Emergency</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="request_text">Reason for Request <span class="required">*</span></label>
                        <textarea name="request_text" id="request_text" rows="4" placeholder="Please provide detailed reason for your permission request..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="permission_request" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Permission Requests History -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Request History</h2>
                <p>View all your previous permission requests and their status</p>
            </div>

            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>No Permission Requests</h3>
                    <p>You haven't submitted any permission requests yet. Use the form above to submit your first request.</p>
                </div>
            <?php else: ?>
                <div class="requests-container" id="requests-container">
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card" data-status="<?php echo $request['status']; ?>" data-date="<?php echo $request['created_at']; ?>">
                            <div class="request-header">
                                <div class="request-student">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($request['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="student-info">
                                        <h4><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h4>
                                        <p>ID: <?php echo htmlspecialchars($request['admission_number'] ?? $request['registration_number'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>

                                <div class="request-status">
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <i class="fas fa-clock"></i> Pending
                                        <?php elseif ($request['status'] == 'approved'): ?>
                                            <i class="fas fa-check-circle"></i> Approved
                                        <?php elseif ($request['status'] == 'rejected'): ?>
                                            <i class="fas fa-times-circle"></i> Rejected
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="request-details">
                                <div class="request-type">
                                    <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $request['request_type'] ?? 'Other')); ?>
                                </div>

                                <?php if (!empty($request['start_date']) && !empty($request['end_date'])): ?>
                                    <div class="request-dates">
                                        <strong>Period:</strong>
                                        <?php echo date('M d, Y', strtotime($request['start_date'])); ?>
                                        <?php if ($request['start_date'] != $request['end_date']): ?>
                                            - <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="request-reason">
                                    <strong>Reason:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
                                </div>

                                <div class="request-meta">
                                    <small>
                                        <i class="fas fa-calendar"></i>
                                        Submitted on <?php echo date('M d, Y \a\t g:i A', strtotime($request['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter requests based on status
        function filterRequests(status) {
            const requestCards = document.querySelectorAll('.request-card');
            const statCards = document.querySelectorAll('.stat-card');

            // Remove active state from all cards
            statCards.forEach(card => {
                card.style.transform = '';
                card.style.boxShadow = '';
            });

            // Add active state to clicked card
            const activeCard = document.querySelector(`.${status}-requests`);
            if (activeCard) {
                activeCard.style.transform = 'translateY(-3px)';
                activeCard.style.boxShadow = '0 8px 16px rgba(0,0,0,0.15)';
            }

            // Filter request cards
            requestCards.forEach(card => {
                const cardStatus = card.getAttribute('data-status');
                const cardDate = card.getAttribute('data-date');
                let shouldShow = false;

                switch(status) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'recent':
                        const weekAgo = new Date();
                        weekAgo.setDate(weekAgo.getDate() - 7);
                        shouldShow = new Date(cardDate) >= weekAgo;
                        break;
                    default:
                        shouldShow = cardStatus === status;
                        break;
                }

                if (shouldShow) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeIn 0.3s ease-in';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update empty state
            const visibleCards = document.querySelectorAll('.request-card[style*="display: block"], .request-card:not([style*="display: none"])');
            const emptyState = document.querySelector('.empty-state');
            const requestsContainer = document.getElementById('requests-container');

            if (visibleCards.length === 0 && requestsContainer) {
                if (!emptyState) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'empty-state';
                    emptyDiv.innerHTML = `
                        <div class="empty-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No ${status === 'all' ? '' : status} requests found</h3>
                        <p>No permission requests match the selected filter.</p>
                    `;
                    requestsContainer.parentNode.appendChild(emptyDiv);
                }
            } else if (emptyState && visibleCards.length > 0) {
                emptyState.remove();
            }
        }

        // Auto-update end date when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('end_date');

            if (startDate && (!endDateInput.value || endDateInput.value < startDate)) {
                endDateInput.value = startDate;
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const studentId = document.getElementById('student_id').value;
            const requestText = document.getElementById('request_text').value.trim();

            if (!studentId) {
                e.preventDefault();
                alert('Please select a student for the permission request.');
                document.getElementById('student_id').focus();
                return false;
            }

            if (!requestText) {
                e.preventDefault();
                alert('Please provide a reason for your permission request.');
                document.getElementById('request_text').focus();
                return false;
            }

            if (requestText.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed reason (at least 10 characters).');
                document.getElementById('request_text').focus();
                return false;
            }
        });

        // Add fade-in animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .request-card {
                animation: fadeIn 0.3s ease-in;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>