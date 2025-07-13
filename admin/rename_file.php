<?php
// ---------------------------------------------------------------------------
// rename_file.php (charmaine)
//
// Purpose:
//   Renames a file both on disk and in the database for an authenticated user.
//
// Inputs:
//   - POST: file_id, new_filename
//
// Access:
//   - Requires logged-in user (session user_id)
//
// Actions:
//   - Updates filename and path in DB, renames physical file
//   - Logs the rename action
//   - Redirects to files.php
//
// ---------------------------------------------------------------------------

session_start();
require_once __DIR__ . '/../connect.php';
include 'auto_log_function.php';

// Verify user session and required POST parameters
if (!isset($_SESSION['user_id']) || !isset($_POST['file_id'], $_POST['new_filename'])) {
    header("Location: ../files.php");
    exit;
}

$file_id = intval($_POST['file_id']);
$new_filename = trim($_POST['new_filename']);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

// Prevent empty new filename
if ($new_filename === '') {
    header("Location: ../files.php");
    exit;
}

// Retrieve old file path from database
$stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$stmt->bind_result($old_path);
$stmt->fetch();
$stmt->close();

// If no file path found, redirect
if (!$old_path) {
    header("Location: ../files.php");
    exit;
}

// Build full absolute paths for old and new files
$old_full_path = realpath(__DIR__ . '/../' . $old_path);
$directory = dirname($old_path);
$new_relative_path = $directory . '/' . $new_filename;
$new_full_path = realpath(__DIR__ . '/../' . $directory) . '/' . $new_filename;

// Attempt to rename the physical file on disk
if (!rename($old_full_path, $new_full_path)) {
    header("Location: ../files.php");
    exit;
}

// Update database record with new filename and path, update uploaded_at timestamp
$stmt = $conn->prepare("UPDATE files SET filename = ?, file_path = ?, uploaded_at = NOW() WHERE id = ?");
$stmt->bind_param("ssi", $new_filename, $new_relative_path, $file_id);

if ($stmt->execute()) {
    // Log the rename action
    log_action($conn, $user_id, 'files', 'edit', "Renamed file ID $file_id to '$new_filename'");
}

$stmt->close();
$conn->close();

// Redirect back to files listing page
header("Location: ../files.php");
exit;
