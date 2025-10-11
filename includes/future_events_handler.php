<?php
// Future Events Detection and Tracking - COMPLETE FIXED VERSION

function detectFutureEvent($message) {
    $message_lower = strtolower(trim($message));
    
    $result = [
        'has_future_event' => false,
        'event_description' => null,
        'time_frame' => null,
        'planned_date' => null,
        'planned_time' => null
    ];
    
    // ===== PRIORITY 1: "at [time]" patterns (HIGHEST PRIORITY) =====
    
    // Pattern 1: "pick up [someone] at [time]"
    if (preg_match('/pick(?:ing)?\s+up\s+(.+?)\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $message_lower, $matches)) {
        $who = trim($matches[1]);
        $hour = (int)$matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
        $meridiem = isset($matches[4]) && $matches[4] !== '' ? strtolower($matches[4]) : '';
        
        // Convert to 24-hour
        $current_hour = (int)date('G');
        
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        } elseif ($meridiem === '' && $hour === 12) {
            // Special case: "at 12" with no AM/PM
            // If it's currently past noon, assume they mean noon tomorrow
            if ($current_hour >= 12) {
                // Keep hour = 12, but flag for tomorrow
                $is_tomorrow = true;
            }
            // If it's morning, assume noon today
        } elseif ($meridiem === '' && $hour < 12) {
            // For hours 1-11 without AM/PM
            if ($hour <= $current_hour) {
                $hour += 12; // Assume PM if time already passed
            }
        }
        
        $current_minute = (int)date('i');
        $current_time_minutes = ($current_hour * 60) + $current_minute;
        $event_time_minutes = ($hour * 60) + $minute;
        
        // Handle "tomorrow" scenario for "at 12" after noon
        if (isset($is_tomorrow) && $is_tomorrow) {
            $result['has_future_event'] = true;
            $result['event_description'] = "picking up $who";
            $result['time_frame'] = 'tomorrow';
            $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
            $result['planned_time'] = '12:00:00'; // Noon tomorrow
            error_log("✅ Detected: picking up $who at noon tomorrow");
            return $result;
        }
        
        if ($event_time_minutes > $current_time_minutes) {
            $result['has_future_event'] = true;
            $result['event_description'] = "picking up $who";
            $result['time_frame'] = 'today';
            $result['planned_date'] = date('Y-m-d');
            $result['planned_time'] = sprintf('%02d:%02d:00', $hour, $minute);
            error_log("✅ Detected: picking up $who at $hour:$minute");
            return $result;
        }
    }
    
    // Pattern 2: "meeting [someone] at [time]"
    if (preg_match('/meet(?:ing)?\s+(.+?)\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $message_lower, $matches)) {
        $who = trim($matches[1]);
        $hour = (int)$matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
        $meridiem = isset($matches[4]) && $matches[4] !== '' ? strtolower($matches[4]) : '';
        
        // Convert to 24-hour
        $current_hour = (int)date('G');
        
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        } elseif ($meridiem === '' && $hour === 12) {
            if ($current_hour >= 12) {
                $is_tomorrow = true;
            }
        } elseif ($meridiem === '' && $hour < 12) {
            if ($hour <= $current_hour) {
                $hour += 12;
            }
        }
        
        $current_minute = (int)date('i');
        $current_time_minutes = ($current_hour * 60) + $current_minute;
        $event_time_minutes = ($hour * 60) + $minute;
        
        if (isset($is_tomorrow) && $is_tomorrow) {
            $result['has_future_event'] = true;
            $result['event_description'] = "meeting $who";
            $result['time_frame'] = 'tomorrow';
            $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
            $result['planned_time'] = '12:00:00';
            error_log("✅ Detected: meeting $who at noon tomorrow");
            return $result;
        }
        
        if ($event_time_minutes > $current_time_minutes) {
            $result['has_future_event'] = true;
            $result['event_description'] = "meeting $who";
            $result['time_frame'] = 'today';
            $result['planned_date'] = date('Y-m-d');
            $result['planned_time'] = sprintf('%02d:%02d:00', $hour, $minute);
            error_log("✅ Detected: meeting $who at $hour:$minute");
            return $result;
        }
    }
    
    // Pattern 3: "going to/gonna [action] at [time]"
    if (preg_match('/(?:going to|gonna|will)\s+(.+?)\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $message_lower, $matches)) {
        $action = trim($matches[1]);
        $hour = (int)$matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
        $meridiem = isset($matches[4]) && $matches[4] !== '' ? strtolower($matches[4]) : '';
        
        // Convert to 24-hour
        $current_hour = (int)date('G');
        
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        } elseif ($meridiem === '' && $hour === 12) {
            if ($current_hour >= 12) {
                $is_tomorrow = true;
            }
        } elseif ($meridiem === '' && $hour < 12) {
            if ($hour <= $current_hour) {
                $hour += 12;
            }
        }
        
        $current_minute = (int)date('i');
        $current_time_minutes = ($current_hour * 60) + $current_minute;
        $event_time_minutes = ($hour * 60) + $minute;
        
        if (isset($is_tomorrow) && $is_tomorrow) {
            $result['has_future_event'] = true;
            $result['event_description'] = $action;
            $result['time_frame'] = 'tomorrow';
            $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
            $result['planned_time'] = '12:00:00';
            error_log("✅ Detected: $action at noon tomorrow");
            return $result;
        }
        
        if ($event_time_minutes > $current_time_minutes) {
            $result['has_future_event'] = true;
            $result['event_description'] = $action;
            $result['time_frame'] = 'today';
            $result['planned_date'] = date('Y-m-d');
            $result['planned_time'] = sprintf('%02d:%02d:00', $hour, $minute);
            error_log("✅ Detected: $action at $hour:$minute");
            return $result;
        }
    }
    
    // Pattern 4: "[action] at [time]" (catch-all for "watch movie at 12")
    if (preg_match('/(.+?)\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?(?:\s+|$)/i', $message_lower, $matches)) {
        $action = trim($matches[1]);
        
        // Filter out non-action phrases
        $skip_phrases = ['looking', 'staring', 'arrived', 'was', 'were', 'am', 'is', 'are'];
        $skip = false;
        foreach ($skip_phrases as $phrase) {
            if (strpos($action, $phrase) !== false) {
                $skip = true;
                break;
            }
        }
        
        if (!$skip) {
            $hour = (int)$matches[2];
            $minute = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
            $meridiem = isset($matches[4]) && $matches[4] !== '' ? strtolower($matches[4]) : '';
            
            // Convert to 24-hour
            $current_hour = (int)date('G');
            
            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            } elseif ($meridiem === '' && $hour === 12) {
                if ($current_hour >= 12) {
                    $is_tomorrow = true;
                }
            } elseif ($meridiem === '' && $hour < 12) {
                if ($hour <= $current_hour) {
                    $hour += 12;
                }
            }
            
            $current_minute = (int)date('i');
            $current_time_minutes = ($current_hour * 60) + $current_minute;
            $event_time_minutes = ($hour * 60) + $minute;
            
            if (isset($is_tomorrow) && $is_tomorrow) {
                $result['has_future_event'] = true;
                $result['event_description'] = $action;
                $result['time_frame'] = 'tomorrow';
                $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
                $result['planned_time'] = '12:00:00';
                error_log("✅ Detected: $action at noon tomorrow");
                return $result;
            }
            
            if ($event_time_minutes > $current_time_minutes) {
                $result['has_future_event'] = true;
                $result['event_description'] = $action;
                $result['time_frame'] = 'today';
                $result['planned_date'] = date('Y-m-d');
                $result['planned_time'] = sprintf('%02d:%02d:00', $hour, $minute);
                error_log("✅ Detected: $action at $hour:$minute");
                return $result;
            }
        }
    }
    
    // ===== PRIORITY 2: "tomorrow" patterns =====
    
    // Pattern: "tomorrow i'll/i will [action]"
    if (preg_match('/tomorrow\s+(?:i\'ll|i will|i\'m|im|i am|gonna|going to)\s+(.+?)(?:\.|!|\?|$)/i', $message_lower, $matches)) {
        $result['has_future_event'] = true;
        $result['event_description'] = trim($matches[1]);
        $result['time_frame'] = 'tomorrow';
        $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
        error_log("✅ Detected: {$result['event_description']} tomorrow");
        return $result;
    }
    
    // Pattern: "i'll/gonna [action] tomorrow"
    if (preg_match('/(?:i\'ll|i will|i\'m|im|i am|gonna|going to)\s+(.+?)\s+tomorrow/i', $message_lower, $matches)) {
        $result['has_future_event'] = true;
        $result['event_description'] = trim($matches[1]);
        $result['time_frame'] = 'tomorrow';
        $result['planned_date'] = date('Y-m-d', strtotime('+1 day'));
        error_log("✅ Detected: {$result['event_description']} tomorrow");
        return $result;
    }
    
    // ===== PRIORITY 3: "later/tonight" patterns =====
    if (preg_match('/(?:later|tonight|this evening|this afternoon)\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i', $message_lower, $matches)) {
        $result['has_future_event'] = true;
        $result['event_description'] = trim($matches[1]);
        $result['time_frame'] = 'today';
        $result['planned_date'] = date('Y-m-d');
        error_log("✅ Detected: {$result['event_description']} later today");
        return $result;
    }
    
    if (preg_match('/(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)\s+(?:later|tonight|this evening|this afternoon)/i', $message_lower, $matches)) {
        $result['has_future_event'] = true;
        $result['event_description'] = trim($matches[1]);
        $result['time_frame'] = 'today';
        $result['planned_date'] = date('Y-m-d');
        error_log("✅ Detected: {$result['event_description']} later today");
        return $result;
    }
    
    // ===== PRIORITY 4: Specific day patterns =====
    if (preg_match('/(?:on|this)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\s+(?:i\'m|im|i am|gonna|going to|will)\s+(.+?)(?:\.|!|\?|$)/i', $message_lower, $matches)) {
        $day_name = $matches[1];
        $result['has_future_event'] = true;
        $result['event_description'] = trim($matches[2]);
        $result['time_frame'] = 'future';
        $result['planned_date'] = date('Y-m-d', strtotime("next $day_name"));
        error_log("✅ Detected: {$result['event_description']} on $day_name");
        return $result;
    }
    
    return $result;
}

function saveFutureEvent($db, $user_id, $event_description, $planned_date, $planned_time = null) {
    if (empty($event_description)) return false;
    
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
        error_log("⏭️ Event already exists, skipping");
        return false;
    }
    
    // Determine event type
    $event_type = 'doing_activity';
    if (preg_match('/watch|see|viewing|movie|film/i', $event_description)) {
        $event_type = 'watching_movie';
    } elseif (preg_match('/go to|going to|visit|trip|travel/i', $event_description)) {
        $event_type = 'going_somewhere';
    } elseif (preg_match('/meet|meeting|see|hang out|pick up|pickup/i', $event_description)) {
        $event_type = 'meeting_someone';
    }
    
    $stmt = $db->prepare("
        INSERT INTO future_events 
        (user_id, event_description, event_type, planned_date, planned_time, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    
    return $stmt->execute([$user_id, $event_description, $event_type, $planned_date, $planned_time]);
}

function getPendingFutureEvents($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM future_events 
        WHERE user_id = ? 
        AND status = 'pending'
        AND (planned_date IS NULL OR planned_date >= CURDATE())
        ORDER BY planned_date ASC, planned_time ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOverdueFutureEvents($db, $user_id) {
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

function getTodaysFutureEvents($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM future_events 
        WHERE user_id = ? 
        AND status = 'pending'
        AND planned_date = CURDATE()
        ORDER BY planned_time ASC
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out events where time has passed
    $current_time = date('H:i:s');
    $pending = [];
    
    foreach ($events as $event) {
        if ($event['planned_time'] === null || $event['planned_time'] > $current_time) {
            $pending[] = $event;
        }
    }
    
    return $pending;
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
    $message_lower = strtolower($message);
    
    $past_patterns = [
        '/(?:i|just|finally)\s+(watched|saw|went to|visited|did|finished|completed)/i',
        '/(?:watched|saw|went|visited|did|finished)\s+(.+?)\s+(?:yesterday|today|earlier|just now)/i'
    ];
    
    foreach ($pending_events as $event) {
        $event_desc_lower = strtolower($event['event_description']);
        
        foreach ($past_patterns as $pattern) {
            if (preg_match($pattern, $message_lower)) {
                $keywords = explode(' ', $event_desc_lower);
                $match_count = 0;
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) > 3 && strpos($message_lower, $keyword) !== false) {
                        $match_count++;
                    }
                }
                
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
    
    $context = "\n=== 📅 DAN'S PLANNED EVENTS ===\n";
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    if (!empty($overdue_events)) {
        $context .= "⚠️ Events Dan mentioned but MAY NOT have done:\n";
        foreach ($overdue_events as $event) {
            $days_ago = floor((time() - strtotime($event['planned_date'])) / 86400);
            $context .= "- {$event['event_description']} (planned {$days_ago} day(s) ago)\n";
            $context .= "  → Don't assume completed! Ask: 'Did you end up...?'\n";
        }
        $context .= "\n";
    }
    
    if (!empty($pending_events)) {
        $context .= "📅 Upcoming/Today's events:\n";
        foreach ($pending_events as $event) {
            $is_today = ($event['planned_date'] === $current_date);
            $has_passed = $is_today && $event['planned_time'] && ($event['planned_time'] < $current_time);
            
            if ($is_today && !$has_passed) {
                $time_str = $event['planned_time'] ? " at " . date('g:i A', strtotime($event['planned_time'])) : '';
                $context .= "- 🎯 TODAY{$time_str}: {$event['event_description']}\n";
                $context .= "  → ⚠️ HAPPENING TODAY! HASN'T HAPPENED YET!\n";
                $context .= "  → Use FUTURE tense: 'Have fun!' NOT 'How was it?'\n";
            } else {
                $date_str = date('l, F j', strtotime($event['planned_date']));
                $context .= "- {$event['event_description']} ({$date_str})\n";
            }
        }
        $context .= "\n🚨 These HAVEN'T happened! Don't use past tense!\n";
    }
    
    return $context;
}

function autoMarkOldEvents($db, $user_id, $days_old = 7) {
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