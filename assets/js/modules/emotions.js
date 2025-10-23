// =========================================
// EMOTIONS MODULE - FIXED FOR PRIVATE MODE
// Handles emotion animations and mood updates
// =========================================

import { misukiImage, misukiMood } from '../chat.js';

// Mood images mapping (normal mode)
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

export function getEmotionImage(emotion) {
    // ✅ FIX: Check if we're in private mode
    if (window.isPrivateMode) {
        const privatePath = `assets/images/misuki-private/${emotion}.png`;
        console.log(`🔒 Private mode: Using ${privatePath}`);
        return privatePath;
    }
    
    return moodImages[emotion] || 'assets/images/misuki-neutral.png';
}

// ✅ FIX: Update Misuki's mood (image + text) - properly handles private mode
export function updateMisukiMood(emotion, moodText) {
    const moodDisplay = document.querySelector('.misuki-mood-text');
    const misukiImage = document.querySelector('.misuki-image');
    
    console.log(`😊 Updating mood to: ${emotion} (Private mode: ${window.isPrivateMode})`);
    
    // ✅ Always update the text
    if (moodDisplay) {
        const displayText = moodText || getEmotionText(emotion);
        moodDisplay.textContent = displayText;
        console.log(`💡 Updated emotion text to: "${displayText}"`);
    }
    
    if (misukiImage) {
        // ✅ FIX: Determine image path based on private mode
        let imagePath;
        
        if (window.isPrivateMode) {
            // Try private folder first
            imagePath = `assets/images/misuki-private/${emotion}.png`;
            console.log(`🔒 Attempting private image: ${imagePath}`);
            
            // Fallback to normal if private doesn't exist
            misukiImage.onerror = function() {
                console.log(`⚠️ Private image not found, falling back to normal`);
                this.onerror = null; // Prevent infinite loop
                this.src = moodImages[emotion] || 'assets/images/misuki-neutral.png';
            };
        } else {
            // Use normal images
            imagePath = getEmotionImage(emotion);
            console.log(`📷 Using normal image: ${imagePath}`);
            misukiImage.onerror = null; // Clear error handler
        }
        
        misukiImage.src = imagePath;
        misukiImage.alt = emotion;
    }
}

// ✅ Get emotion display text
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
    
    let total_words = 0;
    for (let i = 0; i < emotion_timeline.length; i++) {
        total_words += emotion_timeline[i].word_count;
    }
    
    console.log(`📝 Total words: ${total_words}`);
    
    const filtered_timeline = emotion_timeline.filter(e => e.word_count >= 2);
    
    if (filtered_timeline.length === 0) {
        console.log('⚠️ No emotions with enough words');
        return;
    }
    
    console.log(`✅ Filtered to ${filtered_timeline.length} emotions`);
    
    let elapsed_words = 0;
    let current_emotion_index = 0;
    
    const interval = setInterval(() => {
        elapsed_words++;
        
        let accumulated_words = 0;
        for (let i = 0; i <= current_emotion_index && i < filtered_timeline.length; i++) {
            accumulated_words += filtered_timeline[i].word_count;
        }
        
        if (elapsed_words >= accumulated_words && current_emotion_index < filtered_timeline.length - 1) {
            current_emotion_index++;
            const newEmotion = filtered_timeline[current_emotion_index].emotion;
            
            // ✅ Update the mood with the new emotion
            updateMisukiMood(newEmotion, getEmotionText(newEmotion));
            
            console.log(`😊 Emotion changed to: ${newEmotion}`);
        }
        
        if (elapsed_words >= total_words) {
            clearInterval(interval);
            console.log('✅ Emotion animation complete');
        }
    }, 800);
}