<?php
require_once '../config/config.php';

try {
    $conn = getDbConnection();
    
    // First, let's check if we need to modify the table structure
    $dropForeignKeys = "ALTER TABLE parent_feedback 
                       DROP FOREIGN KEY IF EXISTS parent_feedback_ibfk_1,
                       DROP FOREIGN KEY IF EXISTS parent_feedback_ibfk_2";
                       
    // Don't worry if this fails, as the foreign keys might not exist
    $conn->query($dropForeignKeys);
    
    // Now update the table structure to be simpler
    $alterTable = "ALTER TABLE parent_feedback 
                  MODIFY parent_id INT NOT NULL,
                  MODIFY school_id INT NOT NULL";
                  
    if ($conn->query($alterTable)) {
        echo "Table structure updated successfully\n";
    } else {
        echo "Error updating table structure: " . $conn->error . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
