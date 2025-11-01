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
        GatewayIntentBits.DirectMessageTyping
    ],
    partials: [Partials.Channel]
});

// Database connection
let db;

// Your Discord ID (the main user - Dan)
const MAIN_USER_ID = '406105172780122113';

async function connectDB() {
    db = await mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: '',
        database: 'misuki_companion'
    });
    console.log('✅ Connected to MySQL database!');
}

// =========================================
// USER PROFILE & RELATIONSHIP MANAGEMENT
// =========================================

async function getUserProfile(discordId, username) {
    const [rows] = await db.execute(
        'SELECT * FROM user_profiles WHERE discord_id = ?',
        [discordId]
    );
    
    if (rows.length > 0) {
        // Update last interaction and message count
        await db.execute(
            `UPDATE user_profiles 
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
        
        await db.execute(
            `INSERT INTO user_profiles 
             (discord_id, username, display_name, trust_level, relationship_notes, total_messages) 
             VALUES (?, ?, ?, ?, ?, 1)`,
            [discordId, username, username, trustLevel, relationshipNotes]
        );
        
        const [newRows] = await db.execute(
            'SELECT * FROM user_profiles WHERE discord_id = ?',
            [discordId]
        );
        return newRows[0];
    }
}

async function getOtherUsers(currentUserId, limit = 5) {
    const [rows] = await db.execute(
        `SELECT discord_id, username, display_name, nickname, trust_level, 
                total_messages, relationship_notes
         FROM user_profiles 
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
            [user.discord_id, limit]
        );
        
        if (rows.length > 0) {
            const userName = user.nickname || user.display_name || user.username;
            conversationSnippets.push({
                user: userName,
                userId: user.discord_id,
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
async function saveConversation(userId, userMessage, misukiResponse, mood = 'gentle') {
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
            { time: '00:00', activity: 'Sleeping', emoji: '😴', type: 'sleep' }
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

// UPDATE DISCORD STATUS BASED ON SCHEDULE
function updateDiscordStatus() {
    const activity = getMisukiCurrentActivity();
    const activityType = activity.type;
    
    let statusText = '';
    let activityTypeDiscord = ActivityType.Custom;
    let statusState = 'online'; // online, idle, dnd, invisible
    
    // Map activity types to Discord status
    switch (activityType) {
        case 'sleep':
            statusText = `💤 Sleeping`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'idle';
            break;
            
        case 'class':
        case 'lab':
            statusText = `📚 In ${activity.activity}`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'dnd'; // Do Not Disturb during class
            break;
            
        case 'studying':
            statusText = `📖 Studying chemistry`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'dnd';
            break;
            
        case 'commute':
            statusText = `🚃 ${activity.activity}`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'idle';
            break;
            
        case 'university':
            statusText = `🏫 At Saitama University`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'online';
            break;
            
        case 'church':
            statusText = `⛪ At church service`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'dnd';
            break;
            
        case 'personal':
            if (activity.activity.includes('eating') || activity.activity.includes('dinner') || activity.activity.includes('lunch') || activity.activity.includes('breakfast')) {
                statusText = `🍽️ ${activity.activity}`;
                statusState = 'idle';
            } else if (activity.activity.includes('shower') || activity.activity.includes('Getting dressed')) {
                statusText = `🚿 Getting ready`;
                statusState = 'idle';
            } else if (activity.activity.includes('bed') || activity.activity.includes('Getting ready for bed')) {
                statusText = `🌙 Getting ready for bed`;
                statusState = 'idle';
            } else {
                statusText = `${activity.emoji} ${activity.activity}`;
                statusState = 'online';
            }
            activityTypeDiscord = ActivityType.Custom;
            break;
            
        case 'break':
            statusText = `☕ Taking a break`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'idle';
            break;
            
        case 'free':
        default:
            statusText = `💕 Free time - Message me!`;
            activityTypeDiscord = ActivityType.Custom;
            statusState = 'online';
            break;
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
    const gifMessage = `[Sent a ${gifEmotion} anime gif 🎨]`;
    
    await db.execute(
        `INSERT INTO conversations (user_id, user_message, misuki_response, mood, timestamp) 
         VALUES (?, '', ?, ?, NOW())`,
        [userId, gifMessage, gifEmotion]
    );
}

// Web search function for Misuki to use naturally
async function searchWeb(query) {
    try {
        // Add Japanese preference to queries when it makes sense
        // For videos, images, entertainment - add "japanese" or use .jp
        // For factual info/articles - keep as is
        let searchQuery = query;
        
        // If searching for media content (videos, music, entertainment), prefer Japanese
        const mediaKeywords = ['video', 'youtube', 'music', 'song', 'anime', 'game', 'cute', 'funny', 'cat', 'dog', 'compilation'];
        const isMediaSearch = mediaKeywords.some(keyword => query.toLowerCase().includes(keyword));
        
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
async function getRecentChannelMessages(channel, limit = 10) {
    try {
        const messages = await channel.messages.fetch({ limit: limit });
        const messageArray = Array.from(messages.values()).reverse(); // oldest first
        
        const context = [];
        for (const msg of messageArray) {
            if (msg.author.bot && msg.author.id !== client.user.id) continue; // Skip other bots
            
            const authorName = msg.author.username;
            const content = msg.content.replace(`<@${client.user.id}>`, '').trim();
            
            if (msg.author.id === client.user.id) {
                context.push(`Misuki: ${content}`);
            } else {
                context.push(`${authorName}: ${content}`);
            }
        }
        
        return context.join('\n');
    } catch (error) {
        console.error('Error fetching channel messages:', error);
        return '';
    }
}

// =========================================
// GENERATE MISUKI'S RESPONSE (WITH MULTI-USER SUPPORT)
// =========================================

async function generateMisukiResponse(userMessage, conversationHistory, userProfile, currentActivity, isDM = true, otherUsers = [], otherConversations = [], channelContext = '', retryCount = 0) {
    const userName = userProfile.nickname || userProfile.display_name || userProfile.username;
    const isMainUser = userProfile.discord_id === MAIN_USER_ID;
    const trustLevel = userProfile.trust_level;
    
    // Build conversation context with timestamps
    let context = '';
    conversationHistory.forEach(conv => {
        if (conv.user_message) {
            context += `${userName}: ${conv.user_message}\n`;
        }
        if (conv.misuki_response) {
            context += `Misuki: ${conv.misuki_response}\n`;
        }
        context += '\n';
    });
    
    // Add time awareness - calculate time since last message
    let timeContext = '';
    if (conversationHistory.length > 0) {
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
=== 📜 RECENT CHANNEL MESSAGES ===
Here are the recent messages in this channel (for context):

${channelContext}

⚠️ IMPORTANT: When ${userName} refers to "she", "he", "they", or other pronouns, look at the recent channel messages above to understand who they're talking about!
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
        // Other users - relationship building
        const relationshipStage = trustLevel <= 2 ? 'NEW ACQUAINTANCE' :
                                 trustLevel <= 5 ? 'GETTING TO KNOW' :
                                 trustLevel <= 7 ? 'FRIENDLY' : 'CLOSE FRIEND';
        
        relationshipContext = `
=== 🤝 RELATIONSHIP WITH ${userName.toUpperCase()} ===
Relationship stage: ${relationshipStage} (trust level: ${trustLevel}/10)
Total conversations: ${userProfile.total_messages}
${userProfile.relationship_notes ? `Notes: ${userProfile.relationship_notes}` : ''}

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

${timeContextString}
${activityContext}
${relationshipContext}
${otherUsersContext}
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
- **You can send JUST a GIF with no text** - BUT only when the meaning is crystal clear!
- **If GIF-only might be confusing, add a short text explanation**
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

Examples of when to send GIFs:
- You're feeling VERY sleepy while chatting → send a sleepy anime girl GIF
- Something REALLY makes you laugh → send a happy/excited GIF
- You're genuinely confused → send a confused anime GIF
- Feeling particularly shy or embarrassed → send a blushing anime GIF
- Strong emotions that words can't fully express

**GIF-only is okay when:**
- Direct emotional reaction (laughing at something funny → happy GIF alone)
- Simple acknowledgment (nodding → cute nod GIF alone)
- The context makes the meaning 100% obvious

**Add text with your GIF when:**
- The GIF needs explanation
- You're answering a question
- Making a comment about something
- The meaning might be unclear
- You're sharing information (like about yakisoba!)

Examples of when NOT to send GIFs:
- Normal casual chatting → Just use words and emoticons
- You recently sent a GIF → Wait several messages before sending another
- Simple responses → Save GIFs for meaningful moments
- The conversation is serious → GIFs might be inappropriate

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
- Sometimes you can respond with JUST a GIF if it perfectly captures what you want to say!

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
${context}
${timeContext}

Now respond to ${userName}'s message naturally as Misuki. Remember your relationship level with them and respond accordingly!

${userName}: ${userMessage}`;

    try {
        // First API call with tools available
        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 250,
            messages: [{ role: 'user', content: prompt }],
            temperature: 1.0,
            tools: [
                {
                    name: 'web_search',
                    description: 'Search the web for videos, articles, or information. Use this naturally when you want to share something relevant - like a cat video when someone is sad, a chemistry article, a funny video, etc. You can search YouTube by including "youtube" in your query.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            query: {
                                type: 'string',
                                description: 'The search query. For YouTube videos, include "youtube" in the query (e.g., "youtube cute cat video")'
                            }
                        },
                        required: ['query']
                    }
                },
                {
                    name: 'send_gif',
                    description: 'Send an anime GIF that matches your current emotion or mood. Use this when you want to express yourself visually - when excited, sleepy, happy, confused, etc. Be natural about it - send GIFs when it feels right, not every message.',
                    input_schema: {
                        type: 'object',
                        properties: {
                            emotion: {
                                type: 'string',
                                description: 'Your current emotion',
                                enum: ['happy', 'excited', 'love', 'affectionate', 'sad', 'upset', 'sleepy', 'tired', 'confused', 'curious', 'working', 'studying', 'playful', 'teasing', 'shy', 'nervous', 'embarrassed', 'surprised', 'content', 'relaxed', 'cute', 'eating']
                            }
                        },
                        required: ['emotion']
                    }
                }
            ]
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            }
        });

        const content = response.data.content;
        
        // Check if Claude wants to use tools
        const toolUseBlocks = content.filter(block => block.type === 'tool_use');
        
        if (toolUseBlocks.length > 0) {
            // Claude wants to use one or more tools!
            const toolResults = [];
            
            for (const toolBlock of toolUseBlocks) {
                if (toolBlock.name === 'web_search') {
                    console.log(`   🔍 Misuki is searching: "${toolBlock.input.query}"`);
                    const searchResults = await searchWeb(toolBlock.input.query);
                    toolResults.push({
                        type: 'tool_result',
                        tool_use_id: toolBlock.id,
                        content: JSON.stringify(searchResults)
                    });
                } else if (toolBlock.name === 'send_gif') {
                    console.log(`   🎨 Misuki wants to send a ${toolBlock.input.emotion} gif`);
                    const gifUrl = await searchGif(toolBlock.input.emotion);
                    toolResults.push({
                        type: 'tool_result',
                        tool_use_id: toolBlock.id,
                        content: gifUrl || 'No gif found'
                    });
                }
            }
            
            // Send results back to Claude
            const followUpResponse = await axios.post('https://api.anthropic.com/v1/messages', {
                model: 'claude-sonnet-4-20250514',
                max_tokens: 250,
                messages: [
                    { role: 'user', content: prompt },
                    { role: 'assistant', content: content },
                    {
                        role: 'user',
                        content: toolResults
                    }
                ],
                temperature: 1.0
            }, {
                headers: {
                    'x-api-key': process.env.ANTHROPIC_API_KEY,
                    'anthropic-version': '2023-06-01',
                    'content-type': 'application/json'
                }
            });
            
            // Get the final text response and any gif URL
            const textBlock = followUpResponse.data.content.find(block => block.type === 'text');
            let responseText = textBlock ? textBlock.text.trim() : "Oh no... something went wrong ><";
            
            responseText = responseText.replace(/\*[^*]+\*/g, '');
            responseText = responseText.replace(/^["']|["']$/g, '');
            responseText = responseText.replace(/\s+/g, ' ').trim();
            
            // Check if she wants to send a GIF
            const gifToolBlock = toolUseBlocks.find(block => block.name === 'send_gif');
            const gifUrl = gifToolBlock ? toolResults.find(r => r.tool_use_id === gifToolBlock.id)?.content : null;
            
            return {
                text: responseText,
                gifUrl: gifUrl && gifUrl !== 'No gif found' ? gifUrl : null,
                gifEmotion: gifToolBlock?.input.emotion || null
            };
        } else {
            // No tool use - just return the text response
            const textBlock = content.find(block => block.type === 'text');
            let responseText = textBlock ? textBlock.text.trim() : "Oh no... something went wrong ><";
            
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
        const errorType = error.response?.data?.error?.type;
        
        if (errorType === 'overloaded_error' && retryCount < 10) {
            const delay = 1000 * Math.pow(2, retryCount);
            console.log(`⚠️ API overloaded, retrying in ${delay}ms (attempt ${retryCount + 1}/10)...`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
            return generateMisukiResponse(userMessage, conversationHistory, userProfile, currentActivity, isDM, otherUsers, otherConversations, channelContext, retryCount + 1);
        }
        
        console.error('Anthropic API Error:', error.response?.data || error.message);
        return {
            text: "Oh no... something went wrong ><",
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
    console.log(`🎯 Discord status: DYNAMIC`);
    
    updateDiscordStatus();
    setInterval(updateDiscordStatus, 5 * 60 * 1000);
    
    connectDB().catch(console.error);
});

client.on('messageCreate', async (message) => {
    if (message.author.bot) return;
    
    const isDM = !message.guild;
    const isMentioned = message.mentions.has(client.user);
    
    if (!isDM && !isMentioned) return;
    
    console.log(`\n📨 Message from ${message.author.username}`);
    console.log(`📍 Context: ${isDM ? 'Private DM' : 'Server Channel'}`);
    
    let stopTyping = null;
    
    try {
        stopTyping = await startTyping(message.channel);
        
        // Get or create user profile
        const userProfile = await getUserProfile(message.author.id, message.author.username);
        const isMainUser = message.author.id === MAIN_USER_ID;
        
        const userName = userProfile.nickname || userProfile.display_name || message.author.username;
        const userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
        
        console.log(`💬 ${userName} [Trust: ${userProfile.trust_level}/10]: ${userMessage}`);
        
        const currentActivity = getMisukiCurrentActivity();
        console.log(`📅 Current activity: ${currentActivity.activity} ${currentActivity.emoji}`);
        
        // Get conversation history for this specific user
        const history = await getConversationHistory(message.author.id, 10);
        
        // Get other users context (only for main user)
        const otherUsers = isMainUser ? await getOtherUsers(message.author.id, 5) : [];
        const otherConversations = isMainUser ? await getOtherUsersConversations(message.author.id, 3) : [];
        
        // Get recent channel messages for context (if in server)
        const recentChannelMessages = !isDM ? await getRecentChannelMessages(message.channel, 10) : '';
        
        // Generate response
        const responseData = await generateMisukiResponse(
            userMessage, 
            history, 
            userProfile, 
            currentActivity, 
            isDM, 
            otherUsers,
            otherConversations,
            recentChannelMessages
        );
        
        if (stopTyping) stopTyping();
        
        const response = responseData.text;
        const gifUrl = responseData.gifUrl;
        const gifEmotion = responseData.gifEmotion;
        
        // If she's ONLY sending a GIF (no text), skip the text message
        const isGifOnly = gifUrl && (!response || response.trim() === '');
        
        // Save conversation (with appropriate text)
        const conversationText = isGifOnly ? '[GIF only response]' : response;
        await saveConversation(message.author.id, userMessage, conversationText, 'gentle');
        
        const emotion = userMessage.toLowerCase().includes('sad') || 
                       userMessage.toLowerCase().includes('tired') || 
                       userMessage.toLowerCase().includes('upset') ? 'negative' : 'positive';
        await updateEmotionalState(message.author.id, emotion);
        
        // Smart message splitting - but keep URLs intact!
        let messages = [];
        
        // If it's a GIF-only response, don't send any text
        if (!isGifOnly && response) {
            // Check if response contains URLs
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const hasUrl = urlRegex.test(response);
            
            if (hasUrl) {
                // If there's a URL, don't split the message - send it all at once
                messages = [response];
            } else {
                // No URL - use normal smart splitting
                const sentences = response.match(/[^.!?]+[.!?]+/g) || [response];
                
                if (sentences.length <= 2) {
                    messages = [response];
                } else {
                    let currentMessage = '';
                    
                    for (let i = 0; i < sentences.length; i++) {
                        const sentence = sentences[i].trim();
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
        }
        
        // Send messages
        for (let i = 0; i < messages.length; i++) {
            if (i === 0) {
                await message.reply(messages[i]);
            } else {
                const typingTime = messages[i].length * 50 + Math.random() * 1000;
                const pauseTime = 500 + Math.random() * 500;
                const totalDelay = Math.min(typingTime + pauseTime, 5000);
                
                await message.channel.sendTyping();
                await new Promise(resolve => setTimeout(resolve, totalDelay));
                await message.channel.send(messages[i]);
            }
        }
        
        console.log(`✅ Replied with ${messages.length > 0 ? messages.length + ' message(s)' : 'GIF only'}`);
        
        // Anime GIF system - Misuki decides when to send!
        if (gifUrl) {
            const gifDelay = messages.length > 0 ? 800 + Math.random() * 1200 : 0; // No delay if GIF-only
            await new Promise(resolve => setTimeout(resolve, gifDelay));
            
            await message.channel.send(gifUrl);
            console.log(`   🎨 Sent ${gifEmotion} anime GIF`);
            
            await saveGifToHistory(message.author.id, gifEmotion);
        }
        
    } catch (error) {
        console.error('Error handling message:', error);
        if (stopTyping) stopTyping();
        await message.reply("Oh no... something went wrong ><");
    }
});

client.on('error', (error) => {
    console.error('Discord client error:', error);
});

process.on('unhandledRejection', (error) => {
    console.error('Unhandled promise rejection:', error);
});

client.login(process.env.DISCORD_TOKEN);