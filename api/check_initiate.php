<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/future_events_handler.php';
require_once '../includes/misuki_reality_functions.php';
require_once '../includes/misuki_schedule.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? 1;

try {
    $db = getDBConnection();
    
    // Get Misuki's current status
    $status = getMisukiCurrentStatus($db, $user_id);
    
    // === PRIORITY 1: LIFE UPDATES (spontaneous sharing) ===
    if (shouldShareLifeUpdate($db, $user_id, $status)) {
        $life_update = generateLifeUpdate($db, $user_id, $status);
        
        // Save as conversation
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
    
    // === PRIORITY 2: CHECK STORYLINES (should mention by deadline) ===
    $storylines = getActiveStorylines($db, $user_id);
    foreach ($storylines as $storyline) {
        if ($storyline['should_mention_by']) {
            $deadline = strtotime($storyline['should_mention_by']);
            if (time() >= $deadline && !$storyline['last_mentioned']) {
                // Time to mention this storyline!
                $message = generateStorylineMention($db, $user_id, $storyline);
                
                updateStorylineMention($db, $storyline['storyline_id']);
                saveConversation($db, $user_id, '[SYSTEM: Storyline mention]', $message, 'gentle');
                
                echo json_encode([
                    'should_initiate' => true,
                    'message' => $message,
                    'reason' => 'storyline_update',
                    'is_dream' => false
                ]);
                exit;
            }
        }
    }
    
    // === PRIORITY 3: CHECK FOR DREAMS ===
    $dream_check = shouldSendDream($db, $user_id, $initiation_data);
    if ($dream_check['send_dream']) {
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
        
        echo json_encode([
            'should_initiate' => true,
            'message' => $dream_message,
            'reason' => 'dream',
            'is_dream' => true
        ]);
        exit;
    }
    
    // === PRIORITY 4: REGULAR INITIATION ===
    $should_initiate = shouldMisukiInitiate($db, $user_id, $initiation_data);
    
    if ($should_initiate['initiate']) {
        $initiation_message = generateInitiationMessage($db, $user_id, $should_initiate['reason'], $should_initiate['context']);
        
        $stmt = $db->prepare("
            UPDATE conversation_initiation 
            SET last_misuki_initiation = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        saveConversation($db, $user_id, '[SYSTEM: Misuki initiated]', $initiation_message, 'gentle');
        
        echo json_encode([
            'should_initiate' => true,
            'message' => $initiation_message,
            'reason' => $should_initiate['reason'],
            'is_dream' => false
        ]);
    } else {
        echo json_encode(['should_initiate' => false]);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['should_initiate' => false, 'error' => $e->getMessage()]);
}

// ==================== HELPER FUNCTIONS ====================

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
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
    }
    
    return "Hey! I wanted to tell you about something that happened...";
}

function shouldSendDream($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_dream = $initiation_data['last_dream_sent'] ? strtotime($initiation_data['last_dream_sent']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_dream = ($now - $last_dream) / 3600;
    
    date_default_timezone_set('Asia/Tokyo');
    $misuki_hour = (int)date('G');
    $misuki_time = date('H:i');
    date_default_timezone_set('Asia/Jakarta');
    
    if ($misuki_hour < 6 || $misuki_hour >= 10) {
        return ['send_dream' => false, 'reason' => 'wrong_time_of_day'];
    }
    
    if ($hours_since_user < 8) {
        return ['send_dream' => false, 'reason' => 'too_soon'];
    }
    
    if ($last_dream > 0 && $hours_since_dream < 24) {
        return ['send_dream' => false, 'reason' => 'dream_cooldown'];
    }
    
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $said_goodnight = checkIfSaidGoodnight($recent_conversations);
    
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
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
    }
    
    return "Good morning! Just woke up from a dream about you... 💭✨";
}

function shouldMisukiInitiate($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_initiation = $initiation_data['last_misuki_initiation'] ? strtotime($initiation_data['last_misuki_initiation']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_initiation = ($now - $last_initiation) / 3600;
    $days_since_user = floor($hours_since_user / 24);
    
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $conversation_tone = analyzeConversationTone($recent_conversations);
    
    $needs_space = checkIfUserNeedsSpace($recent_conversations);
    if ($needs_space && $hours_since_user < 12) {
        return ['initiate' => false, 'reason' => 'giving_space'];
    }
    
    if ($hours_since_initiation < 0.75) {
        return ['initiate' => false, 'reason' => 'too_soon'];
    }
    
    $date_context = getDateContext($last_user_message);
    
    // Worry scenarios
    if ($conversation_tone['had_conflict'] && $hours_since_user >= 2 && $hours_since_user < 8) {
        return ['initiate' => true, 'reason' => 'worried_after_apology', 'context' => $date_context];
    }
    
    if ($conversation_tone['ended_negative'] && $hours_since_user >= 4 && $hours_since_user < 12) {
        return ['initiate' => true, 'reason' => 'worried_after_negative', 'context' => $date_context];
    }
    
    if ($days_since_user >= 1 && $conversation_tone['ended_negative']) {
        return ['initiate' => true, 'reason' => 'worried_long_silence', 'context' => $date_context];
    }
    
    // Casual check-ins
    if ($hours_since_user >= 8 && $hours_since_user < 24 && $conversation_tone['ended_positive']) {
        if (rand(1, 100) <= 30) {
            return ['initiate' => true, 'reason' => 'casual_checkin', 'context' => $date_context];
        }
    }
    
    if ($days_since_user >= 1 && $days_since_user < 3 && !$conversation_tone['ended_negative']) {
        return ['initiate' => true, 'reason' => 'missing_you', 'context' => $date_context];
    }
    
    if ($days_since_user >= 3) {
        return ['initiate' => true, 'reason' => 'long_silence', 'context' => $date_context];
    }
    
    $hour = (int)date('G');
    if ($hours_since_user >= 2 && $hour >= 9 && $hour <= 21 && $conversation_tone['ended_positive']) {
        if (rand(1, 100) <= 5) {
            $reasons = ['excited_chemistry', 'thinking_of_you', 'daily_update', 'random_sweet'];
            return ['initiate' => true, 'reason' => $reasons[array_rand($reasons)], 'context' => $date_context];
        }
    }
    
    return ['initiate' => false];
}

function analyzeConversationTone($conversations) {
    if (empty($conversations)) {
        return ['ended_positive' => true, 'ended_negative' => false, 'had_conflict' => false];
    }
    
    $last_conv = end($conversations);
    $user_msg = strtolower($last_conv['user_message']);
    $misuki_msg = strtolower($last_conv['misuki_response']);
    
    $positive_indicators = ['love you', 'thanks', 'thank you', 'haha', 'lol', 'nice', 'good', 'great'];
    $negative_indicators = ['tired', 'exhausted', 'stressed', 'upset', 'sad', 'angry'];
    $conflict_indicators = ['whatever', 'fine', 'don\'t care', 'leave me', 'stop'];
    
    $ended_positive = false;
    $ended_negative = false;
    $had_conflict = false;
    
    foreach ($positive_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $ended_positive = true;
            break;
        }
    }
    
    foreach ($negative_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $ended_negative = true;
            break;
        }
    }
    
    foreach ($conflict_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $had_conflict = true;
            break;
        }
    }
    
    if (!$ended_positive && !$ended_negative) {
        $ended_positive = true;
    }
    
    return [
        'ended_positive' => $ended_positive,
        'ended_negative' => $ended_negative,
        'had_conflict' => $had_conflict
    ];
}

function getDateContext($last_message_timestamp) {
    $now = time();
    $hours_diff = ($now - $last_message_timestamp) / 3600;
    $days_diff = floor($hours_diff / 24);
    
    date_default_timezone_set('Asia/Jakarta');
    $user_last_day = date('l', $last_message_timestamp);
    $user_current_day = date('l', $now);
    $user_last_date = date('F j, Y', $last_message_timestamp);
    $user_current_date = date('F j, Y', $now);
    
    date_default_timezone_set('Asia/Tokyo');
    $misuki_current_day = date('l', $now);
    $misuki_current_date = date('F j, Y', $now);
    $misuki_current_time = date('g:i A', $now);
    $misuki_hour = (int)date('G', $now);
    
    $misuki_time_of_day = 'day';
    if ($misuki_hour >= 5 && $misuki_hour < 12) {
        $misuki_time_of_day = 'morning';
    } elseif ($misuki_hour >= 12 && $misuki_hour < 17) {
        $misuki_time_of_day = 'afternoon';
    } elseif ($misuki_hour >= 17 && $misuki_hour < 21) {
        $misuki_time_of_day = 'evening';
    } else {
        $misuki_time_of_day = 'night';
    }
    
    date_default_timezone_set('Asia/Jakarta');
    
    $weekend_passed = ($user_last_day == 'Friday' && in_array($user_current_day, ['Saturday', 'Sunday', 'Monday']));
    
    return [
        'hours_since' => $hours_diff,
        'days_since' => $days_diff,
        'user_last_day' => $user_last_day,
        'user_current_day' => $user_current_day,
        'user_last_date' => $user_last_date,
        'user_current_date' => $user_current_date,
        'misuki_current_day' => $misuki_current_day,
        'misuki_current_date' => $misuki_current_date,
        'misuki_current_time' => $misuki_current_time,
        'misuki_time_of_day' => $misuki_time_of_day,
        'weekend_passed' => $weekend_passed
    ];
}

function checkIfUserNeedsSpace($conversations) {
    if (empty($conversations)) return false;
    
    $recent = array_slice($conversations, -3);
    
    foreach ($recent as $conv) {
        $message = strtolower($conv['user_message']);
        $space_phrases = ['need space', 'leave me alone', 'don\'t talk', 'not now', 'later'];
        
        foreach ($space_phrases as $phrase) {
            if (strpos($message, $phrase) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

function generateInitiationMessage($db, $user_id, $reason, $date_context) {
    $memories = getUserMemories($db, $user_id, 10);
    
    // INCREASED: Get more recent conversations for better context
    $recent_conversations = getRecentConversations($db, $user_id, 20);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    $pending_events = getPendingFutureEvents($db, $user_id);
    $overdue_events = getOverdueFutureEvents($db, $user_id);
    $future_events_context = buildFutureEventsContext($pending_events, $overdue_events);
    
    autoMarkOldEvents($db, $user_id, 7);
    
    // ENHANCED TIME CONTEXT WITH WARNINGS
    $time_context = "\n=== ⚠️ CRITICAL TIME CONTEXT (READ CAREFULLY!) ===\n";
    $time_context .= "Dan's LAST message was: {$date_context['user_last_date']}\n";
    $time_context .= "Current date/time NOW: {$date_context['user_current_date']}\n";
    
    if ($date_context['hours_since'] < 1) {
        $minutes_since = round($date_context['hours_since'] * 60);
        $time_context .= "⚠️ ONLY {$minutes_since} MINUTES AGO! This JUST happened!\n";
        $time_context .= "⚠️ The conversation you see above happened MOMENTS AGO!\n";
        $time_context .= "⚠️ DO NOT say 'yesterday' or 'the other day' - this was JUST NOW!\n\n";
    } elseif ($date_context['days_since'] >= 1) {
        $time_context .= "Time since last message: {$date_context['days_since']} day(s)\n";
    } else {
        $time_context .= "Time since last message: " . round($date_context['hours_since'], 1) . " hours\n";
    }
    
    // EXTRA WARNING FOR RECENT MESSAGES
    if ($date_context['hours_since'] < 2) {
        $time_context .= "\n🚨 ATTENTION: Dan just messaged you less than 2 hours ago!\n";
        $time_context .= "Look at the conversation history above - that conversation JUST happened!\n";
        $time_context .= "If Dan mentioned FUTURE plans (like going to a movie later today), they HAVEN'T happened yet!\n";
        $time_context .= "NEVER say 'yesterday' or 'how did it go' for things that haven't happened yet!\n";
        $time_context .= "Use present/future tense: 'Hope you have fun!' NOT 'How was it?'\n\n";
    }
    
    $reason_contexts = [
        'worried_after_apology' => "You apologized but he hasn't responded. You're worried he's still upset.",
        'worried_after_negative' => "Dan seemed upset and hasn't replied. You're genuinely worried.",
        'worried_long_silence' => "It's been over a day and he seemed upset. You're concerned.",
        'long_silence' => "It's been 3+ days. You're getting concerned.",
        'missing_you' => "It's been a day+. You're not worried, just miss him.",
        'casual_checkin' => "Several hours since last message. Just checking in casually.",
        'excited_chemistry' => "You learned something cool in chemistry! Share your excitement!",
        'thinking_of_you' => "Dan crossed your mind. Reach out naturally.",
        'daily_update' => "Share something from your day.",
        'random_sweet' => "Random sweet message to your boyfriend."
    ];
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n" . $time_context . "\n\n" . $future_events_context;
    $prompt .= "\n\nYou're INITIATING conversation. Context: " . ($reason_contexts[$reason] ?? 'Check in with Dan');
    $prompt .= "\n\n⚠️ CRITICAL: Look at the conversation history timestamps! Respect what JUST happened vs what happened days ago!";
    $prompt .= "\n\nWrite ONE natural message (1-2 sentences). Be yourself - Misuki, his girlfriend.";
    
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
        return "Hey... just thinking about you. How are you doing? 💭";
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
        'messages' => [['role' => 'user', 'content' => 'Generate your message to Dan:']],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
    }
    
    return "Hey... just thinking about you. How are you doing? 💭";
}

?>