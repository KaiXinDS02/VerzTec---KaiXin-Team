<?php
require __DIR__ . '/../connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id    = intval($_POST['user_id']);
    $username   = $conn->real_escape_string($_POST['username']);
    $email      = $conn->real_escape_string($_POST['email']);
    $department = $conn->real_escape_string($_POST['department']);
    $role       = $conn->real_escape_string($_POST['role']);
    $country    = $conn->real_escape_string($_POST['country']);
    $password   = $_POST['password']; // Do not escape yet

    $update_fields = "
        username = '$username',
        email = '$email',
        department = '$department',
        role = '$role',
        country = '$country'
    ";

    // If password is provided and not empty, update it
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields .= ", password = '" . $conn->real_escape_string($hashed_password) . "'";
    }

    $sql = "UPDATE users SET $update_fields WHERE user_id = $user_id";

    if ($conn->query($sql)) {
        echo "success";
    } else {
        echo "error: " . $conn->error;
    }
}
?>
