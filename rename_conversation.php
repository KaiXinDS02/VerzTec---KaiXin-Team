<?php
session_start();
include('connect.php');
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$conversation_id = $data['conversation_id'] ?? null;
$new_name = trim($data['new_name'] ?? '');

if (!$conversation_id || $new_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Only allow renaming if the conversation belongs to the user
$stmt = $conn->prepare('UPDATE conversations SET title = ? WHERE id = ? AND user_id = ?');
$stmt->bind_param('sii', $new_name, $conversation_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Rename failed or no change']);
}
$stmt->close();
$conn->close();
