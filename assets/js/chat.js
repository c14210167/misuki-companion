// =========================================
// MAIN CHAT.JS - Entry Point
// All modules are loaded and initialized here
// =========================================

// DOM Elements - Exported for use in other modules
export const chatMessages = document.getElementById('chatMessages');
export const messageInput = document.getElementById('messageInput');
export const typingIndicator = document.getElementById('typingIndicator');
export const misukiImage = document.getElementById('misukiImage');
export const misukiMood = document.getElementById('misukiMood');
export const timeDisplay = document.getElementById('timeDisplay');
export const celestialBody = document.getElementById('celestialBody');
export const rainContainer = document.getElementById('rainContainer');
export const blurOverlay = document.getElementById('blurOverlay');

// Global state - Initialize on window object so modules can access
window.lastUserMessageTime = Date.now();
window.attachedFile = null;
window.userIsTyping = false;
window.followUpTimeout = null;
window.lastMessageDate = null;

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Initializing Misuki Chat System...');
    
    try {
        // Import all modules dynamically
        console.log('📦 Loading modules...');
        
        const settingsModule = await import('./modules/settings.js');
        const rainModule = await import('./modules/rain.js');
        const timeModule = await import('./modules/time.js');
        const effectsModule = await import('./modules/effects.js');
        const messagingModule = await import('./modules/messaging.js');
        const historyModule = await import('./modules/history.js');
        const statusModule = await import('./modules/status.js');
        const fileUploadModule = await import('./modules/fileUpload.js');
        
        // Initialize all systems
        console.log('⚙️ Initializing settings...');
        settingsModule.initializeSettings();
        
        console.log('🌧️ Initializing rain...');
        rainModule.initializeRain();
        
        console.log('🕐 Initializing time...');
        timeModule.initializeTime();
        
        console.log('✨ Initializing effects...');
        effectsModule.initializeEffects();
        
        console.log('💬 Initializing messaging...');
        messagingModule.initializeMessaging();
        
        console.log('📎 Initializing file upload...');
        fileUploadModule.initializeFileUpload();
        
        // Load chat history
        console.log('📜 Loading chat history...');
        await historyModule.loadChatHistory();
        
        // Update Misuki's status
        console.log('📊 Updating Misuki status...');
        statusModule.updateLiveStatus();
        setInterval(() => statusModule.updateLiveStatus(), 60000); // Every minute
        
        // Check for Misuki initiations
        console.log('💕 Setting up initiation checks...');
        setTimeout(() => statusModule.checkForInitiation(), 30000); // After 30 seconds
        setInterval(() => statusModule.checkForInitiation(), 15 * 60 * 1000); // Every 15 minutes
        
        console.log('✅ Misuki Chat System Ready!');
        console.log('💕 You can now chat with Misuki!');
    } catch (error) {
        console.error('❌ Error initializing chat system:', error);
        console.error('Error details:', error.message);
        console.error('Stack trace:', error.stack);
        alert('Failed to initialize chat system. Check console for details.');
    }
});