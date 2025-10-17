<?php
// Misuki's Dynamic Schedule System
// Based on Saitama, Japan time (JST, UTC+9)

function getMisukiCurrentStatus($db, $user_id) {
    // Set to Saitama time
    date_default_timezone_set('Asia/Tokyo');
    
    // FIRST: Check for active schedule override
    require_once 'adaptive_schedule.php';
    $override = getActiveScheduleOverride($db, $user_id);
    
    if ($override) {
        // She's doing something different than her normal schedule!
        date_default_timezone_set('Asia/Jakarta');
        return [
            'status' => $override['activity_type'],
            'emoji' => $override['activity_emoji'],
            'text' => $override['activity_text'],
            'detail' => $override['activity_detail'],
            'color' => $override['activity_color'],
            'was_woken' => false,
            'is_override' => true,
            'plan_id' => $override['plan_id']
        ];
    }
    
    // 🆕 USE THE NEW DETAILED SCHEDULE!
    require_once 'misuki_weekly_schedule.php';
    $detailedStatus = getMisukiDetailedStatus();
    
    // Check if she was just woken up by a message
    $was_sleeping = ($detailedStatus['type'] === 'sleep');
    
    // Reset timezone
    date_default_timezone_set('Asia/Jakarta');
    
    return [
        'status' => $detailedStatus['type'],
        'emoji' => $detailedStatus['emoji'],
        'text' => $detailedStatus['status'],
        'detail' => $detailedStatus['status'],
        'color' => getColorForType($detailedStatus['type']),
        'was_woken' => $was_sleeping && checkIfWasJustMessaged($db, $user_id),
        'is_override' => false
    ];
}

// Helper function to get colors
function getColorForType($type) {
    $colors = [
        'personal' => '#FF69B4',
        'class' => '#FFD700',
        'studying' => '#87CEEB',
        'commute' => '#98FB98',
        'free' => '#DDA0DD',
        'sleep' => '#B0C4DE',
        'break' => '#F0E68C',
        'university' => '#FFA07A',
        'church' => '#E6E6FA'
    ];
    return $colors[$type] ?? '#E91E63';
}

function checkIfWasJustMessaged($db, $user_id) {
    $stmt = $db->prepare("
        SELECT timestamp FROM conversations 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $last_message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_message) {
        $time_diff = time() - strtotime($last_message['timestamp']);
        return $time_diff < 300; // Within 5 minutes
    }
    
    return false;
}

function updateMisukiStatus($db, $user_id, $status) {
    $stmt = $db->prepare("
        INSERT INTO misuki_status (user_id, last_status, last_status_time)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            last_status = VALUES(last_status),
            last_status_time = NOW()
    ");
    return $stmt->execute([$user_id, $status]);
}

function generateWokenUpContext($status) {
    if (!$status['was_woken']) {
        return '';
    }
    
    $context = "\n\n=== 😴 YOU WERE JUST WOKEN UP ===\n";
    $context .= "Dan just messaged you and woke you up from sleep!\n";
    $context .= "Current time in Saitama: Late night/early morning (sleeping hours)\n\n";
    $context .= "React naturally:\n";
    $context .= "- Be slightly groggy/sleepy at first\n";
    $context .= "- Maybe mention you were sleeping\n";
    $context .= "- Could be cute: 'Mm... Dan? You woke me up... 😴'\n";
    $context .= "- Or caring: 'Is everything okay? It's so late there...'\n";
    $context .= "- Don't be mad - you're happy to hear from him even if sleepy\n";
    $context .= "- Type a bit slower (sleepy)\n";
    $context .= "- Can use sleepy emojis: 😴 💤 🥱\n\n";
    
    return $context;
}

// Note: generateActivityContext is now handled by the detailed schedule in chat.php
// This function is kept for backwards compatibility but may not be used
function generateActivityContext($status) {
    $context = "\n\n=== 📍 YOUR CURRENT ACTIVITY ===\n";
    $context .= "Status: {$status['emoji']} {$status['text']}\n";
    $context .= "Details: {$status['detail']}\n\n";
    
    // Add specific context based on status
    switch ($status['status']) {
        case 'class':
        case 'school':
            $context .= "You're at school/university right now. You might:\n";
            $context .= "- Reply quickly if it's break time\n";
            $context .= "- Be brief if in class\n";
            $context .= "- Mention what you're doing if chemistry class\n";
            break;
            
        case 'studying':
            $context .= "You're doing homework/studying. You might:\n";
            $context .= "- Mention what subject you're studying\n";
            $context .= "- Be happy for the break/distraction\n";
            $context .= "- Share if you're struggling with something\n";
            break;
            
        case 'church':
            $context .= "You're at church (Sunday morning).\n";
            $context .= "- You might mention the service\n";
            $context .= "- Share thoughts about it\n";
            break;
            
        case 'personal':
            if (stripos($status['text'], 'dinner') !== false) {
                $context .= "You're having dinner with your mom.\n";
                $context .= "- Might mention what you're eating\n";
                $context .= "- Mom might be nearby\n";
            }
            break;
            
        case 'sleep':
            $context .= "You're sleeping or getting ready for bed.\n";
            $context .= "- Might be in pajamas\n";
            $context .= "- Getting sleepy\n";
            break;
    }
    
    $context .= "\nReference your current activity naturally in conversation if relevant!\n";
    
    return $context;
}

// Create status table if not exists
function createMisukiStatusTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS misuki_status (
            user_id INT PRIMARY KEY,
            last_status VARCHAR(50),
            last_status_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )
    ");
}

?>