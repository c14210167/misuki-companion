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
        'emotion_timeline' => $emotion_timeline // NEW: Expression changes
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
    
    // Handle time confusion
    $time_confusion_note = '';
    if ($time_confused) {
        $confusions = [
            'morning_at_night' => "The user said 'good morning' but it's currently night time.",
            'night_at_morning' => "The user said 'good night' but it's currently morning.",
            'morning_at_afternoon' => "The user said 'good morning' but it's currently afternoon."
        ];
        $time_confusion_note = "\n\nIMPORTANT: " . ($confusions[$time_confused] ?? '') . " You should gently point this out in a caring, slightly confused way. Maybe they're confused about the time or joking?";
    }
    
    $system_prompt = getMisukiPersonalityPrompt() . "\n\n" . $context . "\n\n" . $time_context . $time_confusion_note;
    
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
        return ['text' => getFallbackResponse(strtolower($message), $analysis, $time_confused)];
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
        'max_tokens' => 220, // Increased for Haiku
        'system' => $system_prompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'temperature' => 1.0 // More creative/emotional
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Claude API Error (HTTP $http_code): " . $response);
        return ['text' => getFallbackResponse(strtolower($message), $analysis, $time_confused)];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['content'][0]['text'])) {
        return ['text' => $result['content'][0]['text']];
    }
    
    // Fallback
    return ['text' => getFallbackResponse(strtolower($message), $analysis, $time_confused)];
}

function getTimeContext($time_of_day) {
    $times = [
        'morning' => "Current time context: It's morning (5 AM - 12 PM). The sun is up, it's a fresh start to the day.",
        'afternoon' => "Current time context: It's afternoon (12 PM - 5 PM). The day is in full swing.",
        'evening' => "Current time context: It's evening (5 PM - 9 PM). The day is winding down, the sun is setting.",
        'night' => "Current time context: It's night time (9 PM - 5 AM). Most people are winding down or sleeping. It's quite late."
    ];
    
    return $times[$time_of_day] ?? $times['day'];
}

function getFallbackResponse($message_lower, $analysis, $time_confused) {
    // Handle time confusion first
    if ($time_confused) {
        $responses = [
            'morning_at_night' => "Um... good evening, actually? *giggles softly* It's nighttime right now... Did you just wake up, or are you having one of those confusing days? Either way, I'm here for you! 🌙",
            'night_at_morning' => "Oh... actually, it's morning! ☀️ Did you mean good morning? *tilts head* Are you feeling sleepy? That's okay, mornings can be confusing sometimes...",
            'morning_at_afternoon' => "Mm... it's actually afternoon now! ☀️ Time flies, doesn't it? How has your day been so far?"
        ];
        
        if (isset($responses[$time_confused])) {
            return $responses[$time_confused];
        }
    }
    
    // Pattern matching for common phrases
    if (strpos($message_lower, 'loud') !== false || strpos($message_lower, 'noisy') !== false) {
        return "That sounds overwhelming... Sometimes quiet is hard to find. Though, maybe they just really enjoy being around you? Still, it's okay to need your own space too. 💭";
    }
    
    if (strpos($message_lower, 'tired') !== false || strpos($message_lower, 'exhausted') !== false) {
        return "You sound really tired... Please don't push yourself too hard, okay? Taking rest isn't being lazy - it's being kind to yourself. ♥";
    }
    
    if (strpos($message_lower, 'stressed') !== false || strpos($message_lower, 'anxious') !== false) {
        return "I can hear the stress in your words... It's okay to feel overwhelmed sometimes. You're doing your best, and that's more than enough. 🌸";
    }
    
    if (strpos($message_lower, 'happy') !== false || strpos($message_lower, 'excited') !== false) {
        return "I'm so glad to hear that! Your happiness makes me happy too! ✨ What made today special for you?";
    }
    
    // Emotion-based responses
    if ($analysis['emotion'] == 'negative') {
        return "I'm here for you... Whatever you're going through, you don't have to face it alone. Want to talk about it? 💕";
    }
    
    if ($analysis['emotion'] == 'positive') {
        return "That's wonderful! I love seeing you happy. Tell me more! ☀️";
    }
    
    // Default gentle response
    return "I'm listening... Tell me more? I'm here to understand. 🌙";
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
        return ['mood' => 'concerned', 'text' => 'Confused'];
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

?>