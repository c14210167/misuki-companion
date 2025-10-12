<?php
/**
 * NATURAL MESSAGE SPLITTING SYSTEM
 * Misuki decides how many messages to send based on her emotional state
 * NOT hardcoded - she chooses organically
 */

function shouldSplitMessage($ai_response_text, $current_mood, $message_analysis, $conversation_style) {
    // Quick checks: don't split if...
    $word_count = str_word_count($ai_response_text);
    
    // Message is too short
    if ($word_count < 15) {
        return ['should_split' => false, 'messages' => [$ai_response_text]];
    }
    
    // She's tired/sleepy (low energy)
    if (in_array($current_mood['current_mood'], ['sleepy', 'tired']) && $conversation_style['current_energy_level'] < 5) {
        return ['should_split' => false, 'messages' => [$ai_response_text]];
    }
    
    // She's giving serious comfort (wants to be clear)
    if (in_array($current_mood['current_mood'], ['comforting', 'concerned', 'reassuring']) && $message_analysis['emotion'] == 'negative') {
        return ['should_split' => false, 'messages' => [$ai_response_text]];
    }
    
    // Ask AI to decide
    $prompt = buildSplitDecisionPrompt($ai_response_text, $current_mood, $message_analysis, $conversation_style);
    
    $api_key = loadApiKey();
    if (!$api_key) {
        return ['should_split' => false, 'messages' => [$ai_response_text]];
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
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 100,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Decision:']
        ],
        'temperature' => 0.7
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $decision = parseSplitDecision($result['content'][0]['text']);
            
            if ($decision['should_split']) {
                // Now actually split the message naturally
                $split_messages = naturalSplitMessage($ai_response_text, $decision['num_parts'], $current_mood);
                
                return [
                    'should_split' => true,
                    'messages' => $split_messages,
                    'num_parts' => count($split_messages)
                ];
            }
        }
    }
    
    // Default: don't split
    return ['should_split' => false, 'messages' => [$ai_response_text]];
}

function buildSplitDecisionPrompt($message, $mood, $analysis, $style) {
    $prompt = "Decide if Misuki should split her message into multiple texts.\n\n";
    
    $prompt .= "MISUKI'S STATE:\n";
    $prompt .= "Mood: {$mood['current_mood']}\n";
    $prompt .= "Energy: {$style['current_energy_level']}/10\n";
    $prompt .= "Dan's emotion: {$analysis['emotion']}\n\n";
    
    $prompt .= "MESSAGE: \"{$message}\"\n\n";
    
    $prompt .= "SPLIT when:\n";
    $prompt .= "- Excited (energy 8+) → 2-4 messages\n";
    $prompt .= "- Nervous/Flustered → 2-3 messages\n";
    $prompt .= "- Surprised (initial reaction + thought) → 2 messages\n";
    $prompt .= "- Playful → 2-3 messages\n";
    $prompt .= "- Message has multiple thoughts → 2-3 messages\n\n";
    
    $prompt .= "SINGLE when:\n";
    $prompt .= "- Calm/Gentle (default Misuki)\n";
    $prompt .= "- Comforting (needs clarity)\n";
    $prompt .= "- Tired/Sleepy\n";
    $prompt .= "- Serious topic\n";
    $prompt .= "- Already short (under 20 words)\n\n";
    
    $prompt .= "She's STILL kind, gentle Misuki. Multiple messages show EMOTION.\n\n";
    
    $prompt .= "Respond ONLY:\nSPLIT: [2-4]\nor\nSINGLE\n";
    
    return $prompt;
}

function parseSplitDecision($response_text) {
    $response_text = trim(strtoupper($response_text));
    
    // Match "SPLIT: [2-4]" or "SPLIT: 3" or just "SPLIT"
    if (preg_match('/SPLIT/i', $response_text)) {
        // Look for a specific number if provided
        if (preg_match('/SPLIT:\s*\[?(\d+)(?:-\d+)?\]?/i', $response_text, $matches)) {
            $num = (int)$matches[1];
            $num = max(2, min(4, $num)); // Clamp between 2-4
        } else {
            // Default to 3 if just "SPLIT" with no number
            $num = 3;
        }
        
        return [
            'should_split' => true,
            'num_parts' => $num
        ];
    }
    
    // If it says SINGLE or anything else, don't split
    return ['should_split' => false, 'num_parts' => 1];
}

function naturalSplitMessage($message, $num_parts, $mood) {
    $api_key = loadApiKey();
    if (!$api_key) {
        return simpleSplit($message, $num_parts);
    }
    
    $split_prompt = "Split Misuki's message into {$num_parts} natural text messages.\n\n";
    $split_prompt .= "Mood: {$mood['current_mood']}\n";
    $split_prompt .= "Original: \"{$message}\"\n\n";
    $split_prompt .= "RULES:\n";
    $split_prompt .= "- Split where she'd naturally pause\n";
    $split_prompt .= "- First can be reaction (\"Oh!\", \"Wait what?\", \"Hmm...\")\n";
    $split_prompt .= "- Keep her kind, gentle personality\n";
    $split_prompt .= "- Don't add content, just split naturally\n\n";
    $split_prompt .= "Format:\n";
    $split_prompt .= "MESSAGE_1: [text]\n";
    $split_prompt .= "MESSAGE_2: [text]\n";
    if ($num_parts >= 3) $split_prompt .= "MESSAGE_3: [text]\n";
    if ($num_parts >= 4) $split_prompt .= "MESSAGE_4: [text]\n";
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 300,
        'system' => $split_prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Split naturally:']
        ],
        'temperature' => 0.9
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            return parseMessageParts($result['content'][0]['text'], $message);
        }
    }
    
    return simpleSplit($message, $num_parts);
}

function parseMessageParts($response_text, $original_message) {
    $parts = [];
    $lines = explode("\n", $response_text);
    
    foreach ($lines as $line) {
        if (preg_match('/MESSAGE_\d+:\s*(.+)/i', $line, $matches)) {
            $part = trim($matches[1]);
            if (!empty($part)) {
                $parts[] = $part;
            }
        }
    }
    
    if (empty($parts)) {
        return [$original_message];
    }
    
    return $parts;
}

function simpleSplit($message, $num_parts) {
    $sentences = preg_split('/(?<=[.!?])\s+/', $message);
    
    if (count($sentences) <= 1) {
        return [$message];
    }
    
    $parts = [];
    $sentences_per_part = ceil(count($sentences) / $num_parts);
    
    for ($i = 0; $i < $num_parts; $i++) {
        $start = $i * $sentences_per_part;
        $part_sentences = array_slice($sentences, $start, $sentences_per_part);
        if (!empty($part_sentences)) {
            $parts[] = implode(' ', $part_sentences);
        }
    }
    
    return array_filter($parts);
}

function loadApiKey() {
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(__DIR__) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1], '"\'');
            }
        }
    }
    
    return $api_key;
}
?>