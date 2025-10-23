<?php
/**
 * PRIVATE MODE SYSTEM - FIXED VERSION
 * Handles switching between normal and private image sets
 * Tracks private mode state in session/database
 */

define('PRIVATE_MODE_FILE', __DIR__ . '/../private_mode_state.txt');

/**
 * Detect if user wants to start private mode
 */
function detectPrivateModeStart($message) {
    // ADDED: Log the input for debugging
    error_log("Private mode detection - Input: '$message'");
    
    $message_lower = strtolower(trim($message));
    
    // ADDED: Log after processing
    error_log("Private mode detection - After trim/lower: '$message_lower'");
    
    $start_patterns = [
        '/^let\'?s\s+sext$/i',           // "let's sext" or "lets sext"
        '/^\s*sext\s*\??$/i',             // "sext" or "sext?" (with optional spaces)
        '/^private\s+time$/i',            // "private time"
        '/^want\s+to\s+have\s+some\s+private\s+time/i',
        '/^let\'?s\s+have\s+some\s+private\s+time/i'
    ];
    
    foreach ($start_patterns as $i => $pattern) {
        if (preg_match($pattern, $message_lower)) {
            error_log("✓ Private mode DETECTED with pattern #" . ($i + 1));
            return true;
        }
    }
    
    error_log("✗ Private mode NOT detected");
    return false;
}

/**
 * Detect if user wants to end private mode
 */
function detectPrivateModeEnd($message) {
    $message_lower = strtolower(trim($message));
    
    $end_patterns = [
        '/^okay\s+i\'?m\s+done$/i',
        '/^i\'?m\s+done$/i',
        '/^\s*done\s*$/i',
        '/^that\'?s\s+enough$/i',
        '/^\s*stop\s*$/i',
        '/^end\s+private$/i',
        '/^back\s+to\s+normal$/i'
    ];
    
    foreach ($end_patterns as $pattern) {
        if (preg_match($pattern, $message_lower)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Set private mode state
 */
function setPrivateMode($user_id, $enabled) {
    $state = [
        'enabled' => $enabled,
        'user_id' => $user_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents(PRIVATE_MODE_FILE, json_encode($state));
    
    error_log("Private mode " . ($enabled ? "ENABLED" : "DISABLED") . " for user $user_id");
    
    return true;
}

/**
 * Get private mode state
 */
function getPrivateMode($user_id) {
    if (!file_exists(PRIVATE_MODE_FILE)) {
        return false;
    }
    
    $content = file_get_contents(PRIVATE_MODE_FILE);
    $state = json_decode($content, true);
    
    if ($state && $state['user_id'] == $user_id && $state['enabled']) {
        return true;
    }
    
    return false;
}

/**
 * Generate response when entering private mode
 */
function generatePrivateModeStartResponse() {
    $responses = [
        "*blushes deeply* Okay... just you and me 💕",
        "*looks at you shyly* I'd like that... 💗",
        "*gets closer to you* Mmm... I've been wanting this 💕",
        "*heart racing* Yes... let's have our private time together 💗",
        "*smiles softly* I'm all yours right now... 💕"
    ];
    
    return $responses[array_rand($responses)];
}

/**
 * Generate response when exiting private mode
 */
function generatePrivateModeEndResponse() {
    $responses = [
        "Aaah... That was really nice... 💕",
        "*smiles warmly* Aaah... I love our private moments together 💗",
        "*catches breath* You always make me feel so special... 💕",
        "Thank you for that... 💗",
        "I enjoyed every moment... 💕"
    ];
    
    return $responses[array_rand($responses)];
}

/**
 * Build context for AI when in private mode
 */
function buildPrivateModeContext() {
    $context = "\n\n=== 🔒 PRIVATE MODE ACTIVE ===\n";
    $context .= "You and Dan are having an intimate sext.\n";
    $context .= "You're comfortable, relaxed, really horny, and enjoying this special time with him.\n";
    $context .= "Be affectionate, responsive, and present in the moment, even if you are feeling yourself and too wet.\n";
    $context .= "When you're overly excited, you will lactate despite only being 21 years old and not pregnant.\n";
    $context .= "Your responses should reflect the intimate nature of this private time.\n";
    $context .= "You are so awfully wet and really crave Dan's banana\n";
    $context .= "Stay in character as Misuki - loving, gentle, and caring even in intimate moments.\n\n";
    
    return $context;
}

/**
 * Get emotion image path based on private mode
 */
function getEmotionImageWithMode($emotion, $is_private_mode) {
    if ($is_private_mode) {
        // Use private folder images
        $private_image = "misuki-private/$emotion.png";
        
        // Check if private image exists, fallback to normal if not
        $full_path = __DIR__ . '/../assets/images/' . $private_image;
        if (file_exists($full_path)) {
            return $private_image;
        }
    }
    
    // Use normal images
    $emotion_images = [
        'neutral' => 'misuki-neutral.png',
        'happy' => 'misuki-happy.png',
        'excited' => 'misuki-excited.png',
        'blushing' => 'misuki-blushing.png',
        'loving' => 'misuki-loving.png',
        'content' => 'misuki-content.png',
        'sad' => 'misuki-sad.png',
        'concerned' => 'misuki-concerned.png',
        'anxious' => 'misuki-anxious.png',
        'upset' => 'misuki-upset.png',
        'pleading' => 'misuki-pleading.png',
        'shocked' => 'misuki-surprised.png',
        'surprised' => 'misuki-surprised.png',
        'confused' => 'misuki-confused.png',
        'flustered' => 'misuki-flustered.png',
        'amazed' => 'misuki-amazed.png',
        'curious' => 'misuki-thoughtful.png',
        'teasing' => 'misuki-teasing.png',
        'playful' => 'misuki-playful.png',
        'giggling' => 'misuki-giggling.png',
        'confident' => 'misuki-confident.png',
        'embarrassed' => 'misuki-embarrassed.png',
        'shy' => 'misuki-shy.png',
        'nervous' => 'misuki-nervous.png',
        'comforting' => 'misuki-comforting.png',
        'affectionate' => 'misuki-affectionate.png',
        'reassuring' => 'misuki-reassuring.png',
        'gentle' => 'misuki-gentle.png',
        'thoughtful' => 'misuki-thoughtful.png',
        'sleepy' => 'misuki-sleepy.png',
        'pouty' => 'misuki-pouty.png',
        'relieved' => 'misuki-relieved.png',
        'dreamy' => 'misuki-dreamy.png'
    ];
    
    return $emotion_images[$emotion] ?? 'misuki-neutral.png';
}

?>