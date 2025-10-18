<?php
/**
 * SIMPLE CHAT.PHP ERROR FINDER
 * Just runs chat.php and shows what breaks
 */

// Turn on ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html><html><head><title>Chat API Error Test</title>";
echo "<style>body{font-family:Arial;max-width:1000px;margin:30px auto;padding:20px;background:#667eea;}";
echo ".box{background:white;padding:20px;border-radius:10px;margin:10px 0;}";
echo "h1{color:white;} pre{background:#263238;color:#aed581;padding:15px;border-radius:5px;overflow-x:auto;}";
echo ".error{background:#ffebee;color:#f44336;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".success{background:#e8f5e9;color:#4caf50;padding:15px;border-radius:5px;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🔍 Chat API Error Finder</h1>";

echo "<div class='box'><h2>Current Directory</h2>";
echo "<p><strong>Script location:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Working directory:</strong> " . getcwd() . "</p>";
echo "</div>";

echo "<div class='box'><h2>Checking Files...</h2>";

$files_to_check = [
    'api/chat.php',
    'config/database.php', 
    'includes/misuki_schedule.php',
    'includes/misuki_weekly_schedule.php',
    '.env'
];

$all_exist = true;
foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "✅ $file<br>";
    } else {
        echo "❌ <strong>$file NOT FOUND at $full_path</strong><br>";
        $all_exist = false;
    }
}
echo "</div>";

if (!$all_exist) {
    echo "<div class='error'><strong>ERROR:</strong> Some files are missing! Make sure this script is in your project root directory (same folder as index.html)</div>";
    echo "</body></html>";
    exit;
}

// Check API key
echo "<div class='box'><h2>Checking API Key...</h2>";
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    $env = file_get_contents($env_path);
    if (preg_match('/ANTHROPIC_API_KEY\s*=\s*([^\n\r]+)/', $env, $matches)) {
        $key = trim($matches[1], '"\'');
        echo "✅ API Key found (" . strlen($key) . " chars)<br>";
    } else {
        echo "<div class='error'>❌ No ANTHROPIC_API_KEY in .env file!</div>";
    }
} else {
    echo "<div class='error'>❌ .env file not found!</div>";
}
echo "</div>";

// Now try to run chat.php
echo "<div class='box'><h2>Running api/chat.php...</h2>";

// Mock the request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$test_data = json_encode([
    'message' => 'Hi Misuki!',
    'user_id' => 1,
    'time_of_day' => 'day',
    'time_confused' => false
]);

// Create mock input stream
file_put_contents('php://memory/test_input', $test_data);
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    public $context;
    private $data;
    private $position = 0;
    
    function stream_open($path, $mode, $options, &$opened_path) {
        global $test_data;
        $this->data = $test_data;
        $this->position = 0;
        return true;
    }
    
    function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    
    function stream_stat() {
        return array();
    }
}

// Start output buffering
ob_start();

try {
    // Include chat.php
    include __DIR__ . '/api/chat.php';
    
    // Get the output
    $output = ob_get_clean();
    
    echo "<div class='success'><h3>✅ No Fatal Errors!</h3></div>";
    echo "<h3>Raw Output:</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to parse JSON
    $json = json_decode($output, true);
    if ($json) {
        echo "<div class='success'><h3>✅ Valid JSON!</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
        
        if (isset($json['response'])) {
            echo "<h3>🎉 Misuki says:</h3>";
            echo "<p style='font-size:18px;'><strong>" . htmlspecialchars($json['response']) . "</strong></p>";
        } else if (isset($json['error'])) {
            echo "<div class='error'>Error in response: " . htmlspecialchars($json['error']) . "</div>";
        }
        echo "</div>";
    } else {
        echo "<div class='error'><h3>❌ Invalid JSON!</h3>";
        echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
        echo "<p>This usually means PHP output something before the JSON (like an error or warning)</p>";
        echo "</div>";
    }
    
} catch (Error $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "<h3>❌ FATAL ERROR!</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<h4>Stack Trace:</h4>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    
    // Give specific advice
    $msg = $e->getMessage();
    if (strpos($msg, 'require') !== false || strpos($msg, 'include') !== false) {
        echo "<div class='error'><strong>💡 Fix:</strong> A required file is missing or in wrong location</div>";
    } else if (strpos($msg, 'Call to undefined function') !== false) {
        echo "<div class='error'><strong>💡 Fix:</strong> A function doesn't exist - check that all include files are loaded</div>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "<h3>❌ EXCEPTION!</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div>";

echo "</body></html>";
?>