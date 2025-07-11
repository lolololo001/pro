<?php
require_once 'config/config.php';
\ = getDbConnection();
\ = 'CREATE TABLE IF NOT EXISTS parent_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    message TEXT NOT NULL,
    school_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM(\'pending\', \'reviewed\') DEFAULT \'pending\'
)';
if (\->query(\)) {
    echo 'Table created successfully';
} else {
    echo 'Error creating table: ' . \->error;
}
\->close();
?>
