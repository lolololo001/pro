<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // Add feedback_type and subject columns
    $alterSQL = "ALTER TABLE parent_feedback 
                ADD COLUMN feedback_type ENUM('Academic', 'Administrative', 'Facility', 'Teacher', 'Safety', 'Communication', 'Other') NOT NULL DEFAULT 'Other',
                ADD COLUMN subject VARCHAR(255) NOT NULL DEFAULT ''";
    
    if ($conn->query($alterSQL)) {
        echo "Successfully added feedback_type and subject columns to parent_feedback table.\n";
    } else {
        throw new Exception("Failed to alter table: " . $conn->error);
    }
    
    $conn->close();
    echo "Database update completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
