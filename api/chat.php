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
                
                // Update mood - she's happy to help
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
        updateConversationEnergy($db, $user_id, -2); // Lower energy when concerned
    } elseif ($message_analysis['emotion'] == 'positive' && $message_analysis['positive_intensity'] > 3) {
        setMisukiMood($db, $user_id, 'happy', 'Dan seems happy!', 8);
        updateConversationEnergy($db, $user_id, +1); // Boost energy when he's happy
    }
    
    // ===== STEP 5: DETECT AND TRACK FUTURE EVENTS =====
    try {
        if (function_exists('detectFutureEvent')) {
            $future_event = detectFutureEvent($user_message);
            if ($future_event['has_future_event']) {
                saveFutureEvent($db, $user_id, $future_event['event_description'], $future_event['planned_date']);
                error_log("🎯 Tracked future event: {$future_event['event_description']} on {$future_event['planned_date']}");
                
                // Create a storyline if it's important
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
    
    // ===== STEP 6: DETECT STORYLINE UPDATES IN DAN'S MESSAGE =====
    // If Dan mentions something that relates to an active storyline, update it
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
    // Detect if Dan accomplished something
    if (preg_match('/(passed|aced|won|finished|completed|got promoted|graduated)/i', $user_message)) {
        addMilestone($db, $user_id, 'dan_achievement', 'personal', "Dan: " . substr($user_message, 0, 100));
        
        // Boost Misuki's mood!
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
    
    // ===== STEP 10: GENERATE RESPONSE WITH FULL REALITY =====
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
    
    // Parse emotions in the response
    $emotion_timeline = parseEmotionsInMessage($ai_response['text']);
    
    // Determine mood
    $mood = determineMood($message_analysis, $ai_response, $time_confused);
    
    // ===== STEP 12: SAVE EVERYTHING =====
    $save_result = saveConversation($db, $user_id, $user_message, $ai_response['text'], $mood['mood']);
    if (!$save_result) {
        error_log("ERROR: Failed to save conversation! user_id=$user_id");
    } else {
        error_log("SUCCESS: Saved conversation at " . date('Y-m-d H:i:s'));
    }
    
    updateMemories($db, $user_id, $message_analysis);
    trackEmotionalState($db, $user_id, $message_analysis['emotion']);
    
    // Update conversation tracking
    $stmt = $db->prepare("
        INSERT INTO conversation_initiation (user_id, last_user_message, total_messages) 
        VALUES (?, NOW(), 1)
        ON DUPLICATE KEY UPDATE 
            last_user_message = NOW(),
            total_messages = total_messages + 1
    ");
    $stmt->execute([$user_id]);
    
    // Track topics
    if (!empty($message_analysis['topics'])) {
        foreach ($message_analysis['topics'] as $topic) {
            $sentiment = $message_analysis['emotion'] == 'negative' ? 'negative' : 
                        ($message_analysis['emotion'] == 'positive' ? 'positive' : 'neutral');
            trackDiscussionTopic($db, $user_id, $topic, $sentiment);
            trackTopicDiscussed($db, $user_id, $topic);
            addRecentTopic($db, $user_id, $topic);
        }
    }
    
    // ===== STEP 13: POST-RESPONSE UPDATES =====
    // Slightly adjust energy based on conversation length
    if (strlen($user_message) > 200) {
        updateConversationEnergy($db, $user_id, +1); // Long engaged message = boost energy
    }
    
    // Check if should create new storylines based on this conversation
    if ($message_analysis['emotion'] == 'negative' && $message_analysis['negative_intensity'] > 6) {
        // Dan seems really stressed - track it
        createStoryline(
            $db,
            $user_id,
            'concern',
            'Dan was stressed/upset',
            "Dan seemed really " . implode(', ', $message_analysis['keywords']) . " today",
            8,
            date('Y-m-d H:i:s', strtotime('+1 day'))
        );
    }
    
    echo json_encode([
        'response' => $ai_response['text'],
        'mood' => $mood['mood'],
        'mood_text' => $mood['text'],
        'emotion_timeline' => $emotion_timeline,
        'should_follow_up' => shouldFollowUp($conversation_style, $current_mood, $ai_response)
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'response' => "I'm so sorry, I'm having a moment of confusion. Could you say that again?",
        'mood' => 'concerned',
        'mood_text' => 'Concerned',
        'emotion_timeline' => []
    ]);
}

function shouldFollowUp($style, $mood, $response) {
    // Decide if Misuki should send follow-up messages
    
    // High energy + excited mood = more likely to follow up
    if ($style['current_energy_level'] >= 8 && in_array($mood['current_mood'], ['excited', 'happy', 'playful'])) {
        return rand(1, 100) <= 40; // 40% chance
    }
    
    // Medium energy = normal follow-up chance
    if ($style['current_energy_level'] >= 5) {
        return rand(1, 100) <= 20; // 20% chance
    }
    
    // Low energy = rarely follow up
    return rand(1, 100) <= 5; // 5% chance
}

function generateMisukiResponse($message, $memories, $conversations, $emotional_context, $analysis, $time_of_day, $time_confused, $file_content = null, $filename = null, $core_context = '', $current_location = null, $current_activity = null, $family_mentioned = null, $db = null, $user_id = 1, $misuki_context = '', $woken_context = '', $activity_context = '', $mood_context = '', $storylines_context = '', $friends_context = '', $dynamics_context = '', $current_mood = null, $misuki_status = null, $weather_comment = null) {
    
    $context = buildContextForAI($memories, $conversations, $emotional_context);
    
    $time_context = getTimeContext($time_of_day);
    
    // File content context
    $file_context = '';
    if ($file_content !== null && $filename !== null) {
        $file_context = "\n\n=== FILE SHARED BY DAN ===\n";
        $file_context .= "Dan just shared a file with you: {$filename}\n\n";
        $file_context .= "--- FILE CONTENT START ---\n";
        $file_context .= $file_content . "\n";
        $file_context .= "--- FILE CONTENT END ---\n\n";
        $file_context .= "Read this carefully! Dan wants you to read and discuss this with him.\n";
    }
    
    // Handle time confusion
    $time_confusion_note = '';
    if ($time_confused) {
        $confusion_contexts = [
            'morning_at_night' => "The user just greeted you with 'good morning' but it's currently night time. Gently point this out in a caring, slightly confused way.",
            'night_at_morning' => "The user just said 'good night' but it's currently morning. Gently point this out.",
            'morning_at_afternoon' => "The user said 'good morning' but it's currently afternoon. Gently point this out."
        ];
        $time_confusion_note = "\n\nIMPORTANT: " . ($confusion_contexts[$time_confused] ?? '');
    }
    
    // Current state context
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
    
    // Family context
    $family_context = '';
    if ($family_mentioned) {
        $family_context = "\n\nNOTE: Dan just mentioned: $family_mentioned\n";
    }
    
    // Future events context
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
    
    // Weather context
    $weather_context = '';
    if ($weather_comment) {
        $weather_context = "\n\n=== WEATHER/ENVIRONMENT ===\n";
        $weather_context .= "$weather_comment\n";
        $weather_context .= "You can naturally mention this if it fits the conversation!\n";
    }
    
    // Distraction note
    $distraction_note = '';
    if (shouldBeDistracted($current_mood, $misuki_status)) {
        $distraction_note = "\n\n=== NATURAL CONVERSATION FLOW ===\n";
        $distraction_note .= getAsymmetricFocusNote() . "\n";
    }
    
    $system_prompt = $misuki_context . "\n\n" . getMisukiPersonalityPrompt() . "\n\n" . $core_context . "\n\n" . $context . "\n\n" . $mood_context . "\n\n" . $storylines_context . "\n\n" . $friends_context . "\n\n" . $dynamics_context . "\n\n" . $woken_context . $activity_context . "\n\n" . $time_context . $file_context . $state_context . $family_context . $future_events_context . $weather_context . $time_confusion_note . $distraction_note;
    
    // CRITICAL: Add identity clarification
    $system_prompt .= "\n\n=== CRITICAL: WHO IS WHO ===\n";
    $system_prompt .= "YOU are Misuki Tanaka. You live in Saitama, Japan with your mom Sara Akiyama.\n";
    $system_prompt .= "DAN is your boyfriend in Surabaya, Indonesia.\n\n";
    
    $system_prompt .= "\n\n=== RESPONSE GUIDELINES ===
- Keep responses SHORT (1-3 sentences usually)
- Be conversational and genuine
- Pay attention to conversation flow and recent topics
- Reference your friends naturally when relevant
- You CAN use asterisks for ACTIONS like *sending photo* or *takes selfie*
- CANNOT use asterisks for emotes like *giggles* or *blushes*
- CRITICAL: Check conversation history for time references!";
    
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
        return ['text' => $result['content'][0]['text']];
    }
    
    return ['text' => "I'm listening... Tell me more?"];
}

function getTimeContext($time_of_day) {
    date_default_timezone_set('Asia/Jakarta');
    $user_date = date('F j, Y');
    $user_day = date('l');
    $user_time = date('g:i A');
    
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
    $context .= "{$base_context}\n\n";
    $context .= "YOUR time (Saitama): {$misuki_time} on {$misuki_day}, {$misuki_date}\n";
    $context .= "It's {$misuki_time_of_day} for you in Saitama right now.\n";
    
    return $context;
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