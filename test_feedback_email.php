<?php
// Test script for feedback reply email functionality
require_once 'includes/email_helper_new.php';

// Test parameters
$parentEmail = 'test@example.com'; // Replace with a real email for testing
$parentName = 'John Doe';
$feedbackSubject = 'Test Feedback Subject';
$feedbackMessage = 'This is a test feedback message from a parent.';
$replyMessage = 'Thank you for your feedback. We have reviewed your concerns and will take appropriate action.';
$schoolName = 'Test School';

echo "Testing feedback reply email functionality...\n";
echo "Sending email to: $parentEmail\n";

$result = sendFeedbackReplyEmail(
    $parentEmail,
    $parentName,
    $feedbackSubject,
    $feedbackMessage,
    $replyMessage,
    $schoolName
);

if ($result['success']) {
    echo "✅ Email sent successfully!\n";
    echo "Message: " . $result['message'] . "\n";
} else {
    echo "❌ Email failed to send.\n";
    echo "Error: " . $result['message'] . "\n";
}

echo "\nTest completed.\n";
?> 