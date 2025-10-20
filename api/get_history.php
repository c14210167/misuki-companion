<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Set timezone consistently
date_default_timezone_set('Asia/Jakarta');

require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;
$limit = $input['limit'] ?? 50; // 🔧 CHANGED DEFAULT FROM 200 TO 25

try {
    $db = getDBConnection();
    
    // First, get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM conversations WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get LAST X conversations (subquery to get most recent, then order chronologically)
    $stmt = $db->prepare("
        SELECT * FROM (
            SELECT user_message, misuki_response, mood, timestamp 
            FROM conversations 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ) AS recent_messages
        ORDER BY timestamp ASC
    ");
    $stmt->execute([$user_id, $limit]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("get_history.php: Total in DB=" . $total_count . ", Returned=" . count($conversations) . ", user_id=" . $user_id . ", limit=" . $limit);
    
    if (!empty($conversations)) {
        $first_msg = $conversations[0];
        $last_msg = end($conversations);
        error_log("get_history.php: First message = " . $first_msg['timestamp']);
        error_log("get_history.php: Last message = " . $last_msg['timestamp']);
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'count' => count($conversations),
        'total_in_db' => $total_count,
        'limit_used' => $limit,
        'server_time' => date('Y-m-d H:i:s'),
        'debug' => [
            'user_id' => $user_id,
            'limit' => $limit,
            'timestamp' => date('Y-m-d H:i:s'),
            'first_timestamp' => !empty($conversations) ? $conversations[0]['timestamp'] : null,
            'last_timestamp' => !empty($conversations) ? end($conversations)['timestamp'] : null
        ]
    ]);
    
} catch (Exception $e) {
    error_log("get_history.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>