<?php
session_start();
require_once '../config/config.php';
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    die('Invalid student ID.');
}
// Optionally fetch student info for header
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT s.*, d.department_name FROM students s LEFT JOIN departments d ON s.department_id = d.dep_id WHERE s.id = ?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Info - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f6fa; margin: 0; }
        .container { max-width: 1100px; margin: 2rem auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2rem; }
        .student-header { display: flex; justify-content: center; align-items: flex-start; margin-bottom: 2.5rem; position: relative; }
        .student-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #009260 0%, #43e97b 100%); display: flex; align-items: center; justify-content: center; font-size: 2.8rem; color: #fff; box-shadow: 0 2px 12px #43e97b33; }
        .student-info { flex: 1; display: flex; flex-direction: column; gap: 0.3rem; }
        .student-info h2 { margin: 0 0 0.5rem 0; font-size: 2rem; font-weight: 700; }
        .student-info p { margin: 0.2rem 0; color: #555; }
        .tabs { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .tab-btn { background: #f5f6fa; border: none; padding: 1rem 2rem; border-radius: 10px 10px 0 0; font-size: 1.1rem; font-weight: 500; color: #00704A; cursor: pointer; transition: background 0.2s, color 0.2s; outline: none; }
        .tab-btn.active { background: #fff; color: #009260; border-bottom: 2px solid #009260; }
        .tab-content { display: none; padding: 2rem 0; }
        .tab-content.active { display: block; }
        .tab-icon { margin-right: 0.7rem; }
        .main-content { margin-left: 260px; padding: 2rem 1rem; }
        @media (max-width: 1100px) { .container { max-width: 100%; } }
        @media (max-width: 900px) { .main-content { margin-left: 0; padding: 1rem; } .container { padding: 1rem; } }
        @media (max-width: 700px) { .tabs { flex-direction: column; gap: 0.5rem; } .tab-btn { width: 100%; text-align: left; } }
        @keyframes chipFadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        /* Status badge styles */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation (copied from dashboard.php) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">SchoolComm<span>.</span></a>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['parent_name'] ?? 'Parent User'); ?></h3>
                <p>Parent</p>
            </div>
        </div>
        <div class="sidebar-menu">
            <div class="menu-heading">Navigation</div>
            <div class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php">Dashboard</a>
            </div>
            <div class="menu-item active">
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
        <div class="container">
            <div class="student-header" style="display: flex; justify-content: center; align-items: flex-start; margin-bottom: 2.5rem; position: relative;">
                <div style="position: relative; max-width: 440px; width: 100%; background: rgba(255,255,255,0.18); border-radius: 2.2rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18); border: 1.5px solid rgba(67, 233, 123, 0.25); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); padding: 3.5rem 2.5rem 2.2rem 2.5rem; display: flex; flex-direction: column; align-items: center;">
                    <div style="position: absolute; top: -55px; left: 50%; transform: translateX(-50%); z-index: 2;">
                        <div class="student-avatar" style="width: 110px; height: 110px; font-size: 3.8rem; background: linear-gradient(135deg, #fff 60%, #43e97b 100%); color: #009260; box-shadow: 0 4px 24px #43e97b33, 0 0 0 8px rgba(67,233,123,0.10); display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 4px solid #fff; filter: drop-shadow(0 2px 12px #43e97b33);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div style="margin-top: 65px; font-size: 2.3rem; font-weight: 900; color: #222; letter-spacing: 1.5px; margin-bottom: 1.3rem; text-align: center; text-shadow: 0 2px 16px #43e97b33, 0 1px 0 #fff; filter: drop-shadow(0 2px 8px #43e97b22);">
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </div>
                    <div style="display: flex; gap: 1.2rem; justify-content: center; margin-bottom: 0.2rem; flex-wrap: wrap;">
                        <div style="background: rgba(255,255,255,0.85); color: #009260; border-radius: 22px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 1.13rem; box-shadow: 0 2px 12px #43e97b22; display: flex; align-items: center; gap: 0.7rem; transition: transform 0.2s; border: 1.5px solid #43e97b33; animation: chipFadeIn 0.7s;">
                            <i class="fas fa-id-card"></i> Reg Number: <?php echo htmlspecialchars($student['reg_number']); ?>
                        </div>
                        <div style="background: rgba(255,255,255,0.85); color: #00704A; border-radius: 22px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 1.13rem; box-shadow: 0 2px 12px #43e97b22; display: flex; align-items: center; gap: 0.7rem; transition: transform 0.2s; border: 1.5px solid #43e97b33; animation: chipFadeIn 0.9s;">
                            <i class="fas fa-music"></i> Department: <?php echo htmlspecialchars($student['department_name'] ?? ''); ?>
                        </div>
                        <div style="background: rgba(255,255,255,0.85); color: #43e97b; border-radius: 22px; padding: 0.7rem 1.5rem; font-weight: 700; font-size: 1.13rem; box-shadow: 0 2px 12px #43e97b22; display: flex; align-items: center; gap: 0.7rem; transition: transform 0.2s; border: 1.5px solid #43e97b33; animation: chipFadeIn 1.1s;">
                            <i class="fas fa-check-circle"></i> Status: <?php echo htmlspecialchars($student['status']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tabs">
                <button class="tab-btn active" data-tab="permissions"><span class="tab-icon"><i class="fas fa-clipboard-list"></i></span>View Permissions</button>
                <button class="tab-btn" data-tab="payment"><span class="tab-icon"><i class="fas fa-money-bill-wave"></i></span>View Payment</button>
                <button class="tab-btn" data-tab="marks"><span class="tab-icon"><i class="fas fa-chart-line"></i></span>View Marks</button>
                <button class="tab-btn" data-tab="attendance"><span class="tab-icon"><i class="fas fa-calendar-check"></i></span>View Attendance</button>
            </div>
            <div class="tab-content active" id="tab-permissions">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3>Permission Requests for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                    <button onclick="window.location.href='dashboard.php?student_id=<?php echo $student_id; ?>#permissions'" 
                            style="background: #00704A; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i>
                        Submit New Request
                    </button>
                </div>
                <?php
                // Fetch permission requests for this specific student
                $conn = getDbConnection();
                $permissionQuery = "SELECT pr.*, 
                                          COALESCE(sa.full_name, 'School Admin') as admin_name
                                   FROM permission_requests pr
                                   LEFT JOIN school_admins sa ON pr.responded_by = sa.id
                                   WHERE pr.student_id = ? AND pr.parent_id = ?
                                   ORDER BY pr.created_at DESC";
                
                $permissionStmt = $conn->prepare($permissionQuery);
                $permissionStmt->bind_param('ii', $student_id, $_SESSION['parent_id']);
                $permissionStmt->execute();
                $permissionResult = $permissionStmt->get_result();
                
                if ($permissionResult->num_rows > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">';
                    echo '<thead>';
                    echo '<tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Date</th>';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Request Type</th>';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Reason</th>';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Duration</th>';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>';
                    echo '<th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Response</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    while ($permission = $permissionResult->fetch_assoc()) {
                        $status = strtolower($permission['status']);
                        $statusClass = 'status-badge status-' . $status;
                        $statusIcon = '';
                        
                        switch ($status) {
                            case 'pending':
                                $statusIcon = '<i class="fas fa-clock"></i> ';
                                break;
                            case 'approved':
                                $statusIcon = '<i class="fas fa-check-circle"></i> ';
                                break;
                            case 'rejected':
                                $statusIcon = '<i class="fas fa-times-circle"></i> ';
                                break;
                        }
                        
                        echo '<tr style="border-bottom: 1px solid #dee2e6; transition: background-color 0.2s;">';
                        echo '<td style="padding: 12px; color: #6c757d;">' . date('M d, Y', strtotime($permission['created_at'])) . '</td>';
                        echo '<td style="padding: 12px; color: #495057; font-weight: 500;">' . ucfirst(htmlspecialchars($permission['request_type'])) . '</td>';
                        echo '<td style="padding: 12px; color: #495057;">' . htmlspecialchars(substr($permission['reason'], 0, 50)) . (strlen($permission['reason']) > 50 ? '...' : '') . '</td>';
                        echo '<td style="padding: 12px; color: #6c757d;">';
                        if (isset($permission['start_date']) && isset($permission['end_date'])) {
                            echo date('M d', strtotime($permission['start_date'])) . ' - ' . date('M d, Y', strtotime($permission['end_date']));
                        } else {
                            echo 'N/A';
                        }
                        echo '</td>';
                        echo '<td style="padding: 12px;">';
                        echo '<span class="' . $statusClass . '" style="display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">';
                        echo $statusIcon . ucfirst($permission['status']);
                        echo '</span>';
                        echo '</td>';
                        echo '<td style="padding: 12px; color: #6c757d;">';
                        if ($permission['response_comment']) {
                            echo '<div style="max-width: 200px; word-wrap: break-word;">';
                            echo htmlspecialchars(substr($permission['response_comment'], 0, 100));
                            if (strlen($permission['response_comment']) > 100) {
                                echo '... <button onclick="showFullResponse(\'' . htmlspecialchars($permission['response_comment'], ENT_QUOTES) . '\')" style="background: none; border: none; color: #00704A; cursor: pointer; font-size: 0.8rem;">Read more</button>';
                            }
                            echo '</div>';
                            if ($permission['admin_name']) {
                                echo '<small style="color: #adb5bd; font-size: 0.8rem;">by ' . htmlspecialchars($permission['admin_name']) . '</small>';
                            }
                        } else {
                            echo '<span style="color: #adb5bd; font-style: italic;">No response yet</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<div style="text-align: center; padding: 3rem; color: #6c757d;">';
                    echo '<i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>';
                    echo '<h4 style="margin-bottom: 0.5rem; color: #495057;">No Permission Requests</h4>';
                    echo '<p>No permission requests have been submitted for this student yet.</p>';
                    echo '</div>';
                }
                
                $permissionStmt->close();
                $conn->close();
                ?>
            </div>
            <div class="tab-content" id="tab-payment">
                <h3>Payment</h3>
                <p>Here you can view all payment records for this student.</p>
            </div>
            <div class="tab-content" id="tab-marks">
                <h3>Marks</h3>
                <p>Here you can view all marks and academic results for this student.</p>
            </div>
            <div class="tab-content" id="tab-attendance">
                <h3>Attendance</h3>
                <p>Here you can view attendance records for this student.</p>
            </div>
        </div>
    </div>
    
    <!-- Modal for showing full response -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 style="color: #00704A; margin-bottom: 1rem;">Admin Response</h3>
            <div id="modalContent" style="line-height: 1.6; color: #333;"></div>
        </div>
    </div>
    
    <script>
        // Tab switching logic
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });
        
        // Modal functions
        function showFullResponse(response) {
            document.getElementById('modalContent').innerHTML = response.replace(/\n/g, '<br>');
            document.getElementById('responseModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('responseModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('responseModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html> 