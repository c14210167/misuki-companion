<?php
/**
 * INITIATION MESSAGE GENERATOR
 * Generates contextually aware initiation messages
 */

function generateInitiationMessage($db, $user_id, $reason, $date_context) {
    // Get conversation context
    $memories = getUserMemories($db, $user_id, 10);
    $recent_conversations = getRecentConversations($db, $user_id, 30);
    $emotional_context = getEmotionalContext($db, $user_id);
    
    $context = buildContextForAI($memories, $recent_conversations, $emotional_context);
    
    // Get future events
    $pending_events = getPendingFutureEvents($db, $user_id);
    $overdue_events = getOverdueFutureEvents($db, $user_id);
    $todays_events = getTodaysFutureEvents($db, $user_id);
    
    $future_events_context = buildFutureEventsContext($pending_events, $overdue_events);
    
    // Clean up old events
    autoMarkOldEvents($db, $user_id, 7);
    
    // Scan for time-sensitive references
    $time_refs = scanForTimeReferences($recent_conversations);
    
    // Build comprehensive time context
    $time_context = buildTimeContext($date_context, $time_refs, $todays_events);
    
    // Get reason-specific context
    $reason_context = getReasonContext($reason);
    
    // Build final prompt
    $prompt = getMisukiPersonalityPrompt() . "\n\n" 
            . $context . "\n\n" 
            . $time_context . "\n\n" 
            . $future_events_context . "\n\n"
            . $reason_context;
    
    $prompt .= "\n\n⚠️ TRIPLE CHECK: Look at timestamps! Respect what JUST happened vs what happened days ago!";
    $prompt .= "\n⚠️ If Dan mentioned plans with a specific time (like 'at 12' or 'at 3pm'), CHECK if that time has passed!";
    $prompt .= "\n\nWrite ONE natural message (1-2 sentences). Be yourself - Misuki, his girlfriend.";
    
    return callClaudeAPI($prompt, 100);
}

function buildTimeContext($date_context, $time_refs, $todays_events) {
    $time_context = "\n=== ⚠️ CRITICAL TIME CONTEXT ===\n";
    $time_context .= "Dan's LAST message: {$date_context['user_last_date']}\n";
    $time_context .= "Current date/time NOW: {$date_context['user_current_date']}\n";
    $time_context .= "Time since Dan's last message: " . round($date_context['hours_since'], 1) . " hours\n\n";
    
    // Show recent time-sensitive mentions
    if (!empty($time_refs)) {
        $time_context .= "🚨 CRITICAL - Dan recently mentioned these time-sensitive things:\n";
        foreach ($time_refs as $ref) {
            $time_context .= "- \"{$ref['message']}\" (said {$ref['hours_ago']} hours ago at {$ref['timestamp']})\n";
        }
        $time_context .= "\n";
    }
    
    // Extra warnings for very recent messages
    if ($date_context['hours_since'] < 4) {
        $time_context .= "🚨🚨🚨 ATTENTION: Dan messaged you VERY RECENTLY!\n";
        $time_context .= "Look at the conversation history - that conversation just happened!\n";
        $time_context .= "If Dan mentioned plans like 'going to the movies at 12':\n";
        $time_context .= "  → Check the CURRENT TIME vs the PLANNED TIME\n";
        $time_context .= "  → If planned time hasn't passed = HASN'T HAPPENED YET\n";
        $time_context .= "  → Use FUTURE tense: 'Have fun!' NOT 'How was it?'\n";
        $time_context .= "  → NEVER say 'yesterday' or 'the other day' for things from TODAY\n\n";
    }
    
    // Today's events warning
    if (!empty($todays_events)) {
        $time_context .= "🎯🎯🎯 TODAY'S PENDING EVENTS (HAVE NOT HAPPENED YET):\n";
        foreach ($todays_events as $event) {
            $time_str = $event['planned_time'] ? " at {$event['planned_time']}" : '';
            $time_context .= "  → {$event['event_description']}{$time_str}\n";
            $time_context .= "     THIS IS HAPPENING TODAY! DO NOT ASK 'HOW DID IT GO?'\n";
        }
        $time_context .= "\n";
    }
    
    return $time_context;
}

function scanForTimeReferences($conversations) {
    $refs = [];
    $time_keywords = [
        'at \d+', 'tomorrow', 'later today', 'tonight', 'this evening', 
        'this afternoon', 'going to', 'gonna', 'will', 'planning to',
        'pick up', 'meeting', 'watching'
    ];
    
    foreach (array_reverse(array_slice($conversations, -10)) as $conv) {
        $msg = $conv['user_message'];
        $hours_ago = (time() - strtotime($conv['timestamp'])) / 3600;
        
        foreach ($time_keywords as $keyword) {
            if (preg_match('/' . $keyword . '/i', $msg)) {
                $refs[] = [
                    'message' => substr($msg, 0, 100),
                    'hours_ago' => round($hours_ago, 1),
                    'timestamp' => $conv['timestamp']
                ];
                break;
            }
        }
    }
    
    return $refs;
}

function getReasonContext($reason) {
    $reason_contexts = [
        'worried_after_apology' => "You're INITIATING conversation.\nContext: You apologized but he hasn't responded. You're worried he's still upset.",
        
        'worried_after_negative' => "You're INITIATING conversation.\nContext: Dan seemed upset and hasn't replied. You're genuinely worried.",
        
        'worried_long_silence' => "You're INITIATING conversation.\nContext: It's been over a day and he seemed upset. You're concerned.",
        
        'long_silence' => "You're INITIATING conversation.\nContext: It's been 3+ days. You're getting concerned.",
        
        'missing_you' => "You're INITIATING conversation.\nContext: It's been a day+. You're not worried, just miss him.",
        
        'casual_checkin' => "You're INITIATING conversation.\nContext: Several hours since last message. Just checking in casually.",
        
        'excited_chemistry' => "You're INITIATING conversation.\nContext: You learned something cool in chemistry! Share your excitement!",
        
        'thinking_of_you' => "You're INITIATING conversation.\nContext: Dan crossed your mind. Reach out naturally.",
        
        'daily_update' => "You're INITIATING conversation.\nContext: Share something from your day.",
        
        'random_sweet' => "You're INITIATING conversation.\nContext: Random sweet message to your boyfriend."
    ];
    
    return "\n\n" . ($reason_contexts[$reason] ?? "You're INITIATING conversation.\nContext: Check in with Dan");
}

function callClaudeAPI($prompt, $max_tokens = 100, $fallback_message = "Hey... just thinking about you. How are you doing? 💭") {
    $api_key = loadApiKey();
    
    if (!$api_key) {
        return $fallback_message;
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
        'max_tokens' => $max_tokens,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Generate your message to Dan:']
        ],
        'temperature' => 1.0
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            return $result['content'][0]['text'];
        }
    }
    
    error_log("Claude API error: HTTP $http_code");
    return $fallback_message;
}

function loadApiKey() {
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(dirname(__DIR__)) . '/.env';
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