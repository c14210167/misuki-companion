<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * MISUKI COMPREHENSIVE DEBUG TOOL
 * Run this file whenever there's an error to get complete diagnostics
 * ═══════════════════════════════════════════════════════════════════
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Misuki Complete Diagnostic Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
        }
        .timestamp {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin: 30px 0;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .section-header {
            background: #f5f5f5;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 18px;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .section-content {
            padding: 20px;
        }
        .status-item {
            padding: 10px;
            margin: 8px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .warning {
            background: #fff3e0;
            color: #e65100;
            border-left: 4px solid #ff9800;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
            font-weight: bold;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.4;
            margin: 10px 0;
        }
        .code-block {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .critical {
            background: #d32f2f;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 16px;
            font-weight: bold;
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
        }
        .summary h2 {
            margin-bottom: 15px;
        }
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
        }
        .copy-btn:hover {
            background: #5568d3;
        }
        .expandable {
            cursor: pointer;
            user-select: none;
        }
        .expandable:hover {
            background: #f0f0f0;
        }
        .hidden-content {
            display: none;
            padding: 15px;
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Misuki Complete Diagnostic Report</h1>
    <div class="timestamp">Generated: <?php echo date('Y-m-d H:i:s'); ?></div>

<?php
// ═══════════════════════════════════════════════════════════════════
// COLLECT ALL DIAGNOSTIC DATA
// ═══════════════════════════════════════════════════════════════════

$diagnostics = [];
$errors_found = [];
$warnings_found = [];

// ═══════════════════════════════════════════════════════════════════
// SECTION 1: PHP ENVIRONMENT
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">📋 1. PHP Environment</div>';
echo '<div class="section-content">';

$php_version = phpversion();
echo "<div class='status-item info'><span class='icon'>ℹ️</span> PHP Version: <strong>$php_version</strong></div>";

$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='status-item success'><span class='icon'>✓</span> Extension: <strong>$ext</strong> - Loaded</div>";
    } else {
        echo "<div class='status-item error'><span class='icon'>✗</span> Extension: <strong>$ext</strong> - MISSING</div>";
        $missing_extensions[] = $ext;
        $errors_found[] = "Missing PHP extension: $ext";
    }
}

// Memory
$memory_limit = ini_get('memory_limit');
echo "<div class='status-item info'><span class='icon'>💾</span> Memory Limit: <strong>$memory_limit</strong></div>";

// Max execution time
$max_time = ini_get('max_execution_time');
echo "<div class='status-item info'><span class='icon'>⏱️</span> Max Execution Time: <strong>{$max_time}s</strong></div>";

// Error log location
$error_log = ini_get('error_log');
echo "<div class='status-item info'><span class='icon'>📝</span> Error Log: <strong>" . ($error_log ?: 'Not configured') . "</strong></div>";

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 2: FILE SYSTEM CHECK
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">📁 2. File System & Permissions</div>';
echo '<div class="section-content">';

$critical_files = [
    'api/chat.php',
    'api/get_history.php',
    'api/private_mode_handler.php',
    'config/database.php',
    'includes/functions.php',
    'includes/core_profile_functions.php',
    'includes/misuki_profile_functions.php',
    'includes/misuki_schedule.php',
    '.env',
    'assets/js/chat.js',
    'assets/js/modules/history.js',
    'assets/js/modules/messaging.js'
];

$missing_files = [];
foreach ($critical_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        $readable = is_readable($full_path) ? 'Yes' : 'No';
        echo "<div class='status-item success'><span class='icon'>✓</span> <strong>$file</strong> - " . number_format($size) . " bytes, Readable: $readable</div>";
    } else {
        echo "<div class='status-item error'><span class='icon'>✗</span> <strong>$file</strong> - FILE NOT FOUND</div>";
        $missing_files[] = $file;
        $errors_found[] = "Missing file: $file";
    }
}

// Check writable directories
$writable_dirs = ['uploads/', 'logs/'];
foreach ($writable_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (file_exists($full_path)) {
        if (is_writable($full_path)) {
            echo "<div class='status-item success'><span class='icon'>✓</span> Directory <strong>$dir</strong> - Writable</div>";
        } else {
            echo "<div class='status-item error'><span class='icon'>✗</span> Directory <strong>$dir</strong> - NOT WRITABLE</div>";
            $errors_found[] = "Directory not writable: $dir";
        }
    } else {
        echo "<div class='status-item warning'><span class='icon'>⚠</span> Directory <strong>$dir</strong> - Does not exist (will be created)</div>";
        $warnings_found[] = "Directory missing: $dir";
    }
}

// Check private_mode_state.txt
$private_state = __DIR__ . '/private_mode_state.txt';
if (file_exists($private_state)) {
    if (is_writable($private_state)) {
        $content = file_get_contents($private_state);
        echo "<div class='status-item success'><span class='icon'>✓</span> <strong>private_mode_state.txt</strong> - Exists and writable</div>";
        echo "<div class='code-block'>Content: " . htmlspecialchars($content) . "</div>";
    } else {
        echo "<div class='status-item error'><span class='icon'>✗</span> <strong>private_mode_state.txt</strong> - NOT WRITABLE</div>";
        $errors_found[] = "private_mode_state.txt not writable";
    }
} else {
    echo "<div class='status-item warning'><span class='icon'>⚠</span> <strong>private_mode_state.txt</strong> - Does not exist</div>";
    $warnings_found[] = "private_mode_state.txt missing";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 3: DATABASE CONNECTION
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">🗄️ 3. Database Connection</div>';
echo '<div class="section-content">';

$db = null;
try {
    require_once __DIR__ . '/config/database.php';
    $db = getDBConnection();
    echo "<div class='status-item success'><span class='icon'>✓</span> Database connection - SUCCESS</div>";
    
    // Check tables
    $required_tables = [
        'users', 'conversations', 'memories', 'conversation_initiation',
        'misuki_mood', 'conversation_state', 'conversation_style',
        'relationship_dynamics', 'future_events', 'reminders'
    ];
    
    foreach ($required_tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            // Get row count
            $count_stmt = $db->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "<div class='status-item success'><span class='icon'>✓</span> Table <strong>$table</strong> - Exists ($count rows)</div>";
        } else {
            echo "<div class='status-item error'><span class='icon'>✗</span> Table <strong>$table</strong> - MISSING</div>";
            $errors_found[] = "Missing database table: $table";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='status-item error'><span class='icon'>✗</span> Database connection - FAILED</div>";
    echo "<div class='code-block'>" . htmlspecialchars($e->getMessage()) . "</div>";
    $errors_found[] = "Database connection failed: " . $e->getMessage();
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 4: API KEY & CONFIGURATION
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">🔑 4. API Key & Configuration</div>';
echo '<div class="section-content">';

$api_key = null;

// Check environment variable
$env_api_key = getenv('ANTHROPIC_API_KEY');
if ($env_api_key) {
    echo "<div class='status-item success'><span class='icon'>✓</span> API Key from environment variable - Found (" . strlen($env_api_key) . " chars)</div>";
    $api_key = $env_api_key;
}

// Check .env file
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    echo "<div class='status-item success'><span class='icon'>✓</span> .env file - Found</div>";
    
    $env_contents = file_get_contents($env_path);
    $env_lines = explode("\n", $env_contents);
    
    foreach ($env_lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        if (preg_match('/ANTHROPIC_API_KEY\s*=\s*(.+)/', $line, $matches)) {
            $key = trim($matches[1], '"\'');
            if (!empty($key) && $key !== 'YOUR-API-KEY-HERE' && $key !== 'your-api-key-here') {
                echo "<div class='status-item success'><span class='icon'>✓</span> API Key from .env - Found (" . strlen($key) . " chars)</div>";
                $api_key = $key;
                
                // Validate format
                if (strpos($key, 'sk-ant-api03-') === 0) {
                    echo "<div class='status-item success'><span class='icon'>✓</span> API Key format - Valid (starts with sk-ant-api03-)</div>";
                } else {
                    echo "<div class='status-item warning'><span class='icon'>⚠</span> API Key format - Unusual (should start with sk-ant-api03-)</div>";
                    $warnings_found[] = "API key format unusual";
                }
            } else {
                echo "<div class='status-item error'><span class='icon'>✗</span> API Key - Empty or placeholder</div>";
                $errors_found[] = "API key is placeholder or empty";
            }
            break;
        }
    }
} else {
    echo "<div class='status-item error'><span class='icon'>✗</span> .env file - NOT FOUND</div>";
    $errors_found[] = ".env file not found";
}

if (!$api_key) {
    echo "<div class='critical'>❌ CRITICAL: No API key found! Misuki cannot function without an Anthropic API key.</div>";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 5: CURL & NETWORK
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">🌐 5. CURL & Network Configuration</div>';
echo '<div class="section-content">';

if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "<div class='status-item success'><span class='icon'>✓</span> CURL - Available (version " . $curl_version['version'] . ")</div>";
    echo "<div class='status-item info'><span class='icon'>ℹ️</span> SSL Version: " . $curl_version['ssl_version'] . "</div>";
} else {
    echo "<div class='status-item error'><span class='icon'>✗</span> CURL - NOT AVAILABLE</div>";
    $errors_found[] = "CURL not available";
}

// Test CURL to Anthropic
if ($api_key && function_exists('curl_init')) {
    echo "<div class='status-item info'><span class='icon'>🧪</span> Testing connection to Anthropic API...</div>";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
            ['role' => 'user', 'content' => 'Say "test" in one word']
        ]
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "<div class='status-item error'><span class='icon'>✗</span> CURL Error: $curl_error</div>";
        $errors_found[] = "CURL error: $curl_error";
    } else {
        echo "<div class='status-item info'><span class='icon'>📡</span> HTTP Status Code: <strong>$http_code</strong></div>";
        
        if ($http_code === 200) {
            echo "<div class='status-item success'><span class='icon'>✓</span> API Connection - SUCCESS</div>";
            $result = json_decode($response, true);
            if (isset($result['content'][0]['text'])) {
                echo "<div class='status-item success'><span class='icon'>💬</span> Claude Response: <strong>" . htmlspecialchars($result['content'][0]['text']) . "</strong></div>";
            }
        } elseif ($http_code === 401) {
            echo "<div class='status-item error'><span class='icon'>✗</span> API Connection - UNAUTHORIZED (Invalid API Key)</div>";
            $errors_found[] = "API key is invalid or expired";
        } elseif ($http_code === 404) {
            echo "<div class='status-item error'><span class='icon'>✗</span> API Connection - NOT FOUND (Model may not exist)</div>";
            $errors_found[] = "Model not found or inaccessible";
        } elseif ($http_code === 429) {
            echo "<div class='status-item warning'><span class='icon'>⚠</span> API Connection - RATE LIMITED</div>";
            $warnings_found[] = "API rate limit exceeded";
        } else {
            echo "<div class='status-item error'><span class='icon'>✗</span> API Connection - FAILED</div>";
            $errors_found[] = "API returned HTTP $http_code";
        }
        
        if ($http_code !== 200) {
            echo "<div class='expandable' onclick='this.nextElementSibling.style.display=this.nextElementSibling.style.display===\"block\"?\"none\":\"block\"'>► Show API Response</div>";
            echo "<div class='hidden-content'><pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre></div>";
        }
    }
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 6: JSON ENCODING TEST
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">📦 6. JSON Encoding Test</div>';
echo '<div class="section-content">';

$test_data = [
    'test' => 'Hello',
    'number' => 123,
    'array' => [1, 2, 3],
    'unicode' => '你好 مرحبا'
];

$json_result = json_encode($test_data);
if ($json_result === false) {
    echo "<div class='status-item error'><span class='icon'>✗</span> JSON Encoding - FAILED</div>";
    echo "<div class='code-block'>Error: " . json_last_error_msg() . "</div>";
    $errors_found[] = "JSON encoding failed: " . json_last_error_msg();
} else {
    echo "<div class='status-item success'><span class='icon'>✓</span> JSON Encoding - Working</div>";
}

// Test large data
$large_text = str_repeat("Test ", 10000);
$large_json = json_encode(['data' => $large_text]);
if ($large_json === false) {
    echo "<div class='status-item error'><span class='icon'>✗</span> Large JSON Encoding - FAILED</div>";
    $errors_found[] = "Cannot encode large JSON";
} else {
    echo "<div class='status-item success'><span class='icon'>✓</span> Large JSON Encoding - Working (" . strlen($large_json) . " bytes)</div>";
}

// Test UTF-8
$utf8_test = "Special chars: 💕 ❤️ 😊";
$utf8_json = json_encode(['text' => $utf8_test]);
if ($utf8_json === false) {
    echo "<div class='status-item warning'><span class='icon'>⚠</span> UTF-8 Encoding - Issues detected</div>";
    $warnings_found[] = "UTF-8 encoding issues";
} else {
    echo "<div class='status-item success'><span class='icon'>✓</span> UTF-8 Encoding - Working</div>";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 7: RECENT ERROR LOGS
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">📝 7. Recent Error Logs</div>';
echo '<div class="section-content">';

$log_locations = [
    ini_get('error_log'),
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    'C:/xampp/apache/logs/error.log',
    __DIR__ . '/error.log',
    __DIR__ . '/logs/error.log'
];

$found_logs = false;
foreach ($log_locations as $log_file) {
    if ($log_file && file_exists($log_file) && is_readable($log_file)) {
        $found_logs = true;
        echo "<div class='status-item success'><span class='icon'>✓</span> Found log: <strong>$log_file</strong></div>";
        
        // Get last 50 lines
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -50);
        
        // Filter for Misuki-related errors
        $misuki_errors = [];
        foreach ($recent_lines as $line) {
            if (stripos($line, 'misuki') !== false || 
                stripos($line, 'chat.php') !== false || 
                stripos($line, 'claude') !== false ||
                stripos($line, 'anthropic') !== false) {
                $misuki_errors[] = $line;
            }
        }
        
        if (!empty($misuki_errors)) {
            echo "<div class='expandable' onclick='this.nextElementSibling.style.display=this.nextElementSibling.style.display===\"block\"?\"none\":\"block\"'>► Show Recent Misuki-Related Errors (" . count($misuki_errors) . " found)</div>";
            echo "<div class='hidden-content'><pre>" . htmlspecialchars(implode('', array_slice($misuki_errors, -20))) . "</pre></div>";
        }
        
        break; // Only show first found log
    }
}

if (!$found_logs) {
    echo "<div class='status-item warning'><span class='icon'>⚠</span> No error logs found in common locations</div>";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 8: DATABASE DATA CHECK
// ═══════════════════════════════════════════════════════════════════
if ($db) {
    echo '<div class="section">';
    echo '<div class="section-header">💾 8. Database Data Analysis</div>';
    echo '<div class="section-content">';
    
    // Recent conversations
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM conversations WHERE user_id = 1");
        $stmt->execute();
        $conv_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<div class='status-item info'><span class='icon'>💬</span> Total Conversations: <strong>$conv_count</strong></div>";
        
        // Last 10 messages
        $stmt = $db->prepare("
            SELECT 
                LEFT(user_message, 50) as user_msg,
                LEFT(misuki_response, 50) as misuki_resp,
                mood,
                timestamp,
                TIMESTAMPDIFF(MINUTE, timestamp, NOW()) as minutes_ago
            FROM conversations 
            WHERE user_id = 1
            ORDER BY timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent)) {
            echo "<div class='expandable' onclick='this.nextElementSibling.style.display=this.nextElementSibling.style.display===\"block\"?\"none\":\"block\"'>► Show Last 10 Conversations</div>";
            echo "<div class='hidden-content'>";
            echo "<table>";
            echo "<tr><th>Time</th><th>User</th><th>Misuki</th><th>Mood</th></tr>";
            foreach ($recent as $msg) {
                echo "<tr>";
                echo "<td>{$msg['timestamp']}<br>({$msg['minutes_ago']} min ago)</td>";
                echo "<td>" . htmlspecialchars($msg['user_msg']) . "...</td>";
                echo "<td>" . htmlspecialchars($msg['misuki_resp']) . "...</td>";
                echo "<td>{$msg['mood']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='status-item error'><span class='icon'>✗</span> Database query error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Memories
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM memories WHERE user_id = 1");
        $stmt->execute();
        $mem_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<div class='status-item info'><span class='icon'>🧠</span> Total Memories: <strong>$mem_count</strong></div>";
    } catch (Exception $e) {}
    
    // Mood
    try {
        $stmt = $db->prepare("SELECT current_mood, reason FROM misuki_mood WHERE user_id = 1 LIMIT 1");
        $stmt->execute();
        $mood = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mood) {
            echo "<div class='status-item info'><span class='icon'>😊</span> Current Mood: <strong>{$mood['current_mood']}</strong> - {$mood['reason']}</div>";
        }
    } catch (Exception $e) {}
    
    echo '</div></div>';
}

// ═══════════════════════════════════════════════════════════════════
// SECTION 9: FULL CHAT SIMULATION
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">🧪 9. Full Chat Flow Simulation</div>';
echo '<div class="section-content">';

if ($api_key && $db) {
    echo "<div class='status-item info'><span class='icon'>🔬</span> Simulating complete chat request...</div>";
    
    try {
        // Include all required files
        require_once __DIR__ . '/includes/functions.php';
        require_once __DIR__ . '/includes/core_profile_functions.php';
        require_once __DIR__ . '/includes/misuki_profile_functions.php';
        
        $test_message = "Hi Misuki! This is a test.";
        
        // Build a simple prompt
        $simple_prompt = "You are Misuki. Respond briefly and naturally.";
        
        // Test JSON encoding
        $payload = [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 100,
            'system' => $simple_prompt,
            'messages' => [
                ['role' => 'user', 'content' => $test_message]
            ]
        ];
        
        $json_payload = json_encode($payload);
        
        if ($json_payload === false || empty($json_payload)) {
            echo "<div class='status-item error'><span class='icon'>✗</span> JSON Encoding Failed: " . json_last_error_msg() . "</div>";
            $errors_found[] = "Chat simulation: JSON encoding failed";
        } else {
            echo "<div class='status-item success'><span class='icon'>✓</span> JSON Payload Created: " . strlen($json_payload) . " bytes</div>";
            
            // Make actual API call
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                echo "<div class='status-item error'><span class='icon'>✗</span> CURL Error: $curl_error</div>";
                $errors_found[] = "Chat simulation: CURL error";
            } elseif ($http_code === 200) {
                $result = json_decode($response, true);
                if (isset($result['content'][0]['text'])) {
                    $misuki_response = $result['content'][0]['text'];
                    echo "<div class='status-item success'><span class='icon'>✅</span> CHAT SIMULATION SUCCESSFUL!</div>";
                    echo "<div class='code-block'>";
                    echo "<strong>You:</strong> $test_message<br>";
                    echo "<strong>Misuki:</strong> " . htmlspecialchars($misuki_response);
                    echo "</div>";
                } else {
                    echo "<div class='status-item error'><span class='icon'>✗</span> Unexpected API response format</div>";
                    echo "<div class='expandable' onclick='this.nextElementSibling.style.display=this.nextElementSibling.style.display===\"block\"?\"none\":\"block\"'>► Show Response</div>";
                    echo "<div class='hidden-content'><pre>" . htmlspecialchars(print_r($result, true)) . "</pre></div>";
                }
            } else {
                echo "<div class='status-item error'><span class='icon'>✗</span> API returned HTTP $http_code</div>";
                $errors_found[] = "Chat simulation: API returned HTTP $http_code";
                echo "<div class='expandable' onclick='this.nextElementSibling.style.display=this.nextElementSibling.style.display===\"block\"?\"none\":\"block\"'>► Show Response</div>";
                echo "<div class='hidden-content'><pre>" . htmlspecialchars($response) . "</pre></div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='status-item error'><span class='icon'>✗</span> Simulation Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors_found[] = "Chat simulation exception: " . $e->getMessage();
    }
} else {
    echo "<div class='status-item warning'><span class='icon'>⚠</span> Cannot simulate - API key or database not available</div>";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SECTION 10: SYSTEM INFORMATION
// ═══════════════════════════════════════════════════════════════════
echo '<div class="section">';
echo '<div class="section-header">⚙️ 10. System Information</div>';
echo '<div class="section-content">';

echo "<div class='status-item info'><span class='icon'>💻</span> Server Software: <strong>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</strong></div>";
echo "<div class='status-item info'><span class='icon'>📂</span> Document Root: <strong>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</strong></div>";
echo "<div class='status-item info'><span class='icon'>🌐</span> Server Name: <strong>" . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "</strong></div>";
echo "<div class='status-item info'><span class='icon'>🔌</span> Server Port: <strong>" . ($_SERVER['SERVER_PORT'] ?? 'Unknown') . "</strong></div>";
echo "<div class='status-item info'><span class='icon'>⏰</span> Server Time: <strong>" . date('Y-m-d H:i:s T') . "</strong></div>";
echo "<div class='status-item info'><span class='icon'>🌍</span> Timezone: <strong>" . date_default_timezone_get() . "</strong></div>";

// Disk space
$free_space = @disk_free_space(__DIR__);
$total_space = @disk_total_space(__DIR__);
if ($free_space && $total_space) {
    $used_percent = round((($total_space - $free_space) / $total_space) * 100, 2);
    echo "<div class='status-item info'><span class='icon'>💾</span> Disk Space: " . 
         round($free_space/1024/1024/1024, 2) . " GB free / " . 
         round($total_space/1024/1024/1024, 2) . " GB total ($used_percent% used)</div>";
}

echo '</div></div>';

// ═══════════════════════════════════════════════════════════════════
// SUMMARY & RECOMMENDATIONS
// ═══════════════════════════════════════════════════════════════════
echo '<div class="summary">';
echo '<h2>📊 Diagnostic Summary</h2>';

if (empty($errors_found) && empty($warnings_found)) {
    echo "<div style='background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; margin: 15px 0;'>";
    echo "<h3 style='color: #fff; margin-bottom: 10px;'>✨ ALL SYSTEMS OPERATIONAL</h3>";
    echo "<p>Misuki is fully functional! If you're still experiencing issues, they may be intermittent or related to specific user actions.</p>";
    echo "</div>";
} else {
    if (!empty($errors_found)) {
        echo "<h3>❌ Critical Errors Found: " . count($errors_found) . "</h3>";
        echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
        foreach ($errors_found as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($warnings_found)) {
        echo "<h3>⚠️ Warnings: " . count($warnings_found) . "</h3>";
        echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
        foreach ($warnings_found as $warning) {
            echo "<li>$warning</li>";
        }
        echo "</ul>";
    }
    
    echo "<div style='background: rgba(255,255,255,0.2); padding: 20px; border-radius: 8px; margin: 15px 0;'>";
    echo "<h3 style='margin-bottom: 10px;'>🔧 Recommended Actions:</h3>";
    echo "<ol style='margin: 10px 0; padding-left: 20px;'>";
    
    if (in_array("API key is invalid or expired", $errors_found)) {
        echo "<li>Get a new API key from <a href='https://console.anthropic.com/' target='_blank' style='color: #fff; text-decoration: underline;'>console.anthropic.com</a></li>";
    }
    
    if (!empty($missing_extensions)) {
        echo "<li>Install missing PHP extensions: " . implode(', ', $missing_extensions) . "</li>";
    }
    
    if (!empty($missing_files)) {
        echo "<li>Restore missing files from backup or repository</li>";
    }
    
    foreach ($errors_found as $error) {
        if (stripos($error, 'database') !== false) {
            echo "<li>Check database configuration in config/database.php</li>";
            break;
        }
    }
    
    echo "<li>Check the error logs section above for specific error messages</li>";
    echo "<li>Copy this entire report and share it when asking for help</li>";
    echo "</ol>";
    echo "</div>";
}

echo '<button class="copy-btn" onclick="copyReport()">📋 Copy Full Report to Clipboard</button>';
echo '</div>';

?>

<script>
function copyReport() {
    const content = document.querySelector('.container').innerText;
    navigator.clipboard.writeText(content).then(() => {
        alert('Report copied to clipboard!');
    }).catch(err => {
        alert('Failed to copy: ' + err);
    });
}
</script>

</div>
</body>
</html>