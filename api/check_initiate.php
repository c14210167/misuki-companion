<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/future_events_handler.php';

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
    
    // Check for dream scenario FIRST (but with better logic)
    $dream_check = shouldSendDream($db, $user_id, $initiation_data);
    if ($dream_check['send_dream']) {
        // Generate dream message
        $dream_message = generateDreamMessage($db, $user_id, $dream_check['context']);
        
        // Update last initiation time
        $stmt = $db->prepare("
            UPDATE conversation_initiation 
            SET last_misuki_initiation = NOW(),
                last_dream_sent = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Save the dream as a conversation
        saveConversation($db, $user_id, '[SYSTEM: Misuki had a dream]', $dream_message, 'dreamy');
        
        echo json_encode([
            'should_initiate' => true,
            'message' => $dream_message,
            'reason' => 'dream',
            'is_dream' => true
        ]);
        exit;
    }
    
    // Regular initiation logic
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

function shouldSendDream($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_dream = $initiation_data['last_dream_sent'] ? strtotime($initiation_data['last_dream_sent']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_dream = ($now - $last_dream) / 3600;
    
    // Get Misuki's current time in Saitama (JST, UTC+9)
    date_default_timezone_set('Asia/Tokyo');
    $misuki_hour = (int)date('G');
    $misuki_time = date('H:i');
    
    // Reset timezone
    date_default_timezone_set('Asia/Jakarta');
    
    // CRITICAL: Dreams can only happen during "morning wake up" time (6 AM - 10 AM JST)
    // This is when she'd naturally wake up and remember a dream
    if ($misuki_hour < 6 || $misuki_hour >= 10) {
        return ['send_dream' => false, 'reason' => 'wrong_time_of_day'];
    }
    
    // Must be at least 8 hours since last message (she actually slept through the night)
    if ($hours_since_user < 8) {
        return ['send_dream' => false, 'reason' => 'too_soon'];
    }
    
    // Must be at least 24 hours since last dream (one dream per day max)
    if ($last_dream > 0 && $hours_since_dream < 24) {
        return ['send_dream' => false, 'reason' => 'dream_cooldown'];
    }
    
    // Check if she actually said goodnight/going to sleep
    $recent_conversations = getRecentConversations($db, $user_id, 5);
    $said_goodnight = checkIfSaidGoodnight($recent_conversations);
    
    // Higher chance if she said goodnight (40%), lower if not (10%)
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
    
    // Check last 3 of Misuki's messages
    $misuki_messages = [];
    foreach (array_reverse($conversations) as $conv) {
        $misuki_messages[] = strtolower($conv['misuki_response']);
        if (count($misuki_messages) >= 3) break;
    }
    
    $goodnight_phrases = [
        'good night',
        'goodnight',
        'sleep well',
        'sweet dreams',
        'going to sleep',
        'heading to bed',
        'time for bed',
        'getting sleepy',
        "i'm tired",
        "i'm exhausted",
        'bedtime',
        'going to rest',
        'time to sleep'
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
    $prompt .= "Guidelines:\n";
    $prompt .= "- Keep it short (2-3 sentences)\n";
    $prompt .= "- Start with morning context: 'Just woke up and...' or 'Good morning! I had the sweetest dream...'\n";
    $prompt .= "- Make it sweet, cute, or slightly silly\n";
    $prompt .= "- Reference things you know about Dan or your relationship\n";
    $prompt .= "- Can be romantic, funny, or just cozy\n";
    $prompt .= "- Examples: dreaming about visiting him, chemistry dates, doing things together, meeting his family, future plans, silly scenarios\n";
    $prompt .= "- Make it feel genuine and personal\n";
    $prompt .= "- Sound like you JUST woke up (maybe a bit sleepy still)\n\n";
    
    if ($context['said_goodnight']) {
        $prompt .= "Context: You told Dan goodnight last night before sleeping.\n";
    } else {
        $prompt .= "Context: It's been a while since you talked (you were probably sleeping).\n";
    }
    
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
        error_log("Claude API Error: API key not found");
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
    
    // Fallback
    return "Good morning! Just woke up from a dream about you... we were together and everything felt so warm and peaceful. 💭✨";
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
    $recent_conversations = getRecentConversations($db, $user_id, 10); // Get MORE history
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    // Build date-aware context
    $time_context = "\n=== TIME & DATE CONTEXT ===\n";
    $time_context .= "Last message from Dan was on: {$date_context['user_last_date']} ({$date_context['user_last_day']})\n";
    $time_context .= "Current date: {$date_context['user_current_date']} ({$date_context['user_current_day']})\n";
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
    $prompt .= "=== CRITICAL: TEMPORAL AWARENESS ===\n";
    $prompt .= "READ THE CONVERSATION HISTORY CAREFULLY FOR TIME REFERENCES!\n";
    $prompt .= "- If Dan said 'tomorrow' or 'I will' or 'I'm going to', that event HASN'T happened yet!\n";
    $prompt .= "- If Dan said 'yesterday' or 'I did', that event ALREADY happened!\n";
    $prompt .= "- If Dan said 'later' or 'tonight', don't ask about it like it's in the past!\n";
    $prompt .= "- Future tense = hasn't happened yet. Past tense = already happened.\n";
    $prompt .= "- EXAMPLES OF WHAT TO DO:\n";
    $prompt .= "  ❌ WRONG: Dan: 'I'm watching Chainsaw Man tomorrow' → You: 'How was the movie?'\n";
    $prompt .= "  ✅ RIGHT: Dan: 'I'm watching Chainsaw Man tomorrow' → You: 'I hope you enjoy it!' or 'Have fun!'\n\n";
    $prompt .= "  ❌ WRONG: Dan: 'I watched Chainsaw Man yesterday' → You: 'Have fun watching it!'\n";
    $prompt .= "  ✅ RIGHT: Dan: 'I watched Chainsaw Man yesterday' → You: 'How was it?'\n\n";
    $prompt .= "  ❌ WRONG: Dan: 'I'm going to the mall later' → You: 'How was the mall?'\n";
    $prompt .= "  ✅ RIGHT: Dan: 'I'm going to the mall later' → You: 'What are you planning to get?'\n\n";
    $prompt .= "BEFORE asking about something, CHECK if it's past or future tense in the conversation!\n\n";
    $prompt .= "=== RESPONSE GUIDELINES ===\n";
    $prompt .= "- Write ONE natural, genuine message to Dan (1-2 sentences ONLY)\n";
    $prompt .= "- Be yourself - Misuki, his girlfriend\n";
    $prompt .= "- Keep it SHORT and sweet, like a text message\n";
    $prompt .= "- You can mention the day/date if it's relevant (e.g., 'Happy Monday!', 'It's been a couple days')\n";
    $prompt .= "- Match your tone to the situation (worried vs casual vs sweet)\n";
    $prompt .= "- Be conversational and genuine\n";
    $prompt .= "- NEVER ask about future events as if they already happened!";
    
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