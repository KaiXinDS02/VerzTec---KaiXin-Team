<?php
// ---------------------------------------------------------------------------
// delete_user.php (charmaine)
// Purpose:
//   Handles POST requests to delete a user record from the database.
//
// Description:
//   - Accepts 'user_id' via POST.
//   - Retrieves the username associated with the user_id for logging purposes.
//   - Deletes the user record from the 'users' table.
//   - Returns 'success' if deletion is successful, 'fail' if not, or 'invalid' for bad requests.
//   - Logs the deletion action if an admin user session is active.
//
// Dependencies:
//   - Database connection via 'connect.php'.
//   - Audit logging function via 'auto_log_function.php'.
// ---------------------------------------------------------------------------

require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // Fetch the username to include in the audit log
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($username);
    $stmt->fetch();
    $stmt->close();

    // Delete the user from the database
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo 'success';

        // Log the deletion if an admin session exists
        if (isset($_SESSION['user_id'])) {
            $adminId = $_SESSION['user_id']; // The admin performing the delete
            $details = "Deleted user '$username' (ID: $userId)";
            log_action($conn, $adminId, 'users', 'delete', $details);
        }
    } else {
        echo 'fail';
    }

    $stmt->close();
    $conn->close();
} else {
    // Request method not POST or user_id not set
    echo 'invalid';
}
