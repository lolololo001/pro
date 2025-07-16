<?php
// Script to fix existing teachers by adding usernames and passwords
require_once 'config/config.php';

echo "<h2>Fixing Teacher Credentials</h2>";

try {
    $conn = getDbConnection();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if username and password columns exist
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'username'");
    $username_exists = $result->num_rows > 0;
    
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'password'");
    $password_exists = $result->num_rows > 0;
    
    if (!$username_exists) {
        echo "<p style='color: orange;'>⚠ Adding username column...</p>";
        $conn->query("ALTER TABLE teachers ADD COLUMN username VARCHAR(50) UNIQUE");
        echo "<p style='color: green;'>✓ Username column added</p>";
    } else {
        echo "<p style='color: green;'>✓ Username column already exists</p>";
    }
    
    if (!$password_exists) {
        echo "<p style='color: orange;'>⚠ Adding password column...</p>";
        $conn->query("ALTER TABLE teachers ADD COLUMN password VARCHAR(255)");
        echo "<p style='color: green;'>✓ Password column added</p>";
    } else {
        echo "<p style='color: green;'>✓ Password column already exists</p>";
    }
    
    // Get teachers without usernames
    $result = $conn->query("SELECT id, name, email FROM teachers WHERE username IS NULL OR username = ''");
    $teachers_without_username = $result->num_rows;
    
    echo "<p style='color: blue;'>📊 Teachers without usernames: $teachers_without_username</p>";
    
    if ($teachers_without_username > 0) {
        echo "<h3>Adding usernames and passwords to existing teachers:</h3>";
        
        while ($teacher = $result->fetch_assoc()) {
            // Generate username
            $name_parts = explode(' ', trim($teacher['name']));
            $first_name = strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[0]));
            $last_name = isset($name_parts[1]) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $name_parts[1])) : '';
            
            $base_username = $first_name . ($last_name ? '.' . $last_name : '');
            
            // Check if username exists and generate unique one
            $username = $base_username;
            $counter = 1;
            
            do {
                $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE username = ?");
                $check_stmt->bind_param('s', $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $username = $base_username . $counter;
                    $counter++;
                } else {
                    break;
                }
                $check_stmt->close();
            } while (true);
            
            // Generate password
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $password = '';
            
            $password .= 'abcdefghijklmnopqrstuvwxyz'[rand(0, 25)];
            $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[rand(0, 25)];
            $password .= '0123456789'[rand(0, 9)];
            $password .= '!@#$%^&*'[rand(0, 7)];
            
            for ($i = 4; $i < 8; $i++) {
                $password .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            $password = str_shuffle($password);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update teacher
            $update_stmt = $conn->prepare("UPDATE teachers SET username = ?, password = ? WHERE id = ?");
            $update_stmt->bind_param('ssi', $username, $hashed_password, $teacher['id']);
            
            if ($update_stmt->execute()) {
                echo "<p style='color: green;'>✓ Updated teacher: " . htmlspecialchars($teacher['name']) . "</p>";
                echo "<p style='color: blue;'>   Username: $username</p>";
                echo "<p style='color: blue;'>   Password: $password</p>";
                echo "<p style='color: blue;'>   Email: " . htmlspecialchars($teacher['email']) . "</p>";
                echo "<hr>";
            } else {
                echo "<p style='color: red;'>✗ Failed to update teacher: " . htmlspecialchars($teacher['name']) . "</p>";
            }
            $update_stmt->close();
        }
        
        echo "<h3>✅ All existing teachers have been updated with credentials!</h3>";
        echo "<p>Teachers can now login using their username and password.</p>";
        
    } else {
        echo "<p style='color: green;'>✓ All teachers already have usernames</p>";
    }
    
    // Test login functionality
    echo "<h3>Testing Login Functionality:</h3>";
    $test_result = $conn->query("SELECT username FROM teachers WHERE username IS NOT NULL LIMIT 1");
    if ($test_result->num_rows > 0) {
        $test_teacher = $test_result->fetch_assoc();
        $test_username = $test_teacher['username'];
        
        $stmt = $conn->prepare("SELECT t.id, t.username, t.password, t.name, t.school_id, s.name as school_name 
                               FROM teachers t 
                               JOIN schools s ON t.school_id = s.id 
                               WHERE t.username = ?");
        $stmt->bind_param('s', $test_username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Login query works correctly</p>";
            echo "<p>Test username: $test_username</p>";
        } else {
            echo "<p style='color: red;'>✗ Login query failed</p>";
        }
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Run this script to fix existing teachers</li>";
echo "<li>Try logging in with a teacher's credentials</li>";
echo "<li>Check the teacher dashboard access</li>";
echo "<li>Test password change functionality</li>";
echo "</ol>";
?> 