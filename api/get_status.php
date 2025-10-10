<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/misuki_schedule.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;

try {
    $db = getDBConnection();
    
    // Get Misuki's current status
    $status = getMisukiCurrentStatus($db, $user_id);
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>