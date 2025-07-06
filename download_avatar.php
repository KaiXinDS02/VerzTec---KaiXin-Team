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

// Build the enhanced URL with morph targets for facial expressions
$enhancedUrl = "https://models.readyplayer.me/{$avatarId}.glb?morphTargets=ARKit,Oculus Visemes&quality=medium";

// Use the provided avatar URL if it already has morph targets, otherwise use enhanced URL
$downloadUrl = (strpos($avatarUrl, 'morphTargets') !== false) ? $avatarUrl : $enhancedUrl;

// Download the avatar with morph targets
$context = stream_context_create([
    'http' => [
        'timeout' => 60, // Increased timeout for larger files with morph targets
        'user_agent' => 'VerzTec Avatar Downloader/1.0',
        'method' => 'GET',
        'header' => [
            'Accept: application/octet-stream',
            'Accept-Encoding: gzip, deflate'
        ]
    ]
]);

$avatarData = file_get_contents($downloadUrl, false, $context);

if ($avatarData === false) {
    // Log the error for debugging
    error_log("Failed to download avatar from: " . $downloadUrl);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to download avatar from Ready Player Me']);
    exit;
}

// Validate that we received GLB data
if (strlen($avatarData) < 1000) {
    error_log("Avatar data too small, might be an error response");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid avatar data received']);
    exit;
}

// Save the avatar to the assets folder with morph targets
$filename = $avatarId . '_with_morphs.glb';
$filepath = $avatarsDir . $filename;

if (file_put_contents($filepath, $avatarData) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save avatar to local storage']);
    exit;
}

// Verify file was saved correctly
if (!file_exists($filepath) || filesize($filepath) === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Avatar file not saved properly']);
    exit;
}

// Return the local URL
$localUrl = $filepath;

// Log the download with enhanced information
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'avatar_id' => $avatarId,
    'original_url' => $avatarUrl,
    'download_url' => $downloadUrl,
    'local_path' => $filepath,
    'file_size' => filesize($filepath),
    'has_morph_targets' => (strpos($downloadUrl, 'morphTargets') !== false)
];

// Ensure logs directory exists
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

file_put_contents('logs/avatar_downloads.log', json_encode($logData) . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'localUrl' => $localUrl,
    'filename' => $filename,
    'fileSize' => filesize($filepath)
]);
?>
