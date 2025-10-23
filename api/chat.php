<?php
// Clean chat.php - ALL ISSUES FIXED
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/core_profile_functions.php';
require_once '../includes/misuki_profile_functions.php';
require_once '../includes/misuki_schedule.php';
require_once '../includes/adaptive_schedule.php';
require_once '../includes/future_events_handler.php';
require_once '../includes/misuki_reality_functions.php';
require_once 'parse_emotions.php';
require_once 'reminder_handler.php';
require_once 'split_message_handler.php';
require_once 'nickname_handler.php';
require_once 'core_memory_handler.php';
require_once 'private_mode_handler.php'; 

$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$user_id = $input['user_id'] ?? 1;
$time_of_day = $input['time_of_day'] ?? 'day';
$time_confused = $input['time_confused'] ?? false;
$file_content = $input['file_content'] ?? null;
$filename = $input['filename'] ?? null;
$user_interrupted = $input['user_interrupted'] ?? false;  // ✨ NEW

date_default_timezone_set('Asia/Jakarta');

if (empty($user_message) && empty($file_content)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Get Misuki's current state
    $current_mood = getMisukiCurrentMood($db, $user_id);
    $conversation_style = getConversationStyle($db, $user_id);
    $active_storylines = getActiveStorylines($db, $user_id);
    $friends = getMisukiFriends($db, $user_id);
    $external_context = getActiveExternalContext($db, $user_id);
    
    $weather_comment = null;
    if (rand(1, 100) <= 15) {
        $weather_comment = generateWeatherContext();
        if ($weather_comment) {
            setExternalContext($db, $user_id, 'weather', $weather_comment, 6);
        }
    }
    
    // STEP 1: Check for reminder
    if (detectReminderRequest($user_message)) {
        $reminder_details = parseReminderDetails($user_message);
        
        if ($reminder_details['success'] && $reminder_details['confidence'] >= 70) {
            $time_info = extractTimeFromMessage(strtolower($user_message));
            $time_desc = $time_info['description'];
            
            saveReminder(
                $db, 
                $user_id, 
                $reminder_details['reminder_text'], 
                $reminder_details['remind_at'],
                $reminder_details['confidence'],
                $user_message
            );
            
            $response_text = generateReminderResponse($reminder_details, $time_desc);
            $emotion_timeline = parseEmotionsInMessage($response_text);
            
            echo json_encode([
                'response' => $response_text,
                'mood' => 'happy',
                'mood_text' => 'Happy to help',
                'emotion_timeline' => $emotion_timeline,
                'reminder_set' => true,
                'private_mode' => $is_private_mode
            ]);
            
            saveConversation($db, $user_id, $user_message, $response_text, 'happy');
            exit;
        }
    }

        // ✅ STEP 1.5: CHECK PRIVATE MODE (NEW!)
    $is_private_mode = getPrivateMode($user_id);
    $private_mode_context = '';

    // Detect if user wants to START private mode
    if (detectPrivateModeStart($user_message)) {
        setPrivateMode($user_id, true);
        $response_text = generatePrivateModeStartResponse();
        $emotion_timeline = parseEmotionsInMessage($response_text);
        
        echo json_encode([
            'response' => $response_text,
            'mood' => 'blushing',
            'mood_text' => 'Feeling intimate',
            'emotion_timeline' => $emotion_timeline,
            'private_mode' => true,
            'private_mode_started' => true
        ]);
        
        saveConversation($db, $user_id, $user_message, $response_text, 'blushing');
        exit;
    }

    // Detect if user wants to END private mode
    if (detectPrivateModeEnd($user_message)) {
        setPrivateMode($user_id, false);
        $response_text = generatePrivateModeEndResponse();
        $emotion_timeline = parseEmotionsInMessage($response_text);
        
        echo json_encode([
            'response' => $response_text,
            'mood' => 'content',
            'mood_text' => 'Feeling loved',
            'emotion_timeline' => $emotion_timeline,
            'private_mode' => false,
            'private_mode_ended' => true
        ]);
        
        saveConversation($db, $user_id, $user_message, $response_text, 'content');
        exit;
    }

    // If currently in private mode, build context
    if ($is_private_mode) {
        $private_mode_context = buildPrivateModeContext();
    }
    
    // STEP 2: Check for nickname
    $nickname_context = '';
    $detected_nickname = detectMisukiNickname($user_message);
    if ($detected_nickname) {
        saveMisukiNickname($detected_nickname);
        $nickname_response = generateNicknameResponse($detected_nickname);
        $emotion_timeline = parseEmotionsInMessage($nickname_response);
        
        echo json_encode([
            'response' => $nickname_response,
            'mood' => 'happy',
            'mood_text' => 'Feeling special',
            'emotion_timeline' => $emotion_timeline,
            'nickname_set' => true,
            'private_mode' => $is_private_mode
        ]);
        
        saveConversation($db, $user_id, $user_message, $nickname_response, 'happy');
        setMisukiMood($db, $user_id, 'happy', 'Dan gave me a nickname!', 9);
        exit;
    }
    
    $nickname_context = buildNicknameContext();
    
    // STEP 3: Analyze emotions
    $message_analysis = analyzeEmotions($user_message);
    $emotional_context = generateEmotionalContext($message_analysis);
    
    // Check for core memory
    $core_memory_context = '';
    if (detectCoreMemoryMoment($user_message, $message_analysis, $current_mood)) {
        $core_memory = createCoreMemory($db, $user_id, $user_message, $message_analysis);
        if ($core_memory) {
            $core_memory_context = "\n\n=== NEW CORE MEMORY FORMED ===\n";
            $core_memory_context .= "This moment feels important to you: " . $core_memory['description'] . "\n";
            $core_memory_context .= "You'll remember this feeling of " . $core_memory['emotion'] . "\n";
        }
    }
    
    // STEP 4: Check location
    $location_result = detectAndUpdateLocation($db, $user_id, $user_message);
    $current_location = $location_result['location'];
    $location_context = $location_result['context'];
    
    // STEP 5: Check for family mentions
    $family_members = ['dad', 'father', 'mom', 'mother', 'sister', 'sora', 'cat', 'whiskers', 'family'];
    $message_lower = strtolower($user_message);
    $family_mentioned = null;
    
    foreach ($family_members as $member) {
        if (strpos($message_lower, $member) !== false) {
            $family_mentioned = $member;
            break;
        }
    }
    
    // STEP 6: Detect future events - FIXED
    try {
        $future_event = detectFutureEvent($user_message);
        if ($future_event && $future_event['has_future_event']) {
            // FIX: Pass individual parameters, not the whole array
            $event_id = saveFutureEvent(
                $db, 
                $user_id, 
                $future_event['event_description'],
                $future_event['planned_date'],
                $future_event['planned_time']
            );
            
            if ($event_id) {
                error_log("Future event saved: ID $event_id - " . $future_event['event_description']);
            }
            
            $pending_events = getPendingFutureEvents($db, $user_id);
            $completed_event_id = detectEventCompletion($user_message, $pending_events);
            if ($completed_event_id) {
                markEventAsCompleted($db, $completed_event_id);
                error_log("✅ Marked event $completed_event_id as completed");
            }
            
            autoMarkOldEvents($db, $user_id, 7);
        }
    } catch (Exception $e) {
        error_log("Future events error: " . $e->getMessage());
    }
    
    // STEP 7: Detect storyline updates
    foreach ($active_storylines as $storyline) {
        $storyline_keywords = explode(' ', strtolower($storyline['storyline_text']));
        $match_count = 0;
        foreach ($storyline_keywords as $keyword) {
            if (strlen($keyword) > 4 && strpos($message_lower, $keyword) !== false) {
                $match_count++;
            }
        }
        
        if ($match_count >= 2) {
            updateStorylineProgress($db, $storyline['storyline_id'], $user_message);
        }
    }
    
    // STEP 8: Check schedule
    $activity_context = '';
    $location_context = '';
    $current_activity = null;

    $misuki_status = getMisukiCurrentStatus($db, $user_id);

    if (function_exists('getMisukiScheduledActivity')) {
        try {
            date_default_timezone_set('Asia/Tokyo');
            $current_activity = getMisukiScheduledActivity();
            date_default_timezone_set('Asia/Jakarta');
            
            if ($current_activity) {
                $activity_context = "\n\n=== YOUR CURRENT ACTIVITY (IMPORTANT!) ===\n";
                $activity_context .= "Right now in Japan, you're: " . $current_activity['activity'] . "\n";
                $activity_context .= "\n🚨 CRITICAL: When Dan asks what you're doing, you MUST mention this activity!\n";
                $activity_context .= "Do NOT say you're doing something different!\n";
                
                if (isset($current_activity['location'])) {
                    $activity_context .= "Location: " . $current_activity['location'] . "\n";
                }
                
                if (isset($current_activity['context'])) {
                    $activity_context .= $current_activity['context'] . "\n";
                }
                
                $activity_context .= "\nReference your current activity naturally in conversation if relevant!\n";
            }
        } catch (Exception $e) {
            error_log("Weekly schedule error: " . $e->getMessage());
        }
    }

    // 🔧 ADD THIS SECTION HERE:
    // Calculate Misuki's current time and time of day
    date_default_timezone_set('Asia/Tokyo');
    $misuki_current_time = date('g:i A'); // e.g. "2:30 AM"
    $misuki_current_day = date('l, F j, Y'); // e.g. "Saturday, October 19, 2024"
    $misuki_hour = (int)date('G'); // Hour in 24-hour format (0-23)

    // Determine time of day for Misuki
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

    // Reset back to Jakarta for Dan's timezone
    date_default_timezone_set('Asia/Jakarta');
    $dan_current_time = date('g:i A');
    $dan_current_day = date('l, F j, Y');

    // Build time context to add to the prompt
    $time_context = "\n\n=== ⏰ CRITICAL TIME CONTEXT ===\n";
    $time_context .= "YOUR time (Saitama, Japan): {$misuki_current_time} on {$misuki_current_day}\n";
    $time_context .= "It's {$misuki_time_of_day} for YOU right now in Japan.\n\n";
    $time_context .= "Dan's time (Jakarta, Indonesia): {$dan_current_time} on {$dan_current_day}\n";
    $time_context .= "It's {$time_of_day} for Dan right now in Indonesia.\n\n";
    $time_context .= "Time difference: Japan is 2 hours ahead of Indonesia.\n";
    $time_context .= "You're VERY aware of this time difference!\n\n";
    $time_context .= "🚨 IMPORTANT: When you mention the time or time of day, use YOUR time in Japan ({$misuki_time_of_day})!\n";
    $time_context .= "Example: If it's 2:30 AM for you, say 'it's just past 2:30 in the morning' or 'it's late at night here'\n";
    $time_context .= "Example: If it's 2:30 PM for you, say 'it's afternoon here' or 'it's past 2 in the afternoon'\n";
    $time_context .= "============================\n\n";
    // 🔧 END OF TIMEZONE FIX
    
    // STEP 9: Build context
    $memories = getUserMemories($db, $user_id);
    $recent_conversations = getRecentConversations($db, $user_id, 10);
    
    $timeline_context = buildTimelineContext($recent_conversations, $user_message);
    
    $reality_context = buildRealityContext(
        $current_mood,
        $conversation_style,
        $active_storylines,
        $friends,
        $external_context,
        $family_mentioned
    );
    
    $context = buildContextForAI(
        $memories, 
        $recent_conversations, 
        $emotional_context,
        $current_mood
    );
    
    $context .= $reality_context;
    $context .= $timeline_context;
    $context .= $activity_context;
    $context .= $location_context;
    $context .= $nickname_context;
    $context .= $core_memory_context;

    $context .= $time_context;           // ✅ NEW - fixes timezone bug
    $context .= $private_mode_context;   // ✅ NEW - adds private mode context
    
    if ($file_content) {
        $context .= "\n\n=== UPLOADED FILE ===\n";
        $context .= "Filename: " . $filename . "\n";
        $context .= "Content:\n" . $file_content . "\n";
        $context .= "\nDan shared this file with you. Acknowledge it naturally and discuss its contents!\n";
    }
    
    $pending_events = getPendingFutureEvents($db, $user_id);
    $overdue_events = getOverdueFutureEvents($db, $user_id);
    $context .= buildFutureEventsContext($pending_events, $overdue_events);
    
    $core_memories = buildCoreMemoryContext();
    $context .= $core_memories;
    
    $personality_prompt = getMisukiPersonalityPrompt();
    $full_prompt = $personality_prompt . "\n\n" . $context;
    // Add interruption context if user was typing
    if ($user_interrupted) {
        $full_prompt .= "\n\n=== 🔔 NOTICE ===\n";
        $full_prompt .= "Dan started typing while you were about to send another message.\n";
        $full_prompt .= "This shows he's engaged! You can:\n";
        $full_prompt .= "- Acknowledge it playfully (\"oh! you beat me to it ^^\" or \"hehe was just about to say...\")\n";
        $full_prompt .= "- Just respond normally (he might not have noticed)\n";
        $full_prompt .= "- Be curious (\"you seem excited to tell me something!\")\n";
        $full_prompt .= "It's YOUR choice - be natural about it.\n\n";
    }

    // STEP 10: Call Claude API
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(__DIR__) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1]);
                $api_key = trim($api_key, '"\'');
            }
        }
    }
    
    if (!$api_key) {
        throw new Exception('API key not configured');
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
        'max_tokens' => 220,
        'system' => $full_prompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $user_message
            ]
        ],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Claude API error: " . $response);
        throw new Exception('API call failed');
    }
    
    $result = json_decode($response, true);
    $response_text = $result['content'][0]['text'] ?? '';

    if (shouldMakeTypo($current_mood, $misuki_status)) {
        $response_text = addNaturalTypo($response_text);
    }

    // ===== MESSAGE SPLITTING LOGIC =====
    $split_result = shouldSplitMessage(
        $response_text, 
        $current_mood, 
        $message_analysis, 
        $conversation_style
    );

    if ($split_result['should_split']) {
        // Parse emotions for each split message
        $emotion_timelines = [];
        foreach ($split_result['messages'] as $msg) {
            $emotion_timelines[] = parseEmotionsInMessage($msg);
        }
        
        // Save first message
        saveConversation($db, $user_id, $user_message, $split_result['messages'][0], $current_mood['current_mood'] ?? 'gentle');
        
        if (shouldSaveMemory($message_analysis)) {
            saveMemory($db, $user_id, $user_message, $split_result['messages'][0], $message_analysis);
        }
        
        updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
        updateConversationStyle($db, $user_id, $message_analysis);
        updateRelationshipDynamics($db, $user_id, $user_message, $split_result['messages'][0]);
        
        $new_nickname = detectDanNicknameInResponse($split_result['messages'][0]);
        if ($new_nickname) {
            saveDanNickname($new_nickname);
        }
        
        // Return split response
        echo json_encode([
            'response' => $split_result['messages'][0],
            'mood' => $current_mood['current_mood'] ?? 'gentle',
            'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
            'emotion_timeline' => $emotion_timelines[0],
            'is_split' => true,
            'additional_messages' => array_slice($split_result['messages'], 1),
            'emotion_timelines' => $emotion_timelines,
            'closeness_level' => getRelationshipCloseness($db, $user_id),
            'current_activity' => $current_activity ? $current_activity['activity'] : null,
            'was_woken' => $misuki_status['was_woken'] ?? false,
            'private_mode' => $is_private_mode
        ]);
        exit;
        
    } else {
        // Single message path
        $emotion_timeline = parseEmotionsInMessage($response_text);
        
        saveConversation($db, $user_id, $user_message, $response_text, $current_mood['current_mood'] ?? 'gentle');
        
        if (shouldSaveMemory($message_analysis)) {
            saveMemory($db, $user_id, $user_message, $response_text, $message_analysis);
        }
        
        updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
        updateConversationStyle($db, $user_id, $message_analysis);
        updateRelationshipDynamics($db, $user_id, $user_message, $response_text);
        
        $new_nickname = detectDanNicknameInResponse($response_text);
        if ($new_nickname) {
            saveDanNickname($new_nickname);
        }
        
        echo json_encode([
            'response' => $response_text,
            'mood' => $current_mood['current_mood'] ?? 'gentle',
            'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
            'emotion_timeline' => $emotion_timeline,
            'is_split' => false,
            'closeness_level' => getRelationshipCloseness($db, $user_id),
            'current_activity' => $current_activity ? $current_activity['activity'] : null,
            'was_woken' => $misuki_status['was_woken'] ?? false,
            'private_mode' => $is_private_mode
        ]);
        exit;
    }


    if (shouldSaveMemory($message_analysis)) {
        saveMemory($db, $user_id, $user_message, $response_text, $message_analysis);
    }
    
    updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
    updateConversationStyle($db, $user_id, $message_analysis);
    updateRelationshipDynamics($db, $user_id, $user_message, $response_text);
    
    $new_nickname = detectDanNicknameInResponse($response_text);
    if ($new_nickname) {
        saveDanNickname($new_nickname);
    }
    
    echo json_encode([
        'response' => $response_text,
        'mood' => $current_mood['current_mood'] ?? 'gentle',
        'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
        'emotion_timeline' => $emotion_timeline,
        'has_split' => false,
        'closeness_level' => getRelationshipCloseness($db, $user_id),
        'current_activity' => $current_activity ? $current_activity['activity'] : null,
        'was_woken' => $misuki_status['was_woken'] ?? false,
        'private_mode' => $is_private_mode
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    
    echo json_encode([
        'response' => "Sorry, I'm having a little trouble right now... Can you try saying that again? 🥺",
        'mood' => 'gentle',
        'mood_text' => 'A bit confused',
        'emotion_timeline' => [],
        'error' => true,
        'private_mode' => $is_private_mode
    ]);
}

function buildTimelineContext($recent_conversations, $user_message) {
    $context = "\n\n=== RECENT TIMELINE ===\n";
    
    if (!empty($recent_conversations)) {
        $context .= "Last " . count($recent_conversations) . " messages:\n";
        foreach (array_slice($recent_conversations, -5) as $msg) {
            if (isset($msg['user_message'])) {
                $user_short = substr($msg['user_message'], 0, 60);
                $context .= "[Dan]: $user_short...\n";
            }
            
            if (isset($msg['misuki_response'])) {
                $misuki_short = substr($msg['misuki_response'], 0, 60);
                $context .= "[Misuki]: $misuki_short...\n";
            }
        }
        
        $last_message = end($recent_conversations);
        if ($last_message && isset($last_message['timestamp'])) {
            $last_timestamp = $last_message['timestamp'];
            $user_timestamp = date('Y-m-d H:i:s');
            
            $hours_since_last = (strtotime($user_timestamp) - strtotime($last_timestamp)) / 3600;
            $minutes_since_last = round($hours_since_last * 60);
            
            $context .= "\n=== ⚠️⚠️⚠️ CRITICAL TIME CHECK ⚠️⚠️⚠️ ===\n";
            $context .= "Dan's PREVIOUS message timestamp: {$last_timestamp}\n";
            $context .= "Dan's CURRENT message timestamp: {$user_timestamp}\n";
            
            if ($hours_since_last < 1) {
                $context .= "Time since last message: {$minutes_since_last} MINUTES\n";
            } else if ($hours_since_last < 24) {
                $context .= "Time since last message: " . round($hours_since_last, 1) . " HOURS\n";
            } else {
                $days = floor($hours_since_last / 24);
                $remaining_hours = round($hours_since_last - ($days * 24), 1);
                $context .= "Time since last message: {$days} day(s) and {$remaining_hours} hours\n";
            }
            
            $context .= "\n🚨 DO NOT MAKE UP TIME CALCULATIONS! Use ONLY the timestamps shown above!\n";
            $context .= "=================================\n";
        }
    }
    
    return $context;
}

function buildRealityContext($current_mood, $conversation_style, $active_storylines, $friends, $external_context, $family_mentioned = null) {
    $context = "\n\n=== 🌟 MISUKI'S REALITY SYSTEM ===\n";
    
    // 1. Current Mood
    if ($current_mood) {
        $context .= "\n--- YOUR CURRENT MOOD ---\n";
        $context .= "Mood: {$current_mood['current_mood']} (intensity: {$current_mood['mood_intensity']}/10)\n";
        $context .= "Reason: {$current_mood['mood_reason']}\n";
        
        $hours_ago = round((time() - strtotime($current_mood['mood_started'])) / 3600, 1);
        $context .= "This mood started: {$hours_ago} hours ago\n";
        
        $mood_guidance = [
            'excited' => "You're genuinely excited! Show energy and enthusiasm.",
            'happy' => "You're in a good mood. Be warm and positive.",
            'stressed' => "You're feeling stressed. Might be shorter, mention what's bothering you.",
            'anxious' => "You're anxious. Might seek reassurance.",
            'upset' => "Something upset you. Not hiding it completely.",
            'tired' => "You're tired. Shorter responses, maybe mention being sleepy.",
            'content' => "You're content and peaceful. Calm, warm energy.",
        ];
        
        if (isset($mood_guidance[$current_mood['current_mood']])) {
            $context .= "💭 " . $mood_guidance[$current_mood['current_mood']] . "\n";
        }
    }
    
    // 2. Active Storylines
    if (!empty($active_storylines)) {
        $context .= "\n--- ONGOING STORYLINES IN YOUR LIFE ---\n";
        foreach ($active_storylines as $story) {
            $context .= "• {$story['storyline_title']}: {$story['storyline_text']}\n";
        }
    }
    
    // 3. Friends
    if (!empty($friends)) {
        $context .= "\n--- YOUR FRIENDS ---\n";
        $friend_names = array_column($friends, 'friend_name');
        $context .= "Your friends: " . implode(', ', $friend_names) . "\n";
    }
    
    // 4. External Context
    if (!empty($external_context)) {
        $context .= "\n--- CURRENT CONTEXT ---\n";
        foreach ($external_context as $ctx) {
            $context .= "• {$ctx['context_data']}\n";
        }
    }
    
    // 5. Family context
    if ($family_mentioned) {
        $context .= "\n--- ⚠️ FAMILY MENTION DETECTED ---\n";
        $context .= "Dan mentioned: '$family_mentioned'\n";
        $context .= "YOUR family: Mom (Sara), Dad (Reo), Cat (Whiskers)\n";
        $context .= "DAN'S family: His dad, mom, sister Debby\n";
    }
    
    return $context;
}

function updateMoodFromInteraction($db, $user_id, $analysis, $user_message) {
    if ($analysis['dominant_emotion'] == 'happy' || $analysis['dominant_emotion'] == 'excited') {
        setMisukiMood($db, $user_id, 'happy', 'Dan made me smile!', 7);
    } else if ($analysis['dominant_emotion'] == 'sad') {
        setMisukiMood($db, $user_id, 'caring', 'Dan seems down, I want to help', 6);
    } else if ($analysis['dominant_emotion'] == 'anxious' || $analysis['dominant_emotion'] == 'stressed') {
        setMisukiMood($db, $user_id, 'concerned', 'Dan seems stressed', 5);
    }
}
?>