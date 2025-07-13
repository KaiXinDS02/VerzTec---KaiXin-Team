<?php
// ---------------------------------------------------------------------------
// fetch_users.php (charmaine)
// Purpose:
//   Returns JSON list of users based on current user's role and department.
//
// Behavior:
//   - ADMIN: returns all users.
//   - MANAGER: returns users in same department.
//   - Others or missing dept: returns empty array.
//
// Dependencies:
//   - Requires DB connection and active session.
//
// Output:
//   - JSON array of user data.
// ---------------------------------------------------------------------------

require __DIR__ . '/../connect.php';
session_start(); // Needed to access session data
header('Content-Type: application/json');

$users = [];

// Default: return empty array if session role not set
if (!isset($_SESSION['role'])) {
    echo json_encode($users);
    exit;
}

$role = $_SESSION['role'];

if ($role === 'ADMIN') {
    // Admins can see all users
    $stmt = $conn->prepare("SELECT user_id, username, email, department, role, country FROM users ORDER BY user_id DESC");
} elseif ($role === 'MANAGER' && isset($_SESSION['department'])) {
    // Managers see users only in their department
    $stmt = $conn->prepare("SELECT user_id, username, email, department, role, country FROM users WHERE department = ? ORDER BY user_id DESC");
    $stmt->bind_param("s", $_SESSION['department']);
} else {
    // Other roles or missing department get empty list
    echo json_encode($users);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

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
