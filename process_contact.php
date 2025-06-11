<?php
// Initialize the application
require_once 'config/config.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    // If no errors, process the form
    if (empty($errors)) {
        try {
            // Get database connection
            $conn = getDbConnection();
            
            // Check if contact_messages table exists
            $tableExists = $conn->query("SHOW TABLES LIKE 'contact_messages'")->num_rows > 0;
            
            // Create table if it doesn't exist
            if (!$tableExists) {
                $createTableSQL = "CREATE TABLE contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    subject VARCHAR(200) NOT NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('new', 'read', 'replied') DEFAULT 'new'
                )";
                
                $conn->query($createTableSQL);
            }
            
            // Insert the message into the database
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            $stmt->execute();
            $stmt->close();
            
            // Close the database connection
            $conn->close();
            
            // Redirect back to the contact form with success message
            header("Location: index.php?contact=success#contact");
            exit;
            
        } catch (Exception $e) {
            // Log the error
            error_log("Error processing contact form: " . $e->getMessage());
            
            // Redirect with error
            header("Location: index.php?contact=error#contact");
            exit;
        }
    } else {
        // If there are errors, redirect back with error message
        $_SESSION['contact_errors'] = $errors;
        header("Location: index.php?contact=error#contact");
        exit;
    }
} else {
    // If not a POST request, redirect to the homepage
    header("Location: index.php");
    exit;
}