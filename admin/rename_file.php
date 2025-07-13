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

// Also rename the raw file in /chatbot/data/pdfs if it exists
$pdfs_dir = '/var/www/html/chatbot/data/pdfs/';
$old_base = pathinfo($old_path, PATHINFO_FILENAME);
$new_base = pathinfo($new_filename, PATHINFO_FILENAME);

$possible_exts = ['pdf', 'docx', 'doc', 'txt', 'xlsx'];
foreach ($possible_exts as $ext) {
    $old_raw = $pdfs_dir . $old_base . '.' . $ext;
    $new_raw = $pdfs_dir . $new_base . '.' . $ext;
    if (file_exists($old_raw)) {
        rename($old_raw, $new_raw);
    }
}

// Also rename cleaned .txt and .docx files in /chatbot/data/cleaned if they exist
$cleaned_dir = '/var/www/html/chatbot/data/cleaned/';
$old_cleaned_txt = $cleaned_dir . $old_base . '.txt';
$new_cleaned_txt = $cleaned_dir . $new_base . '.txt';
if (file_exists($old_cleaned_txt)) {
    rename($old_cleaned_txt, $new_cleaned_txt);
}
$old_cleaned_docx = $cleaned_dir . $old_base . '.docx';
$new_cleaned_docx = $cleaned_dir . $new_base . '.docx';
if (file_exists($old_cleaned_docx)) {
    rename($old_cleaned_docx, $new_cleaned_docx);
}

// Update database record with new filename and path, update uploaded_at timestamp
$stmt = $conn->prepare("UPDATE files SET filename = ?, file_path = ?, uploaded_at = NOW() WHERE id = ?");
$stmt->bind_param("ssi", $new_filename, $new_relative_path, $file_id);

if ($stmt->execute()) {
    // Log the rename action
    log_action($conn, $user_id, 'files', 'edit', "Renamed file ID $file_id to '$new_filename'");

    // Purge old vectors using the old base filename (without extension)
    $old_base = pathinfo($old_path, PATHINFO_FILENAME);
    $escaped_old_base = escapeshellarg($old_base);
    exec("cd /var/www/html/chatbot && python3 chatbot/purge_vectors.py $escaped_old_base 2>&1", $output, $return_code);
    file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
    file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single (edit_visibility) exit code: $return_code\n", FILE_APPEND);
    
    // Ingest new vectors using the new base filename (without extension)
    $new_base = pathinfo($new_filename, PATHINFO_FILENAME);
    $escaped_new_base = escapeshellarg($new_base);
    exec("cd /var/www/html/chatbot && python3 chatbot/ingest_single.py $escaped_new_base 2>&1", $output, $return_code);
    file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
    file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single (edit_visibility) exit code: $return_code\n", FILE_APPEND);

    // Reload the vectorstore in the backend
    $reload_url = 'http://host.docker.internal:8000/reload_vectorstore';
    $ch = curl_init($reload_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $reload_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $reload_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

$stmt->close();
$conn->close();

// Redirect back to files listing page
header("Location: ../files.php");
exit;
