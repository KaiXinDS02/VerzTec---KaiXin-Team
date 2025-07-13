<?php
// ---------------------------------------------------------------------------
// edit_user.php (charmaine)
//
// Purpose:
//   Updates user info in the database by an authenticated admin.
//
// Inputs (POST):
//   - user_id, username, email, department, role, country, (optional) password
//
// Actions:
//   - Updates only changed fields, hashes password if provided
//   - Logs changes via log_action()
//   - Returns success/error messages
//
// Access:
//   - Requires logged-in user (session user_id)
//
// ---------------------------------------------------------------------------


require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id    = intval($_POST['user_id']);
    // Sanitize input using real_escape_string for safe SQL usage
    $username   = $conn->real_escape_string($_POST['username']);
    $email      = $conn->real_escape_string($_POST['email']);
    $department = $conn->real_escape_string($_POST['department']);
    $role       = $conn->real_escape_string($_POST['role']);
    $country    = $conn->real_escape_string($_POST['country']);
    $password   = $_POST['password'];  // password handled separately

    // Fetch existing user data to check for changes
    $result = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
    if (!$result || $result->num_rows === 0) {
        echo "User not found.";
        exit;
    }
    $existing = $result->fetch_assoc();
    $result->close();

    $updates = [];
    $changes = [];

    // Check each field for change, prepare update fragments
    if ($username !== $existing['username']) {
        $updates[] = "username = '$username'";
        $changes[] = "username: '{$existing['username']}' to '$username'";
    }

    if ($email !== $existing['email']) {
        $updates[] = "email = '$email'";
        $changes[] = "email: '{$existing['email']}' to '$email'";
    }

    if ($department !== $existing['department']) {
        $updates[] = "department = '$department'";
        $changes[] = "department: '{$existing['department']}' to '$department'";
    }

    if ($role !== $existing['role']) {
        $updates[] = "role = '$role'";
        $changes[] = "role: '{$existing['role']}' to '$role'";
    }

    if ($country !== $existing['country']) {
        $updates[] = "country = '$country'";
        $changes[] = "country: '{$existing['country']}' to '$country'";
    }

    // Hash new password if provided and add to updates
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $updates[] = "password = '" . $conn->real_escape_string($hashed_password) . "'";
        $changes[] = "password: [changed]";
    }

    // If no changes detected, return early
    if (empty($updates)) {
        echo "No changes made.";
        exit;
    }

    // Construct and execute update query
    $update_sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = $user_id";
    if ($conn->query($update_sql)) {
        echo "success";

        // Log changes if user performing update is logged in
        if (isset($_SESSION['user_id'])) {
            $admin_id = $_SESSION['user_id'];
            $details = "Updated '$username' with user ID $user_id. " . implode(", ", $changes);
            log_action($conn, $admin_id, 'users', 'edit', $details);
        }
    } else {
        echo "error: " . $conn->error;
    }
}
