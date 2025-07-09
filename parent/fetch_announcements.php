<?php
require_once '../config/config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$parentId = $_SESSION['parent_id'];
$conn = getDbConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Get the parent's school_id
    $stmt = $conn->prepare('SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1');
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $school_id = $row['school_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'No school found for parent.']);
        exit;
    }
    $stmt->close();

    // Fetch recent, non-expired announcements for this school, targeted to parents or all
    $today = date('Y-m-d');
    $stmt = $conn->prepare('SELECT title, content, publish_date, expiry_date, priority, attachment FROM announcements WHERE school_id = ? AND (expiry_date IS NULL OR expiry_date >= ?) AND (target_group = "all" OR target_group = "parents") ORDER BY publish_date DESC, created_at DESC LIMIT 10');
    $stmt->bind_param('is', $school_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $announcements[] = [
            'title' => $row['title'],
            'content' => $row['content'],
            'date' => date('M d, Y', strtotime($row['publish_date'])),
            'priority' => strtolower($row['priority'] ?? 'medium'),
            'attachment' => $row['attachment'] ?? null,
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'announcements' => $announcements]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->close();
} 