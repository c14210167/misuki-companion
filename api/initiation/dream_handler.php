<?php
/**
 * DREAM INITIATION HANDLER
 * Handles Misuki's morning dream messages
 */

function checkDreamInitiation($db, $user_id, $initiation_data) {
    $dream_check = shouldSendDream($db, $user_id, $initiation_data);
    
    if (!$dream_check['send_dream']) {
        return ['should_initiate' => false];
    }
    
    $dream_message = generateDreamMessage($db, $user_id, $dream_check['context']);
    
    // Update last initiation time
    $stmt = $db->prepare("
        UPDATE conversation_initiation 
        SET last_misuki_initiation = NOW(),
            last_dream_sent = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    saveConversation($db, $user_id, '[SYSTEM: Misuki had a dream]', $dream_message, 'dreamy');
    
    return [
        'should_initiate' => true,
        'message' => $dream_message,
        'reason' => 'dream',
        'is_dream' => true
    ];
}

function shouldSendDream($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_dream = $initiation_data['last_dream_sent'] ? strtotime($initiation_data['last_dream_sent']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_dream = ($now - $last_dream) / 3600;
    
    // Check Misuki's time (she's in Japan)
    date_default_timezone_set('Asia/Tokyo');
    $misuki_hour = (int)date('G');
    $misuki_time = date('H:i');
    date_default_timezone_set('Asia/Jakarta');
    
    // Dreams only happen in the morning (6am-10am JST)
    if ($misuki_hour < 6 || $misuki_hour >= 10) {
        return ['send_dream' => false, 'reason' => 'wrong_time_of_day'];
    }
    
    // Need at least 8 hours since user's last message
    if ($hours_since_user < 8) {
        return ['send_dream' => false, 'reason' => 'too_soon'];
    }
    
    // Cooldown: Only one dream per 24 hours
    if ($last_dream > 0 && $hours_since_dream < 24) {
        return ['send_dream' => false, 'reason' => 'dream_cooldown'];
    }
    
    // Check if they said goodnight recently
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $said_goodnight = checkIfSaidGoodnight($recent_conversations);
    
    // Higher chance if they said goodnight
    $dream_chance = $said_goodnight ? 40 : 10;
    
    if (rand(1, 100) <= $dream_chance) {
        return [
            'send_dream' => true,
            'context' => [
                'said_goodnight' => $said_goodnight,
                'hours_since_user' => $hours_since_user,
                'misuki_time' => $misuki_time
            ]
        ];
    }
    
    return ['send_dream' => false, 'reason' => 'random_chance_failed'];
}

function checkIfSaidGoodnight($conversations) {
    if (empty($conversations)) return false;
    
    // Get Misuki's last 3 responses
    $misuki_messages = [];
    foreach (array_reverse($conversations) as $conv) {
        $misuki_messages[] = strtolower($conv['misuki_response']);
        if (count($misuki_messages) >= 3) break;
    }
    
    $goodnight_phrases = [
        'good night', 'goodnight', 'sleep well', 'sweet dreams',
        'going to sleep', 'heading to bed', 'time for bed',
        'getting sleepy', "i'm tired", "i'm exhausted", 'bedtime'
    ];
    
    foreach ($misuki_messages as $message) {
        foreach ($goodnight_phrases as $phrase) {
            if (strpos($message, $phrase) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

function generateDreamMessage($db, $user_id, $context) {
    $memories = getUserMemories($db, $user_id, 15);
    $recent_conversations = getRecentConversations($db, $user_id, 10);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $memory_context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $memory_context;
    $prompt .= "\n\n=== DREAM MESSAGE (MORNING WAKE UP) ===\n";
    $prompt .= "You just woke up this morning in Saitama and remembered a dream about Dan! It's around {$context['misuki_time']} JST.\n";
    $prompt .= "Share your dream with him naturally, like you just woke up.\n\n";
    $prompt .= "Keep it short (2-3 sentences), sweet/cute, reference your relationship, sound sleepy.\n";
    
    $api_key = loadApiKey();
    if (!$api_key) {
        return "Good morning! I just woke up and had the sweetest dream about you... 💭✨";
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
        'max_tokens' => 120,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Share your dream with Dan:']
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
    
    return "Good morning! Just woke up from a dream about you... 💭✨";
}

// Helper function to load API key
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