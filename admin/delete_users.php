<?php
require __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'fail';
    }

    $stmt->close();
    $conn->close();
} else {
    echo 'invalid';
}
?>
