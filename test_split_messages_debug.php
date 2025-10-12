<?php
// test_split_messages_debug.php - DEBUG VERSION
// Shows EXACTLY what the API is saying

require_once 'api/split_message_handler.php';

echo "<h1>🔍 Split Messages DEBUG Test</h1>";

// Test if API key is loaded
$api_key = loadApiKey();
if (!$api_key) {
    echo "<div style='background: #ffebee; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
    echo "<h2 style='color: red;'>❌ API KEY NOT FOUND!</h2>";
    echo "<p>The split system requires an API key to work. Make sure your .env file has ANTHROPIC_API_KEY set.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #e8f5e9; padding: 15px; margin: 20px 0; border-radius: 10px;'>";
    echo "<strong>✅ API Key loaded:</strong> " . substr($api_key, 0, 20) . "...";
    echo "</div>";
}

$test_cases = [
    [
        'message' => "Oh my gosh! Really? That's amazing! I'm so happy for you! When did this happen?",
        'mood' => ['current_mood' => 'excited'],
        'energy' => 10,
        'label' => 'Very Excited Misuki (SHOULD SPLIT)'
    ],
    [
        'message' => "That sounds really interesting! I've always wanted to learn more about that. Chemistry is so fascinating, isn't it?",
        'mood' => ['current_mood' => 'excited'],
        'energy' => 9,
        'label' => 'Excited Misuki (SHOULD SPLIT)'
    ]
];

foreach ($test_cases as $index => $test) {
    echo "<div style='background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 10px; border: 2px solid #667eea;'>";
    echo "<h2 style='color: #667eea;'>🧪 Test Case " . ($index + 1) . ": {$test['label']}</h2>";
    
    echo "<div style='background: white; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Message:</strong><br>";
    echo "<em>\"{$test['message']}\"</em>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Mood:</strong> {$test['mood']['current_mood']}<br>";
    echo "<strong>Energy:</strong> {$test['energy']}/10<br>";
    echo "<strong>Word Count:</strong> " . str_word_count($test['message']) . " words";
    echo "</div>";
    
    // Create mock objects
    $message_analysis = ['emotion' => 'positive'];
    $conversation_style = ['current_energy_level' => $test['energy']];
    
    echo "<h3 style='color: #764ba2;'>🔍 Checking pre-filters...</h3>";
    
    $word_count = str_word_count($test['message']);
    if ($word_count < 15) {
        echo "<div style='background: #fff3e0; padding: 10px; margin: 5px 0;'>";
        echo "⚠️ Too short (< 15 words) - Would skip API";
        echo "</div>";
    } else {
        echo "<div style='background: #e8f5e9; padding: 10px; margin: 5px 0;'>";
        echo "✅ Long enough ($word_count words)";
        echo "</div>";
    }
    
    if (in_array($test['mood']['current_mood'], ['sleepy', 'tired']) && $test['energy'] < 5) {
        echo "<div style='background: #fff3e0; padding: 10px; margin: 5px 0;'>";
        echo "⚠️ Too tired - Would skip API";
        echo "</div>";
    } else {
        echo "<div style='background: #e8f5e9; padding: 10px; margin: 5px 0;'>";
        echo "✅ Energy level OK";
        echo "</div>";
    }
    
    echo "<h3 style='color: #764ba2;'>📡 Making API call...</h3>";
    
    // Manually test the decision prompt
    $prompt = buildSplitDecisionPrompt($test['message'], $test['mood'], $message_analysis, $conversation_style);
    
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Prompt sent to AI:</strong><br>";
    echo "<pre style='white-space: pre-wrap; font-size: 12px;'>" . htmlspecialchars($prompt) . "</pre>";
    echo "</div>";
    
    if ($api_key) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 100,
            'system' => $prompt,
            'messages' => [
                ['role' => 'user', 'content' => 'Decision:']
            ],
            'temperature' => 0.7
        ]));
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<div style='background: #fff; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #ddd;'>";
        echo "<strong>HTTP Status:</strong> $http_code<br>";
        
        if ($curl_error) {
            echo "<strong style='color: red;'>CURL Error:</strong> $curl_error<br>";
        }
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['content'][0]['text'])) {
                $ai_decision = $result['content'][0]['text'];
                echo "<strong>✅ AI Response:</strong> <code>" . htmlspecialchars($ai_decision) . "</code><br>";
                
                $decision = parseSplitDecision($ai_decision);
                
                if ($decision['should_split']) {
                    echo "<div style='background: #c8e6c9; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                    echo "<strong>✅ DECISION: SPLIT into {$decision['num_parts']} messages!</strong>";
                    echo "</div>";
                } else {
                    echo "<div style='background: #ffcdd2; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
                    echo "<strong>❌ DECISION: SINGLE message</strong><br>";
                    echo "<em>AI said to keep it as one message</em>";
                    echo "</div>";
                }
            } else {
                echo "<strong style='color: red;'>❌ Unexpected response format</strong><br>";
                echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
            }
        } else {
            echo "<strong style='color: red;'>❌ API Error (Status $http_code)</strong><br>";
            $error_result = json_decode($response, true);
            if (isset($error_result['error'])) {
                echo "<pre>" . htmlspecialchars(print_r($error_result['error'], true)) . "</pre>";
            } else {
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        }
        echo "</div>";
    }
    
    echo "</div>"; // End test case
}

echo "<hr style='margin: 40px 0;'>";
echo "<h2 style='color: #764ba2;'>📋 Summary</h2>";
echo "<p>This debug version shows you:</p>";
echo "<ul>";
echo "<li>✅ Whether the API key is loaded</li>";
echo "<li>✅ The exact prompt sent to Claude</li>";
echo "<li>✅ What Claude actually responds</li>";
echo "<li>✅ Whether the parsing logic works</li>";
echo "<li>✅ Any error messages from the API</li>";
echo "</ul>";

echo "<div style='background: #fff3e0; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>💡 What to Look For:</h3>";
echo "<p><strong>If Claude keeps saying SINGLE:</strong></p>";
echo "<ul>";
echo "<li>The prompt might not be convincing enough</li>";
echo "<li>Claude might be too conservative</li>";
echo "<li>We may need to adjust the criteria</li>";
echo "</ul>";
echo "<p><strong>If you see API errors:</strong></p>";
echo "<ul>";
echo "<li>Check your API key is valid</li>";
echo "<li>Make sure you have API credits</li>";
echo "<li>Verify your internet connection</li>";
echo "</ul>";
echo "</div>";

?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1400px;
    margin: 30px auto;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
h1 { 
    color: white; 
    text-align: center;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    margin-bottom: 30px;
}
h2 { color: #667eea; margin-top: 20px; }
h3 { color: #764ba2; margin-top: 15px; }
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
}
code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
</style>