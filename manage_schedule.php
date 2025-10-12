<?php
// ========================================
// MISUKI SCHEDULE MANAGER
// Easily manage Misuki's special events!
// ========================================

require_once 'config/database.php';
require_once 'includes/adaptive_schedule.php';

$db = getDBConnection();
$user_id = 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_custom') {
            $type = $_POST['type'];
            $text = $_POST['text'];
            $detail = $_POST['detail'];
            $emoji = $_POST['emoji'];
            $color = $_POST['color'];
            $start = date('Y-m-d H:i:s', strtotime($_POST['start_time']));
            $end = date('Y-m-d H:i:s', strtotime($_POST['end_time']));
            
            createScheduleOverride($db, $user_id, $type, $text, $detail, $start, $end, $emoji, $color);
            $message = "✅ Schedule override created!";
            
        } elseif ($_POST['action'] === 'create_quick') {
            $quick_type = $_POST['quick_type'];
            $start = $_POST['quick_start'];
            
            switch ($quick_type) {
                case 'trip':
                    $destination = $_POST['destination'];
                    $end = $_POST['quick_end'];
                    scheduleTrip($db, $user_id, $destination, 
                        date('Y-m-d H:i:s', strtotime($start)), 
                        date('Y-m-d H:i:s', strtotime($end)));
                    $message = "✅ Trip to $destination scheduled!";
                    break;
                    
                case 'exam':
                    $subject = $_POST['subject'];
                    $duration = (int)$_POST['duration'];
                    scheduleExam($db, $user_id, $subject, 
                        date('Y-m-d H:i:s', strtotime($start)), 
                        $duration);
                    $message = "✅ $subject exam scheduled!";
                    break;
                    
                case 'sick':
                    $end = $_POST['quick_end'];
                    scheduleSickDay($db, $user_id, 
                        date('Y-m-d H:i:s', strtotime($start)), 
                        date('Y-m-d H:i:s', strtotime($end)));
                    $message = "✅ Sick day scheduled!";
                    break;
                    
                case 'event':
                    $event_name = $_POST['event_name'];
                    $end = $_POST['quick_end'];
                    scheduleEvent($db, $user_id, $event_name, 
                        date('Y-m-d H:i:s', strtotime($start)), 
                        date('Y-m-d H:i:s', strtotime($end)));
                    $message = "✅ Event '$event_name' scheduled!";
                    break;
            }
            
        } elseif ($_POST['action'] === 'cancel') {
            $plan_id = (int)$_POST['plan_id'];
            cancelScheduleOverride($db, $plan_id);
            $message = "✅ Schedule override cancelled!";
        }
    }
}

// Get current and upcoming overrides
$upcoming = getUpcomingOverrides($db, $user_id);
$past = getPastOverrides($db, $user_id, 5);

// Auto-complete old events
autoCompleteExpiredOverrides($db);

// Get current status
require_once 'includes/misuki_schedule.php';
$current_status = getMisukiCurrentStatus($db, $user_id);

// Set to Japan time for display
date_default_timezone_set('Asia/Tokyo');
$japan_time = date('l, F j, Y g:i A');
date_default_timezone_set('Asia/Jakarta');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Misuki Schedule Manager</title>
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
            max-width: 1400px;
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
        
        .current-status {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .status-emoji {
            font-size: 4rem;
        }
        
        .status-info h2 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .status-time {
            color: #666;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
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
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
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
            width: 100%;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .schedule-item {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            position: relative;
        }
        
        .schedule-item h3 {
            color: #667eea;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schedule-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
            flex-wrap: wrap;
        }
        
        .cancel-btn {
            padding: 8px 15px;
            font-size: 0.9rem;
            background: #f44336;
            margin-top: 10px;
            width: auto;
        }
        
        .quick-forms {
            display: none;
        }
        
        .quick-forms.active {
            display: block;
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
        
        @media (max-width: 968px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="chat.php" class="back-link">← Back to Chat</a>
        
        <h1>📅 Misuki's Schedule Manager</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- Current Status -->
        <div class="current-status">
            <div class="status-emoji"><?= $current_status['emoji'] ?></div>
            <div class="status-info">
                <h2><?= $current_status['text'] ?></h2>
                <p><?= $current_status['detail'] ?></p>
                <p class="status-time">🇯🇵 Japan Time: <?= $japan_time ?></p>
                <?php if ($current_status['was_woken'] ?? false): ?>
                    <p style="color: #E91E63; font-weight: bold; margin-top: 10px;">
                        ⚠️ She was just woken up by a message!
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid">
            <!-- Quick Add -->
            <div class="card">
                <h2>⚡ Quick Add</h2>
                
                <div class="form-group">
                    <label>Event Type:</label>
                    <select id="quickType" onchange="showQuickForm(this.value)">
                        <option value="">-- Select Type --</option>
                        <option value="trip">✈️ Trip</option>
                        <option value="exam">📝 Exam</option>
                        <option value="sick">🤒 Sick Day</option>
                        <option value="event">🎉 Special Event</option>
                    </select>
                </div>
                
                <!-- Trip Form -->
                <form method="POST" class="quick-forms" id="form-trip">
                    <input type="hidden" name="action" value="create_quick">
                    <input type="hidden" name="quick_type" value="trip">
                    
                    <div class="form-group">
                        <label>Destination:</label>
                        <input type="text" name="destination" placeholder="e.g., Kyoto" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time:</label>
                        <input type="datetime-local" name="quick_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time:</label>
                        <input type="datetime-local" name="quick_end" required>
                    </div>
                    
                    <button type="submit">Create Trip</button>
                </form>
                
                <!-- Exam Form -->
                <form method="POST" class="quick-forms" id="form-exam">
                    <input type="hidden" name="action" value="create_quick">
                    <input type="hidden" name="quick_type" value="exam">
                    
                    <div class="form-group">
                        <label>Subject:</label>
                        <input type="text" name="subject" placeholder="e.g., Chemistry" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Exam Time:</label>
                        <input type="datetime-local" name="quick_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Duration (hours):</label>
                        <input type="number" name="duration" value="2" min="1" max="8" required>
                    </div>
                    
                    <button type="submit">Create Exam</button>
                </form>
                
                <!-- Sick Day Form -->
                <form method="POST" class="quick-forms" id="form-sick">
                    <input type="hidden" name="action" value="create_quick">
                    <input type="hidden" name="quick_type" value="sick">
                    
                    <div class="form-group">
                        <label>Start Time:</label>
                        <input type="datetime-local" name="quick_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time:</label>
                        <input type="datetime-local" name="quick_end" required>
                    </div>
                    
                    <button type="submit">Create Sick Day</button>
                </form>
                
                <!-- Event Form -->
                <form method="POST" class="quick-forms" id="form-event">
                    <input type="hidden" name="action" value="create_quick">
                    <input type="hidden" name="quick_type" value="event">
                    
                    <div class="form-group">
                        <label>Event Name:</label>
                        <input type="text" name="event_name" placeholder="e.g., School Festival" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time:</label>
                        <input type="datetime-local" name="quick_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time:</label>
                        <input type="datetime-local" name="quick_end" required>
                    </div>
                    
                    <button type="submit">Create Event</button>
                </form>
            </div>
            
            <!-- Custom Event -->
            <div class="card">
                <h2>🎨 Custom Event</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_custom">
                    
                    <div class="form-group">
                        <label>Activity Type:</label>
                        <input type="text" name="type" placeholder="e.g., vacation, studying, etc." required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status Text:</label>
                        <input type="text" name="text" placeholder="e.g., On Vacation" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Detail:</label>
                        <input type="text" name="detail" placeholder="e.g., Visiting Osaka with family" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Emoji:</label>
                        <input type="text" name="emoji" value="📅" maxlength="2" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Color (hex):</label>
                        <input type="text" name="color" value="#9B59B6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time:</label>
                        <input type="datetime-local" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time:</label>
                        <input type="datetime-local" name="end_time" required>
                    </div>
                    
                    <button type="submit">Create Custom Event</button>
                </form>
            </div>
        </div>
        
        <!-- Upcoming Events -->
        <div class="card">
            <h2>📋 Upcoming Schedule Overrides (<?= count($upcoming) ?>)</h2>
            
            <?php if (empty($upcoming)): ?>
                <p style="color: #999; text-align: center; padding: 20px;">
                    No upcoming events. Misuki is following her normal schedule!
                </p>
            <?php else: ?>
                <?php foreach ($upcoming as $event): ?>
                    <div class="schedule-item">
                        <h3>
                            <?= htmlspecialchars($event['activity_emoji']) ?>
                            <?= htmlspecialchars($event['activity_text']) ?>
                        </h3>
                        <p><?= htmlspecialchars($event['activity_detail']) ?></p>
                        
                        <div class="schedule-meta">
                            <span>📅 <?= date('M j, Y', strtotime($event['start_time'])) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></span>
                            <span>⏱️ <?= round((strtotime($event['end_time']) - strtotime($event['start_time'])) / 3600, 1) ?> hours</span>
                        </div>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="plan_id" value="<?= $event['plan_id'] ?>">
                            <button type="submit" class="cancel-btn" onclick="return confirm('Cancel this event?')">
                                ✕ Cancel
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Past Events -->
        <?php if (!empty($past)): ?>
            <div class="card">
                <h2>✅ Recently Completed</h2>
                
                <?php foreach ($past as $event): ?>
                    <div class="schedule-item" style="opacity: 0.7;">
                        <h3>
                            <?= htmlspecialchars($event['activity_emoji']) ?>
                            <?= htmlspecialchars($event['activity_text']) ?>
                        </h3>
                        <p><?= htmlspecialchars($event['activity_detail']) ?></p>
                        
                        <div class="schedule-meta">
                            <span>📅 <?= date('M j, Y', strtotime($event['start_time'])) ?></span>
                            <span>✅ Completed</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="chat.php" style="display: inline-block; background: white; color: #667eea; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-weight: bold;">
                Back to Chat with Misuki 💕
            </a>
        </div>
    </div>
    
    <script>
        function showQuickForm(type) {
            // Hide all forms
            document.querySelectorAll('.quick-forms').forEach(form => {
                form.classList.remove('active');
            });
            
            // Show selected form
            if (type) {
                document.getElementById('form-' + type).classList.add('active');
            }
        }
    </script>
</body>
</html>