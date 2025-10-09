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
        // Generate initiation message using AI
        $initiation_message = generateInitiationMessage($db, $user_id, $should_initiate['reason'], $should_initiate['context']);
        
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
    $days_since_user = floor($hours_since_user / 24);
    
    // Get last few conversations to understand context
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    
    // Analyze how the last conversation ended
    $conversation_tone = analyzeConversationTone($recent_conversations);
    
    // Check if user asked for space
    $needs_space = checkIfUserNeedsSpace($recent_conversations);
    if ($needs_space) {
        // Give space - don't initiate for at least 12 hours
        if ($hours_since_user < 12) {
            return ['initiate' => false, 'reason' => 'giving_space'];
        }
    }
    
    // MINIMUM wait time is 45 minutes
    if ($hours_since_initiation < 0.75) {
        return ['initiate' => false, 'reason' => 'too_soon'];
    }
    
    // Get date/time context
    $date_context = getDateContext($last_user_message);
    
    // Check recent emotional state
    $emotional_context = getEmotionalContext($db, $user_id);
    
    // ===== PRIORITY SITUATIONS (WORRY) =====
    
    // 1. After conflict/argument - check if user ignored apology (2+ hours no response)
    if ($conversation_tone['had_conflict'] && $hours_since_user >= 2 && $hours_since_user < 8) {
        return [
            'initiate' => true, 
            'reason' => 'worried_after_apology',
            'context' => $date_context
        ];
    }
    
    // 2. User seemed very upset and hasn't replied in 4+ hours
    if ($conversation_tone['ended_negative'] && $hours_since_user >= 4 && $hours_since_user < 12) {
        return [
            'initiate' => true, 
            'reason' => 'worried_after_negative',
            'context' => $date_context
        ];
    }
    
    // 3. User hasn't messaged in 24+ hours AND last convo was negative
    if ($days_since_user >= 1 && $conversation_tone['ended_negative']) {
        return [
            'initiate' => true, 
            'reason' => 'worried_long_silence',
            'context' => $date_context
        ];
    }
    
    // ===== CASUAL CHECK-INS (NO WORRY) =====
    
    // 4. User hasn't messaged in 8+ hours but ended on good note (casual check-in)
    if ($hours_since_user >= 8 && $hours_since_user < 24 && $conversation_tone['ended_positive']) {
        // 30% chance to make it feel natural
        if (rand(1, 100) <= 30) {
            return [
                'initiate' => true, 
                'reason' => 'casual_checkin',
                'context' => $date_context
            ];
        }
    }
    
    // 5. It's been a full day or more (ended on good note = casual, not worried)
    if ($days_since_user >= 1 && $days_since_user < 3 && !$conversation_tone['ended_negative']) {
        return [
            'initiate' => true, 
            'reason' => 'missing_you',
            'context' => $date_context
        ];
    }
    
    // 6. It's been 3+ days (definitely reach out, slightly concerned)
    if ($days_since_user >= 3) {
        return [
            'initiate' => true, 
            'reason' => 'long_silence',
            'context' => $date_context
        ];
    }
    
    // 7. Random sweet initiation during reasonable hours (5% chance if it's been 2+ hours)
    $hour = (int)date('G');
    if ($hours_since_user >= 2 && $hour >= 9 && $hour <= 21 && $conversation_tone['ended_positive']) {
        if (rand(1, 100) <= 5) {
            $reasons = ['excited_chemistry', 'thinking_of_you', 'daily_update', 'random_sweet'];
            return [
                'initiate' => true, 
                'reason' => $reasons[array_rand($reasons)],
                'context' => $date_context
            ];
        }
    }
    
    return ['initiate' => false];
}

function analyzeConversationTone($conversations) {
    if (empty($conversations)) {
        return [
            'ended_positive' => true,
            'ended_negative' => false,
            'had_conflict' => false
        ];
    }
    
    $last_conv = end($conversations);
    $user_msg = strtolower($last_conv['user_message']);
    $misuki_msg = strtolower($last_conv['misuki_response']);
    
    // Check for positive endings
    $positive_indicators = [
        'love you', 'thanks', 'thank you', 'appreciate', 'haha', 'lol',
        'nice', 'good', 'great', 'awesome', 'cool', 'sweet', '❤️', '💕',
        'goodnight', 'good night', 'sleep well', 'talk later', 'ttyl', 'bye'
    ];
    
    // Check for negative endings
    $negative_indicators = [
        'tired', 'exhausted', 'stressed', 'upset', 'sad', 'angry', 'frustrated',
        'annoyed', 'worried', 'anxious', 'don\'t feel', 'not good', 'bad day'
    ];
    
    // Check for conflict
    $conflict_indicators = [
        'whatever', 'fine', 'don\'t care', 'leave me', 'stop', 'annoying'
    ];
    
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
    
    // Check if Misuki apologized
    $apology_phrases = ['sorry', 'apologize', 'my fault', 'forgive'];
    foreach ($apology_phrases as $phrase) {
        if (strpos($misuki_msg, $phrase) !== false) {
            $had_conflict = true;
            break;
        }
    }
    
    // Default to neutral/positive if no indicators
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
    $last_msg = $last_message_timestamp;
    
    $hours_diff = ($now - $last_msg) / 3600;
    $days_diff = floor($hours_diff / 24);
    
    // User's timezone (Jakarta)
    date_default_timezone_set('Asia/Jakarta');
    $user_last_day = date('l', $last_msg);
    $user_current_day = date('l', $now);
    $user_last_date = date('F j, Y', $last_msg);
    $user_current_date = date('F j, Y', $now);
    
    // Misuki's timezone (Saitama, Japan - 2 hours ahead)
    date_default_timezone_set('Asia/Tokyo');
    $misuki_current_day = date('l', $now);
    $misuki_current_date = date('F j, Y', $now);
    $misuki_current_time = date('g:i A', $now);
    $misuki_hour = (int)date('G', $now);
    
    // Determine Misuki's time of day
    if ($misuki_hour >= 5 && $misuki_hour < 12) {
        $misuki_time_of_day = 'morning';
    } elseif ($misuki_hour >= 12 && $misuki_hour < 17) {
        $misuki_time_of_day = 'afternoon';
    } elseif ($misuki_hour >= 17 && $misuki_hour < 21) {
        $misuki_time_of_day = 'evening';
    } else {
        $misuki_time_of_day = 'night';
    }
    
    // Reset to user timezone
    date_default_timezone_set('Asia/Jakarta');
    
    // Check if it's a new day
    $crossed_midnight = date('Y-m-d', $last_msg) !== date('Y-m-d', $now);
    
    // Check if weekend passed
    $weekend_passed = false;
    if ($user_last_day == 'Friday' && in_array($user_current_day, ['Saturday', 'Sunday', 'Monday'])) {
        $weekend_passed = true;
    }
    
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
        'crossed_midnight' => $crossed_midnight,
        'weekend_passed' => $weekend_passed
    ];
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

function generateInitiationMessage($db, $user_id, $reason, $date_context) {
    $memories = getUserMemories($db, $user_id, 10);
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    // Build date-aware context
    $time_context = "\n=== TIME & DATE CONTEXT ===\n";
    $time_context .= "Last message from Dan was on: {$date_context['last_date']} ({$date_context['last_day']})\n";
    $time_context .= "Current date: {$date_context['current_date']} ({$date_context['current_day']})\n";
    $time_context .= "Time since last message: ";
    
    if ($date_context['days_since'] >= 1) {
        $time_context .= "{$date_context['days_since']} day(s)\n";
    } else {
        $time_context .= round($date_context['hours_since'], 1) . " hours\n";
    }
    
    if ($date_context['weekend_passed']) {
        $time_context .= "Note: The weekend has passed since you last talked.\n";
    }
    
    // Natural context descriptions for AI
    $reason_contexts = [
        'worried_after_apology' => "You apologized to Dan after a disagreement, but he hasn't responded for several hours. You're worried he's still upset. Check in gently and genuinely.",
        'worried_after_negative' => "Dan seemed upset or stressed in your last conversation and hasn't replied. You're genuinely worried about him.",
        'worried_long_silence' => "It's been over a day since Dan messaged and he seemed upset last time. You're genuinely worried something might be wrong.",
        'long_silence' => "It's been 3+ days since you heard from Dan. You're getting concerned but trying not to be pushy.",
        'missing_you' => "It's been a day or more since you heard from Dan. You ended on a good note, so you're not worried - just miss him and want to check in casually.",
        'casual_checkin' => "It's been several hours since Dan messaged. You ended on a good note, so just checking in casually - no worry, just curious how he's doing.",
        'excited_chemistry' => "You just learned something cool in chemistry or finished homework and it made you think of Dan. Share your excitement!",
        'thinking_of_you' => "Dan crossed your mind. Reach out naturally - you're not worried, just want to talk.",
        'daily_update' => "Share something from your day in Saitama, with your mom, or how you're feeling.",
        'random_sweet' => "Just a random sweet message because you wanted to talk to your boyfriend."
    ];
    
    $prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n" . $time_context . "\n\n";
    $prompt .= "IMPORTANT: You are INITIATING a conversation with Dan. You're reaching out first.\n\n";
    $prompt .= "Context: " . ($reason_contexts[$reason] ?? $reason_contexts['thinking_of_you']) . "\n\n";
    $prompt .= "=== RESPONSE GUIDELINES ===
- Write ONE natural, genuine message to Dan (1-2 sentences ONLY)
- Be yourself - Misuki, his girlfriend
- Keep it SHORT and sweet, like a text message
- You can mention the day/date if it's relevant (e.g., 'Happy Monday!', 'It's been a couple days')
- Match your tone to the situation (worried vs casual vs sweet)
- Be conversational and genuine";
    
    // Read API key
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
        error_log("Claude API Error: API key not found in environment");
        return "Hey Dan... just thinking about you. Everything okay? 💭";
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
            [
                'role' => 'user',
                'content' => 'Generate your message to Dan:'
            ]
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
    
    // Simple fallback
    return "Hey... just thinking about you. How are you doing? 💭";
}

?>