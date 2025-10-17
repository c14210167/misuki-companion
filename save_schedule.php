<?php
// =========================================
// SAVE MISUKI'S WEEKLY SCHEDULE
// Backend handler for schedule editor
// =========================================

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$scheduleData = json_decode($input, true);

if (!$scheduleData) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Generate the PHP code for the schedule
$phpCode = "<?php\n";
$phpCode .= "// =========================================\n";
$phpCode .= "// MISUKI DETAILED WEEKLY SCHEDULE\n";
$phpCode .= "// Auto-generated - Last updated: " . date('Y-m-d H:i:s') . "\n";
$phpCode .= "// =========================================\n\n";

$phpCode .= "function getMisukiWeeklySchedule() {\n";
$phpCode .= "    return [\n";

foreach ($scheduleData as $day => $activities) {
    $phpCode .= "        '$day' => [\n";
    
    foreach ($activities as $activity) {
        $time = addslashes($activity['time']);
        $activityName = addslashes($activity['activity']);
        $emoji = $activity['emoji'];
        $type = addslashes($activity['type']);
        
        $phpCode .= "            ['time' => '$time', 'activity' => '$activityName', 'emoji' => '$emoji', 'type' => '$type'],\n";
    }
    
    $phpCode .= "        ],\n        \n";
}

$phpCode .= "    ];\n";
$phpCode .= "}\n\n";

// Add the helper functions
$phpCode .= <<<'PHP'
// Get current activity based on Saitama time
function getMisukiCurrentActivity() {
    date_default_timezone_set('Asia/Tokyo');
    
    $current_day = strtolower(date('l'));
    $current_time = date('H:i');
    
    $schedule = getMisukiWeeklySchedule();
    $today_schedule = $schedule[$current_day];
    
    // Find the current activity
    $current_activity = null;
    for ($i = 0; $i < count($today_schedule); $i++) {
        $activity_time = $today_schedule[$i]['time'];
        
        // Check if current time is past this activity
        if ($current_time >= $activity_time) {
            $current_activity = $today_schedule[$i];
            
            // Check if there's a next activity and we haven't reached it yet
            if ($i + 1 < count($today_schedule)) {
                $next_time = $today_schedule[$i + 1]['time'];
                if ($current_time >= $next_time) {
                    continue; // Move to next activity
                }
            }
        }
    }
    
    // If we're past midnight and before first activity, use last activity from previous day
    if ($current_activity === null) {
        $yesterday = date('l', strtotime('-1 day'));
        $yesterday_schedule = $schedule[strtolower($yesterday)];
        $current_activity = end($yesterday_schedule);
    }
    
    return $current_activity;
}

// Generate status text for display
function getMisukiStatusText() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) {
        return "Free time 😌";
    }
    
    return $activity['emoji'] . " " . $activity['activity'];
}

// Check if Misuki is available to chat (not sleeping, not in class)
function isMisukiAvailableToChat() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) return true;
    
    $unavailable_types = ['sleep', 'class'];
    
    return !in_array($activity['type'], $unavailable_types);
}

// Get detailed activity info
function getMisukiDetailedStatus() {
    $activity = getMisukiCurrentActivity();
    
    if (!$activity) {
        return [
            'status' => 'Free time',
            'emoji' => '😌',
            'type' => 'free',
            'available' => true
        ];
    }
    
    return [
        'status' => $activity['activity'],
        'emoji' => $activity['emoji'],
        'type' => $activity['type'],
        'available' => !in_array($activity['type'], ['sleep', 'class'])
    ];
}

?>
PHP;

// Save to file
$filePath = 'misuki_weekly_schedule.php';
$result = file_put_contents($filePath, $phpCode);

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'message' => 'Schedule saved successfully!',
        'file' => $filePath
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to write file'
    ]);
}
?>