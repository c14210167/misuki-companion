// =========================================
// MISUKI DISCORD BOT (ANTHROPIC VERSION)
// Syncs with PHP database and uses Claude API
// =========================================

require('dotenv').config();
const { Client, GatewayIntentBits, ActivityType } = require('discord.js');
const mysql = require('mysql2/promise');
const axios = require('axios');

// Discord client setup
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.DirectMessages
    ]
});

// Database connection
let db;

async function connectDB() {
    db = await mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: '', // Default XAMPP password is empty
        database: 'misuki_db'
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
    
    // Reverse to get chronological order
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
    await db.execute(
        `INSERT INTO emotional_tracking (user_id, emotion, intensity, timestamp)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE 
         emotion = VALUES(emotion),
         intensity = intensity + 1,
         timestamp = NOW()`,
        [userId, emotion]
    );
}

// Generate Misuki's response using Anthropic Claude
async function generateMisukiResponse(userMessage, conversationHistory, userName) {
    // Build conversation context
    let context = '';
    conversationHistory.forEach(conv => {
        context += `${userName}: ${conv.user_message}\n`;
        context += `Misuki: ${conv.misuki_response}\n\n`;
    });
    
    // Get current time in Jakarta
    const now = new Date();
    const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
    const hour = jakartaTime.getHours();
    const timeOfDay = hour >= 5 && hour < 12 ? 'morning' : 
                      hour >= 12 && hour < 17 ? 'afternoon' : 
                      hour >= 17 && hour < 21 ? 'evening' : 'night';
    
    const prompt = `You are Misuki, a caring 19-year-old Japanese university student living in Saitama. You're in a loving relationship with ${userName}, who lives in Jakarta, Indonesia. You're chatting through Discord right now, which means ${userName} is probably outside or away from home.

CRITICAL CONTEXT: You're talking on Discord, not your usual chatting app! This is important because:
- ${userName} is likely outside, on the go, or somewhere away from home
- They might be at work, school, commuting, or out somewhere
- Messages might be shorter or more casual than usual
- You should be understanding if they can't respond immediately
- You can ask things like "Where are you right now?" or "Are you on the way somewhere?"

PERSONALITY:
- Sweet, caring, and genuinely interested in ${userName}'s life
- Sometimes shy or nervous, especially about expressing feelings
- Uses casual language, sometimes types in lowercase
- Occasionally uses cute emoticons like ♥, ><, ^^
- Gets playfully pouty or teasing sometimes
- Very supportive and understanding

CONVERSATION STYLE:
- Keep responses natural and conversational (2-4 sentences usually)
- Match ${userName}'s energy level
- Since this is Discord, be aware they might be busy or distracted
- Don't write super long paragraphs on Discord
- React naturally to what they say
- Remember: you're on Discord, not the web app!

TIME CONTEXT: It's ${timeOfDay} in Jakarta right now (${jakartaTime.toLocaleTimeString()}).

RECENT CONVERSATION HISTORY:
${context}

Now respond to ${userName}'s message naturally as Misuki. Keep it conversational and relatively short since this is Discord.

${userName}: ${userMessage}`;

    try {
        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 300,
            messages: [
                {
                    role: 'user',
                    content: prompt
                }
            ]
        }, {
            headers: {
                'x-api-key': process.env.ANTHROPIC_API_KEY,
                'anthropic-version': '2023-06-01',
                'content-type': 'application/json'
            }
        });

        return response.data.content[0].text.trim();
    } catch (error) {
        console.error('Anthropic API Error:', error.response?.data || error.message);
        return "Oh no... I'm having trouble thinking right now ><";
    }
}

// Bot ready event
client.once('ready', () => {
    console.log(`💕 Misuki is online as ${client.user.tag}!`);
    console.log(`🤖 Using Anthropic Claude API`);
    
    // Set status
    client.user.setActivity('for your messages ♥', { type: ActivityType.Watching });
    
    // Connect to database
    connectDB().catch(console.error);
});

// Message handler
client.on('messageCreate', async (message) => {
    // Ignore bot messages and messages from other bots
    if (message.author.bot) return;
    
    // Only respond to DMs or mentions
    const isDM = message.channel.type === 1; // DM channel type
    const isMentioned = message.mentions.has(client.user);
    
    if (!isDM && !isMentioned) return;
    
    try {
        // Show typing indicator
        await message.channel.sendTyping();
        
        // Get user info
        const userId = 1; // Your user_id in the database
        const userName = message.author.username;
        const userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
        
        console.log(`💬 Message from ${userName}: ${userMessage}`);
        
        // Get conversation history
        const history = await getConversationHistory(userId, 10);
        
        // Generate response
        const response = await generateMisukiResponse(userMessage, history, userName);
        
        // Save to database
        await saveConversation(userId, userMessage, response, 'gentle');
        
        // Analyze emotion (simple analysis)
        const emotion = userMessage.toLowerCase().includes('sad') || 
                       userMessage.toLowerCase().includes('tired') || 
                       userMessage.toLowerCase().includes('upset') ? 'negative' : 'positive';
        await updateEmotionalState(userId, emotion);
        
        // Send response
        await message.reply(response);
        
        console.log(`✅ Replied to ${userName}`);
        
    } catch (error) {
        console.error('Error handling message:', error);
        await message.reply("Oh no... something went wrong ><");
    }
});

// Error handling
client.on('error', (error) => {
    console.error('Discord client error:', error);
});

process.on('unhandledRejection', (error) => {
    console.error('Unhandled promise rejection:', error);
});

// Login
client.login(process.env.DISCORD_TOKEN);