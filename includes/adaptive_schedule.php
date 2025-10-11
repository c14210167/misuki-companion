<?php
// ========================================
// ADAPTIVE SCHEDULE SYSTEM
// Allows Misuki's schedule to change based on plans
// ========================================

/**
 * Get active schedule override
 * Returns the current activity if Misuki is doing something different than her normal schedule
 */
function getActiveScheduleOverride($db, $user_id) {
    // Clean up expired overrides first
    $db->prepare("
        DELETE FROM misuki_schedule_overrides 
        WHERE end_time < NOW() AND status = 'active'
    ")->execute();
    
    // Get current active override
    $stmt = $db->prepare("
        SELECT * FROM misuki_schedule_overrides 
        WHERE user_id = ? 
        AND status = 'active'
        AND start_time <= NOW() 
        AND end_time > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create a schedule override
 * Use this when Misuki has special plans (trip, exam, event, etc.)
 */
function createScheduleOverride($db, $user_id, $activity_type, $activity_text, $activity_detail, $start_time, $end_time, $emoji = '📅', $color = '#9B59B6') {
    $stmt = $db->prepare("
        INSERT INTO misuki_schedule_overrides 
        (user_id, activity_type, activity_text, activity_detail, activity_emoji, activity_color, start_time, end_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $user_id,
        $activity_type,
        $activity_text,
        $activity_detail,
        $emoji,
        $color,
        $start_time,
        $end_time
    ]);
}

/**
 * Cancel a schedule override
 */
function cancelScheduleOverride($db, $plan_id) {
    $stmt = $db->prepare("
        UPDATE misuki_schedule_overrides 
        SET status = 'cancelled' 
        WHERE plan_id = ?
    ");
    return $stmt->execute([$plan_id]);
}

/**
 * Get all upcoming schedule overrides
 */
function getUpcomingOverrides($db, $user_id) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_schedule_overrides 
        WHERE user_id = ? 
        AND status = 'active'
        AND end_time > NOW()
        ORDER BY start_time ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get past schedule overrides
 */
function getPastOverrides($db, $user_id, $limit = 10) {
    $stmt = $db->prepare("
        SELECT * FROM misuki_schedule_overrides 
        WHERE user_id = ? 
        AND (status = 'completed' OR end_time < NOW())
        ORDER BY end_time DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create the schedule overrides table
 * Run this once to set up the table
 */
function createScheduleOverridesTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS misuki_schedule_overrides (
            plan_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            activity_text VARCHAR(100) NOT NULL,
            activity_detail VARCHAR(200),
            activity_emoji VARCHAR(10) DEFAULT '📅',
            activity_color VARCHAR(20) DEFAULT '#9B59B6',
            start_time TIMESTAMP NOT NULL,
            end_time TIMESTAMP NOT NULL,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_active (user_id, status, start_time, end_time)
        )
    ");
}

/**
 * Quick helper functions for common schedule overrides
 */

// Misuki is on a trip
function scheduleTrip($db, $user_id, $destination, $start_time, $end_time) {
    return createScheduleOverride(
        $db,
        $user_id,
        'trip',
        'On a Trip',
        "Visiting $destination",
        $start_time,
        $end_time,
        '✈️',
        '#E91E63'
    );
}

// Misuki has an exam
function scheduleExam($db, $user_id, $subject, $exam_time, $duration_hours = 2) {
    $end_time = date('Y-m-d H:i:s', strtotime($exam_time) + ($duration_hours * 3600));
    return createScheduleOverride(
        $db,
        $user_id,
        'exam',
        'Taking Exam',
        "$subject exam",
        $exam_time,
        $end_time,
        '📝',
        '#F44336'
    );
}

// Misuki is at a special event
function scheduleEvent($db, $user_id, $event_name, $start_time, $end_time) {
    return createScheduleOverride(
        $db,
        $user_id,
        'event',
        'At Event',
        $event_name,
        $start_time,
        $end_time,
        '🎉',
        '#FF9800'
    );
}

// Misuki is sick/resting
function scheduleSickDay($db, $user_id, $start_time, $end_time) {
    return createScheduleOverride(
        $db,
        $user_id,
        'sick',
        'Resting',
        'Not feeling well, resting at home',
        $start_time,
        $end_time,
        '🤒',
        '#9E9E9E'
    );
}

// Misuki has extended study session
function scheduleStudySession($db, $user_id, $subject, $start_time, $end_time) {
    return createScheduleOverride(
        $db,
        $user_id,
        'intensive_study',
        'Studying Hard',
        "Intensive $subject study session",
        $start_time,
        $end_time,
        '📚',
        '#3F51B5'
    );
}

/**
 * Auto-complete overrides that have ended
 */
function autoCompleteExpiredOverrides($db) {
    $stmt = $db->prepare("
        UPDATE misuki_schedule_overrides 
        SET status = 'completed' 
        WHERE status = 'active' 
        AND end_time < NOW()
    ");
    return $stmt->execute();
}

?>