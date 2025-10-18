// =========================================
// MESSAGING MODULE (BULLETPROOF VERSION)
// Handles sending messages, typing, and display
// =========================================
window.isPrivateMode = false;

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
    messageInput.addEventListener('keydown', handleKeyPress);
    
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
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

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'morning';
    if (hour >= 12 && hour < 17) return 'afternoon';
    if (hour >= 17 && hour < 21) return 'evening';
    return 'night';
}

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

function calculateTypingDuration(text, emotionTimeline) {
    const charCount = text.length;
    const avgSpeed = 60;
    
    const punctuationPauses = (text.match(/[.!?]/g) || []).length * 500;
    const commaPauses = (text.match(/[,;]/g) || []).length * 250;
    
    let emotionPauses = 0;
    if (emotionTimeline && emotionTimeline.length > 1) {
        emotionPauses = (emotionTimeline.length - 1) * 300;
    }
    
    const baseDuration = (charCount * avgSpeed) + punctuationPauses + commaPauses + emotionPauses;
    return baseDuration + 1000;
}

// Send message
export async function sendMessage() {
    const message = messageInput.value.trim();
    const userInterrupted = false; 

    if (!message && !window.attachedFile) return;

    if (window.cancelSplitMessages) {
        window.cancelSplitMessages();
        // Check if user sent a message while Misuki was about to send split messages
        const userInterrupted = window.userIsTyping || window.userWasTypingDuringSplit;
        window.userWasTypingDuringSplit = false; // Reset flag
    }

    window.lastUserMessageTime = Date.now();

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
            time_confused: timeConfused,
            user_interrupted: userInterrupted  // ✨ NEW
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
        console.log('🔍 FULL RESPONSE:', JSON.stringify(data, null, 2));
        
        if (data.private_mode !== undefined) {
            window.isPrivateMode = data.private_mode;
        }
        
        if (data.is_split && data.additional_messages) {
            handleSplitMessages(data);
        } else {
            setTimeout(() => {
                typingIndicator.classList.remove('active');
                
                // 🛡️ BULLETPROOF CHECK
                const responseText = data.response || data.message || '[No response]';
                console.log('📝 RESPONSE TEXT:', responseText);
                console.log('😊 EMOTION TIMELINE:', data.emotion_timeline);
                
                addMessage('misuki', responseText, data.emotion_timeline);
                updateMisukiMood(data.mood, data.mood_text);
                
                if (data.should_follow_up) {
                    scheduleFollowUp(0);
                }
            }, 1000 + Math.random() * 1500);
        }

    } catch (error) {
        console.error('❌ ERROR:', error);
        typingIndicator.classList.remove('active');
        addMessage('misuki', "Oh no... I'm having trouble thinking right now. Could you try again?");
    }
}

async function handleSplitMessages(data) {
    const allMessages = [data.response, ...data.additional_messages];
    const allTimelines = data.emotion_timelines;
    
    console.log(`💬 Split: ${allMessages.length} messages`);
    
    let currentPart = 0;
    let nextMessageTimeout = null;
    let userStartedTyping = false;  // Track if user typed during split
    
    function sendNextPart() {
        if (currentPart >= allMessages.length) return;
        
        const message = allMessages[currentPart];
        const timeline = allTimelines[currentPart];
        
        // ✨ Show typing indicator BEFORE 2nd, 3rd, etc. messages
        if (currentPart > 0) {
            typingIndicator.classList.add('active');
        }
        
        setTimeout(() => {
            typingIndicator.classList.remove('active');
            addMessage('misuki', message, timeline);
            
            if (currentPart === 0) {
                updateMisukiMood(data.mood, data.mood_text);
            }
            
            currentPart++;
            
            if (currentPart < allMessages.length) {
                const typingDuration = calculateTypingDuration(message, timeline);
                const pauseBetween = 800 + Math.random() * 1200;
                const totalDelay = typingDuration + pauseBetween;
                
                nextMessageTimeout = setTimeout(() => {
                    // ✨ WAIT 5 SECONDS, then check if user is typing
                    setTimeout(() => {
                        if (window.userIsTyping) {
                            console.log('⏸️ User is typing, waiting up to 20 seconds...');
                            userStartedTyping = true;
                            
                            const startWaitTime = Date.now();
                            const maxWait = 20000; // 20 seconds
                            
                            // Check every second if user stopped typing
                            const checkInterval = setInterval(() => {
                                const waitedTime = Date.now() - startWaitTime;
                                
                                if (!window.userIsTyping) {
                                    // User stopped typing
                                    console.log('▶️ User stopped typing, continuing...');
                                    clearInterval(checkInterval);
                                    sendNextPart();
                                } else if (waitedTime >= maxWait) {
                                    // 20 seconds passed, send anyway
                                    console.log('⏱️ 20 seconds passed, sending anyway (user was typing)');
                                    clearInterval(checkInterval);
                                    // Mark that we should notify backend
                                    window.userWasTypingDuringSplit = true;
                                    sendNextPart();
                                }
                            }, 1000); // Check every 1 second
                            
                        } else {
                            // User not typing, send immediately
                            sendNextPart();
                        }
                    }, 5000); // Wait 5 seconds before checking
                    
                }, totalDelay);
            }
        }, currentPart > 0 ? 1500 : 0); // 1.5s typing indicator for 2nd+ messages
    }
    
    sendNextPart();
    
    window.cancelSplitMessages = function() {
        if (nextMessageTimeout) {
            clearTimeout(nextMessageTimeout);
            nextMessageTimeout = null;
        }
    };
}

export function addDateSeparator(dateStr) {
    const separator = document.createElement('div');
    separator.className = 'date-separator';
    separator.innerHTML = `<span>${dateStr}</span>`;
    chatMessages.appendChild(separator);
}

// 🛡️ BULLETPROOF ADD MESSAGE FUNCTION
export function addMessage(sender, text, emotion_timeline = null) {
    console.log(`\n=== ADD MESSAGE START ===`);
    console.log(`Sender: ${sender}`);
    console.log(`Text: "${text}"`);
    console.log(`Text type: ${typeof text}`);
    console.log(`Text length: ${text?.length || 0}`);
    console.log(`Has emotion timeline: ${!!emotion_timeline}`);
    console.log(`Emotion timeline:`, emotion_timeline);
    
    // 🚨 CRITICAL: Ensure text is valid
    if (!text || typeof text !== 'string' || text.trim() === '') {
        console.error('❌ CRITICAL ERROR: Invalid text!');
        text = '[Error: Empty message]';
    }
    
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
            <div class="message-bubble" id="${bubbleId}">${sender === 'user' ? text : ''}</div>
            <div class="message-time">${timeStr}</div>
        </div>
    `;
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    console.log(`✅ Message div created with bubble ID: ${bubbleId}`);
    
    setTimeout(() => {
        messageDiv.style.transition = 'all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateX(0)';
    }, 10);
    
    // 🛡️ ONLY FOR MISUKI'S MESSAGES
    if (sender === 'misuki') {
        const bubble = document.getElementById(bubbleId);
        
        if (!bubble) {
            console.error(`❌ CRITICAL: Bubble ${bubbleId} not found!`);
            return;
        }
        
        console.log(`📍 Bubble element found:`, bubble);
        
        // Check if we should use typing animation
        const shouldType = emotion_timeline && Array.isArray(emotion_timeline) && emotion_timeline.length > 0;
        console.log(`Should use typing animation: ${shouldType}`);
        
        if (shouldType) {
            console.log(`✨ Starting typeMessageWithEmotions...`);
            typeMessageWithEmotions(bubbleId, text, emotion_timeline);
        } else {
            console.log(`📝 Setting text directly...`);
            bubble.textContent = text;
            console.log(`✅ Text set. Bubble content: "${bubble.textContent}"`);
        }
        
        // 🛡️ SAFETY CHECK: Verify text was added after 200ms
        setTimeout(() => {
            const verifyBubble = document.getElementById(bubbleId);
            if (verifyBubble) {
                console.log(`🔍 VERIFICATION: Bubble content is "${verifyBubble.textContent}"`);
                if (!verifyBubble.textContent || verifyBubble.textContent.trim() === '') {
                    console.error(`❌ EMERGENCY: Bubble is empty! Forcing text...`);
                    verifyBubble.textContent = text;
                    console.log(`🚨 FORCED TEXT. New content: "${verifyBubble.textContent}"`);
                }
            } else {
                console.error(`❌ CRITICAL: Bubble disappeared!`);
            }
        }, 200);
        
        const rect = messageDiv.getBoundingClientRect();
        createSparkle(rect.left + 50, rect.top + 20);
    }
    
    console.log(`=== ADD MESSAGE END ===\n`);
}

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

async function scheduleFollowUp(followUpCount) {
    const delay = 2000 + Math.random() * 3000;
    
    window.followUpTimeout = setTimeout(async () => {
        if (window.userIsTyping) return;
        
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
                    if (window.userIsTyping) return;
                    
                    addMessage('misuki', data.message, data.emotion_timeline);
                    updateMisukiMood(data.mood, data.mood_text);

                    // 🔧 FIX: Save follow-up to database
                    await saveFollowUpToDatabase(data.message, data.mood);

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

// 🔧 NEW: Save follow-up messages to database
async function saveFollowUpToDatabase(message, mood) {
    try {
        await fetch('api/save_follow_up.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: 1,
                misuki_message: message,
                mood: mood || 'gentle'
            })
        });
    } catch (error) {
        console.error('Error saving follow-up:', error);
    }
}

window.sendMessage = sendMessage;