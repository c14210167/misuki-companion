<?php
// Future Events Detection and Tracking

function detectFutureEvent($message) {
    $message_lower = strtolower(trim($message));
    
    // Patterns that indicate future plans
    $future_patterns = [
        // Tomorrow patterns
        'tomorrow' => [
            '/(?:i\'m|im|i am|gonna|going to|will|planning to)\s+(.+?)\s+tomorrow/i',
            '/tomorrow\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i'
        ],
        // Later/tonight patterns
        'today' => [
            '/(?:later|tonight|this evening|this afternoon)\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i',
            '/(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)\s+(?:later|tonight|this evening|this afternoon)/i'
        ],
        // Specific day patterns
        'future' => [
            '/(?:on|this)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i',
            '/(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)\s+(?:on|this)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i',
            '/(?:next week|next month)\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i'
        ]
    ];
    
    $result = [
        'has_future_event' => false,
        'event_description' => null,
        'time_frame' => null,
        'planned_date' => null
    ];
    
    foreach ($future_patterns as $time_frame => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message_lower, $matches)) {
                $result['has_future_event'] = true;
                $result['time_frame'] = $time_frame;
                
                // Extract event description
                if (isset($matches[1])) {
                    $result['event_description'] = trim($matches[1]);
                }
                
                // Calculate planned date
                if ($time_frame === 'tomorrow') {
                    $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
                } elseif ($time_frame === 'today') {
                    $result['planned_date'] = date('Y-m-d');
                }
                
                return $result;
            }
        }
    }
    
    return $result;
}

function saveFutureEvent($db, $user_id, $event_description, $planned_date) {
    // Check if similar event already exists
    $stmt = $db->prepare("
        SELECT event_id FROM future_events 
        WHERE user_id = ? 
        AND status = 'pending' 
        AND LOWER(event_description) LIKE ?
        AND planned_date >= CURDATE()
    ");
    $similar = '%' . strtolower($event_description) . '%';
    $stmt->execute([$user_id, $similar]);
    
    if ($stmt->fetch()) {
        // Event already tracked
        return false;
    }
    
    // Determine event type
    $event_type = 'doing_activity';
    if (preg_match('/watch|see|viewing/i', $event_description)) {
        $event_type = 'watching_movie';
    } elseif (preg_match('/go to|going to|visit/i', $event_description)) {
        $event_type = 'going_somewhere';
    } elseif (preg_match('/meet|meeting|see|hang out/i', $event_description)) {
        $event_type = 'meeting_someone';
    }
    
    $stmt = $db->prepare("
        INSERT INTO future_events 
        (user_id, event_description, event_type, planned_date, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    
    return $stmt->execute([$user_id, $event_description, $event_type, $planned_date]);
}

function getPendingFutureEvents($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM future_events 
        WHERE user_id = ? 
        AND status = 'pending'
        AND (planned_date IS NULL OR planned_date >= CURDATE())
        ORDER BY planned_date ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOverdueFutureEvents($db, $user_id) {
    // Events that should have happened by now
    $stmt = $db->prepare("
        SELECT * FROM future_events 
        WHERE user_id = ? 
        AND status = 'pending'
        AND planned_date < CURDATE()
        ORDER BY planned_date DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markEventAsCompleted($db, $event_id) {
    $stmt = $db->prepare("
        UPDATE future_events 
        SET status = 'completed', completed_at = NOW() 
        WHERE event_id = ?
    ");
    return $stmt->execute([$event_id]);
}

function detectEventCompletion($message, $pending_events) {
    // Check if Dan is talking about completing a pending event
    $message_lower = strtolower($message);
    
    // Past tense indicators
    $past_patterns = [
        '/(?:i|just|finally)\s+(watched|saw|went to|visited|did|finished|completed)/i',
        '/(?:watched|saw|went|visited|did|finished)\s+(.+?)\s+(?:yesterday|today|earlier|just now)/i'
    ];
    
    foreach ($pending_events as $event) {
        $event_desc_lower = strtolower($event['event_description']);
        
        // Check if message mentions this event in past tense
        foreach ($past_patterns as $pattern) {
            if (preg_match($pattern, $message_lower)) {
                // Check if event description keywords are in message
                $keywords = explode(' ', $event_desc_lower);
                $match_count = 0;
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) > 3 && strpos($message_lower, $keyword) !== false) {
                        $match_count++;
                    }
                }
                
                // If 2+ keywords match, likely talking about this event
                if ($match_count >= 2) {
                    return $event['event_id'];
                }
            }
        }
    }
    
    return null;
}

function buildFutureEventsContext($pending_events, $overdue_events) {
    if (empty($pending_events) && empty($overdue_events)) {
        return '';
    }
    
    $context = "\n=== DAN'S PLANNED EVENTS ===\n";
    
    if (!empty($overdue_events)) {
        $context .= "⚠️ Events Dan mentioned but MAY NOT have done yet (be careful!):\n";
        foreach ($overdue_events as $event) {
            $days_ago = floor((time() - strtotime($event['planned_date'])) / 86400);
            $context .= "- {$event['event_description']} (was planning to do {$days_ago} day(s) ago)\n";
            $context .= "  → Don't assume he did this! He might have postponed it or not done it yet.\n";
            $context .= "  → If you want to ask about it, phrase it carefully: 'Did you end up...?' or 'Were you able to...?'\n";
        }
        $context .= "\n";
    }
    
    if (!empty($pending_events)) {
        $context .= "📅 Upcoming events Dan mentioned:\n";
        foreach ($pending_events as $event) {
            if ($event['planned_date']) {
                $date_str = date('l, F j', strtotime($event['planned_date']));
                $context .= "- {$event['event_description']} (planned for {$date_str})\n";
            } else {
                $context .= "- {$event['event_description']} (upcoming)\n";
            }
        }
        $context .= "→ These events HAVEN'T happened yet! Don't ask about them in past tense!\n";
    }
    
    return $context;
}

function autoMarkOldEvents($db, $user_id, $days_old = 7) {
    // Auto-mark very old pending events as completed (cleanup)
    $stmt = $db->prepare("
        UPDATE future_events 
        SET status = 'completed', completed_at = NOW() 
        WHERE user_id = ? 
        AND status = 'pending' 
        AND planned_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ");
    return $stmt->execute([$user_id, $days_old]);
}

?>