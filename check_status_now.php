<?php
/**
 * QUICK STATUS CHECKER
 * Shows exactly what Misuki's status is RIGHT NOW
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/misuki_schedule.php';

echo "<h1>🔍 Misuki Status Debug</h1>";

// Set timezones for display
date_default_timezone_set('Asia/Jakarta');
$jakarta_time = date('Y-m-d H:i:s l');

date_default_timezone_set('Asia/Tokyo');
$japan_time = date('Y-m-d H:i:s l');

date_default_timezone_set('Asia/Jakarta');

echo "<h2>Current Time:</h2>";
echo "<p><strong>Jakarta (Your time):</strong> $jakarta_time</p>";
echo "<p><strong>Japan/Saitama (Misuki's time):</strong> $japan_time</p>";

echo "<hr>";

try {
    $db = getDBConnection();
    
    // Test the actual function
    echo "<h2>What getMisukiCurrentStatus() Returns:</h2>";
    $status = getMisukiCurrentStatus($db, 1);
    
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    print_r($status);
    echo "</pre>";
    
    echo "<h3>Status Breakdown:</h3>";
    echo "<p><strong>Status:</strong> " . ($status['status'] ?? 'N/A') . "</p>";
    echo "<p><strong>Emoji:</strong> " . ($status['emoji'] ?? 'N/A') . "</p>";
    echo "<p><strong>Text:</strong> " . ($status['text'] ?? 'N/A') . "</p>";
    echo "<p><strong>Detail:</strong> " . ($status['detail'] ?? 'N/A') . "</p>";
    echo "<p><strong>Was woken:</strong> " . (($status['was_woken'] ?? false) ? 'Yes' : 'No') . "</p>";
    
    echo "<hr>";
    
    // Test the weekly schedule directly
    echo "<h2>What misuki_weekly_schedule.php Returns:</h2>";
    
    if (file_exists('includes/misuki_weekly_schedule.php')) {
        require_once 'includes/misuki_weekly_schedule.php';
        
        if (function_exists('getMisukiCurrentActivity')) {
            $activity = getMisukiCurrentActivity();
            
            echo "<pre style='background: #e8f5e9; padding: 15px; border-radius: 5px;'>";
            print_r($activity);
            echo "</pre>";
            
            echo "<h3>Activity Breakdown:</h3>";
            echo "<p><strong>Time:</strong> " . ($activity['time'] ?? 'N/A') . "</p>";
            echo "<p><strong>Activity:</strong> " . ($activity['activity'] ?? 'N/A') . "</p>";
            echo "<p><strong>Emoji:</strong> " . ($activity['emoji'] ?? 'N/A') . "</p>";
            echo "<p><strong>Type:</strong> " . ($activity['type'] ?? 'N/A') . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Function getMisukiCurrentActivity() not found!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ File includes/misuki_weekly_schedule.php not found!</p>";
    }
    
    echo "<hr>";
    
    // Check for schedule overrides
    echo "<h2>Schedule Overrides:</h2>";
    
    if (file_exists('includes/adaptive_schedule.php')) {
        require_once 'includes/adaptive_schedule.php';
        
        if (function_exists('getActiveScheduleOverride')) {
            $override = getActiveScheduleOverride($db, 1);
            
            if ($override) {
                echo "<p style='color: orange;'><strong>⚠️ Active Override Found!</strong></p>";
                echo "<pre style='background: #fff3e0; padding: 15px; border-radius: 5px;'>";
                print_r($override);
                echo "</pre>";
            } else {
                echo "<p style='color: green;'>✅ No active overrides (using normal schedule)</p>";
            }
        }
    }
    
    echo "<hr>";
    
    // Check database table
    echo "<h2>Database Table Check:</h2>";
    
    $tables_to_check = ['misuki_weekly_schedule', 'misuki_schedule_overrides'];
    
    foreach ($tables_to_check as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p><strong>Table '$table':</strong> Exists</p>";
            
            // Count rows
            $count_stmt = $db->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "<p>Rows: $count</p>";
            
            // Show data if it has rows
            if ($count > 0 && $count < 50) {
                $data_stmt = $db->query("SELECT * FROM $table LIMIT 10");
                $data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<details><summary>Show data</summary>";
                echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 11px;'>";
                print_r($data);
                echo "</pre>";
                echo "</details>";
            }
        } else {
            echo "<p style='color: #999;'>Table '$table': Does not exist</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 1000px;
        margin: 30px auto;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    h1, h2, h3 {
        color: white;
    }
    p, pre, details {
        background: white;
        padding: 10px;
        border-radius: 5px;
        margin: 10px 0;
    }
    hr {
        border: none;
        border-top: 2px solid white;
        margin: 30px 0;
    }
</style>