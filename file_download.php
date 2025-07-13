<?php
// ---------------------------------------------------------------------------
// download_file.php (Charmaine)
//
// Secure file download handler for authenticated users.
// Validates session, retrieves file info from DB, streams file with correct headers, and logs download actions.
// ---------------------------------------------------------------------------

session_start();
require_once __DIR__ . '/connect.php';             // Connect to database
include 'admin/auto_log_function.php';             // Include action logger

// Ensure the user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    exit('Unauthorized access.');
}

// Handle GET request and check if file_id is provided
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['file_id'])) {
    $file_id = intval($_GET['file_id']);           // Sanitize file ID from input
    $user_id = $_SESSION['user_id'];               // Get user ID from session

    // Retrieve file metadata from the database
    $stmt = $conn->prepare("SELECT filename, file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->bind_result($filename, $file_path);

    if ($stmt->fetch()) {
        $stmt->close();

        // Check that file_path is present in the DB
        if (!$file_path) {
            http_response_code(404);               // Not Found
            exit('File path missing in database.');
        }

        // Build absolute file path
        $fullPath = realpath(__DIR__ . '/' . $file_path);

        // Check that file exists and is accessible
        if ($fullPath && file_exists($fullPath) && is_readable($fullPath)) {
            // Get MIME type dynamically
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fullPath);
            finfo_close($finfo);

            // Set headers to prompt browser download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($fullPath));

            // Clear any previous output to prevent corruption
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Stream the file contents to the browser
            readfile($fullPath);

            // Log this download action
            log_action($conn, $user_id, 'files', 'download', "Downloaded: $filename");

            exit; // Terminate script after file output
        } else {
            http_response_code(404);               // Not Found
            exit('File not found on server.');
        }
    } else {
        $stmt->close();
        http_response_code(404);                   // Not Found
        exit('File not found in database.');
    }
} else {
    http_response_code(400);                       // Bad Request
    exit('Invalid request.');
}
