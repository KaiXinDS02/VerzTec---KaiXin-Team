<?php
session_start();
header("Content-Type: application/json");

$user_id = $_SESSION['user_id'];  // Assumes user_id is stored in session after login

$host = 'localhost';
$db = 'verztec';
$user = 'root';
$pass = 'yourpassword';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$sql = "SELECT question, answer, timestamp FROM chat_history WHERE user_id = ? ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode($history);
?>
