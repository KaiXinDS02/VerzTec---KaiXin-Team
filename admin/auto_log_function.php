<?php
// Ensure database connection and session are started early
require_once __DIR__ . '/../connect.php'; // This must define $conn

// Call audit directly — replace this with register_shutdown_function once it works
auto_log_action(); // ✅ TEMPORARY for debugging

function log_action($conn, $user_id, $action, $details = null) {
    if (!$conn) {
        error_log("No DB connection available for logging.");
        return;
    }

    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return;
    }

    $stmt->bind_param("iss", $user_id, $action, $details);
    if (!$stmt->execute()) {
        error_log("Execution failed: " . $stmt->error);
    }
    $stmt->close();
}

function auto_log_action() {
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        error_log("Session user_id not set. Skipping log.");
        return;
    }

    // Safer way to match pages regardless of folder structure
    $current_page = basename($_SERVER['PHP_SELF']); // ✅ Fix: use just the filename
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $important_pages = [
        'login.php',
        'otp_form.php',
        'home.php',
        'users.php'
    ];

    if (!in_array($current_page, $important_pages)) {
        error_log("Page $current_page not in list of important pages.");
        return;
    }

    $last_log_key = 'last_log_' . md5($current_page);
    $now = time();
    if (isset($_SESSION[$last_log_key]) && ($now - $_SESSION[$last_log_key]) < 60) {
        error_log("Recent log already recorded for $current_page. Skipping.");
        return;
    }

    $_SESSION[$last_log_key] = $now;

    $details = "Page: $current_page from IP $ip | Agent: $user_agent";
    log_action($conn, $_SESSION['user_id'], "visited_page", $details);
}
?>
