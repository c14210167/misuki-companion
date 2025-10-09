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
    dreamy: 'assets/images/misuki-dreamy.png'
};

// Load chat history on page load
async function loadChatHistory() {
    try {
        const response = await fetch('api/get_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: 1,
                limit: 50
            })
        });

        const data = await response.json();
        
        if (data.success && data.conversations.length > 0) {
            // Clear the default greeting
            chatMessages.innerHTML = '';
            
            // Add all previous conversations
            data.conversations.forEach(conv => {
                addMessageInstant('user', conv.user_message);
                addMessageInstant('misuki', conv.misuki_response);
            });
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    } catch (error) {
        console.error('Error loading chat history:', error);
        // Keep the default greeting if history fails to load
    }
}

// Add message instantly (for loading history)
function addMessageInstant(sender, text) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    messageDiv.innerHTML = `
        <div>
            <div class="message-sender">${sender === 'user' ? 'You' : 'Misuki'}</div>
            <div class="message-bubble">${text}</div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
}

// Animate expression changes
function animateEmotionTimeline(emotion_timeline) {
    if (!emotion_timeline || emotion_timeline.length === 0) return;
    
    let currentIndex = 0;
    let lastEmotionChangeTime = Date.now();
    const MIN_EMOTION_DISPLAY_TIME = 1000; // Minimum 1 second per emotion
    
    function changeExpression() {
        if (currentIndex >= emotion_timeline.length) {
            // End of timeline, set to last emotion
            const lastEmotion = emotion_timeline[emotion_timeline.length - 1].emotion;
            updateMisukiMood(lastEmotion, getEmotionText(lastEmotion));
            return;
        }
        
        const current = emotion_timeline[currentIndex];
        const currentDuration = current.duration * 1000; // Convert to milliseconds
        
        // Only change emotion if it will be displayed for at least 1 second
        if (currentDuration >= MIN_EMOTION_DISPLAY_TIME) {
            updateMisukiMood(current.emotion, getEmotionText(current.emotion));
            lastEmotionChangeTime = Date.now();
        }
        
        currentIndex++;
        
        // Schedule next expression change
        if (currentIndex < emotion_timeline.length) {
            setTimeout(changeExpression, currentDuration);
        } else {
            // Set final emotion after everything is done
            const lastEmotion = emotion_timeline[emotion_timeline.length - 1].emotion;
            updateMisukiMood(lastEmotion, getEmotionText(lastEmotion));
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

// Check for Misuki initiations periodically
async function checkForInitiation() {
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
            // Misuki is initiating!
            setTimeout(() => {
                typingIndicator.classList.add('active');
                
                setTimeout(() => {
                    typingIndicator.classList.remove('active');
                    addMessage('misuki', data.message);
                    updateMisukiMood('gentle', 'Thinking of you');
                    
                    // Play a subtle notification sound if you want
                    // new Audio('assets/sounds/notification.mp3').play();
                }, 2000 + Math.random() * 1000);
            }, 1000);
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
    if (event.key === 'Enter') {
        sendMessage();
    }
}

function addMessage(sender, text, emotion_timeline = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    const bubbleId = 'bubble-' + Date.now();
    messageDiv.innerHTML = `
        <div>
            <div class="message-sender">${sender === 'user' ? 'You' : 'Misuki'}</div>
            <div class="message-bubble" id="${bubbleId}"></div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
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
    
    // Create audio element for beep sound
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3; // Adjust volume (0.0 to 1.0)
    
    function getTypingSpeed(emotion) {
        const speeds = {
            // Fast emotions
            'excited': 30,
            'surprised': 25,
            'amazed': 35,
            'playful': 40,
            'giggling': 35,
            'happy': 45,
            
            // Normal speed
            'neutral': 50,
            'thoughtful': 55,
            'gentle': 50,
            'content': 50,
            
            // Slow emotions
            'sad': 80,
            'nervous': 90,
            'anxious': 85,
            'concerned': 70,
            'pleading': 75,
            'upset': 85,
            
            // Very slow (emphasis)
            'shy': 100,
            'embarrassed': 95,
            'blushing': 90,
            
            // Medium-fast
            'confident': 45,
            'teasing': 40,
            'loving': 55,
            'affectionate': 55,
            'comforting': 60,
            'reassuring': 55,
            
            // Special
            'sleepy': 110,
            'pouty': 60,
            'relieved': 65,
            'dreamy': 75,
            'flustered': 35,
            'confused': 70
        };
        
        return speeds[emotion] || 50;
    }
    
    function shouldPause(char, nextChar) {
        // Pause at punctuation
        if (char === '!' || char === '?') return 400; // Long pause
        if (char === '.' || char === '…') return 300; // Medium pause
        if (char === ',' || char === ';') return 200; // Short pause
        if (char === '-' && nextChar === ' ') return 150; // Dash pause
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
            bubble.classList.remove('tremor'); // Remove tremor at end
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
            charCount += sentenceLength + 1; // +1 for space between sentences
        }
        
        // Only update emotion if it actually changed
        if (currentEmotion !== lastEmotionSet) {
            updateMisukiMood(currentEmotion, getEmotionText(currentEmotion));
            lastEmotionSet = currentEmotion;
        }
        addTremorEffect(currentEmotion);
        
        // Add character
        const char = fullText[characterIndex];
        bubble.textContent += char;
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Play beep sound for letters and numbers only (not punctuation or spaces)
        if (/[a-zA-Z0-9]/.test(char)) {
            // Clone and play to allow rapid successive beeps
            const beep = beepSound.cloneNode();
            beep.volume = 0.3;
            beep.play().catch(e => {
                // Silently fail if audio can't play (user hasn't interacted yet)
            });
        }
        
        characterIndex++;
        
        // Check for pause
        const pauseTime = shouldPause(char, fullText[characterIndex]);
        const typingSpeed = getTypingSpeed(currentEmotion);
        
        setTimeout(typeNextCharacter, typingSpeed + pauseTime);
    }
    
    typeNextCharacter();
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
    if (!message) return;

    const timeOfDay = getTimeOfDay();
    const timeConfused = detectTimeConfusion(message, timeOfDay);

    addMessage('user', message);
    messageInput.value = '';

    typingIndicator.classList.add('active');

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message,
                user_id: 1,
                time_of_day: timeOfDay,
                time_confused: timeConfused
            })
        });

        const data = await response.json();
        
        setTimeout(() => {
            typingIndicator.classList.remove('active');
            addMessage('misuki', data.response, data.emotion_timeline);
            updateMisukiMood(data.mood, data.mood_text);
        }, 1000 + Math.random() * 1500);

    } catch (error) {
        console.error('Error:', error);
        typingIndicator.classList.remove('active');
        addMessage('misuki', "Oh no... I'm having trouble thinking right now. Could you try again? 💭");
        updateMisukiMood('concerned', 'Worried');
    }
}