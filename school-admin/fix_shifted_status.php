<?php
// Quick fix script to ensure shifted status works
session_start();
require_once '../config/config.php';

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    die("Please log in as school admin first");
}

$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("School ID not found in session");
}

$conn = getDbConnection();

// Check if we have any students
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE school_id = ?");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();
$total_students = $result->fetch_assoc()['count'];
$stmt->close();

if ($total_students == 0) {
    echo "No students found in the database. Please add some students first.";
    $conn->close();
    exit;
}

// Check if we have any shifted students
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE school_id = ? AND status = 'shifted'");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();
$shifted_count = $result->fetch_assoc()['count'];
$stmt->close();

if ($shifted_count > 0) {
    echo "✅ You already have $shifted_count shifted students. The filter should work now.";
    echo "<br><br><a href='students.php?status=shifted' style='background: #9c27b0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Shifted Filter</a>";
    echo " <a href='students.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Back to Students</a>";
} else {
    // Get the first 2 students and set them to shifted status
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE school_id = ? LIMIT 2");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updated_students = [];
    while ($row = $result->fetch_assoc()) {
        $updated_students[] = $row;
    }
    $stmt->close();
    
    if (count($updated_students) > 0) {
        // Update these students to shifted status
        foreach ($updated_students as $student) {
            $stmt = $conn->prepare("UPDATE students SET status = 'shifted' WHERE id = ? AND school_id = ?");
            $stmt->bind_param('ii', $student['id'], $school_id);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "✅ Successfully set " . count($updated_students) . " students to 'shifted' status:<br>";
        foreach ($updated_students as $student) {
            echo "- " . $student['first_name'] . " " . $student['last_name'] . "<br>";
        }
        
        echo "<br><strong>The shifted filter should now work!</strong><br><br>";
        echo "<a href='students.php?status=shifted' style='background: #9c27b0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Shifted Filter</a>";
        echo " <a href='students.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Back to Students</a>";
    } else {
        echo "❌ No students found to update.";
    }
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
</style>
