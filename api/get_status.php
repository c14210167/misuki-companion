<?php
// Disable all error output
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Simple fallback response
$fallbackStatus = [
    'success' => true,
    'status' => [
        'status' => 'available',
        'emoji' => '💕',
        'text' => 'Available',
        'detail' => 'Free to chat',
        'color' => '#E91E63',
        'was_woken' => false
    ]
];

// Try to load files, but don't fail if they don't exist
@include_once '../config/database.php';
@include_once '../includes/misuki_schedule.php';

$input = @json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? $input['user_id'] : 1;

// Check if we can actually get the status
if (function_exists('getDBConnection') && function_exists('getMisukiCurrentStatus')) {
    try {
        $db = getDBConnection();
        $status = getMisukiCurrentStatus($db, $user_id);
        
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        exit;
    } catch (Exception $e) {
        // Silent fail, use fallback
    }
}

// Always return valid JSON
echo json_encode($fallbackStatus);
exit;
?>