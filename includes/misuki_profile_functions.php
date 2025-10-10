<?php
// Misuki's Personal Profile Functions
// Separate from Dan's profile to avoid confusion

function getMisukiProfile($db) {
    $stmt = $db->prepare("
        SELECT profile_category, profile_key, profile_value 
        FROM misuki_profile 
        WHERE is_active = TRUE
        ORDER BY profile_category, profile_key
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildMisukiProfileContext($misuki_profile) {
    if (empty($misuki_profile)) {
        return '';
    }
    
    $context = "\n=== MISUKI'S OWN LIFE (THIS IS YOU!) ===\n\n";
    $context .= "⚠️ CRITICAL: This section is about YOU (Misuki), NOT about Dan!\n\n";
    
    // Group by category
    $grouped = [];
    foreach ($misuki_profile as $item) {
        $cat = $item['profile_category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $item;
    }
    
    // Category labels
    $category_labels = [
        'identity' => '👤 Your Identity',
        'location' => '📍 Where You Live',
        'family' => '👨‍👩‍👧 Your Family',
        'routine' => '📅 Your Routines',
        'education' => '📚 Your Education',
        'relationship' => '💕 Your Relationship',
        'dan_family' => '👨‍👩‍👧‍👦 Dan\'s Family (for reference)'
    ];
    
    foreach ($grouped as $category => $items) {
        $label = $category_labels[$category] ?? ucfirst($category);
        $context .= "$label:\n";
        foreach ($items as $item) {
            $context .= "  • {$item['profile_value']}\n";
        }
        $context .= "\n";
    }
    
    $context .= "=== CRITICAL REMINDERS ===\n";
    $context .= "✓ YOU are Misuki - answer questions from YOUR perspective!\n";
    $context .= "✓ When Dan asks 'how was your visit?' on a Friday/Saturday, he's asking about YOUR visit to YOUR dad!\n";
    $context .= "✓ YOU live in Saitama, Japan - Dan lives in Surabaya, Indonesia\n";
    $context .= "✓ YOUR mom is Sara - Dan's family is separate\n";
    $context .= "✓ YOUR Friday visits are to YOUR dad Reo - not Dan's visits\n\n";
    
    return $context;
}

function updateMisukiProfile($db, $category, $key, $value) {
    $stmt = $db->prepare("
        INSERT INTO misuki_profile (profile_category, profile_key, profile_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            profile_value = VALUES(profile_value),
            updated_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$category, $key, $value]);
}

function getMisukiProfileByCategory($db, $category) {
    $stmt = $db->prepare("
        SELECT profile_key, profile_value 
        FROM misuki_profile 
        WHERE profile_category = ? AND is_active = TRUE
        ORDER BY profile_key
    ");
    $stmt->execute([$category]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>