<?php
// Simple test script to verify sentiment analysis
header('Content-Type: application/json');

$testText = "The teachers are very supportive and my child loves going to school.";

// Test the Python script
$escapedFeedback = escapeshellarg($testText);
$pythonScript = escapeshellcmd(__DIR__ . '/../python/sentiment_analysis.py');
$command = "python $pythonScript $escapedFeedback 2>&1";

echo "Testing command: $command\n";
$output = shell_exec($command);
echo "Output: $output\n";

$result = json_decode($output, true);
echo "Decoded result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
?> 