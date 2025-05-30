<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $context = $_POST['context'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';
    $priority = $_POST['priority'] ?? '';

    // Database connection
    $conn = new mysqli('localhost', 'root', 'Rumaisa112!', 'verztec');

    if ($conn->connect_error) {
        die('Connection Failed: ' . $conn->connect_error);
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, context, target_audience, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $context, $target_audience, $priority);

        if ($stmt->execute()) {
            // Output full HTML with Bootstrap for styling
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Upload Success</title>
                <!-- Bootstrap CSS CDN -->
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

