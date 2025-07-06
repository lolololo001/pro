<?php
require_once 'config/config.php';
require_once 'includes/email_helper_new.php';

echo "<h2>Permission Request Submission Test</h2>";

// Test database connection
try {
    $conn = getDbConnection();
    echo "✅ Database connection successful<br>";
    
    // Check if permission_requests table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        echo "✅ permission_requests table exists<br>";
        
        // Check table structure
        $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
        echo "📋 Table columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- {$col['Field']} ({$col['Type']})<br>";
        }
        
        // Check recent requests
        $recentRequests = $conn->query("SELECT * FROM permission_requests ORDER BY created_at DESC LIMIT 5");
        echo "<br>📝 Recent permission requests:<br>";
        if ($recentRequests->num_rows > 0) {
            while ($req = $recentRequests->fetch_assoc()) {
                echo "- ID: {$req['id']}, Parent: {$req['parent_id']}, Status: {$req['status']}, Created: {$req['created_at']}<br>";
            }
        } else {
            echo "No recent requests found<br>";
        }
        
    } else {
        echo "❌ permission_requests table does not exist<br>";
    }
    
    // Test email functionality
    echo "<br><h3>Testing Email Functionality</h3>";
    
    $testEmail = 'test@example.com'; // Replace with actual test email
    $testResults = sendPermissionRequestNotifications(
        $testEmail,
        'Test Parent',
        'Test Student',
        'medical',
        '2024-01-15 09:00:00',
        '2024-01-15 12:00:00',
        'This is a test permission request for medical appointment.',
        'Test School'
    );
    
    echo "📧 Email test results:<br>";
    echo "Parent email: " . ($testResults['parent']['success'] ? '✅ Success' : '❌ Failed') . "<br>";
    echo "Admin email: " . ($testResults['admin']['success'] ? '✅ Success' : '❌ Failed') . "<br>";
    
    if (!$testResults['parent']['success']) {
        echo "Parent email error: " . $testResults['parent']['message'] . "<br>";
    }
    if (!$testResults['admin']['success']) {
        echo "Admin email error: " . $testResults['admin']['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h3>Test Complete</h3>";
echo "<p>Check the error logs for detailed information about any issues.</p>";
?> 