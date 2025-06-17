<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // Create parent_feedback table without foreign keys
    $createTable = "CREATE TABLE IF NOT EXISTS parent_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        message TEXT NOT NULL,
        school_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'reviewed') DEFAULT 'pending'
    )";
    
    if ($conn->query($createTable)) {
        echo "Parent feedback table created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
