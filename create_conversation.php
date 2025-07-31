<?php
session_start();
header('Content-Type: application/json');
include('connect.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$title = trim($data['title'] ?? 'New Conversation');
if ($title === '') $title = 'New Conversation';

// Prevent duplicate titles for the same user
$sql = "SELECT id FROM conversations WHERE user_id = ? AND title = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $user_id, $title);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['error' => 'Conversation with this title already exists.']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

$sql = "INSERT INTO conversations (user_id, title) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $user_id, $title);
if ($stmt->execute()) {
    $conversation_id = $stmt->insert_id;
    $_SESSION['conversation_id'] = $conversation_id;
    echo json_encode(['success' => true, 'id' => $conversation_id, 'title' => $title]);
} else {
    echo json_encode(['error' => 'Failed to create conversation']);
}
$stmt->close();
$conn->close();
