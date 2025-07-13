<?php
// ---------------------------------------------------------------------------
// auto_log_function.php (charmaine)
//
// Utility functions for logging user actions to audit_log.
// Includes manual logging (log_action) and automatic page visit logging with rate limiting.
//
// Call auto_log_action($conn) to auto-log page visits.
// ---------------------------------------------------------------------------


// Load Composer dependencies (if needed by other parts of the system)
require __DIR__ . '/../vendor/autoload.php';

// Include database connection
require_once __DIR__ . '/../connect.php'; 

// Optional: Immediately trigger audit log for this page load (mainly for testing)
auto_log_action($conn); 




// ---------------------------------------------------------------------------
// log_action(): Manually log an action to the audit_log table
// ---------------------------------------------------------------------------
function log_action($conn, $user_id, $category, $action, $details = null) {
    if (!$conn) {
        error_log("No DB connection available for logging.");
        return;
    }

    // Prepare insert query
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, category, action, details) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return;
    }

    // Bind parameters and execute
    $stmt->bind_param("isss", $user_id, $category, $action, $details);
    if (!$stmt->execute()) {
        error_log("Execution failed: " . $stmt->error);
    }
    $stmt->close();
}





// ---------------------------------------------------------------------------
// auto_log_action(): Automatically log user page visits for selected pages
// ---------------------------------------------------------------------------
function auto_log_action($conn) {
    // Start session if not already started
    if (!isset($_SESSION)) {
        session_start();
    }

    // Exit if user is not authenticated
    if (!isset($_SESSION['user_id'])) {
        error_log("Session user_id not set. Skipping log.");
        return;
    }

    // Identify current script, IP address, and user agent
    $current_page = basename($_SERVER['PHP_SELF']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $category = 'navigation';  // Category for navigation events

    // Define a whitelist of important pages to log
    $important_pages = [
        'login.php',
        'otp_form.php',
        'home.php',
        'users.php',
        'files.php',
        'audit_log.php',
        'file_preview.php'
    ];

    // If the current page is not on the whitelist, skip logging
    if (!in_array($current_page, $important_pages)) {
        return;
    }

    // Rate-limiting: prevent logging the same page repeatedly in a short time
    $last_log_key = 'last_log_' . md5($current_page);  // Unique session key for each page
    $now = time();

    // If last log was under 60 seconds ago, skip
    if (isset($_SESSION[$last_log_key]) && ($now - $_SESSION[$last_log_key]) < 60) {
        return;
    }

    // Update session timestamp to mark last logging
    $_SESSION[$last_log_key] = $now;

    // Compose log details and call manual logger
    $details = "Page: $current_page from IP $ip";
    log_action($conn, $_SESSION['user_id'], "navigation", "visit", $details);
}
