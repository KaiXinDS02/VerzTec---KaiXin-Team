<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
include 'auto_log_function.php';
include __DIR__ . '/../connect.php'; // ← uses Docker-style: host=db, user=user, password=password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $context = $_POST['context'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';
    $priority = $_POST['priority'] ?? '';

    if ($conn->connect_error) {
        die('Connection Failed: ' . $conn->connect_error);
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, context, target_audience, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $context, $target_audience, $priority);

        if ($stmt->execute()) {
            // Optional: Log the announcement upload
            if (isset($_SESSION['user_id'])) {
                $adminId = $_SESSION['user_id'];
                $details = "Created announcement titled '$title' for '$target_audience'.";
                log_action($conn, $adminId, 'announcements', 'add', $details);
            }

            // Success page with redirect
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Upload Success</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <meta http-equiv="refresh" content="3;url=index.html" />
                <style>
                    body, html {
                        height: 100%;
                        margin: 0;
                    }
                    .center-message {
                        height: 100vh;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        background-color: #f8f9fa;
                    }
                </style>
            </head>
            <body>
                <div class="center-message">
                    <button class="btn btn-warning btn-lg" disabled>
                        Uploaded successfully! Redirecting...
                    </button>
                </div>
            </body>
            </html>
            <?php
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }
} else {
    echo "This script should be accessed through a web form (POST method).";
}
?>

