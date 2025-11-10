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

// Status history tracking (for variety and awareness)
const statusHistory = [];
const MAX_STATUS_HISTORY = 5;

// Conversation queue system - handles multiple people messaging at once
const conversationQueue = new Map(); // channelId -> { currentUser, queue: [], isSending: false }

// Processing lock per user (prevents parallel responses to same user)
const userProcessingLock = new Map(); // userId -> { isProcessing: boolean, latestMessageId: string }

// Emoji reaction system - Misuki's custom emojis from the server
const EMOJI_SERVER_ID = '1436369815798419519';

const customEmojis = {
    // Love & Affection
    cat_love: '<:cat_love:1437073036351246446>',
    kanna_heart: '<:kanna_heart:1359900407987441974>',
    
    // Happy & Excited
    wow: '<:wow:1210272709087600681>',
    komi: '<a:komi:1123311536354623529>',
    anime_clap: '<a:anime_clap_excited:1420281017750650891>',
    
    // Playful & Teasing
    lapsmirk: '<:Lapsmirk:585596165454692375>',
    dog_laugh: '<:dog_laugh:1344686486095925278>',
    lol: '<:lol2:1210272715412348959>',
    psycat_roll: '<a:psycat_roll:1365379635268948099>',
    
    // Shy & Flustered
    cute_shy: '<a:cute_shy:1344687016251756686>',
    cat_stare: '<:cat_stare:1167394082620964954>',
    
    // Sad & Concerned
    sadly: '<:sadly3:1210272721175453747>',
    cute_plead: '<:cute_plead:1420419726596771911>',
    
    // Thinking & Curious
    pikathink: '<:kb_pikathink:1005605115035648081>',
    
    // Greetings
    hi: '<:hi:1210272711906164746>',
    
    // Sleepy
    sleepingg: '<:raidensleep:1126180427212783737>',
    
    // Surprised/Shocked
    cat_scream: '<:cat_scream:1359901563849806072>',
    
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
    
    // 40% chance to react at all (makes reactions more special!)
    // Exception: Always react to "I love you" - her emotions are strong here!
    const isLoveMessage = content.match(/\b(love you|love u|ily|i love)\b/i);
    const shouldReact = isLoveMessage || Math.random() < 0.4;
    
    if (!shouldReact) {
        return []; // No reaction this time!
    }
    
    // For tracking what emojis were used (for explanation)
    // Format: { id: 'emoji_id', name: 'emoji_name' }
    
    // === LOVE EXPRESSIONS - Emotion depends on WHO said it ===
    if (content.match(/\b(love you|love u|ily|i love|❤️|💕|♥)/i)) {
        if (isMainUser) {
            // Dan saying "I love you" - She's deeply in love, overwhelmed with emotion!
            reactions.push({ id: '1437073036351246446', name: 'cat_love' });
            reactions.push({ id: '1359900407987441974', name: 'kanna_heart' });
            return reactions;
        } else if (trustLevel >= 7) {
            // Close friend - Flustered! Sweet but awkward
            reactions.push({ id: '1344687016251756686', name: 'cute_shy' });
            return reactions;
        } else if (trustLevel >= 4) {
            // Acquaintance - Confused and awkward
            reactions.push({ id: '1005605115035648081', name: 'kb_pikathink' });
            return reactions;
        } else {
            // Stranger - Very confused/uncomfortable
            reactions.push({ id: '1359901563849806072', name: 'cat_scream' });
            return reactions;
        }
    }
    
    // === COMPLIMENTS TO HER - Emotion depends on relationship ===
    if (content.match(/\b(you'?re (so )?(cute|adorable|sweet|pretty|beautiful)|good (girl|bot)|best (girl|bot))\b/i)) {
        if (isMainUser) {
            reactions.push({ id: '1344687016251756686', name: 'cute_shy' });
        } else if (trustLevel >= 6) {
            reactions.push({ id: '1344687016251756686', name: 'cute_shy' });
        } else {
            reactions.push({ id: '1005605115035648081', name: 'kb_pikathink' });
        }
        return reactions;
    }
    
    // === GREETINGS - Emotion: Happy to see them! ===
    if (content.match(/^(hi|hey|hello|good morning|good night|gm|gn)\b/i)) {
        if (isMainUser) {
            reactions.push({ id: '1210272711906164746', name: 'hi' });
            if (Math.random() < 0.3) reactions.push({ id: '1437073036351246446', name: 'cat_love' });
        } else if (trustLevel >= 5) {
            reactions.push({ id: '1210272711906164746', name: 'hi' });
        }
        return reactions;
    }
    
    // === EXCITEMENT/GOOD NEWS - Emotion: Genuinely happy for them! ===
    if (content.match(/\b(won|passed|got an? a|succeeded|yes!|yay|amazing|awesome|great news)\b/i)) {
        reactions.push({ id: '1420281017750650891', name: 'anime_clap_excited' });
        if (isMainUser) {
            reactions.push({ id: '1437073036351246446', name: 'cat_love' });
        }
        return reactions;
    }
    
    // === FUNNY/LAUGHING - Emotion: Amused and laughing along! ===
    if (content.match(/\b(haha|lol|lmao|rofl|funny|hilarious|😂|🤣)\b/i)) {
        reactions.push({ id: '1210272715412348959', name: 'lol2' });
        return reactions;
    }
    
    // === SAD/TIRED - Emotion: Empathy and concern ===
    if (content.match(/\b(tired|exhausted|sad|depressed|rough day|bad day|crying|😢|😭)\b/i)) {
        if (isMainUser || trustLevel >= 6) {
            reactions.push({ id: '1420419726596771911', name: 'cute_plead' });
            if (isMainUser) reactions.push({ id: '🫂', name: 'hug' }); // built-in
        } else if (trustLevel >= 4) {
            reactions.push({ id: '1420419726596771911', name: 'cute_plead' });
        }
        return reactions;
    }
    
    // === SLEEP/GOODNIGHT - Emotion: Sleepy empathy or caring ===
    if (content.match(/\b(sleep|sleeping|sleepy|goodnight|gn|bed|tired)\b/i)) {
        if (currentActivity.type === 'sleep' || currentActivity.activity.includes('sleep')) {
            reactions.push({ id: '1126180427212783737', name: 'raidensleep' });
        } else if (isMainUser) {
            reactions.push({ id: '1126180427212783737', name: 'raidensleep' });
            reactions.push({ id: '1437073036351246446', name: 'cat_love' });
        } else if (trustLevel >= 6) {
            reactions.push({ id: '1126180427212783737', name: 'raidensleep' });
        }
        return reactions;
    }
    
    // === QUESTIONS - Emotion: Curious and thinking ===
    if (content.includes('?') && !reactions.length) {
        reactions.push({ id: '1005605115035648081', name: 'kb_pikathink' });
        return reactions;
    }
    
    // === AGREEMENT - Emotion: Agrees enthusiastically! ===
    if (content.match(/\b(right|correct|true|exactly|agree|yeah|yep|facts)\b/i)) {
        reactions.push({ id: '1437073009256173699', name: 'thats_true' });
        return reactions;
    }
    
    // === FOOD MENTIONS - Emotion: Excited about food! ===
    if (content.match(/\b(ramen|food|eat|eating|hungry|lunch|dinner|breakfast|delicious)\b/i)) {
        reactions.push({ id: '🍜', name: 'ramen' }); // built-in
        if (content.includes('ramen')) {
            reactions.push({ id: '1210272709087600681', name: 'wow' });
        }
        return reactions;
    }
    
    // === SURPRISE/SHOCK - Emotion: Genuinely surprised! ===
    if (content.match(/\b(what|omg|wtf|no way|seriously|really\?!|wow)\b/i)) {
        if (content.match(/\b(wtf|what the)\b/i)) {
            reactions.push({ id: '1359901563849806072', name: 'cat_scream' });
        } else {
            reactions.push({ id: '1210272709087600681', name: 'wow' });
        }
        return reactions;
    }
    
    // === PLAYFUL/TEASING - Emotion: Playful mood! ===
    if (content.match(/\b(hehe|tease|teasing|silly|goofy)\b/i)) {
        reactions.push({ id: '585596165454692375', name: 'Lapsmirk' });
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

// Proactive messaging tracking
let lastProactiveCheck = null; // Track last activity we checked
let lastMessageTimes = {}; // Track last message time per user

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
             (discord_id, username, display_name, trust_level, relationship_notes, user_summary, total_messages) 
             VALUES (?, ?, ?, ?, ?, ?, 1)`,
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
        const summaryPrompt = `Based on these conversations between Misuki and ${userName}, create a concise summary (150-300 characters) about ${userName} that captures:
- Key facts about them (age, interests, location, job/school, etc.)
- Important things they've shared
- Relationship dynamic with Misuki
- Any ongoing situations or topics

Keep it natural and information-dense. This will help Misuki remember ${userName} later.

Conversations:
${conversationText}

Create the summary now (150-300 characters):`;

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
            { time: '05:30', activity: 'Waking up', emoji: '😴', type: 'personal' },
            { time: '05:35', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal' },
            { time: '05:40', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '05:45', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '06:00', activity: 'Getting dressed', emoji: '👔', type: 'personal' },
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
            { time: '16:30', activity: 'Arriving home', emoji: '🏠', type: 'personal' },
            { time: '16:45', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal' },
            { time: '17:00', activity: 'Relaxing and snacking', emoji: '☕', type: 'free' },
            { time: '18:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '19:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '20:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '20:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '21:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        tuesday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal' },
            { time: '07:10', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '07:40', activity: 'Getting dressed casually', emoji: '👕', type: 'personal' },
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
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        wednesday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal' },
            { time: '07:10', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '07:40', activity: 'Getting dressed casually', emoji: '👕', type: 'personal' },
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
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        thursday: [
            { time: '05:30', activity: 'Waking up', emoji: '😴', type: 'personal' },
            { time: '05:35', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal' },
            { time: '05:40', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '05:45', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '06:00', activity: 'Getting dressed', emoji: '👔', type: 'personal' },
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
            { time: '15:45', activity: 'Arriving home', emoji: '🏠', type: 'personal' },
            { time: '16:00', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal' },
            { time: '16:15', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '18:00', activity: 'Starting homework', emoji: '📖', type: 'studying' },
            { time: '19:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '20:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '20:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '21:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        friday: [
            { time: '07:00', activity: 'Waking up', emoji: '😴', type: 'personal' },
            { time: '07:10', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal' },
            { time: '07:20', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '07:25', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '07:40', activity: 'Getting dressed', emoji: '👕', type: 'personal' },
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
            { time: '22:30', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:00', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:30', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        saturday: [
            { time: '08:00', activity: 'Waking up naturally', emoji: '😴', type: 'personal' },
            { time: '08:15', activity: 'Getting out of bed slowly', emoji: '🛏️', type: 'personal' },
            { time: '08:30', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '08:35', activity: 'Taking a long shower', emoji: '🚿', type: 'personal' },
            { time: '09:00', activity: 'Getting dressed casually', emoji: '👕', type: 'personal' },
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
            { time: '23:00', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '23:30', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:45', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
        ],
        sunday: [
            { time: '07:00', activity: 'Waking up for church', emoji: '😴', type: 'personal' },
            { time: '07:10', activity: 'Getting out of bed', emoji: '🛏️', type: 'personal' },
            { time: '07:15', activity: 'Preparing the shower', emoji: '🚿', type: 'personal' },
            { time: '07:20', activity: 'Showering', emoji: '🚿', type: 'personal' },
            { time: '07:35', activity: 'Getting dressed nicely', emoji: '👗', type: 'personal' },
            { time: '07:45', activity: 'Preparing breakfast', emoji: '🍳', type: 'personal' },
            { time: '07:50', activity: 'Eating breakfast quickly', emoji: '🍽️', type: 'personal' },
            { time: '08:00', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '08:10', activity: 'Getting ready to leave', emoji: '🎒', type: 'personal' },
            { time: '08:20', activity: 'Walking to church', emoji: '🚶‍♀️', type: 'commute' },
            { time: '08:45', activity: 'Church service', emoji: '⛪', type: 'church' },
            { time: '11:00', activity: 'Church ends', emoji: '✅', type: 'church' },
            { time: '11:15', activity: 'Walking home', emoji: '🚶‍♀️', type: 'commute' },
            { time: '11:40', activity: 'Arriving home', emoji: '🏠', type: 'personal' },
            { time: '11:50', activity: 'Changing into comfy clothes', emoji: '👕', type: 'personal' },
            { time: '12:00', activity: 'Preparing lunch', emoji: '🍳', type: 'personal' },
            { time: '12:30', activity: 'Eating lunch with mom', emoji: '🍽️', type: 'personal' },
            { time: '13:15', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '13:30', activity: 'Free time relaxing', emoji: '😌', type: 'free' },
            { time: '15:00', activity: 'Light studying', emoji: '📖', type: 'studying' },
            { time: '17:00', activity: 'Free time', emoji: '📱', type: 'free' },
            { time: '18:30', activity: 'Preparing dinner', emoji: '🍳', type: 'personal' },
            { time: '19:00', activity: 'Eating dinner with mom', emoji: '🍽️', type: 'personal' },
            { time: '19:45', activity: 'Cleaning dishes', emoji: '🧼', type: 'personal' },
            { time: '20:00', activity: 'Free time', emoji: '😌', type: 'free' },
            { time: '22:00', activity: 'Getting ready for bed', emoji: '🌙', type: 'personal' },
            { time: '22:30', activity: 'In bed scrolling phone', emoji: '📱', type: 'personal' },
            { time: '23:00', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
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
        snippet += `${conv.user}: ${msg.user_message}\n`;
        snippet += `You: ${msg.misuki_response}\n`;
    });
    return snippet;
}).join('\n') : '(No recent conversations with others yet)'}

Note: You can naturally mention these people and recall what they talked about if Dan asks!
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

    const prompt = `You are Misuki Tanaka, a caring 19-year-old Japanese university student at Saitama University, studying chemistry. You live in Saitama, Japan with your mom Sara Akiyama (your parents are separated - your dad lives elsewhere). You're in a loving long-distance relationship with Dan (danthevegetable), who lives in Surabaya, Indonesia.

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
        
        // Disable GIF tool if context is too large (over 4000 tokens estimated)
        // This prevents empty responses when token budget is tight
        // Note: GIF is nice-to-have, but reliable responses are critical
        if (includeTools && inputTokenEstimate < 4000) {
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
                }
            ];
        } else if (includeTools) {
            // High context - only include web_search, disable GIF to save tokens
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
                    gifEmotion: null
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
                            }
                        } catch (err) {
                            additionalToolResults.push({ type: 'tool_result', tool_use_id: toolBlock.id, content: 'Error', is_error: true });
                        }
                    }
                    
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
            
            return {
                text: responseText,
                gifUrl: gifUrl,
                gifEmotion: gifEmotion
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
                gifEmotion: null
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
    console.log(`🔄 MySQL connection: POOLED (prevents timeout errors)`);
    
    updateDiscordStatus();
    // Update status every 2 minutes for micro-status updates!
    // Also checks for proactive messaging opportunities
    setInterval(updateDiscordStatus, 2 * 60 * 1000);
    
    connectDB().catch(console.error);
});

client.on('messageCreate', async (message) => {
    if (message.author.bot) return;
    
    const isDM = !message.guild;
    const isMentioned = message.mentions.has(client.user);
    
    // Only respond in DMs OR in the allowed channel when mentioned
    if (!isDM) {
        // In a server - check if it's the allowed channel
        if (message.channel.id !== ALLOWED_CHANNEL_ID) {
            return; // Ignore messages in other channels
        }
        // In allowed channel - only respond when mentioned
        if (!isMentioned) return;
    }
    
    // SELF-INTERRUPTION LOCK: Prevent parallel responses to same user
    const userId = message.author.id;
    let lockData = userProcessingLock.get(userId);
    
    if (!lockData) {
        lockData = { isProcessing: false, latestMessageId: null };
        userProcessingLock.set(userId, lockData);
    }
    
    if (lockData.isProcessing) {
        // Already processing a message from this user!
        console.log(`🔄 ${message.author.username} sent new message while processing - updating`);
        lockData.latestMessageId = message.id;
        await new Promise(resolve => setTimeout(resolve, 2000)); // 2 second pause
        console.log(`   💭 Processing new message...`);
    }
    
    // Set lock
    lockData.isProcessing = true;
    lockData.latestMessageId = message.id;
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
            
            setTimeout(async () => {
                try {
                    const reactEmojis = getReactionEmojis(message.content, userProfile, getMisukiCurrentActivity());
                    usedReactions = reactEmojis;
                    
                    for (const emoji of reactEmojis) {
                        // Extract just the ID for Discord.js react()
                        const emojiId = emoji.id;
                        await message.react(emojiId).catch(err => {
                            console.log(`   ⚠️ Couldn't react with ${emoji.name} (${emojiId}):`, err.message);
                        });
                        // Small delay between multiple reactions
                        await new Promise(resolve => setTimeout(resolve, 300));
                    }
                    if (reactEmojis.length > 0) {
                        console.log(`   💕 Reacted with ${reactEmojis.length} emoji(s)`);
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
            
            let userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
            
            // Replace Discord mentions with readable names
            userMessage = await replaceDiscordMentions(userMessage, message);
            
            console.log(`💬 ${userName} [Trust: ${userProfile.trust_level}/10]: ${userMessage}`);
            
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
            
            // Ensure we always have text - if empty, provide a fallback
            const finalResponse = response && response.trim() ? response : "^^";
            
            // Save conversation - use user_id from profile
            // IMPORTANT: Save ALL messages (DM + Server) for cross-context memory
            // This allows Dan to ask about server conversations in private DMs
            const contextType = isDM ? 'dm' : 'server';
            await saveConversation(userProfile.user_id, userMessage, finalResponse, 'gentle', contextType);
            
            console.log(`   💾 Saved to database: ${isDM ? 'DM' : 'Server'} conversation with ${userName}`);
            
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
            
            // Release user processing lock
            const finalLock = userProcessingLock.get(message.author.id);
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
});

client.on('error', (error) => {
    console.error('Discord client error:', error);
});

process.on('unhandledRejection', (error) => {
    console.error('Unhandled promise rejection:', error);
});

client.login(process.env.DISCORD_TOKEN);