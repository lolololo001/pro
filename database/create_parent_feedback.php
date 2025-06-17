<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // Create parent_feedback table
    $sql = "CREATE TABLE IF NOT EXISTS parent_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        feedback_text TEXT NOT NULL,
        school_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'reviewed') DEFAULT 'pending',
        FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql)) {
        echo "Parent feedback table created successfully";
    } else {
        echo "Error creating table: " . $conn->error;
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
