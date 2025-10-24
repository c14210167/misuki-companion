<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'api/private_mode_handler.php';

echo "<h1>Private Mode Test</h1>";

// Test detection
$test_message = "sext";
echo "<p>Testing message: '$test_message'</p>";

if (detectPrivateModeStart($test_message)) {
    echo "<p style='color: green;'>✓ Private mode DETECTED!</p>";
} else {
    echo "<p style='color: red;'>✗ Private mode NOT detected</p>";
}

// Test state file
echo "<p>Attempting to save state...</p>";
if (setPrivateMode(1, true)) {
    echo "<p style='color: green;'>✓ State saved successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to save state</p>";
}

// Check if state exists
if (file_exists('private_mode_state.txt')) {
    echo "<p>State file contents:</p>";
    echo "<pre>" . file_get_contents('private_mode_state.txt') . "</pre>";
} else {
    echo "<p style='color: red;'>State file doesn't exist!</p>";
}
?>