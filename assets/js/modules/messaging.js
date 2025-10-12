// =========================================
// MESSAGING MODULE
// Handles sending messages, typing, and display
// =========================================

import { 
    chatMessages, 
    messageInput, 
    typingIndicator
} from '../chat.js';
import { updateMisukiMood } from './emotions.js';
import { typeMessageWithEmotions } from './typing.js';
import { createSparkle } from './effects.js';

// Initialize messaging system
export function initializeMessaging() {
    // Handle Enter key
    messageInput.addEventListener('keydown', handleKeyPress);
    
    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Track typing
    messageInput.addEventListener('input', () => {
        window.userIsTyping = messageInput.value.trim().length > 0;
    });
    
    messageInput.addEventListener('focus', () => {
        window.userIsTyping = true;
    });
    
    messageInput.addEventListener('blur', () => {
        setTimeout(() => {
            window.userIsTyping = messageInput.value.trim().length > 0;
        }, 100);
    });
}

// Handle Enter key press
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Get time of day
function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'morning';
    if (hour >= 12 && hour < 17) return 'afternoon';
    if (hour >= 17 && hour < 21) return 'evening';
    return 'night';
}

// Detect time confusion
function detectTimeConfusion(message, timeOfDay) {
    const messageLower = message.toLowerCase();
    
    const currentGreetingPatterns = [
        /^good morning/i,
        /^morning[!.]/i,
        /^good night[!.]/i,
        /^goodnight[!.]/i,
        /^good evening/i,
        /^evening[!.]/i,
        /^good afternoon/i
    ];
    
    let isCurrentGreeting = false;
    for (const pattern of currentGreetingPatterns) {
        if (pattern.test(message.trim())) {
            isCurrentGreeting = true;
            break;
        }
    }
    
    if (!isCurrentGreeting) return false;
    
    if (timeOfDay === 'night' && /^good morning|^morning/i.test(message.trim())) {
        return 'morning_at_night';
    } else if (timeOfDay === 'morning' && /^good night|^goodnight/i.test(message.trim())) {
        return 'night_at_morning';
    } else if (timeOfDay === 'afternoon' && /^good morning/i.test(message.trim())) {
        return 'morning_at_afternoon';
    }
    
    return false;
}

// Calculate typing duration for a message
function calculateTypingDuration(text, emotionTimeline) {
    // Base calculation: character count × average typing speed
    const charCount = text.length;
    const avgSpeed = 50; // Average ms per character
    
    // Add pause times for punctuation
    const punctuationPauses = (text.match(/[.!?]/g) || []).length * 400;
    const commaPauses = (text.match(/[,;]/g) || []).length * 200;
    
    // Total duration
    const baseDuration = (charCount * avgSpeed) + punctuationPauses + commaPauses;
    
    // Add a small buffer for safety
    return baseDuration + 500;
}

// Send message
export async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message && !window.attachedFile) return;

    // Update last user message time
    window.lastUserMessageTime = Date.now();
    console.log('📤 User sent message at', new Date(window.lastUserMessageTime).toLocaleTimeString());

    const timeOfDay = getTimeOfDay();
    const timeConfused = detectTimeConfusion(message, timeOfDay);
    
    let fullMessage = message;
    if (window.attachedFile) {
        fullMessage = `[File attached: ${window.attachedFile.filename}]\n\n${message || 'Here\'s the file!'}`;
    }

    addMessage('user', fullMessage);
    messageInput.value = '';
    messageInput.style.height = 'auto';
    
    if (window.attachedFile) {
        window.removeFile();
    }

    typingIndicator.classList.add('active');

    try {
        const requestBody = {
            message: message || 'Here\'s the file!',
            user_id: 1,
            time_of_day: timeOfDay,
            time_confused: timeConfused
        };
        
        if (window.attachedFile) {
            requestBody.file_content = window.attachedFile.content;
            requestBody.filename = window.attachedFile.filename;
            requestBody.word_count = window.attachedFile.word_count;
        }
        
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json();
        
        // Check if message is split
        if (data.is_split && data.additional_messages) {
            // She's sending multiple messages!
            handleSplitMessages(data);
        } else {
            // Normal single message
            setTimeout(() => {
                typingIndicator.classList.remove('active');
                addMessage('misuki', data.response, data.emotion_timeline);
                updateMisukiMood(data.mood, data.mood_text);
                
                if (data.should_follow_up) {
                    scheduleFollowUp(0);
                }
            }, 1000 + Math.random() * 1500);
        }

    } catch (error) {
        console.error('Error:', error);
        typingIndicator.classList.remove('active');
        addMessage('misuki', "Oh no... I'm having trouble thinking right now. Could you try again? 💭");
        updateMisukiMood('concerned', 'Worried');
    }
}

// Handle split messages with realistic timing
function handleSplitMessages(data) {
    const allMessages = [data.response, ...data.additional_messages];
    const emotionTimelines = data.emotion_timelines || [];
    
    let currentIndex = 0;
    
    function sendNextPart() {
        if (currentIndex >= allMessages.length) {
            // All messages sent
            typingIndicator.classList.remove('active');
            
            // Check for follow-up after all messages are done
            if (data.should_follow_up) {
                scheduleFollowUp(0);
            }
            return;
        }
        
        const currentMessage = allMessages[currentIndex];
        const currentEmotions = emotionTimelines[currentIndex] || null;
        
        // Show typing indicator
        typingIndicator.classList.add('active');
        
        // Calculate how long the typing indicator should show
        const wordCount = currentMessage.split(' ').length;
        const typingIndicatorDelay = 800 + (wordCount * 150) + (Math.random() * 500);
        
        setTimeout(() => {
            // Hide typing indicator and add message
            typingIndicator.classList.remove('active');
            addMessage('misuki', currentMessage, currentEmotions);
            
            // Update mood based on first message
            if (currentIndex === 0) {
                updateMisukiMood(data.mood, data.mood_text);
            }
            
            // Calculate how long THIS message will take to type out
            const messageTypingDuration = calculateTypingDuration(currentMessage, currentEmotions);
            
            currentIndex++;
            
            // Schedule next message AFTER this one finishes typing
            if (currentIndex < allMessages.length) {
                // Wait for current message to finish typing + a brief natural pause
                const pauseBeforeNext = messageTypingDuration + 600 + (Math.random() * 400);
                setTimeout(sendNextPart, pauseBeforeNext);
            }
        }, typingIndicatorDelay);
    }
    
    // Start sending messages
    sendNextPart();
}

// Add date separator
export function addDateSeparator(dateStr) {
    const separator = document.createElement('div');
    separator.className = 'date-separator';
    separator.innerHTML = `<span>${dateStr}</span>`;
    chatMessages.appendChild(separator);
}

// Add message (animated)
export function addMessage(sender, text, emotion_timeline = null) {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    if (dateStr !== window.lastMessageDate) {
        addDateSeparator(dateStr);
        window.lastMessageDate = dateStr;
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = sender === 'user' ? 'translateX(50px)' : 'translateX(-50px)';
    
    const bubbleId = 'bubble-' + Date.now() + '-' + Math.random();
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
    
    setTimeout(() => {
        messageDiv.style.transition = 'all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateX(0)';
    }, 10);
    
    if (sender === 'misuki' && emotion_timeline) {
        typeMessageWithEmotions(bubbleId, text, emotion_timeline);
    } else if (sender === 'misuki') {
        document.getElementById(bubbleId).textContent = text;
    } else {
        document.getElementById(bubbleId).textContent = text;
    }
    
    if (sender === 'misuki') {
        const rect = messageDiv.getBoundingClientRect();
        createSparkle(rect.left + 50, rect.top + 20);
    }
}

// Add message instantly (for history)
export function addMessageInstant(sender, text, timestamp) {
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

// Schedule follow-up messages
async function scheduleFollowUp(followUpCount) {
    const delay = 2000 + Math.random() * 3000;
    
    window.followUpTimeout = setTimeout(async () => {
        if (window.userIsTyping) {
            console.log('User is typing, canceling follow-up');
            return;
        }
        
        typingIndicator.classList.add('active');
        
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
                    if (window.userIsTyping) {
                        console.log('User started typing, not sending follow-up');
                        return;
                    }
                    
                    addMessage('misuki', data.message, data.emotion_timeline);
                    updateMisukiMood(data.mood, data.mood_text);
                    
                    if (data.should_continue && followUpCount < 2) {
                        scheduleFollowUp(followUpCount + 1);
                    }
                }
            } catch (error) {
                console.error('Follow-up error:', error);
                typingIndicator.classList.remove('active');
            }
        }, 1000 + Math.random() * 1000);
        
    }, delay);
}

// Make sendMessage globally available
window.sendMessage = sendMessage;