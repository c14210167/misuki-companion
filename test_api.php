<?php
// Debug tool to test Claude API connection
// Place this in your root directory and visit it in browser

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>Misuki API Debug Tool</h1>";

// Test 1: Database Connection
echo "<h2>1. Testing Database Connection...</h2>";
try {
    $db = getDBConnection();
    echo "✅ Database connected successfully!<br>";
    
    // Check if tables exist
    $tables = ['users', 'conversations', 'memories', 'conversation_initiation'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' is missing!<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: API Key Check
echo "<h2>2. Testing Claude API Key...</h2>";

$api_key = 'YOUR_API_KEY_HERE'; // REPLACE THIS WITH YOUR ACTUAL KEY

if ($api_key === 'YOUR_API_KEY_HERE' || $api_key === 'YOUR_NEW_API_KEY_HERE') {
    echo "❌ <strong>API KEY NOT SET!</strong> You need to replace 'YOUR_API_KEY_HERE' in api/chat.php<br>";
} else {
    echo "✅ API key is set (length: " . strlen($api_key) . " characters)<br>";
    
    // Test 3: Actually call Claude API
    echo "<h2>3. Testing Claude API Call...</h2>";
    
    $test_message = "Hi! This is a test. Just say hello!";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 100,
        'system' => 'You are Misuki. Say hello!',
        'messages' => [
            [
                'role' => 'user',
                'content' => $test_message
            ]
        ]
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: $http_code<br>";
    
    if ($http_code === 200) {
        echo "✅ <strong>API call successful!</strong><br>";
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            echo "Response from Claude: <strong>" . htmlspecialchars($result['content'][0]['text']) . "</strong><br>";
        } else {
            echo "⚠️ Response format unexpected<br>";
            echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
        }
    } else {
        echo "❌ <strong>API call failed!</strong><br>";
        echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
        
        $error = json_decode($response, true);
        if (isset($error['error']['message'])) {
            echo "<strong>Error message:</strong> " . htmlspecialchars($error['error']['message']) . "<br>";
        }
    }
}

// Test 4: Check if parse_emotions.php exists
echo "<h2>4. Checking Required Files...</h2>";
$required_files = [
    'api/chat.php',
    'api/parse_emotions.php',
    'api/check_initiate.php',
    'includes/functions.php',
    'config/database.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file is MISSING!<br>";
    }
}

echo "<h2>Summary</h2>";
echo "<p>If you see any ❌ errors above, fix them first!</p>";
echo "<p><strong>Most common issue:</strong> API key not set in api/chat.php and api/check_initiate.php</p>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 {
    color: #667eea;
}
h2 {
    color: #764ba2;
    margin-top: 30px;
}
pre {
    background: #fff;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
}
</style>