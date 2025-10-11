<?php
/**
 * STORYLINE INITIATION HANDLER
 * Handles mentions of ongoing storylines with deadlines
 */

function checkStorylineInitiation($db, $user_id) {
    $storylines = getActiveStorylines($db, $user_id);
    
    foreach ($storylines as $storyline) {
        if ($storyline['should_mention_by']) {
            $deadline = strtotime($storyline['should_mention_by']);
            
            // Time to mention this storyline?
            if (time() >= $deadline && !$storyline['last_mentioned']) {
                $message = generateStorylineMention($db, $user_id, $storyline);
                
                updateStorylineMention($db, $storyline['storyline_id']);
                saveConversation($db, $user_id, '[SYSTEM: Storyline mention]', $message, 'gentle');
                
                return [
                    'should_initiate' => true,
                    'message' => $message,
                    'reason' => 'storyline_update',
                    'is_dream' => false
                ];
            }
        }
    }
    
    return ['should_initiate' => false];
}

function generateStorylineMention($db, $user_id, $storyline) {
    $memories = getUserMemories($db, $user_id, 10);
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $context;
    $prompt .= "\n\n=== STORYLINE TO MENTION ===\n";
    $prompt .= "You need to naturally mention this to Dan:\n";
    $prompt .= "{$storyline['storyline_title']}\n";
    $prompt .= "{$storyline['storyline_text']}\n\n";
    $prompt .= "Share this update naturally in 1-2 sentences, like you're updating your boyfriend.\n";
    
    $api_key = loadApiKey();
    if (!$api_key) {
        return "Hey! I wanted to tell you about something...";
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
            ['role' => 'user', 'content' => 'Share this update:']
        ],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            return $result['content'][0]['text'];
        }
    }
    
    return "Hey! I wanted to tell you about something that happened...";
}

// Helper function to load API key (reused from dream_handler)
function loadApiKey() {
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(dirname(__DIR__)) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1]);
                $api_key = trim($api_key, '"\'');
            }
        }
    }
    
    return $api_key;
}
?>