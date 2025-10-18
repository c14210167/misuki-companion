<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Whitespace Hunter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; }
        .issue {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .clean {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        code {
            background: #263238;
            color: #aed581;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Whitespace & Output Hunter</h1>
        <p>Checking all PHP files for content that would break JSON output...</p>

        <?php
        $files_to_check = [
            'config/database.php',
            'includes/functions.php',
            'includes/core_profile_functions.php',
            'includes/misuki_profile_functions.php',
            'includes/misuki_schedule.php',
            'includes/adaptive_schedule.php',
            'includes/future_events_handler.php',
            'includes/misuki_reality_functions.php',
            'includes/misuki_weekly_schedule.php',
            'api/parse_emotions.php',
            'api/reminder_handler.php',
            'api/split_message_handler.php',
            'api/nickname_handler.php',
            'api/core_memory_handler.php'
        ];

        $issues = 0;

        foreach ($files_to_check as $file) {
            $full_path = __DIR__ . '/' . $file;
            
            if (!file_exists($full_path)) {
                echo "<div class='issue'><strong>$file</strong> - File not found!</div>";
                continue;
            }
            
            $contents = file_get_contents($full_path);
            $has_issue = false;
            
            // Check for BOM
            if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
                echo "<div class='issue'>";
                echo "<strong>$file</strong><br>";
                echo "❌ <strong>BOM detected!</strong> File has invisible UTF-8 BOM character.<br>";
                echo "Fix: Save file as 'UTF-8 without BOM' in your editor";
                echo "</div>";
                $has_issue = true;
                $issues++;
            }
            
            // Check what's at the very start
            $first_10_bytes = substr($contents, 0, 10);
            $trimmed_start = ltrim($contents);
            
            if (!str_starts_with($trimmed_start, '<?php') && !str_starts_with($trimmed_start, '<?')) {
                echo "<div class='issue'>";
                echo "<strong>$file</strong><br>";
                echo "❌ <strong>No PHP opening tag at start!</strong><br>";
                echo "First 10 bytes (hex): <code>" . bin2hex($first_10_bytes) . "</code><br>";
                echo "Fix: File should start with &lt;?php";
                echo "</div>";
                $has_issue = true;
                $issues++;
            } else if ($first_10_bytes !== substr($trimmed_start, 0, 10)) {
                // There's whitespace before <?php
                $whitespace = substr($contents, 0, strpos($contents, '<?'));
                echo "<div class='issue'>";
                echo "<strong>$file</strong><br>";
                echo "⚠️ <strong>Whitespace before opening tag!</strong><br>";
                echo "Bytes: <code>" . bin2hex($whitespace) . "</code><br>";
                echo "Fix: Remove all characters before &lt;?php";
                echo "</div>";
                $has_issue = true;
                $issues++;
            }
            
            // Test if file outputs anything when included
            ob_start();
            try {
                include $full_path;
                $output = ob_get_clean();
                
                if (trim($output) !== '') {
                    echo "<div class='issue'>";
                    echo "<strong>$file</strong><br>";
                    echo "❌ <strong>File outputs content when included!</strong><br>";
                    echo "Output:<br><pre>" . htmlspecialchars(substr($output, 0, 200)) . "</pre>";
                    echo "Fix: Remove echo/print statements or fix error that causes output";
                    echo "</div>";
                    $has_issue = true;
                    $issues++;
                }
            } catch (Throwable $e) {
                ob_end_clean();
                echo "<div class='issue'>";
                echo "<strong>$file</strong><br>";
                echo "❌ <strong>Error when loading:</strong> " . htmlspecialchars($e->getMessage());
                echo "</div>";
                $has_issue = true;
                $issues++;
            }
            
            if (!$has_issue) {
                echo "<div class='clean'>✅ <strong>$file</strong> - Clean!</div>";
            }
        }

        if ($issues === 0) {
            echo "<div class='clean' style='margin-top: 30px; font-size: 18px;'>";
            echo "<h2 style='color: #4caf50; margin: 0;'>🎉 All Files Are Clean!</h2>";
            echo "<p>No whitespace or output issues found.</p>";
            echo "<p>The problem must be something else. Check the Apache error log.</p>";
            echo "</div>";
        } else {
            echo "<div class='issue' style='margin-top: 30px; font-size: 18px;'>";
            echo "<h2 style='color: #f44336; margin: 0;'>Found $issues Issue(s)</h2>";
            echo "<p>Fix the issues above and try again!</p>";
            echo "</div>";
        }
        ?>

        <hr style="margin: 30px 0;">
        
        <h2>Next: Check Apache Error Log</h2>
        <p>Look for recent errors in:</p>
        <ul>
            <li><code>D:\XAMPP\apache\logs\error.log</code></li>
            <li><code>D:\XAMPP\php\logs\php_error_log</code></li>
        </ul>
        <p>Search for today's date and "chat.php" to find the exact error.</p>
    </div>
</body>
</html>