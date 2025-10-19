<?php
/**
 * CHAT API DIRECT TESTER
 * Tests api/chat.php directly and shows any PHP errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>🔧 Chat API Direct Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; }
        h2 { color: #764ba2; margin-top: 30px; }
        pre {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            max-height: 500px;
            overflow-y: auto;
        }
        .error { color: #f44336; background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { color: #4caf50; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #ff9800; background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Chat API Direct Tester</h1>
        <p>This will call api/chat.php directly and show you exactly what's breaking.</p>

        <h2>Step 1: Check PHP Error Log</h2>
        <?php
        // Try to find PHP error log
        $error_log_locations = [
            ini_get('error_log'),
            '/var/log/php_errors.log',
            '/var/log/apache2/error.log',
            dirname(__FILE__) . '/error_log',
            dirname(__FILE__) . '/php_errors.log'
        ];
        
        $found_log = false;
        foreach ($error_log_locations as $log_path) {
            if ($log_path && file_exists($log_path) && is_readable($log_path)) {
                echo "<div class='success'>✅ Found error log: <code>$log_path</code></div>";
                
                // Get last 50 lines
                $lines = file($log_path);
                $recent_lines = array_slice($lines, -50);
                
                echo "<h3>Last 50 lines from error log:</h3>";
                echo "<pre>" . htmlspecialchars(implode('', $recent_lines)) . "</pre>";
                
                $found_log = true;
                break;
            }
        }
        
        if (!$found_log) {
            echo "<div class='warning'>⚠️ Could not find PHP error log. Errors will be shown inline below.</div>";
            echo "<p>Error log should be at: <code>" . ini_get('error_log') . "</code></p>";
        }
        ?>

        <h2>Step 2: Test API Call Directly</h2>
        <button onclick="testAPI()">🧪 Test Chat API Now</button>
        <div id="test-result"></div>

        <h2>Step 3: Manual PHP Execution</h2>
        <p>Running api/chat.php directly with test input...</p>
        
        <?php
        // Simulate a chat request
        echo "<h3>Simulating POST to api/chat.php</h3>";
        
        // Save current output buffer
        ob_start();
        
        // Mock the POST data
        $_POST = [];
        $test_json = json_encode([
            'message' => 'Hi Misuki!',
            'user_id' => 1,
            'time_of_day' => 'day',
            'time_confused' => false
        ]);
        
        // Create a temporary stream for php://input
        $temp_input = fopen('php://temp', 'r+');
        fwrite($temp_input, $test_json);
        rewind($temp_input);
        
        try {
            // Change directory to api folder
            chdir(__DIR__ . '/api');
            
            // Include the chat.php file
            include 'chat.php';
            
            // Get the output
            $output = ob_get_clean();
            
            echo "<div class='success'>";
            echo "<h4>✅ API executed without fatal errors!</h4>";
            echo "<p><strong>Raw Output:</strong></p>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
            
            // Try to parse as JSON
            $json = json_decode($output, true);
            if ($json) {
                echo "<h4>✅ Valid JSON Response!</h4>";
                echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
                
                if (isset($json['response'])) {
                    echo "<div class='success'>";
                    echo "<h4>🎉 Misuki says:</h4>";
                    echo "<p><strong>" . htmlspecialchars($json['response']) . "</strong></p>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>";
                echo "<h4>❌ Invalid JSON!</h4>";
                echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
                echo "</div>";
            }
            echo "</div>";
            
        } catch (Throwable $e) {
            ob_end_clean();
            
            echo "<div class='error'>";
            echo "<h4>❌ PHP Error Caught!</h4>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
            echo "<h4>Stack Trace:</h4>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
            
            // Provide specific help based on error
            $error_msg = $e->getMessage();
            
            if (strpos($error_msg, 'require') !== false || strpos($error_msg, 'include') !== false) {
                echo "<div class='warning'>";
                echo "<h4>💡 File Not Found Issue</h4>";
                echo "<p>A required file is missing. Check that all files in the includes/ folder exist.</p>";
                echo "</div>";
            } else if (strpos($error_msg, 'Call to undefined function') !== false) {
                echo "<div class='warning'>";
                echo "<h4>💡 Missing Function Issue</h4>";
                echo "<p>A function is being called that doesn't exist. This usually means a required file isn't being loaded.</p>";
                echo "</div>";
            } else if (strpos($error_msg, 'api_key') !== false || strpos($error_msg, 'ANTHROPIC') !== false) {
                echo "<div class='warning'>";
                echo "<h4>💡 API Key Issue</h4>";
                echo "<p>Check your .env file has: ANTHROPIC_API_KEY=your-key-here</p>";
                echo "</div>";
            }
        }
        
        fclose($temp_input);
        ?>

        <h2>Step 4: Check Required Files</h2>
        <?php
        $required_files = [
            'api/chat.php',
            'api/parse_emotions.php',
            'config/database.php',
            'includes/functions.php',
            'includes/misuki_schedule.php',
            'includes/misuki_weekly_schedule.php',
            '.env'
        ];
        
        echo "<ul>";
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                echo "<li style='color: green;'>✅ $file</li>";
            } else {
                echo "<li style='color: red;'>❌ <strong>$file is MISSING!</strong></li>";
            }
        }
        echo "</ul>";
        ?>

        <h2>Step 5: Check .env File</h2>
        <?php
        if (file_exists('.env')) {
            $env_contents = file_get_contents('.env');
            
            if (preg_match('/ANTHROPIC_API_KEY\s*=\s*(.+)/', $env_contents, $matches)) {
                $key = trim($matches[1], '"\'');
                $masked = substr($key, 0, 20) . '...' . substr($key, -4);
                echo "<div class='success'>✅ API Key found: <code>$masked</code></div>";
                echo "<p>Key length: " . strlen($key) . " characters</p>";
            } else {
                echo "<div class='error'>❌ No ANTHROPIC_API_KEY found in .env file!</div>";
            }
        } else {
            echo "<div class='error'>❌ .env file not found!</div>";
            echo "<p>Create a .env file with: <code>ANTHROPIC_API_KEY=your-key-here</code></p>";
        }
        ?>
    </div>

    <script>
        async function testAPI() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<p>⏳ Testing API...</p>';
            
            try {
                const response = await fetch('api/chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: 'Hi Misuki!',
                        user_id: 1,
                        time_of_day: 'day',
                        time_confused: false
                    })
                });
                
                const status = response.status;
                const text = await response.text();
                
                let html = '<div style="margin-top: 20px;">';
                html += '<h3>HTTP Status: ' + status + '</h3>';
                
                if (status === 200) {
                    html += '<div class="success"><h4>✅ Status 200 OK!</h4>';
                    
                    try {
                        const json = JSON.parse(text);
                        html += '<p><strong>Valid JSON Response!</strong></p>';
                        html += '<pre>' + JSON.stringify(json, null, 2) + '</pre>';
                        
                        if (json.response) {
                            html += '<div class="success"><h4>🎉 Misuki says:</h4>';
                            html += '<p><strong>' + json.response + '</strong></p></div>';
                        }
                    } catch (e) {
                        html += '</div><div class="error"><h4>❌ Invalid JSON!</h4>';
                        html += '<p>JSON Parse Error: ' + e.message + '</p>';
                        html += '<p><strong>Raw Response:</strong></p>';
                        html += '<pre>' + text.substring(0, 1000) + '</pre>';
                    }
                } else {
                    html += '<div class="error"><h4>❌ HTTP Error ' + status + '</h4>';
                    html += '<p><strong>Response:</strong></p>';
                    html += '<pre>' + text.substring(0, 1000) + '</pre>';
                }
                
                html += '</div></div>';
                resultDiv.innerHTML = html;
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="error"><h4>❌ Network Error!</h4><p>' + error.message + '</p></div>';
            }
        }
    </script>
</body>
</html>