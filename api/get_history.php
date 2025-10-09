<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;
$limit = $input['limit'] ?? 50; // Get last 50 messages

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        SELECT user_message, misuki_response, mood, timestamp 
        FROM conversations 
        WHERE user_id = ? 
        ORDER BY timestamp ASC 
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>