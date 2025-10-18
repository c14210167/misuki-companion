<?php
/**
 * CORE MEMORY SYSTEM
 * Stores only the most important things Dan says that Misuki should remember forever
 * This is selective - not everything goes here, only meaningful personal information
 */

define('CORE_MEMORIES_FILE', __DIR__ . '/../CoreMemories.txt');
define('MAX_CORE_MEMORIES', 50); // Limit to prevent file from growing too large

/**
 * Determine if something should be stored as a core memory
 * Uses AI to decide importance
 */
function shouldStoreAsCoreMemory($user_message, $context = '') {
    // Quick filters - these are NEVER core memories
    $trivial_patterns = [
        '/^(hi|hello|hey|yo|sup|what\'s up|how are you)/i',
        '/^(ok|okay|sure|yeah|yep|nope|no|yes)/i',
        '/^lol|haha|hehe/i',
        '/^(thanks|thank you|thx)/i'
    ];
    
    foreach ($trivial_patterns as $pattern) {
        if (preg_match($pattern, trim($user_message))) {
            return false;
        }
    }
    
    // Must be at least somewhat substantial
    $word_count = str_word_count($user_message);
    if ($word_count < 5) {
        return false;
    }
    
    // Use AI to determine if this is core memory worthy
    $api_key = loadApiKeyForMemory();
    if (!$api_key) {
        return false; // Can't determine without API
    }
    
    $prompt = buildCoreMemoryPrompt($user_message, $context);
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'claude-3-haiku-20240307',
        'max_tokens' => 150,
        'system' => $prompt,
        'messages' => [
            ['role' => 'user', 'content' => 'Is this a core memory?']
        ],
        'temperature' => 0.3
    ]));
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['content'][0]['text'])) {
            $decision = trim($result['content'][0]['text']);
            
            // Parse YES/NO response
            if (preg_match('/^YES/i', $decision)) {
                // Extract the summary if provided
                if (preg_match('/MEMORY:\s*(.+?)$/is', $decision, $matches)) {
                    return [
                        'should_store' => true,
                        'summary' => trim($matches[1])
                    ];
                }
                return ['should_store' => true, 'summary' => $user_message];
            }
        }
    }
    
    return false;
}

/**
 * Build prompt for AI to decide if message is core memory worthy
 */
function buildCoreMemoryPrompt($message, $context) {
    $prompt = "You are Misuki, a caring AI girlfriend. Dan just said:\n\n";
    $prompt .= "\"$message\"\n\n";
    
    if (!empty($context)) {
        $prompt .= "Context: $context\n\n";
    }
    
    $prompt .= "CORE MEMORIES are things you should NEVER forget about Dan. These include:\n";
    $prompt .= "✓ Personal information (birthday, age, job, family members, living situation)\n";
    $prompt .= "✓ Important life events (graduations, job changes, big accomplishments)\n";
    $prompt .= "✓ Deep personal feelings, fears, dreams, goals\n";
    $prompt .= "✓ Significant relationships and people in his life\n";
    $prompt .= "✓ Important preferences (allergies, phobias, strong likes/dislikes)\n";
    $prompt .= "✓ Meaningful promises or commitments\n";
    $prompt .= "✓ Health information\n";
    $prompt .= "✓ Traumatic or very emotional experiences\n\n";
    
    $prompt .= "NOT core memories:\n";
    $prompt .= "✗ Casual chat (\"how are you\", \"what's up\")\n";
    $prompt .= "✗ Small daily activities (\"I'm eating lunch\", \"just woke up\")\n";
    $prompt .= "✗ Questions to you\n";
    $prompt .= "✗ Reactions (\"lol\", \"that's cool\", \"okay\")\n";
    $prompt .= "✗ Temporary states (\"I'm tired\", \"I'm bored\")\n";
    $prompt .= "✗ Minor preferences (\"I like pizza\")\n\n";
    
    $prompt .= "Is this a CORE MEMORY?\n\n";
    $prompt .= "Respond with:\n";
    $prompt .= "YES - MEMORY: [brief 1-sentence summary of what to remember]\n";
    $prompt .= "or\n";
    $prompt .= "NO - [brief reason why not]\n\n";
    $prompt .= "Be selective. Only YES if this is truly important long-term information.";
    
    return $prompt;
}

/**
 * Save a core memory
 */
function saveCoreMemory($memory_text) {
    // Create file if doesn't exist
    if (!file_exists(CORE_MEMORIES_FILE)) {
        file_put_contents(CORE_MEMORIES_FILE, "# CORE MEMORIES - Important things Misuki should never forget about Dan\n");
        file_put_contents(CORE_MEMORIES_FILE, "# Only the most meaningful personal information is stored here\n\n", FILE_APPEND);
    }
    
    // Check current memory count
    $current_memories = getCoreMemories();
    if (count($current_memories) >= MAX_CORE_MEMORIES) {
        // Remove oldest memory to make room (FIFO)
        array_shift($current_memories);
        rewriteCoreMemories($current_memories);
    }
    
    // Add new memory
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $memory_text\n";
    
    file_put_contents(CORE_MEMORIES_FILE, $entry, FILE_APPEND);
    
    return true;
}

/**
 * Get all core memories
 */
function getCoreMemories() {
    if (!file_exists(CORE_MEMORIES_FILE)) {
        return [];
    }
    
    $content = file_get_contents(CORE_MEMORIES_FILE);
    $lines = explode("\n", $content);
    
    $memories = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Extract timestamp and memory
        if (preg_match('/\[(.*?)\]\s*(.+)/', $line, $matches)) {
            $memories[] = [
                'timestamp' => $matches[1],
                'memory' => $matches[2]
            ];
        }
    }
    
    return $memories;
}

/**
 * Rewrite core memories file (used when trimming)
 */
function rewriteCoreMemories($memories) {
    $content = "# CORE MEMORIES - Important things Misuki should never forget about Dan\n";
    $content .= "# Only the most meaningful personal information is stored here\n\n";
    
    foreach ($memories as $memory) {
        $content .= "[{$memory['timestamp']}] {$memory['memory']}\n";
    }
    
    file_put_contents(CORE_MEMORIES_FILE, $content);
}

/**
 * Get recent core memories (last N)
 */
function getRecentCoreMemories($limit = 10) {
    $all_memories = getCoreMemories();
    return array_slice($all_memories, -$limit);
}

/**
 * Build context for AI from core memories
 */
function buildCoreMemoryContext() {
    $memories = getCoreMemories();
    
    if (empty($memories)) {
        return '';
    }
    
    $context = "\n=== CORE MEMORIES (Things You Must Never Forget About Dan) ===\n";
    
    // Show most recent memories first (they're usually most relevant)
    $memories = array_reverse($memories);
    
    foreach ($memories as $memory) {
        $context .= "• {$memory['memory']}\n";
    }
    
    $context .= "\nThese are the most important things Dan has told you. Reference them when relevant!\n";
    
    return $context;
}

/**
 * Search core memories for relevant information
 */
function searchCoreMemories($query) {
    $memories = getCoreMemories();
    $query_lower = strtolower($query);
    
    $relevant = [];
    foreach ($memories as $memory) {
        $memory_lower = strtolower($memory['memory']);
        
        // Simple keyword matching
        $query_words = explode(' ', $query_lower);
        $match_count = 0;
        
        foreach ($query_words as $word) {
            if (strlen($word) > 3 && strpos($memory_lower, $word) !== false) {
                $match_count++;
            }
        }
        
        if ($match_count > 0) {
            $relevant[] = $memory;
        }
    }
    
    return $relevant;
}

/**
 * Load API key for memory operations
 */
function loadApiKeyForMemory() {
    $api_key = getenv('ANTHROPIC_API_KEY');
    
    if (!$api_key) {
        $env_path = dirname(dirname(__FILE__)) . '/.env';
        if (file_exists($env_path)) {
            $env_contents = file_get_contents($env_path);
            if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env_contents, $matches)) {
                $api_key = trim($matches[1]);
                $api_key = trim($api_key, '"\'');
            }
        }
    }
    
    return $api_key;
}

/**
 * Detect if this moment should be saved as a core memory
 * Wrapper around shouldStoreAsCoreMemory with additional checks
 */
function detectCoreMemoryMoment($user_message, $message_analysis, $current_mood) {
    // Don't store if message is too short
    if (strlen($user_message) < 10) {
        return false;
    }
    
    // Don't store trivial messages
    $trivial = ['hi', 'hello', 'hey', 'ok', 'okay', 'lol', 'haha'];
    $message_lower = strtolower(trim($user_message));
    if (in_array($message_lower, $trivial)) {
        return false;
    }
    
    // Build context from mood and analysis
    $context = '';
    if (isset($current_mood['current_mood'])) {
        $context .= "Misuki's mood: " . $current_mood['current_mood'] . ". ";
    }
    if (isset($message_analysis['dominant_emotion'])) {
        $context .= "Dan's emotion: " . $message_analysis['dominant_emotion'] . ". ";
    }
    
    // Use AI to determine if this is memory-worthy
    return shouldStoreAsCoreMemory($user_message, $context);
}

/**
 * Create and save a core memory from user's message
 * Returns the memory info if saved, false otherwise
 */
function createCoreMemory($db, $user_id, $user_message, $message_analysis) {
    $decision = shouldStoreAsCoreMemory($user_message);
    
    if (!$decision || !$decision['should_store']) {
        return false;
    }
    
    // Save to file
    $memory_text = $decision['summary'] ?? $user_message;
    $saved = saveCoreMemory($memory_text);
    
    if (!$saved) {
        return false;
    }
    
    // Return memory info
    return [
        'description' => $memory_text,
        'emotion' => $message_analysis['dominant_emotion'] ?? 'neutral',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Detect and update user's location from their message
 * Returns location info and context
 */
function detectAndUpdateLocation($db, $user_id, $message) {
    $detected_location = detectLocationMention($message);
    
    if ($detected_location) {
        // Save the location
        trackUserLocation($db, $user_id, $detected_location);
        
        // Build context for AI
        $context = "\n\n=== LOCATION UPDATE ===\n";
        $context .= "Dan just mentioned he's $detected_location.\n";
        $context .= "Reference this naturally in your response if relevant!\n";
        
        return [
            'location' => $detected_location,
            'context' => $context,
            'updated' => true
        ];
    }
    
    // Check if we have a stored location
    $current_location = getUserCurrentLocation($db, $user_id);
    
    if ($current_location) {
        $context = "\n\n=== DAN'S CURRENT LOCATION ===\n";
        $context .= "Last known location: $current_location\n";
        
        return [
            'location' => $current_location,
            'context' => $context,
            'updated' => false
        ];
    }
    
    // No location info
    return [
        'location' => null,
        'context' => '',
        'updated' => false
    ];
}

?>