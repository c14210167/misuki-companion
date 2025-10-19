// =========================================
// HISTORY MODULE (FIXED)
// Load and display chat history
// 🔧 FIX: Properly display follow-up messages without showing [FOLLOW-UP]
// =========================================

import { chatMessages } from '../chat.js';
import { addDateSeparator, addMessageInstant } from './messaging.js';
import { updateMisukiMood, getEmotionText } from './emotions.js';

// Load chat history on page load
export async function loadChatHistory() {
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
                limit: 50  // ✅ Increased from 25 to 50 to show more history
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
            let lastMood = null;
            
            // Add all previous conversations with date separators
            data.conversations.forEach((conv, index) => {
                const msgDate = new Date(conv.timestamp);
                const dateStr = msgDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                // Log first 3 and last 3 messages for debugging
                if (index < 3 || index >= data.conversations.length - 3) {
                    console.log(`Message ${index + 1}/${data.conversations.length}: ${dateStr} ${msgDate.toLocaleTimeString()} - "${conv.user_message.substring(0, 50)}..."`);
                }
                
                // Add date separator if day changed
                if (dateStr !== lastDate) {
                    addDateSeparator(dateStr);
                    lastDate = dateStr;
                    window.lastMessageDate = dateStr;
                }
                
                // ✅ FIX: Check if this is a follow-up message
                const isFollowUp = conv.user_message === '[FOLLOW-UP]';
                
                if (!isFollowUp) {
                    // Normal message - add user message
                    addMessageInstant('user', conv.user_message, conv.timestamp);
                } else {
                    // Follow-up message - skip user message, only show Misuki's message
                    console.log(`💬 Follow-up message detected at index ${index}`);
                }
                
                // 🔧 Check if Misuki's response contains [SPLIT] marker
                if (conv.misuki_response.includes('[SPLIT]')) {
                    // This was a split message - break it apart and display ALL parts
                    const splitMessages = conv.misuki_response.split('\n[SPLIT]\n');
                    console.log(`💬 Found split message with ${splitMessages.length} parts`);
                    
                    // 🔧 Add EACH part as a separate message
                    splitMessages.forEach((messagePart, partIndex) => {
                        const trimmedPart = messagePart.trim();
                        if (trimmedPart) {  // Only add non-empty parts
                            console.log(`   📝 Part ${partIndex + 1}: "${trimmedPart.substring(0, 50)}..."`);
                            addMessageInstant('misuki', trimmedPart, conv.timestamp);
                        }
                    });
                } else {
                    // Normal single message
                    addMessageInstant('misuki', conv.misuki_response, conv.timestamp);
                }
                
                // Track the last mood
                if (conv.mood) {
                    lastMood = conv.mood;
                }
            });
            
            // Update Misuki's emotion to match the last message's mood
            if (lastMood) {
                console.log(`😊 Restoring Misuki's last emotion: ${lastMood}`);
                updateMisukiMood(lastMood, getEmotionText(lastMood));
            }
            
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
            console.log(`🎯 Last date displayed: ${window.lastMessageDate}`);
        } else {
            console.log('ℹ️ No chat history found or empty');
        }
    } catch (error) {
        console.error('❌ Error loading chat history:', error);
        // Keep the default greeting if history fails to load
    }
}