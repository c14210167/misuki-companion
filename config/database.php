<?php

function getDBConnection() {
    $host = 'localhost';
    $dbname = 'misuki_companion';
    $username = 'root'; // Change this for production
    $password = ''; // Change this for production
    
    try {
        $db = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $db;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Failed to connect to database");
    }
}

function initializeDatabase() {
    $db = getDBConnection();
    
    // Create users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create conversations table
    $db->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            conversation_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            user_message TEXT NOT NULL,
            misuki_response TEXT NOT NULL,
            mood VARCHAR(20) DEFAULT 'neutral',
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_timestamp (user_id, timestamp)
        )
    ");
    
    // Create memories table
    $db->exec("
        CREATE TABLE IF NOT EXISTS memories (
            memory_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            memory_type VARCHAR(50),
            memory_key VARCHAR(100),
            memory_value TEXT,
            importance_score INT DEFAULT 5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            access_count INT DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_importance (user_id, importance_score),
            INDEX idx_user_accessed (user_id, last_accessed)
        )
    ");
    
    // Create emotional_states table
    $db->exec("
        CREATE TABLE IF NOT EXISTS emotional_states (
            state_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            detected_emotion VARCHAR(50),
            context TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_timestamp (user_id, timestamp)
        )
    ");
    
    // Create discussion_topics table
    $db->exec("
        CREATE TABLE IF NOT EXISTS discussion_topics (
            topic_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            topic VARCHAR(100),
            sentiment VARCHAR(20),
            mention_count INT DEFAULT 1,
            last_mentioned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_topic (user_id, topic)
        )
    ");
    
    echo "Database initialized successfully!\n";
}

// Run this once to set up the database
// initializeDatabase();

?>