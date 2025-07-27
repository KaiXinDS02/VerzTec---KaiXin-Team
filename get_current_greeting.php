<?php
session_start();
require_once __DIR__ . '/includes/TimezoneHelper.php';

header('Content-Type: application/json');

// Get user's country from session
$user_country = $_SESSION['country'] ?? 'Singapore';

// Get current greeting based on user's timezone
$greeting = TimezoneHelper::getGreeting($user_country);

// Return greeting as JSON
echo json_encode(['greeting' => $greeting]);
?>
