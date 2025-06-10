<?php
require __DIR__ . '/../connect.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT user_id, username, email, department, role, country FROM users ORDER BY user_id DESC");

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id'     => $row['user_id'],
            'username'    => $row['username'],
            'email'       => $row['email'],
            'department'  => $row['department'],
            'role'        => $row['role'],
            'country'     => $row['country'],
        ];
    }
}
echo json_encode($users);
?>
