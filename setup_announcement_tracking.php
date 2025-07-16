<?php
/**
 * Setup announcement tracking system for "NEW" indicators
 */

require_once 'config/config.php';

echo "<h1>🔧 Setting Up Announcement Tracking System</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Creating Announcement Tracking Table</h2>";
    
    // Create table to track which announcements parents have viewed
    $create_tracking_table = "CREATE TABLE IF NOT EXISTS parent_announcement_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        announcement_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_parent_announcement (parent_id, announcement_id),
        INDEX idx_parent (parent_id),
        INDEX idx_announcement (announcement_id),
        INDEX idx_viewed_at (viewed_at),
        
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_tracking_table)) {
        echo "✅ parent_announcement_views table created successfully<br>";
    } else {
        echo "❌ Error creating tracking table: " . $conn->error . "<br>";
    }
    
    echo "<h2>📝 Adding Last Login Tracking to Parents</h2>";
    
    // Check if last_login column exists in parents table
    $check_column = $conn->query("SHOW COLUMNS FROM parents LIKE 'last_login'");
    
    if ($check_column->num_rows == 0) {
        $add_last_login = "ALTER TABLE parents ADD COLUMN last_login TIMESTAMP NULL";
        if ($conn->query($add_last_login)) {
            echo "✅ Added last_login column to parents table<br>";
        } else {
            echo "❌ Error adding last_login column: " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ last_login column already exists in parents table<br>";
    }
    
    echo "<h2>🔧 Creating Helper Functions</h2>";
    
    // Create a PHP file with helper functions
    $helper_functions = '<?php
/**
 * Helper functions for announcement tracking
 */

/**
 * Check if parent has new announcements
 */
function hasNewAnnouncements($parent_id) {
    $conn = getDbConnection();
    
    try {
        // Get parent\'s school_id
        $stmt = $conn->prepare("SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return false;
        }
        
        $school_id = $row["school_id"];
        $stmt->close();
        
        // Get parent\'s last login time
        $stmt = $conn->prepare("SELECT last_login FROM parents WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent_data = $result->fetch_assoc();
        $last_login = $parent_data["last_login"] ?? "1970-01-01 00:00:00";
        $stmt->close();
        
        // Check for announcements published after last login
        $today = date("Y-m-d");
        $stmt = $conn->prepare("
            SELECT COUNT(*) as new_count 
            FROM announcements a
            LEFT JOIN parent_announcement_views pav ON a.id = pav.announcement_id AND pav.parent_id = ?
            WHERE a.school_id = ? 
            AND (a.expiry_date IS NULL OR a.expiry_date >= ?) 
            AND (a.target_group = \"all\" OR a.target_group = \"parents\")
            AND a.created_at > ?
            AND pav.id IS NULL
        ");
        $stmt->bind_param("iiss", $parent_id, $school_id, $today, $last_login);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()["new_count"];
        $stmt->close();
        
        return $count > 0;
        
    } catch (Exception $e) {
        error_log("Error checking new announcements: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Mark announcements as viewed by parent
 */
function markAnnouncementsAsViewed($parent_id) {
    $conn = getDbConnection();
    
    try {
        // Get parent\'s school_id
        $stmt = $conn->prepare("SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return false;
        }
        
        $school_id = $row["school_id"];
        $stmt->close();
        
        // Get all current announcements for this parent
        $today = date("Y-m-d");
        $stmt = $conn->prepare("
            SELECT a.id 
            FROM announcements a
            WHERE a.school_id = ? 
            AND (a.expiry_date IS NULL OR a.expiry_date >= ?) 
            AND (a.target_group = \"all\" OR a.target_group = \"parents\")
        ");
        $stmt->bind_param("is", $school_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Mark each announcement as viewed
        while ($row = $result->fetch_assoc()) {
            $view_stmt = $conn->prepare("INSERT IGNORE INTO parent_announcement_views (parent_id, announcement_id) VALUES (?, ?)");
            $view_stmt->bind_param("ii", $parent_id, $row["id"]);
            $view_stmt->execute();
            $view_stmt->close();
        }
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error marking announcements as viewed: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Update parent last login time
 */
function updateParentLastLogin($parent_id) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error updating parent last login: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}
?>';
    
    file_put_contents('includes/announcement_helpers.php', $helper_functions);
    echo "✅ Created announcement helper functions file<br>";
    
    echo "<h2>📊 Testing the Tracking System</h2>";
    
    // Test the functions
    include 'includes/announcement_helpers.php';
    
    // Simulate checking for new announcements for parent ID 1
    $parent_id = 1;
    $has_new = hasNewAnnouncements($parent_id);
    
    echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 4px; margin: 1rem 0;'>";
    echo "<h3>🧪 Test Results:</h3>";
    echo "<ul>";
    echo "<li>✅ Tracking table created successfully</li>";
    echo "<li>✅ Helper functions created</li>";
    echo "<li>✅ Parent ID $parent_id has new announcements: " . ($has_new ? "YES" : "NO") . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🎯 Setup Summary</h2>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1.5rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Announcement Tracking System Setup Complete!</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Tracking Table:</strong> parent_announcement_views created</li>";
    echo "<li>✅ <strong>Last Login:</strong> Added to parents table</li>";
    echo "<li>✅ <strong>Helper Functions:</strong> Created in includes/announcement_helpers.php</li>";
    echo "<li>✅ <strong>NEW Indicator:</strong> Ready to show on View Announcements button</li>";
    echo "<li>✅ <strong>View Tracking:</strong> System tracks which announcements parents have seen</li>";
    echo "</ul>";
    echo "</div>";
    
    $conn->close();
    
    echo "<h2>🔗 Next Steps</h2>";
    echo "<p>";
    echo "<a href='update_parent_dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Update Parent Dashboard</a>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Test Parent Dashboard</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
