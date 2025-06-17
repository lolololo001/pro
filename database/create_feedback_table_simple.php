<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // Drop the existing table if it exists
    $conn->query("DROP TABLE IF EXISTS parent_feedback");
    
    // Create the table without foreign key constraints
    $sql = "CREATE TABLE parent_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        message TEXT NOT NULL,
        school_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'reviewed') DEFAULT 'pending'
    )";
    
    if ($conn->query($sql)) {
        echo "Parent feedback table created successfully without constraints\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
