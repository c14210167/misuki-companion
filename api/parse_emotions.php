<?php
// OPTIMIZED: Batch AI-Powered Emotion Detection
// Analyzes ALL sentences in ONE API call instead of multiple calls

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
        // Fallback to keyword-based if no API key
        return array_map('detectEmotionKeywordFallback', $sentences);
    }
    
    // Build numbered list of sentences
    $sentence_list = "";
    foreach ($sentences as $index => $sentence) {
        $num = $index + 1;
        $sentence_list .= "$num. \"$sentence\"\n";
    }
    
    // Create batch emotion detection prompt
    $prompt = "You are an emotion classifier for an anime girlfriend character named Misuki. Analyze each sentence and return its emotion.

Available emotions (choose ONLY from this list):
neutral, happy, excited, loving, content, blushing, sad, concerned, anxious, upset, pleading, surprised, shocked, confused, flustered, amazed, curious, teasing, playful, giggling, confident, embarrassed, shy, nervous, comforting, affectionate, reassuring, gentle, thoughtful, sleepy, pouty, relieved, dreamy

CRITICAL RULES:
- Understand CONTEXT, not just keywords
- 'I'm so sorry' = comforting (not sad)
- '!' in negative context = shocked/concerned (not excited)
- Sarcasm/playful tone = teasing/playful/giggling
- Questions = curious (unless clearly confused)
- Supportive phrases = comforting/reassuring
- If neutral/unclear = gentle

Return ONLY emotions in this format (one per line):
1: emotion
2: emotion
3: emotion

Sentences to analyze:
$sentence_list

Emotions (number: emotion format):";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-haiku-20240307', // Fast and cheap
        'max_tokens' => 150, // Enough for ~20 sentences
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Analyze all emotions:']
        ],
        'temperature' => 0.3
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $emotion_text = $result['content'][0]['text'];
            
            // Parse the response
            $emotions = parseEmotionResponse($emotion_text, count($sentences));
            
            if (count($emotions) === count($sentences)) {
                return $emotions;
            }
        }
    }
    
    // Fallback to keyword-based if API fails
    error_log("AI emotion detection failed, using keyword fallback");
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
    // Simplified keyword-based fallback (only for when AI fails)
    $sentence_lower = strtolower($sentence);
    
    // High priority: Negative emotions
    if (preg_match('/\b(creepy|scary|terrifying|awful|terrible|horrible)\b/i', $sentence_lower)) {
        return substr_count($sentence, '!') > 0 ? 'shocked' : 'concerned';
    }
    
    if (preg_match('/\b(sad|cry|hurt|heartbroken)\b/i', $sentence_lower)) {
        return 'sad';
    }
    
    if (preg_match('/\b(worried|concern|afraid|nervous|anxious)\b/i', $sentence_lower)) {
        return 'concerned';
    }
    
    if (preg_match('/\b(upset|disappointed|frustrated)\b/i', $sentence_lower)) {
        return 'upset';
    }
    
    // Supportive
    if (preg_match('/\b(sorry|i\'m here|it\'s okay|don\'t worry)\b/i', $sentence_lower)) {
        return 'comforting';
    }
    
    // Loving
    if (preg_match('/\b(love you|miss you|my love|💕|❤️)\b/i', $sentence_lower)) {
        return 'loving';
    }
    
    // Happy/Excited
    if (preg_match('/\b(happy|glad|yay|amazing|awesome)\b/i', $sentence_lower)) {
        return 'happy';
    }
    
    if (preg_match('/\b(excited|wow|omg|can\'t wait)\b/i', $sentence_lower)) {
        return 'excited';
    }
    
    // Playful
    if (preg_match('/\b(hehe|haha|lol|funny)\b/i', $sentence_lower)) {
        return 'playful';
    }
    
    // Questions
    if (strpos($sentence, '?') !== false) {
        return 'curious';
    }
    
    // Surprised
    if (preg_match('/(what\?!|really\?!|no way!)/i', $sentence_lower)) {
        return 'surprised';
    }
    
    // Default
    if (substr_count($sentence, '!') > 0) {
        return 'gentle'; // Emphasis without clear emotion
    }
    
    return 'neutral';
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

?>