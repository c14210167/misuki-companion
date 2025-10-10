<?php
// Debug tool - visit this to see what's in the database
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

echo "<h1>💬 Message Database Debug</h1>";
echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    $db = getDBConnection();
    
    echo "<h2>Last 30 Messages in Database (Most Recent First):</h2>";
    
    $stmt = $db->prepare("
        SELECT 
            conversation_id,
            user_id,
            LEFT(user_message, 80) as user_msg_preview,
            LEFT(misuki_response, 80) as misuki_resp_preview,
            mood,
            timestamp,
            TIMESTAMPDIFF(MINUTE, timestamp, NOW()) as minutes_ago
        FROM conversations 
        ORDER BY timestamp DESC 
        LIMIT 30
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "<p style='color: red;'>❌ No messages found in database!</p>";
    } else {
        echo "<p><strong>Total messages found:</strong> " . count($messages) . "</p>";
        echo "<p><strong>Most recent:</strong> " . $messages[0]['timestamp'] . " (" . $messages[0]['minutes_ago'] . " minutes ago)</p>";
        
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #667eea; color: white;'>
                <th>ID</th>
                <th>Timestamp</th>
                <th>Minutes Ago</th>
                <th>User Message</th>
                <th>Misuki Response</th>
              </tr>";
        
        foreach ($messages as $msg) {
            $row_color = $msg['minutes_ago'] < 10 ? 'background: #efe;' : '';
            echo "<tr style='$row_color'>";
            echo "<td>{$msg['conversation_id']}</td>";
            echo "<td>{$msg['timestamp']}</td>";
            echo "<td><strong>{$msg['minutes_ago']} min</strong></td>";
            echo "<td>" . htmlspecialchars($msg['user_msg_preview']) . "...</td>";
            echo "<td>" . htmlspecialchars($msg['misuki_resp_preview']) . "...</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Check what get_history.php would return
    echo "<h2>What get_history.php Returns:</h2>";
    $stmt = $db->prepare("
        SELECT user_message, misuki_response, mood, timestamp 
        FROM conversations 
        WHERE user_id = 1 
        ORDER BY timestamp ASC 
        LIMIT 100
    ");
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Total returned by query:</strong> " . count($history) . "</p>";
    if (!empty($history)) {
        $first = $history[0];
        $last = end($history);
        echo "<p><strong>First message:</strong> {$first['timestamp']}</p>";
        echo "<p><strong>Last message:</strong> {$last['timestamp']}</p>";
        echo "<p><strong>Last message preview:</strong> " . htmlspecialchars(substr($last['user_message'], 0, 100)) . "</p>";
    }
    
    // Check database server time
    echo "<h2>Time Check:</h2>";
    $stmt = $db->query("SELECT NOW() as db_time");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Database server time:</strong> {$result['db_time']}</p>";
    echo "<p><strong>PHP server time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1400px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; }
table {
    background: white;
    margin: 20px 0;
    font-size: 0.85rem;
}
td {
    max-width: 300px;
    overflow: hidden;
}
</style>