<?php
// Place this in your ROOT directory and visit it

echo "<h1>🔍 .env File Debug Test</h1>";

// Test 1: Check if .env file exists
echo "<h2>1. Checking .env file location</h2>";
$env_path = __DIR__ . '/.env';
echo "Looking for .env at: <code>$env_path</code><br>";

if (file_exists($env_path)) {
    echo "✅ .env file EXISTS!<br>";
    echo "File size: " . filesize($env_path) . " bytes<br>";
    echo "File permissions: " . substr(sprintf('%o', fileperms($env_path)), -4) . "<br>";
} else {
    echo "❌ .env file NOT FOUND!<br>";
    echo "<strong>Create a file named '.env' (with the dot) in: $env_path</strong><br>";
}

// Test 2: Try to read .env contents
echo "<h2>2. Reading .env file contents</h2>";
if (file_exists($env_path)) {
    $contents = file_get_contents($env_path);
    
    if ($contents === false) {
        echo "❌ Cannot read .env file (permission issue?)<br>";
    } else {
        echo "✅ File readable! Length: " . strlen($contents) . " characters<br>";
        
        // Show file contents (with API key partially hidden)
        echo "<h3>File contents (API key hidden for security):</h3>";
        echo "<pre>";
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                echo htmlspecialchars($line) . "\n";
            } elseif (strpos($line, 'ANTHROPIC_API_KEY=') !== false) {
                $parts = explode('=', $line, 2);
                if (isset($parts[1])) {
                    $key = trim($parts[1]);
                    if (strlen($key) > 10) {
                        echo "ANTHROPIC_API_KEY=" . substr($key, 0, 20) . "..." . substr($key, -4) . "\n";
                    } else {
                        echo "ANTHROPIC_API_KEY=(empty or invalid)\n";
                    }
                }
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    }
} else {
    echo "⏭️ Skipping (file doesn't exist)<br>";
}

// Test 3: Try getenv()
echo "<h2>3. Testing getenv() function</h2>";
$key_from_getenv = getenv('ANTHROPIC_API_KEY');
if ($key_from_getenv) {
    echo "✅ getenv() found key: " . substr($key_from_getenv, 0, 20) . "...***<br>";
} else {
    echo "❌ getenv() returned nothing<br>";
    echo "Note: getenv() only works if you set environment variables in your server config<br>";
}

// Test 4: Try manual parsing (what the code does)
echo "<h2>4. Testing manual .env parsing (fallback method)</h2>";
if (file_exists($env_path)) {
    $env_contents = file_get_contents($env_path);
    if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
        $api_key = trim($matches[1]);
        
        // Remove quotes if present
        $api_key = trim($api_key, '"\'');
        
        if (!empty($api_key) && $api_key !== 'YOUR-ACTUAL-API-KEY-HERE') {
            echo "✅ Manual parsing SUCCESS!<br>";
            echo "Key found: " . substr($api_key, 0, 20) . "...***<br>";
            echo "Key length: " . strlen($api_key) . " characters<br>";
            
            // Validate format
            if (strpos($api_key, 'sk-ant-api03-') === 0) {
                echo "✅ Key format looks correct (starts with sk-ant-api03-)<br>";
            } else {
                echo "⚠️ Key format may be incorrect (should start with sk-ant-api03-)<br>";
            }
        } else {
            echo "❌ Key is empty or placeholder<br>";
        }
    } else {
        echo "❌ Could not find ANTHROPIC_API_KEY in .env file<br>";
        echo "Make sure the line looks like: ANTHROPIC_API_KEY=sk-ant-api03-...<br>";
    }
} else {
    echo "⏭️ Skipping (file doesn't exist)<br>";
}

// Test 5: Check if curl is available
echo "<h2>5. Checking CURL availability</h2>";
if (function_exists('curl_init')) {
    echo "✅ CURL is available<br>";
} else {
    echo "❌ CURL is NOT available (required for API calls!)<br>";
}

// Test 6: Actual API test
echo "<h2>6. Testing actual API call</h2>";
if (file_exists($env_path)) {
    $env_contents = file_get_contents($env_path);
    if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
        $api_key = trim($matches[1]);
        $api_key = trim($api_key, '"\'');
        
        if (!empty($api_key) && $api_key !== 'YOUR-ACTUAL-API-KEY-HERE') {
            echo "Attempting API call...<br>";
            
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
                'max_tokens' => 50,
                'system' => 'You are Misuki. Say "Hello Dan!" in a cute way.',
                'messages' => [
                    ['role' => 'user', 'content' => 'Say hello!']
                ]
            ]));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "HTTP Status Code: <strong>$http_code</strong><br>";
            
            if ($http_code === 200) {
                echo "✅ <strong style='color: green;'>API CALL SUCCESSFUL!</strong><br>";
                $result = json_decode($response, true);
                if (isset($result['content'][0]['text'])) {
                    echo "Misuki says: <strong>" . htmlspecialchars($result['content'][0]['text']) . "</strong><br>";
                }
            } elseif ($http_code === 401) {
                echo "❌ <strong style='color: red;'>401 Unauthorized - Invalid API Key</strong><br>";
                echo "Your API key is incorrect or has been revoked. Get a new one from console.anthropic.com<br>";
            } elseif ($http_code === 429) {
                echo "⚠️ 429 Rate Limited - Too many requests. Wait a moment and try again.<br>";
            } else {
                echo "❌ API call failed<br>";
                echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "⏭️ Skipping (no valid API key found)<br>";
        }
    }
}

echo "<hr>";
echo "<h2>📋 Summary & Next Steps</h2>";

if (!file_exists($env_path)) {
    echo "<div style='background: #fee; padding: 15px; border-left: 4px solid red;'>";
    echo "<strong>❌ Problem: .env file is missing</strong><br>";
    echo "Solution: Create a file named <code>.env</code> in: <code>$env_path</code><br>";
    echo "Add this line to it: <code>ANTHROPIC_API_KEY=sk-ant-api03-your-key-here</code>";
    echo "</div>";
} else {
    echo "<div style='background: #efe; padding: 15px; border-left: 4px solid green;'>";
    echo "✅ .env file exists! Check the results above to see if the API key is working.";
    echo "</div>";
}

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
h2 { 
    color: #764ba2; 
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #ddd;
}
code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
pre {
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    border: 1px solid #ddd;
}
</style>