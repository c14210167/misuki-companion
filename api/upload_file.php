<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_POST['user_id'] ?? 1;

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    
    $error = $error_messages[$file['error']] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'error' => $error, 'code' => $file['error']]);
    exit;
}

$allowed_extensions = ['txt', 'md'];
$max_size = 10 * 1024 * 1024; // 10MB

// Get file extension
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)', 'size' => $file['size']]);
    exit;
}

if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'error' => 'Please convert to .txt format first. Only .txt and .md files are supported.', 'ext' => $file_ext]);
    exit;
}

// Create uploads directory if doesn't exist
$upload_dir = dirname(__DIR__) . '/uploads/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create uploads directory', 'path' => $upload_dir]);
        exit;
    }
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    echo json_encode(['success' => false, 'error' => 'Uploads directory is not writable', 'path' => $upload_dir]);
    exit;
}

// Generate unique filename
$unique_name = $user_id . '_' . time() . '_' . basename($file['name']);
$upload_path = $upload_dir . $unique_name;

// Move file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Read text content
    $content = file_get_contents($upload_path);
    
    if ($content === false) {
        echo json_encode(['success' => false, 'error' => 'Could not read file content']);
        exit;
    }
    
    // Truncate if too long (keep first 50,000 characters for context)
    $truncated = false;
    if (strlen($content) > 50000) {
        $content = substr($content, 0, 50000);
        $truncated = true;
    }
    
    echo json_encode([
        'success' => true,
        'filename' => $file['name'],
        'content' => $content,
        'truncated' => $truncated,
        'word_count' => str_word_count($content),
        'file_path' => $unique_name
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to move uploaded file',
        'tmp_name' => $file['tmp_name'],
        'target_path' => $upload_path,
        'tmp_exists' => file_exists($file['tmp_name']),
        'dir_writable' => is_writable($upload_dir)
    ]);
}
?>