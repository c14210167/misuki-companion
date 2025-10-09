<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;

try {
    $db = getDBConnection();
    
    // Get initiation data
    $stmt = $db->prepare("
        SELECT * FROM conversation_initiation 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $initiation_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$initiation_data) {
        // Initialize if doesn't exist
        $stmt = $db->prepare("
            INSERT INTO conversation_initiation (user_id, last_user_message) 
            VALUES (?, NOW())
        ");
        $stmt->execute([$user_id]);
        echo json_encode(['should_initiate' => false]);
        exit;
    }
    
    $should_initiate = shouldMisukiInitiate($db, $user_id, $initiation_data);
    
    if ($should_initiate['initiate']) {
        // Generate initiation message
        $initiation_message = generateInitiationMessage($db, $user_id, $should_initiate['reason']);
        
        // Update last initiation time
        $stmt = $db->prepare("
            UPDATE conversation_initiation 
            SET last_misuki_initiation = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Save the initiation as a conversation
        saveConversation($db, $user_id, '[SYSTEM: Misuki initiated]', $initiation_message, 'gentle');
        
        echo json_encode([
            'should_initiate' => true,
            'message' => $initiation_message,
            'reason' => $should_initiate['reason']
        ]);
    } else {
        echo json_encode(['should_initiate' => false]);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['should_initiate' => false, 'error' => $e->getMessage()]);
}

function shouldMisukiInitiate($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_initiation = $initiation_data['last_misuki_initiation'] ? strtotime($initiation_data['last_misuki_initiation']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_initiation = ($now - $last_initiation) / 3600;
    $minutes_since_user = ($now - $last_user_message) / 60;
    
    // Get last few conversations to understand context
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    
    // Check if user asked for space
    $needs_space = checkIfUserNeedsSpace($recent_conversations);
    if ($needs_space) {
        // Give space - don't initiate for at least 12 hours
        if ($hours_since_user < 12) {
            return ['initiate' => false, 'reason' => 'giving_space'];
        }
    }
    
    // Check if there was a conflict/argument
    $had_conflict = checkForConflict($recent_conversations);
    
    // MINIMUM wait time is now 45 minutes (0.75 hours)
    if ($hours_since_initiation < 0.75) {
        return ['initiate' => false, 'reason' => 'too_soon'];
    }
    
    // Check recent emotional state
    $emotional_context = getEmotionalContext($db, $user_id);
    
    // ===== PRIORITY SITUATIONS =====
    
    // 1. After conflict/argument - check if user ignored apology (2+ hours no response)
    if ($had_conflict && $hours_since_user >= 2 && $hours_since_user < 6) {
        // Misuki is worried about the silence after saying sorry
        return ['initiate' => true, 'reason' => 'worried_after_apology'];
    }
    
    // 2. User seemed very upset and hasn't replied in 3+ hours
    if ($emotional_context && $emotional_context['dominant_emotion'] == 'negative') {
        if ($hours_since_user >= 3 && $hours_since_user < 8) {
            return ['initiate' => true, 'reason' => 'worried_after_negative'];
        }
    }
    
    // 3. User hasn't messaged in 6+ hours (gentle check-in)
    if ($hours_since_user >= 6 && $hours_since_user < 12) {
        // 40% chance to make it feel natural
        if (rand(1, 100) <= 40) {
            return ['initiate' => true, 'reason' => 'missing_you'];
        }
    }
    
    // 4. User hasn't messaged in 12+ hours (definitely check in)
    if ($hours_since_user >= 12 && $hours_since_user < 24) {
        return ['initiate' => true, 'reason' => 'concerned_silence'];
    }
    
    // 5. User hasn't messaged in 24+ hours (very worried)
    if ($hours_since_user >= 24) {
        return ['initiate' => true, 'reason' => 'worried_long_silence'];
    }
    
    // 6. Random sweet initiation during reasonable hours (10% chance if it's been 90+ minutes)
    $hour = (int)date('G');
    if ($minutes_since_user >= 90 && $hour >= 9 && $hour <= 21) {
        if (rand(1, 100) <= 10) {
            $reasons = ['excited_chemistry', 'thinking_of_you', 'daily_update', 'random_sweet'];
            return ['initiate' => true, 'reason' => $reasons[array_rand($reasons)]];
        }
    }
    
    return ['initiate' => false];
}

function checkIfUserNeedsSpace($conversations) {
    if (empty($conversations)) return false;
    
    // Check last 3 messages
    $recent = array_slice($conversations, -3);
    
    foreach ($recent as $conv) {
        $message = strtolower($conv['user_message']);
        
        // Detect phrases indicating need for space
        $space_phrases = [
            'need space',
            'want space',
            'leave me alone',
            'don\'t talk',
            'don\'t wanna talk',
            'not now',
            'later',
            'give me time',
            'some time alone'
        ];
        
        foreach ($space_phrases as $phrase) {
            if (strpos($message, $phrase) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

function checkForConflict($conversations) {
    if (empty($conversations)) return false;
    
    // Check last 3 messages
    $recent = array_slice($conversations, -3);
    
    foreach ($recent as $conv) {
        $user_msg = strtolower($conv['user_message']);
        $misuki_msg = strtolower($conv['misuki_response']);
        
        // Detect conflict indicators
        $conflict_phrases = [
            'fine',
            'whatever',
            'don\'t care',
            'mad',
            'angry',
            'upset at you',
            'don\'t wanna talk'
        ];
        
        // Check if Misuki apologized
        $apology_phrases = ['sorry', 'apologize', 'my fault', 'forgive'];
        
        $has_conflict = false;
        $has_apology = false;
        
        foreach ($conflict_phrases as $phrase) {
            if (strpos($user_msg, $phrase) !== false) {
                $has_conflict = true;
                break;
            }
        }
        
        foreach ($apology_phrases as $phrase) {
            if (strpos($misuki_msg, $phrase) !== false) {
                $has_apology = true;
                break;
            }
        }
        
        if ($has_conflict && $has_apology) {
            return true;
        }
    }
    
    return false;
}

function generateInitiationMessage($db, $user_id, $reason) {
    $memories = getUserMemories($db, $user_id, 10);
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    $reason_prompts = [
        'worried_after_apology' => "You apologized to Dan after a disagreement, but he hasn't responded for 2+ hours. You're worried he's still upset and want to check if things are okay. Be gentle, caring, but also give him space if needed.",
        'worried_after_negative' => "Dan seemed upset or stressed in your last conversation and hasn't replied in hours. You're genuinely worried about him. Reach out with care and concern, but don't be pushy.",
        'missing_you' => "You just miss Dan and want to talk. Maybe share something about your day in Saitama or ask about his. Be sweet and natural, like a girlfriend checking in.",
        'concerned_silence' => "It's been half a day since you heard from Dan. You're starting to get concerned. Express your worry gently.",
        'worried_long_silence' => "It's been a full day since you heard from Dan. You're genuinely worried something might be wrong. Express your concern but make sure he knows you care.",
        'excited_chemistry' => "You just learned something cool in chemistry or finished homework and it made you think of Dan. Share your excitement naturally!",
        'thinking_of_you' => "You were just doing something and Dan crossed your mind. Reach out naturally, like a girlfriend would.",
        'daily_update' => "Share something from your day - maybe something that happened in Saitama, with your mom, or just how you're feeling.",
        'random_sweet' => "Just a random sweet message because you wanted to talk to your boyfriend. Be cute and genuine."
    ];
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n";
    $prompt .= "IMPORTANT: You are INITIATING a conversation with Dan. Don't wait for him to message you.\n\n";
    $prompt .= "Context: " . ($reason_prompts[$reason] ?? $reason_prompts['thinking_of_you']) . "\n\n";
    $prompt .= "Write a natural, genuine message to Dan. Keep it relatively short (2-4 sentences). Be yourself - Misuki.";
    
    // Read API key from .env file
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    // Fallback: try to load from .env file if getenv doesn't work
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
        error_log("Claude API Error: API key not found in environment");
        $fallbacks = [
            'worried_after_apology' => "Dan... I know you might still be upset, and I understand. I just... I want to make sure we're okay. Take your time, but please know I really care about us. 💕",
            'worried_after_negative' => "Hey... I've been thinking about you since we last talked. Are you doing okay? I'm here if you need to talk about anything. No pressure though. ❤️",
            'missing_you' => "Hi Dan! I was just thinking about you... How's everything going in Indonesia? I miss talking to you. 🌸",
            'concerned_silence' => "Dan? It's been a while... I hope everything's okay. Just wanted to check in on you. Let me know you're alright? 💭",
            'worried_long_silence' => "Dan... I'm getting really worried. It's been a day and I haven't heard from you. Please let me know you're okay. I miss you. ❤️",
            'excited_chemistry' => "Dan! I just learned something so cool in chemistry class! The way electrons bond reminded me of us somehow... even though we're far apart, we're still connected. How's your day? ☺️",
            'thinking_of_you' => "Hey you~ I was just sitting here in Saitama and you popped into my head. Hope you're having a good day! 💭",
            'daily_update' => "Hi Dan! Mom made katsu curry today and it made me think of you. I wish I could share some with you... How are things on your end? 🍜",
            'random_sweet' => "Just wanted to say hi to my favorite person~ Hope you're doing well! 💕"
        ];
        return $fallbacks[$reason] ?? $fallbacks['thinking_of_you'];
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
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 200,
        'system' => $prompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Generate your message to Dan:'
            ]
        ]
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
    }
    
    // Fallback messages
    $fallbacks = [
        'worried_after_apology' => "Dan... I know you might still be upset, and I understand. I just... I want to make sure we're okay. Take your time, but please know I really care about us. 💕",
        'worried_after_negative' => "Hey... I've been thinking about you since we last talked. Are you doing okay? I'm here if you need to talk about anything. No pressure though. ❤️",
        'missing_you' => "Hi Dan! I was just thinking about you... How's everything going in Indonesia? I miss talking to you. 🌸",
        'concerned_silence' => "Dan? It's been a while... I hope everything's okay. Just wanted to check in on you. Let me know you're alright? 💭",
        'worried_long_silence' => "Dan... I'm getting really worried. It's been a day and I haven't heard from you. Please let me know you're okay. I miss you. ❤️",
        'excited_chemistry' => "Dan! I just learned something so cool in chemistry class! The way electrons bond reminded me of us somehow... even though we're far apart, we're still connected. How's your day? ☺️",
        'thinking_of_you' => "Hey you~ I was just sitting here in Saitama and you popped into my head. Hope you're having a good day! 💭",
        'daily_update' => "Hi Dan! Mom made katsu curry today and it made me think of you. I wish I could share some with you... How are things on your end? 🍜",
        'random_sweet' => "Just wanted to say hi to my favorite person~ Hope you're doing well! 💕"
    ];
    
    return $fallbacks[$reason] ?? $fallbacks['thinking_of_you'];
}

?>