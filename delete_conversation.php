<?php
session_start();
header('Content-Type: application/json');
$user_id = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$conversation_id = (int)($data['conversation_id'] ?? 0);
if (!$user_id || !$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing user or conversation id']);
    exit();
}
$host = 'db';
$db = 'Verztec';
$user = 'user';
$pass = 'password';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit();
}
$sql = "DELETE FROM conversations WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $conversation_id, $user_id);
if ($stmt->execute()) {
    // If the deleted conversation was active, unset it
    if (isset($_SESSION['conversation_id']) && $_SESSION['conversation_id'] == $conversation_id) {
        unset($_SESSION['conversation_id']);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
