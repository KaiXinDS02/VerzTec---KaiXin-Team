<?php
session_start();
header('Content-Type: application/json');
include('connect.php');

$user_id = $_SESSION['user_id'] ?? 1;

$conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
$question = trim($_POST['question'] ?? '');
$answer = trim($_POST['answer'] ?? '');

// If no conversation_id, auto-create one for this user
if (!$conversation_id) {
    // Check if user has any conversations
    $sql = "SELECT id FROM conversations WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($existing_conversation_id);
    if ($stmt->fetch()) {
        $conversation_id = $existing_conversation_id;
    } else {
        // Create a new conversation
        $stmt->close();
        $title = 'New Conversation';
        $sql = "INSERT INTO conversations (user_id, title) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $user_id, $title);
        if ($stmt->execute()) {
            $conversation_id = $stmt->insert_id;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create conversation']);
            $stmt->close();
            $conn->close();
            exit();
        }
    }
    $stmt->close();
}

// Prevent storing empty messages (both question and answer blank)
if ($question === '' && $answer === '') {
    echo json_encode(['success' => false, 'error' => 'Both question and answer are empty']);
    exit();
}

$sql = "INSERT INTO chat_history (user_id, question, answer, conversation_id) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('issi', $user_id, $question, $answer, $conversation_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
