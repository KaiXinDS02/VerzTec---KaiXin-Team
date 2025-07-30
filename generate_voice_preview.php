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

if (!$input || !isset($input['voice_id']) || !isset($input['text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing voice_id or text']);
    exit;
}

$voice_id = $input['voice_id'];
$text = $input['text'];

// Your ElevenLabs API key
$api_key = 'sk_72283c30a844b3d198dda76a38373741c8968217a9472ae7';

// ElevenLabs API endpoint
$url = "https://api.elevenlabs.io/v1/text-to-speech/{$voice_id}";

// Prepare the request data
$data = [
    'text' => $text,
    'model_id' => 'eleven_turbo_v2_5',
    'voice_settings' => [
        'stability' => 0.5,
        'similarity_boost' => 0.8
    ]
];

// Initialize cURL
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: audio/mpeg',
    'Content-Type: application/json',
    'xi-api-key: ' . $api_key
]);

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode(['error' => 'ElevenLabs API error', 'http_code' => $http_code]);
    exit;
}

// Return the audio data
header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($response));
echo $response;
?>
