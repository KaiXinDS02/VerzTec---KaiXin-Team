<?php
// Docker Database Migration Script for Conversation System
// This adds conversation tables to the existing Docker database

require_once 'connect.php';

echo "🐳 VerzTec Docker Conversation System Migration\n";
echo "===============================================\n";

try {
    // Test database connection first
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Connected to Docker database successfully\n";
    
    // Check if conversations table already exists
    $check_conversations = $conn->query("SHOW TABLES LIKE 'conversations'");
    if ($check_conversations && $check_conversations->num_rows > 0) {
        echo "ℹ️  Conversations table already exists\n";
    } else {
        // Create conversations table
        $create_conversations = "
        CREATE TABLE conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT 'New Conversation',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_conversations (user_id),
            INDEX idx_updated_at (updated_at)
        )";
        
        if ($conn->query($create_conversations)) {
            echo "✅ Created conversations table\n";
        } else {
            throw new Exception("Failed to create conversations table: " . $conn->error);
        }
    }
    
    // Check if conversation_id column exists in chat_history
    $check_column = $conn->query("SHOW COLUMNS FROM chat_history LIKE 'conversation_id'");
    if ($check_column && $check_column->num_rows > 0) {
        echo "ℹ️  conversation_id column already exists in chat_history\n";
    } else {
        // Add conversation_id column to chat_history
        $add_column = "ALTER TABLE chat_history ADD COLUMN conversation_id INT, ADD INDEX idx_conversation_id (conversation_id)";
        
        if ($conn->query($add_column)) {
            echo "✅ Added conversation_id column to chat_history table\n";
        } else {
            throw new Exception("Failed to add conversation_id column: " . $conn->error);
        }
    }
    
    // Check if voice_file column exists in chat_history
    $check_voice_column = $conn->query("SHOW COLUMNS FROM chat_history LIKE 'voice_file'");
    if ($check_voice_column && $check_voice_column->num_rows > 0) {
        echo "ℹ️  voice_file column already exists in chat_history\n";
    } else {
        // Add voice_file column to chat_history
        $add_voice_column = "ALTER TABLE chat_history ADD COLUMN voice_file VARCHAR(255)";
        
        if ($conn->query($add_voice_column)) {
            echo "✅ Added voice_file column to chat_history table\n";
        } else {
            throw new Exception("Failed to add voice_file column: " . $conn->error);
        }
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    echo "📋 Your conversation system is now ready for team collaboration.\n";
    echo "🔗 All team members can now access the conversation features.\n\n";
    
    // Show table status
    echo "📊 Database Status:\n";
    echo "==================\n";
    
    $tables = ['users', 'conversations', 'chat_history'];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "• $table: {$row['count']} records\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
