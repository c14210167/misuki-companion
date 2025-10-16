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

// Helper: Count words in string
function str_word_count(str) {
    return str.split(/\s+/).filter(word => word.length > 0).length;
}

// ANTI-FLICKER: Animate emotion timeline
export function animateEmotionTimeline(emotion_timeline) {
    if (!emotion_timeline || emotion_timeline.length === 0) return;
    
    const MIN_EMOTION_DISPLAY_TIME = 2500; // Minimum 2.5 seconds
    
    // STEP 1: Merge consecutive identical emotions
    const merged = [];
    let current = null;
    
    for (let i = 0; i < emotion_timeline.length; i++) {
        if (!current) {
            current = { ...emotion_timeline[i] };
        } else if (current.emotion === emotion_timeline[i].emotion) {
            current.duration += emotion_timeline[i].duration;
            current.sentence += ' ' + emotion_timeline[i].sentence;
        } else {
            merged.push(current);
            current = { ...emotion_timeline[i] };
        }
    }
    if (current) merged.push(current);
    
    console.log(`🔗 Merged ${emotion_timeline.length} → ${merged.length} emotions`);
    
    // STEP 2: Filter out very short emotions
    const filtered = [];
    
    for (let i = 0; i < merged.length; i++) {
        const durationMs = merged[i].duration * 1000;
        
        if (durationMs >= MIN_EMOTION_DISPLAY_TIME) {
            filtered.push(merged[i]);
        } else {
            const half_time = merged[i].duration / 2;
            
            if (filtered.length > 0) {
                filtered[filtered.length - 1].duration += half_time;
            }
            
            if (i + 1 < merged.length) {
                merged[i + 1].duration += half_time;
            } else if (filtered.length > 0) {
                filtered[filtered.length - 1].duration += merged[i].duration;
            }
            
            console.log(`⏭️ Skipped short emotion: ${merged[i].emotion} (${durationMs}ms)`);
        }
    }
    
    // STEP 3: Short messages = single emotion
    const total_words = emotion_timeline.reduce((sum, e) => sum + str_word_count(e.sentence), 0);
    
    if (total_words < 10 && filtered.length > 1) {
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