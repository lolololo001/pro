<?php
/**
 * Helper functions for SchoolComm application
 */

/**
 * Sanitize user input
 *
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a secure password hash
 *
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password against hash
 *
 * @param string $password Plain text password
 * @param string $hash Stored hash
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 *
 * @param int $length Length of token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Redirect to a URL
 *
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is logged in
 *
 * @param string $userType Type of user (system_admin, school_admin, parent)
 * @return bool True if user is logged in
 */
function isLoggedIn($userType) {
    return isset($_SESSION[$userType . '_id']);
}

/**
 * Get current user ID
 *
 * @param string $userType Type of user (system_admin, school_admin, parent)
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId($userType) {
    return $_SESSION[$userType . '_id'] ?? null;
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @param string $format Format string
 * @return string Formatted date
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Calculate fee balance for a student
 *
 * @param int $studentId Student ID
 * @param string $term Current term
 * @param int $year Current year
 * @return float Fee balance
 */
function calculateFeeBalance($studentId, $term, $year) {
    $conn = getDbConnection();
    
    // Get student's class and school
    $stmt = $conn->prepare("SELECT class, school_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        return 0;
    }
    
    // Get fee structure amount
    $stmt = $conn->prepare("SELECT amount FROM fee_structures WHERE school_id = ? AND class = ? AND term = ? AND year = ?");
    $stmt->bind_param("issi", $student['school_id'], $student['class'], $term, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $feeStructure = $result->fetch_assoc();
    $stmt->close();
    
    if (!$feeStructure) {
        return 0;
    }
    
    $totalFees = $feeStructure['amount'];
    
    // Get total payments
    $stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM fee_payments WHERE student_id = ? AND term = ? AND year = ?");
    $stmt->bind_param("isi", $studentId, $term, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_assoc();
    $stmt->close();
    
    $totalPaid = $payments['total_paid'] ?? 0;
    
    return $totalFees - $totalPaid;
}

/**
 * Calculate student's average score
 *
 * @param int $studentId Student ID
 * @param string $term Current term
 * @param int $year Current year
 * @return float Average score
 */
function calculateAverageScore($studentId, $term, $year) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT AVG(score) as average FROM academic_records WHERE student_id = ? AND term = ? AND year = ?");
    $stmt->bind_param("isi", $studentId, $term, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $average = $result->fetch_assoc();
    $stmt->close();
    
    return round($average['average'] ?? 0, 2);
}

/**
 * Get grade from score
 *
 * @param float $score Student score
 * @return string Grade
 */
function getGrade($score) {
    if ($score >= 80) {
        return 'A';
    } elseif ($score >= 70) {
        return 'B';
    } elseif ($score >= 60) {
        return 'C';
    } elseif ($score >= 50) {
        return 'D';
    } else {
        return 'E';
    }
}

/**
 * Log activity
 *
 * @param string $action Action performed
 * @param string $userType Type of user
 * @param int $userId User ID
 * @return void
 */
function logActivity($action, $userType, $userId) {
    // Implementation for activity logging
    // This could write to a log file or database table
}

/**
 * Run sentiment analysis on text using Python script
 *
 * @param string $text Text to analyze
 * @return array Sentiment analysis result with score and label
 */
function analyzeSentiment($text) {
    $pythonPath = PYTHON_PATH;
    $scriptPath = SENTIMENT_SCRIPT_PATH;
    
    // Escape text for shell
    $escapedText = escapeshellarg($text);
    
    // Execute Python script
    $command = "$pythonPath $scriptPath $escapedText";
    $output = shell_exec($command);
    
    // Parse output
    $result = json_decode($output, true);
    
    if (!$result) {
        return [
            'score' => 0,
            'label' => 'neutral'
        ];
    }
    
    return $result;
}

/**
 * Check if a school exists by name
 *
 * @param string $schoolName School name
 * @return bool|array False if not found, school data if found
 */
function findSchoolByName($schoolName) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM schools WHERE name LIKE ? AND status = 'active'");
    $searchTerm = "%$schoolName%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return false;
}

/**
 * Get unread notifications count
 *
 * @param string $userType Type of user
 * @param int $userId User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationsCount($userType, $userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0");
    $stmt->bind_param("si", $userType, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc();
    $stmt->close();
    
    return $count['count'] ?? 0;
}

/**
 * Send notification
 *
 * @param string $userType Type of user
 * @param int $userId User ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool True if notification was sent
 */
function sendNotification($userType, $userId, $title, $message) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $userType, $userId, $title, $message);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}