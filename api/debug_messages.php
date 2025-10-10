<?php
// Debug tool - visit this to see what's in the database
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

echo "<h1>💬 Message Database Debug</h1>";

try {
    $db = getDBConnection();
    
    echo "<h2>Last 20 Messages in Database:</h2>";
    
    $stmt = $db->prepare("
        SELECT 
            conversation_id,
            user_id,
            user_message,
            misuki_response,
            mood,
            timestamp
        FROM conversations 
        ORDER BY timestamp DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "<p style='color: red;'>❌ No messages found in database!</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #667eea; color: white;'>
                <th>ID</th>
                <th>Timestamp</th>
                <th>User Message</th>
                <th>Misuki Response</th>
                <th>Mood</th>
              </tr>";
        
        foreach ($messages as $msg) {
            $time = date('M j, Y g:i A', strtotime($msg['timestamp']));
            echo "<tr>";
            echo "<td>{$msg['conversation_id']}</td>";
            echo "<td>{$time}</td>";
            echo "<td>" . htmlspecialchars(substr($msg['user_message'], 0, 50)) . "...</td>";
            echo "<td>" . htmlspecialchars(substr($msg['misuki_response'], 0, 50)) . "...</td>";
            echo "<td>{$msg['mood']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<p><strong>Total messages:</strong> " . count($messages) . "</p>";
        echo "<p><strong>Most recent:</strong> " . date('M j, Y g:i A', strtotime($messages[0]['timestamp'])) . "</p>";
    }
    
    // Check current time vs database time
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
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; }
table {
    background: white;
    margin: 20px 0;
}
td {
    font-size: 0.9rem;
}
</style>