<?php
session_start();
header('Content-Type: application/json');
include('connect.php');


$user_id = $_SESSION['user_id'] ?? 1;

// Check if user has any conversations, if not, create one
$sql = "SELECT id, title, created_at, updated_at FROM conversations WHERE user_id = ? ORDER BY updated_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();

if (count($conversations) === 0) {
    $title = 'New Conversation';
    $sql = "INSERT INTO conversations (user_id, title) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $user_id, $title);
    if ($stmt->execute()) {
        $conversations[] = [
            'id' => $stmt->insert_id,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    $stmt->close();
}

$conn->close();
echo json_encode($conversations);
