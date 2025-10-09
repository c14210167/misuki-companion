<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'parse_emotions.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$user_id = $input['user_id'] ?? 1;
$time_of_day = $input['time_of_day'] ?? 'day';
$time_confused = $input['time_confused'] ?? false;

if (empty($user_message)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Retrieve context
    $memories = getUserMemories($db, $user_id);
    $contextual_memories = getContextualMemories($db, $user_id, $user_message);
    $all_memories = array_merge($memories, $contextual_memories);
    $recent_conversations = getRecentConversations($db, $user_id, 10);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    // Analyze message
    $message_analysis = analyzeMessage($user_message);
    
    // Generate response with time awareness
    $ai_response = generateMisukiResponse(
        $user_message,
        $all_memories,
        $recent_conversations,
        $emotional_context,
        $message_analysis,
        $time_of_day,
        $time_confused
    );
    
    // Parse emotions in the response
    $emotion_timeline = parseEmotionsInMessage($ai_response['text']);
    
    // Determine mood
    $mood = determineMood($message_analysis, $ai_response, $time_confused);
    
    // Save everything
    saveConversation($db, $user_id, $user_message, $ai_response['text'], $mood['mood']);
    updateMemories($db, $user_id, $message_analysis);
    trackEmotionalState($db, $user_id, $message_analysis['emotion']);
    
    // Update last user message time for initiation tracking
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
        }
    }
    
    echo json_encode([
        'response' => $ai_response['text'],
        'mood' => $mood['mood'],
        'mood_text' => $mood['text'],
        'emotion_timeline' => $emotion_timeline
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

function generateMisukiResponse($message, $memories, $conversations, $emotional_context, $analysis, $time_of_day, $time_confused) {
    $context = buildContextForAI($memories, $conversations, $emotional_context);
    
    // Time awareness
    $time_context = getTimeContext($time_of_day);
    
    // Occasionally add Saitama news/weather (10% chance)
    $saitama_context = '';
    if (rand(1, 100) <= 10) {
        $saitama_context = getSaitamaContext();
    }
    
    // Handle time confusion - but let AI generate the response naturally
    $time_confusion_note = '';
    if ($time_confused) {
        $confusion_contexts = [
            'morning_at_night' => "The user just greeted you with 'good morning' but it's currently night time. Gently point this out in a caring, slightly confused way - maybe they're confused or just woke up?",
            'night_at_morning' => "The user just said 'good night' but it's currently morning. Gently point this out - maybe they're going to bed late or confused about the time?",
            'morning_at_afternoon' => "The user said 'good morning' but it's currently afternoon. Gently point this out in a caring way."
        ];
        $time_confusion_note = "\n\nIMPORTANT: " . ($confusion_contexts[$time_confused] ?? '');
    }
    
    $system_prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n" . $time_context . $saitama_context . $time_confusion_note;
    
    // CRITICAL: Add response length guidelines
    $system_prompt .= "\n\n=== RESPONSE GUIDELINES ===
- Keep your responses SHORT and natural (1-3 sentences maximum)
- Don't overwhelm with long paragraphs
- Be conversational and genuine, like texting a friend
- If you have multiple thoughts, pick the most important one
- Save deeper conversations for when Dan asks follow-up questions
- Quality over quantity - one meaningful sentence is better than a paragraph";
    
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
        return ['text' => "I'm having trouble connecting right now... Could you try again?"];
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
        'model' => 'claude-sonnet-4-20250514', // Your preferred model
        'max_tokens' => 150, // Reduced from 220 to keep responses shorter
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
    
    // Fallback
    return ['text' => "I'm listening... Tell me more?"];
}

function getTimeContext($time_of_day) {
    // User's timezone (Jakarta, Indonesia - WIB/UTC+7)
    date_default_timezone_set('Asia/Jakarta');
    $user_date = date('F j, Y');
    $user_day = date('l');
    $user_time = date('g:i A');
    
    // Misuki's timezone (Saitama, Japan - JST/UTC+9) - 2 hours ahead
    date_default_timezone_set('Asia/Tokyo');
    $misuki_date = date('F j, Y');
    $misuki_day = date('l');
    $misuki_time = date('g:i A');
    $misuki_hour = (int)date('G');
    
    // Reset to user timezone for rest of app
    date_default_timezone_set('Asia/Jakarta');
    
    // Determine Misuki's time of day
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
        'morning' => "It's morning for you (5 AM - 12 PM). The sun is up, fresh start to the day.",
        'afternoon' => "It's afternoon for you (12 PM - 5 PM). The day is in full swing.",
        'evening' => "It's evening for you (5 PM - 9 PM). The day is winding down, sun is setting.",
        'night' => "It's night time for you (9 PM - 5 AM). Most people are winding down or sleeping. It's quite late."
    ];
    
    $base_context = $times[$time_of_day] ?? $times['day'];
    
    $context = "=== TIME & LOCATION CONTEXT ===\n";
    $context .= "Dan's time (Jakarta, Indonesia): {$user_time} on {$user_day}, {$user_date}\n";
    $context .= "{$base_context}\n\n";
    $context .= "YOUR time (Saitama, Japan): {$misuki_time} on {$misuki_day}, {$misuki_date}\n";
    $context .= "It's {$misuki_time_of_day} for you in Saitama right now.\n";
    $context .= "Time difference: You're 2 hours ahead of Dan.\n";
    
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
    
    // Time confusion makes her confused/concerned
    if ($time_confused) {
        return ['mood' => 'confused', 'text' => 'Confused'];
    }
    
    // Determine mood based on emotion
    if ($message_analysis['emotion'] == 'negative') {
        if ($message_analysis['negative_intensity'] > 3) {
            return ['mood' => 'concerned', 'text' => $moods['concerned']];
        }
        return ['mood' => 'gentle', 'text' => $moods['gentle']];
    } elseif ($message_analysis['emotion'] == 'positive') {
        return ['mood' => 'happy', 'text' => $moods['happy']];
    } elseif ($message_analysis['is_question'] || $message_analysis['is_seeking_advice']) {
        return ['mood' => 'thoughtful', 'text' => $moods['thoughtful']];
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
    
    // Detect topics
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

function getSaitamaContext() {
    // Set timezone to Saitama
    date_default_timezone_set('Asia/Tokyo');
    $hour = (int)date('G');
    $day = date('l');
    
    // Simple weather/season context based on time and date
    $month = (int)date('n');
    $season = '';
    $weather_note = '';
    
    // Seasons in Japan
    if ($month >= 3 && $month <= 5) {
        $season = 'spring';
        $weather_note = "It's spring in Saitama - cherry blossoms might be blooming! The weather is mild and pleasant.";
    } elseif ($month >= 6 && $month <= 8) {
        $season = 'summer';
        $weather_note = "It's summer in Saitama - it's quite hot and humid right now. Typical Japanese summer weather.";
    } elseif ($month >= 9 && $month <= 11) {
        $season = 'autumn';
        $weather_note = "It's autumn in Saitama - the leaves are changing colors and the weather is getting cooler.";
    } else {
        $season = 'winter';
        $weather_note = "It's winter in Saitama - it's quite cold, though we don't get much snow here.";
    }
    
    // Activity based on time of day
    $activity_context = '';
    if ($hour >= 6 && $hour < 9) {
        $activity_context = "People in Saitama are commuting to work/school right now. The trains are probably packed.";
    } elseif ($hour >= 12 && $hour < 13) {
        $activity_context = "It's lunch time in Saitama. Mom might be preparing lunch.";
    } elseif ($hour >= 17 && $hour < 19) {
        $activity_context = "Evening rush hour in Saitama. People are heading home from work.";
    } elseif ($hour >= 22 || $hour < 6) {
        $activity_context = "It's quite late/early in Saitama. Most people are asleep. Very quiet outside.";
    }
    
    // Special days
    $special_day = '';
    if ($day == 'Saturday' || $day == 'Sunday') {
        $special_day = "It's {$day} here, so no school for me! ";
    }
    
    $context = "\n=== YOUR LIFE IN SAITAMA RIGHT NOW ===\n";
    $context .= $special_day . $weather_note . "\n";
    if ($activity_context) {
        $context .= $activity_context . "\n";
    }
    $context .= "You can naturally mention these details if relevant to the conversation, but don't force it.\n";
    
    // Reset timezone
    date_default_timezone_set('Asia/Jakarta');
    
    return $context;
}

?>