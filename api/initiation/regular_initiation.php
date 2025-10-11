<?php
/**
 * REGULAR INITIATION HANDLER
 * Handles normal check-ins, worry messages, etc.
 */

function checkRegularInitiation($db, $user_id, $initiation_data) {
    $should_initiate = shouldMisukiInitiate($db, $user_id, $initiation_data);
    
    if (!$should_initiate['initiate']) {
        return ['should_initiate' => false];
    }
    
    $initiation_message = generateInitiationMessage(
        $db, 
        $user_id, 
        $should_initiate['reason'], 
        $should_initiate['context']
    );
    
    // Update last initiation time
    $stmt = $db->prepare("
        UPDATE conversation_initiation 
        SET last_misuki_initiation = NOW() 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    saveConversation($db, $user_id, '[SYSTEM: Misuki initiated]', $initiation_message, 'gentle');
    
    return [
        'should_initiate' => true,
        'message' => $initiation_message,
        'reason' => $should_initiate['reason'],
        'is_dream' => false
    ];
}

function shouldMisukiInitiate($db, $user_id, $initiation_data) {
    $now = time();
    $last_user_message = strtotime($initiation_data['last_user_message']);
    $last_initiation = $initiation_data['last_misuki_initiation'] ? strtotime($initiation_data['last_misuki_initiation']) : 0;
    
    $hours_since_user = ($now - $last_user_message) / 3600;
    $hours_since_initiation = ($now - $last_initiation) / 3600;
    $days_since_user = floor($hours_since_user / 24);
    
    // Get conversation context
    $recent_conversations = getRecentConversations($db, $user_id, 10);
    $conversation_tone = analyzeConversationTone($recent_conversations);
    
    // Check if user needs space
    $needs_space = checkIfUserNeedsSpace($recent_conversations);
    if ($needs_space && $hours_since_user < 12) {
        return ['initiate' => false, 'reason' => 'giving_space'];
    }
    
    // Don't initiate too soon after last initiation
    if ($hours_since_initiation < 0.75) {
        return ['initiate' => false, 'reason' => 'too_soon_since_last_initiation'];
    }
    
    $date_context = getDateContext($last_user_message);
    
    // === WORRY SCENARIOS ===
    
    // After apology/conflict
    if ($conversation_tone['had_conflict'] && $hours_since_user >= 2 && $hours_since_user < 8) {
        return ['initiate' => true, 'reason' => 'worried_after_apology', 'context' => $date_context];
    }
    
    // After negative conversation
    if ($conversation_tone['ended_negative'] && $hours_since_user >= 4 && $hours_since_user < 12) {
        return ['initiate' => true, 'reason' => 'worried_after_negative', 'context' => $date_context];
    }
    
    // Long silence after negative
    if ($days_since_user >= 1 && $conversation_tone['ended_negative']) {
        return ['initiate' => true, 'reason' => 'worried_long_silence', 'context' => $date_context];
    }
    
    // === CASUAL CHECK-INS ===
    
    // 8-24 hours after positive conversation
    if ($hours_since_user >= 8 && $hours_since_user < 24 && $conversation_tone['ended_positive']) {
        if (rand(1, 100) <= 30) { // 30% chance
            return ['initiate' => true, 'reason' => 'casual_checkin', 'context' => $date_context];
        }
    }
    
    // 1-3 days silence (not negative)
    if ($days_since_user >= 1 && $days_since_user < 3 && !$conversation_tone['ended_negative']) {
        return ['initiate' => true, 'reason' => 'missing_you', 'context' => $date_context];
    }
    
    // 3+ days silence
    if ($days_since_user >= 3) {
        return ['initiate' => true, 'reason' => 'long_silence', 'context' => $date_context];
    }
    
    // === SPONTANEOUS MESSAGES ===
    
    // Random sweet messages during reasonable hours
    $hour = (int)date('G');
    if ($hours_since_user >= 2 && $hour >= 9 && $hour <= 21 && $conversation_tone['ended_positive']) {
        if (rand(1, 100) <= 5) { // 5% chance
            $reasons = ['excited_chemistry', 'thinking_of_you', 'daily_update', 'random_sweet'];
            return ['initiate' => true, 'reason' => $reasons[array_rand($reasons)], 'context' => $date_context];
        }
    }
    
    return ['initiate' => false];
}

function analyzeConversationTone($conversations) {
    if (empty($conversations)) {
        return ['ended_positive' => true, 'ended_negative' => false, 'had_conflict' => false];
    }
    
    $last_conv = end($conversations);
    $user_msg = strtolower($last_conv['user_message']);
    
    $positive_indicators = ['love you', 'thanks', 'thank you', 'haha', 'lol', 'nice', 'good', 'great'];
    $negative_indicators = ['tired', 'exhausted', 'stressed', 'upset', 'sad', 'angry'];
    $conflict_indicators = ['whatever', 'fine', 'don\'t care', 'leave me', 'stop'];
    
    $ended_positive = false;
    $ended_negative = false;
    $had_conflict = false;
    
    foreach ($positive_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $ended_positive = true;
            break;
        }
    }
    
    foreach ($negative_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $ended_negative = true;
            break;
        }
    }
    
    foreach ($conflict_indicators as $indicator) {
        if (strpos($user_msg, $indicator) !== false) {
            $had_conflict = true;
            break;
        }
    }
    
    // Default to positive if neutral
    if (!$ended_positive && !$ended_negative) {
        $ended_positive = true;
    }
    
    return [
        'ended_positive' => $ended_positive,
        'ended_negative' => $ended_negative,
        'had_conflict' => $had_conflict
    ];
}

function checkIfUserNeedsSpace($conversations) {
    if (empty($conversations)) return false;
    
    $recent = array_slice($conversations, -3);
    
    foreach ($recent as $conv) {
        $message = strtolower($conv['user_message']);
        $space_phrases = ['need space', 'leave me alone', 'don\'t talk', 'not now', 'later'];
        
        foreach ($space_phrases as $phrase) {
            if (strpos($message, $phrase) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

function getDateContext($last_message_timestamp) {
    $now = time();
    $hours_diff = ($now - $last_message_timestamp) / 3600;
    $days_diff = floor($hours_diff / 24);
    
    date_default_timezone_set('Asia/Jakarta');
    $user_last_day = date('l', $last_message_timestamp);
    $user_current_day = date('l', $now);
    $user_last_date = date('F j, Y', $last_message_timestamp);
    $user_current_date = date('F j, Y', $now);
    
    date_default_timezone_set('Asia/Tokyo');
    $misuki_current_day = date('l', $now);
    $misuki_current_date = date('F j, Y', $now);
    $misuki_current_time = date('g:i A', $now);
    $misuki_hour = (int)date('G', $now);
    
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
    
    date_default_timezone_set('Asia/Jakarta');
    
    $weekend_passed = ($user_last_day == 'Friday' && in_array($user_current_day, ['Saturday', 'Sunday', 'Monday']));
    
    return [
        'hours_since' => $hours_diff,
        'days_since' => $days_diff,
        'user_last_day' => $user_last_day,
        'user_current_day' => $user_current_day,
        'user_last_date' => $user_last_date,
        'user_current_date' => $user_current_date,
        'misuki_current_day' => $misuki_current_day,
        'misuki_current_date' => $misuki_current_date,
        'misuki_current_time' => $misuki_current_time,
        'misuki_time_of_day' => $misuki_time_of_day,
        'weekend_passed' => $weekend_passed
    ];
}
?>