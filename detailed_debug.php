<?php
// Detailed API debugging - place in root directory

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔬 Detailed API Debug</h1>";

// Load API key
$env_path = __DIR__ . '/.env';
$api_key = getenv('ANTHROPIC_API_KEY');

if (!$api_key && file_exists($env_path)) {
    $env_contents = file_get_contents($env_path);
    if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
        $api_key = trim($matches[1]);
        $api_key = trim($api_key, '"\'');
    }
}

if (!$api_key) {
    die("❌ No API key found!");
}

echo "✅ API Key loaded: " . substr($api_key, 0, 20) . "...***<br><br>";

// Test different models
$models_to_test = [
    'claude-sonnet-4-20250514',
    'claude-3-5-sonnet-20240620',
    'claude-3-sonnet-20240229',
    'claude-3-opus-20240229',
    'claude-3-haiku-20240307'
];

echo "<h2>Testing Different Models:</h2>";

foreach ($models_to_test as $model) {
    echo "<h3>Testing: $model</h3>";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'max_tokens' => 50,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Say "Hello" in one word only'
            ]
        ]
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: <strong>$http_code</strong><br>";
    
    if ($http_code === 200) {
        echo "✅ <strong style='color: green;'>SUCCESS!</strong><br>";
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            echo "Response: " . htmlspecialchars($result['content'][0]['text']) . "<br>";
        }
        echo "<div style='background: #efe; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✨ This model works! Use: <code>$model</code></strong>";
        echo "</div>";
    } else {
        echo "❌ Failed<br>";
        $result = json_decode($response, true);
        if (isset($result['error']['message'])) {
            echo "Error: " . htmlspecialchars($result['error']['message']) . "<br>";
        }
    }
    
    echo "<hr>";
}

echo "<h2>Next Steps:</h2>";
echo "<p>Use the model that shows ✅ SUCCESS above in your api/chat.php and api/check_initiate.php files.</p>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; }
h3 { color: #333; margin-top: 20px; }
code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
hr { margin: 20px 0; border: 1px solid #ddd; }
</style>