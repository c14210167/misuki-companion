<?php
// Quick debug script - visit this in browser to test API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 API Response Debug</h1>";

// Load API key
$api_key = getenv('ANTHROPIC_API_KEY');

if (!$api_key) {
    $env_path = dirname(__DIR__) . '/.env';
    if (file_exists($env_path)) {
        $env_contents = file_get_contents($env_path);
        if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
            $api_key = trim($matches[1]);
            $api_key = trim($api_key, '"\'');
        }
    }
}

echo "API Key loaded: " . (substr($api_key, 0, 20) . "...") . "<br><br>";

// Test the exact same call that chat.php makes
$test_message = "Hi Misuki!";

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . $api_key,
    'anthropic-version: 2023-06-01'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 220,
    'system' => 'You are Misuki. Say hello warmly.',
    'messages' => [
        ['role' => 'user', 'content' => $test_message]
    ],
    'temperature' => 1.0
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h2>HTTP Status Code: $http_code</h2>";

if ($curl_error) {
    echo "<p style='color: red;'><strong>CURL Error:</strong> $curl_error</p>";
}

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($http_code === 200) {
    $result = json_decode($response, true);
    if (isset($result['content'][0]['text'])) {
        echo "<h3 style='color: green;'>✅ SUCCESS! Misuki says:</h3>";
        echo "<p><strong>" . htmlspecialchars($result['content'][0]['text']) . "</strong></p>";
    } else {
        echo "<h3 style='color: orange;'>⚠️ Unexpected response format</h3>";
    }
} else {
    $error = json_decode($response, true);
    echo "<h3 style='color: red;'>❌ API Error</h3>";
    if (isset($error['error']['message'])) {
        echo "<p><strong>Error message:</strong> " . htmlspecialchars($error['error']['message']) . "</p>";
    }
    if (isset($error['error']['type'])) {
        echo "<p><strong>Error type:</strong> " . htmlspecialchars($error['error']['type']) . "</p>";
    }
    
    // Common issues
    if ($http_code === 401) {
        echo "<div style='background: #fee; padding: 15px; margin-top: 20px;'>";
        echo "<strong>🔑 API Key Issue:</strong><br>";
        echo "Your API key is invalid or has been revoked. Get a new one from console.anthropic.com";
        echo "</div>";
    } elseif ($http_code === 404) {
        echo "<div style='background: #fee; padding: 15px; margin-top: 20px;'>";
        echo "<strong>🤖 Model Issue:</strong><br>";
        echo "The model 'claude-sonnet-4-20250514' doesn't exist or you don't have access to it.<br>";
        echo "Try using: 'claude-3-5-sonnet-20241022' or 'claude-3-haiku-20240307'";
        echo "</div>";
    } elseif ($http_code === 429) {
        echo "<div style='background: #ffe; padding: 15px; margin-top: 20px;'>";
        echo "<strong>⏳ Rate Limit:</strong><br>";
        echo "Too many requests. Wait a moment and try again.";
        echo "</div>";
    }
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 { color: #667eea; }
h2 { color: #764ba2; }
pre {
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    border: 1px solid #ddd;
}
</style>