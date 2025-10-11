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
    let backspaceChance = 0.15;
    
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3;
    
    function getTypingSpeed(emotion) {
        const speeds = {
            'excited': 20, 'shocked': 15, 'amazed': 25,
            'surprised': 30, 'playful': 35, 'giggling': 30,
            'happy': 40, 'flustered': 25,
            'neutral': 50, 'content': 50, 'gentle': 55, 'curious': 50,
            'thoughtful': 70, 'confused': 75, 'concerned': 65,
            'comforting': 60, 'reassuring': 60, 'affectionate': 65, 'loving': 70,
            'sad': 90, 'upset': 85, 'pleading': 80, 'anxious': 95,
            'nervous': 110, 'shy': 120, 'embarrassed': 115, 'blushing': 100,
            'confident': 45, 'teasing': 40, 'sleepy': 130, 'pouty': 70,
            'relieved': 65, 'dreamy': 85
        };
        return speeds[emotion] || 50;
    }
    
    function shouldBackspace(emotion) {
        const backspaceEmotions = ['nervous', 'shy', 'embarrassed', 'thoughtful', 'concerned', 'anxious'];
        return backspaceEmotions.includes(emotion) && Math.random() < backspaceChance && characterIndex > 5;
    }
    
    function applyEmotionAnimation(emotion) {
        const bubble = document.getElementById(bubbleId);
        if (!bubble) return;
        
        bubble.className = bubble.className.split(' ').filter(c => !c.startsWith('emotion-')).join(' ');
        bubble.classList.add(`emotion-${emotion}`);
        
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
            bubble.className = bubble.className.split(' ').filter(c => !c.startsWith('emotion-')).join(' ');
            return;
        }
        
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
        
        if (currentEmotion !== lastEmotionSet) {
            updateMisukiMood(currentEmotion, getEmotionText(currentEmotion));
            applyEmotionAnimation(currentEmotion);
            lastEmotionSet = currentEmotion;
        }
        addTremorEffect(currentEmotion);
        
        if (!isBackspacing && shouldBackspace(currentEmotion) && characterIndex > 2) {
            isBackspacing = true;
            const backspaceCount = Math.floor(Math.random() * 3) + 2;
            
            function doBackspace(count) {
                if (count <= 0) {
                    isBackspacing = false;
                    setTimeout(typeNextCharacter, getTypingSpeed(currentEmotion));
                    return;
                }
                
                bubble.textContent = bubble.textContent.slice(0, -1);
                characterIndex--;
                setTimeout(() => doBackspace(count - 1), 50);
            }
            
            doBackspace(backspaceCount);
            return;
        }
        
        const char = fullText[characterIndex];
        bubble.textContent += char;
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        if (/[a-zA-Z0-9]/.test(char)) {
            const beep = beepSound.cloneNode();
            beep.volume = 0.3;
            beep.play().catch(e => {});
        }
        
        characterIndex++;
        
        const pauseTime = shouldPause(char, fullText[characterIndex]);
        const typingSpeed = getTypingSpeed(currentEmotion);
        
        setTimeout(typeNextCharacter, typingSpeed + pauseTime);
    }
    
    typeNextCharacter();
}

// Simple typing (fallback)
export function typeMessage(bubbleId, text, emotion = 'neutral') {
    const bubble = document.getElementById(bubbleId);
    if (!bubble) return;
    
    let index = 0;
    const speed = 50;
    
    const beepSound = new Audio('assets/audio/misuki beep.mp3');
    beepSound.volume = 0.3;
    
    function type() {
        if (index < text.length) {
            const char = text[index];
            bubble.textContent += char;
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
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