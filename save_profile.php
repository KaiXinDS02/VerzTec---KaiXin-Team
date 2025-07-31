<?php
// save_profile.php
// Handles profile updates: nickname and profile picture upload

session_start();
header('Content-Type: application/json');

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$profileDir = __DIR__ . '/assets/avatars/profiles/';
if (!is_dir($profileDir)) {
    mkdir($profileDir, 0777, true);
}
$profileFile = $profileDir . $user_id . '.json';

$nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : '';
if ($nickname === '') $nickname = 'User';

$profile = [
    'nickname' => $nickname
];

// Handle profile picture upload
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $picPath = 'assets/avatars/profiles/' . $user_id . '.' . $ext;
        $fullPath = __DIR__ . '/' . $picPath;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $fullPath)) {
            $profile['profile_pic'] = $picPath;
        }
    }
}

// If previous profile pic exists, keep it if not overwritten
if (file_exists($profileFile)) {
    $old = json_decode(file_get_contents($profileFile), true);
    if (isset($old['profile_pic']) && !isset($profile['profile_pic'])) {
        $profile['profile_pic'] = $old['profile_pic'];
    }
}

file_put_contents($profileFile, json_encode($profile));
echo json_encode(['success' => true]);
