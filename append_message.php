<?php
session_start();
header('Content-Type: application/json');
include('connect.php');

$user_id = $_SESSION['user_id'] ?? 1;
$conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
$question = trim($_POST['question'] ?? '');
$answer = trim($_POST['answer'] ?? '');


if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation_id']);
    exit();
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
