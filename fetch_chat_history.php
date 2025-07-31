<?php
session_start();
require_once __DIR__ . '/includes/TimezoneHelper.php';
header("Content-Type: application/json");

$user_id = $_SESSION['user_id'];  // Assumes user_id is stored in session after login
$user_country = $_SESSION['country'] ?? 'Singapore'; // Get user's country from session

$host = 'db';  // Docker service name
$db = 'Verztec';
$user = 'user';
$pass = 'password';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save a new chat message
    $data = json_decode(file_get_contents('php://input'), true);
    $text = $data['text'] ?? '';
    $sender = $data['sender'] ?? '';
    $conversation_id = $_SESSION['conversation_id'] ?? 1; // Default or get from session/request

    if (!$text || !$sender) {
        echo json_encode(['error' => 'Missing text or sender']);
        exit();
    }

    // Insert into chat_history (assume sender is either 'user' or 'bot')
    if ($sender === 'user') {
        $sql = "INSERT INTO chat_history (user_id, conversation_id, question, timestamp) VALUES (?, ?, ?, UTC_TIMESTAMP())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $user_id, $conversation_id, $text);
    } else {
        $sql = "INSERT INTO chat_history (user_id, conversation_id, answer, timestamp) VALUES (?, ?, ?, UTC_TIMESTAMP())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $user_id, $conversation_id, $text);
    }
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to save message: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// Default: GET - fetch chat history
$conversation_id = $_SESSION['conversation_id'] ?? 1; // Default or get from session/request
$sql = "SELECT question, answer, timestamp FROM chat_history WHERE user_id = ? AND conversation_id = ? ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $conversation_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    echo json_encode(['error' => 'SQL result retrieval failed: ' . $stmt->error]);
    exit();
}

$history = [];
while ($row = $result->fetch_assoc()) {
    // Convert timestamp from UTC to user's timezone
    if (isset($row['timestamp'])) {
        $row['timestamp'] = TimezoneHelper::formatForDisplay($row['timestamp'], $user_country);
    }
    $history[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($history);
?>
