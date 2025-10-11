// =========================================
// RAIN MODULE
// Rain effects and intensity control
// =========================================

import { rainContainer, blurOverlay } from '../chat.js';

// Rain intensity settings
const rainIntensities = {
    none: { count: 0, speed: [0.5, 1], width: 2, height: 15 },
    drizzle: { count: 30, speed: [0.8, 1.2], width: 1, height: 10 },
    light: { count: 100, speed: [0.5, 1], width: 2, height: 15 },
    moderate: { count: 200, speed: [0.4, 0.8], width: 2, height: 20 },
    heavy: { count: 350, speed: [0.3, 0.6], width: 3, height: 25 },
    storm: { count: 500, speed: [0.2, 0.4], width: 3, height: 30 },
    thundering: { count: 700, speed: [0.15, 0.3], width: 4, height: 35 }
};

// Keep track of current intensity internally
let currentIntensity = 'light';

// Initialize rain system
export function initializeRain() {
    createRain('light');
    randomBlur();
}

// Create rain effect - now accepts intensity as parameter
export function createRain(intensity) {
    // Update current intensity if provided
    if (intensity) {
        currentIntensity = intensity;
    }
    
    // Clear existing rain
    rainContainer.innerHTML = '';
    
    const settings = rainIntensities[currentIntensity];
    
    for (let i = 0; i < settings.count; i++) {
        const drop = document.createElement('div');
        drop.className = 'raindrop';
        drop.style.left = Math.random() * 100 + '%';
        drop.style.width = settings.width + 'px';
        drop.style.height = settings.height + 'px';
        drop.style.animationDuration = (Math.random() * (settings.speed[1] - settings.speed[0]) + settings.speed[0]) + 's';
        drop.style.animationDelay = Math.random() * 2 + 's';
        
        // For heavy rain, add opacity variation
        if (currentIntensity === 'heavy' || currentIntensity === 'storm' || currentIntensity === 'thundering') {
            drop.style.opacity = 0.4 + Math.random() * 0.4;
        }
        
        rainContainer.appendChild(drop);
    }
    
    // Thunder effect for thundering mode
    if (currentIntensity === 'thundering') {
        createThunderEffect();
    }
}

// Thunder effect
function createThunderEffect() {
    const thunder = () => {
        // Flash effect
        blurOverlay.style.background = 'rgba(255, 255, 255, 0.3)';
        blurOverlay.classList.add('active');
        
        setTimeout(() => {
            blurOverlay.style.background = '';
            blurOverlay.classList.remove('active');
        }, 100);
        
        // Random thunder every 5-15 seconds
        setTimeout(thunder, 5000 + Math.random() * 10000);
    };
    
    setTimeout(thunder, 3000);
}

// Random blur activation
function randomBlur() {
    const activate = () => {
        blurOverlay.classList.add('active');
        setTimeout(() => {
            blurOverlay.classList.remove('active');
        }, 3000 + Math.random() * 2000);
    };
    
    setInterval(() => {
        if (Math.random() > 0.5) {
            activate();
        }
    }, 15000 + Math.random() * 15000);
}