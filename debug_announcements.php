<?php
/**
 * Debug script to check announcements system issues
 */

require_once 'config/config.php';

echo "<h1>🔍 Debugging Announcements System</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Checking Database Connection</h2>";
    if ($conn) {
        echo "✅ Database connection successful<br>";
    } else {
        echo "❌ Database connection failed<br>";
        exit;
    }
    
    echo "<h2>📋 Checking Tables</h2>";
    
    // Check if announcements table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($table_check->num_rows > 0) {
        echo "✅ Announcements table exists<br>";
        
        // Check table structure
        $columns = $conn->query("DESCRIBE announcements");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($col = $columns->fetch_assoc()) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
        
        // Check data count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM announcements");
        $count = $count_result->fetch_assoc()['count'];
        echo "<h3>Data Count: $count announcements</h3>";
        
        if ($count > 0) {
            // Show sample data
            $sample = $conn->query("SELECT * FROM announcements LIMIT 3");
            echo "<h3>Sample Data:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Title</th><th>Priority</th><th>Target</th><th>Publish Date</th><th>Expiry Date</th></tr>";
            while ($row = $sample->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                echo "<td>{$row['priority']}</td>";
                echo "<td>{$row['target_group']}</td>";
                echo "<td>{$row['publish_date']}</td>";
                echo "<td>" . ($row['expiry_date'] ?? 'No expiry') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "⚠️ No announcements found in database<br>";
        }
    } else {
        echo "❌ Announcements table does not exist<br>";
        echo "<p><strong>Creating announcements table...</strong></p>";
        
        // Create the table
        $create_sql = "CREATE TABLE announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            publish_date DATE NOT NULL,
            expiry_date DATE NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            target_group ENUM('all', 'parents', 'teachers', 'students') DEFAULT 'all',
            attachment VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($create_sql)) {
            echo "✅ Announcements table created successfully<br>";
            
            // Add sample data
            $sample_data = [
                [1, 'Welcome Back to School!', 'We are excited to welcome all students and parents back for the new academic year.', date('Y-m-d'), null, 'high', 'all'],
                [1, 'Parent-Teacher Conference', 'Parent-Teacher conferences are scheduled for next week. Please check your schedule.', date('Y-m-d'), date('Y-m-d', strtotime('+14 days')), 'medium', 'parents'],
                [1, 'Fee Payment Reminder', 'This is a reminder that fees are due by the end of this month.', date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), 'urgent', 'parents']
            ];
            
            foreach ($sample_data as $data) {
                $insert = $conn->prepare("INSERT INTO announcements (school_id, title, content, publish_date, expiry_date, priority, target_group) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->bind_param("issssss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]);
                if ($insert->execute()) {
                    echo "✅ Added: " . htmlspecialchars($data[1]) . "<br>";
                }
                $insert->close();
            }
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    }
    
    echo "<h2>🔍 Testing API Query</h2>";
    
    // Test the exact query from fetch_announcements.php
    $school_id = 1;
    $today = date('Y-m-d');
    
    echo "<p><strong>Query:</strong> SELECT title, content, publish_date, expiry_date, priority, attachment FROM announcements WHERE school_id = $school_id AND (expiry_date IS NULL OR expiry_date >= '$today') AND (target_group = 'all' OR target_group = 'parents') ORDER BY publish_date DESC, created_at DESC LIMIT 10</p>";
    
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
    
    echo "<h3>Query Results:</h3>";
    if (!empty($announcements)) {
        echo "<p>✅ Found " . count($announcements) . " announcements</p>";
        foreach ($announcements as $ann) {
            echo "<div style='border: 1px solid #ddd; padding: 1rem; margin: 0.5rem 0; border-radius: 4px;'>";
            echo "<h4>" . htmlspecialchars($ann['title']) . " <span style='color: #666; font-size: 0.8rem;'>(" . $ann['priority'] . ")</span></h4>";
            echo "<p>" . htmlspecialchars(substr($ann['content'], 0, 100)) . "...</p>";
            echo "<small>Date: " . $ann['date'] . "</small>";
            echo "</div>";
        }
    } else {
        echo "<p>❌ No announcements found matching criteria</p>";
    }
    
    echo "<h2>📱 Testing Parent Session</h2>";
    
    session_start();
    if (isset($_SESSION['parent_id'])) {
        echo "✅ Parent session exists: Parent ID = " . $_SESSION['parent_id'] . "<br>";
        
        // Test getting school_id for parent
        $parent_id = $_SESSION['parent_id'];
        $stmt = $conn->prepare('SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1');
        $stmt->bind_param('i', $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "✅ Parent's school ID: " . $row['school_id'] . "<br>";
        } else {
            echo "❌ No school found for parent<br>";
        }
        $stmt->close();
    } else {
        echo "❌ No parent session found<br>";
        echo "<p><a href='parent/login.php'>Login as parent first</a></p>";
    }
    
    echo "<h2>🔗 Direct API Test</h2>";
    echo "<p><a href='parent/fetch_announcements.php' target='_blank'>Test API Endpoint Directly</a></p>";
    
    echo "<h2>🎯 Summary</h2>";
    echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 4px;'>";
    echo "<h3>Checklist:</h3>";
    echo "<ul>";
    echo "<li>" . ($table_check->num_rows > 0 ? "✅" : "❌") . " Announcements table exists</li>";
    echo "<li>" . (($count ?? 0) > 0 ? "✅" : "❌") . " Sample data available</li>";
    echo "<li>" . (!empty($announcements) ? "✅" : "❌") . " Query returns results</li>";
    echo "<li>" . (isset($_SESSION['parent_id']) ? "✅" : "❌") . " Parent session active</li>";
    echo "</ul>";
    echo "</div>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
