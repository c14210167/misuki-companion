<?php
// OPTIMIZED: Batch AI-Powered Emotion Detection
// Analyzes ALL sentences in ONE API call instead of multiple calls
// ANTI-FLICKER: Removes emotions that are too short to display properly
// FIXED: Sleepy emotion only triggers when Misuki is actually tired

function parseEmotionsInMessage($message) {
    // Split message into sentences
    $sentences = preg_split('/(?<=[.!?])\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($sentences)) {
        return [];
    }
    
    // Get emotions for ALL sentences in one batch
    $emotions = detectEmotionsBatch($sentences);
    
    $emotion_timeline = [];
    $current_time = 0;
    
    foreach ($sentences as $index => $sentence) {
        $emotion = $emotions[$index] ?? 'gentle';
        
        // Calculate timing (words per sentence as a rough estimate)
        $word_count = str_word_count($sentence);
        $duration = $word_count * 0.3; // ~0.3 seconds per word
        
        $emotion_timeline[] = [
            'emotion' => $emotion,
            'sentence' => $sentence,
            'start_time' => $current_time,
            'duration' => $duration,
            'sentence_index' => $index
        ];
        
        $current_time += $duration;
    }
    
    return $emotion_timeline;
}

function detectEmotionsBatch($sentences) {
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
        error_log("No API key found - using enhanced keyword fallback");
        return array_map('detectEmotionKeywordFallback', $sentences);
    }
    
    // Build numbered list of sentences
    $sentence_list = "";
    foreach ($sentences as $index => $sentence) {
        $num = $index + 1;
        $sentence_list .= "$num. \"$sentence\"\n";
    }
    
    $prompt = "You are an emotion classifier for Misuki. Analyze each sentence and return its emotion.

Available emotions: neutral, happy, excited, loving, content, blushing, sad, concerned, anxious, upset, pleading, surprised, shocked, confused, flustered, amazed, curious, teasing, playful, giggling, confident, embarrassed, shy, nervous, comforting, affectionate, reassuring, gentle, thoughtful, sleepy, pouty, relieved, dreamy

Return ONLY emotions in this format (one per line):
1: emotion
2: emotion

Sentences:
$sentence_list

Emotions:";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-5-haiku-20241022', // ✨ KEY FIX: Updated model
        'max_tokens' => 200,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Analyze emotions:']
        ],
        'temperature' => 0.3
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // ✨ KEY FIX: Increased timeout
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Emotion API CURL error: $curl_error - using keyword fallback");
        return array_map('detectEmotionKeywordFallback', $sentences);
    }
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $emotion_text = $result['content'][0]['text'];
            $emotions = parseEmotionResponse($emotion_text, count($sentences));
            
            if (count($emotions) === count($sentences)) {
                error_log("✅ AI emotion detection successful");
                return $emotions;
            }
        }
    }
    
    error_log("AI emotion detection failed (HTTP $http_code) - using enhanced keyword fallback");
    return array_map('detectEmotionKeywordFallback', $sentences);
}

function parseEmotionResponse($response_text, $expected_count) {
    $valid_emotions = [
        'neutral', 'happy', 'excited', 'loving', 'content', 'blushing',
        'sad', 'concerned', 'anxious', 'upset', 'pleading',
        'surprised', 'shocked', 'confused', 'flustered', 'amazed', 'curious',
        'teasing', 'playful', 'giggling', 'confident',
        'embarrassed', 'shy', 'nervous',
        'comforting', 'affectionate', 'reassuring', 'gentle',
        'thoughtful', 'sleepy', 'pouty', 'relieved', 'dreamy'
    ];
    
    $emotions = [];
    $lines = explode("\n", trim($response_text));
    
    foreach ($lines as $line) {
        // Match format: "1: emotion" or "1. emotion" or "1 - emotion"
        if (preg_match('/^\s*\d+[\s:.)\-]+(\w+)\s*$/i', $line, $matches)) {
            $emotion = strtolower(trim($matches[1]));
            
            if (in_array($emotion, $valid_emotions)) {
                $emotions[] = $emotion;
            } else {
                $emotions[] = 'gentle'; // Invalid emotion
            }
        }
    }
    
    // If we didn't get enough emotions, fill with 'gentle'
    while (count($emotions) < $expected_count) {
        $emotions[] = 'gentle';
    }
    
    return array_slice($emotions, 0, $expected_count);
}

function detectEmotionKeywordFallback($sentence) {
    $sentence_lower = strtolower($sentence);
    $exclamation_count = substr_count($sentence, '!');
    $question_mark = strpos($sentence, '?') !== false;
    
    // SLEEPY - only when Misuki is actually tired
    $sleepy_patterns = [
        '/^i\'m (?:so |really )?(?:sleepy|tired|exhausted)/i',
        '/\bcan barely keep my eyes open\b/i',
        '/\*yawn/i',
        '/😴/',
        '/💤/'
    ];
    foreach ($sleepy_patterns as $pattern) {
        if (preg_match($pattern, $sentence_lower)) return 'sleepy';
    }
    
    // HIGH ENERGY EMOTIONS
    if ($exclamation_count >= 2) {
        if (preg_match('/\b(amazing|wow|omg|incredible|awesome)\b/i', $sentence_lower)) return 'excited';
        if (preg_match('/\b(love|adore|miss)\b/i', $sentence_lower)) return 'loving';
        return 'excited';
    }
    
    // NEGATIVE EMOTIONS
    if (preg_match('/\b(sorry|i\'m here|it\'s okay|don\'t worry)\b/i', $sentence_lower)) return 'comforting';
    if (preg_match('/\b(sad|cry|hurt)\b/i', $sentence_lower)) return 'sad';
    if (preg_match('/\b(worried|concerned|afraid)\b/i', $sentence_lower)) return 'concerned';
    if (preg_match('/\b(nervous|anxious|shy)\b/i', $sentence_lower)) return 'nervous';
    if (preg_match('/\b(upset|disappointed|frustrated)\b/i', $sentence_lower)) return 'upset';
    
    // POSITIVE EMOTIONS
    if (preg_match('/\b(love you|miss you|💕|❤️)\b/i', $sentence_lower)) return 'loving';
    if (preg_match('/\b(happy|glad|yay)\b/i', $sentence_lower)) return 'happy';
    if (preg_match('/\b(hehe|haha|lol)\b/i', $sentence_lower)) return 'giggling';
    if (preg_match('/\b(teasing|playful)\b/i', $sentence_lower)) return 'teasing';
    
    // MILD/MODERATE EMOTIONS
    if (preg_match('/\b(cute|sweet|dear)\b/i', $sentence_lower)) return 'affectionate';
    if (preg_match('/\b(hmm|thinking|wonder)\b/i', $sentence_lower)) return 'thoughtful';
    if (preg_match('/\b(confused|don\'t understand)\b/i', $sentence_lower)) return 'confused';
    
    // QUESTIONS
    if ($question_mark) return 'curious';
    
    // EMPHASIS
    if ($exclamation_count == 1) return 'happy';
    
    // DEFAULT
    return 'gentle';
}

function getEmotionImage($emotion) {
    // Map emotions to image files
    $emotion_images = [
        'neutral' => 'misuki-neutral.png',
        'happy' => 'misuki-happy.png',
        'excited' => 'misuki-excited.png',
        'blushing' => 'misuki-blushing.png',
        'loving' => 'misuki-loving.png',
        'content' => 'misuki-content.png',
        
        'sad' => 'misuki-sad.png',
        'concerned' => 'misuki-concerned.png',
        'anxious' => 'misuki-anxious.png',
        'upset' => 'misuki-upset.png',
        'pleading' => 'misuki-pleading.png',
        'shocked' => 'misuki-surprised.png',
        
        'surprised' => 'misuki-surprised.png',
        'confused' => 'misuki-confused.png',
        'flustered' => 'misuki-flustered.png',
        'amazed' => 'misuki-amazed.png',
        'curious' => 'misuki-thoughtful.png',
        
        'teasing' => 'misuki-teasing.png',
        'playful' => 'misuki-playful.png',
        'giggling' => 'misuki-giggling.png',
        'confident' => 'misuki-confident.png',
        
        'embarrassed' => 'misuki-embarrassed.png',
        'shy' => 'misuki-shy.png',
        'nervous' => 'misuki-nervous.png',
        
        'comforting' => 'misuki-comforting.png',
        'affectionate' => 'misuki-affectionate.png',
        'reassuring' => 'misuki-reassuring.png',
        'gentle' => 'misuki-gentle.png',
        
        'thoughtful' => 'misuki-thoughtful.png',
        'sleepy' => 'misuki-sleepy.png',
        'pouty' => 'misuki-pouty.png',
        'relieved' => 'misuki-relieved.png',
        'dreamy' => 'misuki-dreamy.png'
    ];
    
    return $emotion_images[$emotion] ?? 'misuki-neutral.png';
}

// Add this function to api/parse_emotions.php

/**
 * Analyze the user's message for emotional content
 * Returns analysis with dominant emotion
 */
function analyzeEmotions($message) {
    $message_lower = strtolower($message);
    
    // Initialize analysis
    $analysis = [
        'original_message' => $message,
        'dominant_emotion' => 'neutral',
        'intensity' => 5,
        'length' => strlen($message)
    ];
    
    // Detect emotions based on keywords and patterns
    $emotions = [];
    
    // Happy/Excited
    if (preg_match('/\b(happy|glad|excited|yay|awesome|amazing|great|wonderful)\b/i', $message_lower)) {
        $emotions['happy'] = 7;
    }
    if (preg_match('/\b(love|adore|miss you)\b/i', $message_lower) || substr_count($message, '!') > 2) {
        $emotions['excited'] = 8;
    }
    
    // Sad/Upset
    if (preg_match('/\b(sad|depressed|cry|hurt|upset|disappointed)\b/i', $message_lower)) {
        $emotions['sad'] = 8;
    }
    
    // Anxious/Stressed
    if (preg_match('/\b(worried|anxious|stress|nervous|scared|afraid)\b/i', $message_lower)) {
        $emotions['anxious'] = 7;
    }
    
    // Angry
    if (preg_match('/\b(angry|mad|furious|hate)\b/i', $message_lower)) {
        $emotions['angry'] = 8;
    }
    
    // Tired/Sleepy
    if (preg_match('/\b(tired|exhausted|sleepy|drowsy)\b/i', $message_lower)) {
        $emotions['tired'] = 6;
    }
    
    // Confused
    if (preg_match('/\?{2,}|\bconfused\b|\bdon\'t understand\b/i', $message_lower)) {
        $emotions['confused'] = 6;
    }
    
    // If no specific emotion found, check for general sentiment
    if (empty($emotions)) {
        if (substr_count($message, '!') > 0) {
            $emotions['excited'] = 5;
        } else if (preg_match('/\b(okay|fine|alright)\b/i', $message_lower)) {
            $emotions['neutral'] = 5;
        } else {
            $emotions['neutral'] = 5;
        }
    }
    
    // Find dominant emotion
    if (!empty($emotions)) {
        arsort($emotions);
        $analysis['dominant_emotion'] = key($emotions);
        $analysis['intensity'] = current($emotions);
    }
    
    return $analysis;
}

/**
 * Generate emotional context text for the AI
 */
function generateEmotionalContext($analysis) {
    if (!isset($analysis['dominant_emotion']) || $analysis['dominant_emotion'] === 'neutral') {
        return '';
    }
    
    $emotion = $analysis['dominant_emotion'];
    $intensity = $analysis['intensity'] ?? 5;
    
    $context = "\n\n=== EMOTIONAL CONTEXT ===\n";
    $context .= "Dan's message has a {$emotion} tone";
    
    if ($intensity >= 7) {
        $context .= " (strong intensity)";
    } else if ($intensity >= 5) {
        $context .= " (moderate intensity)";
    } else {
        $context .= " (mild intensity)";
    }
    
    $context .= ".\n";
    
    // Add contextual guidance
    switch($emotion) {
        case 'sad':
        case 'upset':
            $context .= "Be gentle, supportive, and caring. Show empathy.\n";
            break;
        case 'anxious':
        case 'stressed':
            $context .= "Be reassuring and comforting. Help him feel better.\n";
            break;
        case 'happy':
        case 'excited':
            $context .= "Match his positive energy! Be enthusiastic with him.\n";
            break;
        case 'angry':
            $context .= "Be understanding and patient. Don't escalate.\n";
            break;
        case 'tired':
            $context .= "Be gentle and understanding of his tiredness.\n";
            break;
        case 'confused':
            $context .= "Be clear and helpful in explaining things.\n";
            break;
    }
    
    return $context;
}

?>