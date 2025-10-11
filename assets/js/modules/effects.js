// =========================================
// EFFECTS MODULE
// Visual effects (sparkles, screen shake, etc.)
// =========================================

// Initialize effects
export function initializeEffects() {
    // Random sparkles
    setInterval(() => {
        const x = Math.random() * window.innerWidth;
        const y = Math.random() * window.innerHeight;
        if (Math.random() > 0.7) createSparkle(x, y);
    }, 3000);
}

// Create sparkle effect
export function createSparkle(x, y) {
    const sparkle = document.createElement('div');
    sparkle.className = 'sparkle';
    sparkle.textContent = '✨';
    sparkle.style.left = x + 'px';
    sparkle.style.top = y + 'px';
    document.body.appendChild(sparkle);
    setTimeout(() => sparkle.remove(), 2000);
}

// Screen shake effect
export function triggerScreenShake() {
    const container = document.querySelector('.chat-container');
    if (container) {
        container.classList.add('screen-shake');
        setTimeout(() => container.classList.remove('screen-shake'), 500);
    }
}

// Flash effect
export function triggerFlash() {
    const flash = document.createElement('div');
    flash.className = 'screen-flash';
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 200);
}

// Blur effect
export function triggerBlur() {
    const container = document.querySelector('.chat-container');
    if (container) {
        container.classList.add('screen-blur');
        setTimeout(() => container.classList.remove('screen-blur'), 2000);
    }
}