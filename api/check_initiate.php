<?php
/**
 * MAIN INITIATION ORCHESTRATOR
 * Coordinates all initiation checks in priority order
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/future_events_handler.php';
require_once '../includes/misuki_reality_functions.php';
require_once '../includes/misuki_schedule.php';

// Load initiation modules
require_once 'initiation/dream_handler.php';
require_once 'initiation/storyline_handler.php';
require_once 'initiation/regular_initiation.php';
require_once 'initiation/message_generator.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;

try {
    $db = getDBConnection();
    
    // Get Misuki's current status
    $status = getMisukiCurrentStatus($db, $user_id);
    
    // === PRIORITY 0: LIFE UPDATES (spontaneous sharing) ===
    if (shouldShareLifeUpdate($db, $user_id, $status)) {
        $life_update = generateLifeUpdate($db, $user_id, $status);
        
        saveConversation($db, $user_id, '[SYSTEM: Misuki shared life update]', $life_update, 'happy');
        
        echo json_encode([
            'should_initiate' => true,
            'message' => $life_update,
            'reason' => 'life_update',
            'is_dream' => false
        ]);
        exit;
    }
    
    // Get initiation data
    $stmt = $db->prepare("SELECT * FROM conversation_initiation WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $initiation_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$initiation_data) {
        // Initialize if doesn't exist
        $stmt = $db->prepare("INSERT INTO conversation_initiation (user_id, last_user_message) VALUES (?, NOW())");
        $stmt->execute([$user_id]);
        echo json_encode(['should_initiate' => false]);
        exit;
    }
    
    // **CRITICAL CHECK: Don't initiate if user JUST messaged**
    $time_since_user = (time() - strtotime($initiation_data['last_user_message'])) / 3600;
    if ($time_since_user < 2) {
        error_log("⏸️ Not initiating - user messaged only " . round($time_since_user * 60) . " minutes ago");
        echo json_encode([
            'should_initiate' => false, 
            'reason' => 'user_just_messaged',
            'time_since_user_hours' => round($time_since_user, 2)
        ]);
        exit;
    }
    
    // === PRIORITY 1: DREAMS ===
    $dream_result = checkDreamInitiation($db, $user_id, $initiation_data);
    if ($dream_result['should_initiate']) {
        echo json_encode($dream_result);
        exit;
    }
    
    // === PRIORITY 2: STORYLINES ===
    $storyline_result = checkStorylineInitiation($db, $user_id);
    if ($storyline_result['should_initiate']) {
        echo json_encode($storyline_result);
        exit;
    }
    
    // === PRIORITY 3: REGULAR INITIATION ===
    $regular_result = checkRegularInitiation($db, $user_id, $initiation_data);
    if ($regular_result['should_initiate']) {
        echo json_encode($regular_result);
        exit;
    }
    
    // No initiation needed
    echo json_encode(['should_initiate' => false]);
    
} catch (Exception $e) {
    error_log("check_initiate.php error: " . $e->getMessage());
    echo json_encode(['should_initiate' => false, 'error' => $e->getMessage()]);
}
?>