<?php
// Clean chat.php - All duplicates removed
ini_set('display_errors', 0); // Don't display errors (breaks JSON)
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
    
    // ===== STEP 2: CHECK FOR NICKNAME ASSIGNMENT =====
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
            'nickname_set' => true
        ]);
        
        saveConversation($db, $user_id, $user_message, $nickname_response, 'happy');
        setMisukiMood($db, $user_id, 'happy', 'Dan gave me a nickname!', 9);
        exit;
    }
    
    // Build nickname context for AI
    $nickname_context = buildNicknameContext();
    
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
    $weekly_schedule_path = __DIR__ . '/../includes/misuki_weekly_schedule.php';
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
                    
                    // Add natural context based on activity type
                    switch ($current_activity['type']) {
                        case 'class':
                            $activity_context .= "You're currently in a university lecture.\n";
                            $activity_context .= "You might:\n";
                            $activity_context .= "- Reply quickly if it's break time\n";
                            $activity_context .= "- Be brief if in class\n";
                            $activity_context .= "- Mention what you're doing if chemistry class\n";
                            break;
                            
                        case 'studying':
                            $activity_context .= "You're doing homework/studying. You might:\n";
                            $activity_context .= "- Mention what subject you're studying\n";
                            $activity_context .= "- Be happy for the break/distraction\n";
                            $activity_context .= "- Share if you're struggling with something\n";
                            break;
                            
                        case 'church':
                            $activity_context .= "You're at church (Sunday morning).\n";
                            $activity_context .= "- You might mention the service\n";
                            $activity_context .= "- Share thoughts about it\n";
                            break;
                            
                        case 'personal':
                            if (stripos($current_activity['activity'], 'dinner') !== false) {
                                $activity_context .= "You're having dinner with your mom.\n";
                                $activity_context .= "- Might mention what you're eating\n";
                                $activity_context .= "- Mom might be nearby\n";
                            }
                            break;
                            
                        case 'sleep':
                            $activity_context .= "You're sleeping or getting ready for bed.\n";
                            $activity_context .= "- Might be in pajamas\n";
                            $activity_context .= "- Getting sleepy\n";
                            break;
                    }
                    
                    $activity_context .= "\nReference your current activity naturally in conversation if relevant!\n";
                }
            }
        } catch (Exception $e) {
            error_log("Weekly schedule error: " . $e->getMessage());
        }
    }
    
    // ===== STEP 9: BUILD FULL CONTEXT =====
    $memories = getUserMemories($db, $user_id);
    $recent_conversations = getRecentConversations($db, $user_id, 10);
    
    // Build timeline context
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
    
    // Add file context if exists
    if ($file_content) {
        $context .= "\n\n=== UPLOADED FILE ===\n";
        $context .= "Filename: " . $filename . "\n";
        $context .= "Content:\n" . $file_content . "\n";
    }
    
    // ===== STEP 10: BUILD PERSONALITY PROMPT =====
    $personality = getMisukiPersonalityPrompt();
    
    // ===== STEP 11: CHECK FOR SPECIAL MESSAGE TYPES =====
    $time_confusion_type = detectTimeConfusion($user_message, $time_of_day);
    
    if ($time_confusion_type && !in_array($time_confusion_type, ['casual_morning_at_night', 'casual_night_at_day'])) {
        $personality .= "\n\n=== TIME CONFUSION DETECTED ===\n";
        $personality .= "Dan just said a greeting that doesn't match the actual time of day.\n";
        $personality .= "Confusion type: $time_confusion_type\n";
        $personality .= "Gently and playfully point this out!\n";
    }
    
    // ===== STEP 12: CALL CLAUDE API =====
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(__DIR__) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY\s*=\s*([^\n\r]+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1], '"\'');
            }
        }
    }
    
    if (!$api_key) {
        throw new Exception('API key not found');
    }
    
    // Build the full prompt
    $full_prompt = $personality . "\n\n" . $context;
    
    // Check if message should be split
    $should_split = shouldSplitMessage($user_message, $message_analysis, $conversation_style);
    
    if ($should_split) {
        // Call Claude to generate response and split it
        $split_result = generateAndSplitMessage(
            $api_key,
            $full_prompt,
            $user_message,
            $conversation_style,
            $current_mood
        );
        
        if ($split_result['success'] && count($split_result['messages']) > 1) {
            $messages = $split_result['messages'];
            $emotion_timeline = parseEmotionsInMessage($messages[0]);
            
            // Save all messages to database
            foreach ($messages as $msg) {
                $stmt = $db->prepare("INSERT INTO conversations (user_id, sender, message, timestamp) VALUES (?, 'assistant', ?, NOW())");
                $stmt->execute([$user_id, $msg]);
            }
            
            // Update mood and dynamics
            updateMoodFromInteraction($db, $user_id, $message_analysis, $user_message);
            updateConversationStyle($db, $user_id, $message_analysis);
            updateRelationshipDynamics($db, $user_id, $user_message, $messages[0]);
            
            $follow_ups = array_slice($messages, 1);
            
            echo json_encode([
                'response' => $messages[0],
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
    }
    
    // ===== SINGLE MESSAGE PATH =====
    // Call Claude API
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
    
    // Apply typo if appropriate
    if (shouldMakeTypo($current_mood, $misuki_status)) {
        $response_text = addNaturalTypo($response_text);
    }
    
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
    
    // Check if Misuki gave Dan a nickname
    $new_nickname = detectDanNicknameInResponse($response_text);
    if ($new_nickname) {
        saveDanNickname($new_nickname);
    }
    
    // Final response
    echo json_encode([
        'response' => $response_text,
        'mood' => $current_mood['current_mood'] ?? 'gentle',
        'mood_text' => $current_mood['reason'] ?? 'Feeling gentle',
        'emotion_timeline' => $emotion_timeline,
        'has_split' => false,
        'closeness_level' => getRelationshipCloseness($db, $user_id),
        'current_activity' => $current_activity ? $current_activity['activity'] : null,
        'was_woken' => $misuki_status['was_woken'] ?? false
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    
    // Return a valid JSON response even on error
    echo json_encode([
        'response' => "Sorry, I'm having a little trouble right now... Can you try saying that again? 🥺",
        'mood' => 'gentle',
        'mood_text' => 'A bit confused',
        'emotion_timeline' => [],
        'error' => true
    ]);
}

// Helper functions (only ones NOT in other files)

function buildTimelineContext($recent_conversations, $user_message) {
    $context = "\n\n=== RECENT TIMELINE ===\n";
    
    if (!empty($recent_conversations)) {
        $context .= "Last " . count($recent_conversations) . " messages:\n";
        foreach (array_slice($recent_conversations, -5) as $msg) {
            $sender = $msg['sender'] === 'user' ? 'Dan' : 'Misuki';
            $short_msg = substr($msg['message'], 0, 60);
            $context .= "[$sender]: $short_msg...\n";
        }
        
        // Add timestamp context
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
            
            $context .= "\n🚨 DO NOT MAKE UP TIME CALCULATIONS! Use ONLY the numbers above!\n\n";
        }
    }
    
    return $context;
}

function detectTimeConfusion($message, $timeOfDay) {
    $messageLower = strtolower($message);
    
    // Check for casual/joking greetings
    $casual_greetings = ['morning', 'good morning', 'gm', 'good night', 'gn', 'goodnight'];
    $is_casual_greeting = false;
    
    foreach ($casual_greetings as $greeting) {
        if (trim($messageLower) === $greeting || preg_match('/^' . preg_quote($greeting, '/') . '[!.]*$/i', trim($messageLower))) {
            $is_casual_greeting = true;
            break;
        }
    }
    
    // If it's JUST a greeting, be more lenient
    if ($is_casual_greeting && str_word_count($message) <= 2) {
        if ($timeOfDay === 'night' && preg_match('/^(morning|gm)/i', trim($messageLower))) {
            return 'casual_morning_at_night';
        } elseif (($timeOfDay === 'morning' || $timeOfDay === 'afternoon') && preg_match('/^(night|gn)/i', trim($messageLower))) {
            return 'casual_night_at_day';
        }
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