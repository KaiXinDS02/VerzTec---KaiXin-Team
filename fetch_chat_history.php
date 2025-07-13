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

$sql = "SELECT question, answer, timestamp FROM chat_history WHERE user_id = ? AND conversation_id = ? ORDER BY timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
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
