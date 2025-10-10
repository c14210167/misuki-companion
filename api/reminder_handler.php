<?php
// Natural Language Reminder Detection and Handling

function detectReminderRequest($message) {
    $message_lower = strtolower(trim($message));
    
    // BLOCK: Comparison phrases (NOT reminders)
    $comparison_patterns = [
        '/you remind me of/',
        '/reminds? me of/',
        '/reminded me of/',
        '/that reminds?/',
        '/this reminds?/',
        '/it reminds?/',
        '/as a reminder/',
        '/just a reminder/',
        '/good reminder/',
        '/nice reminder/'
    ];
    
    foreach ($comparison_patterns as $pattern) {
        if (preg_match($pattern, $message_lower)) {
            return false; // Definitely NOT a reminder request
        }
    }
    
    // BLOCK: Past tense (talking about memory)
    if (preg_match('/(reminded|remembered)\s+(me|you)/', $message_lower)) {
        return false;
    }
    
    // BLOCK: Third person
    if (preg_match('/(he|she|they|someone)\s+remind/', $message_lower)) {
        return false;
    }
    
    // CHECK: Must have command verb
    $command_verbs = [
        'remind me',
        'tell me',
        'ping me',
        'let me know',
        'notify me',
        'alert me',
        'don\'t let me forget',
        'make sure i',
        'help me remember'
    ];
    
    $has_command = false;
    foreach ($command_verbs as $verb) {
        if (strpos($message_lower, $verb) !== false) {
            $has_command = true;
            break;
        }
    }
    
    if (!$has_command) return false;
    
    // CHECK: Must have time indicator
    $time_patterns = [
        '/in\s+(\d+)\s*(min|minute|minutes|mins?|hr|hour|hours|hrs?|day|days)s?\b/',
        '/at\s+(\d{1,2})\s*(am|pm|:\d{2})/',
        '/tomorrow/',
        '/later\s+today/',
        '/tonight/',
        '/this\s+(morning|afternoon|evening)/'
    ];
    
    $has_time = false;
    foreach ($time_patterns as $pattern) {
        if (preg_match($pattern, $message_lower)) {
            $has_time = true;
            break;
        }
    }
    
    if (!$has_time) return false;
    
    // If we got here, it's likely a reminder request
    return true;
}

function parseReminderDetails($message) {
    $message_lower = strtolower($message);
    
    $result = [
        'success' => false,
        'remind_at' => null,
        'reminder_text' => null,
        'confidence' => 0,
        'needs_confirmation' => false
    ];
    
    // Extract time
    $time_info = extractTimeFromMessage($message_lower);
    if (!$time_info['success']) {
        return $result;
    }
    
    $result['remind_at'] = $time_info['timestamp'];
    $result['confidence'] += 40;
    
    // Extract reminder text/action
    $reminder_text = extractReminderAction($message);
    if (empty($reminder_text)) {
        $result['needs_confirmation'] = true;
        $result['reminder_text'] = 'Something (please clarify what)';
    } else {
        $result['reminder_text'] = $reminder_text;
        $result['confidence'] += 30;
    }
    
    // Confidence boosters
    if (preg_match('/(please|pls|plz|can you|could you)/i', $message)) {
        $result['confidence'] += 10;
    }
    
    if (preg_match('/\?|okay\?|ok\?/i', $message)) {
        $result['confidence'] += 10;
    }
    
    if (preg_match('/(remind me|tell me)/i', $message)) {
        $result['confidence'] += 20;
    }
    
    $result['success'] = true;
    $result['confidence'] = min(100, $result['confidence']);
    
    return $result;
}

function extractTimeFromMessage($message_lower) {
    $result = ['success' => false, 'timestamp' => null, 'description' => ''];
    
    // Pattern 1: "in X minutes/hours/days"
    if (preg_match('/in\s+(\d+)\s*(min|minute|minutes|mins?|hr|hour|hours|hrs?|day|days)s?\b/', $message_lower, $matches)) {
        $amount = (int)$matches[1];
        $unit = $matches[2];
        
        // Normalize unit
        if (in_array($unit, ['min', 'minute', 'minutes', 'mins'])) {
            $seconds = $amount * 60;
            $desc = "$amount minute" . ($amount > 1 ? 's' : '');
        } elseif (in_array($unit, ['hr', 'hour', 'hours', 'hrs'])) {
            $seconds = $amount * 3600;
            $desc = "$amount hour" . ($amount > 1 ? 's' : '');
        } elseif (in_array($unit, ['day', 'days'])) {
            $seconds = $amount * 86400;
            $desc = "$amount day" . ($amount > 1 ? 's' : '');
        } else {
            return $result;
        }
        
        // Check reasonable range (5 min to 7 days)
        if ($seconds < 300 || $seconds > 604800) {
            return $result;
        }
        
        $result['timestamp'] = date('Y-m-d H:i:s', time() + $seconds);
        $result['description'] = $desc;
        $result['success'] = true;
        return $result;
    }
    
    // Pattern 2: "at 3pm" or "at 15:00"
    if (preg_match('/at\s+(\d{1,2})\s*(?::(\d{2}))?\s*(am|pm)?/i', $message_lower, $matches)) {
        $hour = (int)$matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
        $meridiem = isset($matches[3]) ? strtolower($matches[3]) : null;
        
        // Convert to 24-hour format
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }
        
        // If no meridiem and hour is ambiguous, assume PM if hour < 12 and it's past that time
        if ($meridiem === null && $hour < 12) {
            $current_hour = (int)date('G');
            if ($hour <= $current_hour) {
                $hour += 12; // Assume PM
            }
        }
        
        $target_time = mktime($hour, $minute, 0);
        $now = time();
        
        // If target time is in the past, assume tomorrow
        if ($target_time < $now) {
            $target_time += 86400;
        }
        
        $result['timestamp'] = date('Y-m-d H:i:s', $target_time);
        $result['description'] = date('g:i A', $target_time);
        $result['success'] = true;
        return $result;
    }
    
    // Pattern 3: "tomorrow" or "tomorrow at X"
    if (preg_match('/tomorrow/', $message_lower)) {
        $tomorrow = strtotime('tomorrow 09:00');
        
        // Check if time specified
        if (preg_match('/tomorrow\s+at\s+(\d{1,2})\s*(?::(\d{2}))?\s*(am|pm)?/i', $message_lower, $matches)) {
            $hour = (int)$matches[1];
            $minute = isset($matches[2]) ? (int)$matches[2] : 0;
            $meridiem = isset($matches[3]) ? strtolower($matches[3]) : 'am';
            
            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            }
            
            $tomorrow = mktime($hour, $minute, 0, date('n'), date('j') + 1, date('Y'));
        }
        
        $result['timestamp'] = date('Y-m-d H:i:s', $tomorrow);
        $result['description'] = 'tomorrow at ' . date('g:i A', $tomorrow);
        $result['success'] = true;
        return $result;
    }
    
    return $result;
}

function extractReminderAction($message) {
    // Try to extract what to remind about
    
    // Pattern 1: "remind me to [action]"
    if (preg_match('/remind me\s+to\s+(.+?)(?:\s+in\s+|\s+at\s+|$)/i', $message, $matches)) {
        return trim($matches[1]);
    }
    
    // Pattern 2: "remind me about [thing]"
    if (preg_match('/remind me\s+about\s+(.+?)(?:\s+in\s+|\s+at\s+|$)/i', $message, $matches)) {
        return trim($matches[1]);
    }
    
    // Pattern 3: "remind me [thing] in"
    if (preg_match('/remind me\s+(.+?)\s+in\s+\d+/i', $message, $matches)) {
        $action = trim($matches[1]);
        if (!in_array(strtolower($action), ['to', 'about', 'that'])) {
            return $action;
        }
    }
    
    // Pattern 4: "in X time to [action]"
    if (preg_match('/in\s+\d+\s+\w+\s+to\s+(.+?)$/i', $message, $matches)) {
        return trim($matches[1]);
    }
    
    // Pattern 5: "[action] reminder in X"
    if (preg_match('/^(.+?)\s+reminder\s+in\s+/i', $message, $matches)) {
        return trim($matches[1]);
    }
    
    return '';
}

function saveReminder($db, $user_id, $reminder_text, $remind_at, $confidence, $original_message) {
    $stmt = $db->prepare("
        INSERT INTO reminders (user_id, reminder_text, remind_at, confidence_score, original_message)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$user_id, $reminder_text, $remind_at, $confidence, $original_message]);
}

function getPendingReminders($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM reminders 
        WHERE user_id = ? AND status = 'pending' AND remind_at > NOW()
        ORDER BY remind_at ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDueReminders($db) {
    $stmt = $db->prepare("
        SELECT * FROM reminders 
        WHERE status = 'pending' AND remind_at <= NOW()
        ORDER BY remind_at ASC
        LIMIT 50
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markReminderAsSent($db, $reminder_id) {
    $stmt = $db->prepare("
        UPDATE reminders 
        SET status = 'sent' 
        WHERE reminder_id = ?
    ");
    return $stmt->execute([$reminder_id]);
}

function cancelReminder($db, $user_id, $reminder_id = null) {
    if ($reminder_id) {
        $stmt = $db->prepare("
            UPDATE reminders 
            SET status = 'cancelled' 
            WHERE reminder_id = ? AND user_id = ?
        ");
        return $stmt->execute([$reminder_id, $user_id]);
    } else {
        // Cancel most recent pending reminder
        $stmt = $db->prepare("
            UPDATE reminders 
            SET status = 'cancelled' 
            WHERE user_id = ? AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        return $stmt->execute([$user_id]);
    }
}

function generateReminderResponse($reminder_details, $time_description) {
    $responses = [
        "Got it! I'll remind you in {time} about {action}! 💕",
        "Sure thing! Reminder set for {time} - {action}! 😊",
        "Okay! I'll ping you in {time} to {action}! ✨",
        "Done! I'll remind you {time} from now about {action}! 💭",
        "Consider it done! {time} reminder for {action} set! 💕"
    ];
    
    $template = $responses[array_rand($responses)];
    
    $action = $reminder_details['reminder_text'];
    
    return str_replace(['{time}', '{action}'], [$time_description, $action], $template);
}

function generateReminderConfirmation($reminder_details, $time_description) {
    $action = $reminder_details['reminder_text'];
    
    return "Just to make sure - you want me to remind you in {$time_description} about: {$action}? 😊";
}

function generateReminderMessage($reminder) {
    $messages = [
        "⏰ Reminder: {action}!",
        "💕 Hey! Time to {action}!",
        "⏰ Don't forget: {action}!",
        "Reminder! {action} 😊",
        "⏰ You asked me to remind you: {action}!"
    ];
    
    $template = $messages[array_rand($messages)];
    $action = $reminder['reminder_text'];
    
    return str_replace('{action}', $action, $template);
}

?>