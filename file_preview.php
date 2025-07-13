<?php
// ---------------------------------------------------------------------------
// preview_file.php (Charmaine)
//
// Secure file preview page supporting PDFs, images, text, and Office files.
// Loads file by file_id, shows with appropriate viewer, uses JWT for OnlyOffice.
// Requires user login; logs preview action.
// ---------------------------------------------------------------------------

session_start();
include 'connect.php'; // DB connection ($conn)
require 'vendor/autoload.php'; // Load JWT library via Composer
require_once 'admin/auto_log_function.php'; // Logging function

use Firebase\JWT\JWT;

$filesFolderPath = '/var/www/html/chatbot/data/pdfs/';
$baseFileUrl     = 'http://host.docker.internal:8080/chatbot/data/pdfs/';
$onlyOfficeUrl   = 'http://localhost:8081/';
$jwtSecret       = 'my_jwt_secret';

// Check that file_id is provided via GET
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

$filename = basename($row['filename']); // Sanitize filename
$file_path = $filesFolderPath . $filename;
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); // Get file extension

if (!file_exists($file_path)) {
    die("File does not exist on disk.");
}

// Log the preview action if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $details = "Previewed file: $filename";
    log_action($conn, $user_id, "files", "open", $details);
}

// Build preview URL and document key
$fileUrl = $baseFileUrl . rawurlencode($filename);
$docKey  = md5($filename . filemtime($file_path)); // Unique per file version

// Build config for OnlyOffice (Word/Excel/PPT viewers)
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
        'mode' => 'view'
    ]
];

// Sign the OnlyOffice config using JWT
$token = JWT::encode($config, $jwtSecret, 'HS256');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Preview: <?= htmlspecialchars($filename) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        iframe, #onlyoffice-editor { width: 100%; height: 90vh; border: none; }
        img { max-width: 100%; height: auto; }
        pre { background: #eee; padding: 10px; white-space: pre-wrap; }
    </style>

    <?php if (in_array($ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
        <!-- Load OnlyOffice JS API only for Office documents -->
        <script type="text/javascript" src="<?= $onlyOfficeUrl ?>web-apps/apps/api/documents/api.js"></script>
    <?php endif; ?>
</head>
<body>

<h2>Preview: <?= htmlspecialchars($filename) ?></h2>

<?php
switch ($ext) {
    case 'pdf':
        // Inline preview for PDFs
        echo '<iframe src="' . htmlspecialchars($fileUrl) . '"></iframe>';
        break;

    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':
        // Inline preview for image files
        echo '<img src="' . htmlspecialchars($fileUrl) . '" alt="Image preview">';
        break;

    case 'txt':
    case 'csv':
    case 'log':
        // Inline preview for plaintext files
        echo '<pre>' . htmlspecialchars(file_get_contents($file_path)) . '</pre>';
        break;

    case 'doc':
    case 'docx':
    case 'ppt':
    case 'pptx':
    case 'xls':
    case 'xlsx':
        // Interactive OnlyOffice preview for Office files
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
        // If no viewer is available for the file type
        echo '<p>Preview not available for this file type.</p>';
}
?>

</body>
</html>
