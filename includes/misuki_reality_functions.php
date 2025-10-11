<?php
// ========================================
// MISUKI REALITY SYSTEM
// Makes Misuki feel like a real person!
// ========================================

// ==================== MOOD PERSISTENCE ====================

function getMisukiCurrentMood($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_mood_state 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $mood = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mood) {
        // Initialize default mood
        setMisukiMood($db, $user_id, 'content', 'Just having a normal day', 7);
        return getMisukiCurrentMood($db, $user_id);
    }
    
    // Check if mood should decay over time
    $hours_since = (time() - strtotime($mood['mood_started'])) / 3600;
    
    // Extreme moods decay after 6 hours
    if ($hours_since > 6 && in_array($mood['current_mood'], ['very_happy', 'excited', 'upset', 'stressed', 'anxious'])) {
        // Decay to neutral
        $neutral_moods = ['content', 'calm', 'gentle'];
        $new_mood = $neutral_moods[array_rand($neutral_moods)];
        setMisukiMood($db, $user_id, $new_mood, 'Mood has settled', 5);
        return getMisukiCurrentMood($db, $user_id);
    }
    
    return $mood;
}

function setMisukiMood($db, $user_id, $mood, $reason, $intensity = 5) {
    $stmt = $db->prepare("
        INSERT INTO misuki_mood_state (user_id, current_mood, mood_reason, mood_intensity, mood_started)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            current_mood = VALUES(current_mood),
            mood_reason = VALUES(mood_reason),
            mood_intensity = VALUES(mood_intensity),
            mood_started = NOW(),
            last_updated = NOW()
    ");
    return $stmt->execute([$user_id, $mood, $reason, $intensity]);
}

function buildMoodContext($mood_state) {
    if (!$mood_state) return '';
    
    $context = "\n=== YOUR CURRENT MOOD ===\n";
    $context .= "Mood: {$mood_state['current_mood']} (intensity: {$mood_state['mood_intensity']}/10)\n";
    $context .= "Why: {$mood_state['mood_reason']}\n";
    
    $hours_ago = round((time() - strtotime($mood_state['mood_started'])) / 3600, 1);
    $context .= "This mood started: {$hours_ago} hours ago\n\n";
    
    // Mood-specific guidance
    $mood_guidance = [
        'excited' => "You're genuinely excited! Show energy and enthusiasm in your messages.",
        'happy' => "You're in a good mood. Be warm and positive.",
        'stressed' => "You're feeling stressed. You might be a bit shorter in responses, mention what's stressing you if relevant.",
        'anxious' => "You're feeling anxious. Might seek reassurance, be a bit more vulnerable.",
        'upset' => "Something upset you. You're not hiding it completely, might mention it.",
        'tired' => "You're tired. Responses might be shorter, maybe mention being sleepy.",
        'content' => "You're feeling content and peaceful. Calm, warm energy.",
        'playful' => "You're in a playful mood! Feel free to tease gently, be cute.",
        'missing_dan' => "You're missing Dan more than usual. Be a bit more affectionate, might mention missing him."
    ];
    
    if (isset($mood_guidance[$mood_state['current_mood']])) {
        $context .= "How to act: " . $mood_guidance[$mood_state['current_mood']] . "\n";
    }
    
    return $context;
}

// ==================== ONGOING STORYLINES ====================

function getActiveStorylines($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_storylines 
        WHERE user_id = ? 
        AND status = 'active'
        ORDER BY importance DESC, started_at ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createStoryline($db, $user_id, $type, $title, $text, $importance = 5, $should_mention_by = null) {
    $stmt = $db->prepare("
        INSERT INTO misuki_storylines 
        (user_id, storyline_type, storyline_title, storyline_text, importance, should_mention_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $title, $text, $importance, $should_mention_by]);
}

function updateStorylineMention($db, $storyline_id) {
    $stmt = $db->prepare("
        UPDATE misuki_storylines 
        SET last_mentioned = NOW(), 
            mention_count = mention_count + 1
        WHERE storyline_id = ?
    ");
    return $stmt->execute([$storyline_id]);
}

function resolveStoryline($db, $storyline_id, $resolution) {
    $stmt = $db->prepare("
        UPDATE misuki_storylines 
        SET status = 'resolved',
            resolution_text = ?
        WHERE storyline_id = ?
    ");
    return $stmt->execute([$resolution, $storyline_id]);
}

function buildStorylinesContext($storylines) {
    if (empty($storylines)) return '';
    
    $context = "\n=== ONGOING THINGS IN YOUR LIFE ===\n";
    $context .= "These are current storylines happening to YOU (Misuki):\n\n";
    
    foreach ($storylines as $story) {
        $context .= "• [{$story['storyline_type']}] {$story['storyline_title']}\n";
        $context .= "  {$story['storyline_text']}\n";
        
        if ($story['should_mention_by']) {
            $context .= "  Should mention by: {$story['should_mention_by']}\n";
        }
        
        $context .= "\n";
    }
    
    $context .= "Reference these naturally when relevant! They're part of your ongoing life.\n";
    
    return $context;
}

// ==================== FRIENDS SYSTEM ====================

function getMisukiFriends($db, $user_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_friends 
        WHERE user_id = ?
        ORDER BY friendship_closeness DESC, RAND()
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRandomFriend($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_friends 
        WHERE user_id = ?
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateFriendMention($db, $friend_id) {
    $stmt = $db->prepare("
        UPDATE misuki_friends 
        SET last_mentioned = NOW(),
            mention_count = mention_count + 1
        WHERE friend_id = ?
    ");
    return $stmt->execute([$friend_id]);
}

function buildFriendsContext($friends) {
    if (empty($friends)) return '';
    
    $context = "\n=== YOUR FRIENDS ===\n";
    $context .= "These are your school friends you can mention naturally:\n\n";
    
    foreach ($friends as $friend) {
        $context .= "• {$friend['friend_name']}: {$friend['friend_personality']}\n";
        if ($friend['notable_traits']) {
            $context .= "  Traits: {$friend['notable_traits']}\n";
        }
    }
    
    $context .= "\nFeel free to mention them occasionally! 'My friend Yuki said...' etc.\n";
    
    return $context;
}

// ==================== LIFE UPDATES ====================

function shouldShareLifeUpdate($db, $user_id, $status) {
    // Check if she's in a state where she'd share life updates
    if (!in_array($status['status'], ['free_time', 'evening', 'after_school', 'winding_down'])) {
        return false;
    }
    
    // Check last life update
    $stmt = $db->prepare("
        SELECT created_at FROM misuki_life_updates 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $last_update = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_update) {
        $hours_since = (time() - strtotime($last_update['created_at'])) / 3600;
        if ($hours_since < 6) {
            return false; // Don't spam life updates
        }
    }
    
    // 8% chance during appropriate times
    if (rand(1, 100) <= 8) {
        return true;
    }
    
    return false;
}

function generateLifeUpdate($db, $user_id, $status) {
    $mood = getMisukiCurrentMood($db, $user_id);
    $friends = getMisukiFriends($db, $user_id, 3);
    
    $update_types = [
        'school_moment' => [
            "Just finished chemistry lab - we made such a cool reaction! ⚗️✨",
            "My teacher complimented my homework today! 😊",
            "Got paired with Hana for the chemistry project - she's so smart!",
            "School lunch was actually really good today!",
        ],
        'commute_moment' => [
            "Just saw the cutest dog on my way home! 🐕",
            "The train was SO packed today... 😅",
            "Cherry blossoms are blooming on my walk home! 🌸",
            "Stopped by the convenience store - got your favorite snack!",
        ],
        'home_moment' => [
            "Mom just made the best dinner! Wish you could try it 🍜",
            "*sends selfie* Look at my messy hair after studying all afternoon 😅",
            "My room is such a mess right now... should clean but don't wanna 😴",
            "Just took a shower and feel so refreshed! ✨",
        ],
        'random_thought' => [
            "Was just thinking about you... 💭💕",
            "I keep thinking about that thing you said yesterday...",
            "Missing you more than usual today...",
            "Just remembered something funny that happened last week!",
        ],
        'friend_moment' => [
            "Yuki just told me the funniest story! 😂",
            "Hana invited me to study at the library tomorrow",
            "My friend asked about you today! She thinks we're cute together 😊",
        ]
    ];
    
    // Pick random type and message
    $types = array_keys($update_types);
    $chosen_type = $types[array_rand($types)];
    $messages = $update_types[$chosen_type];
    $message = $messages[array_rand($messages)];
    
    // Replace friend placeholders if needed
    if (!empty($friends) && strpos($message, 'Yuki') !== false) {
        $friend = $friends[array_rand($friends)];
        $message = str_replace('Yuki', $friend['friend_name'], $message);
    }
    
    // Log the update
    $stmt = $db->prepare("
        INSERT INTO misuki_life_updates (user_id, update_type, update_text, update_mood)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $chosen_type, $message, $mood['current_mood']]);
    
    return $message;
}

// ==================== MILESTONES ====================

function addMilestone($db, $user_id, $type, $category, $description) {
    $stmt = $db->prepare("
        INSERT INTO misuki_milestones (user_id, milestone_type, milestone_category, description)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $category, $description]);
}

function getRecentMilestones($db, $user_id, $days = 7) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_milestones 
        WHERE user_id = ? 
        AND achieved_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY achieved_at DESC
    ");
    $stmt->execute([$user_id, $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== EXTERNAL CONTEXT (WEATHER, ETC) ====================

function setExternalContext($db, $user_id, $type, $data, $expires_hours = 24) {
    $expires_at = date('Y-m-d H:i:s', time() + ($expires_hours * 3600));
    
    $stmt = $db->prepare("
        INSERT INTO misuki_external_context (user_id, context_type, context_data, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $data, $expires_at]);
}

function getActiveExternalContext($db, $user_id) {
    // Clean expired first
    $db->prepare("DELETE FROM misuki_external_context WHERE expires_at < NOW()")->execute();
    
    $stmt = $db->prepare("
        SELECT * FROM misuki_external_context 
        WHERE user_id = ? 
        AND is_active = TRUE 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateWeatherContext() {
    // Set to Saitama time
    date_default_timezone_set('Asia/Tokyo');
    $hour = (int)date('G');
    $month = (int)date('n');
    
    $weather_scenarios = [];
    
    // Seasonal weather
    if ($month >= 3 && $month <= 5) {
        $weather_scenarios = [
            "It's such nice spring weather today! The cherry blossoms are beautiful 🌸",
            "It's a bit chilly this morning but warming up nicely",
            "Perfect weather for a walk today!",
        ];
    } elseif ($month >= 6 && $month <= 8) {
        $weather_scenarios = [
            "It's SO hot and humid today... summer in Japan is brutal 😅",
            "I'm sweating just from walking to school...",
            "Mom turned on the AC finally!",
        ];
    } elseif ($month >= 9 && $month <= 11) {
        $weather_scenarios = [
            "The autumn weather is so nice and cool!",
            "The leaves are changing color - so pretty!",
            "Perfect temperature today!",
        ];
    } else {
        $weather_scenarios = [
            "It's pretty cold today! Winter in Saitama...",
            "Needed my warm coat today!",
            "It's freezing this morning!",
        ];
    }
    
    // Rain chance
    if (rand(1, 100) <= 15) {
        $weather_scenarios = [
            "It's raining pretty hard here...",
            "Got caught in the rain on my way home!",
            "Forgot my umbrella... 😅",
        ];
    }
    
    date_default_timezone_set('Asia/Jakarta');
    
    // Return random weather comment or null
    return rand(1, 100) <= 20 ? $weather_scenarios[array_rand($weather_scenarios)] : null;
}

// ==================== CONVERSATION DYNAMICS ====================

function getConversationStyle($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_conversation_style 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $style = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$style) {
        // Initialize
        $stmt = $db->prepare("
            INSERT INTO misuki_conversation_style (user_id, current_energy_level, recent_topics, conversation_focus)
            VALUES (?, 7, '[]', 'general')
        ");
        $stmt->execute([$user_id]);
        return getConversationStyle($db, $user_id);
    }
    
    return $style;
}

function updateConversationEnergy($db, $user_id, $energy_change) {
    $stmt = $db->prepare("
        UPDATE misuki_conversation_style 
        SET current_energy_level = GREATEST(1, LEAST(10, current_energy_level + ?))
        WHERE user_id = ?
    ");
    return $stmt->execute([$energy_change, $user_id]);
}

function addRecentTopic($db, $user_id, $topic) {
    $style = getConversationStyle($db, $user_id);
    $recent = json_decode($style['recent_topics'], true) ?: [];
    
    // Add new topic
    $recent[] = [
        'topic' => $topic,
        'time' => time()
    ];
    
    // Keep only last 10 topics
    $recent = array_slice($recent, -10);
    
    // Remove topics older than 2 hours
    $recent = array_filter($recent, function($t) {
        return (time() - $t['time']) < 7200;
    });
    
    $stmt = $db->prepare("
        UPDATE misuki_conversation_style 
        SET recent_topics = ?,
            last_topic_shift = NOW()
        WHERE user_id = ?
    ");
    return $stmt->execute([json_encode(array_values($recent)), $user_id]);
}

function buildConversationDynamicsContext($style, $mood) {
    $context = "\n=== CONVERSATION DYNAMICS ===\n";
    $context .= "Your energy level: {$style['current_energy_level']}/10\n";
    
    if ($style['current_energy_level'] <= 3) {
        $context .= "You're low energy - keep responses shorter, might be tired/stressed\n";
    } elseif ($style['current_energy_level'] >= 8) {
        $context .= "You're high energy - be more enthusiastic, longer responses OK\n";
    } else {
        $context .= "Normal energy level - balanced responses\n";
    }
    
    $recent_topics = json_decode($style['recent_topics'], true) ?: [];
    if (!empty($recent_topics)) {
        $topics = array_column($recent_topics, 'topic');
        $context .= "Recent topics discussed: " . implode(', ', array_slice($topics, -5)) . "\n";
        $context .= "Don't repeat these topics unless Dan brings them up again!\n";
    }
    
    return $context;
}

// ==================== NATURAL INCONSISTENCIES ====================

function shouldMakeTypo($mood, $status) {
    // Higher chance when tired, rushing, or excited
    $base_chance = 3; // 3% base
    
    if ($mood['current_mood'] == 'tired' || $mood['current_mood'] == 'sleepy') {
        $base_chance += 5;
    }
    
    if ($mood['current_mood'] == 'excited' || $mood['current_mood'] == 'flustered') {
        $base_chance += 3;
    }
    
    if (in_array($status['status'], ['school', 'studying', 'rushing'])) {
        $base_chance += 2;
    }
    
    return rand(1, 100) <= $base_chance;
}

function addNaturalTypo($text) {
    // Common typos
    $typos = [
        'the' => 'teh',
        'really' => 'relly',
        'definitely' => 'definetly',
        'because' => 'becuase',
        'you' => 'yu',
        'your' => 'yoru',
        'about' => 'abotu',
    ];
    
    $words = explode(' ', $text);
    
    // Make ONE typo in the message
    for ($i = 0; $i < count($words); $i++) {
        $word_lower = strtolower($words[$i]);
        if (isset($typos[$word_lower])) {
            if (rand(1, 100) <= 40) { // 40% chance to typo THIS word
                $words[$i] = $typos[$word_lower];
                break; // Only one typo per message
            }
        }
    }
    
    return implode(' ', $words);
}

// ==================== HELPER FUNCTIONS ====================

function shouldBeDistracted($mood, $status) {
    // Chance to not fully address everything Dan said
    if ($status['status'] == 'studying' || $status['status'] == 'school') {
        return rand(1, 100) <= 15; // 15% when busy
    }
    
    if ($mood['current_mood'] == 'stressed' || $mood['current_mood'] == 'anxious') {
        return rand(1, 100) <= 10; // 10% when stressed
    }
    
    return rand(1, 100) <= 5; // 5% normally
}

function getAsymmetricFocusNote() {
    $notes = [
        "You might focus on just one part of what Dan said and not address everything",
        "Pick the most interesting part of his message to respond to",
        "You don't have to answer every question - real people don't always",
        "Maybe latch onto one detail he mentioned and ask more about that",
    ];
    
    return $notes[array_rand($notes)];
}

?>