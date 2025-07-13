<?php
// ---------------------------------------------------------------------------
// delete_file.php (charmaine)
// Purpose:
//   Deletes a file record and associated physical files, updates vector store.
//
// Description:
//   - Accepts POST 'file_id'.
//   - Removes file and related cleaned data (.txt, .docx).
//   - Deletes DB record and logs action.
//   - Runs Python script to purge vector embeddings.
//   - Calls backend to reload vector store.
//
// Dependencies:
//   - connect.php, auto_log_function.php, purge_vectors.py
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../connect.php';
require __DIR__ . '/../admin/auto_log_function.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_id'])) {
    $file_id = intval($_POST['file_id']);

    // Get the file path from the database
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->bind_result($file_path);

    if ($stmt->fetch()) {
        $stmt->close();

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path;

        // Delete the physical file if it exists
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Delete corresponding cleaned files (.txt and .docx) from chatbot data folder
        $baseName = pathinfo($file_path, PATHINFO_FILENAME); // filename without extension
        $cleanedTxtPath = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseName . '.txt';
        $cleanedDocxPath = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/data/cleaned/' . $baseName . '.docx';

        if (file_exists($cleanedTxtPath)) {
            unlink($cleanedTxtPath);
        }
        if (file_exists($cleanedDocxPath)) {
            unlink($cleanedDocxPath);
        }

        // Delete the database record for the file
        $del = $conn->prepare("DELETE FROM files WHERE id = ?");
        $del->bind_param("i", $file_id);
        if ($del->execute()) {
            // Log deletion action if user session exists
            if (isset($_SESSION['user_id'])) {
                $filename = basename($file_path);
                $details = "Deleted file: $filename";
                log_action($conn, $_SESSION['user_id'], 'files', 'delete', $details);
            }
            
            // Purge vectors associated with the deleted file by running Python script
            $delete_filename = escapeshellarg($baseName);  // sanitize filename without extension
            exec("cd /var/www/html/chatbot && python3 chatbot/purge_vectors.py $delete_filename 2>&1", $output, $return_code);
            
            // Trigger vectorstore reload via backend API to reflect deletion
            $reload_url = 'http://host.docker.internal:8000/reload_vectorstore';
            $ch = curl_init($reload_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $reload_response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $reload_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo 'success';
        } else {
            echo 'Database delete failed.';
        }
        $del->close();

    } else {
        echo 'File not found in database.';
    }
} else {
    echo 'Invalid request.';
}
