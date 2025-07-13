<?php
// ---------------------------------------------------------------------------
// upload_files.php (charmaine)
//
// Purpose:
//   Handles multiple file uploads, saving files, running cleaning/ingestion scripts,
//   updating DB, setting visibility, and reloading vector store.
//
// Inputs (POST):
//   - upload_files[] (files), visibility, departments[], countries[], manager_visibility
//
// Features:
//   - Renames duplicates, runs Python scripts for cleaning/ingestion
//   - Sets visibility based on user role (ADMIN/MANAGER)
//   - Logs actions and reloads chatbot vector store
//
// Access:
//   - Uses session user info for access and visibility controls
//
// Redirect:
//   - Redirects to files.php after processing
//
// ---------------------------------------------------------------------------


require __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php';

// Get user info from session for permission checks and visibility assignment
$user_id      = $_SESSION['user_id']   ?? null;
$user_role    = $_SESSION['role']      ?? '';
$user_dept    = $_SESSION['department']?? null;
$user_country = $_SESSION['country']   ?? null;

// Directory where uploaded PDF files will be stored
$directory = __DIR__ . '/../chatbot/data/pdfs';

// Map MIME types to simplified file types for easier classification and DB storage
function getFriendlyFileType($mimeType) {
    $map = [
        'application/pdf' => 'pdf',
        'application/msword' => 'msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'msword',
        'application/vnd.ms-excel' => 'excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
        'application/vnd.ms-powerpoint' => 'powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'powerpoint',
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'text/plain' => 'text',
        'application/zip' => 'zip',
        'application/x-rar-compressed' => 'rar',
    ];
    // Return mapped type or 'other' if MIME type unknown
    return $map[$mimeType] ?? 'other';
}

// Main handler: process POST request with file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['upload_files'])
    && !empty($_FILES['upload_files']['name'][0])) {

    $f = $_FILES['upload_files'];
    $fileCount = count($f['name']); // Number of files uploaded

    for ($i = 0; $i < $fileCount; $i++) {

        // Skip files with upload errors (e.g. file too large, partial upload)
        if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;

        $originalName = basename($f['name'][$i]);

        // Ensure target directory exists, create if missing
        if (!is_dir($directory)) mkdir($directory, 0755, true);

        // Extract filename and extension separately
        $pathInfo  = pathinfo($originalName);
        $filename  = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        // Prevent overwriting existing files by adding numeric suffix if needed
        $counter = 1;
        $targetPath   = "$directory/$originalName";
        $relativePath = "chatbot/data/pdfs/$originalName";

        while (file_exists($targetPath)) {
            $originalName = $filename . "($counter)." . $extension;
            $targetPath   = "$directory/$originalName";
            $relativePath = "chatbot/data/pdfs/$originalName";
            $counter++;
        }

        // Move the uploaded file from temporary location to target directory
        if (!move_uploaded_file($f['tmp_name'][$i], $targetPath)) continue;

        // Run the external Python cleaning script on the newly uploaded file
        $escaped_filename = escapeshellarg($originalName);
        $output = [];
        exec("cd /var/www/html/chatbot && python3 chatbot/data_cleaning.py $escaped_filename 2>&1", $output, $return_code);
        // Append output and exit code to debug log for troubleshooting
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
        error_log("data_cleaning exit code: $return_code");

        // Wait up to 10 seconds (poll every 0.2s) for cleaned output file to appear
        $baseNoExt = pathinfo($originalName, PATHINFO_FILENAME);
        $cleaned_txt = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseNoExt . '.txt';
        $cleaned_docx = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseNoExt . '.docx';
        $wait_time = 0;
        while (!file_exists($cleaned_txt) && !file_exists($cleaned_docx) && $wait_time < 100) {
            usleep(200000); // Sleep for 0.2 seconds
            $wait_time += 0.2;
        }

        // Get file size in KB and MIME type for database record
        $fileSizeKb = round(filesize($targetPath) / 1024);
        $mimeType   = mime_content_type($targetPath);
        $fileType   = getFriendlyFileType($mimeType);

        // Prepare and execute SQL to insert file metadata into 'files' table
        $stmt = $conn->prepare(
            "INSERT INTO files (user_id, filename, file_path, file_type, file_size)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi", $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);

        if (!$stmt->execute()) {
            // If insertion fails, close statement and skip to next file
            $stmt->close();
            continue;
        }

        $file_id = $stmt->insert_id; // Get auto-incremented file ID
        $stmt->close();

        // Handle visibility settings for ADMIN users
        if ($user_role === 'ADMIN') {
            $visibility = $_POST['visibility'] ?? 'all';

            if ($visibility === 'all') {
                // If visibility is 'all', insert a single 'ALL' scope record
                $conn->query("INSERT INTO file_visibility (file_id, visibility_scope) VALUES ($file_id, 'ALL')");
            } else {
                // Insert department visibility restrictions if specified
                if (!empty($_POST['departments'])) {
                    $dStmt = $conn->prepare(
                        "INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)"
                    );
                    foreach ($_POST['departments'] as $d) {
                        $d = trim($d);
                        if ($d !== '') {
                            $dStmt->bind_param("is", $file_id, $d);
                            $dStmt->execute();
                        }
                    }
                    $dStmt->close();
                }
                // Insert country visibility restrictions if specified
                if (!empty($_POST['countries'])) {
                    $cStmt = $conn->prepare(
                        "INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)"
                    );
                    foreach ($_POST['countries'] as $c) {
                        $c = trim($c);
                        if ($c !== '') {
                            $cStmt->bind_param("is", $file_id, $c);
                            $cStmt->execute();
                        }
                    }
                    $cStmt->close();
                }
            }
        }
        // Handle visibility for MANAGER users
        elseif ($user_role === 'MANAGER') {
            $mgrVis = $_POST['manager_visibility'] ?? 'department';

            if ($mgrVis === 'department' && $user_dept) {
                // Insert visibility scoped to user's department only
                $mStmt = $conn->prepare(
                    "INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)"
                );
                $mStmt->bind_param("is", $file_id, $user_dept);
                $mStmt->execute();
                $mStmt->close();
            }

            if ($mgrVis === 'country' && $user_country) {
                // Insert visibility scoped to user's country only
                $mStmt = $conn->prepare(
                    "INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)"
                );
                $mStmt->bind_param("is", $file_id, $user_country);
                $mStmt->execute();
                $mStmt->close();
            }
        }

        // After DB updates, run ingestion script to update chatbot vector data
        $output = [];
        exec("cd /var/www/html/chatbot && python3 chatbot/ingest_single.py $escaped_filename 2>&1", $output, $return_code);
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single exit code: $return_code\n", FILE_APPEND);

        // Trigger chatbot backend vector store reload via HTTP POST request
        $reload_url = 'http://host.docker.internal:8000/reload_vectorstore';
        $ch = curl_init($reload_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $reload_response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $reload_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        error_log("Reload vectors POST to $reload_url");
        error_log("Reload vectors cURL error: $curl_error");
        error_log("Reload vectors response: $reload_response (HTTP $reload_http_code)");

        // Log the successful file upload action for audit trail
        log_action($conn, $user_id, 'files', 'add',
                   "Uploaded file: $originalName ($fileType, $fileSizeKb KB).");
    }

    // Redirect user back to file listing page after all uploads processed
    header("Location: ../files.php");
    exit;
}
?>
