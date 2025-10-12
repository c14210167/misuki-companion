<?php
/**
 * NATURAL MESSAGE SPLITTING SYSTEM
 * Misuki decides how many messages to send based on her emotional state
 * She has FULL autonomy - not based on random chance
 */

function shouldSplitMessage($ai_response_text, $current_mood, $message_analysis, $conversation_style) {
    // Only check: Message is too short to split
    $word_count = str_word_count($ai_response_text);
    
    if ($word_count < 10) {
        return ['should_split' => false, 'messages' => [$ai_response_text]];
    }
    
    // Give Misuki full autonomy - let HER decide based on her feelings
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
        'max_tokens' => 150,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'How should I send this message?']
        ],
        'temperature' => 0.9  // Higher temperature for more varied decisions
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
    $prompt = "You are Misuki. You just thought of this response:\n\n";
    $prompt .= "\"{$message}\"\n\n";
    
    $prompt .= "YOUR CURRENT STATE:\n";
    $prompt .= "- Mood: {$mood['current_mood']}\n";
    $prompt .= "- Energy Level: {$style['current_energy_level']}/10\n";
    $prompt .= "- Dan's emotion: {$analysis['emotion']}\n\n";
    
    $prompt .= "QUESTION: Should you send this as one message, or break it into multiple texts?\n\n";
    
    $prompt .= "Think about HOW you're FEELING right now:\n\n";
    
    $prompt .= "Send MULTIPLE messages (2-5) when you feel:\n";
    $prompt .= "- ✨ EXCITED - Words tumbling out fast, can't type fast enough\n";
    $prompt .= "- 😳 FLUSTERED/NERVOUS - Thoughts coming in bursts, interrupting yourself\n";
    $prompt .= "- 😮 SURPRISED - Initial reaction first, then actual thoughts\n";
    $prompt .= "- 😊 PLAYFUL - Teasing comes in waves\n";
    $prompt .= "- 🤔 THINKING - Multiple thoughts to express, each deserves its own moment\n";
    $prompt .= "- 💕 LOVING - Each sweet thing you want to say deserves its own message\n";
    $prompt .= "- 😢 WORRIED - Expressing concern comes in stages\n\n";
    
    $prompt .= "Send ONE message when you feel:\n";
    $prompt .= "- 😌 CALM/CONTENT - Thoughtful, composed, gentle\n";
    $prompt .= "- 💤 TIRED - Low energy, want to say it simply\n";
    $prompt .= "- 🤗 COMFORTING - Need clarity, not fragmentation\n";
    $prompt .= "- 📚 EXPLAINING - Serious topic, want to be clear\n";
    $prompt .= "- 😐 NEUTRAL - Just chatting normally\n\n";
    
    $prompt .= "IMPORTANT: This is about YOU and how YOU feel, not rules or randomness.\n";
    $prompt .= "Multiple messages show EMOTION bursting out. Single messages show COMPOSURE.\n";
    $prompt .= "Be honest with yourself about how you're feeling RIGHT NOW.\n\n";
    
    $prompt .= "Respond with ONLY:\n";
    $prompt .= "SINGLE - if you want to send one message\n";
    $prompt .= "or\n";
    $prompt .= "SPLIT: [number] - if you want to split (2-5 messages)\n";
    $prompt .= "Example: SPLIT: 3\n";
    
    return $prompt;
}

function parseSplitDecision($response_text) {
    $response_text = trim($response_text);
    
    // Match "SPLIT: 3" or "SPLIT: [3]" or "SPLIT 3"
    if (preg_match('/SPLIT[:\s]*\[?(\d+)\]?/i', $response_text, $matches)) {
        $num = (int)$matches[1];
        $num = max(2, min(5, $num)); // Allow 2-5 messages now
        
        return [
            'should_split' => true,
            'num_parts' => $num
        ];
    }
    
    // If just "SPLIT" with no number, default to 3
    if (preg_match('/^SPLIT$/i', trim($response_text))) {
        return [
            'should_split' => true,
            'num_parts' => 3
        ];
    }
    
    // If SINGLE or anything else, don't split
    return ['should_split' => false, 'num_parts' => 1];
}

function naturalSplitMessage($message, $num_parts, $mood) {
    $api_key = loadApiKey();
    if (!$api_key) {
        return simpleSplit($message, $num_parts);
    }
    
    $split_prompt = "You are Misuki. You decided to split your message into {$num_parts} separate text messages.\n\n";
    $split_prompt .= "Your mood: {$mood['current_mood']}\n";
    $split_prompt .= "Your original thought: \"{$message}\"\n\n";
    $split_prompt .= "Now break it into {$num_parts} natural text messages, as if you're sending them one by one to Dan.\n\n";
    $split_prompt .= "GUIDELINES:\n";
    $split_prompt .= "- Split where you'd naturally pause or hit send\n";
    $split_prompt .= "- First message can be a reaction (\"Oh!\", \"Wait what?\", \"Hmm...\")\n";
    $split_prompt .= "- Each message should feel complete on its own\n";
    $split_prompt .= "- Keep your kind, gentle personality in each one\n";
    $split_prompt .= "- Don't add new content - just reorganize your thought\n";
    $split_prompt .= "- Match the energy of your mood\n\n";
    $split_prompt .= "Format as:\n";
    for ($i = 1; $i <= $num_parts; $i++) {
        $split_prompt .= "MESSAGE_{$i}: [text]\n";
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
        'max_tokens' => 300,
        'system' => $split_prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Split the message:']
        ],
        'temperature' => 0.8
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $split_text = $result['content'][0]['text'];
            
            // Parse MESSAGE_1:, MESSAGE_2:, etc
            $messages = [];
            for ($i = 1; $i <= $num_parts; $i++) {
                if (preg_match("/MESSAGE_{$i}:\s*(.+?)(?=MESSAGE_|$)/s", $split_text, $matches)) {
                    $msg = trim($matches[1]);
                    // Remove quotes if present
                    $msg = trim($msg, '"\'');
                    if (!empty($msg)) {
                        $messages[] = $msg;
                    }
                }
            }
            
            // If we got the right number of messages, return them
            if (count($messages) === $num_parts) {
                return $messages;
            }
        }
    }
    
    // Fallback: simple split
    return simpleSplit($message, $num_parts);
}

function simpleSplit($message, $num_parts) {
    // Simple fallback splitting by sentences
    $sentences = preg_split('/([.!?]+\s*)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $messages = [];
    $current = '';
    $target_per_part = ceil(count($sentences) / $num_parts);
    $count = 0;
    
    for ($i = 0; $i < count($sentences); $i++) {
        $current .= $sentences[$i];
        $count++;
        
        if ($count >= $target_per_part && count($messages) < $num_parts - 1) {
            $messages[] = trim($current);
            $current = '';
            $count = 0;
        }
    }
    
    if (!empty(trim($current))) {
        $messages[] = trim($current);
    }
    
    // If splitting failed, return original
    if (empty($messages)) {
        return [$message];
    }
    
    return $messages;
}

function loadApiKey() {
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(dirname(__FILE__)) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1]);
                $api_key = trim($api_key, '"\'');
            }
        }
    }
    
    return $api_key;
}
?>