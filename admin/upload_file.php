<?php
require __DIR__ . '/../connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$directory = __DIR__ . '/../files';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $uploadFile = $_FILES['upload_file'];

    if ($uploadFile['error'] === UPLOAD_ERR_OK) {
        $originalName = basename($uploadFile['name']);
        if (!is_dir($directory)) mkdir($directory, 0755, true);

        $targetPath = $directory . '/' . $originalName;
        if (move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
            $fileSizeKb = round(filesize($targetPath) / 1024);
            $fileType = mime_content_type($targetPath);
            $relativePath = 'files/' . $originalName;

            $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);
            if ($stmt->execute()) {
                $file_id = $stmt->insert_id;

                // Handle file_visibility
                $visibility = $_POST['visibility'] ?? 'all';
                if ($visibility === 'all') {
                    $conn->query("INSERT INTO file_visibility (file_id, visibility_scope) VALUES ($file_id, 'ALL')");
                } else {
                    if (!empty($_POST['departments'])) {
                        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
                        foreach ($_POST['departments'] as $dept) {
                            $stmt->bind_param("is", $file_id, $dept);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    if (!empty($_POST['countries'])) {
                        $stmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
                        foreach ($_POST['countries'] as $country) {
                            $stmt->bind_param("is", $file_id, $country);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }
                }

                echo "Upload successful";
            } else {
                echo "DB error: " . $stmt->error;
            }
        } else {
            echo "Failed to move uploaded file.";
        }
    } else {
        echo "Upload error: " . $uploadFile['error'];
    }
}
?>
