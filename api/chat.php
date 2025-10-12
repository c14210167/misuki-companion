<?php
header('Content-Type: application/json');
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
                setMisukiMood($db, $user_id, 'happy', 'Helping Dan with a reminder', 8);
                
                exit;
            } else {
                $confirmation = generateReminderConfirmation($reminder_details, $time_desc);
                setConversationState($db, $user_id, 'pending_reminder', json_encode($reminder_details), 1);
                
                $emotion_timeline = parseEmotionsInMessage($confirmation);
                
                echo json_encode([
                    'response' => $confirmation,
                    'mood' => 'thoughtful',
                    'mood_text' => 'Checking',
                    'emotion_timeline' => $emotion_timeline,
                    'awaiting_confirmation' => true
                ]);
                
                saveConversation($db, $user_id, $user_message, $confirmation, 'thoughtful');
                exit;
            }
        }
    }
    
    // ===== STEP 2: CHECK FOR REMINDER CONFIRMATION =====
    $pending_reminder = getConversationState($db, $user_id, 'pending_reminder');
    if ($pending_reminder && preg_match('/^(yes|yeah|yep|sure|okay|ok|correct|right)\b/i', $user_message)) {
        $reminder_details = json_decode($pending_reminder, true);
        
        saveReminder(
            $db, 
            $user_id, 
            $reminder_details['reminder_text'], 
            $reminder_details['remind_at'],
            $reminder_details['confidence'],
            ''
        );
        
        clearConversationState($db, $user_id, 'pending_reminder');
        
        $response_text = "Perfect! Reminder set! 💕";
        $emotion_timeline = parseEmotionsInMessage($response_text);
        
        echo json_encode([
            'response' => $response_text,
            'mood' => 'happy',
            'mood_text' => 'Happy',
            'emotion_timeline' => $emotion_timeline
        ]);
        
        saveConversation($db, $user_id, $user_message, $response_text, 'happy');
        exit;
    }
    
    // ===== STEP 3: DETECT LOCATION/ACTIVITY MENTIONS =====
    $location = detectLocationMention($user_message);
    if ($location) {
        trackUserLocation($db, $user_id, $location);
    }
    
    $activity = detectActivityMention($user_message);
    if ($activity) {
        trackUserActivity($db, $user_id, $activity);
    }
    
    $family_mentioned = detectFamilyMention($user_message);
    
    // ===== STEP 4: DETECT MOOD CHANGES IN DAN'S MESSAGE =====
    $message_analysis = analyzeMessage($user_message);
    
    // Update Misuki's mood based on Dan's emotional state
    if ($message_analysis['emotion'] == 'negative' && $message_analysis['negative_intensity'] > 5) {
        setMisukiMood($db, $user_id, 'concerned', 'Dan seems upset or stressed', 7);
        updateConversationEnergy($db, $user_id, -2);
    } elseif ($message_analysis['emotion'] == 'positive' && $message_analysis['positive_intensity'] > 3) {
        setMisukiMood($db, $user_id, 'happy', 'Dan seems happy!', 8);
        updateConversationEnergy($db, $user_id, +1);
    }
    
    // ===== STEP 5: DETECT AND TRACK FUTURE EVENTS =====
    try {
        if (function_exists('detectFutureEvent')) {
            $future_event = detectFutureEvent($user_message);
            if ($future_event['has_future_event']) {
                saveFutureEvent($db, $user_id, $future_event['event_description'], $future_event['planned_date'], $future_event['planned_time']);
                error_log("🎯 Tracked future event: {$future_event['event_description']} on {$future_event['planned_date']}");
                
                if (preg_match('/(trip|visit|important|exam|test|interview)/i', $future_event['event_description'])) {
                    createStoryline(
                        $db, 
                        $user_id, 
                        'future_plan', 
                        "Dan's upcoming: " . $future_event['event_description'],
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
    
    // ===== STEP 8: GET ENHANCED CONTEXT =====
    
    $misuki_status = getMisukiCurrentStatus($db, $user_id);
    $woken_context = generateWokenUpContext($misuki_status);
    $activity_context = generateActivityContext($misuki_status);
    
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
    $current_activity = getUserCurrentActivity($db, $user_id);
    
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
        $current_activity,
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
        $weather_comment
    );
    
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
        
        error_log("💬 Misuki splitting message into " . count($messages) . " parts");
        
        // Parse emotions for each message separately
        $emotion_timelines = [];
        foreach ($messages as $msg) {
            $emotion_timelines[] = parseEmotionsInMessage($msg);
        }
        
        // Determine overall mood
        $mood = determineMood($message_analysis, ['text' => $messages[0]], $time_confused);
        
        // Save to database
        $full_response = implode("\n[SPLIT]\n", $messages);
        $save_result = saveConversation($db, $user_id, $user_message, $full_response, $mood['mood']);
        
        if (!$save_result) {
            error_log("ERROR: Failed to save split conversation! user_id=$user_id");
        }
        
        // Update memories and tracking
        updateMemories($db, $user_id, $message_analysis);
        trackEmotionalState($db, $user_id, $message_analysis['emotion']);
        
        $stmt = $db->prepare("
            INSERT INTO conversation_initiation (user_id, last_user_message, total_messages) 
            VALUES (?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
                last_user_message = NOW(),
                total_messages = total_messages + 1
        ");
        $stmt->execute([$user_id]);
        
        if (!empty($message_analysis['topics'])) {
            foreach ($message_analysis['topics'] as $topic) {
                $sentiment = $message_analysis['emotion'] == 'negative' ? 'negative' : 
                            ($message_analysis['emotion'] == 'positive' ? 'positive' : 'neutral');
                trackDiscussionTopic($db, $user_id, $topic, $sentiment);
                trackTopicDiscussed($db, $user_id, $topic);
                addRecentTopic($db, $user_id, $topic);
            }
        }
        
        // Return split messages
        echo json_encode([
            'response' => $messages[0],
            'additional_messages' => array_slice($messages, 1),
            'emotion_timelines' => $emotion_timelines,
            'mood' => $mood['mood'],
            'mood_text' => $mood['text'],
            'is_split' => true,
            'num_parts' => count($messages),
            'should_follow_up' => false
        ]);
        
    } else {
        // Normal single message
        
        $emotion_timeline = parseEmotionsInMessage($ai_response['text']);
        $mood = determineMood($message_analysis, $ai_response, $time_confused);
        
        $save_result = saveConversation($db, $user_id, $user_message, $ai_response['text'], $mood['mood']);
        if (!$save_result) {
            error_log("ERROR: Failed to save conversation! user_id=$user_id");
        } else {
            error_log("SUCCESS: Saved conversation at " . date('Y-m-d H:i:s'));
        }
        
        updateMemories($db, $user_id, $message_analysis);
        trackEmotionalState($db, $user_id, $message_analysis['emotion']);
        
        $stmt = $db->prepare("
            INSERT INTO conversation_initiation (user_id, last_user_message, total_messages) 
            VALUES (?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
                last_user_message = NOW(),
                total_messages = total_messages + 1
        ");
        $stmt->execute([$user_id]);
        
        if (!empty($message_analysis['topics'])) {
            foreach ($message_analysis['topics'] as $topic) {
                $sentiment = $message_analysis['emotion'] == 'negative' ? 'negative' : 
                            ($message_analysis['emotion'] == 'positive' ? 'positive' : 'neutral');
                trackDiscussionTopic($db, $user_id, $topic, $sentiment);
                trackTopicDiscussed($db, $user_id, $topic);
                addRecentTopic($db, $user_id, $topic);
            }
        }
        
        echo json_encode([
            'response' => $ai_response['text'],
            'mood' => $mood['mood'],
            'mood_text' => $mood['text'],
            'emotion_timeline' => $emotion_timeline,
            'is_split' => false,
            'should_follow_up' => shouldFollowUp($conversation_style, $current_mood, $ai_response)
        ]);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'response' => "I'm so sorry, I'm having a moment of confusion. Could you say that again?",
        'mood' => 'concerned',
        'mood_text' => 'Concerned',
        'emotion_timeline' => [],
        'is_split' => false
    ]);
}

function shouldFollowUp($style, $mood, $response) {
    if ($style['current_energy_level'] >= 8 && in_array($mood['current_mood'], ['excited', 'happy', 'playful'])) {
        return rand(1, 100) <= 40;
    }
    
    if ($style['current_energy_level'] >= 5) {
        return rand(1, 100) <= 20;
    }
    
    return rand(1, 100) <= 5;
}

function generateMisukiResponse($message, $memories, $conversations, $emotional_context, $analysis, $time_of_day, $time_confused, $file_content = null, $filename = null, $core_context = '', $current_location = null, $current_activity = null, $family_mentioned = null, $db = null, $user_id = 1, $misuki_context = '', $woken_context = '', $activity_context = '', $mood_context = '', $storylines_context = '', $friends_context = '', $dynamics_context = '', $current_mood = null, $misuki_status = null, $weather_comment = null) {
    
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
        $time_confusion_note = "\n\n⚠️ IMPORTANT: " . ($confusion_contexts[$time_confused] ?? '');
    }
    
    $state_context = '';
    if ($current_location || $current_activity) {
        $state_context = "\n\n=== DAN'S CURRENT STATE ===\n";
        if ($current_location) {
            $state_context .= "Dan is currently: $current_location\n";
        }
        if ($current_activity) {
            $state_context .= "Dan is currently: $current_activity\n";
        }
    }
    
    $family_context = '';
    if ($family_mentioned) {
        $family_context = "\n\nNOTE: Dan just mentioned: $family_mentioned\n";
    }
    
    $future_events_context = '';
    if ($db && function_exists('getPendingFutureEvents')) {
        try {
            $pending_events = getPendingFutureEvents($db, $user_id);
            $overdue_events = getOverdueFutureEvents($db, $user_id);
            $future_events_context = buildFutureEventsContext($pending_events, $overdue_events);
        } catch (Exception $e) {
            error_log("Error getting future events: " . $e->getMessage());
        }
    }
    
    $weather_context = '';
    if ($weather_comment) {
        $weather_context = "\n\n=== WEATHER/ENVIRONMENT ===\n";
        $weather_context .= "$weather_comment\n";
        $weather_context .= "You can naturally mention this if it fits the conversation!\n";
    }
    
    $distraction_note = '';
    if (shouldBeDistracted($current_mood, $misuki_status)) {
        $distraction_note = "\n\n=== NATURAL CONVERSATION FLOW ===\n";
        $distraction_note .= getAsymmetricFocusNote() . "\n";
    }
    
    $system_prompt = $misuki_context . "\n\n" . getMisukiPersonalityPrompt() . "\n\n" . $core_context . "\n\n" . $context . "\n\n" . $mood_context . "\n\n" . $storylines_context . "\n\n" . $friends_context . "\n\n" . $dynamics_context . "\n\n" . $woken_context . $activity_context . "\n\n" . $time_context . $file_context . $state_context . $family_context . $future_events_context . $weather_context . $time_confusion_note . $distraction_note;
    
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
        return ['text' => "I'm having trouble thinking right now..."];
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
    $currentGreetingPatterns = [
        '/^good morning/i',
        '/^morning[!.]/i',
        '/^good night[!.]/i',
        '/^goodnight[!.]/i',
        '/^good evening/i',
        '/^evening[!.]/i',
        '/^good afternoon/i'
    ];
    
    $isCurrentGreeting = false;
    for ($i = 0; $i < count($currentGreetingPatterns); $i++) {
        if (preg_match($currentGreetingPatterns[$i], trim($message))) {
            $isCurrentGreeting = true;
            break;
        }
    }
    
    if (!$isCurrentGreeting) return false;
    
    if ($timeOfDay === 'night' && preg_match('/^good morning|^morning/i', trim($message))) {
        return 'morning_at_night';
    } else if ($timeOfDay === 'morning' && preg_match('/^good night|^goodnight/i', trim($message))) {
        return 'night_at_morning';
    } else if ($timeOfDay === 'afternoon' && preg_match('/^good morning/i', trim($message))) {
        return 'morning_at_afternoon';
    }
    
    return false;
}

function determineMood($message_analysis, $response, $time_confused) {
    $moods = [
        'neutral' => 'Listening',
        'happy' => 'Happy for you',
        'concerned' => 'Concerned',
        'thoughtful' => 'Thinking',
        'gentle' => 'Being gentle'
    ];
    
    if ($time_confused) {
        return ['mood' => 'confused', 'text' => 'Confused'];
    }
    
    $response_lower = strtolower($response['text']);
    
    $concern_words = ['oh no', 'poor', 'sorry', 'worried', 'hope you\'re okay', 'are you okay', 'that sounds hard'];
    foreach ($concern_words as $word) {
        if (strpos($response_lower, $word) !== false) {
            return ['mood' => 'concerned', 'text' => $moods['concerned']];
        }
    }
    
    if (strpos($response_lower, '?') !== false && substr_count($response_lower, '?') >= 2) {
        return ['mood' => 'thoughtful', 'text' => $moods['thoughtful']];
    }
    
    if ($message_analysis['emotion'] == 'negative') {
        if ($message_analysis['negative_intensity'] > 3) {
            return ['mood' => 'concerned', 'text' => $moods['concerned']];
        }
        return ['mood' => 'gentle', 'text' => $moods['gentle']];
    } elseif ($message_analysis['emotion'] == 'positive') {
        return ['mood' => 'happy', 'text' => $moods['happy']];
    }
    
    return ['mood' => 'gentle', 'text' => $moods['gentle']];
}

function analyzeMessage($message) {
    $negative_words = ['hate', 'angry', 'frustrated', 'annoyed', 'tired', 'exhausted', 'stressed', 'anxious', 'sad', 'upset', 'awful', 'terrible', 'worried', 'nervous', 'scared', 'lonely', 'depressed', 'overwhelmed'];
    $positive_words = ['happy', 'great', 'good', 'excited', 'love', 'amazing', 'wonderful', 'fantastic', 'glad', 'grateful', 'proud', 'accomplished', 'joyful', 'content', 'peaceful'];
    
    $message_lower = strtolower($message);
    $emotion = 'neutral';
    $keywords = [];
    $negative_count = 0;
    $positive_count = 0;
    
    foreach ($negative_words as $word) {
        if (strpos($message_lower, $word) !== false) {
            $negative_count++;
            $keywords[] = $word;
        }
    }
    
    foreach ($positive_words as $word) {
        if (strpos($message_lower, $word) !== false) {
            $positive_count++;
            $keywords[] = $word;
        }
    }
    
    if ($negative_count > $positive_count) {
        $emotion = 'negative';
    } elseif ($positive_count > $negative_count) {
        $emotion = 'positive';
    } elseif ($negative_count > 0 && $positive_count > 0) {
        $emotion = 'mixed';
    }
    
    $topics = [];
    if (preg_match('/\b(cousin|family|parent|sibling|brother|sister|mom|dad|mother|father|relatives?)\b/i', $message_lower)) {
        $topics[] = 'family';
    }
    if (preg_match('/\b(work|job|boss|colleague|office|career|project)\b/i', $message_lower)) {
        $topics[] = 'work';
    }
    if (preg_match('/\b(school|class|teacher|homework|exam|study|college|university)\b/i', $message_lower)) {
        $topics[] = 'school';
    }
    if (preg_match('/\b(friend|boyfriend|girlfriend|relationship|dating|partner)\b/i', $message_lower)) {
        $topics[] = 'relationships';
    }
    if (preg_match('/\b(hobby|hobbies|play|game|music|art|sport|read)\b/i', $message_lower)) {
        $topics[] = 'hobbies';
    }
    
    $is_question = (strpos($message, '?') !== false);
    $is_seeking_advice = preg_match('/\b(should i|what do you think|advice|help|suggestion)\b/i', $message_lower);
    
    return [
        'emotion' => $emotion,
        'keywords' => $keywords,
        'topics' => $topics,
        'length' => str_word_count($message),
        'is_question' => $is_question,
        'is_seeking_advice' => $is_seeking_advice,
        'original_message' => $message,
        'negative_intensity' => $negative_count,
        'positive_intensity' => $positive_count
    ];
}

?>