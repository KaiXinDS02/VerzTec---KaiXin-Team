<?php
// latest_announcement.php
require __DIR__ . '/../connect.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT id, title, context, priority, target_audience, timestamp FROM announcements ORDER BY timestamp DESC LIMIT 1");

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        'id' => $row['id'], // 👈 this is missing in your current version!
        'title' => $row['title'],
        'context' => $row['context'],
        'priority' => $row['priority'],
        'target_audience' => $row['target_audience'],
        'timestamp' => $row['timestamp']
    ]);
} else {
    echo json_encode([]);
}
?>
