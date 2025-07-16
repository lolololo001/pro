<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'parent_feedback'")->num_rows > 0;
    
    if (!$tableExists) {
        // Create parent_feedback table if it doesn't exist
        $createTableSql = "CREATE TABLE parent_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            message TEXT NOT NULL,
            sentiment_score FLOAT NULL,
            sentiment_label VARCHAR(10) NULL,
            category VARCHAR(50) NULL,
            suggestion TEXT NULL,
            school_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'reviewed') DEFAULT 'pending',
            FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($createTableSql)) {
            echo "Parent feedback table created successfully\n";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    } else {
        // Add new columns for sentiment analysis if they don't exist
        $alterQueries = [
            "ALTER TABLE parent_feedback CHANGE COLUMN feedback_text message TEXT NOT NULL",
            "ALTER TABLE parent_feedback ADD COLUMN IF NOT EXISTS sentiment_score FLOAT NULL AFTER message",
            "ALTER TABLE parent_feedback ADD COLUMN IF NOT EXISTS sentiment_label VARCHAR(10) NULL AFTER sentiment_score",
            "ALTER TABLE parent_feedback ADD COLUMN IF NOT EXISTS category VARCHAR(50) NULL AFTER sentiment_label",
            "ALTER TABLE parent_feedback ADD COLUMN IF NOT EXISTS suggestion TEXT NULL AFTER category"
        ];

        foreach ($alterQueries as $query) {
            try {
                $conn->query($query);
                echo "Table structure updated successfully\n";
            } catch (Exception $e) {
                // Ignore errors if column already exists or other non-critical issues
                continue;
            }
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
