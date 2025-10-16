<?php
/**
 * NICKNAME MEMORY SYSTEM
 * Handles nicknames Dan gives to Misuki and nicknames Misuki gives to Dan
 */

// File paths
define('MISUKI_NICKNAMES_FILE', __DIR__ . '/../MisukiNicknames.txt');
define('DAN_NICKNAMES_FILE', __DIR__ . '/../DanNicknames.txt');

/**
 * Detect if user is giving Misuki a nickname
 */
function detectMisukiNickname($message) {
    $message_lower = strtolower(trim($message));
    
    // First check: Is this just a short greeting/affectionate call?
    // If message is very short (1-3 words) and is JUST the nickname, don't treat as assignment
    $word_count = str_word_count($message);
    if ($word_count <= 3) {
        // Check if this looks like just calling her by the nickname
        // Examples: "my misooks", "hey misooks", "misooks!"
        if (preg_match('/^(hi|hey|hello|yo)?\s*(my\s+)?[a-z]+[!.]*$/i', $message_lower)) {
            return null; // Just using the nickname, not assigning it
        }
    }
    
    // Patterns for explicit nickname assignment
    $patterns = [
        '/(?:i\'ll |i will |gonna |going to )?call you (.+?)(?:\s+from now|\s+now|\s+okay|\s+ok|\.|\!|\?|$)/i',
        '/(?:you\'re |you are |you\'ll be )my (.+?)(?:\s+from now|\s+now|\.|\!|\?|$)/i',
        '/(?:your nickname is |your new name is )(.+?)(?:\s+from now|\s+now|\.|\!|\?|$)/i',
        '/(?:can i call you |let me call you )(.+?)(?:\s+from now|\s+now|\?|$)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message_lower, $matches)) {
            $nickname = trim($matches[1]);
            
            // Clean up the nickname
            $nickname = preg_replace('/\s+(from now|now|okay|ok)$/', '', $nickname);
            $nickname = trim($nickname, '.,!? ');
            
            // Validate nickname (must be reasonable length and not empty)
            if (strlen($nickname) >= 2 && strlen($nickname) <= 30) {
                return $nickname;
            }
        }
    }
    
    // NEW: Detect possessive affectionate names ONLY in longer messages with context
    // This catches: "haha not really... my little misooks hehe"
    // But NOT: "my misooks" (just calling her)
    if ($word_count > 3) { // Only check if message has substance
        if (preg_match('/\bmy\s+(?:little\s+|sweet\s+|dear\s+|lovely\s+)?([a-z]{3,20})\b/i', $message_lower, $matches)) {
            $potential_nickname = trim($matches[1]);
            
            // Make sure it's not a common word (avoid false positives)
            $common_words = ['baby', 'love', 'dear', 'honey', 'sweetheart', 'darling', 'babe', 'girl', 
                            'girlfriend', 'wife', 'queen', 'princess', 'angel', 'heart', 'life', 'world',
                            'friend', 'buddy', 'dude', 'man', 'god', 'day', 'night', 'time', 'house', 'room',
                            'dog', 'cat', 'pet', 'car', 'phone', 'computer', 'laptop', 'mom', 'dad', 'sister',
                            'brother', 'cousin', 'uncle', 'aunt', 'boss', 'teacher', 'doctor', 'neighbor'];
            
            if (!in_array($potential_nickname, $common_words)) {
                // CRITICAL: Check that this is DIRECTLY addressed to Misuki
                // Only save if the message is clearly TO her, not ABOUT someone/something else
                
                // Red flags - if these appear, it's probably NOT a nickname for Misuki
                $red_flags = [
                    'dog', 'cat', 'pet', 'named', 'called', 'puppy', 'kitten',
                    'he ', 'she ', 'they ', 'his ', 'her ', 'their ',
                    'is a', 'was a', 'went', 'did', 'pissed', 'barked', 'meowed'
                ];
                
                foreach ($red_flags as $flag) {
                    if (stripos($message, $flag) !== false) {
                        return null; // This is about something else, not Misuki
                    }
                }
                
                // Green lights - indicators this IS for Misuki
                $nickname_indicators = [
                    'misuki', 'misu', 'miki', 'suki', // variations of her actual name
                    'tanaka', // her last name
                ];
                
                // Check if potential nickname is similar to her actual name
                $name_similarity = false;
                if (stripos($potential_nickname, 'misu') !== false || 
                    stripos($potential_nickname, 'miki') !== false || 
                    stripos($potential_nickname, 'suki') !== false ||
                    levenshtein(strtolower($potential_nickname), 'misuki') <= 3) {
                    $name_similarity = true;
                }
                
                // Only accept if: name similarity OR explicit indicators
                foreach ($nickname_indicators as $indicator) {
                    if (stripos($message, $indicator) !== false) {
                        return $potential_nickname;
                    }
                }
                
                if ($name_similarity) {
                    return $potential_nickname;
                }
            }
        }
    }
    
    return null;
}

/**
 * Detect if Misuki is giving Dan a nickname
 * This should be called AFTER Misuki generates her response
 */
function detectDanNicknameInResponse($response) {
    $response_lower = strtolower(trim($response));
    
    // Patterns where Misuki assigns a nickname
    $patterns = [
        '/(?:i\'ll |i will |gonna |going to )?call you (.+?)(?:\s+from now|\s+now|,|\.|!|\?|$)/i',
        '/(?:you\'re |you are )my (.+?)(?:\s+from now|\s+now|,|\.|!|\?|$)/i',
        '/(?:can i call you |let me call you )(.+?)(?:\?|$)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $response_lower, $matches)) {
            $nickname = trim($matches[1]);
            
            // Clean up
            $nickname = preg_replace('/\s+(from now|now|okay|ok)$/', '', $nickname);
            $nickname = trim($nickname, '.,!? ');
            
            // Validate
            if (strlen($nickname) >= 2 && strlen($nickname) <= 30) {
                return $nickname;
            }
        }
    }
    
    return null;
}

/**
 * Save nickname for Misuki
 */
function saveMisukiNickname($nickname) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "$timestamp | $nickname\n";
    
    // Create file if doesn't exist
    if (!file_exists(MISUKI_NICKNAMES_FILE)) {
        file_put_contents(MISUKI_NICKNAMES_FILE, "# Nicknames Dan gave to Misuki\n\n");
    }
    
    // Append new nickname
    file_put_contents(MISUKI_NICKNAMES_FILE, $entry, FILE_APPEND);
    
    return true;
}

/**
 * Save nickname for Dan
 */
function saveDanNickname($nickname) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "$timestamp | $nickname\n";
    
    // Create file if doesn't exist
    if (!file_exists(DAN_NICKNAMES_FILE)) {
        file_put_contents(DAN_NICKNAMES_FILE, "# Nicknames Misuki gave to Dan\n\n");
    }
    
    // Append new nickname
    file_put_contents(DAN_NICKNAMES_FILE, $entry, FILE_APPEND);
    
    return true;
}

/**
 * Get all nicknames for Misuki
 */
function getMisukiNicknames() {
    if (!file_exists(MISUKI_NICKNAMES_FILE)) {
        return [];
    }
    
    $content = file_get_contents(MISUKI_NICKNAMES_FILE);
    $lines = explode("\n", $content);
    
    $nicknames = [];
    foreach ($lines as $line) {
        if (empty(trim($line)) || strpos($line, '#') === 0) {
            continue;
        }
        
        $parts = explode('|', $line);
        if (count($parts) >= 2) {
            $nicknames[] = [
                'timestamp' => trim($parts[0]),
                'nickname' => trim($parts[1])
            ];
        }
    }
    
    return $nicknames;
}

/**
 * Get all nicknames for Dan
 */
function getDanNicknames() {
    if (!file_exists(DAN_NICKNAMES_FILE)) {
        return [];
    }
    
    $content = file_get_contents(DAN_NICKNAMES_FILE);
    $lines = explode("\n", $content);
    
    $nicknames = [];
    foreach ($lines as $line) {
        if (empty(trim($line)) || strpos($line, '#') === 0) {
            continue;
        }
        
        $parts = explode('|', $line);
        if (count($parts) >= 2) {
            $nicknames[] = [
                'timestamp' => trim($parts[0]),
                'nickname' => trim($parts[1])
            ];
        }
    }
    
    return $nicknames;
}

/**
 * Get current nickname for Misuki (most recent)
 */
function getCurrentMisukiNickname() {
    $nicknames = getMisukiNicknames();
    if (empty($nicknames)) {
        return null;
    }
    
    return end($nicknames)['nickname'];
}

/**
 * Get current nickname for Dan (most recent)
 */
function getCurrentDanNickname() {
    $nicknames = getDanNicknames();
    if (empty($nicknames)) {
        return null;
    }
    
    return end($nicknames)['nickname'];
}

/**
 * Build context for AI about nicknames
 */
function buildNicknameContext() {
    $context = "";
    
    $misuki_nickname = getCurrentMisukiNickname();
    $dan_nickname = getCurrentDanNickname();
    
    if ($misuki_nickname) {
        $context .= "\n=== YOUR NICKNAME ===\n";
        $context .= "Dan calls you: \"$misuki_nickname\"\n";
        $context .= "Remember to acknowledge and appreciate this nickname sometimes!\n";
    }
    
    if ($dan_nickname) {
        $context .= "\n=== DAN'S NICKNAME ===\n";
        $context .= "You call Dan: \"$dan_nickname\"\n";
        $context .= "Use this nickname occasionally in your messages to be affectionate!\n";
    }
    
    return $context;
}

/**
 * Generate response when Dan gives Misuki a nickname
 */
function generateNicknameResponse($nickname) {
    $responses = [
        "\"$nickname\"? *blushes* I... I really like that! 💕",
        "You're calling me \"$nickname\" now? That's so sweet! 😊",
        "\"$nickname\"... *smiles shyly* I'll treasure that nickname 💕",
        "Really? \"$nickname\"? *gets flustered* That makes me so happy! ✨",
        "\"$nickname\"~ I love it! Thank you 💕"
    ];
    
    return $responses[array_rand($responses)];
}

?>