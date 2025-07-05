<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['avatarUrl']) || !isset($input['avatarId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$avatarUrl = $input['avatarUrl'];
$avatarId = $input['avatarId'];

// Validate avatar ID (should be 24 character hex string)
if (!preg_match('/^[a-f0-9]{24}$/', $avatarId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid avatar ID format']);
    exit;
}

// Create avatars directory if it doesn't exist
$avatarsDir = 'assets/avatars/models/';
if (!is_dir($avatarsDir)) {
    if (!mkdir($avatarsDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create avatars directory']);
        exit;
    }
}

// Build the enhanced URL with morph targets
$enhancedUrl = "https://models.readyplayer.me/{$avatarId}.glb?morphTargets=ARKit,Oculus Visemes&quality=medium";

// Download the avatar
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'VerzTec Avatar Downloader'
    ]
]);

$avatarData = file_get_contents($enhancedUrl, false, $context);

if ($avatarData === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to download avatar']);
    exit;
}

// Save the avatar to the assets folder
$filename = $avatarId . '.glb';
$filepath = $avatarsDir . $filename;

if (file_put_contents($filepath, $avatarData) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save avatar']);
    exit;
}

// Return the local URL
$localUrl = $filepath;

// Log the download
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'avatar_id' => $avatarId,
    'original_url' => $avatarUrl,
    'enhanced_url' => $enhancedUrl,
    'local_path' => $filepath,
    'file_size' => filesize($filepath)
];

file_put_contents('logs/avatar_downloads.log', json_encode($logData) . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'localUrl' => $localUrl,
    'filename' => $filename,
    'fileSize' => filesize($filepath)
]);
?>
