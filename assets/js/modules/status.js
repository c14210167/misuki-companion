// =========================================
// STATUS MODULE
// Live status updates and conversation initiations
// =========================================

import { typingIndicator } from '../chat.js';
import { addMessage } from './messaging.js';
import { updateMisukiMood } from './emotions.js';

// Update Misuki's live status
export async function updateLiveStatus() {
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

// Check if Misuki should initiate conversation
export async function checkForInitiation() {
    // Don't check if user just sent a message (within last 3 minutes)
    const timeSinceLastMessage = Date.now() - window.lastUserMessageTime;
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