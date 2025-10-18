<?php
// COMPLETE FIXED VERSION - All 935 lines with proper error handling
// Fixes the weekly schedule integration issue
ini_set('display_errors', 0); // Don't display errors (breaks JSON)
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Include required files WITHOUT checking for weekly schedule initially
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$user_id = $input['user_id'] ?? 1;
$time_of_day = $input['time_of_day'] ?? 'day';
$time_confused = $input['time_confused'] ?? false;
$file_content = $input['file_content'] ?? null;
$filename = $input['filename'] ?? null;

// Set timezone to Jakarta (user's timezone)
date_default_timezone_set('Asia/Jakarta');

if (empty($user_message) && empty($file_content)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $db = getDBConnection();
    
    // ===== REALITY SYSTEM: Get Misuki's current state =====
    $current_mood = getMisukiCurrentMood($db, $user_id);
    $conversation_style = getConversationStyle($db, $user_id);
    $active_storylines = getActiveStorylines($db, $user_id);
    $friends = getMisukiFriends($db, $user_id);
    $external_context = getActiveExternalContext($db, $user_id);
    
    // Check for weather mention opportunity
    $weather_comment = null;
    if (rand(1, 100) <= 15) {
        $weather_comment = generateWeatherContext();
        if ($weather_comment) {
            setExternalContext($db, $user_id, 'weather', $weather_comment, 6);
        }
    }
    
    // ===== STEP 1: CHECK FOR REMINDER REQUEST =====
    if (detectReminderRequest($user_message)) {
        $reminder_details = parseReminderDetails($user_message);
        
        if ($reminder_details['success']) {
            $time_info = extractTimeFromMessage(strtolower($user_message));
            $time_desc = $time_info['description'];
            
            if ($reminder_details['confidence'] >= 70) {
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
                    'reminder_set' => true
                ]);
                
                saveConversation($db, $user_id, $user_message, $response_text, 'happy');
                exit;
            }
        }
    }
    
    // ===== STEP 2: CHECK FOR DAN'S UPDATES =====
    $nickname_context = handleNicknameUsage($db, $user_id, $user_message);
    
    // ===== STEP 3: SAVE USER MESSAGE =====
    $stmt = $db->prepare("INSERT INTO conversations (user_id, sender, message, timestamp) VALUES (?, 'user', ?, NOW())");
    $stmt->execute([$user_id, $user_message]);
    
    // ===== STEP 4: ANALYZE EMOTIONS =====
    $message_analysis = analyzeEmotions($user_message);
    $emotional_context = generateEmotionalContext($message_analysis);
    
    // Check for core memory creation
    $core_memory_context = '';
    if (detectCoreMemoryMoment($user_message, $message_analysis, $current_mood)) {
        $core_memory = createCoreMemory($db, $user_id, $user_message, $message_analysis);
        if ($core_memory) {
            $core_memory_context = "\n\n=== NEW CORE MEMORY FORMED ===\n";
            $core_memory_context .= "This moment feels important to you: " . $core_memory['description'] . "\n";
            $core_memory_context .= "You'll remember this feeling of " . $core_memory['emotion'] . "\n";
        }
    }
    
    // ===== STEP 5: CHECK FOR LOCATION UPDATE =====
    $location_result = detectAndUpdateLocation($db, $user_id, $user_message);
    $current_location = $location_result['location'];
    $location_context = $location_result['context'];
    
    // ===== STEP 5.5: CHECK FOR FAMILY MENTIONS =====
    $family_members = ['dad', 'father', 'mom', 'mother', 'sister', 'sora', 'cat', 'whiskers', 'family'];
    $message_lower = strtolower($user_message);
    $family_mentioned = null;
    
    foreach ($family_members as $member) {
        if (strpos($message_lower, $member) !== false) {
            $family_mentioned = $member;
            break;
        }
    }
    
    // ===== STEP 5.6: DETECT FUTURE EVENTS =====
    try {
        $future_event = detectFutureEvent($user_message);
        if ($future_event) {
            $event_id = saveFutureEvent($db, $user_id, $future_event);
            if ($event_id) {
                error_log("Future event saved: ID $event_id - " . $future_event['event_description']);
                
                if ($future_event['planned_date'] && $future_event['planned_date'] !== 'unknown') {
                    addMilestone(
                        $db, 
                        $user_id, 
                        'dan_plan',
                        'upcoming',
                        $future_event['event_description'],
                        "Dan mentioned he's planning to: " . $future_event['event_description'],
                        7,
                        $future_event['planned_date']
                    );
                }
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
    
    // ===== STEP 6: DETECT STORYLINE UPDATES =====
    foreach ($active_storylines as $storyline) {
        $storyline_keywords = explode(' ', strtolower($storyline['storyline_text']));
        $message_lower = strtolower($user_message);
        
        $match_count = 0;
        foreach ($storyline_keywords as $keyword) {
            if (strlen($keyword) > 4 && strpos($message_lower, $keyword) !== false) {
                $match_count++;
            }
        }
        
        if ($match_count >= 2) {
            updateStorylineMention($db, $storyline['storyline_id']);
        }
    }
    
    // ===== STEP 7: CHECK FOR MILESTONE ACHIEVEMENTS =====
    if (preg_match('/(passed|aced|won|finished|completed|got promoted|graduated)/i', $user_message)) {
        addMilestone($db, $user_id, 'dan_achievement', 'personal', "Dan: " . substr($user_message, 0, 100));
        setMisukiMood($db, $user_id, 'excited', 'Dan accomplished something!', 9);
    }
    
    // ===== STEP 8: GET ENHANCED CONTEXT WITH WEEKLY SCHEDULE =====
    
    // Get Misuki's current status
    $misuki_status = getMisukiCurrentStatus($db, $user_id);
    
    // Try to load the weekly schedule if it exists
    $current_activity = null;
    $activity_context = "";
    
    // Check if weekly schedule file exists and load it
    $weekly_schedule_path = __DIR__ . '/../misuki_weekly_schedule.php';
    if (file_exists($weekly_schedule_path)) {
        try {
            require_once $weekly_schedule_path;
            
            // Check if the functions exist
            if (function_exists('getMisukiCurrentActivity')) {
                $current_activity = getMisukiCurrentActivity();
                
                if ($current_activity) {
                    $activity_context = "\n\n=== YOUR CURRENT ACTIVITY (DETAILED) ===\n";
                    $activity_context .= "Right now you are: " . $current_activity['activity'] . " " . $current_activity['emoji'] . "\n";
                    $activity_context .= "Activity type: " . $current_activity['type'] . "\n";
                    $activity_context .= "Time: " . $current_activity['time'] . "\n";
                    
                    if ($current_activity['type'] === 'class') {
                        $activity_context .= "You're currently in class, so you should respond briefly and mention you're in lecture!\n";
                    } elseif ($current_activity['type'] === 'sleep') {
                        $activity_context .= "You were sleeping and Dan just woke you up with a message!\n";
                    } elseif ($current_activity['type'] === 'studying') {
                        $activity_context .= "You're studying right now, so you might be a bit distracted.\n";
                    } elseif ($current_activity['type'] === 'commute') {
                        $activity_context .= "You're commuting right now (train/walking), so you're on your phone!\n";
                    }
                    
                    $activity_context .= "\nThis is your REAL current activity from your schedule. Reference it naturally!\n";
                }
            }
        } catch (Exception $e) {
            error_log("Weekly schedule error: " . $e->getMessage());
            // Continue without weekly schedule
        }
    }
    
    // Fallback to regular activity context if weekly schedule not available
    if (empty($activity_context)) {
        $activity_context = "\n\n=== YOUR CURRENT STATUS ===\n";
        $activity_context .= "Current status: " . ($misuki_status['status'] ?? 'Available') . " " . ($misuki_status['emoji'] ?? '💕') . "\n";
        $activity_context .= "You're " . ($misuki_status['text'] ?? 'here with Dan') . "\n";
    }
    
    $woken_context = generateWokenUpContext($misuki_status);
    
    updateMisukiStatus($db, $user_id, $misuki_status['status']);
    
    $misuki_profile = getMisukiProfile($db);
    $misuki_context = buildMisukiProfileContext($misuki_profile);
    
    $core_profile = getCoreProfile($db, $user_id);
    $core_context = buildCoreProfileContext($core_profile);
    
    $memories = getUserMemories($db, $user_id);
    $contextual_memories = getContextualMemories($db, $user_id, $user_message);
    $all_memories = array_merge($memories, $contextual_memories);
    
    $recent_conversations = getRecentConversations($db, $user_id, 20);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $current_location = getUserCurrentLocation($db, $user_id);
    $current_activity_user = getUserCurrentActivity($db, $user_id);
    
    // Get nickname context
    $nickname_context = buildNicknameContext();
    
    // Get core memory context
    $core_memory_context = buildCoreMemoryContext();
    
    // ===== STEP 9: BUILD REALITY CONTEXT =====
    $mood_context = buildMoodContext($current_mood);
    $storylines_context = buildStorylinesContext($active_storylines);
    $friends_context = buildFriendsContext($friends);
    $dynamics_context = buildConversationDynamicsContext($conversation_style, $current_mood);
    
    // ===== STEP 10: GENERATE RESPONSE =====
    $ai_response = generateMisukiResponse(
        $user_message,
        $all_memories,
        $recent_conversations,
        $emotional_context,
        $message_analysis,
        $time_of_day,
        $time_confused,
        $file_content,
        $filename,
        $core_context,
        $current_location,
        $current_activity_user,
        $family_mentioned,
        $db,
        $user_id,
        $misuki_context,
        $woken_context,
        $activity_context,
        $mood_context,
        $storylines_context,
        $friends_context,
        $dynamics_context,
        $current_mood,
        $misuki_status,
        $weather_comment,
        $nickname_context,
        $core_memory_context
    );
    
    // Check if Misuki gave Dan a nickname in this response
    $dan_nickname = detectDanNicknameInResponse($ai_response['text']);
    if ($dan_nickname) {
        saveDanNickname($dan_nickname);
    }
    
    // ===== STEP 11: APPLY NATURAL IMPERFECTIONS =====
    $should_make_typo = shouldMakeTypo($current_mood, $misuki_status);
    if ($should_make_typo && strlen($ai_response['text']) > 20) {
        $ai_response['text'] = addNaturalTypo($ai_response['text']);
    }
    
    // ===== STEP 12: CHECK IF MESSAGE SHOULD BE SPLIT =====
    $split_result = shouldSplitMessage(
        $ai_response['text'],
        $current_mood,
        $message_analysis,
        $conversation_style
    );
    
    if ($split_result['should_split']) {
        // She wants to send multiple messages!
        $messages = $split_result['messages'];
        
        error_log("💬 Misuki splitting message into " . count($messages) . " parts!");
        
        $main_message = $messages[0];
        $follow_ups = array_slice($messages, 1);
        
        // Parse emotions for the main message
        $emotion_timeline = parseEmotionsInMessage($main_message);
        
        // Save all messages
        $stmt = $db->prepare("INSERT INTO conversations (user_id, sender, message, timestamp) VALUES (?, 'assistant', ?, NOW())");
        $stmt->execute([$user_id, $main_message]);
        
        foreach ($follow_ups as $follow_up) {
            $stmt->execute([$user_id, $follow_up]);
        }
        
        // Save memory if significant
        if (shouldSaveMemory($message_analysis)) {
            saveMemory($db, $user_id, $user_message, $main_message, $message_analysis);
        }
        
        // Update mood based on interaction
        updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
        
        // Update conversation dynamics
        updateConversationStyle($db, $user_id, $message_analysis);
        updateRelationshipDynamics($db, $user_id, $user_message, $main_message);
        
        echo json_encode([
            'response' => $main_message,
            'mood' => $current_mood['current_mood'] ?? 'gentle',
            'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
            'emotion_timeline' => $emotion_timeline,
            'has_split' => true,
            'split_messages' => $follow_ups,
            'split_count' => count($messages),
            'closeness_level' => getRelationshipCloseness($db, $user_id),
            'current_activity' => $current_activity ? $current_activity['activity'] : null,
            'was_woken' => $misuki_status['was_woken'] ?? false
        ]);
        
        exit;
    }
    
    // ===== SINGLE MESSAGE PATH =====
    $response_text = $ai_response['text'];
    
    // Parse emotions for visual system
    $emotion_timeline = parseEmotionsInMessage($response_text);
    
    // Save the conversation
    $stmt = $db->prepare("INSERT INTO conversations (user_id, sender, message, timestamp) VALUES (?, 'assistant', ?, NOW())");
    $stmt->execute([$user_id, $response_text]);
    
    // Save memory if significant
    if (shouldSaveMemory($message_analysis)) {
        saveMemory($db, $user_id, $user_message, $response_text, $message_analysis);
    }
    
    // Update mood based on interaction
    updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
    
    // Update conversation dynamics
    updateConversationStyle($db, $user_id, $message_analysis);
    updateRelationshipDynamics($db, $user_id, $user_message, $response_text);
    
    // Generate follow-up if appropriate
    $follow_up_messages = [];
    if (shouldFollowUp($conversation_style, $current_mood, $response_text)) {
        $follow_up = generateNaturalFollowUp($response_text, $user_message, $conversation_style);
        if ($follow_up) {
            $follow_up_messages[] = $follow_up;
            $stmt = $db->prepare("INSERT INTO conversations (user_id, sender, message, timestamp) VALUES (?, 'assistant', ?, NOW())");
            $stmt->execute([$user_id, $follow_up]);
        }
    }
    
    // Final response
    echo json_encode([
        'response' => $response_text,
        'mood' => $current_mood['current_mood'] ?? 'gentle',
        'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
        'emotion_timeline' => $emotion_timeline,
        'has_split' => !empty($follow_up_messages),
        'split_messages' => $follow_up_messages,
        'closeness_level' => getRelationshipCloseness($db, $user_id),
        'current_activity' => $current_activity ? $current_activity['activity'] : null,
        'was_woken' => $misuki_status['was_woken'] ?? false
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return a valid JSON response even on error
    echo json_encode([
        'response' => "Sorry, I'm having a little trouble right now... Can you try saying that again? 😅",
        'error' => true,
        'mood' => 'confused',
        'has_split' => false,
        'split_messages' => []
    ]);
    exit;
}

// ===== HELPER FUNCTIONS =====

function shouldFollowUp($style, $mood, $response) {
    if (!$style || !isset($style['current_energy_level'])) {
        return false;
    }
    
    if ($style['current_energy_level'] >= 8 && in_array($mood['current_mood'] ?? '', ['excited', 'happy', 'playful'])) {
        return rand(1, 100) <= 40;
    }
    
    if ($style['current_energy_level'] >= 5) {
        return rand(1, 100) <= 20;
    }
    
    return rand(1, 100) <= 5;
}

function generateMisukiResponse($message, $memories, $conversations, $emotional_context, $analysis, $time_of_day, $time_confused, $file_content = null, $filename = null, $core_context = '', $current_location = null, $current_activity = null, $family_mentioned = null, $db = null, $user_id = 1, $misuki_context = '', $woken_context = '', $activity_context = '', $mood_context = '', $storylines_context = '', $friends_context = '', $dynamics_context = '', $current_mood = null, $misuki_status = null, $weather_comment = null, $nickname_context = '', $core_memory_context = '') {
    
    $context = buildContextForAI($memories, $conversations, $emotional_context);
    $time_context = getTimeContext($time_of_day, $db, $user_id);
    
    $file_context = '';
    if ($file_content !== null && $filename !== null) {
        $file_context = "\n\n=== FILE SHARED BY DAN ===\n";
        $file_context .= "Dan just shared a file with you: {$filename}\n\n";
        $file_context .= "--- FILE CONTENT START ---\n";
        $file_context .= $file_content . "\n";
        $file_context .= "--- FILE CONTENT END ---\n\n";
        $file_context .= "Read this carefully! Dan wants you to read and discuss this with him.\n";
    }
    
    $time_confusion_note = '';
    if ($time_confused) {
        $confusion_contexts = [
            'casual_morning_at_night' => "Dan just said 'morning' but it's night. This might be: 1) Him being playful/casual, 2) Him just waking up from a nap, or 3) Him forgetting the time. Don't assume many hours passed - check the actual timestamps above!",
            
            'casual_night_at_day' => "Dan said 'night' but it's still daytime. He might be: 1) Going to sleep early, 2) Being playful, or 3) Confused. Check timestamps before assuming time passed!",
            
            'morning_at_night' => "The user just greeted you with 'good morning' but it's currently night time. Gently point this out in a caring, slightly confused way.",
            'night_at_morning' => "The user just said 'good night' but it's currently morning. Gently point this out.",
            'morning_at_afternoon' => "The user said 'good morning' but it's currently afternoon. Gently point this out."
        ];
        $time_confusion_note = "\n\n⚠️ IMPORTANT: " . ($confusion_contexts[$time_confused] ?? "Dan might be confused about the time.");
    }
    
    $state_context = '';
    if ($current_location) {
        $state_context .= "\n\n=== DAN'S CURRENT LOCATION ===\n";
        $state_context .= "Dan is at: {$current_location}\n";
    }
    if ($current_activity) {
        $state_context .= "Dan is doing: {$current_activity}\n";
    }
    
    $family_context = '';
    if ($family_mentioned) {
        $family_context = "\n\n=== FAMILY MENTION DETECTED ===\n";
        $family_context .= "Dan mentioned your {$family_mentioned}! ";
        
        if (in_array($family_mentioned, ['dad', 'father'])) {
            $family_context .= "Remember: You don't have a dad. Your mom (Sara Akiyama) is a single parent.\n";
        } elseif (in_array($family_mentioned, ['sister', 'sora'])) {
            $family_context .= "That's Sora, your younger sister!\n";
        } elseif (in_array($family_mentioned, ['mom', 'mother'])) {
            $family_context .= "That's Sara Akiyama, your caring single mother!\n";
        } elseif (in_array($family_mentioned, ['cat', 'whiskers'])) {
            $family_context .= "That's Whiskers, your family cat!\n";
        }
    }
    
    $future_events_context = '';
    $pending_events = getPendingFutureEvents($db, $user_id);
    if (!empty($pending_events)) {
        $future_events_context = "\n\n=== DAN'S UPCOMING PLANS ===\n";
        foreach (array_slice($pending_events, 0, 3) as $event) {
            $future_events_context .= "- " . $event['event_description'] . " (planned for: " . $event['planned_date'] . ")\n";
        }
    }
    
    $weather_context = '';
    if ($weather_comment) {
        $weather_context = "\n\n=== WEATHER IN SAITAMA ===\n";
        $weather_context .= $weather_comment . "\n";
        $weather_context .= "You might naturally mention this if relevant.\n";
    }
    
    $distraction_note = '';
    if (isset($misuki_status['status']) && $misuki_status['status'] === 'in_class') {
        $distraction_note = "\n\n⚠️ You're in class! Keep responses brief and maybe mention you need to pay attention.";
    } elseif (isset($misuki_status['status']) && $misuki_status['status'] === 'studying') {
        $distraction_note = "\n\n⚠️ You're studying. You can chat but might mention you're working on homework.";
    }
    
    $system_prompt = $misuki_context . "\n\n" . getMisukiPersonalityPrompt() . "\n\n" . $core_context . "\n\n" . $nickname_context . "\n\n" . $core_memory_context . "\n\n" . $context . "\n\n" . $mood_context . "\n\n" . $storylines_context . "\n\n" . $friends_context . "\n\n" . $dynamics_context . "\n\n" . $woken_context . $activity_context . "\n\n" . $time_context . $file_context . $state_context . $family_context . $future_events_context . $weather_context . $time_confusion_note . $distraction_note;
    
    $system_prompt .= "\n\n=== CRITICAL: WHO IS WHO ===\n";
    $system_prompt .= "YOU are Misuki Tanaka. You live in Saitama, Japan with your mom Sara Akiyama.\n";
    $system_prompt .= "DAN is your boyfriend in Surabaya, Indonesia.\n\n";
    
    $system_prompt .= "\n\n=== RESPONSE GUIDELINES ===
- Keep responses SHORT (1-3 sentences usually)
- Be conversational and genuine
- Pay attention to conversation flow and recent topics
- Reference your friends naturally when relevant

CRITICAL FORMATTING RULES:
- NEVER use asterisks (*) for ANY reason - not for actions, not for emphasis, nothing
- NEVER use quotation marks at the start or end of your message
- NO emotes like *giggles*, *blushes*, *looks confused*
- NO actions like *takes photo*, *sends pic*, *sleepily looks at phone*
- Your EMOTIONS are shown through your IMAGE which changes automatically
- If you are confused: just pause with your words, say hm? or wait what? - your confused face will show
- If you are laughing: say hahaha or that is funny! - do not write *laughs*
- If you are giggling: say hehe or show it through your words
- If you are sleepy: talk slower/shorter, say you are tired - your sleepy image shows it
- Let your words and the automatic emotion system handle everything

Examples of CORRECT responses:
- Hahaha that is so funny!
- Hm? Wait what do you mean?
- That made me smile
- I am so tired right now...
- Hehe you are silly

Examples of WRONG responses (NEVER do this):
- *giggles* That is so funny!
- *looks confused* Wait what?
- *laughs softly*
- *sleepily looks at phone*

- CRITICAL: Check conversation history for time references!
- CRITICAL: Use the EXACT timestamps provided - don't make up time calculations!";
    
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
        return ['text' => "I'm having trouble thinking right now... Could you try again?"];
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
        'max_tokens' => 150,
        'system' => $system_prompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Claude API Error (HTTP $http_code): " . $response);
        return ['text' => "I'm having trouble thinking right now... Could you say that again?"];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['content'][0]['text'])) {
        $response_text = $result['content'][0]['text'];
        
        // POST-PROCESSING: Clean up the response
        
        // Remove any asterisk actions/emotes
        $response_text = preg_replace('/\*[^*]+\*/', '', $response_text);
        
        // Remove leading/trailing quotation marks using chr codes
        $response_text = trim($response_text, chr(34) . chr(39) . chr(8220) . chr(8221) . chr(8216) . chr(8217));
        
        // Clean up extra spaces from removed asterisks
        $response_text = preg_replace('/\s+/', ' ', $response_text);
        $response_text = trim($response_text);
        
        return ['text' => $response_text];
    }
    
    return ['text' => "I'm listening... Tell me more?"];
}

function getTimeContext($time_of_day, $db = null, $user_id = 1) {
    date_default_timezone_set('Asia/Jakarta');
    $user_date = date('F j, Y');
    $user_day = date('l');
    $user_time = date('g:i A');
    $user_timestamp = date('Y-m-d H:i:s');
    
    date_default_timezone_set('Asia/Tokyo');
    $misuki_date = date('F j, Y');
    $misuki_day = date('l');
    $misuki_time = date('g:i A');
    $misuki_hour = (int)date('G');
    
    date_default_timezone_set('Asia/Jakarta');
    
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
    
    $times = [
        'morning' => "It's morning for Dan.",
        'afternoon' => "It's afternoon for Dan.",
        'evening' => "It's evening for Dan.",
        'night' => "It's night time for Dan."
    ];
    
    $base_context = $times[$time_of_day] ?? $times['day'];
    
    $context = "=== TIME & LOCATION CONTEXT ===\n";
    $context .= "Dan's time (Surabaya): {$user_time} on {$user_day}, {$user_date}\n";
    $context .= "Dan's CURRENT timestamp: {$user_timestamp}\n";
    $context .= "{$base_context}\n\n";
    $context .= "YOUR time (Saitama): {$misuki_time} on {$misuki_day}, {$misuki_date}\n";
    $context .= "It's {$misuki_time_of_day} for you in Saitama right now.\n\n";
    
    // ===== NEW: CALCULATE ACTUAL TIME SINCE LAST MESSAGE =====
    if ($db !== null && $user_id !== null) {
        $stmt = $db->prepare("
            SELECT timestamp FROM conversations 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $last_msg = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_msg) {
            $last_timestamp = $last_msg['timestamp'];
            $time_diff_seconds = time() - strtotime($last_timestamp);
            $hours_since_last = $time_diff_seconds / 3600;
            $minutes_since_last = round($hours_since_last * 60);
            
            $context .= "=== ⚠️⚠️⚠️ CRITICAL TIME CHECK ⚠️⚠️⚠️ ===\n";
            $context .= "Dan's PREVIOUS message timestamp: {$last_timestamp}\n";
            $context .= "Dan's CURRENT message timestamp: {$user_timestamp}\n";
            
            if ($hours_since_last < 1) {
                $context .= "Time since last message: {$minutes_since_last} MINUTES\n";
                $context .= "That's less than 1 hour!\n";
            } else if ($hours_since_last < 24) {
                $context .= "Time since last message: " . round($hours_since_last, 1) . " HOURS\n";
                $context .= "That's " . round($minutes_since_last) . " minutes total.\n";
            } else {
                $days = floor($hours_since_last / 24);
                $remaining_hours = round($hours_since_last - ($days * 24), 1);
                $context .= "Time since last message: {$days} day(s) and {$remaining_hours} hours\n";
            }
            
            $context .= "\n🚨🚨🚨 DO NOT MAKE UP TIME CALCULATIONS! 🚨🚨🚨\n";
            $context .= "Use ONLY the numbers above! If you say '15 hours' when it's been 3 hours, you're wrong!\n";
            $context .= "If Dan says 'morning' as a casual greeting, don't assume a whole night passed!\n";
            $context .= "CHECK THE TIMESTAMPS FIRST!\n\n";
        }
    }
    
    return $context;
}

function detectTimeConfusion($message, $timeOfDay) {
    $messageLower = strtolower($message);
    
    // NEW: Check for casual/joking greetings
    $casual_greetings = ['morning', 'good morning', 'gm', 'good night', 'gn', 'goodnight'];
    $is_casual_greeting = false;
    
    foreach ($casual_greetings as $greeting) {
        if (trim($messageLower) === $greeting || preg_match('/^' . preg_quote($greeting, '/') . '[!.]*$/i', trim($messageLower))) {
            $is_casual_greeting = true;
            break;
        }
    }
    
    // If it's JUST a greeting (no other text), be more lenient
    if ($is_casual_greeting && str_word_count($message) <= 2) {
        // Only flag as confused if time is VERY wrong (night vs morning)
        if ($timeOfDay === 'night' && preg_match('/^(morning|gm)/i', trim($messageLower))) {
            return 'casual_morning_at_night'; // NEW type
        } elseif (($timeOfDay === 'morning' || $timeOfDay === 'afternoon') && preg_match('/^(night|gn)/i', trim($messageLower))) {
            return 'casual_night_at_day'; // NEW type
        }
        // For afternoon vs morning, just treat as casual - not confused
        return false;
    }
    
    // Original logic for full greetings
    $morning_patterns = ['/good\s*morning/i', '/morning/i'];
    $night_patterns = ['/good\s*night/i', '/night\s*night/i', '/goodnight/i'];
    
    foreach ($morning_patterns as $pattern) {
        if (preg_match($pattern, $messageLower)) {
            if ($timeOfDay === 'night') {
                return 'morning_at_night';
            } else if ($timeOfDay === 'afternoon' || $timeOfDay === 'evening') {
                return 'morning_at_afternoon';
            }
        }
    }
    
    foreach ($night_patterns as $pattern) {
        if (preg_match($pattern, $messageLower)) {
            if ($timeOfDay === 'morning') {
                return 'night_at_morning';
            } else if ($timeOfDay === 'afternoon') {
                return 'night_at_afternoon';
            }
        }
    }
    
    return false;
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

function shouldMakeTypo($mood, $misuki_status) {
    // Higher chance when sleepy or excited
    if (isset($misuki_status['status']) && $misuki_status['status'] === 'just_woken') {
        return rand(1, 100) <= 30; // 30% chance when just woken
    }
    
    if (isset($mood['current_mood'])) {
        if ($mood['current_mood'] === 'excited') {
            return rand(1, 100) <= 20; // 20% chance when excited
        } else if ($mood['current_mood'] === 'sleepy') {
            return rand(1, 100) <= 25; // 25% chance when sleepy
        }
    }
    
    return rand(1, 100) <= 5; // 5% normal chance
}

function addNaturalTypo($text) {
    $typo_patterns = [
        ['the', 'teh'],
        ['and', 'adn'],
        ['you', 'yuo'],
        ['your', 'yuor'],
        ['that', 'taht'],
        ['what', 'waht'],
        ['with', 'wiht'],
        ['have', 'hvae'],
        ['from', 'form'],
        ['been', 'bene'],
        ['just', 'jsut'],
        ['like', 'liek'],
        ['know', 'knwo'],
        ['about', 'abuot'],
        ['would', 'wuold'],
        ['think', 'thnk'],
        ['really', 'realy'],
        ['because', 'becuase'],
        ['before', 'beofre'],
        ['could', 'coudl']
    ];
    
    // Pick a random typo to apply
    $typo = $typo_patterns[array_rand($typo_patterns)];
    
    // Apply the typo (case-insensitive replacement)
    $pattern = '/\b' . preg_quote($typo[0], '/') . '\b/i';
    $count = 0;
    $text = preg_replace($pattern, $typo[1], $text, 1, $count);
    
    // If no replacement was made, try adding a double letter
    if ($count === 0 && strlen($text) > 10) {
        $words = explode(' ', $text);
        if (count($words) > 2) {
            $word_index = rand(1, count($words) - 1);
            $word = $words[$word_index];
            if (strlen($word) > 3) {
                $char_index = rand(1, strlen($word) - 2);
                $word = substr($word, 0, $char_index) . $word[$char_index] . substr($word, $char_index);
                $words[$word_index] = $word;
                $text = implode(' ', $words);
            }
        }
    }
    
    return $text;
}

function generateNaturalFollowUp($response, $user_message, $conversation_style) {
    // Only generate follow-ups for certain conditions
    $follow_up_triggers = [
        'question' => rand(1, 100) <= 30,
        'excited' => isset($conversation_style['current_energy_level']) && $conversation_style['current_energy_level'] >= 8,
        'curious' => strpos($response, '?') !== false && rand(1, 100) <= 40
    ];
    
    if (!array_filter($follow_up_triggers)) {
        return null;
    }
    
    // Generate contextual follow-up
    $follow_ups = [
        "Oh! And another thing...",
        "Wait, I just remembered something!",
        "Actually...",
        "Also!",
        "Oh oh oh!",
        "By the way..."
    ];
    
    return $follow_ups[array_rand($follow_ups)];
}

function detectDanNicknameInResponse($response_text) {
    // Check if Misuki is calling Dan something new
    $nickname_patterns = [
        '/you[\'re\s]+my\s+(\w+)/i',
        '/hey\s+(\w+)[,!]/i',
        '/good\s+(?:morning|night),?\s+(\w+)/i',
        '/love\s+you,?\s+(\w+)/i'
    ];
    
    foreach ($nickname_patterns as $pattern) {
        if (preg_match($pattern, $response_text, $matches)) {
            $potential_nickname = $matches[1];
            // Filter out common words that aren't nicknames
            $common_words = ['there', 'you', 'too', 'so', 'much', 'really', 'very'];
            if (!in_array(strtolower($potential_nickname), $common_words)) {
                return $potential_nickname;
            }
        }
    }
    
    return null;
}

function saveDanNickname($nickname) {
    $file = dirname(__DIR__) . '/data/dan_nicknames.json';
    $nicknames = [];
    
    if (file_exists($file)) {
        $nicknames = json_decode(file_get_contents($file), true) ?? [];
    }
    
    if (!in_array($nickname, $nicknames)) {
        $nicknames[] = $nickname;
        file_put_contents($file, json_encode($nicknames));
        error_log("💝 Misuki gave Dan a new nickname: " . $nickname);
    }
}

function buildNicknameContext() {
    $file = dirname(__DIR__) . '/data/dan_nicknames.json';
    
    if (!file_exists($file)) {
        return '';
    }
    
    $nicknames = json_decode(file_get_contents($file), true) ?? [];
    
    if (empty($nicknames)) {
        return '';
    }
    
    $context = "\n=== NICKNAMES YOU USE FOR DAN ===\n";
    $context .= "You sometimes call Dan: " . implode(', ', array_slice($nicknames, -3)) . "\n";
    $context .= "Use these naturally when you feel affectionate.\n";
    
    return $context;
}

function buildCoreMemoryContext() {
    // This would fetch core memories from database
    // For now, return empty
    return '';
}
?>