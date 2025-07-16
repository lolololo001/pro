<?php
// Comprehensive test script for login system
require_once 'config/config.php';

echo "<h2>Testing Complete Login System</h2>";

try {
    $conn = getDbConnection();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test all user tables
    $tables = [
        'parents' => 'SELECT COUNT(*) as count FROM parents WHERE username IS NOT NULL',
        'school_admins' => 'SELECT COUNT(*) as count FROM school_admins WHERE username IS NOT NULL',
        'teachers' => 'SELECT COUNT(*) as count FROM teachers WHERE username IS NOT NULL',
        'system_admins' => 'SELECT COUNT(*) as count FROM system_admins WHERE username IS NOT NULL'
    ];
    
    echo "<h3>User Counts:</h3>";
    foreach ($tables as $table => $query) {
        $result = $conn->query($query);
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            echo "<p style='color: blue;'>📊 $table: $count users with usernames</p>";
        } else {
            echo "<p style='color: red;'>✗ Error checking $table</p>";
        }
    }
    
    // Test teacher login specifically
    echo "<h3>Testing Teacher Login Query:</h3>";
    
    // First, check if we have any teachers
    $result = $conn->query("SELECT COUNT(*) as count FROM teachers");
    $total_teachers = $result->fetch_assoc()['count'];
    echo "<p>Total teachers in database: $total_teachers</p>";
    
    // Check teachers with usernames
    $result = $conn->query("SELECT COUNT(*) as count FROM teachers WHERE username IS NOT NULL AND username != ''");
    $teachers_with_username = $result->fetch_assoc()['count'];
    echo "<p>Teachers with usernames: $teachers_with_username</p>";
    
    if ($teachers_with_username > 0) {
        // Get a sample teacher
        $result = $conn->query("SELECT id, name, username, email, school_id FROM teachers WHERE username IS NOT NULL LIMIT 1");
        $sample_teacher = $result->fetch_assoc();
        
        echo "<h4>Sample Teacher:</h4>";
        echo "<ul>";
        echo "<li>ID: " . $sample_teacher['id'] . "</li>";
        echo "<li>Name: " . htmlspecialchars($sample_teacher['name']) . "</li>";
        echo "<li>Username: " . htmlspecialchars($sample_teacher['username']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($sample_teacher['email']) . "</li>";
        echo "<li>School ID: " . $sample_teacher['school_id'] . "</li>";
        echo "</ul>";
        
        // Test the exact login query
        $test_username = $sample_teacher['username'];
        echo "<h4>Testing Login Query with username: $test_username</h4>";
        
        $stmt = $conn->prepare("SELECT t.id, t.username, t.password, t.name, t.school_id, s.name as school_name 
                               FROM teachers t 
                               JOIN schools s ON t.school_id = s.id 
                               WHERE t.username = ?");
        $stmt->bind_param('s', $test_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            echo "<p style='color: green;'>✓ Login query successful!</p>";
            echo "<ul>";
            echo "<li>Teacher ID: " . $teacher['id'] . "</li>";
            echo "<li>Name: " . htmlspecialchars($teacher['name']) . "</li>";
            echo "<li>School: " . htmlspecialchars($teacher['school_name']) . "</li>";
            echo "<li>Has Password: " . (!empty($teacher['password']) ? 'Yes' : 'No') . "</li>";
            echo "</ul>";
            
            // Test password verification
            if (!empty($teacher['password'])) {
                echo "<p style='color: green;'>✓ Teacher has password set</p>";
            } else {
                echo "<p style='color: red;'>✗ Teacher has no password!</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Login query failed!</p>";
            
            // Debug: Check if school exists
            $school_id = $sample_teacher['school_id'];
            $school_check = $conn->query("SELECT COUNT(*) as count FROM schools WHERE id = $school_id");
            $school_exists = $school_check->fetch_assoc()['count'];
            echo "<p>School ID $school_id exists: " . ($school_exists > 0 ? 'Yes' : 'No') . "</p>";
        }
        $stmt->close();
        
    } else {
        echo "<p style='color: red;'>✗ No teachers have usernames!</p>";
        echo "<p>This is the problem. Run fix_teacher_credentials.php to add usernames to existing teachers.</p>";
    }
    
    // Test schools table
    echo "<h3>Testing Schools Table:</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM schools");
    $school_count = $result->fetch_assoc()['count'];
    echo "<p>Total schools: $school_count</p>";
    
    if ($school_count > 0) {
        $result = $conn->query("SELECT id, name FROM schools LIMIT 3");
        echo "<h4>Sample Schools:</h4>";
        while ($school = $result->fetch_assoc()) {
            echo "<p>ID: " . $school['id'] . " - Name: " . htmlspecialchars($school['name']) . "</p>";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Diagnosis:</h3>";
echo "<ul>";
echo "<li>If no teachers have usernames → Run fix_teacher_credentials.php</li>";
echo "<li>If teachers have usernames but login fails → Check database structure</li>";
echo "<li>If schools table is empty → Add schools first</li>";
echo "<li>If JOIN fails → Check school_id foreign key relationships</li>";
echo "</ul>";

echo "<h3>Quick Fix Steps:</h3>";
echo "<ol>";
echo "<li>Run <a href='debug_teacher_login.php'>debug_teacher_login.php</a> to check current state</li>";
echo "<li>Run <a href='fix_teacher_credentials.php'>fix_teacher_credentials.php</a> to add credentials to existing teachers</li>";
echo "<li>Try logging in with the generated credentials</li>";
echo "<li>Check if teacher dashboard loads correctly</li>";
echo "</ol>";
?> 