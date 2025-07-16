<?php
/**
 * Setup script to create student_marks table if it doesn't exist
 */

require_once 'config/config.php';

try {
    $conn = getDbConnection();
    
    // Create student_marks table
    $sql = "CREATE TABLE IF NOT EXISTS student_marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        marks DECIMAL(5,2) NOT NULL,
        max_marks DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        term VARCHAR(50) NOT NULL DEFAULT 'Term 1',
        comments TEXT NULL,
        teacher_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_student_subject (student_id, subject),
        INDEX idx_student_term (student_id, term),
        INDEX idx_teacher (teacher_id),
        INDEX idx_created (created_at),
        
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($sql)) {
        echo "✅ student_marks table created successfully or already exists<br>";
    } else {
        echo "❌ Error creating student_marks table: " . $conn->error . "<br>";
    }
    
    // Insert some sample data for testing
    $sample_data = [
        [1, 'Mathematics', 85.5, 100, 'Term 1', 'Good understanding of algebra concepts', 1],
        [1, 'English', 92.0, 100, 'Term 1', 'Excellent writing skills', 2],
        [1, 'Science', 78.5, 100, 'Term 1', 'Needs improvement in physics concepts', 1],
        [2, 'Mathematics', 76.0, 100, 'Term 1', 'Shows good problem-solving skills', 1],
        [2, 'English', 88.5, 100, 'Term 1', 'Very creative in writing assignments', 2],
    ];
    
    echo "<h3>Inserting sample marks data...</h3>";
    
    foreach ($sample_data as $data) {
        $check_stmt = $conn->prepare("SELECT id FROM student_marks WHERE student_id = ? AND subject = ? AND term = ?");
        $check_stmt->bind_param("iss", $data[0], $data[1], $data[4]);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO student_marks (student_id, subject, marks, max_marks, term, comments, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("isddssi", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]);
            
            if ($insert_stmt->execute()) {
                echo "✅ Sample marks added: Student {$data[0]} - {$data[1]}: {$data[2]}/{$data[3]}<br>";
            } else {
                echo "❌ Failed to add sample marks: " . $conn->error . "<br>";
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Sample marks already exist: Student {$data[0]} - {$data[1]}<br>";
        }
        $check_stmt->close();
    }
    
    echo "<h3>✅ Database setup complete!</h3>";
    echo "<p><a href='teacher/marks.php'>Go to Teacher Marks Page</a></p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
