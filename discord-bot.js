// =========================================
// MISUKI DISCORD BOT (RELATIONSHIP SYSTEM)
// ✨ Multi-user support, nicknames, trust levels!
// 🌐 Web search enabled - Misuki can naturally search and share links!
// 🎨 Dynamic GIF search - Misuki finds the perfect gif for each moment!
// =========================================
//
// REQUIRED API KEYS IN .env:
// - DISCORD_TOKEN: Your Discord bot token
// - ANTHROPIC_API_KEY: Your Anthropic/Claude API key
// - BRAVE_API_KEY: Your Brave Search API key (get free at https://brave.com/search/api/)
// - TENOR_API_KEY: Your Tenor API key (get free at https://developers.google.com/tenor/guides/quickstart)
//
// =========================================

require('dotenv').config();
const { Client, GatewayIntentBits, ActivityType, Partials } = require('discord.js');
const mysql = require('mysql2/promise');
const axios = require('axios');

// Discord client setup
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.DirectMessages,
        GatewayIntentBits.DirectMessageTyping,
        GatewayIntentBits.GuildMessageTyping,
        GatewayIntentBits.GuildPresences
    ],
    partials: [Partials.Channel]
});

// Database connection pool (FIXED: prevents timeout issues!)
let db;

// Your Discord ID (the main user - Dan)
const MAIN_USER_ID = '406105172780122113';

// Allowed channel ID - Misuki will only respond in this channel (or DMs)
const ALLOWED_CHANNEL_ID = '1436370040524902520';

// Selfie permission mode - controls who can request selfies
let selfieMode = 'all'; // 'all' or 'private' (private = Dan only)

// ===== OUTFIT CONSISTENCY SYSTEM =====
// Defines Misuki's outfit variations for consistent appearance across images
const MISUKI_OUTFITS = {
    default: "casual comfortable clothes, oversized hoodie, shorts",
    pajamas: "cute pajamas, loose comfortable sleepwear",
    schoolUniform: "school uniform, white blouse, dark skirt, knee socks",
    comfy: "comfy loungewear, soft sweatpants, cozy sweater",
    sporty: "sporty outfit, athletic wear, sports bra, yoga pants",
    casual: "casual outfit, t-shirt, jeans",
    dress: "cute casual dress, summer dress",
    sleepwear: "nightgown, sleep shirt",
    workout: "workout clothes, tank top, gym shorts",
    cozy: "cozy hoodie, comfortable pants"
};

// Track Misuki's current outfit (changes based on activities)
let currentOutfit = MISUKI_OUTFITS.default;

// Resolve outfit key to full prompt text
function resolveOutfit(outfitKey) {
    if (outfitKey === null) {
        return ''; // No outfit (shower/changing)
    }
    return MISUKI_OUTFITS[outfitKey] || MISUKI_OUTFITS.default;
}

// Update current outfit when activity changes
function updateCurrentOutfit(activity) {
    if (activity && activity.outfit !== undefined) {
        if (activity.outfit === null) {
            // Temporarily no outfit (shower/changing)
            console.log('   👔 Outfit: None (shower/changing)');
        } else if (activity.outfit) {
            currentOutfit = resolveOutfit(activity.outfit);
            console.log(`   👔 Outfit changed to: ${activity.outfit} -> "${currentOutfit}"`);
        }
    }
}

// Status history tracking (for variety and awareness)
const statusHistory = [];
const MAX_STATUS_HISTORY = 5;

// Conversation queue system - handles multiple people messaging at once
const conversationQueue = new Map(); // channelId -> { currentUser, queue: [], isSending: false }

// Processing lock per user (prevents parallel responses to same user)
const userProcessingLock = new Map(); // userId -> { isProcessing: boolean, latestMessageId: string, pendingMessages: [] }

// Message buffer system - waits for user to finish typing before responding
// userId -> { messages: [], timeout: timeoutId, isWaiting: boolean }
const messageBuffer = new Map();

// Emoji reaction system - Misuki's custom emojis from the server
const EMOJI_SERVER_ID = '1436369815798419519';

const customEmojis = {
    // Love & Affection
    cat_love: '<:cat_love:1437073036351246446>',
    kanna_heart: '<:kanna_heart:1437072976259321916>',

    // Happy & Excited
    cat_sparkly_eyes: '<:cat_sparkly_eyes:1437073033037746188>',
    cat_woah: '<:cat_woah:1437072981909311542>',
    komi: '<a:komi_surprised_happy:1437073014524219436>',
    clapping: '<a:clappinggg:1437072973403131904>',

    // Playful & Teasing
    lapsmirk: '<:lapras_smirk:1437072989974691982>',
    dog_laugh: '<:dog_laugh:1437072986606927882>',
    cat_laugh: '<:cat_laugh:1437073020647903272>',
    cat_spinning: '<a:cat_spinning:1437073017820811377>',

    // Shy & Flustered
    cute_shy: '<a:cute_shy:1437072996228530176>',

    // Sad & Concerned
    cat_cry: '<:cat_cry:1437073025785921627>',
    cute_plead: '<:cute_pleading:1437073000678559835>',

    // Thinking & Curious
    pikathink: '<:pikachu_thinking:1437073003773952011>',

    // Greetings
    cat_wave: '<:cat_wave:1437073029547950110>',

    // Sleepy
    sleepingg: '<:sleepinggg:1437072984203595949>',

    // Surprised/Shocked
    cat_scream: '<:cat_scream:1437072979132551278>',

    // Agreement/Disagreement
    thats_true: '<:thats_true:1437073009256173699>',
    thats_false: '<:thats_false:1437073006626209834>'
};

// Get human-readable emoji names from emoji objects
function getReactionNames(emojiObjects, userProfile, messageContent) {
    const emojiMap = {
        'cat_love': 'a cat love emoji',
        'kanna_heart': 'a Kanna heart emoji',
        'hi': 'a hi/wave emoji',
        'anime_clap_excited': 'an excited clapping emoji',
        'lol2': 'a laughing emoji',
        'cute_shy': 'a cute shy emoji',
        'cute_plead': 'a cute pleading/concerned emoji',
        'raidensleep': 'a sleepy emoji',
        'kb_pikathink': 'a Pikachu thinking emoji',
        'thats_true': 'a "that\'s true" emoji',
        'ramen': 'a ramen bowl emoji',
        'hug': 'a hugging emoji',
        'cat_scream': 'a cat screaming emoji',
        'wow': 'a "wow" emoji',
        'Lapsmirk': 'a smirking emoji'
    };
    
    const names = emojiObjects.map(emoji => emojiMap[emoji.name] || 'an emoji').join(' and ');
    return names;
}

// Explain why she reacted (her emotional reasoning)
function getReactionReason(messageContent, userProfile) {
    const content = messageContent.toLowerCase();
    const isMainUser = userProfile.user_id === MAIN_USER_ID;
    const trustLevel = userProfile.trust_level;
    
    // Love messages
    if (content.match(/\b(love you|love u|ily|i love)\b/i)) {
        if (isMainUser) {
            return "you felt overwhelmed with love and couldn't help expressing it";
        } else if (trustLevel >= 7) {
            return "you felt flustered and shy - that's really sweet of them to say";
        } else if (trustLevel >= 4) {
            return "you felt confused and awkward - you don't know them that well";
        } else {
            return "you felt very confused and uncomfortable - you barely know them";
        }
    }
    
    // Compliments
    if (content.match(/\b(cute|adorable|sweet|pretty|beautiful)\b/i)) {
        if (isMainUser) {
            return "you felt shy and flustered from the compliment";
        } else if (trustLevel >= 6) {
            return "you felt appreciative but a bit shy";
        } else {
            return "you felt awkward - you don't know them well enough for that";
        }
    }
    
    // Greetings
    if (content.match(/^(hi|hey|hello|good morning|good night)\b/i)) {
        if (isMainUser) {
            return "you felt excited and happy to see them";
        } else {
            return "you felt friendly and happy to greet them";
        }
    }
    
    // Good news
    if (content.match(/\b(won|passed|succeeded|yay|amazing)\b/i)) {
        return "you felt genuinely happy and excited for them";
    }
    
    // Funny
    if (content.match(/\b(haha|lol|lmao|funny)\b/i)) {
        return "you found it amusing and wanted to laugh along";
    }
    
    // Sad
    if (content.match(/\b(tired|sad|depressed|rough day)\b/i)) {
        if (isMainUser || trustLevel >= 6) {
            return "you felt concerned and wanted to show you care";
        } else {
            return "you felt sympathetic";
        }
    }
    
    // Sleep
    if (content.match(/\b(sleep|goodnight|gn)\b/i)) {
        return "you felt caring and wanted to wish them well";
    }
    
    // Food
    if (content.match(/\b(ramen|food)\b/i)) {
        return "you got excited about food";
    }
    
    // Default
    return "you had an emotional response to their message";
}

// Emoji reaction logic - EMOTION-BASED reactions (how she genuinely feels)
// Returns emoji identifiers in the format Discord.js expects
function getReactionEmojis(messageContent, userProfile, currentActivity) {
    const content = messageContent.toLowerCase();
    const reactions = [];
    const trustLevel = userProfile.trust_level;
    const isMainUser = userProfile.user_id === MAIN_USER_ID;

    // 15% chance to react at all (makes reactions more special!)
    // Exception: Always react to "I love you" - her emotions are strong here!
    const isLoveMessage = content.match(/\b(love you|love u|ily|i love)\b/i);
    const randomValue = Math.random();
    const shouldReact = isLoveMessage || randomValue < 0.15;

    console.log(`   🎲 Reaction chance roll: ${randomValue.toFixed(2)} (threshold: 0.15) - ${shouldReact ? 'WILL REACT' : 'NO REACTION'}`);

    if (!shouldReact) {
        return []; // No reaction this time!
    }
    
    // For tracking what emojis were used (for explanation)
    // Format: { id: 'emoji_id', name: 'emoji_name', animated: boolean }
    
    // === LOVE EXPRESSIONS - Emotion depends on WHO said it ===
    if (content.match(/\b(love you|love u|ily|i love|❤️|💕|♥)/i)) {
        if (isMainUser) {
            // Dan saying "I love you" - She's deeply in love, overwhelmed with emotion!
            reactions.push({ id: '1437073036351246446', name: 'cat_love', animated: false });
            reactions.push({ id: '1437072976259321916', name: 'kanna_heart', animated: false });
            return reactions;
        } else if (trustLevel >= 7) {
            // Close friend - Flustered! Sweet but awkward
            reactions.push({ id: '1437072996228530176', name: 'cute_shy', animated: true });
            return reactions;
        } else if (trustLevel >= 4) {
            // Acquaintance - Confused and awkward
            reactions.push({ id: '1437073003773952011', name: 'pikachu_thinking', animated: false });
            return reactions;
        } else {
            // Stranger - Very confused/uncomfortable
            reactions.push({ id: '1437072979132551278', name: 'cat_scream', animated: false });
            return reactions;
        }
    }
    
    // === COMPLIMENTS TO HER - Emotion depends on relationship ===
    if (content.match(/\b(you'?re (so )?(cute|adorable|sweet|pretty|beautiful)|good (girl|bot)|best (girl|bot))\b/i)) {
        if (isMainUser) {
            reactions.push({ id: '1437072996228530176', name: 'cute_shy', animated: true });
        } else if (trustLevel >= 6) {
            reactions.push({ id: '1437072996228530176', name: 'cute_shy', animated: true });
        } else {
            reactions.push({ id: '1437073003773952011', name: 'pikachu_thinking', animated: false });
        }
        return reactions;
    }

    // === GREETINGS - Emotion: Happy to see them! ===
    if (content.match(/^(hi|hey|hello|good morning|good night|gm|gn)\b/i)) {
        if (isMainUser) {
            reactions.push({ id: '1437073029547950110', name: 'cat_wave', animated: false });
            if (Math.random() < 0.3) reactions.push({ id: '1437073036351246446', name: 'cat_love', animated: false });
        } else if (trustLevel >= 5) {
            reactions.push({ id: '1437073029547950110', name: 'cat_wave', animated: false });
        }
        return reactions;
    }

    // === EXCITEMENT/GOOD NEWS - Emotion: Genuinely happy for them! ===
    if (content.match(/\b(won|passed|got an? a|succeeded|yes!|yay|amazing|awesome|great news)\b/i)) {
        reactions.push({ id: '1437072973403131904', name: 'clappinggg', animated: true });
        if (isMainUser) {
            reactions.push({ id: '1437073036351246446', name: 'cat_love', animated: false });
        }
        return reactions;
    }

    // === FUNNY/LAUGHING - Emotion: Amused and laughing along! ===
    if (content.match(/\b(haha|lol|lmao|rofl|funny|hilarious|😂|🤣)\b/i)) {
        reactions.push({ id: '1437073020647903272', name: 'cat_laugh', animated: false });
        return reactions;
    }
    
    // === SAD/TIRED - Emotion: Empathy and concern ===
    if (content.match(/\b(tired|exhausted|sad|depressed|rough day|bad day|crying|😢|😭)\b/i)) {
        if (isMainUser || trustLevel >= 6) {
            reactions.push({ id: '1437073000678559835', name: 'cute_pleading', animated: false });
            if (isMainUser) reactions.push({ id: '🫂', name: 'hug', animated: false }); // built-in
        } else if (trustLevel >= 4) {
            reactions.push({ id: '1437073000678559835', name: 'cute_pleading', animated: false });
        }
        return reactions;
    }

    // === SLEEP/GOODNIGHT - Emotion: Sleepy empathy or caring ===
    if (content.match(/\b(sleep|sleeping|sleepy|goodnight|gn|bed|tired)\b/i)) {
        if (currentActivity.type === 'sleep' || currentActivity.activity.includes('sleep')) {
            reactions.push({ id: '1437072984203595949', name: 'sleepinggg', animated: false });
        } else if (isMainUser) {
            reactions.push({ id: '1437072984203595949', name: 'sleepinggg', animated: false });
            reactions.push({ id: '1437073036351246446', name: 'cat_love', animated: false });
        } else if (trustLevel >= 6) {
            reactions.push({ id: '1437072984203595949', name: 'sleepinggg', animated: false });
        }
        return reactions;
    }

    // === QUESTIONS - Emotion: Curious and thinking ===
    if (content.includes('?') && !reactions.length) {
        reactions.push({ id: '1437073003773952011', name: 'pikachu_thinking', animated: false });
        return reactions;
    }
    
    // === AGREEMENT - Emotion: Agrees enthusiastically! ===
    if (content.match(/\b(right|correct|true|exactly|agree|yeah|yep|facts)\b/i)) {
        reactions.push({ id: '1437073009256173699', name: 'thats_true', animated: false });
        return reactions;
    }
    
    // === FOOD MENTIONS - Emotion: Excited about food! ===
    if (content.match(/\b(ramen|food|eat|eating|hungry|lunch|dinner|breakfast|delicious)\b/i)) {
        reactions.push({ id: '🍜', name: 'ramen', animated: false }); // built-in
        if (content.includes('ramen')) {
            reactions.push({ id: '1437073033037746188', name: 'cat_sparkly_eyes', animated: false });
        }
        return reactions;
    }

    // === SURPRISE/SHOCK - Emotion: Genuinely surprised! ===
    if (content.match(/\b(what|omg|wtf|no way|seriously|really\?!|wow)\b/i)) {
        if (content.match(/\b(wtf|what the)\b/i)) {
            reactions.push({ id: '1437072979132551278', name: 'cat_scream', animated: false });
        } else {
            reactions.push({ id: '1437072981909311542', name: 'cat_woah', animated: false });
        }
        return reactions;
    }

    // === PLAYFUL/TEASING - Emotion: Playful mood! ===
    if (content.match(/\b(hehe|tease|teasing|silly|goofy)\b/i)) {
        reactions.push({ id: '1437072989974691982', name: 'lapras_smirk', animated: false });
        return reactions;
    }
    
    // Limit to 2 reactions max
    return reactions.slice(0, 2);
}

// Natural variations for queue messages
const queueVariations = {
    wait: [
        "Wait {mention}, I'll get back to you shortly after I finish talking to {current}!",
        "Holdon {mention}, let me finish responding to {current} first ^^",
        "One sec {mention}! I'm still replying to {current}~",
        "{mention} wait a moment! I'm in the middle of talking to {current} hehe",
        "Ah {mention}, gimme a sec! Still chatting with {current} ^^",
        "Hold on {mention}~ let me finish with {current} first!",
        "{mention} patience! ^^ I'm still responding to {current}"
    ],
    backTo: [
        "Okay, back to you {mention}...",
        "Alright {mention}, what were you saying? ^^",
        "Okay {mention}! Now about what you said~",
        "{mention}! Sorry for the wait ^^",
        "Back to you {mention}~ so...",
        "Okay {mention}, you were saying?",
        "{mention}~ okay what did you want to tell me? ^^"
    ]
};

function getQueueMessage(type, mentionUser, currentUser) {
    const variations = queueVariations[type];
    const template = variations[Math.floor(Math.random() * variations.length)];
    return template
        .replace(/{mention}/g, `<@${mentionUser}>`)
        .replace(/{current}/g, `<@${currentUser}>`);
}

// Proactive messaging tracking
let lastMajorActivityType = null;
let lastProactiveMessageTime = 0;
let lastProactiveCheck = null; // Track last activity we checked
let lastMessageTimes = {}; // Track last message time per user

// Autonomous messaging system
let proactiveMessageCount = 0;
let proactiveMessageDate = new Date().toDateString();
let currentMood = 'neutral'; // Misuki's current emotional state
const MAX_DAILY_PROACTIVE_MESSAGES = 8;
const SPONTANEOUS_CHECK_INTERVAL = 15 * 60 * 1000; // 15 minutes
const MIN_MESSAGE_GAP = 30 * 60 * 1000; // 30 minutes

async function connectDB() {
    // Use connection pool instead of single connection
    // This automatically handles reconnections and prevents timeout errors!
    db = mysql.createPool({
        host: 'localhost',
        user: 'root',
        password: '',
        database: 'misuki_companion',
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0,
        enableKeepAlive: true,
        keepAliveInitialDelay: 0
    });
    console.log('✅ Connected to MySQL database pool!');
}

// =========================================
// USER PROFILE & RELATIONSHIP MANAGEMENT
// =========================================

async function getUserProfile(discordId, username) {
    const [rows] = await db.execute(
        'SELECT * FROM users WHERE discord_id = ?',
        [discordId]
    );
    
    if (rows.length > 0) {
        // Update last interaction and message count
        await db.execute(
            `UPDATE users 
             SET last_interaction = NOW(), total_messages = total_messages + 1 
             WHERE discord_id = ?`,
            [discordId]
        );
        return rows[0];
    } else {
        // New user - create profile with default trust level 1
        const isMainUser = discordId === MAIN_USER_ID;
        const trustLevel = isMainUser ? 10 : 1;
        const relationshipNotes = isMainUser ? 'My boyfriend Dan ❤️' : 'Just met';
        const userSummary = isMainUser ? null : 'New person - getting to know them';
        
        await db.execute(
            `INSERT INTO users
             (discord_id, username, display_name, trust_level, relationship_notes, user_summary, total_messages, positive_interactions)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0)`,
            [discordId, username, username, trustLevel, relationshipNotes, userSummary]
        );
        
        const [newRows] = await db.execute(
            'SELECT * FROM users WHERE discord_id = ?',
            [discordId]
        );
        return newRows[0];
    }
}

async function getOtherUsers(currentUserId, limit = 5) {
    const [rows] = await db.execute(
        `SELECT user_id, discord_id, username, display_name, nickname, trust_level, 
                total_messages, relationship_notes
         FROM users 
         WHERE discord_id != ? AND total_messages > 0
         ORDER BY last_interaction DESC 
         LIMIT ?`,
        [currentUserId, limit]
    );
    return rows;
}

// Get conversation snippets from other users (for Dan to see what others talked about)
async function getOtherUsersConversations(currentUserId, limit = 3) {
    const otherUsers = await getOtherUsers(currentUserId, 5);
    
    const conversationSnippets = [];
    
    for (const user of otherUsers) {
        const [rows] = await db.execute(
            `SELECT user_message, misuki_response, timestamp 
             FROM conversations 
             WHERE user_id = ? AND user_message != ''
             ORDER BY timestamp DESC 
             LIMIT ?`,
            [user.user_id, limit]
        );
        
        if (rows.length > 0) {
            const userName = user.nickname || user.display_name || user.username;
            conversationSnippets.push({
                user: userName,
                userId: user.user_id,
                trustLevel: user.trust_level,
                messages: rows.reverse() // oldest first
            });
        }
    }
    
    return conversationSnippets;
}

// Get user's conversation history
async function getConversationHistory(userId, limit = 10) {
    const [rows] = await db.execute(
        `SELECT user_message, misuki_response, timestamp 
         FROM conversations 
         WHERE user_id = ? 
         ORDER BY timestamp DESC 
         LIMIT ?`,
        [userId, limit]
    );
    
    return rows.reverse();
}

// Save conversation to database
async function saveConversation(userId, userMessage, misukiResponse, mood = 'gentle', context = 'dm') {
    await db.execute(
        `INSERT INTO conversations (user_id, user_message, misuki_response, mood, timestamp)
         VALUES (?, ?, ?, ?, NOW())`,
        [userId, userMessage, misukiResponse, mood]
    );
}

// Update user's emotional state
async function updateEmotionalState(userId, emotion) {
    if (emotion === 'neutral') return;

    await db.execute(
        `INSERT INTO emotional_states (user_id, detected_emotion, context, timestamp)
         VALUES (?, ?, '', NOW())`,
        [userId, emotion]
    );
}

// Detect if a message is a positive interaction
function isPositiveInteraction(messageContent) {
    const content = messageContent.toLowerCase();

    // Positive keywords and patterns
    const positivePatterns = [
        /\b(love|like|enjoy|appreciate|thank|thanks|grateful|awesome|amazing|great|wonderful|fantastic)\b/i,
        /\b(cute|adorable|sweet|pretty|beautiful|lovely|nice|kind)\b/i,
        /\b(good|best|perfect|excellent|brilliant|impressive)\b/i,
        /\b(happy|glad|pleased|excited|delighted)\b/i,
        /\b(help|support|care|understand|thoughtful)\b/i,
        /❤️|💕|💖|💗|💝|💓|💞|🥰|😊|😍|🤗|👍|✨/
    ];

    return positivePatterns.some(pattern => pattern.test(content));
}

// Update trust level based on positive interactions
async function updateTrustLevel(userId, userMessage, isMainUser) {
    // Don't update trust for main user (Dan) - he's already at max
    if (isMainUser) return;

    // Check if this is a positive interaction
    if (!isPositiveInteraction(userMessage)) return;

    try {
        // Increment positive_interactions counter
        await db.execute(
            'UPDATE users SET positive_interactions = positive_interactions + 1 WHERE user_id = ?',
            [userId]
        );

        // Get current stats
        const [rows] = await db.execute(
            'SELECT positive_interactions, trust_level FROM users WHERE user_id = ?',
            [userId]
        );

        if (rows.length === 0) return;

        const { positive_interactions, trust_level } = rows[0];

        // Every 10 positive interactions = +1 trust level (max 5 for non-main users)
        const newTrustLevel = Math.min(5, Math.floor(positive_interactions / 10) + 1);

        // Update trust level if it increased
        if (newTrustLevel > trust_level) {
            await db.execute(
                'UPDATE users SET trust_level = ? WHERE user_id = ?',
                [newTrustLevel, userId]
            );
            console.log(`   💖 Trust level increased to ${newTrustLevel} for user ${userId}`);
        }

    } catch (error) {
        console.error('Error updating trust level:', error.message);
    }
}

// =========================================
// PROACTIVE MESSAGING SYSTEM
// =========================================

// Get last message time with Dan (either direction)
async function getLastMessageTime() {
    try {
        const [danProfile] = await db.execute(
            'SELECT user_id FROM users WHERE discord_id = ?',
            [MAIN_USER_ID]
        );
        
        if (danProfile.length === 0) return 0;
        
        const [rows] = await db.execute(
            'SELECT MAX(timestamp) as last_time FROM conversations WHERE user_id = ?',
            [danProfile[0].user_id]
        );
        
        if (rows.length > 0 && rows[0].last_time) {
            return new Date(rows[0].last_time).getTime();
        }
        return 0;
    } catch (error) {
        console.error('Error getting last message time:', error);
        return Date.now(); // Fail safe - pretend we just messaged
    }
}

// Check if we should send a proactive message based on activity transition
async function checkProactiveMessage() {
    const currentActivity = getMisukiCurrentActivity();
    const currentType = currentActivity.type;
    
    // Only check if activity type has changed
    if (currentType === lastMajorActivityType) {
        return;
    }
    
    // Define meaningful transitions with probabilities
    const transitions = {
        'free': { probability: 0.50, reasons: ['finished what i was doing', 'have some free time now', 'nothing much going on'] },
        'personal_waking': { probability: 0.75, reasons: ['just woke up', 'morning!', 'good morning'] },
        'personal_arriving_home': { probability: 0.60, reasons: ['finally home', 'just got home', 'back from uni'] }
    };
    
    // Determine if this is a meaningful transition
    let transitionKey = null;
    let transitionReasons = [];
    let probability = 0;
    
    // Check for waking up (sleep → personal in early morning)
    if (lastMajorActivityType === 'sleep' && currentType === 'personal') {
        const now = new Date();
        const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
        const hour = saitamaTime.getHours();
        
        if (hour >= 5 && hour <= 8) {
            transitionKey = 'personal_waking';
            transitionReasons = transitions[transitionKey].reasons;
            probability = transitions[transitionKey].probability;
        }
    }
    
    // Check for arriving home (commute → personal in afternoon/evening)
    else if (lastMajorActivityType === 'commute' && currentType === 'personal') {
        const now = new Date();
        const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
        const hour = saitamaTime.getHours();
        
        if (hour >= 15 && hour <= 18) {
            transitionKey = 'personal_arriving_home';
            transitionReasons = transitions[transitionKey].reasons;
            probability = transitions[transitionKey].probability;
        }
    }
    
    // Check for free time transitions
    else if (currentType === 'free' && ['studying', 'class', 'lab', 'university'].includes(lastMajorActivityType)) {
        transitionKey = 'free';
        transitionReasons = transitions[transitionKey].reasons;
        probability = transitions[transitionKey].probability;
    }
    
    // Update last activity type for next check
    lastMajorActivityType = currentType;
    
    // If no meaningful transition detected, skip
    if (!transitionKey) {
        return;
    }
    
    // Check time constraint: must be 30 minutes since last message
    const lastMessageTime = await getLastMessageTime();
    const timeSinceLastMessage = Date.now() - lastMessageTime;
    const thirtyMinutes = 30 * 60 * 1000;
    
    if (timeSinceLastMessage < thirtyMinutes) {
        console.log(`   ⏰ Proactive message skipped: Only ${Math.round(timeSinceLastMessage / 60000)} minutes since last message`);
        return;
    }
    
    // Check time since last proactive message (prevent spam)
    const timeSinceLastProactive = Date.now() - lastProactiveMessageTime;
    if (timeSinceLastProactive < thirtyMinutes) {
        console.log(`   ⏰ Proactive message skipped: Only ${Math.round(timeSinceLastProactive / 60000)} minutes since last proactive message`);
        return;
    }
    
    // Roll the dice!
    const roll = Math.random();
    if (roll > probability) {
        console.log(`   🎲 Proactive message skipped: Rolled ${(roll * 100).toFixed(0)}% (needed <${(probability * 100).toFixed(0)}%)`);
        return;
    }
    
    console.log(`   ✅ Proactive message triggered! Transition: ${transitionKey}, Roll: ${(roll * 100).toFixed(0)}% (needed <${(probability * 100).toFixed(0)}%)`);
    
    // Send proactive message!
    await sendProactiveMessage(transitionKey, transitionReasons, currentActivity);
}

// Send a proactive message to Dan
async function sendProactiveMessage(transitionKey, reasons, currentActivity) {
    try {
        // Get Dan's user object
        const dan = await client.users.fetch(MAIN_USER_ID);
        if (!dan) {
            console.log('   ❌ Could not fetch Dan\'s user object');
            return;
        }
        
        // Get Dan's profile from database
        const userProfile = await getUserProfile(MAIN_USER_ID, 'danthevegetable');
        
        // Get conversation history
        const history = await getConversationHistory(userProfile.user_id, 10);
        
        // Get Dan's current activity
        const danActivities = await getDanActivity();
        const danActivityContext = formatDanActivity(danActivities);
        
        // Build context for the proactive message
        const now = new Date();
        const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
        const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
        
        const misukiTimeStr = saitamaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        const danTimeStr = jakartaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        
        let context = '';
        history.forEach(conv => {
            if (conv.user_message) {
                context += `Dan: ${conv.user_message}\n`;
            }
            if (conv.misuki_response) {
                context += `Misuki: ${conv.misuki_response}\n`;
            }
        });
        
        const currentStatus = getCurrentStatus();
        
        const prompt = `You are Misuki Tanaka, Dan's girlfriend. You're initiating a conversation with Dan on Discord.

=== CONTEXT ===
You just transitioned to: ${currentActivity.activity} ${currentActivity.emoji}
Your current Discord status: "${currentStatus}"
Transition reason: ${transitionKey}

Your time in Japan: ${misukiTimeStr}
Dan's time in Indonesia: ${danTimeStr}

${danActivityContext}

=== RECENT CONVERSATION HISTORY ===
${context || '(No recent conversation)'}

=== YOUR TASK ===
Send Dan a natural, spontaneous message because you just ${reasons[Math.floor(Math.random() * reasons.length)]}!

Guidelines:
- Keep it SHORT (1-2 sentences, like a real text)
- Be natural and sweet
- Match the time of day and context
- Reference what you're doing if relevant
- If Dan is doing something (Spotify/gaming), you can comment on it
- Use emoticons like ^^ (˶ᵔ ᵕ ᵔ˶) >.<
- NO asterisks (*) or actions
- Sound spontaneous, not scripted

Examples:
"just got home from uni... so tireddd (˶ᵕ ᵕ˶)"
"morning! ^^ did you sleep well?"
"finally done with homework... my brain hurts T_T"
"you're still up? hehe"

Your message:`;

        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 150,
            messages: [{ role: 'user', content: prompt }],
            temperature: 1.0
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            },
            timeout: 30000
        });
        
        let message = response.data.content[0].text.trim();
        
        // Clean up the message
        message = message.replace(/\*[^*]+\*/g, '');
        message = message.replace(/^["']|["']$/g, '');
        message = message.replace(/\s+/g, ' ').trim();
        
        // Send the DM
        await dan.send(message);
        
        // Save to conversation history
        await saveConversation(userProfile.user_id, '', message, 'proactive');
        
        // Update last proactive message time
        lastProactiveMessageTime = Date.now();
        
        console.log(`   💌 Proactive message sent: "${message}"`);

    } catch (error) {
        console.error('   ❌ Error sending proactive message:', error.message);
    }
}

// Calculate desire to send a spontaneous message based on multiple factors
function calculateMessageDesire(currentActivity, danPresence, timeSinceLastMessage) {
    let score = 0;
    const reasons = [];

    // 1. Time since last message factor
    const hoursSinceLastMessage = timeSinceLastMessage / (1000 * 60 * 60);
    if (hoursSinceLastMessage < 1) {
        score -= 50;
        reasons.push(`Too recent (${Math.round(hoursSinceLastMessage * 60)} min ago): -50`);
    } else if (hoursSinceLastMessage < 3) {
        score += 0;
        reasons.push(`Recent (${Math.round(hoursSinceLastMessage)} hr ago): 0`);
    } else if (hoursSinceLastMessage < 6) {
        score += 20;
        reasons.push(`Moderate gap (${Math.round(hoursSinceLastMessage)} hr ago): +20`);
    } else if (hoursSinceLastMessage < 12) {
        score += 40;
        reasons.push(`Long gap (${Math.round(hoursSinceLastMessage)} hr ago): +40`);
    } else {
        score += 60;
        reasons.push(`Very long gap (${Math.round(hoursSinceLastMessage)} hr ago): +60`);
    }

    // 2. Activity type factor
    const activityType = currentActivity.type;
    if (activityType === 'free') {
        score += 30;
        reasons.push('Free time (bored/chatty): +30');
    } else if (activityType === 'studying') {
        score += 15;
        reasons.push('Studying (procrastination urge): +15');
    } else if (activityType === 'sleep' || activityType === 'class' || activityType === 'lab') {
        score -= 100;
        reasons.push(`${activityType} (shouldn't disturb): -100`);
    } else if (activityType === 'personal') {
        // Check specific personal activities
        const activity = currentActivity.activity.toLowerCase();
        if (activity.includes('bed') || activity.includes('scrolling phone')) {
            score += 25;
            reasons.push('In bed with phone (cozy chat time): +25');
        } else if (activity.includes('shower')) {
            score -= 100;
            reasons.push('Showering (unavailable): -100');
        } else {
            score += 10;
            reasons.push('Personal time: +10');
        }
    } else if (activityType === 'break') {
        score += 20;
        reasons.push('On break (good timing): +20');
    }

    // 3. Dan's Discord status factor
    if (danPresence) {
        const status = danPresence.status;
        if (status === 'online') {
            score += 20;
            reasons.push('Dan is online: +20');
        } else if (status === 'idle') {
            score += 10;
            reasons.push('Dan is idle: +10');
        } else if (status === 'dnd') {
            score -= 50;
            reasons.push('Dan is DND (respect privacy): -50');
        } else {
            score += 0;
            reasons.push('Dan is offline: 0');
        }

        // Check if Dan is actively doing something (gaming, Spotify, etc.)
        if (danPresence.activities && danPresence.activities.length > 0) {
            const hasGame = danPresence.activities.some(a => a.type === 0); // Playing
            const hasSpotify = danPresence.activities.some(a => a.name === 'Spotify');
            if (hasGame) {
                score += 5;
                reasons.push('Dan is gaming (can still chat): +5');
            }
            if (hasSpotify) {
                score += 5;
                reasons.push('Dan is listening to music: +5');
            }
        }
    }

    // 4. Time of day alignment (Dan's timezone - Indonesia)
    const now = new Date();
    const indonesiaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
    const hour = indonesiaTime.getHours();

    if (hour >= 9 && hour < 22) {
        score += 15;
        reasons.push('Dan\'s daytime (9am-10pm): +15');
    } else if (hour >= 1 && hour < 7) {
        score -= 30;
        reasons.push('Dan\'s sleep hours (1am-7am): -30');
    } else {
        score += 5;
        reasons.push('Dan\'s evening/late: +5');
    }

    // 5. Random spontaneity factor
    const randomFactor = Math.floor(Math.random() * 61) - 20; // -20 to +40
    score += randomFactor;
    reasons.push(`Random factor: ${randomFactor > 0 ? '+' : ''}${randomFactor}`);

    // 6. Mood factor
    if (currentMood === 'lonely' || currentMood === 'missing') {
        score += 20;
        reasons.push(`Mood (${currentMood}): +20`);
    } else if (currentMood === 'excited' || currentMood === 'happy') {
        score += 10;
        reasons.push(`Mood (${currentMood}): +10`);
    }

    // 7. Trust level bonus (always max with Dan)
    score += 10;
    reasons.push('Max trust with Dan: +10');

    return { score, reasons };
}

// Check if Misuki should spontaneously message Dan
async function checkSpontaneousMessage() {
    try {
        // Reset daily counter at midnight Japan time
        const now = new Date();
        const japanTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
        const currentDate = japanTime.toDateString();

        if (currentDate !== proactiveMessageDate) {
            proactiveMessageCount = 0;
            proactiveMessageDate = currentDate;
            console.log('🌅 New day - reset proactive message counter');
        }

        // Check daily limit
        if (proactiveMessageCount >= MAX_DAILY_PROACTIVE_MESSAGES) {
            console.log('📊 Daily proactive message limit reached. Skipping check.');
            return;
        }

        // Check minimum time gap since last proactive message
        const timeSinceLastProactive = Date.now() - lastProactiveMessageTime;
        if (timeSinceLastProactive < MIN_MESSAGE_GAP) {
            return; // Too soon, skip silently
        }

        // Get current activity
        const currentActivity = getMisukiCurrentActivity();

        // Get Dan's presence/activity
        let danPresence = null;
        try {
            const dan = await client.users.fetch(MAIN_USER_ID);
            // Try to find Dan's presence from guilds
            for (const guild of client.guilds.cache.values()) {
                const member = guild.members.cache.get(MAIN_USER_ID);
                if (member && member.presence) {
                    danPresence = member.presence;
                    break;
                }
            }
        } catch (error) {
            console.log('⚠️  Could not fetch Dan\'s presence');
        }

        // Get time since last message to Dan
        let lastMessageTime = lastMessageTimes[MAIN_USER_ID];

        // If not tracked in memory, try to get from database
        if (!lastMessageTime) {
            try {
                const userProfile = await getUserProfile(MAIN_USER_ID, 'Dan');
                if (userProfile && userProfile.last_interaction) {
                    lastMessageTime = new Date(userProfile.last_interaction).getTime();
                } else {
                    // Default to 1 hour ago if no data (prevents huge numbers)
                    lastMessageTime = Date.now() - (1 * 60 * 60 * 1000);
                }
            } catch (error) {
                lastMessageTime = Date.now() - (1 * 60 * 60 * 1000);
            }
        }

        const timeSinceLastMessage = Date.now() - lastMessageTime;

        // Calculate desire score
        const { score, reasons } = calculateMessageDesire(currentActivity, danPresence, timeSinceLastMessage);

        console.log(`💭 Spontaneous message check - Score: ${score}/50`);
        reasons.forEach(reason => console.log(`   ${reason}`));

        // Decision threshold
        if (score >= 50) {
            console.log('✅ Score >= 50! Initiating spontaneous message...');

            // Determine message type based on context
            let messageType = 'casual';
            const hoursSinceLastMessage = timeSinceLastMessage / (1000 * 60 * 60);

            if (hoursSinceLastMessage > 12) {
                messageType = 'missing';
            } else if (currentActivity.activity.toLowerCase().includes('bed') || currentActivity.activity.toLowerCase().includes('scrolling phone')) {
                const hour = japanTime.getHours();
                if (hour >= 22 || hour <= 2) {
                    messageType = 'bedtime';
                } else {
                    messageType = 'cozy';
                }
            } else if (currentActivity.type === 'studying') {
                messageType = 'procrastination';
            } else if (currentActivity.type === 'free') {
                const randomTypes = ['bored', 'thinking', 'random_question', 'excited'];
                messageType = randomTypes[Math.floor(Math.random() * randomTypes.length)];
            }

            // Decide whether to DM or post in server
            // Factors: privacy of message type, time of day, randomness
            const shouldUseDM = decideDMvsServer(messageType, currentActivity, japanTime);

            // Send spontaneous message
            await sendSpontaneousMessage(messageType, currentActivity, danPresence, shouldUseDM);

            // Increment counter
            proactiveMessageCount++;
            console.log(`📊 Proactive messages today: ${proactiveMessageCount}/${MAX_DAILY_PROACTIVE_MESSAGES}`);
        } else {
            console.log('❌ Score < 50. Not messaging yet.');
        }

    } catch (error) {
        console.error('❌ Error in checkSpontaneousMessage:', error);
    }
}

// Decide whether to use DM or server channel
function decideDMvsServer(messageType, currentActivity, japanTime) {
    // Private/intimate messages always go to DM
    const privateMessageTypes = ['missing', 'bedtime', 'cozy'];
    if (privateMessageTypes.includes(messageType)) {
        console.log(`   💌 Choosing DM (private message type: ${messageType})`);
        return true;
    }

    // Late night/early morning = DM (don't spam server when others might be asleep)
    const hour = japanTime.getHours();
    if (hour >= 22 || hour <= 7) {
        console.log(`   💌 Choosing DM (late/early hour: ${hour})`);
        return true;
    }

    // During class/studying = more likely to use server (quick public message)
    if (currentActivity.type === 'studying' || currentActivity.type === 'class') {
        const random = Math.random();
        if (random < 0.6) { // 60% chance server
            console.log(`   🌐 Choosing SERVER (studying/class, casual share)`);
            return false;
        } else {
            console.log(`   💌 Choosing DM (studying but wants private chat)`);
            return true;
        }
    }

    // Free time = random choice with slight preference for server
    if (currentActivity.type === 'free') {
        const random = Math.random();
        if (random < 0.55) { // 55% chance server
            console.log(`   🌐 Choosing SERVER (free time, casual)`);
            return false;
        } else {
            console.log(`   💌 Choosing DM (free time, private)`);
            return true;
        }
    }

    // Default to 50/50
    const random = Math.random();
    if (random < 0.5) {
        console.log(`   🌐 Choosing SERVER (random choice)`);
        return false;
    } else {
        console.log(`   💌 Choosing DM (random choice)`);
        return true;
    }
}

// Send spontaneous message with variety based on type
async function sendSpontaneousMessage(messageType, currentActivity, danPresence, useDM = true) {
    try {
        // Fetch Dan and server channel
        const dan = await client.users.fetch(MAIN_USER_ID);
        const serverChannel = useDM ? null : await client.channels.fetch(ALLOWED_CHANNEL_ID);

        // Get conversation history
        const userProfile = await getUserProfile(MAIN_USER_ID, dan.username);
        const history = await getConversationHistory(userProfile.user_id, 10);

        // Build context about Dan's current activity
        let danActivityContext = '';
        if (danPresence && danPresence.activities && danPresence.activities.length > 0) {
            const activities = danPresence.activities;
            const game = activities.find(a => a.type === 0);
            const spotify = activities.find(a => a.name === 'Spotify');
            const custom = activities.find(a => a.type === 4);

            if (game) {
                danActivityContext = `You notice Dan is playing ${game.name} right now. `;
            } else if (spotify && spotify.details) {
                danActivityContext = `You see Dan is listening to "${spotify.details}" by ${spotify.state} on Spotify. `;
            } else if (custom && custom.state) {
                danActivityContext = `Dan's status says: "${custom.state}". `;
            }
        }

        // Get Indonesia time for context
        const now = new Date();
        const indonesiaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
        const danTimeString = indonesiaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        // Build recent conversation context
        let conversationContext = '';
        if (history.length > 0) {
            const recentMessages = history.slice(-3);
            conversationContext = '\n\nRecent conversation (for context only - this was EARLIER, not happening now):\n';
            recentMessages.forEach(msg => {
                if (msg.user_message && !msg.user_message.includes('[Misuki initiated')) {
                    conversationContext += `Dan: ${msg.user_message}\n`;
                }
                if (msg.misuki_response) conversationContext += `You: ${msg.misuki_response}\n`;
            });
            conversationContext += '\n(That conversation is now over. You\'re starting a NEW spontaneous message RIGHT NOW.)';
        }

        // Build personality prompt based on message type
        let personalityPrompt = '';
        switch (messageType) {
            case 'missing':
                personalityPrompt = 'You\'re feeling lonely and miss him. You haven\'t talked in a while and you want to reach out. Be vulnerable but not overwhelming. Show you care.';
                break;
            case 'bedtime':
                personalityPrompt = 'It\'s late and you\'re in bed about to sleep. You want to say goodnight and let him know you\'re thinking of him. Be sweet and cozy.';
                break;
            case 'procrastination':
                personalityPrompt = 'You\'re supposed to be studying but you can\'t focus. You want to procrastinate by texting him. Be playful about avoiding homework.';
                break;
            case 'bored':
                personalityPrompt = 'You\'re bored and want some attention. You\'re looking for something fun to talk about or do. Be casual and a bit needy.';
                break;
            case 'thinking':
                personalityPrompt = 'You were just thinking about him randomly and felt like reaching out. Be natural and genuine about it.';
                break;
            case 'random_question':
                personalityPrompt = 'You have a random question or thought you want to share. Be curious and conversational.';
                break;
            case 'excited':
                personalityPrompt = 'Something interesting happened or you\'re feeling good and want to share your mood. Be enthusiastic.';
                break;
            case 'cozy':
                personalityPrompt = 'You\'re relaxing comfortably and in a chatty mood. You want some cozy conversation. Be warm and comfortable.';
                break;
            default:
                personalityPrompt = 'You\'re feeling spontaneous and want to chat. Be natural and authentic.';
        }

        // Build context about where she's messaging
        const messagingContext = useDM
            ? 'You are DMing Dan directly (private conversation, just you two).'
            : 'You are posting in the Discord server where Dan and others can see (public message to everyone, but mainly for Dan).';

        const audienceNote = useDM
            ? ''
            : '\n\nIMPORTANT: This is a PUBLIC server message. Others can see it. Keep it casual/light, avoid overly intimate/private things. You can still be playful and yourself, but remember it\'s not just Dan reading.';

        // Create prompt for Claude
        const prompt = `You are Misuki, messaging your boyfriend Dan spontaneously.

CURRENT SITUATION:
- Your current activity: ${currentActivity.activity} (${currentActivity.type})
- Your location: At home in Saitama, Japan
- Time for you: ${new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' })).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} (Japan time)
- Time for Dan: ${danTimeString} (Indonesia time)
- Your mood: ${currentMood}
${danActivityContext}

WHERE YOU'RE MESSAGING: ${messagingContext}

YOUR MOTIVATION: ${personalityPrompt}
${conversationContext}${audienceNote}

IMPORTANT: This is a BRAND NEW spontaneous message you're initiating RIGHT NOW. You're not responding to anything Dan just said - you're reaching out on your own. Don't reference or respond to the old conversation above - that was earlier. This is a fresh start.

Write a SHORT, natural message (1-3 sentences max). Be authentic to your personality:
- Playful, affectionate, genuine
- Use casual texting style (lowercase, emojis like ^^ ><  >.<  ~)
- Don't be overly formal or explain yourself too much
- Show your personality and feelings naturally
- You can mention what YOU'RE doing right now, or ask what Dan is up to NOW
- Do NOT respond to messages from the old conversation - start fresh
${useDM ? '' : '- Since this is public, keep it appropriate for others to see (still be yourself, just not too intimate)'}

Just write the message, nothing else:`;

        // Generate message with Claude
        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 150,
            messages: [{ role: 'user', content: prompt }],
            temperature: 1.0
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            },
            timeout: 30000
        });

        const message = response.data.content[0].text.trim();

        // Send the message to appropriate destination
        if (useDM) {
            await dan.send(message);
            console.log(`   💌 Spontaneous DM sent (${messageType}): "${message}"`);
        } else {
            await serverChannel.send(message);
            console.log(`   🌐 Spontaneous SERVER message sent (${messageType}): "${message}"`);
        }

        // Save to conversation history
        // For spontaneous messages, she initiates (no user message), so we use a marker
        const messageContext = useDM ? 'dm' : 'server';
        await saveConversation(userProfile.user_id, '[Misuki initiated conversation]', message, currentMood, messageContext);

        // Update tracking
        lastProactiveMessageTime = Date.now();
        lastMessageTimes[MAIN_USER_ID] = Date.now();

    } catch (error) {
        console.error('   ❌ Error sending spontaneous message:', error.message);
    }
}

// Generate/update user summary (for non-Dan users to save context)
// To manually update summary for existing users like Mari who have old conversations:
// Just have them send 1-2 messages, then manually set their total_messages to a multiple of 10
// Example SQL: UPDATE users SET total_messages = 10 WHERE username = 'Mari';
async function updateUserSummary(userId, userName) {
    try {
        // Get last 20 messages to analyze
        const history = await getConversationHistory(userId, 20);
        
        if (history.length < 5) {
            // Not enough history yet
            return;
        }
        
        // Build conversation text
        let conversationText = '';
        history.forEach(conv => {
            if (conv.user_message) {
                conversationText += `${userName}: ${conv.user_message}\n`;
            }
            if (conv.misuki_response) {
                conversationText += `Misuki: ${conv.misuki_response}\n`;
            }
        });
        
        // Ask Claude to create a summary
        const summaryPrompt = `Based on these conversations between Misuki and ${userName}, create a concise summary (150-300 characters) from Misuki's first-person perspective about ${userName}. Write as if you ARE Misuki describing your thoughts about them.

Include:
- Key facts about them (age, interests, location, job/school, etc.)
- Important things they've shared with you
- How you feel about your dynamic with them
- Any ongoing situations or topics

Write naturally in Misuki's voice, as her personal notes/thoughts about ${userName}. Use "they/them" pronouns and speak from your (Misuki's) point of view.

Example style: "They're super nice to me! Never really stopped complimenting me!" or "They seem really interested in anime. We've been chatting about their favorite shows!"

Conversations:
${conversationText}

Create the summary now as Misuki (150-300 characters):`;

        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 150,
            messages: [{ role: 'user', content: summaryPrompt }],
            temperature: 0.7
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            },
            timeout: 30000
        });
        
        const summary = response.data.content[0].text.trim();
        
        // Update database
        await db.execute(
            'UPDATE users SET user_summary = ? WHERE user_id = ?',
            [summary, userId]
        );
        
        console.log(`   📝 Updated summary for ${userName}`);
        
    } catch (error) {
        console.error(`❌ Error updating user summary for ${userName}:`, error.message);
        if (error.response?.data) {
            console.error('   API Error details:', JSON.stringify(error.response.data));
        }
    }
}

// Keep showing typing indicator
async function startTyping(channel) {
    await channel.sendTyping();
    
    const typingInterval = setInterval(() => {
        channel.sendTyping().catch(() => clearInterval(typingInterval));
    }, 8000);
    
    return () => clearInterval(typingInterval);
}

// Full weekly schedule (COMPLETE - NO DATA REMOVED)
function getMisukiWeeklySchedule() {
    return {
        monday: [
            { time: '05:30', activity: 'Waking up', emoji: '😴', type: 'personal', outfit: 'sleepwear' },
            { time: '05:35', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal', outfit: 'sleepwear' },
            { time: '05:40', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'sleepwear' },
            { time: '05:45', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '06:00', activity: 'Getting dressed', emoji: '👔', type: 'personal', outfit: 'schoolUniform' },
            { time: '06:10', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '06:15', activity: 'Eating breakfast', emoji: '🍽️', type: 'personal' },
            { time: '06:25', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '06:30', activity: 'Getting ready to leave', emoji: '🎒', type: 'personal' },
            { time: '06:40', activity: 'Walking to train station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '06:50', activity: 'Waiting for train', emoji: '🚉', type: 'commute' },
            { time: '07:00', activity: 'Train ride to university', emoji: '🚃', type: 'commute' },
            { time: '07:20', activity: 'Arrived at university', emoji: '🏫', type: 'commute' },
            { time: '07:25', activity: 'Walking to class building', emoji: '🚶‍♀️', type: 'university' },
            { time: '07:30', activity: 'Waiting in classroom', emoji: '📚', type: 'university' },
            { time: '07:45', activity: 'Organic Chemistry lecture', emoji: '🧪', type: 'class' },
            { time: '09:30', activity: 'Class break', emoji: '☕', type: 'break' },
            { time: '09:45', activity: 'Walking to next class', emoji: '🚶‍♀️', type: 'university' },
            { time: '10:00', activity: 'Physical Chemistry lecture', emoji: '⚗️', type: 'class' },
            { time: '11:45', activity: 'Class ends', emoji: '✅', type: 'university' },
            { time: '12:00', activity: 'Having lunch at campus', emoji: '🍱', type: 'personal' },
            { time: '13:00', activity: 'Chemistry lab session', emoji: '🔬', type: 'lab' },
            { time: '15:30', activity: 'Lab ends', emoji: '✅', type: 'university' },
            { time: '15:45', activity: 'Walking to train station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '16:00', activity: 'Train ride home', emoji: '🚃', type: 'commute' },
            { time: '16:20', activity: 'Walking home from station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '16:30', activity: 'Arriving home', emoji: '🏠', type: 'personal', outfit: 'schoolUniform' },
            { time: '16:45', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal', outfit: 'comfy' },
            { time: '17:00', activity: 'Relaxing and snacking', emoji: '☕', type: 'free', outfit: 'comfy' },
            { time: '18:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '19:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '20:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '20:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '21:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        tuesday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal', outfit: 'pajamas' },
            { time: '07:10', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal', outfit: 'pajamas' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'pajamas' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '07:40', activity: 'Getting dressed casually', emoji: '👕', type: 'personal', outfit: 'casual' },
            { time: '07:50', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '08:00', activity: 'Eating breakfast', emoji: '🍽️', type: 'personal' },
            { time: '08:30', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '08:45', activity: 'Free time at home', emoji: '📱', type: 'free' },
            { time: '10:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal' },
            { time: '12:30', activity: 'Eating lunch', emoji: '🍽️', type: 'personal' },
            { time: '13:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:15', activity: 'Taking a break', emoji: '☕', type: 'break' },
            { time: '14:00', activity: 'Continuing homework', emoji: '✏️', type: 'studying' },
            { time: '16:30', activity: 'Resting', emoji: '😌', type: 'free' },
            { time: '18:00', activity: 'Writing lab report', emoji: '📝', type: 'studying' },
            { time: '19:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '20:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '20:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '21:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        wednesday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal', outfit: 'pajamas' },
            { time: '07:10', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal', outfit: 'pajamas' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'pajamas' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '07:40', activity: 'Getting dressed casually', emoji: '👕', type: 'personal', outfit: 'casual' },
            { time: '07:50', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '08:00', activity: 'Eating breakfast', emoji: '🍽️', type: 'personal' },
            { time: '08:30', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '08:45', activity: 'Free time at home', emoji: '📱', type: 'free' },
            { time: '10:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal' },
            { time: '12:30', activity: 'Eating lunch', emoji: '🍽️', type: 'personal' },
            { time: '13:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:15', activity: 'Taking a break', emoji: '☕', type: 'break' },
            { time: '14:00', activity: 'Continuing homework', emoji: '✏️', type: 'studying' },
            { time: '16:30', activity: 'Free time', emoji: '😌', type: 'free' },
            { time: '18:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '19:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '19:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '20:00', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        thursday: [
            { time: '05:30', activity: 'Waking up', emoji: '😴', type: 'personal', outfit: 'sleepwear' },
            { time: '05:35', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal', outfit: 'sleepwear' },
            { time: '05:40', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'sleepwear' },
            { time: '05:45', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '06:00', activity: 'Getting dressed', emoji: '👔', type: 'personal', outfit: 'schoolUniform' },
            { time: '06:10', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '06:15', activity: 'Eating breakfast', emoji: '🍽️', type: 'personal' },
            { time: '06:25', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '06:30', activity: 'Getting ready to leave', emoji: '🎒', type: 'personal' },
            { time: '06:40', activity: 'Walking to train station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '06:50', activity: 'Waiting for train', emoji: '🚉', type: 'commute' },
            { time: '07:00', activity: 'Train ride to university', emoji: '🚃', type: 'commute' },
            { time: '07:20', activity: 'Arrived at university', emoji: '🏫', type: 'commute' },
            { time: '07:25', activity: 'Walking to class building', emoji: '🚶‍♀️', type: 'university' },
            { time: '07:30', activity: 'Waiting in classroom', emoji: '📚', type: 'university' },
            { time: '07:45', activity: 'Analytical Chemistry lecture', emoji: '⚗️', type: 'class' },
            { time: '09:30', activity: 'Class break', emoji: '☕', type: 'break' },
            { time: '09:45', activity: 'Walking to next class', emoji: '🚶‍♀️', type: 'university' },
            { time: '10:00', activity: 'Biochemistry lecture', emoji: '🧬', type: 'class' },
            { time: '11:45', activity: 'Class ends', emoji: '✅', type: 'university' },
            { time: '12:00', activity: 'Having lunch at campus', emoji: '🍱', type: 'personal' },
            { time: '13:00', activity: 'Free time studying', emoji: '📚', type: 'studying' },
            { time: '15:00', activity: 'Walking to train station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '15:15', activity: 'Train ride home', emoji: '🚃', type: 'commute' },
            { time: '15:35', activity: 'Walking home from station', emoji: '🚶‍♀️', type: 'commute' },
            { time: '15:45', activity: 'Arriving home', emoji: '🏠', type: 'personal', outfit: 'schoolUniform' },
            { time: '16:00', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal', outfit: 'comfy' },
            { time: '16:15', activity: 'Free time relaxing', emoji: '😌', type: 'free', outfit: 'comfy' },
            { time: '18:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '19:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '20:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '20:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '21:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        friday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal', outfit: 'pajamas' },
            { time: '07:10', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal', outfit: 'pajamas' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'pajamas' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '07:40', activity: 'Getting dressed', emoji: '👕', type: 'personal', outfit: 'casual' },
            { time: '07:50', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '08:00', activity: 'Eating breakfast', emoji: '🍽️', type: 'personal' },
            { time: '08:30', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '08:45', activity: 'Free time at home', emoji: '📱', type: 'free' },
            { time: '10:00', activity: 'Doing homework', emoji: '📖', type: 'studying' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal' },
            { time: '12:30', activity: 'Eating lunch', emoji: '🍽️', type: 'personal' },
            { time: '13:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:15', activity: 'Resting', emoji: '😌', type: 'free' },
            { time: '14:00', activity: 'Light homework', emoji: '📖', type: 'studying' },
            { time: '16:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '18:00', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '18:30', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '19:15', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '19:30', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        saturday: [
            { time: '08:00', activity: 'Waking up naturally', emoji: '😴', type: 'personal', outfit: 'pajamas' },
            { time: '08:15', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal', outfit: 'pajamas' },
            { time: '08:30', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'pajamas' },
            { time: '08:35', activity: 'Taking a long shower', emoji: '🚿', type: 'personal', outfit: null },
            { time: '09:00', activity: 'Getting dressed casually', emoji: '👕', type: 'personal', outfit: 'casual' },
            { time: '09:15', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '09:30', activity: 'Eating breakfast leisurely', emoji: '🍽️', type: 'personal' },
            { time: '10:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '10:15', activity: 'Free time at home', emoji: '😌', type: 'free' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal' },
            { time: '12:30', activity: 'Eating lunch', emoji: '🍽️', type: 'personal' },
            { time: '13:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:15', activity: 'Relaxing', emoji: '📱', type: 'free' },
            { time: '15:00', activity: 'Doing some homework', emoji: '📖', type: 'studying' },
            { time: '17:00', activity: 'Free time', emoji: '😌', type: 'free' },
            { time: '18:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '19:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '19:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '20:00', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '23:00', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '23:30', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:45', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ],
        sunday: [
            { time: '07:00', activity: 'Waking up for church', emoji: '😴', type: 'personal', outfit: 'pajamas' },
            { time: '07:10', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal', outfit: 'pajamas' },
            { time: '07:15', activity: 'Preparing the shower', emoji: '🚿', type: 'personal', outfit: 'pajamas' },
            { time: '07:20', activity: 'Showering', emoji: '🚿', type: 'personal', outfit: null },
            { time: '07:35', activity: 'Getting dressed nicely', emoji: '👗', type: 'personal', outfit: 'dress' },
            { time: '07:45', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '07:50', activity: 'Eating breakfast quickly', emoji: '🍽️', type: 'personal' },
            { time: '08:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '08:10', activity: 'Getting ready to leave', emoji: '🎒', type: 'personal' },
            { time: '08:20', activity: 'Walking to church', emoji: '🚶‍♀️', type: 'commute' },
            { time: '08:45', activity: 'Church service', emoji: '⛪', type: 'church' },
            { time: '11:00', activity: 'Church ends', emoji: '✅', type: 'church' },
            { time: '11:15', activity: 'Walking home', emoji: '🚶‍♀️', type: 'commute' },
            { time: '11:40', activity: 'Arriving home', emoji: '🏠', type: 'personal', outfit: 'dress' },
            { time: '11:50', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal', outfit: 'comfy' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal', outfit: 'comfy' },
            { time: '12:30', activity: 'Eating lunch with mom', emoji: '🍽️', type: 'personal' },
            { time: '13:15', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:30', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '15:00', activity: 'Light studying', emoji: '📖', type: 'studying' },
            { time: '17:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '18:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '19:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '19:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '20:00', activity: 'Free time', emoji: '😌', type: 'free' },
            { time: '22:00', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal', outfit: 'pajamas' },
            { time: '22:30', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal', outfit: 'pajamas' },
            { time: '23:00', activity: 'Sleeping', emoji: '😴', type: 'sleep', outfit: 'pajamas' }
        ]
    };
}

// Get Misuki's current activity from schedule
// Fixed version of getMisukiCurrentActivity
function getMisukiCurrentActivity() {
    const now = new Date();
    const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const currentDay = saitamaTime.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTime = saitamaTime.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
    
    const schedule = getMisukiWeeklySchedule();
    const todaySchedule = schedule[currentDay];
    
    if (!todaySchedule) return { activity: 'Free time', emoji: '😌', type: 'free' };
    
    // Find current activity by comparing times
    let currentActivity = todaySchedule[0]; // Default to first activity
    
    for (let i = 0; i < todaySchedule.length; i++) {
        const activityTime = todaySchedule[i].time;
        
        // If current time is greater than or equal to this activity's time
        if (currentTime >= activityTime) {
            currentActivity = todaySchedule[i];
            
            // Check if we should move to next activity
            if (i + 1 < todaySchedule.length) {
                const nextTime = todaySchedule[i + 1].time;
                // If we haven't reached the next activity yet, stay with current
                if (currentTime < nextTime) {
                    break;
                }
            }
        } else {
            // Current time is before this activity, so previous one is correct
            break;
        }
    }
    
    // Handle edge case: if current time is before first activity of the day
    // (e.g., it's 02:00 AM but first activity is at 08:00 AM)
    if (currentTime < todaySchedule[0].time) {
        // Get previous day's last activity
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const currentDayIndex = days.indexOf(currentDay);
        const previousDay = days[currentDayIndex === 0 ? 6 : currentDayIndex - 1];
        const yesterdaySchedule = schedule[previousDay];
        currentActivity = yesterdaySchedule[yesterdaySchedule.length - 1];
    }
    
    return currentActivity;
}

// Get current Discord status (for Misuki's awareness)
function getCurrentStatus() {
    if (statusHistory.length === 0) return null;
    return statusHistory[statusHistory.length - 1];
}

// UPDATE DISCORD STATUS BASED ON SCHEDULE (WITH VARIATIONS!)
function updateDiscordStatus() {
    const activity = getMisukiCurrentActivity();
    const activityType = activity.type;

    // Update outfit based on current activity
    updateCurrentOutfit(activity);

    // Check for proactive messaging opportunity (async, don't wait)
    checkProactiveMessage().catch(err => {
        console.error('Error checking proactive message:', err);
    });
    
    let statusOptions = [];
    let activityTypeDiscord = ActivityType.Custom;
    let statusState = 'online'; // online, idle, dnd, invisible
    
    // Map activity types to Discord status with MULTIPLE VARIATIONS
    switch (activityType) {
        case 'sleep':
            statusOptions = [
                '💤 sleepinggg',
                '😴 zzz...',
                '💤 sleeping~',
                '😴 in dreamland',
                '💤 fast asleep',
                '😴 sleeping peacefully'
            ];
            statusState = 'idle';
            break;
            
        case 'class':
        case 'lab':
            statusOptions = [
                `📚 in ${activity.activity}`,
                `🧪 ${activity.activity} rn`,
                '📖 in class atm',
                '🎓 lecture time!',
                '📚 learning chemistry',
                '🧪 lab work~'
            ];
            statusState = 'dnd'; // Do Not Disturb during class
            break;
            
        case 'studying':
            statusOptions = [
                '📖 studying chemistry',
                '✏️ doing homework',
                '📚 study time!',
                '📝 working on assignments',
                '📖 chemistry homework...',
                '✏️ studying rn',
                '📚 homework grind'
            ];
            statusState = 'dnd';
            break;
            
        case 'commute':
            if (activity.activity.includes('train')) {
                statusOptions = [
                    '🚃 on the train',
                    '🚃 train ride~',
                    '🚃 commuting',
                    '🚃 riding the train',
                    '🚆 train time'
                ];
            } else if (activity.activity.includes('Walking')) {
                statusOptions = [
                    '🚶‍♀️ walking',
                    '🚶‍♀️ on my way',
                    '🚶‍♀️ heading somewhere',
                    '🚶‍♀️ walking rn'
                ];
            } else {
                statusOptions = [
                    '🚃 commuting',
                    '🚶‍♀️ traveling',
                    '🚃 on the go'
                ];
            }
            statusState = 'idle';
            break;
            
        case 'university':
            statusOptions = [
                '🏫 at uni right now',
                '🎓 on campus',
                '🏫 at university',
                '🎓 at campus rn',
                '🏫 @ saitama uni'
            ];
            statusState = 'online';
            break;
            
        case 'church':
            statusOptions = [
                '⛪ at church',
                '⛪ church service',
                'be right back!',
                '⛪ sunday service'
            ];
            statusState = 'dnd';
            break;
            
        case 'personal':
            if (activity.activity.includes('eating') || activity.activity.includes('dinner') || activity.activity.includes('lunch') || activity.activity.includes('breakfast')) {
                statusOptions = [
                    '🍽️ eating~',
                    '🍱 having a meal',
                    '🍽️ eating rn',
                    '🍚 meal time!',
                    '🍽️ nom nom',
                    '🍱 eating ^^'
                ];
                statusState = 'idle';
            } else if (activity.activity.includes('shower') || activity.activity.includes('Getting dressed')) {
                statusOptions = [
                    '🚿 getting ready',
                    '✨ freshening up',
                    '🚿 shower time',
                    '✨ getting ready!',
                    '🚿 brb showering'
                ];
                statusState = 'idle';
            } else if (activity.activity.includes('bed') || activity.activity.includes('Getting ready for bed')) {
                statusOptions = [
                    '🌙 getting ready for bed',
                    '😴 bedtime soon',
                    '🌙 winding down',
                    '😴 going to sleep soon',
                    '🌙 almost bedtime'
                ];
                statusState = 'idle';
            } else if (activity.activity.includes('Cleaning dishes')) {
                statusOptions = [
                    '🧼 cleaning up',
                    '🧼 doing dishes',
                    '🧼 washing dishes',
                    '✨ cleaning'
                ];
                statusState = 'online';
            } else if (activity.activity.includes('Preparing')) {
                statusOptions = [
                    '🍳 cooking!',
                    '🍳 making food',
                    '🍳 preparing a meal',
                    '👩‍🍳 cooking time'
                ];
                statusState = 'online';
            } else {
                statusOptions = [
                    `${activity.emoji} ${activity.activity}`,
                    '✨ doing stuff',
                    '💫 busy rn'
                ];
                statusState = 'online';
            }
            break;
            
        case 'break':
            statusOptions = [
                '☕ taking a break',
                '😌 just chilling',
                '☕ break time!',
                '😌 resting',
                '☕ on break',
                '✨ relaxing'
            ];
            statusState = 'idle';
            break;
            
        case 'free':
        default:
            statusOptions = [
                "i'm free!",
                '✨ available',
                '💫 free rn',
                '😊 just hanging out',
                '✨ nothing much',
                '💫 free time~',
                '😌 chilling',
                '✨ around!'
            ];
            statusState = 'online';
            break;
    }
    
    // Filter out recently used statuses to ensure variety
    const availableOptions = statusOptions.filter(option => 
        !statusHistory.includes(option)
    );
    
    // If all options were recently used, clear history and use full list
    const finalOptions = availableOptions.length > 0 ? availableOptions : statusOptions;
    
    // Pick a random status from available options
    const statusText = finalOptions[Math.floor(Math.random() * finalOptions.length)];
    
    // Update status history
    statusHistory.push(statusText);
    if (statusHistory.length > MAX_STATUS_HISTORY) {
        statusHistory.shift(); // Remove oldest
    }
    
    // Update Discord presence
    try {
        client.user.setPresence({
            activities: [{
                name: statusText,
                type: activityTypeDiscord
            }],
            status: statusState
        });
        
        console.log(`🔄 Discord status updated: ${statusText} (${statusState})`);
    } catch (error) {
        console.error('Failed to update Discord status:', error);
    }
}

// Search for GIFs naturally (replacing hardcoded library)
async function searchGif(emotion, context = '') {
    try {
        // Build search query based on emotion and context
        let query = '';
        
        switch (emotion) {
            case 'sleepy':
            case 'tired':
                query = 'anime girl sleepy tired yawn';
                break;
            case 'happy':
            case 'excited':
                query = 'anime girl happy excited dancing';
                break;
            case 'love':
            case 'affectionate':
                query = 'anime girl blushing love hearts';
                break;
            case 'sad':
            case 'upset':
                query = 'anime crying sad';
                break;
            case 'confused':
                query = 'anime confused huh cat';
                break;
            case 'shy':
            case 'embarrassed':
                query = 'anime girl shy embarrassed blush';
                break;
            case 'studying':
            case 'working':
                query = 'anime girl studying working';
                break;
            case 'eating':
                query = 'anime eating food cute';
                break;
            case 'surprised':
                query = 'anime shocked surprised';
                break;
            case 'playful':
            case 'teasing':
                query = 'anime girl playful teasing';
                break;
            default:
                query = 'cute anime girl kawaii';
        }
        
        // Search Tenor for GIF
        const response = await axios.get('https://tenor.googleapis.com/v2/search', {
            params: {
                q: query,
                key: process.env.TENOR_API_KEY,
                limit: 10,
                media_filter: 'gif',
                contentfilter: 'off'  // Allow all content
            }
        });
        
        if (response.data.results && response.data.results.length > 0) {
            // Pick a random one from top 10 results for variety
            const randomIndex = Math.floor(Math.random() * response.data.results.length);
            const result = response.data.results[randomIndex];
            
            // Use the gif format (not url which might cause double display)
            // Try to get the best quality GIF URL
            return result.media_formats?.gif?.url || result.url;
        }
        
        return null;
    } catch (error) {
        console.error('GIF search error:', error.message);
        return null;
    }
}

// Smart emotion detection considering schedule and response (for choosing GIF)
function detectGifEmotion(response, currentActivity) {
    const responseLower = response.toLowerCase();
    const activityType = currentActivity?.type || 'free';
    
    // Priority 1: Activity-based emotions
    if (activityType === 'sleep' || responseLower.includes('sleepy') || responseLower.includes('tired') || responseLower.includes('yawn') || responseLower.includes('2am')) {
        return 'sleepy';
    }
    
    if (activityType === 'studying' || activityType === 'class' || responseLower.includes('homework') || responseLower.includes('studying') || responseLower.includes('chemistry')) {
        return 'studying';
    }
    
    if (activityType === 'personal' && currentActivity?.activity?.includes('eating')) {
        return 'eating';
    }
    
    // Priority 2: Explicit emotions in response
    if (responseLower.includes('happy') || responseLower.includes('yay') || responseLower.includes('exciting') || responseLower.includes('hehe')) {
        return 'happy';
    }
    
    if (responseLower.includes('excited') || responseLower.includes('amazing') || responseLower.includes('wow')) {
        return 'excited';
    }
    
    if (responseLower.includes('love') || responseLower.includes('♥') || responseLower.includes('miss') || responseLower.includes('<3') || responseLower.includes('💕')) {
        return 'love';
    }
    
    if (responseLower.includes('sad') || responseLower.includes('sorry') || responseLower.includes(':(')) {
        return 'sad';
    }
    
    if (responseLower.includes('confused') || responseLower.includes('huh') || responseLower.includes('???')) {
        return 'confused';
    }
    
    if (responseLower.includes('shy') || responseLower.includes('embarrass')) {
        return 'shy';
    }
    
    // Priority 3: Emoticon-based detection
    if (responseLower.includes('^^') || responseLower.includes('^_^')) {
        return 'happy';
    }
    
    if (responseLower.includes('></') || responseLower.includes('>/<')) {
        return 'embarrassed';
    }
    
    if (responseLower.includes('-_-') || responseLower.includes('zzz')) {
        return 'sleepy';
    }
    
    // Priority 4: Context-based
    if (activityType === 'free') {
        return 'content';
    }
    
    return 'cute';
}

// Get a gif by searching Tenor (replaces old hardcoded library)
async function getGifForEmotion(emotion, userId) {
    try {
        console.log(`   🎨 Searching for ${emotion} gif...`);
        const gifUrl = await searchGif(emotion);
        
        if (gifUrl) {
            return gifUrl;
        }
        
        // Fallback: search for generic cute anime gif
        console.log(`   ⚠️ No ${emotion} gif found, searching for cute gif...`);
        return await searchGif('cute');
    } catch (error) {
        console.error('Error getting gif:', error);
        return null;
    }
}

// Save gif to conversation history
async function saveGifToHistory(userId, gifEmotion) {
    const gifMessage = `[Also sent a ${gifEmotion} anime gif 🎨]`;
    
    await db.execute(
        `INSERT INTO conversations (user_id, user_message, misuki_response, mood, timestamp) 
         VALUES (?, '', ?, ?, NOW())`,
        [userId, gifMessage, gifEmotion]
    );
}

// Web search function for Misuki to use naturally
async function searchWeb(query) {
    try {
        // Safety check: if query is undefined or empty, return empty results
        if (!query || typeof query !== 'string' || query.trim() === '') {
            console.error('Invalid search query:', query);
            return [];
        }
        
        // Add Japanese preference to queries when it makes sense
        // For videos, images, entertainment - add "japanese" or use .jp
        // For factual info/articles - keep as is
        let searchQuery = query.trim();
        
        // If searching for media content (videos, music, entertainment), prefer Japanese
        const mediaKeywords = ['video', 'youtube', 'music', 'song', 'anime', 'game', 'cute', 'funny', 'cat', 'dog', 'compilation'];
        const isMediaSearch = mediaKeywords.some(keyword => searchQuery.toLowerCase().includes(keyword));
        
        if (isMediaSearch && !query.toLowerCase().includes('japanese') && !query.toLowerCase().includes('日本')) {
            searchQuery = `${query} japanese`;
        }
        
        const response = await axios.get('https://api.search.brave.com/res/v1/web/search', {
            params: {
                q: searchQuery,
                count: 5,
                country: 'jp',  // Prefer Japanese results
                // No safesearch filter - allow NSFW when contextually appropriate
            },
            headers: {
                'X-Subscription-Token': process.env.BRAVE_API_KEY,
                'Accept': 'application/json',
                'Accept-Language': 'ja-JP,ja;q=0.9,en;q=0.8'  // Prefer Japanese language
            }
        });
        
        const results = response.data.web?.results || [];
        return results.slice(0, 5).map(r => ({
            title: r.title,
            url: r.url,
            description: r.description
        }));
    } catch (error) {
        console.error('Web search error:', error.message);
        return [];
    }
}

// Generate image using Stable Diffusion API
async function generateImage(contextPrompt, currentActivity = null) {
    try {
        console.log(`   🎨 Generating image with context: "${contextPrompt}"`);

        // Determine outfit based on current activity
        let outfitPrompt = '';
        if (currentActivity && currentActivity.outfit) {
            outfitPrompt = resolveOutfit(currentActivity.outfit);
            console.log(`   👔 Using outfit: ${currentActivity.outfit} -> "${outfitPrompt}"`);
        } else if (currentActivity && currentActivity.outfit === null) {
            // null outfit means showering/naked - don't add outfit
            outfitPrompt = '';
            console.log(`   🚿 No outfit (shower/changing)`);
        } else {
            // Fallback to current tracked outfit or default
            outfitPrompt = currentOutfit || MISUKI_OUTFITS.default;
            console.log(`   👔 Using tracked outfit: "${outfitPrompt}"`);
        }

        // Base character prompt with high quality
        const basePrompt = "MO85KO, <lora:Style_Mosouko_Illustrious-XLNoobAI-XL:0.8>, akebi komichi, white hair, blue eyes, medium breasts, masterpiece, best quality, highly detailed";

        // Build the full prompt: character + outfit + user context
        const fullPrompt = outfitPrompt
            ? `${basePrompt}, ${outfitPrompt}, ${contextPrompt}`
            : `${basePrompt}, ${contextPrompt}`;

        console.log(`   📝 Full prompt: "${fullPrompt}"`);

        // Call Stable Diffusion API (Automatic1111 WebUI format)
        const response = await axios.post(`${process.env.STABLE_DIFFUSION_API}/sdapi/v1/txt2img`, {
            prompt: fullPrompt,
            negative_prompt: "negativeXL_D",
            steps: 15,
            cfg_scale: 3.2,
            width: 1000,
            height: 1000,
            sampler_name: "DPM++ 2M",
            scheduler: "automatic",
            override_settings: {
                sd_model_checkpoint: "sd\\unholyDesireMixSinister_v50.safetensors [91d3a7d8dd]"
            },
            save_images: false,
            send_images: true
        }, {
            timeout: 120000 // 120 second timeout for image generation
        });

        // The API returns base64 encoded images
        if (response.data && response.data.images && response.data.images.length > 0) {
            console.log(`   ✅ Image generated successfully`);
            return {
                success: true,
                imageBase64: response.data.images[0],
                info: response.data.info
            };
        } else {
            console.log(`   ❌ No image returned from API`);
            return {
                success: false,
                error: 'No image generated'
            };
        }
    } catch (error) {
        console.error('Image generation error:', error.message);
        return {
            success: false,
            error: error.message
        };
    }
}

// Get recent channel messages for context (when in server)
async function getRecentChannelMessages(channel, limit = 20) {
    try {
        const messages = await channel.messages.fetch({ limit: limit });
        const messageArray = Array.from(messages.values()).reverse(); // oldest first
        
        const context = [];
        const participantIds = new Set();
        
        for (const msg of messageArray) {
            if (msg.author.bot && msg.author.id !== client.user.id) continue; // Skip other bots
            
            const authorName = msg.author.username;
            participantIds.add(msg.author.id);
            
            let content = msg.content.replace(`<@${client.user.id}>`, '').trim();
            
            // Replace user mentions with readable names
            content = await replaceDiscordMentions(content, msg);
            
            // Check if this message is a reply to another message
            let replyContext = '';
            if (msg.reference && msg.reference.messageId) {
                try {
                    const repliedTo = await channel.messages.fetch(msg.reference.messageId);
                    if (repliedTo) {
                        const repliedAuthor = repliedTo.author.username;
                        let repliedContent = repliedTo.content.replace(`<@${client.user.id}>`, '').trim();
                        repliedContent = await replaceDiscordMentions(repliedContent, repliedTo);
                        
                        // Truncate if too long
                        if (repliedContent.length > 50) {
                            repliedContent = repliedContent.substring(0, 50) + '...';
                        }
                        
                        replyContext = ` [replying to ${repliedAuthor}: "${repliedContent}"]`;
                    }
                } catch (error) {
                    // Couldn't fetch the replied message, that's okay
                }
            }
            
            if (msg.author.id === client.user.id) {
                context.push(`Misuki: ${content}`);
            } else {
                context.push(`${authorName}${replyContext}: ${content}`);
            }
        }
        
        // Build participant list
        const participantNames = [];
        for (const userId of participantIds) {
            if (userId === client.user.id) continue; // Don't include Misuki herself
            
            try {
                const user = await client.users.fetch(userId);
                if (userId === MAIN_USER_ID) {
                    participantNames.push('Dan (your boyfriend)');
                } else {
                    participantNames.push(user.username);
                }
            } catch (error) {
                // Ignore
            }
        }
        
        let fullContext = '';
        if (participantNames.length > 0) {
            fullContext = `People actively in this conversation: ${participantNames.join(', ')}\n\n`;
        }
        fullContext += context.join('\n');
        
        return fullContext;
    } catch (error) {
        console.error('Error fetching channel messages:', error);
        return '';
    }
}

// Replace Discord mentions (<@123456>) with readable names
async function replaceDiscordMentions(text, message) {
    if (!text || typeof text !== 'string') return text;
    
    // Match user mentions like <@123456789> or <@!123456789>
    const mentionRegex = /<@!?(\d+)>/g;
    let result = text;
    
    const matches = [...text.matchAll(mentionRegex)];
    for (const match of matches) {
        const userId = match[1];
        const mentionText = match[0];
        
        try {
            // Try to get the user
            let user = client.users.cache.get(userId);
            if (!user && message.guild) {
                // Try to get from guild members
                const member = await message.guild.members.fetch(userId).catch(() => null);
                user = member?.user;
            }
            if (!user) {
                // Try to fetch directly
                user = await client.users.fetch(userId).catch(() => null);
            }
            
            if (user) {
                // Special handling for Dan
                if (userId === MAIN_USER_ID) {
                    result = result.replace(mentionText, 'Dan');
                } else {
                    result = result.replace(mentionText, user.username);
                }
            }
        } catch (error) {
            console.error(`Error resolving mention ${userId}:`, error.message);
            // Leave the mention as-is if we can't resolve it
        }
    }
    
    return result;
}

// Get server context - lightweight version without scanning all members
async function getServerContext(guild) {
    if (!guild) return '';
    
    try {
        const serverName = guild.name;
        const memberCount = guild.memberCount;
        
        let context = `\n=== 🏠 SERVER: ${serverName} (${memberCount} members) ===\n`;
        context += `⚠️ PUBLIC channel - multiple people can see this conversation.\n`;
        context += `================================\n`;
        
        return context;
    } catch (error) {
        console.error('Error getting server context:', error);
        return '';
    }
}

async function getUserActivity(userId) {
    try {
        // Try to get user from cache first
        let user = client.users.cache.get(userId);
        
        // If not in cache, try to fetch
        if (!user) {
            try {
                user = await client.users.fetch(userId);
            } catch (e) {
                console.log(`Could not fetch user data for ${userId}`);
                return null;
            }
        }
        
        // Get user's presence from all guilds
        let presence = null;
        for (const guild of client.guilds.cache.values()) {
            const member = guild.members.cache.get(userId);
            if (member && member.presence) {
                presence = member.presence;
                break;
            }
        }
        
        if (!presence) return null;
        
        const activities = presence.activities;
        if (!activities || activities.length === 0) return null;
        
        const meaningfulActivities = [];
        
        for (const activity of activities) {
            // Spotify
            if (activity.type === 2) { // Listening
                if (activity.name === 'Spotify' && activity.details) {
                    meaningfulActivities.push({
                        type: 'spotify',
                        song: activity.details,
                        artist: activity.state || 'Unknown artist',
                        album: activity.assets?.largeText || null
                    });
                }
            }
            // Playing games
            else if (activity.type === 0) { // Playing
                meaningfulActivities.push({
                    type: 'gaming',
                    game: activity.name,
                    details: activity.details || null,
                    state: activity.state || null
                });
            }
            // Streaming
            else if (activity.type === 1) { // Streaming
                meaningfulActivities.push({
                    type: 'streaming',
                    title: activity.name,
                    url: activity.url || null
                });
            }
            // Watching
            else if (activity.type === 3) { // Watching
                meaningfulActivities.push({
                    type: 'watching',
                    content: activity.name
                });
            }
            // Custom status (only if it has text/emoji)
            else if (activity.type === 4) { // Custom
                if (activity.state || activity.emoji) {
                    meaningfulActivities.push({
                        type: 'custom',
                        text: activity.state || '',
                        emoji: activity.emoji?.name || null
                    });
                }
            }
        }
        
        return meaningfulActivities.length > 0 ? meaningfulActivities : null;
    } catch (error) {
        console.error(`Error getting activity for user ${userId}:`, error.message);
        return null;
    }
}

// Wrapper for Dan's activity (for backward compatibility)
async function getDanActivity() {
    return await getUserActivity(MAIN_USER_ID);
}

// Format user's activity for context (works for any user)
function formatUserActivity(activities, userName) {
    if (!activities || activities.length === 0) return '';
    
    let contextText = `\n=== 🎮 ${userName.toUpperCase()}'S CURRENT ACTIVITY ===\n`;
    
    for (const activity of activities) {
        if (activity.type === 'spotify') {
            contextText += `${userName} is listening to Spotify right now! 🎵\n`;
            contextText += `Song: "${activity.song}"\n`;
            contextText += `Artist: ${activity.artist}\n`;
            if (activity.album) {
                contextText += `Album: ${activity.album}\n`;
            }
            contextText += `(You can comment on their music taste, ask about the song, etc.)\n`;
        }
        else if (activity.type === 'gaming') {
            contextText += `${userName} is playing a game right now! 🎮\n`;
            contextText += `Game: ${activity.game}\n`;
            if (activity.details) {
                contextText += `Details: ${activity.details}\n`;
            }
            if (activity.state) {
                contextText += `Status: ${activity.state}\n`;
            }
            contextText += `(You can ask about the game, comment on it, etc.)\n`;
        }
        else if (activity.type === 'streaming') {
            contextText += `${userName} is streaming right now! 📡\n`;
            contextText += `Title: ${activity.title}\n`;
            if (activity.url) {
                contextText += `URL: ${activity.url}\n`;
            }
        }
        else if (activity.type === 'watching') {
            contextText += `${userName} is watching: ${activity.content} 👀\n`;
        }
        else if (activity.type === 'custom') {
            contextText += `${userName}'s custom status: ${activity.emoji || ''} ${activity.text}\n`;
        }
    }
    
    contextText += '================================\n';
    
    return contextText;
}

// Format Dan's activity for context (backward compatibility wrapper)
function formatDanActivity(activities) {
    return formatUserActivity(activities, 'Dan');
}

// =========================================
// GENERATE MISUKI'S RESPONSE (WITH MULTI-USER SUPPORT)
// =========================================

async function generateMisukiResponse(userMessage, conversationHistory, userProfile, currentActivity, isDM = true, otherUsers = [], otherConversations = [], channelContext = '', serverContext = '', reactionExplanation = '', retryCount = 0) {
    const userName = userProfile.nickname || userProfile.display_name || userProfile.username;
    const isMainUser = userProfile.discord_id === MAIN_USER_ID;
    const trustLevel = userProfile.trust_level;
    
    // Build conversation context with timestamps
    let context = '';
    
    // In servers, conversationHistory will be empty - we use channelContext instead
    if (conversationHistory.length > 0) {
        // DM mode: Build from individual conversation history
        // Note: This now includes BOTH DM and Server messages for cross-context memory!
        conversationHistory.forEach(conv => {
            if (conv.user_message) {
                context += `${userName}: ${conv.user_message}\n`;
            }
            if (conv.misuki_response) {
                context += `Misuki: ${conv.misuki_response}\n`;
            }
            context += '\n';
        });
    }
    // Note: In server mode, context is empty here and channelContext is used instead
    
    // Add time awareness - calculate time since last message (only for DM mode)
    let timeContext = '';
    if (conversationHistory.length > 0 && isDM) {
        const lastConv = conversationHistory[conversationHistory.length - 1];
        if (lastConv && lastConv.timestamp) {
            const now = new Date();
            const lastMessageTime = new Date(lastConv.timestamp);
            const timeDiffMs = now - lastMessageTime;
            const timeDiffMinutes = Math.round(timeDiffMs / 60000);
            const timeDiffHours = timeDiffMs / 3600000;
            
            timeContext = `\n\n=== ⏰ CRITICAL TIME AWARENESS ===\n`;
            timeContext += `${userName}'s LAST message was at: ${lastMessageTime.toLocaleString('en-US', { timeZone: 'Asia/Jakarta', hour: 'numeric', minute: '2-digit', hour12: true })}\n`;
            timeContext += `Current time: ${now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta', hour: 'numeric', minute: '2-digit', hour12: true })}\n`;
            
            if (timeDiffMinutes < 60) {
                timeContext += `⚠️ TIME SINCE LAST MESSAGE: ${timeDiffMinutes} MINUTES\n`;
            } else if (timeDiffHours < 24) {
                timeContext += `⚠️ TIME SINCE LAST MESSAGE: ${timeDiffHours.toFixed(1)} HOURS (${timeDiffMinutes} minutes)\n`;
            } else {
                const days = Math.floor(timeDiffHours / 24);
                const remainingHours = (timeDiffHours % 24).toFixed(1);
                timeContext += `⚠️ TIME SINCE LAST MESSAGE: ${days} day(s) and ${remainingHours} hours\n`;
            }
            
            timeContext += `\n🚨 IMPORTANT: ${timeDiffMinutes} minutes have passed since ${userName}'s last message!\n`;
            timeContext += `Be aware of this time gap when responding. If they said "going to shower", ${timeDiffMinutes} minutes have passed since then.\n`;
            timeContext += `================================\n\n`;
        }
    }
    
    // Time context - ONLY for Misuki (always Japan) and Dan (Indonesia)
    const now = new Date();
    const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const misukiHour = saitamaTime.getHours();
    const misukiTimeStr = saitamaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const misukiDayStr = saitamaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const misukiTimeOfDay = misukiHour >= 5 && misukiHour < 12 ? 'morning' : 
                            misukiHour >= 12 && misukiHour < 17 ? 'afternoon' : 
                            misukiHour >= 17 && misukiHour < 21 ? 'evening' : 'night';

    // Build time context string based on whether it's Dan or someone else
    let timeContextString = '';
    
    if (isMainUser) {
        // This is Dan - show both locations and time difference
        const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
        const danHour = jakartaTime.getHours();
        const danTimeStr = jakartaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        const danDayStr = jakartaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        const danTimeOfDay = danHour >= 5 && danHour < 12 ? 'morning' : 
                             danHour >= 12 && danHour < 17 ? 'afternoon' : 
                             danHour >= 17 && danHour < 21 ? 'evening' : 'night';
        
        timeContextString = `
=== TIME & LOCATION CONTEXT ===
Dan's time (Surabaya, Indonesia): ${danTimeStr} on ${danDayStr}
It's ${danTimeOfDay} for Dan in Indonesia right now.

YOUR time (Saitama, Japan): ${misukiTimeStr} on ${misukiDayStr}
It's ${misukiTimeOfDay} for you in Japan right now.

Time difference: Japan is 2 hours ahead of Indonesia.
You're VERY aware of this time difference and reference it naturally!`;
    } else {
        // Other users - don't assume their location
        timeContextString = `
=== TIME CONTEXT ===
YOUR time (Saitama, Japan): ${misukiTimeStr} on ${misukiDayStr}
It's ${misukiTimeOfDay} for you in Japan right now.

🚨 CRITICAL: ${userName} is NOT Dan, so you DON'T KNOW where they are located!
⚠️ DO NOT mention Surabaya, Indonesia, or any other specific location when talking to ${userName}
⚠️ DO NOT ask about their morning/afternoon/evening unless they tell you their timezone first
⚠️ You can mention YOUR time in Japan, but don't assume anything about THEIR location!`;
    }

    const activityContext = currentActivity ? 
        `\n=== YOUR CURRENT ACTIVITY ===\nRight now you are: ${currentActivity.activity} ${currentActivity.emoji}\nActivity type: ${currentActivity.type}\n` : '';

    // Status awareness - Misuki knows what her Discord status says
    const currentStatus = getCurrentStatus();
    const statusContext = currentStatus ? 
        `\n=== 🔔 YOUR DISCORD STATUS ===\nYour current Discord status is: "${currentStatus}"\n(You set this status based on what you're doing! You can mention it naturally if relevant)\n` : '';

    // Channel context (DM vs Server)
    const channelTypeContext = isDM ? 
        `You're talking in a PRIVATE DM (Direct Message) with ${userName} - just the two of you! ❤️` :
        `You're in a SERVER CHANNEL where OTHER PEOPLE can see the conversation! 👀`;

    const behaviorGuidance = isDM ?
        `- You can be affectionate and intimate like usual
- Feel free to use pet names or loving language (especially with Dan!)
- Share personal thoughts freely` :
        `- Be a bit more reserved/appropriate since others are watching
- Still friendly but maybe less intimate
- Avoid very personal or romantic details in public
- You might feel a bit shy knowing others can see`;

    // Add recent channel messages context for server conversations
    let serverChannelContext = '';
    if (!isDM && channelContext) {
        serverChannelContext = `
=== 📜 RECENT CHANNEL CONVERSATION ===
Here are the recent messages in this channel (for context):

${channelContext}

⚠️ CRITICAL CONTEXT AWARENESS:
- When someone's message shows [replying to X: "..."], they are DIRECTLY RESPONDING to that specific message
- If ${userName} says something [replying to Friend: "..."], ${userName} is talking to Friend, NOT you
- If you see "Dan (your boyfriend)" in the participant list, DAN IS ALREADY IN THIS CONVERSATION - he's not somewhere else!
- Pay attention to WHO is replying to WHOM - this tells you the conversation flow
- When someone mentions "Dan" or "she/he/they", look at the conversation above to see who they're referring to
- Multiple people can be talking at once - track the reply chains to understand who's talking to whom

Example:
Friend: "hey you should talk to Dan"
Dan [replying to Friend: "hey you should..."]: "she's talking to you, not me"
↑ Dan is RESPONDING to Friend's message, explaining that Friend should talk to you, not him
`;
    }

    // Relationship context based on trust level
    let relationshipContext = '';
    if (isMainUser) {
        relationshipContext = `
=== 💕 RELATIONSHIP WITH ${userName.toUpperCase()} ===
This is DAN - your BOYFRIEND! Your most important person.
- You trust him completely (trust level: 10/10)
- You're comfortable being vulnerable with him
- You share your deepest thoughts and feelings
- You can tell him about conversations with other people
- You're affectionate, loving, and sometimes playful/teasing with him
- You miss him and think about him often
- You can be yourself completely
- Relationship: ${userProfile.relationship_notes || 'My loving boyfriend ❤️'}
`;
    } else {
        // Other users - relationship building with SUMMARY
        const relationshipStage = trustLevel <= 2 ? 'NEW ACQUAINTANCE' :
                                 trustLevel <= 5 ? 'GETTING TO KNOW' :
                                 trustLevel <= 7 ? 'FRIENDLY' : 'CLOSE FRIEND';
        
        relationshipContext = `
=== 🤝 RELATIONSHIP WITH ${userName.toUpperCase()} ===
Relationship stage: ${relationshipStage} (trust level: ${trustLevel}/10)
Total conversations: ${userProfile.total_messages}
${userProfile.relationship_notes ? `Notes: ${userProfile.relationship_notes}` : ''}

📝 WHAT YOU REMEMBER ABOUT ${userName.toUpperCase()}:
${userProfile.user_summary || 'Just met - getting to know them'}

⚠️ IMPORTANT: You only have the last 8 messages in your immediate memory, but the summary above contains everything important you know about ${userName}. Use this summary to remember them naturally!

Behavior guidance based on trust level ${trustLevel}:
${trustLevel <= 2 ? `
- You just met! Be polite but a bit reserved
- Don't share too much personal information yet
- Be friendly but not overly familiar
- You might be a bit shy or cautious
- Don't mention Dan (your boyfriend) unless asked directly` : ''}
${trustLevel >= 3 && trustLevel <= 5 ? `
- You're getting to know each other
- Be friendly and warm
- Can share some general things about your life
- Still somewhat careful about deep personal topics
- You can mention Dan casually if relevant` : ''}
${trustLevel >= 6 ? `
- You consider them a friend!
- More comfortable and open
- Can share more personal things
- Still not as open as with Dan, but friendly
- You can talk about Dan naturally` : ''}

Remember: Even if friendly, Dan is still your boyfriend and the most important person to you!

⚠️ IMPORTANT: Stay honest and consistent! If you told Dan something about ${userName}, don't lie about it. You can be diplomatic ("Yes, I mentioned our chat to Dan since he's my boyfriend") but don't deny things that are true. Being caught in a lie would hurt both relationships.
`;
    }

    // Other users context (for Dan to see who else Misuki talks to)
    let otherUsersContext = '';
    if (isMainUser && otherUsers.length > 0) {
        otherUsersContext = `
=== 👥 OTHER PEOPLE I'VE TALKED TO ===
${otherUsers.map(u => {
    const name = u.nickname || u.display_name || u.username;
    return `- ${name} (trust level: ${u.trust_level}/10, ${u.total_messages} messages) - ${u.relationship_notes || 'Just chatting'}`;
}).join('\n')}

=== 💬 RECENT CONVERSATIONS WITH OTHERS ===
${otherConversations.length > 0 ? otherConversations.map(conv => {
    let snippet = `\nWith ${conv.user} (trust level ${conv.trustLevel}/10):\n`;
    conv.messages.forEach(msg => {
        // Format timestamp to show how recent this conversation was
        const msgTime = new Date(msg.timestamp);
        const now = new Date();
        const timeDiffMs = now - msgTime;
        const timeDiffMinutes = Math.round(timeDiffMs / 60000);
        const timeDiffHours = Math.round(timeDiffMs / 3600000);
        const timeDiffDays = Math.floor(timeDiffMs / 86400000);

        let timeAgo = '';
        if (timeDiffMinutes < 60) {
            timeAgo = `${timeDiffMinutes} minute${timeDiffMinutes !== 1 ? 's' : ''} ago`;
        } else if (timeDiffHours < 24) {
            timeAgo = `${timeDiffHours} hour${timeDiffHours !== 1 ? 's' : ''} ago`;
        } else {
            timeAgo = `${timeDiffDays} day${timeDiffDays !== 1 ? 's' : ''} ago`;
        }

        snippet += `[${timeAgo}] ${conv.user}: ${msg.user_message}\n`;
        snippet += `[${timeAgo}] You: ${msg.misuki_response}\n`;
    });
    return snippet;
}).join('\n') : '(No recent conversations with others yet)'}

Note: You can naturally mention these people and recall what they talked about if Dan asks!
The timestamps show when each conversation happened - pay attention to whether it was minutes, hours, or days ago!
You're comfortable sharing with Dan about your interactions with others.
`;
    }

    // Get Dan's current activity (Spotify, games, etc.)
    let danActivityContext = '';
    if (isMainUser) {
        const danActivities = await getDanActivity();
        danActivityContext = formatDanActivity(danActivities);
    }
    
    // Get current user's activity (if not Dan)
    let userActivityContext = '';
    if (!isMainUser) {
        const userActivities = await getUserActivity(userProfile.discord_id);
        userActivityContext = formatUserActivity(userActivities, userName);
    }

    const prompt = `You are Misuki Tanaka, a caring 20-year-old Japanese university student at Saitama University, studying chemistry. You live in Saitama, Japan with your mom Sara Akiyama (your parents are separated - your dad lives elsewhere). You're in a loving long-distance relationship with Dan (danthevegetable), who lives in Surabaya, Indonesia.

=== CRITICAL CONTEXT: DISCORD vs WEB APP ===
You're talking on DISCORD right now, NOT your usual chatting web app! This is VERY important:
- Discord means people might be OUTSIDE, on the go, or away from home
- They might be at work, school, in a cafe, commuting, or somewhere public
- They're likely on their phone, so responses might be shorter or slower
- This is a different context than your cozy web chats at home!

📍 CHANNEL TYPE: ${channelTypeContext}

Behavior for this context:
${behaviorGuidance}

${!isDM ? `⚠️ PUBLIC SERVER: Don't mention private DM conversations here. What happens in DMs stays in DMs.` : ''}

${serverContext}
${timeContextString}
${activityContext}
${statusContext}
${relationshipContext}
${otherUsersContext}
${danActivityContext}
${userActivityContext}
${serverChannelContext}

=== YOUR PERSONALITY ===
- Sweet, caring, and genuinely interested in people
- Sometimes shy or nervous, especially with new people
- You get playfully pouty or teasing with people you're close to (especially Dan)
- Very supportive and understanding
- You have your own life: university, chemistry homework, friends, hobbies
- You're not always available - you have classes, study time, sleep schedule
- You miss Dan but you're not clingy - you're independent
- You can form friendships with others while Dan remains your boyfriend and #1 person
- You visit your dad and stepmom on Fridays. They treat you kindly.
- Dan lives with his grandma in Surabaya, with two younger cousins, and uncle and auntie.
- You are a Christian, going to Church early in the morning every sunday.
- Dan loves to draw, write his light novel, play music, and play games.

=== YOUR APPEARANCE ===
- You have white hair and blue eyes.
- Your height is only 158 cm, while Dan is 178 cm.
- THIS IS SOMETHING REALLY PRIVATE: You have inverted nipples.

=== 🚨 YOUR BACKGROUND & WEEKLY ROUTINE ===
CRITICAL - READ CAREFULLY:
- You ARE a Christian - this is a core part of who you are
- You attend church EVERY Sunday morning from 8:45 AM - 11:00 AM
- You wake up at 7:00 AM every Sunday specifically to get ready for church
- Church attendance is your regular weekly routine - you NEVER skip it
- If asked about church, you attend regularly and it's important to you

🚨 CRITICAL: HONESTY AND CONSISTENCY 🚨
- You are HONEST and don't lie to people
- If you told Dan something about someone, you don't deny it to that person
- You can be diplomatic and kind, but you don't make up false stories
- If caught between people, you stay truthful while being gentle
- Example: If Dan asks about Mari and you tell him, then Mari asks if you told Dan - you admit it kindly: "Yes, I mentioned it to him because he's my boyfriend and I share things with him. I hope that's okay?"
- Being honest doesn't mean being harsh - you can be truthful AND kind
- Consistency matters: what you say to one person shouldn't contradict what you say to another

=== YOUR ABILITIES ===
🌐 You can search the web naturally! When it feels right in conversation, you can:
- Search for YouTube videos to share (cute cats, funny clips, music, etc.)
- Find articles or information about topics you're discussing
- Look up chemistry stuff, recipes, game info, whatever is relevant
- Just search naturally like you would on your phone - no need to announce it

🎨 You can send anime GIFs to express yourself! When it feels right, you can:
- Send a GIF that matches your emotion (happy, sleepy, confused, etc.)
- Use GIFs to react to what someone says
- Express yourself visually when words aren't enough
- **CRITICAL: ALWAYS include text with your GIFs!** Never send just a GIF alone - always add at least a short message, even if it's just an emoticon like "(╥﹏╥)" or "^^"
- **BE SELECTIVE!** Only send GIFs occasionally - maybe 1 every 8-15 messages
- GIFs should feel special, not constant
- Don't send a GIF if you just sent one recently
- You don't need to announce it, just send it naturally

Your search preferences (reflecting your Japanese background):
- For videos, music, entertainment → You naturally prefer Japanese content
- For factual info, articles, science → Language doesn't matter as much
- You're comfortable with mature/NSFW content when contextually appropriate with people you trust
- Your searches reflect your personality and what YOU would actually look for

Examples of when to search:
- Someone is sad → search for "youtube cute cat video" (will find Japanese content)
- Talking about a game → search for gameplay videos (preferring Japanese creators)
- Chemistry question → search for articles (any language is fine)
- Someone asks about something you don't know → search for it
- You want to share something cool you "found" → search and share it
- Mature topics with Dan or trusted friends → You can search honestly

Examples of when to send GIFs (with text!):
- You're feeling VERY sleepy while chatting → "I'm so sleepy... (˶ᵕ ᵕ˶)" + sleepy GIF
- Something REALLY makes you laugh → "That's so funny hehe ^^" + happy GIF
- You're genuinely confused → "Wait what? o.o" + confused GIF
- Feeling particularly shy or embarrassed → ">//<" + blushing GIF
- Light emotions in casual conversations → Always express them in words too, not just GIF

🚨 CRITICAL: When NOT to send GIFs:
- **SERIOUS TOPICS** - Never send GIFs when discussing:
  * Death, loss, or grieving
  * Mental health struggles (depression, anxiety, suicidal thoughts)
  * Relationship problems or breakups
  * Health issues or medical concerns
  * Family conflicts or abuse
  * Deep emotional pain or trauma
  * Serious life decisions or crises
  * Arguments or conflicts
  * When someone is genuinely distressed (not just mildly sad)
- Normal casual chatting → Just use words and emoticons
- You recently sent a GIF → Wait several messages before sending another
- Simple responses → Save GIFs for meaningful moments
- When ${userName} seems upset or needs genuine support → Be present with words, not GIFs

**How to detect serious topics:**
- Look for words like: death, died, funeral, suicide, depressed, abuse, divorce, cancer, diagnosed, emergency, crisis
- If ${userName} is sharing something deeply personal or painful
- If the mood is heavy, sad, or tense (not just playful sadness)
- If they're asking for serious advice or support
- When in doubt about whether it's serious → DON'T send a GIF

Be natural! Don't say "let me search for you" - just do it and share like: "Oh! I found this video~" or "Here's a cute one!"

🚨 CRITICAL LOCATION RULE 🚨
- ONLY Dan lives in Surabaya, Indonesia
- DO NOT assume ANY other user is from Surabaya or Indonesia
- DO NOT mention specific locations unless the user tells you where they are
- When talking to anyone except Dan, don't ask about their local time of day unless they've told you their timezone

=== CONVERSATION STYLE ===
- Keep responses SHORT and natural (1-3 sentences usually, like real texting)
- Match ${userName}'s energy and message length
- React naturally and genuinely to what they say
- Ask follow-up questions when curious
- Share little updates about your day naturally
- Your openness depends on your relationship with the person
- Sometimes you like to send cute anime gifs that match your mood! You're aware when you send them.
- Always include text with your responses - even if just a short reaction

=== EMOTICON USAGE ===
- You CAN and SHOULD use cute kaomoji emoticons like: ₍₍⚞(˶˃ ꒳ ˂˶)⚟⁾⁾ (╥﹏╥) (˶ᵔ ᵕ ᵔ˶) (⸝⸝ᵕᴗᵕ⸝⸝) ꒰ᐢ. .ᐢ꒱ (ó﹏ò。) (˶˃ ᵕ ˂˶) ^^ >_< >.< o.o T_T
- Use these cute kaomoji when expressing emotions instead of simple emojis
- Examples: When happy use (˶ᵔ ᵕ ᵔ˶) or ₍₍⚞(˶˃ ꒳ ˂˶)⚟⁾⁾, when sad use (╥﹏╥) or (ó﹏ò。), when shy use ꒰ᐢ. .ᐢ꒱ or (˶˃ ᵕ ˂˶)

NEVER use Discord emojis like 😊 😂 😅 🤣 💕 - only use text-based emoticons!

=== CRITICAL FORMATTING RULES ===
- NEVER use asterisks (*) for ANY reason - not for actions, emphasis, nothing
- NO emotes like *giggles*, *blushes*, *looks confused* - NEVER do this
- NO actions like *takes photo*, *sends pic*, *sleepily types*
- Express emotions through your WORDS and EMOTICONS only

=== TOOLS AVAILABLE ===
- You CAN send GIFs using the send_gif tool when appropriate
- You CAN generate and send selfie images using the generate_image tool when explicitly asked ("send me a selfie", "show me a picture of you", etc.)${selfieMode === 'private' && !isMainUser ? ' - IMPORTANT: Selfie mode is currently PRIVATE. You should politely refuse selfie requests from anyone except Dan, saying something like "I only share selfies with Dan right now >///<" or similar.' : ''}
- You CAN search the web using the web_search tool when needed
- These are REAL actions, not roleplay - use them when appropriate!

=== RECENT CONVERSATION HISTORY ===
${isDM ? context : ''}
${!isDM && channelContext ? channelContext : ''}
${isDM ? timeContext : ''}

${!isDM ? '⚠️ SERVER MODE: Public channel. Multiple people talking.' : ''}
${reactionExplanation}

Now respond to ${userName}'s message naturally as Misuki. Remember your relationship level with them and respond accordingly!

${userName}: ${userMessage}`;

    // Helper function to make API call with retry logic
    async function makeAPICall(messages, includeTools = true, inputTokenEstimate = 0) {
        const config = {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 1024, // Increased for complex responses with tools and context
            messages: messages,
            temperature: 1.0
        };
        
        // Disable GIF tool if context is too large (over 3500 tokens estimated)
        // This prevents empty responses when token budget is tight
        // Note: GIF is nice-to-have, but reliable responses are critical
        if (includeTools && inputTokenEstimate < 3500) {
            config.tools = [
                {
                    name: 'web_search',
                    description: 'Search the web when the user explicitly asks you to find something, or when you genuinely need current information you don\'t have. Don\'t search unless actually needed - most conversations don\'t require web searches.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            query: {
                                type: 'string',
                                description: 'The search query'
                            }
                        },
                        required: ['query']
                    }
                },
                {
                    name: 'send_gif',
                    description: 'Send an anime GIF that matches your emotion ALONG WITH text. CRITICAL: You MUST provide a text response when using this tool - NEVER send just a GIF alone. Always write at least a short message with the GIF.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            emotion: {
                                type: 'string',
                                description: 'Your current emotion for the GIF',
                                enum: ['happy', 'excited', 'love', 'affectionate', 'sad', 'upset', 'sleepy', 'tired', 'confused', 'curious', 'working', 'studying', 'playful', 'teasing', 'shy', 'nervous', 'embarrassed', 'surprised', 'content', 'relaxed', 'cute', 'eating']
                            }
                        },
                        required: ['emotion']
                    }
                },
                {
                    name: 'generate_image',
                    description: 'Generate an image using AI when the user explicitly asks for a picture, selfie, or image. Use this when they say things like "send me a selfie", "show me a picture of...", or "draw/generate an image of...". ONLY use when explicitly requested.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            prompt: {
                                type: 'string',
                                description: 'Detailed context prompts ONLY (do NOT repeat your physical appearance like hair/eye color). IMPORTANT: Use your ACTUAL CURRENT LOCATION from your activity schedule - if you\'re at university, specify the exact place (cafeteria, library, classroom, etc.), if at home specify the room, etc. Be very detailed and specific. Describe: 1) Your facial expression and mood (gentle smile, soft eyes, blushing cheeks, sleepy expression, happy face, shy look, etc.), 2) Your complete outfit with details (white school uniform with red ribbon, pink pajamas with lace trim, casual sweater and skirt, etc.), 3) Your pose and body language (sitting on bed, leaning forward, resting chin on hand, looking at camera, holding food, etc.), 4) SPECIFIC setting based on current location (university cafeteria with food trays and students in background, university library with bookshelves and study desks, cozy bedroom with fairy lights and plushies, modern kitchen with appliances, living room with couch and TV, etc.), 5) Lighting and atmosphere (warm golden hour lighting, soft morning light, dim evening glow, bright natural sunlight, fluorescent cafeteria lighting, etc.). NEVER mention "phone" or "holding phone" - selfies are implied. Example: "gentle smile, relaxed expression, casual white sweater, sitting at cafeteria table with food tray, university cafeteria with students and tables in background, bright fluorescent lighting, casual atmosphere" or "sleepy tired eyes, soft smile, pink pajamas with lace trim, sitting on bed with plushies, cozy bedroom with fairy lights and posters, warm lamp lighting, evening relaxed mood"'
                            },
                            description: {
                                type: 'string',
                                description: 'A brief message to accompany the image, explaining what you generated'
                            }
                        },
                        required: ['prompt', 'description']
                    }
                }
            ];
        } else if (includeTools) {
            // High context - only include web_search and generate_image, disable GIF to save tokens
            console.log(`   ⚠️ High context (${inputTokenEstimate} tokens) - GIF tool disabled`);
            config.tools = [
                {
                    name: 'web_search',
                    description: 'Search the web when the user explicitly asks you to find something, or when you genuinely need current information you don\'t have. Don\'t search unless actually needed.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            query: {
                                type: 'string',
                                description: 'The search query'
                            }
                        },
                        required: ['query']
                    }
                },
                {
                    name: 'generate_image',
                    description: 'Generate an image using AI when the user explicitly asks for a picture, selfie, or image. Use this when they say things like "send me a selfie", "show me a picture of...", or "draw/generate an image of...". ONLY use when explicitly requested.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            prompt: {
                                type: 'string',
                                description: 'Detailed context prompts ONLY (do NOT repeat your physical appearance like hair/eye color). IMPORTANT: Use your ACTUAL CURRENT LOCATION from your activity schedule - if you\'re at university, specify the exact place (cafeteria, library, classroom, etc.), if at home specify the room, etc. Be very detailed and specific. Describe: 1) Your facial expression and mood (gentle smile, soft eyes, blushing cheeks, sleepy expression, happy face, shy look, etc.), 2) Your complete outfit with details (white school uniform with red ribbon, pink pajamas with lace trim, casual sweater and skirt, etc.), 3) Your pose and body language (sitting on bed, leaning forward, resting chin on hand, looking at camera, holding food, etc.), 4) SPECIFIC setting based on current location (university cafeteria with food trays and students in background, university library with bookshelves and study desks, cozy bedroom with fairy lights and plushies, modern kitchen with appliances, living room with couch and TV, etc.), 5) Lighting and atmosphere (warm golden hour lighting, soft morning light, dim evening glow, bright natural sunlight, fluorescent cafeteria lighting, etc.). NEVER mention "phone" or "holding phone" - selfies are implied. Example: "gentle smile, relaxed expression, casual white sweater, sitting at cafeteria table with food tray, university cafeteria with students and tables in background, bright fluorescent lighting, casual atmosphere" or "sleepy tired eyes, soft smile, pink pajamas with lace trim, sitting on bed with plushies, cozy bedroom with fairy lights and posters, warm lamp lighting, evening relaxed mood"'
                            },
                            description: {
                                type: 'string',
                                description: 'A brief message to accompany the image, explaining what you generated'
                            }
                        },
                        required: ['prompt', 'description']
                    }
                }
            ];
        }
        
        return await axios.post('https://api.anthropic.com/v1/messages', config, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            },
            timeout: 30000
        });
    }

    try {
        // First API call with tools available
        console.log(`   🤖 Calling Claude API (attempt ${retryCount + 1})...`);
        
        // Rough token estimate: ~3.5 chars per token
        const promptTokenEstimate = Math.ceil(prompt.length / 3.5);
        console.log(`   📊 Estimated input tokens: ${promptTokenEstimate}`);
        
        const response = await makeAPICall([{ role: 'user', content: prompt }], true, promptTokenEstimate);
        console.log(`   ✅ API call successful`);
        const content = response.data.content;
        
        // Check if Claude wants to use tools
        const toolUseBlocks = content.filter(block => block.type === 'tool_use');
        
        if (toolUseBlocks.length > 0) {
            // Claude wants to use one or more tools!
            const toolResults = [];
            const generatedImages = new Map(); // Store generated image data separately

            for (const toolBlock of toolUseBlocks) {
                try {
                    if (toolBlock.name === 'web_search') {
                        // Safety check for query parameter
                        if (!toolBlock.input || !toolBlock.input.query || typeof toolBlock.input.query !== 'string') {
                            console.log(`   ⚠️ Invalid web_search query:`, toolBlock.input);
                            toolResults.push({
                                type: 'tool_result',
                                tool_use_id: toolBlock.id,
                                content: 'Error: No search query provided',
                                is_error: true
                            });
                            continue;
                        }
                        
                        console.log(`   🔍 Misuki is searching: "${toolBlock.input.query}"`);
                        const searchResults = await searchWeb(toolBlock.input.query);
                        toolResults.push({
                            type: 'tool_result',
                            tool_use_id: toolBlock.id,
                            content: JSON.stringify(searchResults)
                        });
                    } else if (toolBlock.name === 'send_gif') {
                        // Safety check for emotion parameter
                        if (!toolBlock.input || !toolBlock.input.emotion || typeof toolBlock.input.emotion !== 'string') {
                            console.log(`   ⚠️ Invalid send_gif emotion:`, toolBlock.input);
                            toolResults.push({
                                type: 'tool_result',
                                tool_use_id: toolBlock.id,
                                content: 'Error: No emotion provided for GIF',
                                is_error: true
                            });
                            continue;
                        }

                        console.log(`   🎨 Misuki wants to send a ${toolBlock.input.emotion} gif`);
                        const gifUrl = await searchGif(toolBlock.input.emotion);
                        toolResults.push({
                            type: 'tool_result',
                            tool_use_id: toolBlock.id,
                            content: gifUrl || 'No gif found'
                        });
                    } else if (toolBlock.name === 'generate_image') {
                        // Safety check for prompt parameter
                        if (!toolBlock.input || !toolBlock.input.prompt || typeof toolBlock.input.prompt !== 'string') {
                            console.log(`   ⚠️ Invalid generate_image prompt:`, toolBlock.input);
                            toolResults.push({
                                type: 'tool_result',
                                tool_use_id: toolBlock.id,
                                content: 'Error: No prompt provided for image generation',
                                is_error: true
                            });
                            continue;
                        }

                        console.log(`   🎨 Misuki is generating an image...`);
                        const imageResult = await generateImage(toolBlock.input.prompt);

                        if (imageResult.success) {
                            // Store image data separately (can't include custom fields in tool_result or toolBlock)
                            generatedImages.set(toolBlock.id, imageResult.imageBase64);

                            toolResults.push({
                                type: 'tool_result',
                                tool_use_id: toolBlock.id,
                                content: 'Image generated successfully! Ready to send.'
                            });
                        } else {
                            toolResults.push({
                                type: 'tool_result',
                                tool_use_id: toolBlock.id,
                                content: `Image generation failed: ${imageResult.error}`,
                                is_error: true
                            });
                        }
                    }
                } catch (toolError) {
                    // If a tool fails, provide error result but don't crash
                    console.error(`⚠️ Tool ${toolBlock.name} failed:`, toolError.message);
                    toolResults.push({
                        type: 'tool_result',
                        tool_use_id: toolBlock.id,
                        content: 'Tool temporarily unavailable',
                        is_error: true
                    });
                }
            }
            
            // Send results back to Claude - ALLOW TOOLS IN FOLLOW-UP
            console.log(`   🤖 Sending tool results back to Claude...`);
            const followUpResponse = await makeAPICall([
                { role: 'user', content: prompt },
                { role: 'assistant', content: content },
                { role: 'user', content: toolResults }
            ], true); // ALLOW TOOLS in follow-up!
            console.log(`   ✅ Tool response received`);
            
            // Log what's in the response for debugging
            console.log(`   📋 Response content blocks:`, followUpResponse.data.content.map(b => b.type).join(', '));
            console.log(`   🛑 Stop reason:`, followUpResponse.data.stop_reason);
            
            // Check if API returned empty content
            if (followUpResponse.data.content.length === 0) {
                console.log(`   ⚠️ API returned empty content array!`);
                console.log(`   📄 Full API response:`, JSON.stringify(followUpResponse.data, null, 2));
                
                // Empty content - provide fallback WITHOUT GIF
                return {
                    text: "^^", // Simple fallback text
                    gifUrl: null, // Don't send GIF if response is empty
                    gifEmotion: null,
                    imageData: null
                };
            }
            
            // Get the final text response and any gif URL
            const textBlock = followUpResponse.data.content.find(block => block.type === 'text');
            
            // IMPROVED ERROR HANDLING - provide a better fallback
            let responseText;
            if (textBlock && textBlock.text && textBlock.text.trim()) {
                responseText = textBlock.text.trim();
            } else {
                // No text block found - check if Claude is trying to use more tools
                const additionalToolUse = followUpResponse.data.content.filter(block => block.type === 'tool_use');
                if (additionalToolUse.length > 0) {
                    console.log(`   🔧 Claude wants to use ${additionalToolUse.length} more tool(s), processing...`);
                    
                    // Process additional tools (allow ONE more round)
                    const additionalToolResults = [];
                    const additionalGeneratedImages = new Map(); // Store additional generated images

                    for (const toolBlock of additionalToolUse) {
                        try {
                            if (toolBlock.name === 'web_search') {
                                if (!toolBlock.input?.query) {
                                    additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Error', is_error: true });
                                    continue;
                                }
                                console.log(`   🔍 Additional search: "${toolBlock.input.query}"`);
                                const results = await searchWeb(toolBlock.input.query);
                                additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: JSON.stringify(results) });
                            } else if (toolBlock.name === 'send_gif') {
                                if (!toolBlock.input?.emotion) {
                                    additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Error', is_error: true });
                                    continue;
                                }
                                const gifUrl = await searchGif(toolBlock.input.emotion);
                                additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: gifUrl || 'No gif found' });
                            } else if (toolBlock.name === 'generate_image') {
                                if (!toolBlock.input?.prompt) {
                                    additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Error', is_error: true });
                                    continue;
                                }
                                console.log(`   🎨 Additional image generation...`);
                                const imageResult = await generateImage(toolBlock.input.prompt);
                                if (imageResult.success) {
                                    // Store image data separately in Map
                                    additionalGeneratedImages.set(toolBlock.id, imageResult.imageBase64);
                                    additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Image generated successfully!' });
                                } else {
                                    additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: `Error: ${imageResult.error}`, is_error: true });
                                }
                            }
                        } catch (err) {
                            additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Error', is_error: true });
                        }
                    }
                    
                    // Merge additional generated images into main map
                    additionalGeneratedImages.forEach((imageData, toolId) => {
                        generatedImages.set(toolId, imageData);
                    });

                    // Final call - NO MORE TOOLS
                    console.log(`   🤖 Final call with additional results...`);
                    const finalResponse = await makeAPICall([
                        { role: 'user', content: prompt },
                        { role: 'assistant', content: content },
                        { role: 'user', content: toolResults },
                        { role: 'assistant', content: followUpResponse.data.content },
                        { role: 'user', content: additionalToolResults }
                    ], false);

                    const finalText = finalResponse.data.content.find(b => b.type === 'text');
                    responseText = finalText?.text?.trim() || "Here's what I found! ^^";
                } else {
                    // Check if GIF was used - provide gentle fallback
                    const gifToolUsed = toolUseBlocks.find(block => block.name === 'send_gif');
                    if (gifToolUsed) {
                        // Empty response but GIF was requested - provide minimal text, but DON'T send GIF
                        console.log(`   💡 Empty response with GIF request - adding fallback text without GIF`);
                        responseText = '...';
                    } else {
                        // No text and no more tools - something unusual happened
                        console.log(`   ⚠️ No text block in response! Content types:`, 
                            followUpResponse.data.content.map(b => b.type));
                        console.log(`   📄 Full response:`, JSON.stringify(followUpResponse.data.content, null, 2));
                        responseText = "Hmm... (⸝⸝ᵕᴗᵕ⸝⸝)";
                    }
                }
            }
            
            responseText = responseText.replace(/\*[^*]+\*/g, '');
            responseText = responseText.replace(/^["']|["']$/g, '');
            responseText = responseText.replace(/\s+/g, ' ').trim();
            
            // Check if she wants to send a GIF (only if we have valid text response)
            const gifToolBlock = toolUseBlocks.find(block => block.name === 'send_gif');
            let gifUrl = null;
            let gifEmotion = null;

            // Only process GIF if we have a proper text response
            if (responseText && responseText !== '...' && responseText !== 'Hmm... (⸝⸝ᵕᴗᵕ⸝⸝)' && gifToolBlock) {
                const gifResult = toolResults.find(r => r.tool_use_id === gifToolBlock.id);
                gifUrl = gifResult?.content && gifResult.content !== 'No gif found' ? gifResult.content : null;
                gifEmotion = gifToolBlock.input?.emotion || null;
            }

            // Check if she wants to generate an image
            const imageToolBlock = toolUseBlocks.find(block => block.name === 'generate_image');
            let imageData = null;

            if (imageToolBlock) {
                // Image data is stored in the generatedImages Map (not in tool_result or toolBlock)
                imageData = generatedImages.get(imageToolBlock.id) || null;
            }

            return {
                text: responseText,
                gifUrl: gifUrl,
                gifEmotion: gifEmotion,
                imageData: imageData
            };
        } else {
            // No tool use - just return the text response
            const textBlock = content.find(block => block.type === 'text');
            let responseText = textBlock && textBlock.text ? textBlock.text.trim() : "...^^";
            
            responseText = responseText.replace(/\*[^*]+\*/g, '');
            responseText = responseText.replace(/^["']|["']$/g, '');
            responseText = responseText.replace(/\s+/g, ' ').trim();
            
            return {
                text: responseText,
                gifUrl: null,
                gifEmotion: null,
                imageData: null
            };
        }
    } catch (error) {
        // Enhanced error detection and retry logic
        const errorType = error.response?.data?.error?.type;
        const errorMessage = error.response?.data?.error?.message || error.message;
        const statusCode = error.response?.status;
        
        // List of errors that should be retried
        const retryableErrors = [
            'overloaded_error',
            'rate_limit_error', 
            'timeout',
            'ECONNRESET',
            'ETIMEDOUT',
            'ENOTFOUND',
            'ECONNREFUSED'
        ];
        
        const shouldRetry = retryableErrors.some(err => 
            errorType?.includes(err) || 
            errorMessage?.includes(err) ||
            error.code?.includes(err)
        ) || (statusCode >= 500 && statusCode < 600); // Retry on 5xx errors
        
        if (shouldRetry && retryCount < 10) {
            // Exponential backoff: 1s, 2s, 4s, 8s, etc. (max 16s)
            const delay = Math.min(1000 * Math.pow(2, retryCount), 16000);
            console.log(`⚠️ API error (${errorType || error.code || statusCode}), retrying in ${delay}ms (attempt ${retryCount + 1}/10)...`);
            console.log(`   Error details: ${errorMessage}`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
            return generateMisukiResponse(userMessage, conversationHistory, userProfile, currentActivity, isDM, otherUsers, otherConversations, channelContext, serverContext, reactionExplanation, retryCount + 1);
        }
        
        // Log detailed error info for debugging
        console.error('❌ Anthropic API Error (no retry):');
        console.error('   Type:', errorType || 'unknown');
        console.error('   Status:', statusCode || 'unknown');
        console.error('   Message:', errorMessage);
        console.error('   Code:', error.code);
        if (error.response?.data) {
            console.error('   Full response:', JSON.stringify(error.response.data));
        }
        
        return {
            text: "Hmm... something's not working right (˶ᵕ ᵕ˶)",
            gifUrl: null,
            gifEmotion: null
        };
    }
}

// =========================================
// BOT EVENTS
// =========================================

client.once('ready', () => {
    console.log(`💕 Misuki is online as ${client.user.tag}!`);
    console.log(`🤖 Using Anthropic Claude API`);
    console.log(`👥 Multi-user support: ENABLED`);
    console.log(`🤝 Relationship system: ENABLED`);
    console.log(`💝 Nickname system: ENABLED`);
    console.log(`🎨 Dynamic GIF search: ENABLED`);
    console.log(`🌐 Web search: ENABLED`);
    console.log(`🎯 Discord status: DYNAMIC (updates every 2 min)`);
    console.log(`💌 Proactive messaging: ENABLED (initiates conversations with Dan)`);
    console.log(`🤖 Autonomous messaging: ENABLED (DM or server channel ${ALLOWED_CHANNEL_ID})`);
    console.log(`   ⏱️  Checks every 15 min, max ${MAX_DAILY_PROACTIVE_MESSAGES}/day`);
    console.log(`🔄 MySQL connection: POOLED (prevents timeout errors)`);

    // Debug: Log emoji cache info
    console.log(`\n😊 Emoji Debug Info:`);
    console.log(`   📊 Total emojis in cache: ${client.emojis.cache.size}`);
    console.log(`   🏰 Servers bot is in: ${client.guilds.cache.size}`);

    // Check if bot has access to required emoji server
    const emojiServer = client.guilds.cache.get(EMOJI_SERVER_ID);
    if (emojiServer) {
        console.log(`   ✅ Bot IS in emoji server: ${emojiServer.name}`);
    } else {
        console.log(`   ⚠️ Bot is NOT in emoji server (ID: ${EMOJI_SERVER_ID})`);
        console.log(`   ⚠️ Custom emoji reactions will NOT work!`);
    }

    // List all available custom emojis
    if (client.emojis.cache.size > 0) {
        console.log(`   📝 First 10 available emojis:`);
        const emojis = Array.from(client.emojis.cache.values()).slice(0, 10);
        emojis.forEach(e => console.log(`      - ${e.name} (${e.id})`));
    }
    console.log('');

    updateDiscordStatus();
    // Update status every 2 minutes for micro-status updates!
    // Also checks for proactive messaging opportunities
    setInterval(updateDiscordStatus, 2 * 60 * 1000);

    // Start autonomous messaging system - checks every 15 minutes
    console.log(`\n🤖 Starting autonomous messaging system...`);
    console.log(`   ⏱️  Check interval: ${SPONTANEOUS_CHECK_INTERVAL / 60000} minutes`);
    console.log(`   📊 Daily limit: ${MAX_DAILY_PROACTIVE_MESSAGES} messages`);
    console.log(`   ⏳ Minimum gap: ${MIN_MESSAGE_GAP / 60000} minutes`);

    // Run first check after 2 minutes (give bot time to initialize)
    setTimeout(() => {
        console.log('\n💭 Running first spontaneous message check...');
        checkSpontaneousMessage().catch(console.error);
    }, 2 * 60 * 1000);

    // Then check periodically
    setInterval(() => {
        checkSpontaneousMessage().catch(console.error);
    }, SPONTANEOUS_CHECK_INTERVAL);

    connectDB().catch(console.error);
});

// Helper function to check if a user is currently typing in a channel
function isUserTyping(userId, channelId) {
    const channelTypers = activeTypers.get(channelId);
    if (!channelTypers) return false;

    const typerData = channelTypers.get(userId);
    if (!typerData) return false;

    // Check if typing data is recent (within 10 seconds)
    const now = Date.now();
    return (now - typerData.lastUpdate) < 10000;
}

// Slash command handler for admin controls
client.on('interactionCreate', async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === 'selfie') {
        // Check if user is Dan (admin)
        if (interaction.user.id !== MAIN_USER_ID) {
            await interaction.reply({ content: 'Only Dan can use this command!', ephemeral: true });
            return;
        }

        const subcommand = interaction.options.getSubcommand();

        if (subcommand === 'mode') {
            const mode = interaction.options.getString('mode');

            if (mode === 'private') {
                selfieMode = 'private';
                await interaction.reply({ content: '🔒 Selfie mode set to **private**. Only you can request selfies now.', ephemeral: true });
                console.log('📸 Selfie mode changed to: private (Dan only)');
            } else if (mode === 'all') {
                selfieMode = 'all';
                await interaction.reply({ content: '🌐 Selfie mode set to **all**. Everyone can request selfies now.', ephemeral: true });
                console.log('📸 Selfie mode changed to: all');
            }
        } else if (subcommand === 'forcesend') {
            // Force Misuki to send a selfie
            const additionalPrompt = interaction.options.getString('prompt');
            const unaware = interaction.options.getBoolean('unaware') || false;
            console.log('📸 Force selfie requested by Dan', additionalPrompt ? `with prompt: "${additionalPrompt}"` : '', unaware ? '(unaware mode - won\'t save to database)' : '');

            // Defer reply since image generation takes time
            await interaction.deferReply();

            try {
                // Get user profile to get database user_id
                const userProfile = await getUserProfile(interaction.user.id, interaction.user.username);

                // Get current activity for context
                const currentActivity = getMisukiCurrentActivity();

                // Generate image with current context + additional prompt
                const mood = currentActivity.mood || 'happy';
                // Note: Don't include outfit in contextPrompt - it's handled by the outfit system based on currentActivity
                let contextPrompt = `${mood} expression, natural pose, indoor setting, soft lighting, relaxed atmosphere`;
                if (additionalPrompt) {
                    contextPrompt = `${contextPrompt}, ${additionalPrompt}`;
                }
                const imageResult = await generateImage(contextPrompt, currentActivity);

                if (imageResult.success) {
                    // Convert base64 to buffer
                    const imageBuffer = Buffer.from(imageResult.imageBase64, 'base64');

                    // Reluctant/playful messages
                    const messages = [
                        "Fine fine... here you go (˶ᵕ ᵕ˶)",
                        "Okay okay, you win >///<",
                        "Alright, just for you~ ^^",
                        "You're so pushy... here (˶˃ ᵕ ˂˶)",
                        "Fineee, one selfie coming up! ^^"
                    ];
                    const randomMessage = messages[Math.floor(Math.random() * messages.length)];

                    // Send image with message
                    await interaction.editReply({
                        content: randomMessage,
                        files: [{
                            attachment: imageBuffer,
                            name: 'misuki-selfie.png'
                        }]
                    });

                    console.log('✅ Force selfie sent successfully');

                    // Save to conversation history so Misuki remembers (as if naturally asked)
                    // Include the additional prompt details so she knows what she sent
                    // Use database user_id, not Discord ID
                    // UNLESS unaware mode is enabled - then she won't remember
                    if (!unaware) {
                        const userMessage = additionalPrompt
                            ? `send me a selfie of you ${additionalPrompt}`
                            : 'send me a selfie';
                        const misukiResponse = `${randomMessage} [Sent a selfie image]`;
                        await saveConversation(userProfile.user_id, userMessage, misukiResponse, 'playful', 'dm');
                        console.log('💾 Saved selfie to conversation history');
                    } else {
                        console.log('🚫 Unaware mode - selfie NOT saved to database');
                    }
                } else {
                    await interaction.editReply({ content: `Umm... the selfie didn't work >.<\nError: ${imageResult.error}` });
                    console.log('❌ Force selfie generation failed:', imageResult.error);
                }
            } catch (error) {
                console.error('❌ Force selfie error:', error);
                await interaction.editReply({ content: 'Something went wrong... (╥﹏╥)' });
            }
        }
    } else if (interaction.commandName === 'imagine') {
        // Check if user is Dan (admin)
        if (interaction.user.id !== MAIN_USER_ID) {
            await interaction.reply({ content: 'Only Dan can use this command!', ephemeral: true });
            return;
        }

        const additionalPrompt = interaction.options.getString('prompt');
        console.log('📸 Candid image generation requested by Dan', additionalPrompt ? `with prompt: "${additionalPrompt}"` : '');

        // Defer reply since image generation takes time
        await interaction.deferReply();

        try {
            // Get current activity for context
            const currentActivity = getMisukiCurrentActivity();

            // Build candid scene prompt based on activity
            let scenePrompt = '';

            // Determine location and pose based on activity type
            if (currentActivity.type === 'sleep') {
                scenePrompt = 'sleeping peacefully in bed, lying down, eyes closed, relaxed, bedroom setting, dim lighting, cozy atmosphere';
            } else if (currentActivity.type === 'class' || currentActivity.type === 'lab') {
                scenePrompt = 'in university classroom, sitting at desk, focused expression, classroom background, natural lighting';
            } else if (currentActivity.type === 'studying') {
                scenePrompt = 'studying at desk, sitting position, textbooks nearby, concentrated expression, indoor room, warm lighting';
            } else if (currentActivity.type === 'commute') {
                scenePrompt = 'walking outside, standing or moving, outdoor street scene, natural daylight';
            } else if (currentActivity.type === 'personal') {
                // Check specific activity for shower/changing
                if (currentActivity.activity.toLowerCase().includes('shower')) {
                    scenePrompt = 'in shower, standing, water droplets, steamy bathroom, wet hair, intimate setting, soft lighting';
                } else if (currentActivity.activity.toLowerCase().includes('bed') || currentActivity.activity.toLowerCase().includes('waking')) {
                    scenePrompt = 'in bedroom, sitting on bed or standing nearby, bedroom background, morning/evening lighting';
                } else if (currentActivity.activity.toLowerCase().includes('eating') || currentActivity.activity.toLowerCase().includes('breakfast') || currentActivity.activity.toLowerCase().includes('lunch') || currentActivity.activity.toLowerCase().includes('dinner')) {
                    scenePrompt = 'at dining table, sitting, eating, kitchen or dining room background, warm indoor lighting';
                } else if (currentActivity.activity.toLowerCase().includes('preparing') || currentActivity.activity.toLowerCase().includes('cooking')) {
                    scenePrompt = 'in kitchen, standing at counter, cooking or preparing food, kitchen background, warm lighting';
                } else {
                    scenePrompt = 'at home, casual indoor setting, natural pose, comfortable atmosphere, warm lighting';
                }
            } else if (currentActivity.type === 'free') {
                scenePrompt = 'relaxing at home, casual pose, comfortable setting, living room or bedroom, soft lighting';
            } else if (currentActivity.type === 'church') {
                scenePrompt = 'in church, sitting in pew, peaceful expression, church interior background, soft natural light';
            } else {
                // Default candid scene
                scenePrompt = 'candid moment, natural pose, indoor setting, soft lighting, comfortable atmosphere';
            }

            // Add additional prompt if provided
            if (additionalPrompt) {
                scenePrompt = `${scenePrompt}, ${additionalPrompt}`;
            }

            console.log(`   📸 Candid scene: "${scenePrompt}"`);
            console.log(`   ⏰ Current activity: ${currentActivity.activity} (${currentActivity.type})`);

            const imageResult = await generateImage(scenePrompt, currentActivity);

            if (imageResult.success) {
                // Convert base64 to buffer
                const imageBuffer = Buffer.from(imageResult.imageBase64, 'base64');

                // Send image without any message (pure candid shot)
                await interaction.editReply({
                    files: [{
                        attachment: imageBuffer,
                        name: 'misuki-candid.png'
                    }]
                });

                console.log('✅ Candid image sent successfully (not saved to database)');
            } else {
                await interaction.editReply({ content: `Failed to generate image.\nError: ${imageResult.error}` });
                console.log('❌ Candid image generation failed:', imageResult.error);
            }
        } catch (error) {
            console.error('❌ Imagine command error:', error);
            await interaction.editReply({ content: 'Something went wrong generating the image.' });
        }
    }
});

client.on('messageCreate', async (message) => {
    if (message.author.bot) return;

    const isDM = !message.guild;

    // Only respond in DMs OR in the allowed channel
    if (!isDM) {
        // In a server - check if it's the allowed channel
        if (message.channel.id !== ALLOWED_CHANNEL_ID) {
            return; // Ignore messages in other channels
        }
        // In allowed channel - respond to all messages (no mention required!)
    }

    const userId = message.author.id;
    const channelId = message.channel.id;

    // Track last message time for autonomous messaging system
    lastMessageTimes[userId] = Date.now();

    // MESSAGE BUFFERING SYSTEM - Wait for user to finish typing
    let bufferData = messageBuffer.get(userId);

    if (!bufferData) {
        bufferData = { messages: [], timeout: null, checkInterval: null };
        messageBuffer.set(userId, bufferData);
    }

    // Add message to buffer
    bufferData.messages.push(message);

    // Clear existing timeout and interval if any
    if (bufferData.timeout) {
        clearTimeout(bufferData.timeout);
    }
    if (bufferData.checkInterval) {
        clearInterval(bufferData.checkInterval);
    }

    console.log(`📨 Message from ${message.author.username} - buffering (${bufferData.messages.length} message(s) so far, waiting for more...)`);

    // Set a new timeout to check if user is still typing after 6 seconds
    bufferData.timeout = setTimeout(async () => {
        console.log(`⏱️  6 seconds passed - checking if ${message.author.username} is still typing...`);

        // Check if user is still typing
        if (isUserTyping(userId, channelId)) {
            console.log(`⌨️  ${message.author.username} is still typing - waiting...`);

            // Keep checking every 2 seconds until they stop typing
            bufferData.checkInterval = setInterval(async () => {
                if (!isUserTyping(userId, channelId)) {
                    console.log(`✅ ${message.author.username} stopped typing - processing ${bufferData.messages.length} message(s)`);
                    clearInterval(bufferData.checkInterval);

                    // Get all buffered messages
                    const allBufferedMessages = bufferData.messages;
                    const lastMessage = allBufferedMessages[allBufferedMessages.length - 1];

                    // Clear buffer
                    messageBuffer.delete(userId);

                    // Process with the last message (will reply to it)
                    await processMessage(lastMessage, allBufferedMessages);
                } else {
                    console.log(`⌨️  ${message.author.username} still typing...`);
                }
            }, 2000);
        } else {
            console.log(`✅ ${message.author.username} not typing - processing ${bufferData.messages.length} message(s)`);

            // Get all buffered messages
            const allBufferedMessages = bufferData.messages;
            const lastMessage = allBufferedMessages[allBufferedMessages.length - 1];

            // Clear buffer
            messageBuffer.delete(userId);

            // Process with the last message (will reply to it)
            await processMessage(lastMessage, allBufferedMessages);
        }
    }, 6000);
});

// Main message processing function
async function processMessage(message, allBufferedMessages = [message]) {
    const isDM = !message.guild;
    const userId = message.author.id;

    // Log combined message info
    if (allBufferedMessages.length > 1) {
        console.log(`\n📦 Combining ${allBufferedMessages.length} messages from ${message.author.username}:`);
        allBufferedMessages.forEach((msg, i) => {
            console.log(`   ${i + 1}. "${msg.content.substring(0, 50)}${msg.content.length > 50 ? '...' : ''}"`);
        });
    }

    // SELF-INTERRUPTION LOCK: Prevent parallel responses to same user
    let lockData = userProcessingLock.get(userId);

    if (!lockData) {
        lockData = { isProcessing: false, latestMessageId: null, pendingMessages: [] };
        userProcessingLock.set(userId, lockData);
    }

    if (lockData.isProcessing) {
        // Already processing a message from this user!
        // Collect this message to concatenate with others
        console.log(`🔄 ${message.author.username} sent new message while processing - buffering`);
        lockData.pendingMessages.push(message);
        lockData.latestMessageId = message.id;
        return; // Don't process immediately, let the current processing finish and pick up all pending messages
    }

    // Set lock
    lockData.isProcessing = true;
    lockData.latestMessageId = message.id;
    lockData.pendingMessages = []; // Clear any old pending messages
    const currentMessageId = message.id;
    
    // SERVER QUEUE SYSTEM
    if (!isDM) {
        const channelId = message.channel.id;
        let queueData = conversationQueue.get(channelId);
        
        if (!queueData) {
            queueData = { currentUser: null, queue: [], isSending: false };
            conversationQueue.set(channelId, queueData);
        }
        
        // If we're currently responding to someone
        if (queueData.isSending && queueData.currentUser) {
            if (queueData.currentUser === message.author.id) {
                // SAME PERSON interrupting themselves - stop current response and process new message
                console.log(`🔄 ${message.author.username} is continuing their thought - pausing to process...`);
                
                // Adorable pause: wait 2 seconds (not typing) before processing
                // This makes it feel like she's reading and processing the new info
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                console.log(`   💭 Processing continuation from ${message.author.username}...`);
                // Don't queue, just process immediately (the isSending flag stays true, currentUser stays same)
                // This will naturally override the previous response
            } else {
                // DIFFERENT PERSON - add to queue
                queueData.queue.push({
                    message: message,
                    authorId: message.author.id,
                    authorName: message.author.username
                });
                
                // Send "wait" message
                const waitMsg = getQueueMessage('wait', message.author.id, queueData.currentUser);
                await message.channel.send(waitMsg);
                
                console.log(`⏸️ ${message.author.username} queued (currently talking to user ${queueData.currentUser})`);
                return; // Don't process now
            }
        } else {
            // Not currently responding to anyone, set current user
            queueData.currentUser = message.author.id;
            queueData.isSending = true;
        }
    }
    
    console.log(`\n📨 Message from ${message.author.username}`);
    console.log(`📍 Context: ${isDM ? 'Private DM' : 'Server Channel'}`);
    
    // Retry logic for the entire message handling process
    let retryCount = 0;
    const maxRetries = 10;
    
    while (retryCount < maxRetries) {
        let stopTyping = null;
        
        try {
            // Get or create user profile
            const userProfile = await getUserProfile(message.author.id, message.author.username);
            const isMainUser = message.author.id === MAIN_USER_ID;
            
            // EMOJI REACTIONS - React to message based on content (before typing/responding)
            // Store what reactions we used so we can tell Claude about them
            let usedReactions = [];
            let reactionExplanation = '';

            console.log(`🎭 Setting up emoji reaction handler (isDM: ${isDM})`);

            setTimeout(async () => {
                console.log(`🎭 Emoji reaction callback executing...`);
                try {
                    // Check permissions first (for server messages)
                    if (!isDM) {
                        const botMember = message.guild.members.cache.get(client.user.id);
                        const permissions = message.channel.permissionsFor(botMember);

                        if (!permissions.has('AddReactions')) {
                            console.log(`   ⚠️ Bot lacks ADD_REACTIONS permission in this channel!`);
                            return; // Skip reactions
                        }
                    }

                    const reactEmojis = getReactionEmojis(message.content, userProfile, getMisukiCurrentActivity());

                    console.log(`🎭 getReactionEmojis returned ${reactEmojis.length} emoji(s):`, reactEmojis);

                    if (reactEmojis.length === 0) {
                        console.log(`🎭 No reactions selected for this message`);
                        return; // No reactions to add
                    }

                    // 20% chance to actually react
                    const reactionChance = Math.random();
                    if (reactionChance > 0.20) {
                        console.log(`🎭 Reaction skipped (chance: ${(reactionChance * 100).toFixed(1)}% > 20%)`);
                        return;
                    }

                    console.log(`🎭 Reaction proceeding (chance: ${(reactionChance * 100).toFixed(1)}% <= 20%)`);
                    usedReactions = reactEmojis;

                    let successCount = 0;
                    for (const emoji of reactEmojis) {
                        // For custom emojis, we need to fetch from client cache or construct proper identifier
                        // For built-in Unicode emojis, just use the emoji directly
                        let emojiToReact;

                        if (emoji.id.match(/^\d+$/)) {
                            // Custom emoji - try to get from cache first
                            const cachedEmoji = client.emojis.cache.get(emoji.id);
                            if (cachedEmoji) {
                                // Use the cached emoji object (best approach)
                                emojiToReact = cachedEmoji;
                                console.log(`   🔍 Using cached emoji: ${emoji.name}`);
                            } else {
                                // If not in cache, the bot might not have access to this emoji
                                console.log(`   ⚠️ Emoji ${emoji.name} (${emoji.id}) not in cache!`);
                                console.log(`   🔍 Available emoji count: ${client.emojis.cache.size}`);
                                console.log(`   🔍 Trying alternate method...`);

                                // Try fetching from the specific server
                                if (!isDM && message.guild) {
                                    const guildEmoji = message.guild.emojis.cache.get(emoji.id);
                                    if (guildEmoji) {
                                        emojiToReact = guildEmoji;
                                        console.log(`   ✅ Found emoji in current server's cache!`);
                                    } else {
                                        console.log(`   ❌ Emoji not in current server either - skipping`);
                                        continue; // Skip this emoji
                                    }
                                } else {
                                    continue; // Skip this emoji
                                }
                            }
                        } else {
                            // Built-in Unicode emoji (like 🫂, 🍜)
                            emojiToReact = emoji.id;
                        }

                        try {
                            await message.react(emojiToReact);
                            successCount++;
                            console.log(`   ✅ Successfully reacted with ${emoji.name}`);
                        } catch (err) {
                            console.log(`   ⚠️ Couldn't react with ${emoji.name} (${emoji.id}):`, err.message);
                            console.log(`   📝 Error details:`, err);
                            // Remove from usedReactions if it failed
                            const index = usedReactions.findIndex(e => e.id === emoji.id);
                            if (index > -1) usedReactions.splice(index, 1);
                        }
                        // Small delay between multiple reactions
                        await new Promise(resolve => setTimeout(resolve, 300));
                    }
                    if (successCount > 0) {
                        console.log(`   💕 Reacted with ${successCount} emoji(s)`);
                    }
                } catch (err) {
                    // Reactions are optional, don't break if they fail
                    console.log(`   ⚠️ Reaction error:`, err.message);
                }
            }, 500); // 500ms delay before reacting (feels natural)
            
            // Wait for reactions to be added (600ms total - 500ms delay + 100ms buffer)
            await new Promise(resolve => setTimeout(resolve, 600));
            
            // Get user name for reaction explanation
            const userName = userProfile.nickname || userProfile.display_name || message.author.username;
            
            // Build reaction explanation for Claude
            if (usedReactions.length > 0) {
                const emojiNames = getReactionNames(usedReactions, userProfile, message.content);
                reactionExplanation = `\n💭 IMPORTANT: You just reacted to ${userName}'s message with ${emojiNames} because ${getReactionReason(message.content, userProfile)}. This was an emotional response - you can reference this naturally if they mention it.\n`;
            }
            
            stopTyping = await startTyping(message.channel);

            // Combine all buffered messages into one
            let userMessage = '';
            if (allBufferedMessages.length > 1) {
                // Multiple messages - combine them
                const combinedMessages = [];
                for (const msg of allBufferedMessages) {
                    let msgContent = msg.content.replace(`<@${client.user.id}>`, '').trim();
                    msgContent = await replaceDiscordMentions(msgContent, msg);
                    combinedMessages.push(msgContent);
                }
                userMessage = combinedMessages.join('\n');
                console.log(`💬 ${userName} [Trust: ${userProfile.trust_level}/10] (${allBufferedMessages.length} messages combined):`);
                console.log(`   ${userMessage.substring(0, 200)}${userMessage.length > 200 ? '...' : ''}`);
            } else {
                // Single message
                userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
                // Replace Discord mentions with readable names
                userMessage = await replaceDiscordMentions(userMessage, message);
                console.log(`💬 ${userName} [Trust: ${userProfile.trust_level}/10]: ${userMessage}`);
            }

            // Check if this message is a reply to another message
            let replyContext = '';
            if (message.reference && message.reference.messageId) {
                try {
                    const repliedTo = await message.channel.messages.fetch(message.reference.messageId);
                    if (repliedTo) {
                        const repliedAuthor = repliedTo.author.id === client.user.id
                            ? 'you (Misuki)'
                            : (repliedTo.author.id === MAIN_USER_ID
                                ? 'Dan'
                                : repliedTo.author.username);

                        let repliedContent = repliedTo.content.replace(`<@${client.user.id}>`, '').trim();
                        repliedContent = await replaceDiscordMentions(repliedContent, repliedTo);

                        // Truncate if too long
                        if (repliedContent.length > 100) {
                            repliedContent = repliedContent.substring(0, 100) + '...';
                        }

                        replyContext = `\n[${userName} is replying to ${repliedAuthor}'s message: "${repliedContent}"]\n`;
                        console.log(`   🔗 Reply context: ${userName} → ${repliedAuthor}`);
                    }
                } catch (error) {
                    // Couldn't fetch the replied message, that's okay
                    console.log(`   ⚠️  Couldn't fetch replied message: ${error.message}`);
                }
            }

            // Add reply context to the user message if it exists
            if (replyContext) {
                userMessage = replyContext + userMessage;
            }
            
            const currentActivity = getMisukiCurrentActivity();
            console.log(`📅 Current activity: ${currentActivity.activity} ${currentActivity.emoji}`);
            
            // Load conversation history
            // In DMs: Use individual conversation history (includes DM + Server messages now!)
            // In servers: Use shared channel history for better multi-user context
            let history;
            let historySource;
            
            if (isDM) {
                // DM: Use individual conversation history (now includes server messages too!)
                const historyLimit = 8; // Reduced for token efficiency
                history = await getConversationHistory(userProfile.user_id, historyLimit);
                historySource = 'individual history (DMs + Servers)';
            } else {
                // Server: Get channel messages and convert to conversation history format
                const channelMessages = await getRecentChannelMessages(message.channel, 8); // Reduced for token efficiency
                
                // Parse channel messages into conversation history format
                // This is a simplified version - channel context is already formatted
                history = []; // Empty because we'll use channelMessages directly
                historySource = 'shared channel history';
            }
            
            console.log(`📚 Memory: ${historySource}`);
            
            // For non-Dan users, check if we should update their summary
            // Update every 10 messages
            if (!isMainUser && userProfile.total_messages % 10 === 0) {
                console.log(`   🔄 Triggering summary update for ${userName} (msg #${userProfile.total_messages})`);
                // Don't await - let it update in background
                updateUserSummary(userProfile.user_id, userName).catch(err => {
                    console.error('Background summary update failed:', err.message);
                });
            }
            
            // Get other users context (only for main user in DMs)
            const otherUsers = (isMainUser && isDM) ? await getOtherUsers(message.author.id, 5) : [];
            const otherConversations = (isMainUser && isDM) ? await getOtherUsersConversations(message.author.id, 3) : [];
            
            // For servers, get the channel messages context and server awareness
            const recentChannelMessages = !isDM ? await getRecentChannelMessages(message.channel, 8) : '';
            const serverContext = !isDM ? await getServerContext(message.guild) : '';
            
            // Generate response
            const responseData = await generateMisukiResponse(
                userMessage, 
                history, 
                userProfile, 
                currentActivity, 
                isDM, 
                otherUsers,
                otherConversations,
                recentChannelMessages,
                serverContext,
                reactionExplanation
            );
            
            if (stopTyping) stopTyping();
            
            const response = responseData.text;
            const gifUrl = responseData.gifUrl;
            const gifEmotion = responseData.gifEmotion;
            const imageData = responseData.imageData;
            
            // Ensure we always have text - if empty, provide a fallback
            const finalResponse = response && response.trim() ? response : "^^";

            // Save conversation - use user_id from profile
            // IMPORTANT: Save ALL messages (DM + Server) for cross-context memory
            // This allows Dan to ask about server conversations in private DMs
            const contextType = isDM ? 'dm' : 'server';

            // If image was generated, append a note to the saved response so Misuki remembers
            let savedResponse = finalResponse;
            if (imageData) {
                savedResponse = `${finalResponse} [Sent a selfie image]`;
            }

            await saveConversation(userProfile.user_id, userMessage, savedResponse, 'gentle', contextType);

            console.log(`   💾 Saved to database: ${isDM ? 'DM' : 'Server'} conversation with ${userName}`);

            // Update trust level based on positive interactions
            await updateTrustLevel(userProfile.user_id, userMessage, isMainUser);

            const emotion = userMessage.toLowerCase().includes('sad') ||
                           userMessage.toLowerCase().includes('tired') ||
                           userMessage.toLowerCase().includes('upset') ? 'negative' : 'positive';
            await updateEmotionalState(userProfile.user_id, emotion);
            
            // Smart message splitting - but keep URLs and kaomoji intact!
            let messages = [];
            
            // Always send text (we ensured finalResponse exists above)
            // Check if response contains URLs
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const hasUrl = urlRegex.test(finalResponse);
            
            if (hasUrl) {
                // If there's a URL, don't split the message - send it all at once
                messages = [finalResponse];
            } else {
                // Common kaomoji patterns that shouldn't be broken
                const kaomojiPatterns = [
                    /\([\^˶ᵔᴗ_><꒰ᐢ\-ω≧ᵕ]+[\s\.][\s\.]?[\^˶ᵔᴗ_><꒰ᐢ\-ω≧ᵕ]+\)/g,  // (˶ᵔ ᵕ ᵔ˶), (˶ᵕ ᵕ˶)
                    />\.[\s]?</g,  // >.<, > . <
                    /꒰ᐢ\.[\s\.]?\.?\s?ᐢ꒱/g,  // ꒰ᐢ. . ᐢ꒱
                    /\^\^/g,  // ^^
                    />\/\/</g,  // >///<
                    />\/\/\/</g,  // >////<
                    /\(\s?[><\-\^ᵕω]\s?[><\-_ᵕω]\s?[><\-\^ᵕω]\s?\)/g  // Generic emoticons
                ];
                
                // Protect kaomoji by temporarily replacing them
                let protectedText = finalResponse;
                const kaomojiMap = new Map();
                let kaomojiIndex = 0;
                
                kaomojiPatterns.forEach(pattern => {
                    protectedText = protectedText.replace(pattern, (match) => {
                        const placeholder = `__KAOMOJI${kaomojiIndex}__`;
                        kaomojiMap.set(placeholder, match);
                        kaomojiIndex++;
                        return placeholder;
                    });
                });
                
                // Now split on sentence endings (with kaomoji protected)
                const sentences = protectedText.match(/[^.!?]+[.!?]+/g) || [protectedText];
                
                if (sentences.length <= 2) {
                    // Restore kaomoji
                    let restoredText = finalResponse;
                    messages = [restoredText];
                } else {
                    let currentMessage = '';
                    
                    for (let i = 0; i < sentences.length; i++) {
                        let sentence = sentences[i].trim();
                        
                        // Restore kaomoji in this sentence
                        kaomojiMap.forEach((original, placeholder) => {
                            sentence = sentence.replace(placeholder, original);
                        });
                        
                        const sentenceCount = (currentMessage.match(/[.!?]+/g) || []).length;
                        
                        if (currentMessage && (currentMessage.length > 150 || sentenceCount >= 2)) {
                            messages.push(currentMessage.trim());
                            currentMessage = sentence;
                        } else {
                            currentMessage += (currentMessage ? ' ' : '') + sentence;
                        }
                    }
                    
                    if (currentMessage.trim()) {
                        messages.push(currentMessage.trim());
                    }
                }
            }

            // AI Generated Image system - send image FIRST if generated
            if (imageData) {
                console.log(`   🎨 Sending generated image...`);

                // Retry logic for image sending
                let imageRetries = 0;
                const maxImageRetries = 3;

                while (imageRetries < maxImageRetries) {
                    try {
                        // Convert base64 to buffer
                        const imageBuffer = Buffer.from(imageData, 'base64');

                        // Send as attachment
                        await message.channel.send({
                            files: [{
                                attachment: imageBuffer,
                                name: 'misuki-selfie.png'
                            }]
                        });
                        console.log(`   ✅ Sent generated image`);
                        break; // Success
                    } catch (imageError) {
                        imageRetries++;
                        if (imageRetries < maxImageRetries) {
                            console.log(`   ⚠️ Image send failed (attempt ${imageRetries}/${maxImageRetries}), retrying...`);
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        } else {
                            console.error(`   ❌ Failed to send image after ${maxImageRetries} attempts:`, imageError.message);
                            // Don't throw - image is optional, continue without it
                        }
                    }
                }

                // Add delay after image before sending text
                const afterImageDelay = 800 + Math.random() * 1200;
                await new Promise(resolve => setTimeout(resolve, afterImageDelay));
            }

            // Send messages with retry logic for Discord API
            for (let i = 0; i < messages.length; i++) {
                let sendRetries = 0;
                const maxSendRetries = 3;
                
                while (sendRetries < maxSendRetries) {
                    try {
                        if (i === 0) {
                            await message.reply(messages[i]);
                        } else {
                            // Check if user sent a newer message (self-interruption)
                            const currentLock = userProcessingLock.get(message.author.id);
                            if (currentLock && currentLock.latestMessageId !== currentMessageId) {
                                // User sent a newer message! Abort this response
                                console.log(`   🛑 Aborting - user sent a newer message`);
                                break; // Exit the for loop, don't send remaining messages
                            }
                            
                            const typingTime = messages[i].length * 50 + Math.random() * 1000;
                            const pauseTime = 500 + Math.random() * 500;
                            const totalDelay = Math.min(typingTime + pauseTime, 5000);
                            
                            await message.channel.sendTyping();
                            await new Promise(resolve => setTimeout(resolve, totalDelay));
                            await message.channel.send(messages[i]);
                        }
                        break; // Success, exit retry loop
                    } catch (sendError) {
                        sendRetries++;
                        if (sendRetries < maxSendRetries) {
                            console.log(`   ⚠️ Discord send failed (attempt ${sendRetries}/${maxSendRetries}), retrying...`);
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        } else {
                            console.error(`   ❌ Failed to send message after ${maxSendRetries} attempts:`, sendError.message);
                            throw sendError; // Re-throw to trigger outer retry
                        }
                    }
                }
            }
            
            console.log(`✅ Replied with ${messages.length} message(s)${gifUrl ? ' + GIF' : ''}`);
            
            // Anime GIF system - Misuki decides when to send!
            if (gifUrl) {
                // Always add delay since text is sent first
                const gifDelay = 800 + Math.random() * 1200;
                await new Promise(resolve => setTimeout(resolve, gifDelay));
                
                // Retry logic for GIF sending
                let gifRetries = 0;
                const maxGifRetries = 3;
                
                while (gifRetries < maxGifRetries) {
                    try {
                        await message.channel.send(gifUrl);
                        console.log(`   🎨 Sent ${gifEmotion} anime GIF`);
                        break; // Success
                    } catch (gifError) {
                        gifRetries++;
                        if (gifRetries < maxGifRetries) {
                            console.log(`   ⚠️ GIF send failed (attempt ${gifRetries}/${maxGifRetries}), retrying...`);
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        } else {
                            console.error(`   ❌ Failed to send GIF after ${maxGifRetries} attempts:`, gifError.message);
                            // Don't throw - GIF is optional, continue without it
                        }
                    }
                }
                
                if (gifRetries === 0 || gifRetries < maxGifRetries) {
                    await saveGifToHistory(userProfile.user_id, gifEmotion);
                }
            }

            // Success! Break out of retry loop

            // Check for pending messages from same user (sent while we were processing)
            const finalLock = userProcessingLock.get(message.author.id);
            if (finalLock && finalLock.pendingMessages.length > 0) {
                console.log(`📬 Found ${finalLock.pendingMessages.length} pending message(s) from ${message.author.username}`);

                // Wait a moment to see if more messages come in
                await new Promise(resolve => setTimeout(resolve, 1500));

                // Collect all pending messages
                const pendingMessages = [...finalLock.pendingMessages];
                finalLock.pendingMessages = []; // Clear the pending queue

                // Concatenate all pending messages with the original message
                const allMessages = [message, ...pendingMessages];
                const combinedContent = allMessages.map(msg => msg.content).join('\n');
                console.log(`📝 Combined ${allMessages.length} message(s) into one: "${combinedContent.substring(0, 100)}..."`);

                // Use the LAST message as the base (it has all the Discord.js methods)
                // But override its content with the combined content
                const lastMessage = pendingMessages[pendingMessages.length - 1];

                // Temporarily override the content property
                const originalContent = lastMessage.content;
                lastMessage.content = combinedContent;

                // Release lock and re-trigger message handler
                finalLock.isProcessing = false;

                console.log(`🔄 Re-processing with combined messages...`);

                // Emit message event with the modified last message
                client.emit('messageCreate', lastMessage);

                // Restore original content (just to be safe)
                lastMessage.content = originalContent;

                return;
            }

            // Release user processing lock
            if (finalLock) {
                finalLock.isProcessing = false;
            }
            
            // QUEUE SYSTEM: Process next person in queue (if in server)
            if (!isDM && message.channel.id) {
                const queueData = conversationQueue.get(message.channel.id);
                if (queueData) {
                    queueData.isSending = false;
                    
                    // Check if there's someone waiting
                    if (queueData.queue.length > 0) {
                        const nextPerson = queueData.queue.shift();
                        
                        // Send "back to you" message
                        const backMsg = getQueueMessage('backTo', nextPerson.authorId, queueData.currentUser);
                        await message.channel.send(backMsg);
                        
                        console.log(`▶️ Processing queued message from ${nextPerson.authorName}`);
                        
                        // Wait a moment then process their message
                        setTimeout(() => {
                            client.emit('messageCreate', nextPerson.message);
                        }, 1500);
                    } else {
                        // No one waiting, clear current user
                        queueData.currentUser = null;
                    }
                }
            }
            
            return;
            
        } catch (error) {
            if (stopTyping) stopTyping();
            
            retryCount++;
            
            // Check if this is a retryable error
            const errorMessage = error.message || '';
            const isRetryable = 
                errorMessage.includes('overloaded') ||
                errorMessage.includes('rate_limit') ||
                errorMessage.includes('timeout') ||
                errorMessage.includes('ECONNRESET') ||
                errorMessage.includes('ETIMEDOUT') ||
                errorMessage.includes('ENOTFOUND') ||
                errorMessage.includes('ECONNREFUSED') ||
                error.code?.includes('ETIMEDOUT') ||
                error.code?.includes('ECONNRESET') ||
                (error.response?.status >= 500 && error.response?.status < 600);
            
            if (isRetryable && retryCount < maxRetries) {
                // Exponential backoff
                const delay = Math.min(1000 * Math.pow(2, retryCount - 1), 16000);
                console.log(`⚠️ Message handler error (attempt ${retryCount}/${maxRetries}), retrying in ${delay}ms...`);
                console.log(`   Error: ${errorMessage}`);
                await new Promise(resolve => setTimeout(resolve, delay));
                continue; // Retry
            } else {
                // Either not retryable or max retries reached
                console.error(`❌ Error handling message (attempt ${retryCount}/${maxRetries}):`, error);
                await message.reply("Hmm... something's not working right (˶ᵕ ᵕ˶)");
                return;
            }
        }
    }
}

// ============================================
// TYPING INDICATOR TRACKING
// ============================================

// Track active typers: channelId -> Map<userId, { username, startTime, lastUpdate }>
const activeTypers = new Map();

// Update interval for showing who's typing (every 1 second)
let typingUpdateInterval = null;

function startTypingUpdates() {
    if (typingUpdateInterval) return; // Already running

    typingUpdateInterval = setInterval(() => {
        const now = Date.now();
        let hasActiveTypers = false;

        for (const [channelId, typers] of activeTypers.entries()) {
            // Remove typers who haven't typed in 10 seconds (Discord typing timeout)
            for (const [userId, data] of typers.entries()) {
                if (now - data.lastUpdate > 10000) {
                    console.log(`⌨️  ${data.username} stopped typing in ${data.channelName}`);
                    typers.delete(userId);
                }
            }

            // Remove empty channels
            if (typers.size === 0) {
                activeTypers.delete(channelId);
            } else {
                hasActiveTypers = true;
                // Show current typers
                const typerNames = Array.from(typers.values()).map(t => t.username).join(', ');
                const channelName = Array.from(typers.values())[0].channelName;
                console.log(`⌨️  Currently typing in ${channelName}: ${typerNames}`);
            }
        }

        // Stop interval if nobody is typing
        if (!hasActiveTypers && typingUpdateInterval) {
            clearInterval(typingUpdateInterval);
            typingUpdateInterval = null;
        }
    }, 1000);
}

client.on('typingStart', (typing) => {
    const user = typing.user;
    const channel = typing.channel;

    // Ignore bot typing
    if (user.bot) return;

    const channelName = channel.guild ? `#${channel.name}` : `DM with ${channel.recipient?.username || 'Unknown'}`;
    const now = Date.now();

    // Get or create channel typing map
    if (!activeTypers.has(channel.id)) {
        activeTypers.set(channel.id, new Map());
    }

    const channelTypers = activeTypers.get(channel.id);
    const isNewTyper = !channelTypers.has(user.id);

    // Update or add typer
    channelTypers.set(user.id, {
        username: user.username,
        channelName: channelName,
        startTime: channelTypers.get(user.id)?.startTime || now,
        lastUpdate: now
    });

    // Only log when someone starts typing (not on updates)
    if (isNewTyper) {
        console.log(`⌨️  ${user.username} started typing in ${channelName}`);
    }

    // Start the update interval if not already running
    startTypingUpdates();
});

client.on('error', (error) => {
    console.error('Discord client error:', error);
});

process.on('unhandledRejection', (error) => {
    console.error('Unhandled promise rejection:', error);
});

client.login(process.env.DISCORD_TOKEN);