<?php
// get_profile.php
// Returns the current user's profile info (nickname and profile picture URL)

session_start();
header('Content-Type: application/json');

// You may need to adjust this depending on your auth system
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Example: store user profiles in a simple file or database
$profileDir = __DIR__ . '/assets/avatars/profiles/';
$profileFile = $profileDir . $user_id . '.json';
$defaultPic = 'assets/avatars/default.png';

if (!file_exists($profileFile)) {
    echo json_encode([
        'nickname' => 'User',
        'profile_pic_url' => $defaultPic
    ]);
    exit;
}

$profile = json_decode(file_get_contents($profileFile), true);
if (!$profile) $profile = [];

$nickname = isset($profile['nickname']) ? $profile['nickname'] : 'User';
$picUrl = isset($profile['profile_pic']) && file_exists(__DIR__ . '/' . $profile['profile_pic'])
    ? $profile['profile_pic']
    : $defaultPic;

echo json_encode([
    'nickname' => $nickname,
    'profile_pic_url' => $picUrl
]);
