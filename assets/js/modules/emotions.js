// =========================================
// EMOTIONS MODULE
// Handles emotion animations and mood updates
// =========================================

import { misukiImage, misukiMood } from '../chat.js';

// Mood images mapping
const moodImages = {
    neutral: 'assets/images/misuki-neutral.png',
    happy: 'assets/images/misuki-happy.png',
    excited: 'assets/images/misuki-excited.png',
    blushing: 'assets/images/misuki-blushing.png',
    loving: 'assets/images/misuki-loving.png',
    content: 'assets/images/misuki-content.png',
    sad: 'assets/images/misuki-sad.png',
    concerned: 'assets/images/misuki-concerned.png',
    anxious: 'assets/images/misuki-anxious.png',
    upset: 'assets/images/misuki-upset.png',
    pleading: 'assets/images/misuki-pleading.png',
    surprised: 'assets/images/misuki-surprised.png',
    shocked: 'assets/images/misuki-surprised.png',
    confused: 'assets/images/misuki-confused.png',
    flustered: 'assets/images/misuki-flustered.png',
    amazed: 'assets/images/misuki-amazed.png',
    curious: 'assets/images/misuki-thoughtful.png',
    teasing: 'assets/images/misuki-teasing.png',
    playful: 'assets/images/misuki-playful.png',
    giggling: 'assets/images/misuki-giggling.png',
    confident: 'assets/images/misuki-confident.png',
    embarrassed: 'assets/images/misuki-embarrassed.png',
    shy: 'assets/images/misuki-shy.png',
    nervous: 'assets/images/misuki-nervous.png',
    comforting: 'assets/images/misuki-comforting.png',
    affectionate: 'assets/images/misuki-affectionate.png',
    reassuring: 'assets/images/misuki-reassuring.png',
    gentle: 'assets/images/misuki-gentle.png',
    thoughtful: 'assets/images/misuki-thoughtful.png',
    sleepy: 'assets/images/misuki-sleepy.png',
    pouty: 'assets/images/misuki-pouty.png',
    relieved: 'assets/images/misuki-relieved.png',
    dreamy: 'assets/images/misuki-dreamy.png'
};

// 🔧 MISSING FUNCTION - THIS IS WHAT WAS CAUSING THE ERROR!
export function getEmotionImage(emotion) {
    return moodImages[emotion] || 'assets/images/misuki-neutral.png';
}

// Update Misuki's mood (image + text)
export function updateMisukiMood(emotion, moodText) {
    const moodDisplay = document.querySelector('.misuki-mood-text');
    const misukiImage = document.querySelector('.misuki-image');
    
    if (moodDisplay) {
        moodDisplay.textContent = moodText || getEmotionText(emotion);
    }
    
    if (misukiImage) {
        // Determine image path based on private mode
        let imagePath;
        if (window.isPrivateMode) {
            // Use private folder images
            imagePath = `assets/images/misuki-private/${emotion}.png`;
            
            // Fallback to normal if private doesn't exist
            misukiImage.onerror = function() {
                this.onerror = null;
                this.src = getEmotionImage(emotion);
            };
        } else {
            imagePath = getEmotionImage(emotion);
        }
        
        misukiImage.src = imagePath;
        misukiImage.alt = emotion;
    }
}

// Get emotion display text
export function getEmotionText(emotion) {
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
        curious: 'Curious',
        teasing: 'Teasing',
        playful: 'Playful',
        giggling: 'Giggling',
        confident: 'Confident',
        embarrassed: 'Embarrassed',
        shy: 'Shy',
        nervous: 'Nervous',
        comforting: 'Comforting',
        affectionate: 'Affectionate',
        reassuring: 'Reassuring',
        gentle: 'Being gentle',
        thoughtful: 'Thinking',
        sleepy: 'Sleepy',
        pouty: 'Pouty',
        relieved: 'Relieved',
        dreamy: 'Dreaming'
    };
    return emotionTexts[emotion] || 'Listening';
}

// Animate emotions over time (like Undertale)
export function animateEmotions(emotion_timeline) {
    if (!emotion_timeline || emotion_timeline.length === 0) {
        console.log('⚠️ No emotion timeline');
        return;
    }
    
    console.log(`😊 Animating ${emotion_timeline.length} emotions`);
    
    // STEP 1: Calculate total words in the message
    let total_words = 0;
    for (let i = 0; i < emotion_timeline.length; i++) {
        total_words += emotion_timeline[i].word_count;
    }
    
    console.log(`📝 Total words: ${total_words}`);
    
    // STEP 2: Filter out very short emotions (less than 5% of message)
    const filtered = [];
    for (let i = 0; i < emotion_timeline.length; i++) {
        const percentage = (emotion_timeline[i].word_count / total_words) * 100;
        
        if (percentage >= 5) {
            filtered.push(emotion_timeline[i]);
        } else {
            console.log(`⏭️ Skipping ${emotion_timeline[i].emotion} (${percentage.toFixed(1)}% - too short)`);
        }
    }
    
    // STEP 3: If message is very short (under 15 words), just use the longest/most important emotion
    if (total_words < 15) {
        let best = filtered[0];
        let best_score = 0;
        
        for (let i = 0; i < filtered.length; i++) {
            const score = filtered[i].word_count * 100;
            if (score > best_score) {
                best = filtered[i];
                best_score = score;
            }
        }
        
        console.log(`🎯 Short message (${total_words} words) - single emotion: ${best.emotion}`);
        updateMisukiMood(best.emotion, getEmotionText(best.emotion));
        return;
    }
    
    // STEP 4: Fallback to last emotion if filtered is empty
    if (filtered.length === 0) {
        const lastEmotion = emotion_timeline[emotion_timeline.length - 1].emotion;
        updateMisukiMood(lastEmotion, getEmotionText(lastEmotion));
        console.log(`⚠️ All filtered - using last: ${lastEmotion}`);
        return;
    }
    
    // STEP 5: Single emotion = no animation
    if (filtered.length === 1) {
        updateMisukiMood(filtered[0].emotion, getEmotionText(filtered[0].emotion));
        console.log(`✅ Single emotion: ${filtered[0].emotion}`);
        return;
    }
    
    console.log(`✅ Final: ${filtered.length} emotions`);
    
    // STEP 6: Animate
    let currentIndex = 0;
    
    function changeExpression() {
        if (currentIndex >= filtered.length) return;
        
        const current = filtered[currentIndex];
        const currentDuration = current.duration * 1000;
        
        updateMisukiMood(current.emotion, getEmotionText(current.emotion));
        console.log(`😊 [${currentIndex + 1}/${filtered.length}] ${current.emotion} for ${(currentDuration/1000).toFixed(1)}s`);
        
        currentIndex++;
        
        if (currentIndex < filtered.length) {
            setTimeout(changeExpression, currentDuration);
        }
    }
    
    changeExpression();
}