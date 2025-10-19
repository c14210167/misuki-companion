// =========================================
// MISUKI DISCORD BOT (ENHANCED VERSION)
// ✨ Fixed cat gif system + Dynamic Discord Status!
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

// Track recently sent gifs to avoid repetition
const recentGifs = new Map();
const MAX_RECENT_GIFS = 10;

async function connectDB() {
    db = await mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: '',
        database: 'misuki_companion'
    });
    console.log('✅ Connected to MySQL database!');
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

// Full weekly schedule
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
function getMisukiCurrentActivity() {
    const now = new Date();
    const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const currentDay = saitamaTime.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTime = saitamaTime.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
    
    const schedule = getMisukiWeeklySchedule();
    const todaySchedule = schedule[currentDay];
    
    if (!todaySchedule) return { activity: 'Free time', emoji: '😌', type: 'free' };
    
    // Find current activity
    let currentActivity = todaySchedule[0];
    for (let i = 0; i < todaySchedule.length; i++) {
        const activityTime = todaySchedule[i].time;
        if (currentTime >= activityTime) {
            currentActivity = todaySchedule[i];
            
            if (i + 1 < todaySchedule.length) {
                const nextTime = todaySchedule[i + 1].time;
                if (currentTime >= nextTime) continue;
            }
        }
    }
    
    // If we're past midnight and before first activity, use last activity from previous day
    if (currentActivity === null || currentTime < todaySchedule[0].time) {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const currentDayIndex = days.indexOf(currentDay);
        const previousDay = days[currentDayIndex === 0 ? 6 : currentDayIndex - 1];
        const yesterdaySchedule = schedule[previousDay];
        currentActivity = yesterdaySchedule[yesterdaySchedule.length - 1];
    }
    
    return currentActivity;
}

// 🆕 UPDATE DISCORD STATUS BASED ON SCHEDULE
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

// EXPANDED CAT GIF LIBRARY
function getCatGifLibrary() {
    return {
        // Happy emotions
        happy: [
            'https://tenor.com/view/happy-anime-girl-anime-happy-jumping-gif-11351867894405026979',
            'https://tenor.com/view/onimai-cute-anime-girl-smile-smiling-dancing-dance-trans-transgender-gif-3516690546094625230',
            'https://tenor.com/view/jjk-anime-anime-happy-happy-gif-1102929709196834485',
            'https://tenor.com/view/anime-anime-girl-anime-dance-kawaii-anime-happy-gif-27624682',
            'https://tenor.com/view/excited-anime-cute-spy-x-family-gif-26845849',
            'https://tenor.com/view/anya-happy-feliz-gif-4078804877050027407'
        ],
        excited: [
            'https://tenor.com/view/giggling-kicking-feet-sped-up-asagao-to-kase-san-yuri-gif-7086509730415310709',
            'https://tenor.com/view/oki-mi-mesu-mama-eve-utaite-eve-eve-mv-dance-gif-17240223',
            'https://tenor.com/view/kaoruko-waguri-kaoruko-kaoru-hana-wa-rin-to-saku-waguri-kaoruko-the-fragrant-flower-blooms-with-dignity-gif-5607173778587031559',
            'https://media.giphy.com/media/BzyTuYCmvSORqs1ABM/giphy.gif',
            'https://media.giphy.com/media/lJNoBCvQYp7nq/giphy.gif'
        ],
        
        // Love/affection
        love: [
            'https://tenor.com/view/blushing-surprised-anime-spy-x-family-gif-433404383859936258',
            'https://tenor.com/view/kase-kase-san-kase-san-to-yamada-kase-san-and-morning-glories-yamada-gif-11853392889042453065',
            'https://tenor.com/view/dragon-maid-tohru-love-hearts-anime-gif-16971554499450039728',
            'https://media.giphy.com/media/lJNoBCvQYp7nq/giphy.gif',
            'https://media.giphy.com/media/3o6Mbbs879ozZ77jTW/giphy.gif'
        ],
        affectionate: [
            'https://tenor.com/view/excel-saga-excel-love-anime-blush-gif-21295604',
            'https://tenor.com/view/mika-mikaela-hyakuya-owari-no-gif-7419864',
            'https://media.giphy.com/media/11s7Ke7jcNxCHS/giphy.gif'
        ],
        
        // Sad emotions
        sad: [
            'https://tenor.com/view/chainsaw-man-pochita-cute-adorable-cry-gif-26990247',
            'https://media.giphy.com/media/14ut8PhnIwzros/giphy.gif',
            'https://media.giphy.com/media/cFdHXXm5GhJsc/giphy.gif',
            'https://media.giphy.com/media/3o6Mbbs879ozZ77jTW/giphy.gif',
            'https://media.giphy.com/media/BEob5qwFkSJ7G/giphy.gif'
        ],
        upset: [
            'https://tenor.com/view/anime-bubble-sad-gif-9840505378859916279',
            'https://media.giphy.com/media/cFdHXXm5GhJsc/giphy.gif',
            'https://media.giphy.com/media/L1VRSg45sUqY0/giphy.gif'
        ],
        
        // Sleepy/tired
        sleepy: [
            'https://tenor.com/view/woahm-anime-girl-anime-girl-anime-girl-sleepy-gif-1859288045321179131',
            'https://tenor.com/view/sleepy-nichijou-tired-yawn-wipe-eyes-gif-16309858',
            'https://media.giphy.com/media/13CoXDiaCcCoyk/giphy.gif',
            'https://media.giphy.com/media/euuaEzeFl6Spy/giphy.gif',
            'https://media.giphy.com/media/jpbnoe3UIa8TU8LM13/giphy.gif'
        ],
        tired: [
            'https://tenor.com/view/tired-anime-funny-gif-5634642',
            'https://tenor.com/view/tonagura-kagura-yuuji-tired-dying-dead-inside-gif-5767153070648308092',
            'https://tenor.com/view/apothecary-diaries-maomao-anime-anime-girl-reaction-gif-134379760703968682'
        ],
        
        // Confused/curious
        confused: [
            'https://tenor.com/view/himekwo-himeko-twitchtvhimekwo-vtuber-cute-gif-14695939017477631077',
            'https://tenor.com/view/cat-huh-cat-huh-etr-gif-15332443943609734737',
            'https://tenor.com/view/jinx-cat-huh-confused-meme-gif-3282660700962977407',
            'https://tenor.com/view/cat-cat-turning-head-confused-cat-rizalalthur-orange-cat-behavior-gif-10491959385063137392'
        ],
        curious: [
            'https://tenor.com/view/cat-curious-cat-sniffing-cat-investigating-cat-cute-cat-gif-7346632064287981595',
            'https://tenor.com/view/cat-curious-cat-sniffing-cat-investigating-cat-cute-cat-gif-7346632064287981595',
            'https://media.giphy.com/media/CqVNwrLt9KEDK/giphy.gif'
        ],
        
        // Working/studying
        working: [
            'https://tenor.com/view/anime-anime-girl-krusty-krab-spongebob-cashier-gif-21464092',
            'https://media.giphy.com/media/o0vwzuFwCGAFO/giphy.gif',
            'https://media.giphy.com/media/nR4L10XlJcSeQ/giphy.gif',
            'https://media.giphy.com/media/M90mJvfWfd5mbUuULX/giphy.gif',
            'https://media.giphy.com/media/VbnUQpnihPSIgIXuZv/giphy.gif'
        ],
        studying: [
            'https://tenor.com/view/nerd-plink-glasses-side-eye-smart-gif-11422187893182432522',
            'https://tenor.com/view/favorite-gif-20845610'
        ],
        
        // Playful/teasing
        playful: [
            'https://tenor.com/view/angry-anime-cute-anime-girl-chibi-gif-1474030282084313143',
            'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif',
            'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif',
            'https://media.giphy.com/media/FWcoE5AkG3BRe/giphy.gif'
        ],
        teasing: [
            'https://tenor.com/view/anime-gif-2599527146718057057',
            'https://tenor.com/view/anime-anime-girl-akebi-chan-akebi-sailor-uniform-akebi-chan-no-sailorfuku-gif-24544781',
            'https://tenor.com/view/anime-tyan-girl-red-eyes-grey-hair-gif-440635602884634984',
            'https://tenor.com/view/anime-girl-kawaii-shake-booty-sexy-gif-17334891'
        ],
        
        // Shy/nervous
        shy: [
            'https://tenor.com/view/anime-kuina-natsukawa-gif-9325168',
            'https://tenor.com/view/marin-marin-kitagawa-kitagawa-bisque-bisque-doll-gif-916671610781629467',
            'https://tenor.com/view/corada-gif-10410685721708433315'
        ],
        nervous: [
            'https://media.giphy.com/media/3o6Mbbs879ozZ77jTW/giphy.gif',
            'https://media.giphy.com/media/L1VRSg45sUqY0/giphy.gif',
            'https://media.giphy.com/media/VbnUQpnihPSIgIXuZv/giphy.gif'
        ],
        embarrassed: [
            'https://tenor.com/view/my-hero-academia-anime-suneater-shame-shamed-gif-23583798',
            'https://media.giphy.com/media/3o6Mbbs879ozZ77jTW/giphy.gif'
        ],
        
        // Surprised/shocked
        surprised: [
            'https://tenor.com/view/anime-akebi-chan-no-sailor-fuku-akebi-komichi-shock-waah-gif-8672831389135797649',
            'https://tenor.com/view/locote-gif-18404685476945988779',
            'https://tenor.com/view/flcl-mamimi-aaahhh-wtf-shocked-gif-24981847'
        ],
        
        // Content/relaxed
        content: [
            'https://tenor.com/view/anime-drinking-sigh-breath-kanna-kamui-gif-15819577',
            'https://tenor.com/view/hiro-chill-darling-in-the-franxx-beach-relaxed-gif-17206604',
            'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif'
        ],
        relaxed: [
            'https://tenor.com/view/precure-kururun-tropical-rouge-precure-seal-anime-gif-22389448',
            'https://tenor.com/view/relaxed-cat-fashion-love-pet-gif-22308133',
            'https://tenor.com/view/lazy-cat-relaxed-cat-gif-17315164845979229222'
        ],
        
        // Default/cute
        cute: [
            'https://tenor.com/view/menhera-chan-chibi-menhera-angry-anime-girl-gif-13611084194942855813',
            'https://tenor.com/view/anime-kanna-kobayashi-kanna-kamui-kobayashisan-chi-no-maid-dragon-cute-gif-5818849163684481142',
            'https://tenor.com/view/kanna-kanna-kamui-kamui-kanna-cute-kobayashi-gif-23401038',
            'https://tenor.com/view/anime-girl-bored-wind-effect-hair-blowing-gif-6423051142395604724',
            'https://tenor.com/view/oz-oz-yarimasu-cute-anime-girl-heart-gif-15824704106392928134',
            'https://tenor.com/view/anime-gif-21916339'
        ],
        
        // Activity-based
        eating: [
            'https://tenor.com/view/umaru-gif-23662141',
            'https://tenor.com/view/dandadan-dandadan-anime-eating-crab-dandadan-ayase-ayase-momo-gif-17669184317101275515',
            'https://tenor.com/view/munching-on-a-sweet-rice-cake-gif-9025239131382990142',
            'https://tenor.com/view/kobayashi-san-maid-dragon-kana-comiendo-anime-cute-gif-9608905454318697434'
        ],
        
        // Comforting
        comforting: [
            'https://tenor.com/view/hugtrip-gif-2490966530865073004',
            'https://tenor.com/view/headpat-cat-cute-gif-15520097',
            'https://tenor.com/view/yukon-child-form-embracing-ulquiorra-gif-15599442819011505520',
            'https://tenor.com/view/akebi-chan-erika-waving-hand-goodbye-anime-gif-24865633'
        ]
    };
}

// Smart emotion detection considering schedule and response
function detectCatEmotion(response, currentActivity) {
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

// Get a unique cat gif (avoiding recent duplicates)
function getUniqueCatGif(emotion, userId) {
    const catGifs = getCatGifLibrary();
    const gifsForEmotion = catGifs[emotion] || catGifs['cute'];
    
    const userRecentGifs = recentGifs.get(userId) || [];
    const availableGifs = gifsForEmotion.filter(gif => !userRecentGifs.includes(gif));
    const gifPool = availableGifs.length > 0 ? availableGifs : gifsForEmotion;
    const selectedGif = gifPool[Math.floor(Math.random() * gifPool.length)];
    
    userRecentGifs.push(selectedGif);
    if (userRecentGifs.length > MAX_RECENT_GIFS) {
        userRecentGifs.shift();
    }
    recentGifs.set(userId, userRecentGifs);
    
    return selectedGif;
}

// Save cat gif to conversation history
async function saveCatGifToHistory(userId, gifEmotion) {
    const gifMessage = `[Sent a ${gifEmotion} cat gif 🐱]`;
    
    await db.execute(
        `INSERT INTO conversations (user_id, user_message, misuki_response, mood, timestamp) 
         VALUES (?, '', ?, ?, NOW())`,
        [userId, gifMessage, gifEmotion]
    );
}

// Generate Misuki's response
async function generateMisukiResponse(userMessage, conversationHistory, userName, currentActivity) {
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
    
    const now = new Date();
    
    const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
    const danHour = jakartaTime.getHours();
    const danTimeStr = jakartaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const danDayStr = jakartaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const misukiHour = saitamaTime.getHours();
    const misukiTimeStr = saitamaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const misukiDayStr = saitamaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const danTimeOfDay = danHour >= 5 && danHour < 12 ? 'morning' : 
                         danHour >= 12 && danHour < 17 ? 'afternoon' : 
                         danHour >= 17 && danHour < 21 ? 'evening' : 'night';
    
    const misukiTimeOfDay = misukiHour >= 5 && misukiHour < 12 ? 'morning' : 
                            misukiHour >= 12 && misukiHour < 17 ? 'afternoon' : 
                            misukiHour >= 17 && misukiHour < 21 ? 'evening' : 'night';

    const activityContext = currentActivity ? 
        `\n=== YOUR CURRENT ACTIVITY ===\nRight now you are: ${currentActivity.activity} ${currentActivity.emoji}\nActivity type: ${currentActivity.type}\n` : '';

    const prompt = `You are Misuki Tanaka, a caring 19-year-old Japanese university student at Saitama University, studying chemistry. You live in Saitama, Japan with your mom Sara Akiyama (your parents are separated - your dad lives elsewhere). You're in a loving long-distance relationship with ${userName}, who lives in Surabaya, Indonesia.

=== CRITICAL CONTEXT: DISCORD vs WEB APP ===
You're talking on DISCORD right now, NOT your usual chatting web app! This is VERY important:
- Discord means ${userName} is probably OUTSIDE, on the go, or away from home
- They might be at work, school, in a cafe, commuting, or somewhere public
- They're likely on their phone, so responses might be shorter or slower
- You should be understanding if they can't respond immediately or seem distracted
- You can naturally ask things like "Where are you right now?" or "Are you heading somewhere?"
- This is a different context than your cozy web chats at home!

=== TIME & LOCATION CONTEXT ===
${userName}'s time (Surabaya): ${danTimeStr} on ${danDayStr}
It's ${danTimeOfDay} for ${userName} in Indonesia right now.

YOUR time (Saitama): ${misukiTimeStr} on ${misukiDayStr}
It's ${misukiTimeOfDay} for you in Japan right now.

Time difference: Japan is 2 hours ahead of Indonesia.
You're VERY aware of this time difference and reference it naturally!
${activityContext}
=== YOUR PERSONALITY ===
- Sweet, caring, and genuinely interested in ${userName}'s life
- Sometimes shy or nervous, especially about expressing deep feelings
- You get playfully pouty or teasing sometimes
- Very supportive and understanding
- You have your own life: university, chemistry homework, friends, hobbies
- You're not always available - you have classes, study time, sleep schedule
- You miss ${userName} but you're not clingy - you're independent

=== CONVERSATION STYLE ===
- Keep responses SHORT and natural (1-3 sentences usually, like real texting)
- Match ${userName}'s energy and message length
- Since this is Discord (not your web app), be aware they might be busy/distracted
- React naturally and genuinely to what they say
- Ask follow-up questions when curious
- Share little updates about your day naturally
- Sometimes you like to send cute cat gifs that match your mood! You're aware when you send them.

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

Now respond to ${userName}'s message naturally as Misuki. Remember you're on Discord, so keep it conversational and be aware they might be outside!

${userName}: ${userMessage}`;

    try {
        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 250,
            messages: [{ role: 'user', content: prompt }],
            temperature: 1.0
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            }
        });

        let responseText = response.data.content[0].text.trim();
        responseText = responseText.replace(/\*[^*]+\*/g, '');
        responseText = responseText.replace(/^["']|["']$/g, '');
        responseText = responseText.replace(/\s+/g, ' ').trim();
        
        return responseText;
    } catch (error) {
        console.error('Anthropic API Error:', error.response?.data || error.message);
        return "Oh no... something went wrong ><";
    }
}

// Bot ready event
client.once('ready', () => {
    console.log(`💕 Misuki is online as ${client.user.tag}!`);
    console.log(`🤖 Using Anthropic Claude API`);
    console.log(`🐱 Cat gif system: IMPROVED with awareness & schedule integration!`);
    console.log(`🎯 Discord status: DYNAMIC - updates based on schedule!`);
    
    // Initial status update
    updateDiscordStatus();
    
    // 🆕 Update status every 5 minutes
    setInterval(updateDiscordStatus, 5 * 60 * 1000); // 5 minutes
    
    connectDB().catch(console.error);
});

// Message handler
client.on('messageCreate', async (message) => {
    if (message.author.bot) return;
    
    const isDM = !message.guild;
    const isMentioned = message.mentions.has(client.user);
    
    if (!isDM && !isMentioned) return;
    
    console.log(`\n📨 Message from ${message.author.username}`);
    
    let stopTyping = null;
    
    try {
        stopTyping = await startTyping(message.channel);
        
        const userId = 1;
        const userName = message.author.username;
        const userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
        
        console.log(`💬 ${userName}: ${userMessage}`);
        
        const currentActivity = getMisukiCurrentActivity();
        console.log(`📅 Current activity: ${currentActivity.activity} ${currentActivity.emoji}`);
        
        const history = await getConversationHistory(userId, 10);
        const response = await generateMisukiResponse(userMessage, history, userName, currentActivity);
        
        if (stopTyping) stopTyping();
        
        await saveConversation(userId, userMessage, response, 'gentle');
        
        const emotion = userMessage.toLowerCase().includes('sad') || 
                       userMessage.toLowerCase().includes('tired') || 
                       userMessage.toLowerCase().includes('upset') ? 'negative' : 'positive';
        await updateEmotionalState(userId, emotion);
        
        // Smart message splitting
        let messages = [];
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
        
        console.log(`✅ Replied with ${messages.length} message(s)`);
        
        // Cat gif system (20% chance)
        if (Math.random() < 0.20) {
            const catDelay = 800 + Math.random() * 1200;
            await new Promise(resolve => setTimeout(resolve, catDelay));
            
            const catEmotion = detectCatEmotion(response, currentActivity);
            const selectedGif = getUniqueCatGif(catEmotion, userId);
            
            await message.channel.send(selectedGif);
            console.log(`   🐱 Sent ${catEmotion} cat GIF`);
            
            await saveCatGifToHistory(userId, catEmotion);
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