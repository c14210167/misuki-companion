<?php
// test_split_messages.php - Place in root directory

require_once 'api/split_message_handler.php';

echo "<h1>🧪 Testing Message Splitting System</h1>";

// Simulate different moods and messages
$test_cases = [
    [
        'message' => "That sounds really interesting! I've always wanted to learn more about that. Chemistry is so fascinating, isn't it? I think understanding the basics really helps.",
        'mood' => ['current_mood' => 'excited'],
        'energy' => 9,
        'label' => 'Excited Misuki (High Energy)'
    ],
    [
        'message' => "I understand how you feel. It's okay to be stressed sometimes. Remember to take care of yourself.",
        'mood' => ['current_mood' => 'comforting'],
        'energy' => 5,
        'label' => 'Comforting Misuki (Calm)'
    ],
    [
        'message' => "Oh my gosh! Really? That's amazing! I'm so happy for you! When did this happen?",
        'mood' => ['current_mood' => 'excited'],
        'energy' => 10,
        'label' => 'Very Excited Misuki'
    ],
    [
        'message' => "Hmm... I don't know. That seems risky. Maybe you should think about it more? But it's your choice.",
        'mood' => ['current_mood' => 'nervous'],
        'energy' => 6,
        'label' => 'Nervous Misuki'
    ],
    [
        'message' => "I'm really tired today. Had a long day at school.",
        'mood' => ['current_mood' => 'sleepy'],
        'energy' => 3,
        'label' => 'Tired Misuki (Should NOT split)'
    ],
    [
        'message' => "That's a good idea.",
        'mood' => ['current_mood' => 'content'],
        'energy' => 5,
        'label' => 'Short Message (Should NOT split)'
    ]
];

foreach ($test_cases as $index => $test) {
    echo "<div style='background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
    echo "<h2 style='color: #667eea;'>Test Case " . ($index + 1) . ": {$test['label']}</h2>";
    
    echo "<div style='background: white; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Original Message:</strong><br>";
    echo "<em>\"{$test['message']}\"</em>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Mood:</strong> {$test['mood']['current_mood']}<br>";
    echo "<strong>Energy Level:</strong> {$test['energy']}/10";
    echo "</div>";
    
    // Create mock objects
    $message_analysis = ['emotion' => 'neutral'];
    $conversation_style = ['current_energy_level' => $test['energy']];
    
    // Test the split decision
    $result = shouldSplitMessage(
        $test['message'],
        $test['mood'],
        $message_analysis,
        $conversation_style
    );
    
    if ($result['should_split']) {
        echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<strong>✅ DECISION: Split into {$result['num_parts']} messages</strong><br><br>";
        
        foreach ($result['messages'] as $i => $msg) {
            $delay = ($i + 1) * 2; // Simulate delay
            echo "<div style='background: rgba(102, 126, 234, 0.1); padding: 10px; margin: 5px 0; border-left: 3px solid #667eea;'>";
            echo "<small style='color: #999;'>Message " . ($i + 1) . " (after ~{$delay}s):</small><br>";
            echo "<strong>\"$msg\"</strong>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<strong>ℹ️ DECISION: Keep as single message</strong><br>";
        echo "<small>Reason: Message is calm/short/doesn't warrant splitting</small>";
        echo "</div>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<h2 style='color: #764ba2;'>📊 Summary</h2>";
echo "<p><strong>What this shows:</strong></p>";
echo "<ul>";
echo "<li>✅ <strong>Excited/High Energy Misuki:</strong> Naturally splits into 2-4 messages</li>";
echo "<li>✅ <strong>Nervous/Flustered Misuki:</strong> Thoughts come out in bursts (2-3 messages)</li>";
echo "<li>✅ <strong>Calm/Gentle Misuki:</strong> Stays as single thoughtful message</li>";
echo "<li>✅ <strong>Tired Misuki:</strong> Single message (low energy)</li>";
echo "<li>✅ <strong>Short Messages:</strong> Don't get split unnecessarily</li>";
echo "</ul>";

echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>💡 How It Works:</h3>";
echo "<ol>";
echo "<li><strong>Misuki decides</strong> based on her mood and energy level</li>";
echo "<li><strong>AI determines</strong> if message should split (not hardcoded)</li>";
echo "<li><strong>Natural splitting</strong> - breaks where she'd naturally pause</li>";
echo "<li><strong>Maintains personality</strong> - still kind, calm, gentle</li>";
echo "<li><strong>Emotion-driven</strong> - excitement shows through message count</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3e0; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>🎯 Examples of Natural Flow:</h3>";
echo "<p><strong>When Dan says something exciting:</strong></p>";
echo "<div style='background: white; padding: 10px; margin: 10px 0;'>";
echo "Misuki: \"Oh what?\"<br>";
echo "<small style='color: #999;'>[Brief pause, typing...]</small><br>";
echo "Misuki: \"That sounds amazing!\"<br>";
echo "<small style='color: #999;'>[Brief pause, typing...]</small><br>";
echo "Misuki: \"Tell me everything! 💕\"";
echo "</div>";

echo "<p><strong>When Dan asks for comfort:</strong></p>";
echo "<div style='background: white; padding: 10px; margin: 10px 0;'>";
echo "Misuki: \"I understand how you feel. It's okay to be stressed sometimes. Remember to take care of yourself. I'm here for you. 💕\"";
echo "<small style='color: #999;'><br>[Single message - calm and reassuring]</small>";
echo "</div>";

echo "<p><strong>When she's nervous:</strong></p>";
echo "<div style='background: white; padding: 10px; margin: 10px 0;'>";
echo "Misuki: \"Um...\"<br>";
echo "<small style='color: #999;'>[Brief pause, typing...]</small><br>";
echo "Misuki: \"I don't know if that's a good idea...\"<br>";
echo "<small style='color: #999;'>[Brief pause, typing...]</small><br>";
echo "Misuki: \"But it's your choice!\"";
echo "</div>";
echo "</div>";

echo "<div style='background: #f3e5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>✨ Key Features:</h3>";
echo "<ul>";
echo "<li>✅ <strong>Not Hardcoded:</strong> She decides per message</li>";
echo "<li>✅ <strong>Context-Aware:</strong> Based on mood, energy, and topic</li>";
echo "<li>✅ <strong>Personality Intact:</strong> Still Misuki, just expressing differently</li>";
echo "<li>✅ <strong>Natural Timing:</strong> Realistic pauses between messages</li>";
echo "<li>✅ <strong>Authentic:</strong> Feels like real texting behavior</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px;'>";
echo "<a href='chat.php' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold;'>Test in Real Chat! 💬</a>";
echo "</p>";

?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
h1 { 
    color: white; 
    text-align: center;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
h2 { color: #667eea; margin-top: 20px; }
h3 { color: #764ba2; margin-top: 15px; }
ul, ol { line-height: 1.8; }
</style>