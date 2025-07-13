<?php
// ---------------------------------------------------------------------------
// edit_file.php (charmaine)
//
// Purpose:
//   Provides a web interface to edit documents with OnlyOffice,
//   handles save callbacks, logs actions, and secures sessions via JWT.
//
// Features:
//   - Serves OnlyOffice editor for supported files (Word, PPT, Excel).
//   - Receives POST save callbacks to update documents.
//   - Uses JWT for secure document sessions.
//   - Logs editor usage and save events.
//
// Dependencies:
//   - Firebase JWT library, database connection, audit logging, OnlyOffice server.
//
// Usage:
//   - GET with file_id to open editor.
//   - POST callback from OnlyOffice to save document.
//
// Supported formats: doc, docx, ppt, pptx, xls, xlsx.
// ---------------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../admin/auto_log_function.php'; 
require_once __DIR__ . '/../connect.php';
use Firebase\JWT\JWT;

$filesFolderPath = '/var/www/html/chatbot/data/pdfs/';
$baseFileUrl = 'http://web:80/chatbot/data/pdfs/';
$onlyOfficeUrl = 'http://localhost:8081/';
$jwtSecret = 'my_jwt_secret';

// Handle POST callback from OnlyOffice to save document changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['callback']) && $_GET['callback'] == '1') {
    $logFile = __DIR__ . '/onlyoffice_callback.log';

    // Validate file_id parameter
    if (!isset($_GET['file_id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing file_id"]);
        exit;
    }

    $file_id = intval($_GET['file_id']);

    // Fetch filename from DB for given file_id
    $stmt = $conn->prepare('SELECT filename FROM files WHERE id = ?');
    $stmt->bind_param('i', $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
        exit;
    }

    $filename = basename($row['filename']);
    $save_path = $filesFolderPath . $filename;

    // Decode JSON payload sent by OnlyOffice
    $data = json_decode(file_get_contents("php://input"), true);

    // Log callback data for debugging
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Callback: " . json_encode($data) . PHP_EOL, FILE_APPEND);

    // Handle OnlyOffice statuses as per their API
    if (isset($data['status'])) {
        switch ($data['status']) {
            case 1: // Editing
                echo json_encode(["error" => 0]);
                exit;

            case 2: // Document is ready to be saved
            case 3: // Document saving
            case 6: // Document saved
            case 7: // Document must be saved
                if (isset($data['url'])) {
                    $fileContents = file_get_contents($data['url']);
                    if ($fileContents !== false) {
                        file_put_contents($save_path, $fileContents);
                        echo json_encode(["error" => 0]);
                        exit;
                    }
                }
                break;

            case 4: // Document saving error
                echo json_encode(["error" => 0]);
                exit;
        }
    }

    // If none of the above, return error
    echo json_encode(["error" => "Unhandled status or failed to save document"]);
    exit;
}

// GET request: Render the OnlyOffice editor page for the given file_id
if (!isset($_GET['file_id'])) {
    die("No file_id specified.");
}

$file_id = intval($_GET['file_id']);

// Fetch filename from DB
$stmt = $conn->prepare('SELECT filename FROM files WHERE id = ?');
$stmt->bind_param('i', $file_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("File not found in database.");
}

$filename = basename($row['filename']);
$file_path = $filesFolderPath . $filename;
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Check if physical file exists on disk
if (!file_exists($file_path)) {
    die("File does not exist on disk.");
}

$fileUrl = $baseFileUrl . rawurlencode($filename);

// Create a unique docKey based on filename and modification time for cache validation
$docKey = md5($filename . filemtime($file_path));

// Log file editing action if user logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $details = "Opened file editor: $filename";
    log_action($conn, $user_id, "files", "edit", $details);
}

// OnlyOffice editor configuration array
$config = [
    'document' => [
        'fileType' => $ext,
        'key' => $docKey,
        'title' => $filename,
        'url' => $fileUrl
    ],
    'documentType' => in_array($ext, ['doc', 'docx']) ? 'word' :
                      (in_array($ext, ['ppt', 'pptx']) ? 'slide' : 'cell'),
    'editorConfig' => [
        'mode' => 'edit',
        'callbackUrl' => 'http://web/admin/edit_file.php?callback=1&file_id=' . $file_id
    ]
];

// Generate JWT token to authenticate document session
$token = JWT::encode($config, $jwtSecret, 'HS256');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit: <?= htmlspecialchars($filename) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        iframe, #onlyoffice-editor { width: 100%; height: 90vh; border: none; }
    </style>
    <?php if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
        <!-- Load OnlyOffice editor API script -->
        <script type="text/javascript" src="http://localhost:8081/web-apps/apps/api/documents/api.js"></script>
    <?php endif; ?>
</head>
<body>

<h2>Edit: <?= htmlspecialchars($filename) ?></h2>

<?php
// Render editor or show unsupported message based on file extension
switch ($ext) {
    case 'doc':
    case 'docx':
    case 'ppt':
    case 'pptx':
    case 'xls':
    case 'xlsx':
        echo '<div id="onlyoffice-editor"></div>';
        ?>
        <script>
            const config = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;
            const token = "<?= $token ?>";
            const docEditor = new DocsAPI.DocEditor("onlyoffice-editor", {
                ...config,
                token: token
            });
        </script>
        <?php
        break;

    default:
        echo '<p>This file type is not editable in OnlyOffice.</p>';
}
?>

</body>
</html>
