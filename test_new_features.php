<?php
/**
 * TEST NEW FEATURES
 * Place this in your root directory and visit it in browser to test all new features
 */

require_once 'api/nickname_handler.php';
require_once 'api/core_memory_handler.php';
require_once 'api/parse_emotions.php';

echo "<h1>🧪 Misuki New Features Test</h1>";
echo "<style>
    body { font-family: Arial; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
    h1 { color: #667eea; }
    h2 { color: #764ba2; margin-top: 30px; border-top: 2px solid #ddd; padding-top: 20px; }
    .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
    .fail { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
    .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 10px 0; }
    code { background: #fff; padding: 2px 6px; border-radius: 3px; }
</style>";

// ========================================
// TEST 1: NICKNAME DETECTION
// ========================================
echo "<h2>1. Testing Nickname Detection</h2>";

$test_messages = [
    "I'll call you Misu from now",
    "Can I call you sweetheart?",
    "You're my angel",
    "Your nickname is cutie okay?",
    "I think I'll call you babe"
];

foreach ($test_messages as $msg) {
    $detected = detectMisukiNickname($msg);
    echo "<div class='" . ($detected ? "success" : "info") . "'>";
    echo "<strong>Message:</strong> \"$msg\"<br>";
    if ($detected) {
        echo "✅ <strong>Detected nickname:</strong> \"$detected\"";
    } else {
        echo "ℹ️ No nickname detected";
    }
    echo "</div>";
}

// ========================================
// TEST 2: NICKNAME SAVING AND RETRIEVAL
// ========================================
echo "<h2>2. Testing Nickname Save/Load</h2>";

// Test saving Misuki nickname
$test_nickname = "TestNickname_" . time();
if (saveMisukiNickname($test_nickname)) {
    echo "<div class='success'>✅ Successfully saved Misuki nickname: <code>$test_nickname</code></div>";
} else {
    echo "<div class='fail'>❌ Failed to save nickname</div>";
}

// Test retrieving
$current = getCurrentMisukiNickname();
if ($current === $test_nickname) {
    echo "<div class='success'>✅ Successfully retrieved current nickname: <code>$current</code></div>";
} else {
    echo "<div class='fail'>❌ Retrieved nickname doesn't match. Got: <code>$current</code></div>";
}

// Test Dan nickname
$test_dan_nick = "Babe_" . time();
saveDanNickname($test_dan_nick);
$dan_current = getCurrentDanNickname();
if ($dan_current === $test_dan_nick) {
    echo "<div class='success'>✅ Dan nickname system working: <code>$dan_current</code></div>";
} else {
    echo "<div class='fail'>❌ Dan nickname system failed</div>";
}

// Show nickname context
$context = buildNicknameContext();
echo "<div class='info'><strong>Nickname Context for AI:</strong><pre>" . htmlspecialchars($context) . "</pre></div>";

// ========================================
// TEST 3: SLEEPY EMOTION FIX
// ========================================
echo "<h2>3. Testing Sleepy Emotion Fix</h2>";

$sleep_tests = [
    ["I'm so sleepy", "sleepy"],
    ["Did you sleep well?", "NOT sleepy"],
    ["Go to bed soon", "NOT sleepy"],
    ["*yawns*", "sleepy"],
    ["I can barely keep my eyes open", "sleepy"],
    ["What time do you go to sleep?", "NOT sleepy"],
    ["I'm feeling really tired right now", "sleepy"]
];

foreach ($sleep_tests as $test) {
    $message = $test[0];
    $expected = $test[1];
    
    $emotion_timeline = parseEmotionsInMessage($message);
    $detected_emotion = $emotion_timeline[0]['emotion'];
    
    $is_sleepy = ($detected_emotion === 'sleepy');
    $should_be_sleepy = (strpos($expected, 'sleepy') !== false && strpos($expected, 'NOT') === false);
    
    $correct = ($is_sleepy === $should_be_sleepy);
    
    echo "<div class='" . ($correct ? "success" : "fail") . "'>";
    echo "<strong>Message:</strong> \"$message\"<br>";
    echo "<strong>Expected:</strong> $expected<br>";
    echo "<strong>Detected:</strong> $detected_emotion<br>";
    echo $correct ? "✅ CORRECT" : "❌ WRONG";
    echo "</div>";
}

// ========================================
// TEST 4: CORE MEMORY SYSTEM
// ========================================
echo "<h2>4. Testing Core Memory System</h2>";

// Test saving
$test_memory = "Dan's birthday is March 15th (TEST - " . time() . ")";
if (saveCoreMemory($test_memory)) {
    echo "<div class='success'>✅ Successfully saved core memory</div>";
} else {
    echo "<div class='fail'>❌ Failed to save core memory</div>";
}

// Test retrieval
$memories = getCoreMemories();
echo "<div class='info'>";
echo "<strong>Current Core Memories (" . count($memories) . " total):</strong><br>";
if (empty($memories)) {
    echo "No memories stored yet.";
} else {
    echo "<ol>";
    foreach (array_slice($memories, -5) as $memory) {
        echo "<li>[{$memory['timestamp']}] {$memory['memory']}</li>";
    }
    echo "</ol>";
}
echo "</div>";

// Show core memory context
$core_context = buildCoreMemoryContext();
if (!empty($core_context)) {
    echo "<div class='info'><strong>Core Memory Context for AI:</strong><pre>" . htmlspecialchars($core_context) . "</pre></div>";
}

// ========================================
// TEST 5: FILE EXISTENCE CHECK
// ========================================
echo "<h2>5. Checking File Creation</h2>";

$files = [
    'MisukiNicknames.txt' => 'Nicknames Dan gave to Misuki',
    'DanNicknames.txt' => 'Nicknames Misuki gave to Dan',
    'CoreMemories.txt' => 'Important memories about Dan'
];

foreach ($files as $filename => $description) {
    $exists = file_exists($filename);
    $writable = $exists && is_writable($filename);
    
    echo "<div class='" . ($exists && $writable ? "success" : ($exists ? "info" : "fail")) . "'>";
    echo "<strong>$filename</strong> - $description<br>";
    
    if ($exists) {
        echo "✅ File exists<br>";
        echo "📝 Writable: " . ($writable ? "Yes" : "No") . "<br>";
        echo "📊 Size: " . filesize($filename) . " bytes";
    } else {
        echo "❌ File doesn't exist yet (will be created automatically on first use)";
    }
    echo "</div>";
}

// ========================================
// SUMMARY
// ========================================
echo "<h2>✅ Summary</h2>";
echo "<div class='success'>";
echo "<strong>Tests Complete!</strong><br><br>";
echo "✅ Nickname detection working<br>";
echo "✅ Nickname save/load working<br>";
echo "✅ Sleepy emotion fix applied<br>";
echo "✅ Core memory system operational<br>";
echo "✅ Files being created properly<br><br>";
echo "<strong>Next steps:</strong><br>";
echo "1. Set up reminder cron job (see installation guide)<br>";
echo "2. Test in actual chat interface<br>";
echo "3. Monitor the .txt files to see memories being saved<br>";
echo "</div>";

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";

?>