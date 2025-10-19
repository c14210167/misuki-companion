// =========================================
// TYPING MODULE
// Undertale-style character-by-character typing
// =========================================

import { chatMessages } from '../chat.js';
import { updateMisukiMood, getEmotionText } from './emotions.js';
import { triggerScreenShake, triggerFlash, triggerBlur } from './effects.js';

// Undertale-style typing with emotions
export function typeMessageWithEmotions(bubbleId, fullText, emotion_timeline) {
    const bubble = document.getElementById(bubbleId);
    if (!bubble) return;
    
    let currentEmotionIndex = 0;
    let characterIndex = 0;
    let currentSentenceStart = 0;
    let lastEmotionSet = '';
    let isBackspacing = false;
    let hasBackspaced = false;
    
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3;
    
    // ✅ FIX #6: Slower typing speeds across the board (doubled all values for slower typing)
    function getTypingSpeed(emotion) {
        const speeds = {
            'excited': 40, 'shocked': 30, 'amazed': 50,
            'surprised': 60, 'playful': 70, 'giggling': 60,
            'happy': 80, 'flustered': 50,
            'neutral': 100, 'content': 100, 'gentle': 110, 'curious': 100,
            'thoughtful': 140, 'confused': 150, 'concerned': 130,
            'comforting': 120, 'reassuring': 120, 'affectionate': 130, 'loving': 140,
            'sad': 180, 'upset': 170, 'pleading': 160, 'anxious': 190,
            'nervous': 220, 'shy': 240, 'embarrassed': 230, 'blushing': 200,
            'confident': 90, 'teasing': 80, 'sleepy': 260, 'pouty': 140,
            'relieved': 130, 'dreamy': 170
        };
        return speeds[emotion] || 100;
    }
    
    function shouldBackspace(emotion) {
        if (hasBackspaced) return false;
        if (!['nervous', 'shy', 'embarrassed', 'anxious', 'flustered'].includes(emotion)) return false;
        
        const nextChar = fullText[characterIndex];
        if (!nextChar || nextChar === ' ') return false;
        
        return Math.random() < 0.15;
    }
    
    function typeNextCharacter() {
        if (characterIndex >= fullText.length) {
            bubble.classList.remove('typing');
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return;
        }
        
        const currentEmotion = emotion_timeline[currentEmotionIndex]?.emotion || 'neutral';
        
        if (currentEmotion !== lastEmotionSet) {
            bubble.className = 'message-bubble typing emotion-' + currentEmotion;
            updateMisukiMood(currentEmotion, getEmotionText(currentEmotion));
            lastEmotionSet = currentEmotion;
        }
        
        if (!isBackspacing && shouldBackspace(currentEmotion)) {
            isBackspacing = true;
            hasBackspaced = true;
            
            const backspaceCount = Math.floor(Math.random() * 3) + 2;
            let backspaced = 0;
            
            const backspaceInterval = setInterval(() => {
                if (backspaced >= backspaceCount || characterIndex === currentSentenceStart) {
                    clearInterval(backspaceInterval);
                    isBackspacing = false;
                    setTimeout(typeNextCharacter, 100);
                    return;
                }
                
                if (characterIndex > currentSentenceStart) {
                    characterIndex--;
                    bubble.textContent = fullText.substring(0, characterIndex);
                    backspaced++;
                }
            }, 50);
            return;
        }
        
        const char = fullText[characterIndex];
        bubble.textContent += char;
        characterIndex++;
        
        if (['.', '!', '?'].includes(char)) {
            currentSentenceStart = characterIndex;
            currentEmotionIndex = Math.min(currentEmotionIndex + 1, emotion_timeline.length - 1);
        }
        
        if (char !== ' ' && char !== '\n') {
            beepSound.currentTime = 0;
            beepSound.play().catch(() => {});
        }
        
        if (char === '!' && currentEmotion === 'excited') {
            triggerScreenShake();
        }
        if (char === '?' && currentEmotion === 'shocked') {
            triggerFlash();
        }
        
        const typingSpeed = getTypingSpeed(currentEmotion);
        
        let delay = typingSpeed;
        if (['.', '!', '?'].includes(char)) {
            delay += 300;
        } else if ([',', ';', ':'].includes(char)) {
            delay += 150;
        }
        
        chatMessages.scrollTop = chatMessages.scrollHeight;
        setTimeout(typeNextCharacter, delay);
    }
    
    bubble.className = 'message-bubble typing';
    typeNextCharacter();
}