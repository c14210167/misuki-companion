<?php
// ========================================
// STORYLINE MANAGER
// Easily add/edit/resolve Misuki's storylines!
// ========================================

require_once 'config/database.php';
require_once 'includes/misuki_reality_functions.php';

$db = getDBConnection();
$user_id = 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $type = $_POST['type'];
            $title = $_POST['title'];
            $text = $_POST['text'];
            $importance = (int)$_POST['importance'];
            $should_mention = $_POST['should_mention'] ? date('Y-m-d H:i:s', strtotime($_POST['should_mention'])) : null;
            
            createStoryline($db, $user_id, $type, $title, $text, $importance, $should_mention);
            $message = "✅ Storyline created successfully!";
        } elseif ($_POST['action'] === 'resolve') {
            $id = (int)$_POST['storyline_id'];
            $resolution = $_POST['resolution'];
            resolveStoryline($db, $id, $resolution);
            $message = "✅ Storyline resolved!";
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['storyline_id'];
            $stmt = $db->prepare("DELETE FROM misuki_storylines WHERE storyline_id = ?");
            $stmt->execute([$id]);
            $message = "✅ Storyline deleted!";
        }
    }
}

// Get all storylines
$active = getActiveStorylines($db, $user_id);

$stmt = $db->prepare("
    SELECT * FROM misuki_storylines 
    WHERE user_id = ? AND status = 'resolved'
    ORDER BY started_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$resolved = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misuki Storyline Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .message {
            background: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            padding-left: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"],
        input[type="datetime-local"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .storyline {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        
        .storyline h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .storyline-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .badge-importance {
            background: #FFC107;
            color: #333;
        }
        
        .badge-type {
            background: #2196F3;
            color: white;
        }
        
        .storyline-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .btn-danger {
            background: #f44336;
        }
        
        .btn-success {
            background: #4CAF50;
        }
        
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="chat.php" class="back-link">← Back to Chat</a>
        
        <h1>📖 Misuki's Life Storylines</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- Create New Storyline -->
        <div class="card">
            <h2>✨ Create New Storyline</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Type:</label>
                    <select name="type" required>
                        <option value="school">School/Academic</option>
                        <option value="personal">Personal Life</option>
                        <option value="family">Family</option>
                        <option value="chemistry">Chemistry Related</option>
                        <option value="friend">Friend Drama</option>
                        <option value="worry">Worry/Concern</option>
                        <option value="excitement">Exciting Event</option>
                        <option value="hobby">Hobby/Interest</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Title (Short summary):</label>
                    <input type="text" name="title" placeholder="e.g., Big chemistry test coming up" required>
                </div>
                
                <div class="form-group">
                    <label>Description (What's happening):</label>
                    <textarea name="text" placeholder="Describe what's happening in Misuki's life..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Importance (1-10):</label>
                    <input type="number" name="importance" min="1" max="10" value="5" required>
                </div>
                
                <div class="form-group">
                    <label>Should Mention By (optional):</label>
                    <input type="datetime-local" name="should_mention">
                    <small style="color: #666; display: block; margin-top: 5px;">If set, Misuki will try to bring this up by this date/time</small>
                </div>
                
                <button type="submit">Create Storyline</button>
            </form>
        </div>
        
        <!-- Active Storylines -->
        <div class="card">
            <h2>🔥 Active Storylines (<?= count($active) ?>)</h2>
            
            <?php if (empty($active)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">No active storylines. Create one above!</p>
            <?php else: ?>
                <?php foreach ($active as $story): ?>
                    <div class="storyline">
                        <h3><?= htmlspecialchars($story['storyline_title']) ?></h3>
                        <p><?= htmlspecialchars($story['storyline_text']) ?></p>
                        
                        <div class="storyline-meta">
                            <span class="badge badge-type"><?= htmlspecialchars($story['storyline_type']) ?></span>
                            <span class="badge badge-importance">Importance: <?= $story['importance'] ?>/10</span>
                            <?php if ($story['mention_count'] > 0): ?>
                                <span>💬 Mentioned <?= $story['mention_count'] ?> times</span>
                            <?php endif; ?>
                            <?php if ($story['should_mention_by']): ?>
                                <span>⏰ Mention by: <?= date('M j, g:i A', strtotime($story['should_mention_by'])) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="storyline-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="resolve">
                                <input type="hidden" name="storyline_id" value="<?= $story['storyline_id'] ?>">
                                <input type="text" name="resolution" placeholder="How did it end?" required style="width: 300px; margin-right: 10px;">
                                <button type="submit" class="btn-small btn-success">✓ Resolve</button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="storyline_id" value="<?= $story['storyline_id'] ?>">
                                <button type="submit" class="btn-small btn-danger" onclick="return confirm('Delete this storyline?')">✕ Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Resolved Storylines -->
        <?php if (!empty($resolved)): ?>
            <div class="card">
                <h2>✅ Recently Resolved Storylines</h2>
                
                <?php foreach ($resolved as $story): ?>
                    <div class="storyline" style="opacity: 0.7;">
                        <h3><?= htmlspecialchars($story['storyline_title']) ?></h3>
                        <p><?= htmlspecialchars($story['storyline_text']) ?></p>
                        <?php if ($story['resolution_text']): ?>
                            <p style="margin-top: 10px; color: #4CAF50;"><strong>Resolution:</strong> <?= htmlspecialchars($story['resolution_text']) ?></p>
                        <?php endif; ?>
                        <div class="storyline-meta">
                            <span class="badge badge-type"><?= htmlspecialchars($story['storyline_type']) ?></span>
                            <span>Resolved <?= date('M j, Y', strtotime($story['started_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="chat.php" style="display: inline-block; background: white; color: #667eea; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold;">Back to Chat with Misuki 💕</a>
        </div>
    </div>
</body>
</html>