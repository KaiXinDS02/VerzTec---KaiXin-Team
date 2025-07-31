<?php
session_start();
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$conversation_id = $_SESSION['conversation_id'] ?? 1;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$host = 'db';
$db = 'Verztec';
$user = 'user';
$pass = 'password';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "DELETE FROM chat_history WHERE user_id = ? AND conversation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $conversation_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
