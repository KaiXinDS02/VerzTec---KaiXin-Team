<?php
session_start();
header("Content-Type: application/json");

$user_id = $_SESSION['user_id'];  // Assumes user_id is stored in session after login

$host = 'db';  // Docker service name
$db = 'Verztec';
$user = 'user';
$pass = 'password';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$sql = "SELECT question, answer, timestamp FROM chat_history WHERE user_id = ? ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'SQL execute failed: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();
if (!$result) {
    echo json_encode(['error' => 'SQL result retrieval failed: ' . $stmt->error]);
    exit();
}

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($history);
?>
