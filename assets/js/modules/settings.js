// =========================================
// SETTINGS MODULE
// Rain intensity and other settings
// =========================================

// Export currentIntensity so rain.js can access it
export let currentIntensity = 'light';

// Function to update the intensity (called by rain.js)
export function setCurrentIntensity(intensity) {
    currentIntensity = intensity;
}

// Initialize settings
export function initializeSettings() {
    // Make functions globally available for onclick handlers
    window.toggleSettings = toggleSettings;
    window.setRainIntensity = setRainIntensity;
}

// Toggle settings panel
function toggleSettings() {
    const panel = document.getElementById('settingsPanel');
    if (panel) {
        panel.classList.toggle('active');
    }
}

// Set rain intensity
function setRainIntensity(intensity) {
    // Update the exported variable
    currentIntensity = intensity;
    
    // Update active button
    document.querySelectorAll('.intensity-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find the button that was clicked and make it active
    const clickedButton = event?.target;
    if (clickedButton) {
        clickedButton.classList.add('active');
    }
    
    // Recreate rain with new intensity
    import('./rain.js').then(module => {
        module.createRain(intensity);
    });
}