<?php
/**
 * DEBUG VERSION OF CHAT.PHP
 * This shows ALL errors so we can see what's breaking
 */

// TURN ON ALL ERRORS
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "Starting chat.php debug...\n";

// Try to load config
echo "Loading config/database.php...\n";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✓ database.php loaded\n";
} catch (Throwable $e) {
    die("ERROR loading database.php: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
}

// Try to connect to database
echo "Connecting to database...\n";
try {
    $db = getDBConnection();
    echo "✓ Database connected\n";
} catch (Throwable $e) {
    die("ERROR connecting to database: " . $e->getMessage());
}

// Try to load other includes
$includes = [
    'functions.php',
    'core_profile_functions.php',
    'misuki_profile_functions.php',
    'misuki_schedule.php',
    'adaptive_schedule.php',
    'future_events_handler.php',
    'misuki_reality_functions.php'
];

foreach ($includes as $file) {
    echo "Loading includes/$file...\n";
    try {
        require_once __DIR__ . '/../includes/' . $file;
        echo "✓ $file loaded\n";
    } catch (Throwable $e) {
        die("ERROR loading $file: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    }
}

// Try to load api includes
$api_includes = [
    'parse_emotions.php',
    'reminder_handler.php',
    'split_message_handler.php',
    'nickname_handler.php',
    'core_memory_handler.php'
];

foreach ($api_includes as $file) {
    echo "Loading api/$file...\n";
    try {
        require_once __DIR__ . '/' . $file;
        echo "✓ $file loaded\n";
    } catch (Throwable $e) {
        die("ERROR loading $file: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    }
}

echo "\n✅ All files loaded successfully!\n\n";

// Now try to get input
echo "Reading input...\n";
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? 'test message';
$user_id = $input['user_id'] ?? 1;

echo "Message: $user_message\n";
echo "User ID: $user_id\n\n";

// Try to get user status
echo "Getting Misuki status...\n";
try {
    $status = getMisukiCurrentStatus($db, $user_id);
    echo "✓ Status retrieved: " . print_r($status, true) . "\n";
} catch (Throwable $e) {
    die("ERROR getting status: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
}

// Try to get mood
echo "Getting current mood...\n";
try {
    $mood = getMisukiCurrentMood($db, $user_id);
    echo "✓ Mood retrieved: " . print_r($mood, true) . "\n";
} catch (Throwable $e) {
    die("ERROR getting mood: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
}

// Check API key
echo "\nChecking API key...\n";
$api_key = getenv('ANTHROPIC_API_KEY');

if (!$api_key) {
    echo "API key not in environment, checking .env file...\n";
    $env_path = dirname(__DIR__) . '/.env';
    if (file_exists($env_path)) {
        $env_contents = file_get_contents($env_path);
        if (preg_match('/ANTHROPIC_API_KEY\s*=\s*([^\n\r]+)/', $env_contents, $matches)) {
            $api_key = trim($matches[1], '"\'');
            echo "✓ API key found in .env (" . strlen($api_key) . " chars)\n";
        } else {
            die("ERROR: ANTHROPIC_API_KEY not found in .env file!");
        }
    } else {
        die("ERROR: .env file not found at $env_path");
    }
}

echo "\n🎉 ALL CHECKS PASSED!\n\n";
echo "The API would work if we called Claude now.\n";
echo "The error must be happening during the actual Claude API call or response processing.\n";

// Let's try a minimal Claude API call
echo "\nTrying minimal Claude API call...\n";

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
            'content' => 'Say hi'
        ]
    ]
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $http_code\n";
if ($curl_error) {
    echo "CURL Error: $curl_error\n";
}

if ($http_code === 200) {
    $result = json_decode($response, true);
    if (isset($result['content'][0]['text'])) {
        echo "✅ Claude responded: " . $result['content'][0]['text'] . "\n";
    }
} else {
    echo "❌ API call failed!\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

?>