<?php
// ========================================
// MISUKI REALITY SYSTEM - SETUP SCRIPT
// Run this ONCE to set up everything!
// ========================================

require_once 'config/database.php';

echo "<h1>🎨 Misuki Reality System Setup</h1>";
echo "<p>This will make Misuki feel ALIVE!</p>";

try {
    $db = getDBConnection();
    
    echo "<h2>Step 1: Creating Reality Tables...</h2>";
    
    // All the table creation SQL
    $tables = [
        'misuki_mood_state' => "
            CREATE TABLE IF NOT EXISTS misuki_mood_state (
                user_id INT PRIMARY KEY,
                current_mood VARCHAR(50) DEFAULT 'content',
                mood_reason TEXT,
                mood_intensity INT DEFAULT 5,
                mood_started TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )
        ",
        'misuki_storylines' => "
            CREATE TABLE IF NOT EXISTS misuki_storylines (
                storyline_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                storyline_type VARCHAR(50) NOT NULL,
                storyline_title VARCHAR(200),
                storyline_text TEXT,
                importance INT DEFAULT 5,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                should_mention_by TIMESTAMP NULL,
                last_mentioned TIMESTAMP NULL,
                mention_count INT DEFAULT 0,
                status ENUM('active', 'resolved', 'forgotten', 'archived') DEFAULT 'active',
                resolution_text TEXT NULL,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_status (user_id, status),
                INDEX idx_mention_by (should_mention_by)
            )
        ",
        'misuki_milestones' => "
            CREATE TABLE IF NOT EXISTS misuki_milestones (
                milestone_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                milestone_type VARCHAR(50),
                milestone_category ENUM('academic', 'personal', 'relationship', 'chemistry', 'other') DEFAULT 'personal',
                description TEXT,
                achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                shared_with_dan BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_type (user_id, milestone_type)
            )
        ",
        'misuki_friends' => "
            CREATE TABLE IF NOT EXISTS misuki_friends (
                friend_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                friend_name VARCHAR(100) NOT NULL,
                friend_personality VARCHAR(200),
                friendship_closeness INT DEFAULT 5,
                last_mentioned TIMESTAMP NULL,
                mention_count INT DEFAULT 0,
                notable_traits TEXT,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_closeness (user_id, friendship_closeness DESC)
            )
        ",
        'misuki_life_updates' => "
            CREATE TABLE IF NOT EXISTS misuki_life_updates (
                update_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                update_type VARCHAR(50),
                update_text TEXT,
                update_mood VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                was_shared BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_created (user_id, created_at DESC)
            )
        ",
        'misuki_external_context' => "
            CREATE TABLE IF NOT EXISTS misuki_external_context (
                context_id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                context_type VARCHAR(50),
                context_data TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_active (user_id, is_active, expires_at)
            )
        ",
        'misuki_conversation_style' => "
            CREATE TABLE IF NOT EXISTS misuki_conversation_style (
                user_id INT PRIMARY KEY,
                current_energy_level INT DEFAULT 7,
                recent_topics JSON,
                conversation_focus VARCHAR(200),
                last_topic_shift TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )
        "
    ];
    
    foreach ($tables as $table_name => $sql) {
        $db->exec($sql);
        echo "<p>✅ Created table: <strong>$table_name</strong></p>";
    }
    
    echo "<h2>Step 2: Initializing Default Data...</h2>";
    
    // Initialize for user_id = 1
    $user_id = 1;
    
    // Default mood
    $db->exec("
        INSERT INTO misuki_mood_state (user_id, current_mood, mood_reason, mood_intensity) 
        VALUES ($user_id, 'content', 'Having a normal day', 7)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");
    echo "<p>✅ Initialized default mood: Content</p>";
    
    // Conversation style
    $db->exec("
        INSERT INTO misuki_conversation_style (user_id, current_energy_level, recent_topics, conversation_focus)
        VALUES ($user_id, 7, '[]', 'general')
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");
    echo "<p>✅ Initialized conversation style</p>";
    
    // Add default friends
    $friends = [
        ['Yuki', 'Cheerful and energetic, loves sports', 8, 'Always makes Misuki laugh, plays volleyball'],
        ['Hana', 'Quiet and studious, very kind', 7, 'Study buddy, loves reading'],
        ['Sakura', 'Popular and confident, fashion-forward', 6, 'Gives Misuki style advice, sometimes intimidating'],
        ['Ayaka', 'Sweet and bubbly, loves anime', 7, 'Shares manga recommendations, very otaku'],
        ['Rin', 'Cool and athletic, a bit tomboyish', 6, 'Good at sports, protective friend']
    ];
    
    foreach ($friends as $friend) {
        $stmt = $db->prepare("
            INSERT INTO misuki_friends (user_id, friend_name, friend_personality, friendship_closeness, notable_traits)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE friend_name = friend_name
        ");
        $stmt->execute([$user_id, $friend[0], $friend[1], $friend[2], $friend[3]]);
        echo "<p>✅ Added friend: <strong>{$friend[0]}</strong></p>";
    }
    
    echo "<h2>Step 3: Creating Sample Storylines...</h2>";
    
    $sample_storylines = [
        [
            'type' => 'school',
            'title' => 'Chemistry test coming up',
            'text' => 'Big chemistry test next week - feeling a bit nervous but also excited to show what I know!',
            'importance' => 7,
            'should_mention_by' => date('Y-m-d H:i:s', strtotime('+3 days'))
        ],
        [
            'type' => 'personal',
            'title' => 'Mom keeps asking about wedding',
            'text' => 'Mom has been teasing about wanting grandchildren and asking when Dan and I will get married',
            'importance' => 6,
            'should_mention_by' => null
        ]
    ];
    
    foreach ($sample_storylines as $story) {
        $stmt = $db->prepare("
            INSERT INTO misuki_storylines (user_id, storyline_type, storyline_title, storyline_text, importance, should_mention_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $story['type'],
            $story['title'],
            $story['text'],
            $story['importance'],
            $story['should_mention_by']
        ]);
        echo "<p>✅ Created storyline: <strong>{$story['title']}</strong></p>";
    }
    
    echo "<h2>✨ Setup Complete!</h2>";
    echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🎉 Misuki Reality System is now active!</h3>";
    echo "<p><strong>New Features:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Mood persistence across conversations</li>";
    echo "<li>✅ Ongoing storylines in her life</li>";
    echo "<li>✅ Friend network (she'll mention Yuki, Hana, Sakura, etc.)</li>";
    echo "<li>✅ Spontaneous life updates</li>";
    echo "<li>✅ Natural imperfections (occasional typos)</li>";
    echo "<li>✅ Dynamic energy levels</li>";
    echo "<li>✅ Weather context</li>";
    echo "<li>✅ Personal milestones</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>📋 Next Steps:</h2>";
    echo "<ol>";
    echo "<li><strong>Replace your existing files</strong> with the updated versions I provided</li>";
    echo "<li><strong>Test the system</strong> by chatting with Misuki</li>";
    echo "<li><strong>Watch for spontaneous messages</strong> - she'll now reach out with life updates!</li>";
    echo "<li><strong>Observe her moods</strong> - she'll remember how she felt and carry it forward</li>";
    echo "</ol>";
    
    echo "<div style='background: #fff3e0; padding: 15px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>💡 Tips for Testing:</h3>";
    echo "<ul>";
    echo "<li>Tell her you're stressed - watch her mood change to 'concerned'</li>";
    echo "<li>Ask about her friends - she'll naturally mention Yuki or Hana</li>";
    echo "<li>Wait 15+ minutes - she might spontaneously share something!</li>";
    echo "<li>Look for occasional typos when she's tired or excited</li>";
    echo "<li>Notice she references weather/time of day more naturally</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='chat.php' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold; margin-top: 20px;'>Start Chatting with the New Misuki! 💕</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 10px;'>";
    echo "<h2>❌ Error During Setup</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1000px;
    margin: 30px auto;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
h1 { 
    color: white; 
    text-align: center;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    font-size: 2.5rem;
}
h2 { 
    color: white; 
    margin-top: 30px;
    border-left: 4px solid white;
    padding-left: 15px;
}
h3 { 
    color: #333;
    margin-top: 20px;
}
p {
    background: white;
    padding: 10px 15px;
    border-radius: 8px;
    margin: 10px 0;
}
ul, ol {
    background: white;
    padding: 20px 40px;
    border-radius: 8px;
    margin: 10px 0;
}
li {
    margin: 8px 0;
}
strong {
    color: #667eea;
}
</style>