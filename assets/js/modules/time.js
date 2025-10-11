// =========================================
// TIME MODULE
// Time display and day/night cycle
// =========================================

import { timeDisplay, celestialBody } from '../chat.js';

// Initialize time system
export function initializeTime() {
    updateTimeDisplay();
    setInterval(updateTimeDisplay, 60000); // Update every minute
    
    updateGreeting();
}

// Update time display
function updateTimeDisplay() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    timeDisplay.textContent = `${hours}:${minutes}`;
    
    // Update sun/moon
    const hour = now.getHours();
    if (hour >= 6 && hour < 18) {
        celestialBody.className = 'sun';
    } else {
        celestialBody.className = 'moon';
    }
}

// Get time of day
export function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'morning';
    if (hour >= 12 && hour < 17) return 'afternoon';
    if (hour >= 17 && hour < 21) return 'evening';
    return 'night';
}

// Get time-based greeting
function getTimeBasedGreeting() {
    const timeOfDay = getTimeOfDay();
    const greetings = {
        morning: "Good morning! ☀️ I hope you slept well. How are you feeling today?",
        afternoon: "Good afternoon! 🌤️ How has your day been treating you so far?",
        evening: "Good evening! 🌆 I hope you had a nice day. Want to talk about it?",
        night: "It's quite late... 🌙 Are you having trouble sleeping, or just enjoying the quiet night?"
    };
    return greetings[timeOfDay];
}

// Update greeting message
function updateGreeting() {
    const greetingMessage = document.getElementById('greetingMessage');
    if (greetingMessage) {
        greetingMessage.textContent = getTimeBasedGreeting();
    }
}