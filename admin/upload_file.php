<?php
require __DIR__ . '/../connect.php';

session_start();  // Ensure session is started

$user_id   = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';
$user_dept = $_SESSION['department'] ?? null;
$user_country = $_SESSION['country'] ?? null;

$directory = __DIR__ . '/../files';

// Friendly file type mapper
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $uploadFile = $_FILES['upload_file'];

    if ($uploadFile['error'] === UPLOAD_ERR_OK) {
        $originalName = basename($uploadFile['name']);

        if (!is_dir($directory)) mkdir($directory, 0755, true);

        $targetPath = $directory . '/' . $originalName;
        $relativePath = 'files/' . $originalName;

        // Prevent overwrite by renaming
        $pathInfo = pathinfo($originalName);
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $counter = 1;
        $targetPath = $directory . '/' . $originalName;
        $relativePath = 'files/' . $originalName;

        while (file_exists($targetPath)) {
            $newName = $filename . "($counter)." . $extension;
            $targetPath = $directory . '/' . $newName;
            $relativePath = 'files/' . $newName;
            $originalName = $newName;
            $counter++;
        }


        if (move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
            $fileSizeKb = round(filesize($targetPath) / 1024);
            $mimeType = mime_content_type($targetPath);
            $fileType = getFriendlyFileType($mimeType);

            $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);

            if ($stmt->execute()) {
                $file_id = $stmt->insert_id;

                // --- Visibility Handling ---
                if ($user_role === 'ADMIN') {
                    $visibility = $_POST['visibility'] ?? 'all';

                    if ($visibility === 'all') {
                        $conn->query("INSERT INTO file_visibility (file_id, visibility_scope) VALUES ($file_id, 'ALL')");
                    } else {
                        // Handle DEPARTMENT restrictions
                        if (!empty($_POST['departments'])) {
                            $deptStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
                            foreach ($_POST['departments'] as $dept) {
                                $dept = trim($dept);
                                if ($dept !== '') {
                                    $deptStmt->bind_param("is", $file_id, $dept);
                                    $deptStmt->execute();
                                }
                            }
                            $deptStmt->close();
                        }

                        // Handle COUNTRY restrictions
                        if (!empty($_POST['countries'])) {
                            $countryStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
                            foreach ($_POST['countries'] as $country) {
                                $country = trim($country);
                                if ($country !== '') {
                                    $countryStmt->bind_param("is", $file_id, $country);
                                    $countryStmt->execute();
                                }
                            }
                            $countryStmt->close();
                        }
                    }
                }

                elseif ($user_role === 'MANAGER') {
                    $managerVis = $_POST['manager_visibility'] ?? 'department';

                    if ($managerVis === 'department' && $user_dept) {
                        $mgrDeptStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'DEPARTMENT', ?)");
                        $mgrDeptStmt->bind_param("is", $file_id, $user_dept);
                        $mgrDeptStmt->execute();
                        $mgrDeptStmt->close();
                    }

                    if ($managerVis === 'country' && $user_country) {
                        $mgrCountryStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope, category) VALUES (?, 'COUNTRY', ?)");
                        $mgrCountryStmt->bind_param("is", $file_id, $user_country);
                        $mgrCountryStmt->execute();
                        $mgrCountryStmt->close();
                    }
                }


                echo "Upload successful.";
            } else {
                echo "Database error: " . $stmt->error;
            }

            $stmt->close();
        } else {
            echo "Failed to move uploaded file.";
        }
    } else {
        echo "Upload error: " . $uploadFile['error'];
    }
    // Success message
    if (!headers_sent()) {
        header("Location: ../files.php");
        exit();
    } else {
        echo "<script>window.location.href = '../files.php';</script>";
        exit();
    }

}
?>
