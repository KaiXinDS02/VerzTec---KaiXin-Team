<?php
session_start();
header('Content-Type: application/json');
include('connect.php');

$user_id = $_SESSION['user_id'] ?? 1;

// Fetch all conversations for the user
$sql = "SELECT id, title, created_at, updated_at FROM conversations WHERE user_id = ? ORDER BY updated_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();


$conversations = [];
while ($row = $result->fetch_assoc()) {
    // Only include conversations with at least one message (user or assistant)
    $sql2 = "SELECT question, answer FROM chat_history WHERE conversation_id = ? AND (question != '' OR answer != '') LIMIT 1";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $row['id']);
    $stmt2->execute();
    $stmt2->bind_result($q, $a);
    $has_message = $stmt2->fetch();
    $stmt2->close();
    if ($has_message) {
        $conversations[] = $row;
    }
}
$stmt->close();

// For each conversation, fetch the first user message for preview, or first assistant message if no user message
foreach ($conversations as &$conv) {
    $sql2 = "SELECT question FROM chat_history WHERE conversation_id = ? AND user_id = ? AND question IS NOT NULL AND question != '' ORDER BY id ASC LIMIT 1";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('ii', $conv['id'], $user_id);
    $stmt2->execute();
    $stmt2->bind_result($first_question);
    $stmt2->fetch();
    if ($first_question) {
        $conv['preview'] = mb_substr($first_question, 0, 30);
    } else {
        // If no user question, use first assistant answer
        $stmt2->close();
        $sql3 = "SELECT answer FROM chat_history WHERE conversation_id = ? AND answer IS NOT NULL AND answer != '' ORDER BY id ASC LIMIT 1";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param('i', $conv['id']);
        $stmt3->execute();
        $stmt3->bind_result($first_answer);
        $stmt3->fetch();
        $conv['preview'] = $first_answer ? mb_substr($first_answer, 0, 30) : 'New Chat';
        $stmt3->close();
        continue;
    }
    $stmt2->close();
}

$conn->close();
echo json_encode($conversations);
