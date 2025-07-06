<?php
require_once '../config/config.php';
session_start();
if (!isset($_SESSION['parent_id'])) {
    http_response_code(403);
    exit('Not authorized');
}
$parentId = $_SESSION['parent_id'];
header('Content-Type: text/html; charset=UTF-8');

try {
    $conn = getDbConnection();
    $requests = [];
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        $columnsResult = $conn->query("SHOW COLUMNS FROM permission_requests");
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $stmt = null;
        if (in_array('reason', $columns) && in_array('student_id', $columns)) {
            $stmt = $conn->prepare("SELECT id, request_text, status, created_at, response_comment FROM permission_requests WHERE parent_id = ? ORDER BY created_at DESC");
        } else {
            $stmt = $conn->prepare("SELECT id, request_text, status, created_at, response_comment FROM permission_requests WHERE parent_id = ? ORDER BY created_at DESC");
        }
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log('Permission requests error: ' . $e->getMessage());
    echo '<div class="alert alert-warning">Unable to load your permission requests at this time. Please try again later.</div>';
    exit;
}

if (empty($requests)) {
    echo '<div class="alert alert-info">No permission requests found.</div>';
    exit;
}
?>
<table class="permission-requests-table" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Request</th>
            <th>Status</th>
            <th>Date</th>
            <th>Admin Response</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($requests as $req): ?>
        <tr>
            <td><?php echo htmlspecialchars($req['request_text']); ?></td>
            <td><span class="status-badge status-<?php echo strtolower($req['status']); ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($req['created_at']))); ?></td>
            <td><?php echo htmlspecialchars($req['response_comment'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
