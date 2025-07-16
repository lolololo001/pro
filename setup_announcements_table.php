<?php
/**
 * Setup script to create announcements table and add sample data
 */

require_once 'config/config.php';

echo "<h1>🔧 Setting Up Announcements System</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Creating Announcements Table</h2>";
    
    // Create announcements table
    $create_table_sql = "CREATE TABLE IF NOT EXISTS announcements (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_school_date (school_id, publish_date),
        INDEX idx_expiry (expiry_date),
        INDEX idx_target (target_group),
        INDEX idx_priority (priority),
        
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES school_admins(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($create_table_sql)) {
        echo "✅ Announcements table created successfully<br>";
    } else {
        echo "❌ Error creating announcements table: " . $conn->error . "<br>";
    }
    
    echo "<h2>📝 Adding Sample Announcements</h2>";
    
    // Sample announcements data
    $sample_announcements = [
        [
            'school_id' => 1,
            'title' => 'Welcome Back to School!',
            'content' => 'We are excited to welcome all students and parents back for the new academic year. Classes will begin on Monday, and we look forward to a successful year ahead.',
            'publish_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+30 days')),
            'priority' => 'high',
            'target_group' => 'all',
            'created_by' => 1
        ],
        [
            'school_id' => 1,
            'title' => 'Parent-Teacher Conference',
            'content' => 'Parent-Teacher conferences are scheduled for next week. Please check your child\'s schedule and book your appointment through the parent portal.',
            'publish_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+14 days')),
            'priority' => 'medium',
            'target_group' => 'parents',
            'created_by' => 1
        ],
        [
            'school_id' => 1,
            'title' => 'School Sports Day',
            'content' => 'Our annual Sports Day will be held on Friday, March 15th. All students are encouraged to participate. Parents are welcome to attend and cheer for their children.',
            'publish_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+21 days')),
            'priority' => 'medium',
            'target_group' => 'all',
            'created_by' => 1
        ],
        [
            'school_id' => 1,
            'title' => 'Library Hours Extended',
            'content' => 'The school library will now be open until 6 PM on weekdays to provide more study time for students. New books have also been added to our collection.',
            'publish_date' => date('Y-m-d'),
            'expiry_date' => null,
            'priority' => 'low',
            'target_group' => 'all',
            'created_by' => 1
        ],
        [
            'school_id' => 1,
            'title' => 'Fee Payment Reminder',
            'content' => 'This is a reminder that the second term fees are due by the end of this month. Please ensure timely payment to avoid any inconvenience.',
            'publish_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+7 days')),
            'priority' => 'urgent',
            'target_group' => 'parents',
            'created_by' => 1
        ]
    ];
    
    foreach ($sample_announcements as $announcement) {
        // Check if announcement already exists
        $check_stmt = $conn->prepare("SELECT id FROM announcements WHERE school_id = ? AND title = ?");
        $check_stmt->bind_param("is", $announcement['school_id'], $announcement['title']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new announcement
            $insert_stmt = $conn->prepare("INSERT INTO announcements (school_id, title, content, publish_date, expiry_date, priority, target_group, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("issssssi", 
                $announcement['school_id'],
                $announcement['title'],
                $announcement['content'],
                $announcement['publish_date'],
                $announcement['expiry_date'],
                $announcement['priority'],
                $announcement['target_group'],
                $announcement['created_by']
            );
            
            if ($insert_stmt->execute()) {
                echo "✅ Added: " . htmlspecialchars($announcement['title']) . "<br>";
            } else {
                echo "❌ Failed to add: " . htmlspecialchars($announcement['title']) . " - " . $conn->error . "<br>";
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Already exists: " . htmlspecialchars($announcement['title']) . "<br>";
        }
        $check_stmt->close();
    }
    
    echo "<h2>📋 Current Announcements</h2>";
    
    // Display current announcements
    $display_stmt = $conn->prepare("SELECT * FROM announcements WHERE school_id = 1 ORDER BY priority DESC, publish_date DESC");
    $display_stmt->execute();
    $result = $display_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>Title</th>";
        echo "<th style='padding: 8px;'>Priority</th>";
        echo "<th style='padding: 8px;'>Target</th>";
        echo "<th style='padding: 8px;'>Publish Date</th>";
        echo "<th style='padding: 8px;'>Expiry Date</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            $priority_colors = [
                'low' => '#28a745',
                'medium' => '#ffc107',
                'high' => '#fd7e14',
                'urgent' => '#dc3545'
            ];
            
            echo "<tr>";
            echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($row['title']) . "</strong><br><small>" . htmlspecialchars(substr($row['content'], 0, 100)) . "...</small></td>";
            echo "<td style='padding: 8px; text-align: center;'><span style='background: " . ($priority_colors[$row['priority']] ?? '#6c757d') . "; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;'>" . ucfirst($row['priority']) . "</span></td>";
            echo "<td style='padding: 8px; text-align: center;'>" . ucfirst($row['target_group']) . "</td>";
            echo "<td style='padding: 8px; text-align: center;'>" . date('M j, Y', strtotime($row['publish_date'])) . "</td>";
            echo "<td style='padding: 8px; text-align: center;'>" . ($row['expiry_date'] ? date('M j, Y', strtotime($row['expiry_date'])) : 'No expiry') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No announcements found.</p>";
    }
    $display_stmt->close();
    
    echo "<h2>🎯 Setup Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Announcements System Setup Complete!</h3>";
    echo "<ul>";
    echo "<li>✅ Announcements table created with proper structure</li>";
    echo "<li>✅ Sample announcements added for testing</li>";
    echo "<li>✅ Priority levels: Low, Medium, High, Urgent</li>";
    echo "<li>✅ Target groups: All, Parents, Teachers, Students</li>";
    echo "<li>✅ Expiry date support for time-sensitive announcements</li>";
    echo "<li>✅ Foreign key relationships established</li>";
    echo "</ul>";
    echo "</div>";
    
    $conn->close();
    
    echo "<h2>🔗 Test Links</h2>";
    echo "<p>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Parent Dashboard (Test Announcements)</a>";
    echo "<a href='school-admin/add_announcement.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Add Announcement (Admin)</a>";
    echo "<a href='test_announcements_system.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Test System</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
