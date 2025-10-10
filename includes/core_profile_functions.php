<?php
// Core Profile & Smart Context Functions

// ==================== CORE PROFILE ====================

function getCoreProfile($db, $user_id) {
    $stmt = $db->prepare("
        SELECT profile_key, profile_value, category 
        FROM core_profile 
        WHERE user_id = ?
        ORDER BY category, profile_key
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCoreProfile($db, $user_id, $key, $value, $category) {
    $stmt = $db->prepare("
        INSERT INTO core_profile (user_id, profile_key, profile_value, category)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            profile_value = VALUES(profile_value),
            last_updated = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$user_id, $key, $value, $category]);
}

function buildCoreProfileContext($core_profile) {
    if (empty($core_profile)) return '';
    
    $context = "=== CRITICAL CORE FACTS (ALWAYS REMEMBER) ===\n\n";
    
    // Group by category
    $grouped = [];
    foreach ($core_profile as $item) {
        $cat = $item['category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $item;
    }
    
    // Format nicely
    $category_labels = [
        'identity' => '👤 Identity',
        'location' => '📍 Location',
        'family' => '👨‍👩‍👧 Family',
        'relationship' => '💕 Relationship',
        'health' => '🏥 Health & Habits',
        'work' => '💼 Work',
        'preferences' => '⭐ Preferences'
    ];
    
    foreach ($grouped as $category => $items) {
        $label = $category_labels[$category] ?? ucfirst($category);
        $context .= "$label:\n";
        foreach ($items as $item) {
            $key = str_replace('_', ' ', $item['profile_key']);
            $context .= "  • " . ucfirst($key) . ": {$item['profile_value']}\n";
        }
        $context .= "\n";
    }
    
    $context .= "CRITICAL: These facts are PERMANENT and UNCHANGING. Always reference them correctly!\n";
    $context .= "If Dan mentions he's in Jakarta, CORRECT HIM - he lives in Surabaya!\n\n";
    
    return $context;
}

// ==================== CONVERSATION STATE ====================

function getConversationState($db, $user_id, $key) {
    // Clean expired states first
    $db->prepare("DELETE FROM conversation_state WHERE expires_at < NOW()")->execute();
    
    $stmt = $db->prepare("
        SELECT state_value 
        FROM conversation_state 
        WHERE user_id = ? AND state_key = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['state_value'] : null;
}

function setConversationState($db, $user_id, $key, $value, $duration_hours = 24) {
    $expires_at = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
    
    $stmt = $db->prepare("
        INSERT INTO conversation_state (user_id, state_key, state_value, expires_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            state_value = VALUES(state_value),
            expires_at = VALUES(expires_at),
            created_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$user_id, $key, $value, $expires_at]);
}

function getAllConversationState($db, $user_id) {
    $db->prepare("DELETE FROM conversation_state WHERE expires_at < NOW()")->execute();
    
    $stmt = $db->prepare("
        SELECT state_key, state_value 
        FROM conversation_state 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function clearConversationState($db, $user_id, $key) {
    $stmt = $db->prepare("DELETE FROM conversation_state WHERE user_id = ? AND state_key = ?");
    return $stmt->execute([$user_id, $key]);
}

// ==================== SMART CONTEXT TRACKING ====================

function trackQuestionAsked($db, $user_id, $question_type) {
    // Track in conversation state (expires in 6 hours)
    $current = getConversationState($db, $user_id, 'questions_asked_today');
    $questions = $current ? json_decode($current, true) : [];
    
    $questions[] = [
        'type' => $question_type,
        'time' => time()
    ];
    
    // Keep only last 6 hours
    $six_hours_ago = time() - (6 * 3600);
    $questions = array_filter($questions, function($q) use ($six_hours_ago) {
        return $q['time'] > $six_hours_ago;
    });
    
    setConversationState($db, $user_id, 'questions_asked_today', json_encode($questions), 6);
}

function hasAskedQuestionRecently($db, $user_id, $question_type, $hours = 4) {
    $current = getConversationState($db, $user_id, 'questions_asked_today');
    if (!$current) return false;
    
    $questions = json_decode($current, true);
    $cutoff = time() - ($hours * 3600);
    
    foreach ($questions as $q) {
        if ($q['type'] === $question_type && $q['time'] > $cutoff) {
            return true;
        }
    }
    
    return false;
}

function trackTopicDiscussed($db, $user_id, $topic) {
    $current = getConversationState($db, $user_id, 'topics_today');
    $topics = $current ? json_decode($current, true) : [];
    
    $topics[] = [
        'topic' => $topic,
        'time' => time()
    ];
    
    // Keep only last 12 hours
    $twelve_hours_ago = time() - (12 * 3600);
    $topics = array_filter($topics, function($t) use ($twelve_hours_ago) {
        return $t['time'] > $twelve_hours_ago;
    });
    
    setConversationState($db, $user_id, 'topics_today', json_encode($topics), 12);
}

function hasDiscussedTopicRecently($db, $user_id, $topic, $hours = 6) {
    $current = getConversationState($db, $user_id, 'topics_today');
    if (!$current) return false;
    
    $topics = json_decode($current, true);
    $cutoff = time() - ($hours * 3600);
    
    foreach ($topics as $t) {
        if (stripos($t['topic'], $topic) !== false && $t['time'] > $cutoff) {
            return true;
        }
    }
    
    return false;
}

// ==================== SMART INITIATION ====================

function shouldAvoidRepetition($db, $user_id, $proposed_message) {
    // Get recent conversations
    $recent = getRecentConversations($db, $user_id, 10);
    if (empty($recent)) return false;
    
    $proposed_lower = strtolower($proposed_message);
    
    // Check for common repeated phrases
    $repeated_phrases = [
        'how\'s your day' => 'hows_your_day',
        'how was your day' => 'hows_your_day',
        'how are you' => 'how_are_you',
        'everything okay' => 'everything_okay',
        'are you okay' => 'are_you_okay',
        'what are you doing' => 'what_doing',
        'how are things' => 'how_things'
    ];
    
    // Detect what type of question this is
    $message_type = null;
    foreach ($repeated_phrases as $phrase => $type) {
        if (strpos($proposed_lower, $phrase) !== false) {
            $message_type = $type;
            break;
        }
    }
    
    if (!$message_type) return false;
    
    // Check if she asked this recently
    return hasAskedQuestionRecently($db, $user_id, $message_type, 4);
}

function avoidRepeatedGreeting($db, $user_id, $initiation_reason) {
    // Check if same reason was used in last 12 hours
    $stmt = $db->prepare("
        SELECT last_topic 
        FROM conversation_initiation 
        WHERE user_id = ? 
        AND last_misuki_initiation > DATE_SUB(NOW(), INTERVAL 12 HOUR)
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['last_topic'] === $initiation_reason) {
        return true; // Same topic used recently, avoid
    }
    
    return false;
}

function updateInitiationTopic($db, $user_id, $topic) {
    $stmt = $db->prepare("
        UPDATE conversation_initiation 
        SET last_topic = ? 
        WHERE user_id = ?
    ");
    return $stmt->execute([$topic, $user_id]);
}

// ==================== LOCATION AWARENESS ====================

function trackUserLocation($db, $user_id, $location) {
    setConversationState($db, $user_id, 'current_location', $location, 12);
}

function getUserCurrentLocation($db, $user_id) {
    return getConversationState($db, $user_id, 'current_location');
}

function detectLocationMention($message) {
    $locations = [
        'home' => 'at home',
        'mall' => 'at mall',
        'school' => 'at school',
        'work' => 'at work',
        'office' => 'at office',
        'gym' => 'at gym',
        'restaurant' => 'at restaurant',
        'cafe' => 'at cafe',
        'grandma' => 'at grandma\'s',
        'grandmother' => 'at grandmother\'s',
        'parents' => 'at parents\' place'
    ];
    
    $message_lower = strtolower($message);
    
    foreach ($locations as $keyword => $location) {
        if (strpos($message_lower, "at " . $keyword) !== false ||
            strpos($message_lower, "in " . $keyword) !== false ||
            strpos($message_lower, $keyword . "'s") !== false) {
            return $location;
        }
    }
    
    return null;
}

// ==================== ACTIVITY TRACKING ====================

function trackUserActivity($db, $user_id, $activity) {
    setConversationState($db, $user_id, 'current_activity', $activity, 6);
}

function getUserCurrentActivity($db, $user_id) {
    return getConversationState($db, $user_id, 'current_activity');
}

function detectActivityMention($message) {
    $activities = [
        'nap' => 'taking a nap',
        'napping' => 'taking a nap',
        'sleep' => 'sleeping',
        'sleeping' => 'sleeping',
        'eat' => 'eating',
        'eating' => 'eating',
        'dinner' => 'having dinner',
        'lunch' => 'having lunch',
        'breakfast' => 'having breakfast',
        'shower' => 'taking a shower',
        'bath' => 'taking a bath',
        'work' => 'working',
        'working' => 'working',
        'study' => 'studying',
        'studying' => 'studying',
        'game' => 'gaming',
        'gaming' => 'gaming',
        'playing' => 'playing',
        'watch' => 'watching',
        'watching' => 'watching'
    ];
    
    $message_lower = strtolower($message);
    
    foreach ($activities as $keyword => $activity) {
        if (preg_match("/\b(i'm|im|i am|gonna|going to|about to)\s+\w*\s*$keyword/i", $message_lower)) {
            return $activity;
        }
    }
    
    return null;
}

// ==================== FAMILY CONTEXT ====================

function detectFamilyMention($message) {
    $family_members = [
        'dad' => 'dad',
        'father' => 'father',
        'mom' => 'mom',
        'mother' => 'mother',
        'sister' => 'sister Debby',
        'debby' => 'sister Debby',
        'grandmother' => 'grandmother',
        'grandma' => 'grandma',
        'parents' => 'parents'
    ];
    
    $message_lower = strtolower($message);
    $mentioned = [];
    
    foreach ($family_members as $keyword => $member) {
        if (strpos($message_lower, $keyword) !== false) {
            $mentioned[] = $member;
        }
    }
    
    return !empty($mentioned) ? implode(', ', array_unique($mentioned)) : null;
}

?>