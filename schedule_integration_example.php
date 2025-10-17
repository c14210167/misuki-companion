<?php
// =========================================
// MISUKI SCHEDULE INTEGRATION EXAMPLE
// How to use the new detailed schedule system
// =========================================

require_once 'misuki_weekly_schedule.php';

// ===== EXAMPLE USAGE =====

// 1. Get Misuki's current activity
$currentActivity = getMisukiCurrentActivity();
echo "Current activity: " . $currentActivity['activity'] . " " . $currentActivity['emoji'] . "\n";
echo "Type: " . $currentActivity['type'] . "\n";
echo "Time: " . $currentActivity['time'] . "\n\n";

// 2. Get simple status text
$statusText = getMisukiStatusText();
echo "Status: $statusText\n\n";

// 3. Check if she's available to chat
$isAvailable = isMisukiAvailableToChat();
echo "Available to chat: " . ($isAvailable ? "Yes ✅" : "No ❌") . "\n\n";

// 4. Get detailed status
$detailedStatus = getMisukiDetailedStatus();
echo "Detailed status:\n";
print_r($detailedStatus);

// ===== INTEGRATION WITH EXISTING SYSTEM =====

/**
 * Replace the old getMisukiStatus() function in includes/misuki_schedule.php
 * with this new implementation:
 */

function getMisukiStatus($db, $user_id) {
    // Use the new detailed schedule
    require_once 'misuki_weekly_schedule.php';
    
    $detailedStatus = getMisukiDetailedStatus();
    
    // Return in the format expected by the existing system
    return [
        'current_status' => $detailedStatus['status'],
        'status_emoji' => $detailedStatus['emoji'],
        'is_available' => $detailedStatus['available'],
        'activity_type' => $detailedStatus['type']
    ];
}

/**
 * Usage in api/chat.php:
 * 
 * // Get Misuki's current status from detailed schedule
 * require_once 'misuki_weekly_schedule.php';
 * $misuki_status = getMisukiDetailedStatus();
 * 
 * // Build context with current activity
 * $activity_context = "\n\n=== YOUR CURRENT ACTIVITY ===\n";
 * $activity_context .= "Right now you are: " . $misuki_status['status'] . " " . $misuki_status['emoji'] . "\n";
 * 
 * if (!$misuki_status['available']) {
 *     $activity_context .= "You're currently busy with this activity.\n";
 * }
 * 
 * // Add to system prompt
 * $system_prompt .= $activity_context;
 */

/**
 * Display in index.php:
 * 
 * require_once 'misuki_weekly_schedule.php';
 * $current_activity = getMisukiStatusText();
 * 
 * // Show in the UI
 * echo "<div class='misuki-status'>$current_activity</div>";
 */

/**
 * Check availability before sending messages:
 * 
 * if (!isMisukiAvailableToChat()) {
 *     $currentActivity = getMisukiCurrentActivity();
 *     
 *     if ($currentActivity['type'] === 'sleep') {
 *         echo "Misuki is sleeping right now 😴";
 *     } else if ($currentActivity['type'] === 'class') {
 *         echo "Misuki is in class right now 📚";
 *     }
 * }
 */

?>