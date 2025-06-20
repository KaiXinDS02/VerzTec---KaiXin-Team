<?php
session_start();
include('connect.php'); 
include 'admin/auto_log_function.php';
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

$message = "";
$directory = 'files';

// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }
$user_id = $_SESSION['user_id'];

// Fetch username
$username = 'Unknown';
$stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

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

// File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $uploadFile = $_FILES['upload_file'];

    if ($uploadFile['error'] === UPLOAD_ERR_OK) {
        $originalName = basename($uploadFile['name']);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/ \(\d+\)$/', '', $base);

        $counter = 0;
        do {
            $newName = $counter === 0 ? "{$base}.{$ext}" : "{$base} ({$counter}).{$ext}";
            $targetPath = $directory . '/' . $newName;
            $counter++;
        } while (file_exists($targetPath));

        $originalName = $newName;

        if (move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
            $mimeType = mime_content_type($targetPath);
            $fileType = getFriendlyFileType($mimeType);
            $fileSizeKb = round(filesize($targetPath) / 1024);
            $relativePath = $directory . '/' . $originalName;

            $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $originalName, $relativePath, $fileType, $fileSizeKb);

            if ($stmt->execute()) {
                $message = "File uploaded successfully.";
                log_action($conn, $user_id, 'files', 'add', "Uploaded file: $originalName of size $fileSizeKb KB.");
            } else {
                $message = "Database insert failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Failed to move uploaded file.";
        }
    } else {
        $message = "Upload error code: " . $uploadFile['error'];
    }
}

// Scan /files and insert if not in DB
$inserted = 0;
foreach (scandir($directory) as $file) {
    if ($file === '.' || $file === '..') continue;

    $filePath = realpath("$directory/$file");
    $mimeType = mime_content_type($filePath);
    $fileType = getFriendlyFileType($mimeType);
    $fileSize = round(filesize($filePath) / 1024);
    $relativePath = $directory . '/' . $file;

    $check = $conn->prepare("SELECT id FROM files WHERE file_path = ?");
    $check->bind_param("s", $relativePath);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $user_id, $file, $relativePath, $fileType, $fileSize);
        if ($stmt->execute()) {
            $inserted++;
        }
        $stmt->close();
    }
    $check->close();
}

// Fetch all file records
$fileRecords = [];
$sql = "
  SELECT 
    f.id,
    f.uploaded_at,
    f.filename,
    f.file_type,
    f.file_size,
    u.username
  FROM files AS f
  LEFT JOIN users AS u ON f.user_id = u.user_id
  ORDER BY f.uploaded_at DESC
";
$result = $conn->query($sql);
if ($result && $result->num_rows) {
    while ($row = $result->fetch_assoc()) {
        $fileRecords[] = $row;
    }
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Verztec Admin – File Records</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    html, body { height:100%; margin:0; }
    body {
      background: #f2f3fa;
      padding-top: 160px;
      padding-bottom: 160px;
    }
    .search-box {
      position: relative;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      width: 250px;
    }
    .search-box input {
      border: none;
      padding: .375rem .75rem .375rem 2.5rem;
      width: 100%;
      border-radius: 8px;
    }
    .search-box i {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    .filter-dropdown .dropdown-toggle {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      color: #333;
    }
    .filter-dropdown .dropdown-toggle::after {
      margin-left: .5em;
      border-top: .3em solid #333;
      border-right: .3em solid transparent;
      border-left: .3em solid transparent;
    }
    .filter-dropdown .dropdown-toggle:hover {
      background: #000;
      color: #fff;
      border-color: #000;
    }
    .filter-dropdown .dropdown-toggle:hover::after {
      border-top-color: #fff;
    }
    .table-container {
      background: #fff;
      border-radius: 8px;
      overflow: auto;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .table-container table thead th {
      background: #212529;
      color: #fff;
    }
    .table-container table thead th:first-child {
      border-top-left-radius: 8px;
    }
    .table-container table thead th:last-child {
      border-top-right-radius: 8px;
    }

    /* Layout fix for controls */
    .controls-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
  </style>
</head>
<body>

  <header class="header-area" style="position:fixed;top:0;left:0;width:100%;z-index:999;background:white;">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp">
            <img src="images/logo.png" alt="Verztec">
          </a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li><a href="chatbot.html">Chatbot</a></li>
              <li class="active"><a href="#">Files</a></li>
              <li><a href="admin/users.php">Admin</a></li>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile"></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-message-smile"></i> Inbox</a></li>
                <li><a href="#"><i class="fa-regular fa-gear"></i> Settings</a></li>
                <li><a href="#"><i class="fa-regular fa-square-question"></i> Help</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="container-fluid">
    <div class="row">
      <div class="col-md-10 d-flex flex-column px-4" style="height:calc(100vh - 320px);">

        <!-- Controls -->
        <div class="controls-row">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" placeholder="Search file">
          </div>

          <div class="d-flex align-items-center gap-2">
            <div class="dropdown filter-dropdown">
              <button 
                class="btn dropdown-toggle" 
                id="typeFilterBtn" 
                data-bs-toggle="dropdown" 
                aria-expanded="false"
              >
                File Type: All
              </button>
              <div 
                class="dropdown-menu p-3" 
                id="typeFilterMenu"
                aria-labelledby="typeFilterBtn"
                style="max-height:300px; overflow-y:auto;"
              >
                <!-- dynamically filled -->
              </div>
            </div>

            <button class="btn btn-dark d-flex align-items-center" onclick="document.getElementById('upload_file').click();">
              <i class="fa fa-upload me-2"></i> Upload File
            </button>
            <form method="POST" enctype="multipart/form-data" style="display:none;">
              <input type="file" name="upload_file" id="upload_file" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf,.csv,.txt" onchange="this.form.submit()">
            </form>
          </div>
        </div>

        <!-- Table -->
        <div class="table-container">
          <table id="file-table" class="table table-hover mb-0 w-100">
            <thead class="table-dark">
              <tr>
                <th>Filename</th>
                <th>Uploaded At</th>
                <th>Type</th>
                <th>Size (kb)</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fileRecords as $file): ?>
                <tr>
                  <td><?= htmlspecialchars($file['filename']) ?></td>
                  <td><?= htmlspecialchars($file['uploaded_at']) ?></td>
                  <td><?= htmlspecialchars($file['file_type']) ?></td>
                  <td><?= htmlspecialchars($file['file_size']) ?></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item preview-file" href="file_preview.php?file_id=<?= urlencode($file['id']) ?>" target="_blank">Preview</a></li>
                        <li><a class="dropdown-item" href="/file_download.php?file_id=<?= $file['id'] ?>" target="_blank" download>Download</a></li>
                        <li><a class="dropdown-item edit-file" href="admin/edit_file.php?file_id=<?= $file['id'] ?>" target="_blank">Edit</a></li>
                        <li><a class="dropdown-item text-danger delete-file" href="delete_file.php" data-fileid="<?= $file['id'] ?>">Delete</a></li>
                      </ul>
                    </div>
                  </td>
                </tr>

              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script>
    const table = $('#file-table').DataTable({
      dom: 'rt',
      paging: false,
      info: false,
      lengthChange: false
    });

    // SEARCH
    $('#tableSearch').on('input', function(){
      table.search(this.value).draw();
    });

    // FILTER
    const types = new Set();
    table.rows().every(function(){
      const val = this.data()[2]; // column = file_type
      types.add(val);
    });

    let menuHtml = '';
    [...types].sort().forEach(type => {
      menuHtml += `
        <div class="form-check">
          <input class="form-check-input type-checkbox" type="checkbox" value="${type}">
          <label class="form-check-label">${type}</label>
        </div>`;
    });
    $('#typeFilterMenu').html(menuHtml);

    $.fn.dataTable.ext.search.push((settings, row) => {
      const selected = $('.type-checkbox:checked').map((_,el)=>el.value).get();
      return !selected.length || selected.includes(row[2]); // column = file_type
    });

    $('#typeFilterMenu').on('change', '.type-checkbox', function(){
      table.draw();
      const chosen = $('.type-checkbox:checked').map((_,el)=>el.value).get();
      $('#typeFilterBtn').text('File Type: ' + (chosen.length ? chosen.join(', ') : 'All'));
    });
  </script>

  <script>
    $(document).ready(function () {
      let fileIdToDelete = null;

      // Open the modal when delete is clicked
      $('#file-table').on('click', '.delete-file', function (e) {
        e.preventDefault();
        fileIdToDelete = $(this).data('fileid');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
      });

      // Confirm deletion
      $('#confirmDeleteBtn').on('click', function () {
        if (fileIdToDelete) {
          $.post('admin/delete_file.php', { file_id: fileIdToDelete }, function (res) {
            if (res.trim() === 'success') {
              location.reload();
            } else {
              alert('Delete failed: ' + res);
            }
          }).fail(function () {
            alert('Error contacting server.');
          });
        }

        // Close modal
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
        deleteModal.hide();
      });
    });
  </script>


  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this file?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
