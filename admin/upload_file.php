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
    return $map[$mimeType] ?? 'other';
}

// Main handler: process POST request with file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_FILES['upload_files'])
    && !empty($_FILES['upload_files']['name'][0])) {

    $f = $_FILES['upload_files'];
    $fileCount = count($f['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;

        $originalName = basename($f['name'][$i]);

        if (!is_dir($directory)) mkdir($directory, 0755, true);

        $pathInfo  = pathinfo($originalName);
        $filename  = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $counter = 1;
        $targetPath   = "$directory/$originalName";
        $relativePath = "chatbot/data/pdfs/$originalName";

        while (file_exists($targetPath)) {
            $originalName = $filename . "($counter)." . $extension;
            $targetPath   = "$directory/$originalName";
            $relativePath = "chatbot/data/pdfs/$originalName";
            $counter++;
        }

        if (!move_uploaded_file($f['tmp_name'][$i], $targetPath)) continue;

        // Run Python cleaning script
        $escaped_filename = escapeshellarg($originalName);
        $output = [];
        exec("cd /var/www/html/chatbot && python3 chatbot/data_cleaning.py $escaped_filename 2>&1", $output, $return_code);
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
        error_log("data_cleaning exit code: $return_code");

        // Wait for cleaned file to appear
        $baseNoExt = pathinfo($originalName, PATHINFO_FILENAME);
        $cleaned_txt = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseNoExt . '.txt';
        $cleaned_docx = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseNoExt . '.docx';
        $wait_time = 0;
        while (!file_exists($cleaned_txt) && !file_exists($cleaned_docx) && $wait_time < 100) {
            usleep(200000);
            $wait_time += 0.2;
        }

        $fileSizeKb = round(filesize($targetPath) / 1024);
        $mimeType   = mime_content_type($targetPath);
        $fileType   = getFriendlyFileType($mimeType);

        $stmt = $conn->prepare(
            "INSERT INTO files (user_id, filename, file_path, file_type, file_size)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi", $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);

        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }

        $file_id = $stmt->insert_id;
        $stmt->close();

        // Visibility for ADMIN
        if ($user_role === 'ADMIN') {
            $visibility = $_POST['visibility'] ?? 'all';

            if ($visibility === 'all') {
                // All access: country = ALL, department = ALL
                $conn->query("INSERT INTO file_visibility (file_id, country, department) VALUES ($file_id, 'ALL', 'ALL')");
            } else {
                // If country-wide checkbox is ticked (Singapore + ALL)
                if (!empty($_POST['countries'])) {
                    $cStmt = $conn->prepare("INSERT INTO file_visibility (file_id, country, department) VALUES (?, ?, 'ALL')");
                    foreach ($_POST['countries'] as $country) {
                        $country = trim($country);
                        if ($country !== '') {
                            $cStmt->bind_param("is", $file_id, $country);
                            $cStmt->execute();
                        }
                    }
                    $cStmt->close();
                }

                // If department checkboxes are ticked (Singapore + HR, Singapore + Finance, etc.)
                if (!empty($_POST['matrix_selection'])) {
                    $dStmt = $conn->prepare("INSERT INTO file_visibility (file_id, country, department) VALUES (?, ?, ?)");
                    foreach ($_POST['matrix_selection'] as $country => $departments) {
                        $country = trim($country);
                        foreach ($departments as $dept) {
                            $dept = trim($dept);
                            if ($country !== '' && $dept !== '') {
                                $dStmt->bind_param("iss", $file_id, $country, $dept);
                                $dStmt->execute();
                            }
                        }
                    }
                    $dStmt->close();
                }
            }
        }

        // Visibility for MANAGER
        elseif ($user_role === 'MANAGER') {
            $mgrVis = $_POST['manager_visibility'] ?? 'department';
            $mStmt = $conn->prepare("INSERT INTO file_visibility (file_id, country, department) VALUES (?, ?, ?)");
            if ($mgrVis === 'country' && $user_country) {
                // Manager shares to whole country: Singapore + ALL
                $mStmt->bind_param("iss", $file_id, $user_country, $null = 'ALL');
                $mStmt->execute();
            } elseif ($mgrVis === 'department' && $user_country && $user_dept) {
                // Manager shares to own department: Singapore + HR
                $mStmt->bind_param("iss", $file_id, $user_country, $user_dept);
                $mStmt->execute();
            }
            $mStmt->close();
        }

        // Run ingestion script
        $output = [];
        exec("cd /var/www/html/chatbot && python3 chatbot/ingest_single.py $escaped_filename 2>&1", $output, $return_code);
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', implode("\n", $output), FILE_APPEND);
        file_put_contents('/var/www/html/logs/ingest_single_debug.log', "ingest_single exit code: $return_code\n", FILE_APPEND);

        // Reload chatbot vectorstore
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

        // Log upload
        log_action($conn, $user_id, 'files', 'add',
            "Uploaded file: $originalName ($fileType, $fileSizeKb KB).");
    }

    header("Location: ../files.php");
    exit;
}
?>