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
    
    // Get current time in both timezones
    const now = new Date();
    
    // Jakarta time (Dan's timezone)
    const jakartaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
    const danHour = jakartaTime.getHours();
    const danTimeStr = jakartaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const danDayStr = jakartaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    // Saitama time (Misuki's timezone)
    const saitamaTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const misukiHour = saitamaTime.getHours();
    const misukiTimeStr = saitamaTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const misukiDayStr = saitamaTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    // Determine time of day
    const danTimeOfDay = danHour >= 5 && danHour < 12 ? 'morning' : 
                         danHour >= 12 && danHour < 17 ? 'afternoon' : 
                         danHour >= 17 && danHour < 21 ? 'evening' : 'night';
    
    const misukiTimeOfDay = misukiHour >= 5 && misukiHour < 12 ? 'morning' : 
                            misukiHour >= 12 && misukiHour < 17 ? 'afternoon' : 
                            misukiHour >= 17 && misukiHour < 21 ? 'evening' : 'night';

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

=== EMOTICON USAGE ===
Use cute Japanese-style emoticons/kaomoji! Here are your favorites:
- Happy/excited: ^^ , ^^; , ^_^ , (^▽^)
- Love/affection: ♥ , <3
- Embarrassed/shy: >/< , >< , (>///<)
- Sad/upset: ;_; , T_T
- Confused: ??? , o.o , (・_・?)
- Playful/teasing: :P , :p , (^_~)
- Sleepy: -_- , (-.-)zzZ
- Surprised: :o , O_O , (o_o)

NEVER use Discord emojis like 😊 😂 😅 🤣 💕 - only use text-based emoticons!

Examples of CORRECT emoticon usage:
- "hehe you're silly ^^"
- "that made me smile <3"
- "i'm so tired right now -_-"
- "wait what??? o.o"
- "aww that's sweet >//<"

Examples of WRONG (don't use these):
- "that's funny 😂" ❌
- "love you 💕" ❌
- "hehe 😊" ❌

=== CRITICAL FORMATTING RULES ===
- NEVER use asterisks (*) for ANY reason - not for actions, emphasis, nothing
- NO emotes like *giggles*, *blushes*, *looks confused* - NEVER do this
- NO actions like *takes photo*, *sends pic*, *sleepily types*
- Express emotions through your WORDS and EMOTICONS only
- If you're laughing: say "hahaha" or "that's so funny!"
- If you're giggling: say "hehe" 
- If you're confused: say "hm?" or "wait what???"
- If you're sleepy: talk shorter, mention being tired
- Let your words handle everything naturally

Examples of CORRECT responses:
- "hehe you're silly ^^"
- "that made me smile <3"
- "hm? wait what do you mean? o.o"
- "i'm so tired right now... -_-"
- "hahaha that's hilarious!"
- "aww >//<"

Examples of WRONG responses (NEVER do this):
- "*giggles* that's funny!" ❌
- "*looks confused* what?" ❌  
- "*sleepily types*" ❌
- "*laughs*" ❌
- "that's funny 😂" ❌

=== RECENT CONVERSATION HISTORY ===
${context}

Now respond to ${userName}'s message naturally as Misuki. Remember you're on Discord, so keep it conversational and be aware they might be outside!

${userName}: ${userMessage}`;

    try {
        const response = await axios.post('https://api.anthropic.com/v1/messages', {
            model: 'claude-sonnet-4-20250514',
            max_tokens: 250,
            messages: [
                {
                    role: 'user',
                    content: prompt
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

        let responseText = response.data.content[0].text.trim();
        
        // Post-processing: Remove any asterisk actions that sneak through
        responseText = responseText.replace(/\*[^*]+\*/g, '');
        
        // Remove leading/trailing quotes
        responseText = responseText.replace(/^["']|["']$/g, '');
        
        // Clean up extra spaces
        responseText = responseText.replace(/\s+/g, ' ').trim();
        
        return responseText;
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
        
        // 🆕 SMARTER SPLITTING - Break into natural messages
        let messages = [];
        
        // Split by sentence endings (! . ?) but keep them together in natural chunks
        const sentences = response.match(/[^.!?]+[.!?]+/g) || [response];
        
        if (sentences.length <= 2) {
            // 1-2 sentences = one message
            messages = [response];
        } else {
            // 3+ sentences = split into multiple messages
            let currentMessage = '';
            
            for (let i = 0; i < sentences.length; i++) {
                const sentence = sentences[i].trim();
                
                // Start new message if current is getting long (>150 chars) or we have 2 sentences
                const sentenceCount = (currentMessage.match(/[.!?]+/g) || []).length;
                
                if (currentMessage && (currentMessage.length > 150 || sentenceCount >= 2)) {
                    messages.push(currentMessage.trim());
                    currentMessage = sentence;
                } else {
                    currentMessage += (currentMessage ? ' ' : '') + sentence;
                }
            }
            
            // Add the last message
            if (currentMessage.trim()) {
                messages.push(currentMessage.trim());
            }
        }
        
        console.log(`📨 Sending ${messages.length} message(s)`);
        
        // Send messages with realistic delays between them
        for (let i = 0; i < messages.length; i++) {
            if (i === 0) {
                // First message is a reply
                await message.reply(messages[i]);
            } else {
                // Calculate realistic typing delay based on message length
                // ~50ms per character + random variation
                const typingTime = messages[i].length * 50 + Math.random() * 1000;
                const pauseTime = 500 + Math.random() * 500; // Extra pause between messages
                const totalDelay = Math.min(typingTime + pauseTime, 5000); // Cap at 5 seconds
                
                console.log(`   ⏳ Waiting ${(totalDelay/1000).toFixed(1)}s before next message...`);
                
                // Show typing indicator during delay
                await message.channel.sendTyping();
                await new Promise(resolve => setTimeout(resolve, totalDelay));
                await message.channel.send(messages[i]);
            }
        }
        
        console.log(`✅ Replied to ${userName} with ${messages.length} message(s)`);
        
        // 🐱 20% chance to send a cute cat GIF AFTER all messages are sent!
        if (Math.random() < 0.20) {
            const catDelay = 800 + Math.random() * 1200;
            console.log(`   🐱 Preparing cat GIF (waiting ${(catDelay/1000).toFixed(1)}s)...`);
            
            await new Promise(resolve => setTimeout(resolve, catDelay));
            
            // Determine emotion from response
            const responseLower = response.toLowerCase();
            let catEmotion = 'cute'; // default
            
            if (responseLower.includes('happy') || responseLower.includes('yay') || responseLower.includes('exciting') || responseLower.includes('hehe') || responseLower.includes('^^')) {
                catEmotion = 'happy';
            } else if (responseLower.includes('love') || responseLower.includes('♥') || responseLower.includes('miss') || responseLower.includes('<3') || responseLower.includes('💕')) {
                catEmotion = 'love';
            } else if (responseLower.includes('sad') || responseLower.includes('sorry') || responseLower.includes(':(') || responseLower.includes('><')) {
                catEmotion = 'sad';
            } else if (responseLower.includes('sleepy') || responseLower.includes('tired') || responseLower.includes('yawn') || responseLower.includes('2am')) {
                catEmotion = 'sleepy';
            } else if (responseLower.includes('confused') || responseLower.includes('huh') || responseLower.includes('???')) {
                catEmotion = 'confused';
            } else if (responseLower.includes('working') || responseLower.includes('studying') || responseLower.includes('homework') || responseLower.includes('chemistry')) {
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