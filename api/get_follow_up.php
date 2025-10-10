<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'parse_emotions.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;
$follow_up_count = $input['follow_up_count'] ?? 1;

try {
    $db = getDBConnection();
    
    // Get context
    $memories = getUserMemories($db, $user_id);
    $recent_conversations = getRecentConversations($db, $user_id, 5); // Last 5 messages for context
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    // Get last 2-3 messages to understand what was just said
    $last_messages = array_slice($recent_conversations, -3);
    $conversation_summary = "\n=== JUST NOW (LAST FEW MESSAGES) ===\n";
    foreach ($last_messages as $msg) {
        $conversation_summary .= "Dan: {$msg['user_message']}\n";
        $conversation_summary .= "You: {$msg['misuki_response']}\n";
    }
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n" . $conversation_summary;
    $prompt .= "\n\n=== FOLLOW-UP MESSAGE ===\n";
    $prompt .= "You just sent a message to Dan, but you have MORE to say. This is message #" . ($follow_up_count + 1) . " in your follow-up.\n";
    $prompt .= "Generate a natural follow-up message - like when you're texting and send multiple messages in a row.\n";
    $prompt .= "Examples of natural follow-ups:\n";
    $prompt .= "- Adding another thought\n";
    $prompt .= "- Sharing a related detail\n";
    $prompt .= "- Asking a follow-up question\n";
    $prompt .= "- Expressing more emotion about what was just discussed\n\n";
    $prompt .= "Keep it SHORT (1-2 sentences). Be natural like real texting. NO asterisk actions.\n";
    
    // Generate follow-up
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(__DIR__) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1]);
            }
        }
    }
    
    if (!$api_key) {
        echo json_encode(['success' => false, 'error' => 'API key not found']);
        exit;
    }
    
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
        'max_tokens' => 100,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Generate your follow-up message:']
        ],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $follow_up_text = $result['content'][0]['text'];
            
            // Parse emotions
            $emotion_timeline = parseEmotionsInMessage($follow_up_text);
            
            // Save as a conversation
            $mood = determineMood(['emotion' => 'neutral'], ['text' => $follow_up_text], false);
            saveConversation($db, $user_id, '[FOLLOW-UP]', $follow_up_text, $mood['mood']);
            
            // Decide if there should be ANOTHER follow-up (max 3 total)
            $should_continue = false;
            if ($follow_up_count < 2) { // Max 3 messages total (0, 1, 2)
                $should_continue = (rand(1, 100) <= 30); // 30% chance of another
            }
            
            echo json_encode([
                'success' => true,
                'message' => $follow_up_text,
                'emotion_timeline' => $emotion_timeline,
                'mood' => $mood['mood'],
                'mood_text' => $mood['text'],
                'should_continue' => $should_continue,
                'follow_up_count' => $follow_up_count + 1
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Failed to generate follow-up']);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>