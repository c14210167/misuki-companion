<?php
// Clear PHP cache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared<br><br>";
}

// Check file modification time
$file = 'includes/future_events_handler.php';
if (file_exists($file)) {
    echo "📁 File: $file<br>";
    echo "📅 Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";
    echo "📏 File size: " . filesize($file) . " bytes<br><br>";
} else {
    echo "❌ File not found: $file<br><br>";
}

require_once 'includes/future_events_handler.php';

// Test if the function exists
if (function_exists('detectFutureEvent')) {
    echo "✅ Function 'detectFutureEvent' exists<br><br>";
} else {
    echo "❌ Function 'detectFutureEvent' NOT FOUND<br><br>";
}

// Test specific message
$test = "i'm going to pick up my friend at 12";
echo "<h2>Testing: \"$test\"</h2>";

// Check if regex matches
if (preg_match('/pick(?:ing)?\s+up\s+(.+?)\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $test, $matches)) {
    echo "✅ Regex MATCHES!<br>";
    echo "Matches:<br>";
    echo "<pre>";
    print_r($matches);
    echo "</pre>";
} else {
    echo "❌ Regex DOES NOT MATCH<br>";
}

// Now test the actual function
echo "<h3>Function Result:</h3>";
$result = detectFutureEvent($test);
echo "<pre>";
print_r($result);
echo "</pre>";

// Check initial value
echo "<h3>Testing initial array:</h3>";
$test_array = [
    'has_future_event' => false,
    'event_description' => null,
    'time_frame' => null,
    'planned_date' => null,
    'planned_time' => null
];
echo "<pre>";
print_r($test_array);
echo "</pre>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2 { color: #667eea; }
h3 { color: #764ba2; }
pre {
    background: white;
    padding: 15px;
    border-radius: 5px;
}
</style>