<?php
// latest_announcement.php
session_start(); // Start session to access user data
require __DIR__ . '/../connect.php';

header('Content-Type: application/json');

// Get user's country for timezone conversion
$user_country = $_SESSION['country'] ?? 'Singapore';

$result = $conn->query("SELECT id, title, context, priority, target_audience, timestamp FROM announcements ORDER BY timestamp DESC LIMIT 1");

if ($result && $row = $result->fetch_assoc()) {
    // Convert timestamp to user's timezone
    $formattedTimestamp = TimezoneHelper::formatForDisplay($row['timestamp'], $user_country);
    
    echo json_encode([
        'id' => $row['id'], // 👈 this is missing in your current version!
        'title' => $row['title'],
        'context' => $row['context'],
        'priority' => $row['priority'],
        'target_audience' => $row['target_audience'],
        'timestamp' => $formattedTimestamp,
        'raw_timestamp' => $row['timestamp'] // Keep original for reference
    ]);
} else {
    echo json_encode([]);
}
?>
