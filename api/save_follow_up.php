<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;
$misuki_message = $input['misuki_message'] ?? '';
$mood = $input['mood'] ?? 'gentle';

if (empty($misuki_message)) {
    echo json_encode(['success' => false, 'error' => 'Message empty']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Save as a continuation message (empty user message)
    $stmt = $db->prepare("
        INSERT INTO conversations (user_id, user_message, misuki_response, mood, timestamp) 
        VALUES (?, '[FOLLOW-UP]', ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $misuki_message, $mood]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>