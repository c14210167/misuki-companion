// =========================================
// FILE UPLOAD MODULE
// Handle file attachments
// =========================================

// Initialize file upload system
export function initializeFileUpload() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
}

// Handle file selection
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const filePreview = document.getElementById('filePreview');
    const filePreviewText = document.getElementById('filePreviewText');
    
    // Show preview
    filePreviewText.textContent = `📄 ${file.name}`;
    filePreview.style.display = 'block';
    
    // Upload file
    const formData = new FormData();
    formData.append('file', file);
    formData.append('user_id', 1);
    
    fetch('api/upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.attachedFile = {
                filename: data.filename,
                content: data.content,
                word_count: data.word_count,
                truncated: data.truncated
            };
            console.log('✅ File uploaded:', data);
            
            if (data.truncated) {
                filePreviewText.textContent = `📄 ${file.name} (first 50k chars)`;
            }
        } else {
            alert('Error uploading file: ' + data.error);
            removeFile();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        alert('Failed to upload file');
        removeFile();
    });
}

// Remove attached file
export function removeFile() {
    window.attachedFile = null;
    const filePreview = document.getElementById('filePreview');
    const fileInput = document.getElementById('fileInput');
    
    if (filePreview) {
        filePreview.style.display = 'none';
    }
    if (fileInput) {
        fileInput.value = '';
    }
}

// Make removeFile globally available for inline onclick
window.removeFile = removeFile;