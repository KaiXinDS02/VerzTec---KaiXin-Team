<?php
session_start();
require_once 'connect.php';

// Set JSON content type and prevent HTML output
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create_new_conversation':
            createNewConversation($conn, $user_id);
            break;
        case 'get_user_conversations':
            getUserConversations($conn, $user_id);
            break;
        case 'load_conversation_messages':
            $conversation_id = $_POST['conversation_id'] ?? $_GET['conversation_id'] ?? null;
            loadConversationMessages($conn, $user_id, $conversation_id);
            break;
        case 'delete_conversation':
            $conversation_id = $_POST['conversation_id'] ?? null;
            deleteConversation($conn, $user_id, $conversation_id);
            break;
        case 'rename_conversation':
            $conversation_id = $_POST['conversation_id'] ?? null;
            $new_title = $_POST['new_title'] ?? '';
            renameConversation($conn, $user_id, $conversation_id, $new_title);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// Function 1: Create a new conversation
function createNewConversation($conn, $user_id) {
    try {
        $title = 'New Conversation';
        
        $sql = "INSERT INTO conversations (user_id, title) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $stmt->bind_param("is", $user_id, $title);
        
        if ($stmt->execute()) {
            $conversation_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'conversation_id' => $conversation_id,
                'title' => $title
            ]);
        } else {
            echo json_encode(['error' => 'Failed to create conversation: ' . $stmt->error]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception in createNewConversation: ' . $e->getMessage()]);
    }
}

// Function 2: Get all conversations for current user
function getUserConversations($conn, $user_id) {
    try {
        $sql = "SELECT c.id, c.title, c.created_at, c.updated_at,
                       COUNT(ch.id) as message_count,
                       MAX(ch.timestamp) as last_message_time
                FROM conversations c
                LEFT JOIN chat_history ch ON c.id = ch.conversation_id
                WHERE c.user_id = ?
                GROUP BY c.id, c.title, c.created_at, c.updated_at
                ORDER BY c.updated_at DESC";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            // message_count is the number of chat_history rows for this conversation
            // Do not multiply by 2; each row is one message (question/answer pair)
            // If you want to count both user and bot messages, you can multiply by 2 in the frontend

            // Format timestamp for display
            if ($row['last_message_time']) {
                $timestamp = new DateTime($row['last_message_time']);
                $now = new DateTime();
                $diff = $now->diff($timestamp);

                if ($diff->days > 0) {
                    $row['last_message_display'] = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                } elseif ($diff->h > 0) {
                    $row['last_message_display'] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                } elseif ($diff->i > 0) {
                    $row['last_message_display'] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                } else {
                    $row['last_message_display'] = 'Just now';
                }
            } else {
                $row['last_message_display'] = 'No messages';
                $row['message_count'] = 0;
            }

            $conversations[] = $row;
        }
        
        echo json_encode(['conversations' => $conversations]);
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception in getUserConversations: ' . $e->getMessage()]);
    }
}

// Function 3: Load messages for a specific conversation
function loadConversationMessages($conn, $user_id, $conversation_id) {
    try {
        if (!$conversation_id) {
            echo json_encode(['error' => 'No conversation ID provided']);
            return;
        }
        
        // Verify conversation belongs to current user
        $verify_sql = "SELECT id FROM conversations WHERE id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        
        if (!$verify_stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $verify_stmt->bind_param("ii", $conversation_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['error' => 'Conversation not found or access denied']);
            return;
        }
        
        // Get messages for this conversation
        $sql = "SELECT question, answer, timestamp, voice_file 
                FROM chat_history 
                WHERE conversation_id = ? 
                ORDER BY timestamp ASC";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $stmt->bind_param("i", $conversation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            // Add user message
            $messages[] = [
                'type' => 'user',
                'content' => $row['question'],
                'timestamp' => $row['timestamp']
            ];
            
            // Add bot response
            $messages[] = [
                'type' => 'bot',
                'content' => $row['answer'],
                'timestamp' => $row['timestamp'],
                'voice_file' => $row['voice_file']
            ];
        }
        
        echo json_encode(['messages' => $messages]);
        $stmt->close();
        $verify_stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception in loadConversationMessages: ' . $e->getMessage()]);
    }
}

// Function 4: Delete conversation
function deleteConversation($conn, $user_id, $conversation_id) {
    try {
        if (!$conversation_id) {
            echo json_encode(['error' => 'No conversation ID provided']);
            return;
        }
        
        // Verify conversation belongs to current user
        $verify_sql = "SELECT id FROM conversations WHERE id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        
        if (!$verify_stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $verify_stmt->bind_param("ii", $conversation_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['error' => 'Conversation not found or access denied']);
            return;
        }
        
        // Delete related chat history first
        $delete_history_sql = "DELETE FROM chat_history WHERE conversation_id = ?";
        $delete_history_stmt = $conn->prepare($delete_history_sql);
        
        if ($delete_history_stmt) {
            $delete_history_stmt->bind_param("i", $conversation_id);
            $delete_history_stmt->execute();
            $delete_history_stmt->close();
        }
        
        // Delete conversation
        $delete_sql = "DELETE FROM conversations WHERE id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        
        if (!$delete_stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $delete_stmt->bind_param("ii", $conversation_id, $user_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete conversation: ' . $delete_stmt->error]);
        }
        
        $delete_stmt->close();
        $verify_stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception in deleteConversation: ' . $e->getMessage()]);
    }
}

// Function 5: Rename conversation
function renameConversation($conn, $user_id, $conversation_id, $new_title) {
    try {
        if (!$conversation_id || !$new_title) {
            echo json_encode(['error' => 'Missing conversation ID or title']);
            return;
        }
        
        // Verify conversation belongs to current user
        $verify_sql = "SELECT id FROM conversations WHERE id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        
        if (!$verify_stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $verify_stmt->bind_param("ii", $conversation_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode(['error' => 'Conversation not found or access denied']);
            return;
        }
        
        // Update conversation title
        $update_sql = "UPDATE conversations SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            return;
        }
        
        $update_stmt->bind_param("sii", $new_title, $conversation_id, $user_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to rename conversation: ' . $update_stmt->error]);
        }
        
        $update_stmt->close();
        $verify_stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception in renameConversation: ' . $e->getMessage()]);
    }
}
?>
