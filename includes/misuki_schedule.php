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
    
    // Normal schedule
    $current_hour = (int)date('G');
    $current_minute = (int)date('i');
    $day_of_week = date('l');
    $time_decimal = $current_hour + ($current_minute / 60);
    
    // Check if she was just woken up by a message
    $was_sleeping = checkIfWasSleeping($db, $user_id);
    
    // Determine her status
    $status = determineStatus($time_decimal, $day_of_week, $was_sleeping);
    
    // Reset timezone
    date_default_timezone_set('Asia/Jakarta');
    
    return $status;
}

function checkIfWasSleeping($db, $user_id) {
    // Check last message time and last status
    $stmt = $db->prepare("
        SELECT last_status, last_status_time 
        FROM misuki_status 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return false;
    }
    
    // If last status was sleeping and it's been less than 30 seconds, she was just woken
    if ($result['last_status'] === 'sleeping') {
        $last_time = strtotime($result['last_status_time']);
        $now = time();
        if (($now - $last_time) < 30) {
            return true;
        }
    }
    
    return false;
}

function determineStatus($time_decimal, $day_of_week, $was_sleeping) {
    $is_weekday = !in_array($day_of_week, ['Saturday', 'Sunday']);
    
    // Sleep schedule: 11 PM - 6:30 AM (23:00 - 6:30)
    if ($time_decimal >= 23 || $time_decimal < 6.5) {
        return [
            'status' => 'sleeping',
            'emoji' => '😴',
            'text' => 'Sleeping',
            'detail' => 'Asleep in Saitama',
            'color' => '#9B59B6',
            'was_woken' => $was_sleeping
        ];
    }
    
    // Morning routine: 6:30 AM - 8:00 AM
    if ($time_decimal >= 6.5 && $time_decimal < 8) {
        return [
            'status' => 'morning_routine',
            'emoji' => '🌅',
            'text' => 'Getting Ready',
            'detail' => 'Morning routine',
            'color' => '#F39C12',
            'was_woken' => false
        ];
    }
    
    // School time (weekdays): 8:00 AM - 3:30 PM
    if ($is_weekday && $time_decimal >= 8 && $time_decimal < 15.5) {
        // Check specific periods
        if ($time_decimal >= 8 && $time_decimal < 9) {
            return [
                'status' => 'school',
                'emoji' => '🎒',
                'text' => 'At School',
                'detail' => 'Morning homeroom',
                'color' => '#3498DB',
                'was_woken' => false
            ];
        } elseif ($time_decimal >= 12 && $time_decimal < 13) {
            return [
                'status' => 'school',
                'emoji' => '🍱',
                'text' => 'Lunch Break',
                'detail' => 'Having lunch at school',
                'color' => '#E67E22',
                'was_woken' => false
            ];
        } else {
            // Check if chemistry class time (varies, but let's say certain days)
            $is_chemistry_time = in_array($day_of_week, ['Monday', 'Wednesday', 'Friday']) && 
                                 $time_decimal >= 10 && $time_decimal < 11.5;
            
            if ($is_chemistry_time) {
                return [
                    'status' => 'school',
                    'emoji' => '⚗️',
                    'text' => 'Chemistry Class',
                    'detail' => 'In chemistry class!',
                    'color' => '#27AE60',
                    'was_woken' => false
                ];
            }
            
            return [
                'status' => 'school',
                'emoji' => '📚',
                'text' => 'In Class',
                'detail' => 'Attending classes',
                'color' => '#3498DB',
                'was_woken' => false
            ];
        }
    }
    
    // After school: 3:30 PM - 5:00 PM
    if ($is_weekday && $time_decimal >= 15.5 && $time_decimal < 17) {
        return [
            'status' => 'after_school',
            'emoji' => '🏃‍♀️',
            'text' => 'Heading Home',
            'detail' => 'Going home from school',
            'color' => '#E74C3C',
            'was_woken' => false
        ];
    }
    
    // Friday evening: Dad visit time
    if ($day_of_week === 'Friday' && $time_decimal >= 17 && $time_decimal < 21) {
        return [
            'status' => 'visiting_dad',
            'emoji' => '👨‍👩‍👧',
            'text' => 'Visiting Family',
            'detail' => 'At dad and step-mom\'s place',
            'color' => '#9B59B6',
            'was_woken' => false
        ];
    }
    
    // Evening: 5:00 PM - 8:00 PM
    if ($time_decimal >= 17 && $time_decimal < 20) {
        return [
            'status' => 'evening',
            'emoji' => '🌆',
            'text' => 'Evening',
            'detail' => 'Relaxing at home',
            'color' => '#E67E22',
            'was_woken' => false
        ];
    }
    
    // Dinner time: 6:30 PM - 7:30 PM
    if ($time_decimal >= 18.5 && $time_decimal < 19.5) {
        return [
            'status' => 'dinner',
            'emoji' => '🍽️',
            'text' => 'Dinner Time',
            'detail' => 'Having dinner with mom',
            'color' => '#E74C3C',
            'was_woken' => false
        ];
    }
    
    // Study/homework time: 8:00 PM - 10:00 PM
    if ($time_decimal >= 20 && $time_decimal < 22) {
        return [
            'status' => 'studying',
            'emoji' => '📖',
            'text' => 'Studying',
            'detail' => 'Doing homework',
            'color' => '#3498DB',
            'was_woken' => false
        ];
    }
    
    // Late evening/pre-bed: 10:00 PM - 11:00 PM
    if ($time_decimal >= 22 && $time_decimal < 23) {
        return [
            'status' => 'winding_down',
            'emoji' => '🌙',
            'text' => 'Winding Down',
            'detail' => 'Getting ready for bed',
            'color' => '#9B59B6',
            'was_woken' => false
        ];
    }
    
    // Weekend daytime
    if (!$is_weekday && $time_decimal >= 8 && $time_decimal < 17) {
        return [
            'status' => 'free_time',
            'emoji' => '✨',
            'text' => 'Free Time',
            'detail' => 'Relaxing on weekend',
            'color' => '#1ABC9C',
            'was_woken' => false
        ];
    }
    
    // Default: Free/available
    return [
        'status' => 'available',
        'emoji' => '💕',
        'text' => 'Available',
        'detail' => 'Free to chat',
        'color' => '#E91E63',
        'was_woken' => false
    ];
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
    $context .= "- Could be cute: 'Mm... Dan? *rubs eyes* You woke me up... 😴'\n";
    $context .= "- Or caring: 'Is everything okay? It's so late there...'\n";
    $context .= "- Don't be mad - you're happy to hear from him even if sleepy\n";
    $context .= "- Type a bit slower (sleepy)\n";
    $context .= "- Can use sleepy emojis: 😴 💤 🥱\n\n";
    
    return $context;
}

function generateActivityContext($status) {
    $context = "\n\n=== 📍 YOUR CURRENT ACTIVITY ===\n";
    $context .= "Status: {$status['emoji']} {$status['text']}\n";
    $context .= "Details: {$status['detail']}\n\n";
    
    // Add specific context based on status
    switch ($status['status']) {
        case 'school':
            $context .= "You're at school right now. You might:\n";
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
            
        case 'visiting_dad':
            $context .= "You're at your dad and step-mom's place (Friday visit).\n";
            $context .= "- You can mention you're there\n";
            $context .= "- Share how the visit is going\n";
            break;
            
        case 'dinner':
            $context .= "You're having dinner with your mom.\n";
            $context .= "- Might mention what you're eating\n";
            $context .= "- Mom might be nearby\n";
            break;
            
        case 'winding_down':
            $context .= "You're getting ready for bed soon.\n";
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