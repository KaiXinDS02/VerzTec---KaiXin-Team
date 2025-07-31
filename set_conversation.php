<?php
session_start();
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$conversation_id = isset($data['conversation_id']) ? intval($data['conversation_id']) : 0;

if ($conversation_id <= 0) {
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit();
}

$_SESSION['conversation_id'] = $conversation_id;
echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
