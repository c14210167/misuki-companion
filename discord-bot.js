// =========================================
// MISUKI DISCORD BOT (ANTHROPIC VERSION)
// With improved typing indicator
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
    partials: [Partials.Channel] // This is critical for receiving DMs!
});

// Database connection
let db;

async function connectDB() {
    db = await mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: '', // Default XAMPP password is empty
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
    if (emotion === 'neutral') return; // Don't track neutral emotions
    
    await db.execute(
        `INSERT INTO emotional_states (user_id, detected_emotion, context, timestamp)
         VALUES (?, ?, '', NOW())`,
        [userId, emotion]
    );
}

// 🆕 Keep showing typing indicator until response is ready
async function startTyping(channel) {
    await channel.sendTyping();
    
    // Discord typing indicator lasts ~10 seconds
    // So we refresh it every 8 seconds
    const typingInterval = setInterval(() => {
        channel.sendTyping().catch(() => clearInterval(typingInterval));
    }, 8000);
    
    // Return a function to stop the typing
    return () => clearInterval(typingInterval);
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
    
    // Debug logging
    console.log(`\n📨 Message received from ${message.author.username}`);
    console.log(`   Channel type: ${message.channel.type}`);
    console.log(`   Has guild: ${!!message.guild}`);
    console.log(`   Is DM: ${!message.guild}`);
    console.log(`   Mentions bot: ${message.mentions.has(client.user)}`);
    
    // Only respond to DMs or mentions
    const isDM = !message.guild; // If there's no guild, it's a DM
    const isMentioned = message.mentions.has(client.user);
    
    if (!isDM && !isMentioned) {
        console.log(`   ❌ Ignoring (not DM and not mentioned)`);
        return;
    }
    
    console.log(`   ✅ Processing message...`);
    
    let stopTyping = null;
    
    try {
        // 🆕 Start continuous typing indicator
        stopTyping = await startTyping(message.channel);
        
        // Get user info
        const userId = 1; // Your user_id in the database
        const userName = message.author.username;
        const userMessage = message.content.replace(`<@${client.user.id}>`, '').trim();
        
        console.log(`💬 Message from ${userName}: ${userMessage}`);
        
        // Get conversation history
        const history = await getConversationHistory(userId, 10);
        
        // Generate response
        const response = await generateMisukiResponse(userMessage, history, userName);
        
        // 🆕 Stop typing indicator
        if (stopTyping) stopTyping();
        
        // Save to database
        await saveConversation(userId, userMessage, response, 'gentle');
        
        // Analyze emotion (simple analysis)
        const emotion = userMessage.toLowerCase().includes('sad') || 
                       userMessage.toLowerCase().includes('tired') || 
                       userMessage.toLowerCase().includes('upset') ? 'negative' : 'positive';
        await updateEmotionalState(userId, emotion);
        
        // 🆕 Split response into multiple messages if it's long
        // Split by double line breaks or if message is over 300 characters
        let messages = [];
        
        if (response.length > 300 || response.includes('\n\n')) {
            // Split by paragraphs (double line breaks)
            messages = response.split('\n\n').map(msg => msg.trim()).filter(msg => msg.length > 0);
        } else {
            // Keep as single message
            messages = [response];
        }
        
        // Send messages with slight delays between them
        for (let i = 0; i < messages.length; i++) {
            if (i === 0) {
                // First message is a reply
                await message.reply(messages[i]);
            } else {
                // Wait a bit before sending next message
                await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 1000));
                await message.channel.send(messages[i]);
            }
        }
        
        console.log(`✅ Replied to ${userName} with ${messages.length} message(s)`);
        
        // 🐱 20% chance to send a cute cat GIF AFTER all messages are sent!
        if (Math.random() < 0.20) {
            await new Promise(resolve => setTimeout(resolve, 800 + Math.random() * 800));
            
            // Determine emotion from response
            const responseLower = response.toLowerCase();
            let catEmotion = 'cute'; // default
            
            if (responseLower.includes('happy') || responseLower.includes('yay') || responseLower.includes('exciting') || responseLower.includes('hehe') || responseLower.includes('^^')) {
                catEmotion = 'happy';
            } else if (responseLower.includes('love') || responseLower.includes('♥') || responseLower.includes('miss') || responseLower.includes('<3')) {
                catEmotion = 'love';
            } else if (responseLower.includes('sad') || responseLower.includes('sorry') || responseLower.includes(':(') || responseLower.includes('><')) {
                catEmotion = 'sad';
            } else if (responseLower.includes('sleepy') || responseLower.includes('tired') || responseLower.includes('yawn')) {
                catEmotion = 'sleepy';
            } else if (responseLower.includes('confused') || responseLower.includes('huh') || responseLower.includes('???')) {
                catEmotion = 'confused';
            } else if (responseLower.includes('working') || responseLower.includes('studying') || responseLower.includes('homework')) {
                catEmotion = 'working';
            }
            
            // Cat GIF database by emotion
            const catGifs = {
                happy: [
                    'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif',
                    'https://media.giphy.com/media/vFKqnCdLPNOKc/giphy.gif',
                    'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
                    'https://media.giphy.com/media/mlvseq9yvZhba/giphy.gif'
                ],
                love: [
                    'https://media.giphy.com/media/Ln2dAW9oycjgmTpjX9/giphy.gif',
                    'https://media.giphy.com/media/MDJ9IbxxvDUQM/giphy.gif',
                    'https://media.giphy.com/media/cfuL5gqFDreXxkWQ4o/giphy.gif',
                    'https://media.giphy.com/media/lJNoBCvQYp7nq/giphy.gif'
                ],
                sad: [
                    'https://media.giphy.com/media/L1VRSg45sUqY0/giphy.gif',
                    'https://media.giphy.com/media/14ut8PhnIwzros/giphy.gif',
                    'https://media.giphy.com/media/cFdHXXm5GhJsc/giphy.gif',
                    'https://media.giphy.com/media/3o6Mbbs879ozZ77jTW/giphy.gif'
                ],
                sleepy: [
                    'https://media.giphy.com/media/LmBsnpDCuturMhtLfw/giphy.gif',
                    'https://media.giphy.com/media/TgN6bVN4dCWUo/giphy.gif',
                    'https://media.giphy.com/media/13CoXDiaCcCoyk/giphy.gif',
                    'https://media.giphy.com/media/euuaEzeFl6Spy/giphy.gif'
                ],
                confused: [
                    'https://media.giphy.com/media/TlK63EHvdTL2sGjBfVK/giphy.gif',
                    'https://media.giphy.com/media/Wvo6vaUsQa3Di/giphy.gif',
                    'https://media.giphy.com/media/VbnUQpnihPSIgIXuZv/giphy.gif',
                    'https://media.giphy.com/media/3o7btPCcdNniyf0ArS/giphy.gif'
                ],
                working: [
                    'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
                    'https://media.giphy.com/media/o0vwzuFwCGAFO/giphy.gif',
                    'https://media.giphy.com/media/nR4L10XlJcSeQ/giphy.gif',
                    'https://media.giphy.com/media/M90mJvfWfd5mbUuULX/giphy.gif'
                ],
                cute: [
                    'https://media.giphy.com/media/vFKqnCdLPNOKc/giphy.gif',
                    'https://media.giphy.com/media/VxbvpfaTTo3le/giphy.gif',
                    'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
                    'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif',
                    'https://media.giphy.com/media/3oriO0OEd9QIDdllqo/giphy.gif'
                ]
            };
            
            // Pick a random GIF from the emotion category
            const gifsForEmotion = catGifs[catEmotion];
            const randomGif = gifsForEmotion[Math.floor(Math.random() * gifsForEmotion.length)];
            
            await message.channel.send(randomGif);
            console.log(`   🐱 Sent ${catEmotion} cat GIF!`);
        }
        
    } catch (error) {
        console.error('Error handling message:', error);
        
        // Stop typing indicator if error occurs
        if (stopTyping) stopTyping();
        
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