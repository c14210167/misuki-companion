<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Misuki</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/emotion-animations.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Video Background -->
    <video id="videoBackground" autoplay loop muted playsinline>
        <source src="assets/images/background.mp4" type="video/mp4">
    </video>

    <!-- Rain Effect -->
    <div class="rain" id="rainContainer"></div>

    <!-- Blur Overlay (appears occasionally) -->
    <div class="blur-overlay" id="blurOverlay"></div>

    <!-- Settings Button -->
    <div class="settings-button" onclick="toggleSettings()">⚙️</div>

    <!-- Settings Panel -->
    <div class="settings-panel" id="settingsPanel">
        <h3>☔ SETTINGS ☔</h3>
        
        <div class="settings-option">
            <label>Rain Intensity:</label>
            <div class="rain-intensity">
                <button class="intensity-btn" onclick="setRainIntensity('none')">None</button>
                <button class="intensity-btn" onclick="setRainIntensity('drizzle')">Drizzle</button>
                <button class="intensity-btn active" onclick="setRainIntensity('light')" id="light-btn">Light</button>
                <button class="intensity-btn" onclick="setRainIntensity('moderate')">Moderate</button>
                <button class="intensity-btn" onclick="setRainIntensity('heavy')">Heavy</button>
                <button class="intensity-btn" onclick="setRainIntensity('storm')">Storm</button>
                <button class="intensity-btn" onclick="setRainIntensity('thundering')">Thunder</button>
            </div>
        </div>

        <button class="close-settings" onclick="toggleSettings()">CLOSE</button>
    </div>

    <!-- Sky decorations -->
    <div class="cloud"></div>
    <div class="cloud"></div>
    <div class="cloud"></div>
    <div id="celestialBody" class="sun"></div>

    <div class="chat-container">
        <div class="misuki-display">
            <div class="time-display" id="timeDisplay">00:00</div>
            <div class="heart">♥</div>
            <div class="heart">♥</div>
            <div class="heart">♥</div>
            <img src="assets/images/misuki-neutral.png" alt="Misuki" class="misuki-image" id="misukiImage">
            <div class="misuki-mood" id="misukiMood">● Listening ●</div>
        </div>

        <div class="chat-section">
            <div class="chat-header">
                <h2>✿ MISUKI ✿</h2>
                <p>Your cozy companion ♥</p>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="message misuki">
                    <div>
                        <div class="message-sender">Misuki</div>
                        <div class="message-bubble" id="greetingMessage">
                            Hi there! It's so nice to see you again. How has your day been?
                        </div>
                    </div>
                </div>
            </div>

            <div class="typing-indicator" id="typingIndicator">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <!-- File preview -->
            <div class="file-preview" id="filePreview" style="display: none;">
                <div class="file-preview-content">
                    <span id="filePreviewText">📄 File attached</span>
                    <button onclick="removeFile()">✕</button>
                </div>
            </div>

            <!-- Chat input with textarea -->
            <div class="chat-input">
                <input 
                    type="file" 
                    id="fileInput" 
                    accept=".txt,.pdf,.doc,.docx,.md"
                    style="display: none;"
                    onchange="handleFileSelect(event)"
                >
                <button class="attach-button" onclick="document.getElementById('fileInput').click()" title="Attach file">
                    📎
                </button>
                <textarea 
                    id="messageInput" 
                    placeholder="Type your message..."
                    rows="1"
                    onkeydown="handleKeyPress(event)"
                ></textarea>
                <button onclick="sendMessage()">SEND</button>
            </div>
        </div>
    </div>

    <script src="assets/js/chat.js"></script>
</body>
</html>