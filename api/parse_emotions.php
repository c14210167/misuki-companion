<?php
// Parse emotions from Misuki's message and return expression changes
// This analyzes the message and suggests when to change expressions

function parseEmotionsInMessage($message) {
    // Split message into sentences
    $sentences = preg_split('/(?<=[.!?])\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);
    
    $emotion_timeline = [];
    $current_time = 0;
    
    foreach ($sentences as $index => $sentence) {
        $emotion = detectEmotionInSentence($sentence);
        
        // Calculate timing (words per sentence as a rough estimate)
        $word_count = str_word_count($sentence);
        $duration = $word_count * 0.3; // ~0.3 seconds per word
        
        $emotion_timeline[] = [
            'emotion' => $emotion,
            'sentence' => $sentence,
            'start_time' => $current_time,
            'duration' => $duration,
            'sentence_index' => $index
        ];
        
        $current_time += $duration;
    }
    
    return $emotion_timeline;
}

function detectEmotionInSentence($sentence) {
    $sentence_lower = strtolower($sentence);
    
    // Emotion keyword mapping
    $emotion_patterns = [
        // Excited/Happy
        'excited' => ['!', 'wow', 'amazing', 'awesome', 'yay', 'yes!', 'cool!', 'chemistry', 'discovered', 'found out'],
        'happy' => ['happy', 'glad', 'good', 'great', 'wonderful', 'love', 'hehe', 'haha', '😊', '💕', '✨'],
        'blushing' => ['blush', 'embarrass', 'shy', 'um...', 'uh...', 'well...', '😳', 'actually...'],
        'loving' => ['miss you', 'love you', 'care about', 'thinking of you', 'i adore', '❤️', '💕', 'sweetheart'],
        
        // Playful
        'teasing' => ['hehe', 'hihi', 'tease', '~', 'oh really?', 'sure~', 'mhm~'],
        'playful' => ['haha', 'lol', 'funny', 'silly', 'goofing', '😜', 'kidding'],
        'giggling' => ['giggle', 'hehe', 'heehee', '*laugh*', '*giggles*'],
        
        // Sad/Worried
        'sad' => ['sad', 'cry', 'tears', 'hurt', 'pain', '😢', '💔', 'heartbroken'],
        'concerned' => ['worried', 'concern', 'afraid', 'scared', 'nervous about', 'anxious about', 'what if'],
        'anxious' => ['anxious', 'nervous', 'stress', 'panic', 'overwhelm'],
        'upset' => ['upset', 'disappointed', 'let down', 'hurt', 'bothered'],
        'pleading' => ['please', 'sorry', 'apologize', 'forgive', 'i hope', 'can you', '🥺'],
        
        // Surprised/Confused
        'surprised' => ['what?!', 'really?!', 'no way!', 'seriously?!', 'omg', 'oh my', 'whoa'],
        'confused' => ['huh?', 'what?', 'confused', "don't understand", 'how come', 'why', '???'],
        'flustered' => ['flustered', 'embarrassed', 'oh no', 'uh oh', 'wait what', 'h-hey'],
        'amazed' => ['incredible', 'unbelievable', 'fascinating', 'mind-blowing', 'whoa'],
        
        // Supportive
        'comforting' => ["it's okay", 'there there', "don't worry", "i'm here", "you're safe", 'everything will'],
        'reassuring' => ['trust me', 'believe', "you can do", "you're strong", "you'll be", 'promise'],
        'affectionate' => ['hug', 'cuddle', 'hold', 'embrace', '*hugs*', 'my dear', 'my love'],
        
        // Thoughtful
        'thoughtful' => ['i think', 'maybe', 'perhaps', 'i wonder', 'hmm', '🤔', 'considering'],
        'confident' => ['definitely', 'absolutely', 'for sure', 'certainly', 'of course', 'obviously'],
        
        // Special
        'sleepy' => ['sleepy', 'tired', 'yawn', '*yawns*', 'bed', 'exhausted', '😴'],
        'pouty' => ['hmph', 'meanie', 'you dummy', 'baka', 'ignoring me', 'forgotten'],
        'relieved' => ['phew', 'relief', 'thank god', 'thank goodness', "i'm glad", 'finally'],
        'dreamy' => ['imagine', 'dream', 'one day', 'someday', 'wish', 'if only']
    ];
    
    // Check for punctuation intensity
    $has_exclamation = substr_count($sentence, '!') > 0;
    $has_question = substr_count($sentence, '?') > 0;
    $has_ellipsis = strpos($sentence, '...') !== false;
    
    // Detect emotion based on keywords
    foreach ($emotion_patterns as $emotion => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($sentence_lower, $keyword) !== false) {
                return $emotion;
            }
        }
    }
    
    // Fallback based on punctuation
    if ($has_exclamation) {
        return 'excited';
    } elseif ($has_question) {
        return 'confused';
    } elseif ($has_ellipsis) {
        return 'thoughtful';
    }
    
    // Default
    return 'neutral';
}

function getEmotionImage($emotion) {
    // Map emotions to image files
    $emotion_images = [
        'neutral' => 'misuki-neutral.png',
        'happy' => 'misuki-happy.png',
        'excited' => 'misuki-excited.png',
        'blushing' => 'misuki-blushing.png',
        'loving' => 'misuki-loving.png',
        'content' => 'misuki-content.png',
        
        'sad' => 'misuki-sad.png',
        'concerned' => 'misuki-concerned.png',
        'anxious' => 'misuki-anxious.png',
        'upset' => 'misuki-upset.png',
        'pleading' => 'misuki-pleading.png',
        
        'surprised' => 'misuki-surprised.png',
        'confused' => 'misuki-confused.png',
        'flustered' => 'misuki-flustered.png',
        'amazed' => 'misuki-amazed.png',
        
        'teasing' => 'misuki-teasing.png',
        'playful' => 'misuki-playful.png',
        'giggling' => 'misuki-giggling.png',
        'confident' => 'misuki-confident.png',
        
        'embarrassed' => 'misuki-embarrassed.png',
        'shy' => 'misuki-shy.png',
        'nervous' => 'misuki-nervous.png',
        
        'comforting' => 'misuki-comforting.png',
        'affectionate' => 'misuki-affectionate.png',
        'reassuring' => 'misuki-reassuring.png',
        'gentle' => 'misuki-gentle.png',
        
        'thoughtful' => 'misuki-thoughtful.png',
        'sleepy' => 'misuki-sleepy.png',
        'pouty' => 'misuki-pouty.png',
        'relieved' => 'misuki-relieved.png',
        'dreamy' => 'misuki-dreamy.png'
    ];
    
    return $emotion_images[$emotion] ?? 'misuki-neutral.png';
}

?>