const chatMessages = document.getElementById('chatMessages');
const messageInput = document.getElementById('messageInput');
const typingIndicator = document.getElementById('typingIndicator');
const misukiImage = document.getElementById('misukiImage');
const misukiMood = document.getElementById('misukiMood');
const timeDisplay = document.getElementById('timeDisplay');
const celestialBody = document.getElementById('celestialBody');
const greetingMessage = document.getElementById('greetingMessage');
const rainContainer = document.getElementById('rainContainer');
const blurOverlay = document.getElementById('blurOverlay');

const moodImages = {
    // Basic
    neutral: 'assets/images/misuki-neutral.png',
    happy: 'assets/images/misuki-happy.png',
    concerned: 'assets/images/misuki-concerned.png',
    thoughtful: 'assets/images/misuki-thoughtful.png',
    gentle: 'assets/images/misuki-gentle.png',
    
    // Happy range
    excited: 'assets/images/misuki-excited.png',
    blushing: 'assets/images/misuki-blushing.png',
    loving: 'assets/images/misuki-loving.png',
    content: 'assets/images/misuki-content.png',
    
    // Sad/Worried
    sad: 'assets/images/misuki-sad.png',
    anxious: 'assets/images/misuki-anxious.png',
    upset: 'assets/images/misuki-upset.png',
    pleading: 'assets/images/misuki-pleading.png',
    
    // Surprised
    surprised: 'assets/images/misuki-surprised.png',
    confused: 'assets/images/misuki-confused.png',
    flustered: 'assets/images/misuki-flustered.png',
    amazed: 'assets/images/misuki-amazed.png',
    shocked: 'assets/images/misuki-surprised.png',
    
    // Playful
    teasing: 'assets/images/misuki-teasing.png',
    playful: 'assets/images/misuki-playful.png',
    giggling: 'assets/images/misuki-giggling.png',
    confident: 'assets/images/misuki-confident.png',
    
    // Shy
    embarrassed: 'assets/images/misuki-embarrassed.png',
    shy: 'assets/images/misuki-shy.png',
    nervous: 'assets/images/misuki-nervous.png',
    
    // Supportive
    comforting: 'assets/images/misuki-comforting.png',
    affectionate: 'assets/images/misuki-affectionate.png',
    reassuring: 'assets/images/misuki-reassuring.png',
    
    // Special
    sleepy: 'assets/images/misuki-sleepy.png',
    pouty: 'assets/images/misuki-pouty.png',
    relieved: 'assets/images/misuki-relieved.png',
    dreamy: 'assets/images/misuki-dreamy.png',
    curious: 'assets/images/misuki-thoughtful.png'
};

// Track last user message time to prevent immediate initiations
let lastUserMessageTime = Date.now();

// Load chat history on page load
async function loadChatHistory() {
    console.log('🔄 Loading chat history...');
    
    try {
        // Add cache-busting timestamp + random number
        const timestamp = new Date().getTime();
        const random = Math.random();
        
        const response = await fetch(`api/get_history.php?t=${timestamp}&r=${random}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            body: JSON.stringify({
                user_id: 1,
                limit: 200
            }),
            cache: 'no-store'
        });

        const data = await response.json();
        console.log('📦 Received data:', data);
        console.log(`📊 Total in DB: ${data.total_in_db}, Returned: ${data.conversations?.length || 0}`);
        console.log(`🕐 Server time: ${data.server_time}`);
        
        if (data.success && data.conversations.length > 0) {
            console.log(`✅ Loading ${data.conversations.length} messages`);
            
            // Log first and last message timestamps
            const firstMsg = data.conversations[0];
            const lastMsg = data.conversations[data.conversations.length - 1];
            console.log(`📅 First message: ${firstMsg.timestamp}`);
            console.log(`📅 Last message: ${lastMsg.timestamp}`);
            
            // Clear the default greeting
            chatMessages.innerHTML = '';
            
            let lastDate = null;
            
            // Add all previous conversations with date separators
            data.conversations.forEach((conv, index) => {
                const msgDate = new Date(conv.timestamp);
                const dateStr = msgDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                if (index < 3 || index >= data.conversations.length - 3) {
                    // Log first 3 and last 3 messages
                    console.log(`Message ${index + 1}/${data.conversations.length}: ${dateStr} ${msgDate.toLocaleTimeString()} - "${conv.user_message.substring(0, 50)}..."`);
                }
                
                // Add date separator if day changed
                if (dateStr !== lastDate) {
                    addDateSeparator(dateStr);
                    lastDate = dateStr;
                    lastMessageDate = dateStr; // Update global tracker
                }
                
                addMessageInstant('user', conv.user_message, conv.timestamp);
                addMessageInstant('misuki', conv.misuki_response, conv.timestamp);
            });
            
            // Scroll to bottom - force it multiple times to ensure it works
            setTimeout(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
                console.log('📜 Scrolled to bottom');
            }, 100);
            
            setTimeout(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
                console.log('📜 Scroll check 2');
            }, 300);
            
            console.log('✅ Chat history loaded successfully');
            console.log(`🎯 Last date displayed: ${lastMessageDate}`);
        } else {
            console.log('ℹ️ No chat history found or empty');
        }
    } catch (error) {
        console.error('❌ Error loading chat history:', error);
        // Keep the default greeting if history fails to load
    }
}

// Add date separator
function addDateSeparator(dateStr) {
    const separator = document.createElement('div');
    separator.className = 'date-separator';
    separator.innerHTML = `<span>${dateStr}</span>`;
    chatMessages.appendChild(separator);
}

// Add message instantly (for loading history)
function addMessageInstant(sender, text, timestamp) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    const time = new Date(timestamp);
    const timeStr = time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    messageDiv.innerHTML = `
        <div>
            <div class="message-sender">${sender === 'user' ? 'You' : 'Misuki'}</div>
            <div class="message-bubble">${text}</div>
            <div class="message-time">${timeStr}</div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
}

// Helper function for word count
function str_word_count(str) {
    return str.split(/\s+/).filter(word => word.length > 0).length;
}

// Animate expression changes - IMPROVED VERSION
function animateEmotionTimeline(emotion_timeline) {
    if (!emotion_timeline || emotion_timeline.length === 0) return;
    
    const MIN_EMOTION_DISPLAY_TIME = 3000; // Increased to 3 seconds
    
    // STEP 1: Merge consecutive identical emotions
    const merged = [];
    let current = null;
    
    for (let i = 0; i < emotion_timeline.length; i++) {
        if (!current) {
            current = { ...emotion_timeline[i] };
        } else if (current.emotion === emotion_timeline[i].emotion) {
            // Same emotion, merge duration
            current.duration += emotion_timeline[i].duration;
            current.sentence += ' ' + emotion_timeline[i].sentence;
        } else {
            // Different emotion, save current and start new
            merged.push(current);
            current = { ...emotion_timeline[i] };
        }
    }
    if (current) merged.push(current);
    
    console.log(`🔗 Merged ${emotion_timeline.length} → ${merged.length} emotions (removed duplicates)`);
    
    // STEP 2: Filter out very short emotions and redistribute time
    const filtered = [];
    
    for (let i = 0; i < merged.length; i++) {
        const durationMs = merged[i].duration * 1000;
        
        if (durationMs >= MIN_EMOTION_DISPLAY_TIME) {
            // Long enough, keep it
            filtered.push(merged[i]);
        } else {
            // Too short, distribute time to neighbors
            const half_time = merged[i].duration / 2;
            
            // Add to previous if exists
            if (filtered.length > 0) {
                filtered[filtered.length - 1].duration += half_time;
            }
            
            // Add to next if exists
            if (i + 1 < merged.length) {
                merged[i + 1].duration += half_time;
            } else if (filtered.length > 0) {
                // Last emotion is short, give all time to previous
                filtered[filtered.length - 1].duration += merged[i].duration;
            }
            
            console.log(`⏭️ Skipped short emotion: ${merged[i].emotion} (${durationMs}ms)`);
        }
    }
    
    // STEP 3: If message is very short (< 10 words), use SINGLE dominant emotion
    const total_words = emotion_timeline.reduce((sum, e) => sum + str_word_count(e.sentence), 0);
    
    if (total_words < 10 && filtered.length > 1) {
        // Pick the most prominent emotion (longest duration or most intense)
        const emotion_priority = {
            'shocked': 10, 'surprised': 9, 'excited': 9,
            'sad': 8, 'upset': 8, 'concerned': 8,
            'loving': 7, 'affectionate': 7,
            'happy': 6, 'playful': 6,
            'thoughtful': 5, 'curious': 5,
            'gentle': 3, 'neutral': 1
        };
        
        let best = filtered[0];
        let best_score = (filtered[0].duration * 1000) + (emotion_priority[filtered[0].emotion] || 0) * 100;
        
        for (let i = 1; i < filtered.length; i++) {
            const score = (filtered[i].duration * 1000) + (emotion_priority[filtered[i].emotion] || 0) * 100;
            if (score > best_score) {
                best = filtered[i];
                best_score = score;
            }
        }
        
        console.log(`🎯 Short message detected (${total_words} words) - using single emotion: ${best.emotion}`);
        updateMisukiMood(best.emotion, getEmotionText(best.emotion));
        return; // Don't animate, just set once
    }
    
    // STEP 4: If everything was filtered out, use last emotion from original
    if (filtered.length === 0) {
        const lastEmotion = emotion_timeline[emotion_timeline.length - 1].emotion;
        updateMisukiMood(lastEmotion, getEmotionText(lastEmotion));
        console.log(`⚠️ All emotions filtered - using last: ${lastEmotion}`);
        return;
    }
    
    console.log(`✅ Final timeline: ${filtered.length} emotions (${emotion_timeline.length} → ${merged.length} → ${filtered.length})`);
    
    // STEP 5: Animate the filtered emotions
    let currentIndex = 0;
    
    function changeExpression() {
        if (currentIndex >= filtered.length) {
            return;
        }
        
        const current = filtered[currentIndex];
        const currentDuration = current.duration * 1000;
        
        updateMisukiMood(current.emotion, getEmotionText(current.emotion));
        console.log(`😊 [${currentIndex + 1}/${filtered.length}] ${current.emotion} for ${(currentDuration/1000).toFixed(1)}s`);
        
        currentIndex++;
        
        // Schedule next expression change
        if (currentIndex < filtered.length) {
            setTimeout(changeExpression, currentDuration);
        }
    }
    
    // Start the animation
    changeExpression();
}

function getEmotionText(emotion) {
    const emotionTexts = {
        neutral: 'Listening',
        happy: 'Happy',
        excited: 'Excited!',
        blushing: 'Blushing',
        loving: 'Loving',
        content: 'Content',
        
        sad: 'Sad',
        concerned: 'Concerned',
        anxious: 'Anxious',
        upset: 'Upset',
        pleading: 'Apologetic',
        
        surprised: 'Surprised!',
        shocked: 'Shocked!',
        confused: 'Confused',
        flustered: 'Flustered',
        amazed: 'Amazed!',
        
        teasing: 'Teasing~',
        playful: 'Playful',
        giggling: 'Giggling',
        confident: 'Confident',
        
        embarrassed: 'Embarrassed',
        shy: 'Shy',
        nervous: 'Nervous',
        
        comforting: 'Comforting',
        affectionate: 'Affectionate',
        reassuring: 'Reassuring',
        gentle: 'Gentle',
        
        thoughtful: 'Thinking',
        curious: 'Curious',
        sleepy: 'Sleepy',
        pouty: 'Pouty',
        relieved: 'Relieved',
        dreamy: 'Dreaming'
    };
    
    return emotionTexts[emotion] || 'Listening';
}

// Rain intensity settings
const rainIntensities = {
    none: { count: 0, speed: [0.5, 1], width: 2, height: 15 },
    drizzle: { count: 30, speed: [0.8, 1.2], width: 1, height: 10 },
    light: { count: 100, speed: [0.5, 1], width: 2, height: 15 },
    moderate: { count: 200, speed: [0.4, 0.8], width: 2, height: 20 },
    heavy: { count: 350, speed: [0.3, 0.6], width: 3, height: 25 },
    storm: { count: 500, speed: [0.2, 0.4], width: 3, height: 30 },
    thundering: { count: 700, speed: [0.15, 0.3], width: 4, height: 35 }
};

let currentIntensity = 'light';

// Settings panel toggle
function toggleSettings() {
    const panel = document.getElementById('settingsPanel');
    panel.classList.toggle('active');
}

// Set rain intensity
function setRainIntensity(intensity) {
    currentIntensity = intensity;
    
    // Update active button
    document.querySelectorAll('.intensity-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Recreate rain
    createRain();
}

// Create rain effect with adjustable intensity
function createRain() {
    // Clear existing rain
    rainContainer.innerHTML = '';
    
    const settings = rainIntensities[currentIntensity];
    
    for (let i = 0; i < settings.count; i++) {
        const drop = document.createElement('div');
        drop.className = 'raindrop';
        drop.style.left = Math.random() * 100 + '%';
        drop.style.width = settings.width + 'px';
        drop.style.height = settings.height + 'px';
        drop.style.animationDuration = (Math.random() * (settings.speed[1] - settings.speed[0]) + settings.speed[0]) + 's';
        drop.style.animationDelay = Math.random() * 2 + 's';
        
        // For heavy rain, add some opacity variation
        if (currentIntensity === 'heavy' || currentIntensity === 'storm' || currentIntensity === 'thundering') {
            drop.style.opacity = 0.4 + Math.random() * 0.4;
        }
        
        rainContainer.appendChild(drop);
    }
    
    // Add thunder effect for thundering mode
    if (currentIntensity === 'thundering') {
        createThunderEffect();
    }
}

// Thunder effect for maximum intensity
function createThunderEffect() {
    const thunder = () => {
        // Flash effect
        blurOverlay.style.background = 'rgba(255, 255, 255, 0.3)';
        blurOverlay.classList.add('active');
        
        setTimeout(() => {
            blurOverlay.style.background = '';
            blurOverlay.classList.remove('active');
        }, 100);
        
        // Random thunder every 5-15 seconds
        setTimeout(thunder, 5000 + Math.random() * 10000);
    };
    
    setTimeout(thunder, 3000);
}

// Occasionally activate blur effect
function randomBlur() {
    const activate = () => {
        blurOverlay.classList.add('active');
        setTimeout(() => {
            blurOverlay.classList.remove('active');
        }, 3000 + Math.random() * 2000);
    };
    
    setInterval(() => {
        if (Math.random() > 0.5) {
            activate();
        }
    }, 15000 + Math.random() * 15000);
}

// Initialize
createRain();
randomBlur();
loadChatHistory(); // Load previous conversations on page load
updateLiveStatus(); // Load Misuki's current status
setInterval(updateLiveStatus, 60000); // Update every minute

// Function to update live status
async function updateLiveStatus() {
    try {
        const response = await fetch('api/get_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: 1
            })
        });

        const data = await response.json();
        
        if (data.success && data.status) {
            const statusIndicator = document.querySelector('.status-indicator');
            const statusText = document.querySelector('.status-text');
            
            // Update text with emoji
            statusText.textContent = `${data.status.emoji} ${data.status.text}`;
            
            // Update status attribute for color
            statusIndicator.setAttribute('data-status', data.status.status);
            
            // Add tooltip with detail
            statusIndicator.title = data.status.detail;
        }
    } catch (error) {
        console.error('Error fetching status:', error);
    }
}

// Check for Misuki initiations periodically (including dreams!)
async function checkForInitiation() {
    // CRITICAL FIX: Don't check if user just sent a message (within last 3 minutes)
    const timeSinceLastMessage = Date.now() - lastUserMessageTime;
    if (timeSinceLastMessage < 180000) { // 3 minutes in milliseconds
        console.log('⏸️ Skipping initiation check - user just messaged', Math.floor(timeSinceLastMessage / 1000), 'seconds ago');
        return;
    }
    
    try {
        const response = await fetch('api/check_initiate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: 1
            })
        });

        const data = await response.json();
        
        if (data.should_initiate && data.message) {
            console.log('💕 Misuki is initiating:', data.reason);
            
            // Misuki is initiating!
            setTimeout(() => {
                typingIndicator.classList.add('active');
                
                setTimeout(() => {
                    typingIndicator.classList.remove('active');
                    
                    // Determine mood based on whether it's a dream or regular message
                    const mood = data.is_dream ? 'dreamy' : 'gentle';
                    const moodText = data.is_dream ? 'Dreaming' : 'Thinking of you';
                    
                    addMessage('misuki', data.message);
                    updateMisukiMood(mood, moodText);
                    
                    // Play notification sound
                    const notificationSound = new Audio('assets/audio/notification.m4a');
                    notificationSound.volume = 0.5;
                    notificationSound.play().catch(e => {
                        console.log('Could not play notification sound:', e);
                    });
                    
                }, 2000 + Math.random() * 1000);
            }, 1000);
        } else {
            console.log('✅ No initiation needed');
        }
    } catch (error) {
        console.error('Error checking initiation:', error);
    }
}

// Check every 15 minutes for Misuki initiations
setInterval(checkForInitiation, 15 * 60 * 1000);

// Also check on page load after 30 seconds
setTimeout(checkForInitiation, 30000);

// Time management
function updateTimeDisplay() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    timeDisplay.textContent = `${hours}:${minutes}`;
    
    const hour = now.getHours();
    if (hour >= 6 && hour < 18) {
        celestialBody.className = 'sun';
    } else {
        celestialBody.className = 'moon';
    }
}

function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'morning';
    if (hour >= 12 && hour < 17) return 'afternoon';
    if (hour >= 17 && hour < 21) return 'evening';
    return 'night';
}

function getTimeBasedGreeting() {
    const timeOfDay = getTimeOfDay();
    const greetings = {
        morning: "Good morning! ☀️ I hope you slept well. How are you feeling today?",
        afternoon: "Good afternoon! 🌤️ How has your day been treating you so far?",
        evening: "Good evening! 🌆 I hope you had a nice day. Want to talk about it?",
        night: "It's quite late... 🌙 Are you having trouble sleeping, or just enjoying the quiet night?"
    };
    return greetings[timeOfDay];
}

greetingMessage.textContent = getTimeBasedGreeting();
updateTimeDisplay();
setInterval(updateTimeDisplay, 60000);

// Sparkle effects
function createSparkle(x, y) {
    const sparkle = document.createElement('div');
    sparkle.className = 'sparkle';
    sparkle.textContent = '✨';
    sparkle.style.left = x + 'px';
    sparkle.style.top = y + 'px';
    document.body.appendChild(sparkle);
    setTimeout(() => sparkle.remove(), 2000);
}

// Random sparkles
setInterval(() => {
    const x = Math.random() * window.innerWidth;
    const y = Math.random() * window.innerHeight;
    if (Math.random() > 0.7) createSparkle(x, y);
}, 3000);

// Message handling with Undertale-style typing
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault(); // Prevent newline
        sendMessage();
    }
    // Shift+Enter will allow newline
}

// Auto-resize textarea as user types
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

let lastMessageDate = null; // Track last message date for separators
let attachedFile = null; // Track attached file
let userIsTyping = false; // Track if user is currently typing
let followUpTimeout = null; // Track follow-up timeout

// Track when user is typing
messageInput.addEventListener('input', () => {
    userIsTyping = messageInput.value.trim().length > 0;
});

messageInput.addEventListener('focus', () => {
    userIsTyping = true;
});

messageInput.addEventListener('blur', () => {
    setTimeout(() => {
        userIsTyping = messageInput.value.trim().length > 0;
    }, 100);
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const filePreview = document.getElementById('filePreview');
    const filePreviewText = document.getElementById('filePreviewText');
    
    // Show preview
    filePreviewText.textContent = `📄 ${file.name}`;
    filePreview.style.display = 'block';
    
    // Upload file
    const formData = new FormData();
    formData.append('file', file);
    formData.append('user_id', 1);
    
    fetch('api/upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            attachedFile = {
                filename: data.filename,
                content: data.content,
                word_count: data.word_count,
                truncated: data.truncated
            };
            console.log('✅ File uploaded:', data);
            
            if (data.truncated) {
                filePreviewText.textContent = `📄 ${file.name} (first 50k chars)`;
            }
        } else {
            alert('Error uploading file: ' + data.error);
            removeFile();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        alert('Failed to upload file');
        removeFile();
    });
}

function removeFile() {
    attachedFile = null;
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('fileInput').value = '';
}

function addMessage(sender, text, emotion_timeline = null) {
    // Check if we need a date separator
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    if (dateStr !== lastMessageDate) {
        addDateSeparator(dateStr);
        lastMessageDate = dateStr;
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    // Add initial animation class for slide-in with bounce
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = sender === 'user' ? 'translateX(50px)' : 'translateX(-50px)';
    
    const bubbleId = 'bubble-' + Date.now();
    const timeStr = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    messageDiv.innerHTML = `
        <div>
            <div class="message-sender">${sender === 'user' ? 'You' : 'Misuki'}</div>
            <div class="message-bubble" id="${bubbleId}"></div>
            <div class="message-time">${timeStr}</div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Trigger bounce animation
    setTimeout(() => {
        messageDiv.style.transition = 'all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateX(0)';
    }, 10);
    
    if (sender === 'misuki' && emotion_timeline) {
        // Undertale-style typing with emotion-based speed
        typeMessageWithEmotions(bubbleId, text, emotion_timeline);
    } else if (sender === 'misuki') {
        // Regular typing for fallback
        typeMessage(bubbleId, text, 'neutral');
    } else {
        // User messages appear instantly
        document.getElementById(bubbleId).textContent = text;
    }
    
    if (sender === 'misuki') {
        const rect = messageDiv.getBoundingClientRect();
        createSparkle(rect.left + 50, rect.top + 20);
    }
}

function typeMessageWithEmotions(bubbleId, fullText, emotion_timeline) {
    const bubble = document.getElementById(bubbleId);
    if (!bubble) return;
    
    let currentEmotionIndex = 0;
    let characterIndex = 0;
    let currentSentenceStart = 0;
    let lastEmotionSet = ''; // Track last emotion to avoid redundant updates
    let isBackspacing = false;
    let backspaceChance = 0.15; // 15% chance to backspace when thinking/nervous
    
    // Create audio element for beep sound
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3;
    
    function getTypingSpeed(emotion) {
        const speeds = {
            // Super fast emotions (EXCITED!)
            'excited': 20,
            'shocked': 15,
            'amazed': 25,
            
            // Fast emotions
            'surprised': 30,
            'playful': 35,
            'giggling': 30,
            'happy': 40,
            'flustered': 25,
            
            // Normal speed
            'neutral': 50,
            'content': 50,
            'gentle': 55,
            'curious': 50,
            
            // Medium-slow (thoughtful, careful)
            'thoughtful': 70,
            'confused': 75,
            'concerned': 65,
            'comforting': 60,
            'reassuring': 60,
            'affectionate': 65,
            'loving': 70,
            
            // Slow emotions (sad, careful)
            'sad': 90,
            'upset': 85,
            'pleading': 80,
            'anxious': 95,
            
            // Very slow (nervous, shy - might backspace)
            'nervous': 110,
            'shy': 120,
            'embarrassed': 115,
            'blushing': 100,
            
            // Special
            'confident': 45,
            'teasing': 40,
            'sleepy': 130,
            'pouty': 70,
            'relieved': 65,
            'dreamy': 85
        };
        
        return speeds[emotion] || 50;
    }
    
    function shouldBackspace(emotion) {
        // Emotions that might cause backspacing (she's being careful)
        const backspaceEmotions = ['nervous', 'shy', 'embarrassed', 'thoughtful', 'concerned', 'anxious'];
        return backspaceEmotions.includes(emotion) && Math.random() < backspaceChance && characterIndex > 5;
    }
    
    function applyEmotionAnimation(emotion) {
        const bubble = document.getElementById(bubbleId);
        if (!bubble) return;
        
        // Remove all emotion classes first
        bubble.className = bubble.className.split(' ').filter(c => !c.startsWith('emotion-')).join(' ');
        
        // Add emotion-specific animation class
        bubble.classList.add(`emotion-${emotion}`);
        
        // Screen effects for intense emotions
        if (emotion === 'shocked' || emotion === 'surprised' || emotion === 'excited') {
            triggerScreenShake();
        }
        
        if (emotion === 'surprised' || emotion === 'shocked' || emotion === 'amazed') {
            triggerFlash();
        }
        
        if (emotion === 'sad' || emotion === 'upset' || emotion === 'crying') {
            triggerBlur();
        }
    }
    
    function shouldPause(char, nextChar) {
        // Pause at punctuation
        if (char === '!' || char === '?') return 400;
        if (char === '.' || char === '…') return 300;
        if (char === ',' || char === ';') return 200;
        if (char === '-' && nextChar === ' ') return 150;
        return 0;
    }
    
    function addTremorEffect(emotion) {
        const tremorEmotions = ['nervous', 'anxious', 'scared', 'shy', 'embarrassed', 'flustered'];
        if (tremorEmotions.includes(emotion)) {
            bubble.classList.add('tremor');
        } else {
            bubble.classList.remove('tremor');
        }
    }
    
    function typeNextCharacter() {
        if (characterIndex >= fullText.length) {
            bubble.classList.remove('tremor');
            // Remove emotion animation classes when done
            bubble.className = bubble.className.split(' ').filter(c => !c.startsWith('emotion-')).join(' ');
            return;
        }
        
        // Find current emotion based on character position
        let currentEmotion = 'neutral';
        let charCount = 0;
        
        for (let i = 0; i < emotion_timeline.length; i++) {
            const sentenceLength = emotion_timeline[i].sentence.length;
            if (characterIndex >= charCount && characterIndex < charCount + sentenceLength) {
                currentEmotion = emotion_timeline[i].emotion;
                break;
            }
            charCount += sentenceLength + 1;
        }
        
        // Only update emotion if it actually changed
        if (currentEmotion !== lastEmotionSet) {
            updateMisukiMood(currentEmotion, getEmotionText(currentEmotion));
            applyEmotionAnimation(currentEmotion);
            lastEmotionSet = currentEmotion;
        }
        addTremorEffect(currentEmotion);
        
        // Check for backspace (thinking/nervous emotions)
        if (!isBackspacing && shouldBackspace(currentEmotion) && characterIndex > 2) {
            isBackspacing = true;
            const backspaceCount = Math.floor(Math.random() * 3) + 2; // Backspace 2-4 characters
            
            function doBackspace(count) {
                if (count <= 0) {
                    isBackspacing = false;
                    setTimeout(typeNextCharacter, getTypingSpeed(currentEmotion));
                    return;
                }
                
                bubble.textContent = bubble.textContent.slice(0, -1);
                characterIndex--;
                setTimeout(() => doBackspace(count - 1), 50); // Fast backspace
            }
            
            doBackspace(backspaceCount);
            return;
        }
        
        // Add character
        const char = fullText[characterIndex];
        bubble.textContent += char;
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Play beep sound for letters and numbers only
        if (/[a-zA-Z0-9]/.test(char)) {
            const beep = beepSound.cloneNode();
            beep.volume = 0.3;
            beep.play().catch(e => {});
        }
        
        characterIndex++;
        
        // Check for pause
        const pauseTime = shouldPause(char, fullText[characterIndex]);
        const typingSpeed = getTypingSpeed(currentEmotion);
        
        setTimeout(typeNextCharacter, typingSpeed + pauseTime);
    }
    
    typeNextCharacter();
}

// Screen effect functions
function triggerScreenShake() {
    const container = document.querySelector('.chat-container');
    container.classList.add('screen-shake');
    setTimeout(() => container.classList.remove('screen-shake'), 500);
}

function triggerFlash() {
    const flash = document.createElement('div');
    flash.className = 'screen-flash';
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 200);
}

function triggerBlur() {
    const container = document.querySelector('.chat-container');
    container.classList.add('screen-blur');
    setTimeout(() => container.classList.remove('screen-blur'), 2000);
}

function typeMessage(bubbleId, text, emotion = 'neutral') {
    // Fallback typing without emotion timeline
    const bubble = document.getElementById(bubbleId);
    if (!bubble) return;
    
    let index = 0;
    const speed = 50;
    
    // Create audio element for beep sound
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3;
    
    function type() {
        if (index < text.length) {
            const char = text[index];
            bubble.textContent += char;
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Play beep for letters and numbers
            if (/[a-zA-Z0-9]/.test(char)) {
                const beep = beepSound.cloneNode();
                beep.volume = 0.3;
                beep.play().catch(e => {});
            }
            
            index++;
            
            const pauseTime = text[index - 1] === '.' || text[index - 1] === '!' || text[index - 1] === '?' ? 300 : 0;
            setTimeout(type, speed + pauseTime);
        }
    }
    
    type();
}

function updateMisukiMood(mood, moodText) {
    if (moodImages[mood]) {
        // Instant change, no fade
        misukiImage.src = moodImages[mood];
    }
    misukiMood.textContent = `● ${moodText} ●`;
}

// IMPROVED: Better time confusion detection
function detectTimeConfusion(message, timeOfDay) {
    const messageLower = message.toLowerCase();
    
    // Patterns that indicate CURRENT greeting (not future reference)
    const currentGreetingPatterns = [
        /^good morning/i,
        /^morning[!.]/i,
        /^good night[!.]/i,
        /^goodnight[!.]/i,
        /^good evening/i,
        /^evening[!.]/i,
        /^good afternoon/i
    ];
    
    // Check if this is actually a current greeting
    let isCurrentGreeting = false;
    for (const pattern of currentGreetingPatterns) {
        if (pattern.test(message.trim())) {
            isCurrentGreeting = true;
            break;
        }
    }
    
    // If not a current greeting, don't flag time confusion
    if (!isCurrentGreeting) {
        return false;
    }
    
    // Now check for time mismatch
    if (timeOfDay === 'night' && /^good morning|^morning/i.test(message.trim())) {
        return 'morning_at_night';
    } else if (timeOfDay === 'morning' && /^good night|^goodnight/i.test(message.trim())) {
        return 'night_at_morning';
    } else if (timeOfDay === 'afternoon' && /^good morning/i.test(message.trim())) {
        return 'morning_at_afternoon';
    }
    
    return false;
}

async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message && !attachedFile) return;

    // CRITICAL: Update last user message time to prevent immediate initiations
    lastUserMessageTime = Date.now();
    console.log('📤 User sent message at', new Date(lastUserMessageTime).toLocaleTimeString());

    const timeOfDay = getTimeOfDay();
    const timeConfused = detectTimeConfusion(message, timeOfDay);
    
    // Build message text
    let fullMessage = message;
    if (attachedFile) {
        fullMessage = `[File attached: ${attachedFile.filename}]\n\n${message || 'Here\'s the file!'}`;
    }

    addMessage('user', fullMessage);
    messageInput.value = '';
    messageInput.style.height = 'auto'; // Reset height after sending
    
    // Remove file preview after sending
    if (attachedFile) {
        removeFile();
    }

    typingIndicator.classList.add('active');

    try {
        const requestBody = {
            message: message || 'Here\'s the file!',
            user_id: 1,
            time_of_day: timeOfDay,
            time_confused: timeConfused
        };
        
        // Add file content if attached
        if (attachedFile) {
            requestBody.file_content = attachedFile.content;
            requestBody.filename = attachedFile.filename;
            requestBody.word_count = attachedFile.word_count;
        }
        
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json();
        
        setTimeout(() => {
            typingIndicator.classList.remove('active');
            addMessage('misuki', data.response, data.emotion_timeline);
            updateMisukiMood(data.mood, data.mood_text);
            
            // Check if she wants to send follow-up messages
            if (data.should_follow_up) {
                scheduleFollowUp(0); // Start with count 0
            }
        }, 1000 + Math.random() * 1500);

    } catch (error) {
        console.error('Error:', error);
        typingIndicator.classList.remove('active');
        addMessage('misuki', "Oh no... I'm having trouble thinking right now. Could you try again? 💭");
        updateMisukiMood('concerned', 'Worried');
    }
}

async function scheduleFollowUp(followUpCount) {
    // Random delay between 2-5 seconds (like natural texting)
    const delay = 2000 + Math.random() * 3000;
    
    followUpTimeout = setTimeout(async () => {
        // Check if user started typing - if so, cancel follow-up
        if (userIsTyping) {
            console.log('User is typing, canceling follow-up');
            return;
        }
        
        // Show typing indicator
        typingIndicator.classList.add('active');
        
        // Wait a bit (she's "typing")
        setTimeout(async () => {
            try {
                const response = await fetch('api/get_follow_up.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: 1,
                        follow_up_count: followUpCount
                    })
                });
                
                const data = await response.json();
                
                typingIndicator.classList.remove('active');
                
                if (data.success) {
                    // Check again if user started typing
                    if (userIsTyping) {
                        console.log('User started typing, not sending follow-up');
                        return;
                    }
                    
                    addMessage('misuki', data.message, data.emotion_timeline);
                    updateMisukiMood(data.mood, data.mood_text);
                    
                    // Check if she wants to send ANOTHER follow-up
                    if (data.should_continue && followUpCount < 2) {
                        scheduleFollowUp(followUpCount + 1);
                    }
                }
            } catch (error) {
                console.error('Follow-up error:', error);
                typingIndicator.classList.remove('active');
            }
        }, 1000 + Math.random() * 1000); // Typing time
        
    }, delay);
}