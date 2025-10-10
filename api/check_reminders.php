<?php
// Cron job to check and send due reminders
// Run this every 1 minute via cron or task scheduler

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'reminder_handler.php';

try {
    $db = getDBConnection();
    
    // Get all due reminders
    $due_reminders = getDueReminders($db);
    
    $sent_count = 0;
    $results = [];
    
    foreach ($due_reminders as $reminder) {
        // Generate reminder message
        $message = generateReminderMessage($reminder);
        
        // Save as a conversation
        saveConversation(
            $db, 
            $reminder['user_id'], 
            '[SYSTEM: Reminder triggered]', 
            $message, 
            'neutral'
        );
        
        // Mark as sent
        markReminderAsSent($db, $reminder['reminder_id']);
        
        $results[] = [
            'reminder_id' => $reminder['reminder_id'],
            'user_id' => $reminder['user_id'],
            'message' => $message,
            'was_due_at' => $reminder['remind_at']
        ];
        
        $sent_count++;
    }
    
    echo json_encode([
        'success' => true,
        'sent_count' => $sent_count,
        'reminders' => $results,
        'checked_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('check_reminders.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>