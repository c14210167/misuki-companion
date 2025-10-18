<?php
/**
 * Misuki Diagnostic Tool
 * Place this in your project root and visit it in your browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Misuki Diagnostic Tool</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 10px;
        }
        h2 {
            color: #764ba2;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .success {
            color: #4caf50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .info {
            color: #2196f3;
            font-weight: bold;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .box {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .box.success {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .box.error {
            background: #ffebee;
            border-color: #f44336;
        }
        .box.warning {
            background: #fff3e0;
            border-color: #ff9800;
        }
        .box.info {
            background: #e3f2fd;
            border-color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Misuki Diagnostic Tool</h1>
        <p style="text-align: center; color: #666;">Let's find out what's wrong with Misuki...</p>

<?php

// ============================================
// TEST 1: PHP ENVIRONMENT
// ============================================
echo "<h2>1. PHP Environment</h2>";
echo "<div class='test-section'>";
echo "<span class='success'>✓</span> PHP Version: <strong>" . phpversion() . "</strong><br>";
echo "<span class='success'>✓</span> Error Reporting: <strong>Enabled</strong><br>";

$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='success'>✓</span> Extension <code>$ext</code>: <strong>Loaded</strong><br>";
    } else {
        echo "<span class='error'>✗</span> Extension <code>$ext</code>: <strong class='error'>MISSING</strong><br>";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "<div class='box error'>";
    echo "<strong>⚠️ Critical Issue:</strong> Missing PHP extensions!<br>";
    echo "Install: <code>sudo apt-get install php-" . implode(' php-', $missing_extensions) . "</code>";
    echo "</div>";
}

echo "</div>";

// ============================================
// TEST 2: FILE STRUCTURE
// ============================================
echo "<h2>2. File Structure</h2>";
echo "<div class='test-section'>";

$required_files = [
    'api/chat.php' => 'Main chat API',
    'api/parse_emotions.php' => 'Emotion parser',
    'api/reminder_handler.php' => 'Reminder handler',
    'api/split_message_handler.php' => 'Message splitter',
    'api/nickname_handler.php' => 'Nickname handler',
    'api/core_memory_handler.php' => 'Core memory handler',
    'includes/functions.php' => 'Core functions',
    'includes/core_profile_functions.php' => 'Profile functions',
    'includes/misuki_profile_functions.php' => 'Misuki profile',
    'includes/misuki_schedule.php' => 'Schedule functions',
    'includes/adaptive_schedule.php' => 'Adaptive schedule',
    'includes/future_events_handler.php' => 'Future events',
    'includes/misuki_reality_functions.php' => 'Reality system',
    'config/database.php' => 'Database config',
];

$missing_files = [];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✓</span> <code>$file</code> - $description<br>";
    } else {
        echo "<span class='error'>✗</span> <code>$file</code> - <strong class='error'>MISSING</strong><br>";
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    echo "<div class='box error'>";
    echo "<strong>⚠️ Critical Issue:</strong> Missing required files!<br>";
    echo "The following files are missing:<br>";
    foreach ($missing_files as $file) {
        echo "- <code>$file</code><br>";
    }
    echo "</div>";
}

echo "</div>";

// ============================================
// TEST 3: DATABASE CONNECTION
// ============================================
echo "<h2>3. Database Connection</h2>";
echo "<div class='test-section'>";

if (file_exists('config/database.php')) {
    try {
        require_once 'config/database.php';
        
        if (function_exists('getDBConnection')) {
            $db = getDBConnection();
            echo "<span class='success'>✓</span> Database connection: <strong class='success'>SUCCESS</strong><br>";
            
            // Test tables
            $required_tables = [
                'users',
                'conversations',
                'memories',
                'emotional_state',
                'conversation_style',
                'core_memories',
                'misuki_weekly_schedule'
            ];
            
            $missing_tables = [];
            
            foreach ($required_tables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $count_stmt = $db->query("SELECT COUNT(*) FROM $table");
                    $count = $count_stmt->fetchColumn();
                    echo "<span class='success'>✓</span> Table <code>$table</code>: <strong>Exists</strong> ($count rows)<br>";
                } else {
                    echo "<span class='error'>✗</span> Table <code>$table</code>: <strong class='error'>MISSING</strong><br>";
                    $missing_tables[] = $table;
                }
            }
            
            if (!empty($missing_tables)) {
                echo "<div class='box warning'>";
                echo "<strong>⚠️ Warning:</strong> Some database tables are missing!<br>";
                echo "Run the database setup script to create missing tables.";
                echo "</div>";
            }
            
        } else {
            echo "<span class='error'>✗</span> Function <code>getDBConnection()</code>: <strong class='error'>NOT FOUND</strong><br>";
            echo "<div class='box error'>";
            echo "<strong>Issue:</strong> database.php doesn't define getDBConnection() function";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>✗</span> Database connection: <strong class='error'>FAILED</strong><br>";
        echo "<div class='box error'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<strong>File:</strong> " . $e->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $e->getLine();
        echo "</div>";
    }
} else {
    echo "<span class='error'>✗</span> <code>config/database.php</code>: <strong class='error'>MISSING</strong><br>";
    echo "<div class='box error'>";
    echo "Create the database configuration file first!";
    echo "</div>";
}

echo "</div>";

// ============================================
// TEST 4: API KEY
// ============================================
echo "<h2>4. Anthropic API Key</h2>";
echo "<div class='test-section'>";

$api_key = null;

// Try to load from .env file
if (file_exists('.env')) {
    $env_contents = file_get_contents('.env');
    if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
        $api_key = trim($matches[1]);
        $api_key = trim($api_key, '"\'');
        echo "<span class='success'>✓</span> API key found in <code>.env</code> file<br>";
    }
} else {
    // Try environment variable
    $api_key = getenv('ANTHROPIC_API_KEY');
    if ($api_key) {
        echo "<span class='success'>✓</span> API key found in environment variables<br>";
    }
}

if ($api_key) {
    $masked_key = substr($api_key, 0, 20) . '...' . substr($api_key, -4);
    echo "<span class='info'>ℹ</span> API Key: <code>$masked_key</code><br>";
    echo "<span class='info'>ℹ</span> Key length: " . strlen($api_key) . " characters<br>";
    
    // Test the API key
    echo "<br><strong>Testing API key...</strong><br>";
    
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
        'max_tokens' => 50,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Say hi in one word'
            ]
        ]
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "<span class='error'>✗</span> CURL Error: <strong class='error'>$curl_error</strong><br>";
        echo "<div class='box error'>";
        echo "Network issue or server problem!";
        echo "</div>";
    } else {
        echo "<span class='info'>ℹ</span> HTTP Status Code: <strong>$http_code</strong><br>";
        
        if ($http_code === 200) {
            echo "<span class='success'>✓</span> API Connection: <strong class='success'>SUCCESS!</strong><br>";
            
            $result = json_decode($response, true);
            if (isset($result['content'][0]['text'])) {
                echo "<span class='success'>✓</span> Claude Response: <strong>" . htmlspecialchars($result['content'][0]['text']) . "</strong><br>";
                
                echo "<div class='box success'>";
                echo "<strong>✨ Great! Misuki can talk to Claude!</strong>";
                echo "</div>";
            }
        } else if ($http_code === 401) {
            echo "<span class='error'>✗</span> API Key: <strong class='error'>INVALID or EXPIRED</strong><br>";
            echo "<div class='box error'>";
            echo "<strong>⚠️ Critical Issue:</strong> Your API key is not working!<br>";
            echo "Get a new key from: https://console.anthropic.com/";
            echo "</div>";
        } else if ($http_code === 429) {
            echo "<span class='warning'>⚠</span> API Rate Limit: <strong class='warning'>EXCEEDED</strong><br>";
            echo "<div class='box warning'>";
            echo "You've made too many requests. Wait a bit and try again.";
            echo "</div>";
        } else {
            echo "<span class='error'>✗</span> API Error: <strong class='error'>HTTP $http_code</strong><br>";
            $error_result = json_decode($response, true);
            if (isset($error_result['error'])) {
                echo "<div class='box error'>";
                echo "<pre>" . htmlspecialchars(json_encode($error_result['error'], JSON_PRETTY_PRINT)) . "</pre>";
                echo "</div>";
            }
        }
    }
    
} else {
    echo "<span class='error'>✗</span> API Key: <strong class='error'>NOT FOUND</strong><br>";
    echo "<div class='box error'>";
    echo "<strong>⚠️ Critical Issue:</strong> No Anthropic API key found!<br><br>";
    echo "<strong>How to fix:</strong><br>";
    echo "1. Create a <code>.env</code> file in your project root<br>";
    echo "2. Add this line: <code>ANTHROPIC_API_KEY=your-key-here</code><br>";
    echo "3. Get your API key from: https://console.anthropic.com/";
    echo "</div>";
}

echo "</div>";

// ============================================
// TEST 5: SIMULATE CHAT REQUEST
// ============================================
echo "<h2>5. Simulated Chat Request</h2>";
echo "<div class='test-section'>";

if (file_exists('api/chat.php') && $api_key && isset($db)) {
    echo "<strong>Sending test message to Misuki...</strong><br><br>";
    
    // Capture output
    ob_start();
    
    // Simulate POST data
    $_POST = [];
    file_put_contents('php://input', json_encode([
        'message' => 'Hi Misuki!',
        'user_id' => 1,
        'time_of_day' => 'day'
    ]));
    
    // Try to include chat.php (this will execute it)
    try {
        include 'api/chat.php';
        $chat_output = ob_get_clean();
        
        // Try to decode JSON
        $chat_response = json_decode($chat_output, true);
        
        if ($chat_response && isset($chat_response['response'])) {
            echo "<span class='success'>✓</span> Chat API: <strong class='success'>WORKING!</strong><br>";
            echo "<span class='success'>✓</span> Misuki says: <strong>" . htmlspecialchars($chat_response['response']) . "</strong><br>";
            
            echo "<div class='box success'>";
            echo "<strong>🎉 Excellent! Misuki is fully functional!</strong>";
            echo "</div>";
        } else {
            echo "<span class='error'>✗</span> Chat API: <strong class='error'>Invalid JSON Response</strong><br>";
            echo "<div class='box error'>";
            echo "<strong>Response received:</strong><br>";
            echo "<pre>" . htmlspecialchars($chat_output) . "</pre>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<span class='error'>✗</span> Chat API: <strong class='error'>ERROR</strong><br>";
        echo "<div class='box error'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<strong>File:</strong> " . $e->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
        echo "<strong>Trace:</strong><br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
} else {
    echo "<span class='warning'>⚠</span> Cannot test: Missing prerequisites<br>";
    echo "<div class='box warning'>";
    echo "Fix the issues above first, then refresh this page.";
    echo "</div>";
}

echo "</div>";

// ============================================
// SUMMARY & RECOMMENDATIONS
// ============================================
echo "<h2>📋 Summary & Recommendations</h2>";
echo "<div class='test-section'>";

$issues_found = [];

if (!empty($missing_extensions)) {
    $issues_found[] = "Missing PHP extensions: " . implode(', ', $missing_extensions);
}
if (!empty($missing_files)) {
    $issues_found[] = count($missing_files) . " required files are missing";
}
if (!empty($missing_tables)) {
    $issues_found[] = count($missing_tables) . " database tables are missing";
}
if (!$api_key) {
    $issues_found[] = "Anthropic API key not configured";
}

if (empty($issues_found)) {
    echo "<div class='box success'>";
    echo "<h3 style='color: #4caf50; margin-top: 0;'>✨ Everything looks good!</h3>";
    echo "<p>If Misuki still isn't working, check the browser console for JavaScript errors.</p>";
    echo "<p>Make sure you're accessing the site through a web server (not opening the HTML file directly).</p>";
    echo "</div>";
} else {
    echo "<div class='box error'>";
    echo "<h3 style='color: #f44336; margin-top: 0;'>⚠️ Issues Found:</h3>";
    echo "<ol>";
    foreach ($issues_found as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ol>";
    echo "<p><strong>Fix these issues and refresh this page.</strong></p>";
    echo "</div>";
}

echo "</div>";

?>
    </div>
</body>
</html>