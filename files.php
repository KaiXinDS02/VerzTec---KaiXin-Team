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

$user_id = 1;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

$role = $_SESSION['role'] ?? '';
$dept = $_SESSION['department'] ?? 'Your Department';
$country = $_SESSION['country'] ?? 'Your Country';

// Fetch unique departments from users table
$deptResult = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = [];
if ($deptResult) {
  while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row['department'];
  }
}

// Fetch countries from countries table
$countryResult = $conn->query("SELECT country FROM countries ORDER BY country ASC");
$countries = [];
if ($countryResult) {
  while ($row = $countryResult->fetch_assoc()) {
    $countries[] = $row['country'];
  }
}

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

// Scan /files and insert if not in DB
$inserted = 0;

foreach (scandir($directory) as $file) {
    if ($file === '.' || $file === '..') continue;

    $filePath = realpath("$directory/$file");
    $mimeType = mime_content_type($filePath);
    $fileType = getFriendlyFileType($mimeType);
    $fileSize = round(filesize($filePath) / 1024);
    $relativePath = 'files/' . $file; // Use web-safe relative path

    $check = $conn->prepare("SELECT id FROM files WHERE file_path = ?");
    $check->bind_param("s", $relativePath);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $user_id, $file, $relativePath, $fileType, $fileSize);

        if ($stmt->execute()) {
            $file_id = $stmt->insert_id;

            // Insert default visibility: ALL
            $visStmt = $conn->prepare("INSERT INTO file_visibility (file_id, visibility_scope) VALUES (?, 'ALL')");
            $visStmt->bind_param("i", $file_id);
            $visStmt->execute();
            $visStmt->close();

            $inserted++;
        }
        $stmt->close();
    }
    $check->close();
}

$role = $_SESSION['role'] ?? '';
$country = $_SESSION['country'] ?? '';
$department = $_SESSION['department'] ?? '';

if ($role === 'ADMIN') {
    $stmt = $conn->prepare("
        SELECT f.*
        FROM files f
        ORDER BY f.uploaded_at DESC
    ");
} elseif ($role === 'MANAGER') {
    $stmt = $conn->prepare("
        SELECT DISTINCT f.*
        FROM files f
        JOIN file_visibility v ON f.id = v.file_id
        WHERE v.visibility_scope = 'ALL'
           OR (v.visibility_scope = 'COUNTRY' AND v.category = ?)
        ORDER BY f.uploaded_at DESC
    ");
    $stmt->bind_param("s", $country);
} else { // USER
    $stmt = $conn->prepare("
        SELECT DISTINCT f.*
        FROM files f
        JOIN file_visibility v1 ON f.id = v1.file_id
        LEFT JOIN file_visibility v2 ON f.id = v2.file_id
        WHERE v1.visibility_scope = 'ALL'
           OR (v1.visibility_scope = 'COUNTRY' AND v1.category = ?)
           OR (v2.visibility_scope = 'DEPARTMENT' AND v2.category = ?)
        GROUP BY f.id
        HAVING 
            SUM(v1.visibility_scope = 'ALL') > 0 OR 
            (SUM(v1.visibility_scope = 'COUNTRY' AND v1.category = ?) > 0 AND SUM(v2.visibility_scope = 'DEPARTMENT' AND v2.category = ?) > 0)
        ORDER BY f.uploaded_at DESC
    ");
    $stmt->bind_param("ssss", $country, $department, $country, $department);
}

$stmt->execute();
$result = $stmt->get_result();
$files = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
    #file-table thead th {
      position: sticky;
      top: 0;
      background: #212529;
      color: white;
      z-index: 10;
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

    .edit-submenu {
      display: none;
      position: absolute;
      top: 50px;
      left: 100%; /* position to the right of main dropdown */
      min-width: 150px;
      z-index: 1050;
    }
    .dropdown-menu {
      padding: 0.5rem 0;
    }
    .dropdown-item {
      padding: 0.4rem 1rem;
    }

    /* nested dropdown */
    .dropdown-submenu {
      position: relative;
    }

    .dropdown-submenu .dropdown-menu {
      position: absolute;
      top: 0;
      right: 100%;
      margin-top: -6px;
      margin-right: 2px;
      border-radius: 6px;
      min-width: 180px;
      z-index: 1001;
    }

    .dropdown-submenu:hover > .dropdown-menu {
      display: block;
    }

    .dropdown-submenu > a:after {
      display: none;
    }

    .dropdown-submenu .dropdown-toggle::after {
      display: none;
    }

    /* Ensure submenu appears completely outside the parent */
    .dropdown-menu {
      position: relative;
      z-index: 1000;
    }

    /* Arrow styling */
    .dropdown-submenu .fa-chevron-right {
      font-size: 0.8em;
      color: #6c757d;
    }

    /* Prevent overlap and ensure proper spacing */
    .dropdown-submenu .dropdown-menu {
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(0, 0, 0, 0.175);
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

            <button class="btn btn-dark d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
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
            <thead id="file-table" class="table-dark">
              <tr>
                <th>Filename</th>
                <th>Uploaded At</th>
                <th>Type</th>
                <th>Size (kb)</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($files as $file): ?>
                <tr>
                  <td><?= htmlspecialchars($file['filename']) ?></td>
                  <td><?= htmlspecialchars($file['uploaded_at']) ?></td>
                  <td><?= htmlspecialchars($file['file_type']) ?></td>
                  <td><?= htmlspecialchars($file['file_size']) ?></td>
                  <td>
                    <!-- Dropdown for Vertical Ellipsis -->
                    <div class="dropdown">
                      <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item preview-file" href="file_preview.php?file_id=<?= urlencode($file['id']) ?>" target="_blank">Preview</a></li>
                        <li><a class="dropdown-item" href="/file_download.php?file_id=<?= $file['id'] ?>" target="_blank" download>Download</a></li>
                        
                        <!-- Role-Specific Buttons -->
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['MANAGER', 'ADMIN'])): ?>
                            <!-- Nested Dropdown for Edit -->
                            <li class="dropdown-submenu">
                              <a class="dropdown-item dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Edit
                                <i class="fas fa-chevron-right float-end mt-1"></i>
                              </a>
                              <ul class="dropdown-menu dropdown-submenu-left">
                                <li><a class="dropdown-item rename-file" href="rename_file.php" data-id="<?= $file['id'] ?>" data-name="<?= htmlspecialchars($file['filename']) ?>">Rename File</a></li>
                                <li><a class="dropdown-item edit-file" href="admin/edit_file.php?file_id=<?= $file['id'] ?>" target="_blank">Edit Content</a></li>
                                <li><a class="dropdown-item edit-visibility" href="admin/edit_visibility.php?file_id=<?= $file['id'] ?>">Edit Visibility</a></li>
                              </ul>
                            </li>
                            
                            <li><a class="dropdown-item text-danger delete-file" href="delete_file.php" data-fileid="<?= $file['id'] ?>">Delete</a></li>
                        <?php endif; ?>
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
  
  <!-- Upload File Modal -->
  <div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title" id="uploadFileModalLabel">Upload File</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Form -->
        <form id="uploadFileForm" action="admin/upload_file.php" method="POST" enctype="multipart/form-data">
          <div class="modal-body">

            <!-- Upload Area -->
            <div class="border border-dashed rounded p-4 text-center"
                style="border: 2px dashed #ccc;"
                ondrop="handleDrop(event)"
                ondragover="event.preventDefault()">
              <p class="mb-2">Drag and drop a file here or</p>
              <input type="file" id="fileInput" name="upload_file" class="form-control d-inline-block" style="width: auto;" required>
            </div>

            <hr class="my-4">

            <?php if ($role === 'ADMIN'): ?>
              <!-- Admin Visibility Options -->
              <div class="mb-3">
                <label class="form-label fw-bold">Visibility</label><br>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="visibility" id="accessAll" value="all" checked>
                  <label class="form-check-label" for="accessAll">All</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="visibility" id="accessRestricted" value="restricted">
                  <label class="form-check-label" for="accessRestricted">Restricted Access</label>
                </div>
              </div>

              <div id="restrictionOptions" class="d-none">
                <!-- Restrict By -->
                <div class="mb-3">
                  <label class="form-label fw-bold">Restrict By</label><br>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input restrict-toggle" type="checkbox" id="restrictByDept" value="department">
                    <label class="form-check-label" for="restrictByDept">Department</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input restrict-toggle" type="checkbox" id="restrictByCountry" value="country">
                    <label class="form-check-label" for="restrictByCountry">Country</label>
                  </div>
                </div>

                <!-- Department Options -->
                <div class="mb-3 d-none" id="restrictDepartmentDiv">
                  <label class="form-label">Select Departments</label>
                  <?php foreach ($departments as $d): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="departments[]" value="<?= htmlspecialchars($d) ?>" id="dept<?= htmlspecialchars($d) ?>">
                      <label class="form-check-label" for="dept<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Country Options -->
                <div class="mb-3 d-none" id="restrictCountryDiv">
                  <label class="form-label">Select Countries</label>
                  <?php foreach ($countries as $c): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="countries[]" value="<?= htmlspecialchars($c) ?>" id="country<?= htmlspecialchars($c) ?>">
                      <label class="form-check-label" for="country<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

            <?php elseif ($role === 'MANAGER'): ?>
              <!-- Manager Visibility Options -->
              <input type="hidden" name="visibility" value="restricted">
              <input type="hidden" name="departments[]" value="<?= htmlspecialchars($dept) ?>">

              <div class="mb-3">
                <label class="form-label fw-bold">Visibility</label><br>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manager_visibility" id="onlyDepartment" value="department" checked>
                  <label class="form-check-label" for="onlyDepartment">
                    Only allow access to my Department: <?= htmlspecialchars($dept) ?>
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="manager_visibility" id="wholeCountry" value="country">
                  <label class="form-check-label" for="wholeCountry">
                    Allow access to the whole country: <?= htmlspecialchars($country) ?>
                  </label>
                </div>
              </div>

              <!-- Hidden input populated by JS before submit -->
              <input type="hidden" name="countries[]" value="<?= htmlspecialchars($country) ?>" id="managerCountryInput" disabled>
            <?php endif; ?>

          </div>

          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Upload</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>

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

  <!-- Rename File Modal -->
  <div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" action="admin/rename_file.php" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Rename File</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="file_id" id="fileIdInput">
          <div class="mb-3">
            <label for="newFilename" class="form-label">New Filename</label>
            <input type="text" class="form-control" name="new_filename" id="newFilename" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-dark">Rename</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  document.querySelectorAll('.rename-file').forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const fileId = this.dataset.id;
      const fileName = this.dataset.name;

      document.getElementById('fileIdInput').value = fileId;
      document.getElementById('newFilename').value = fileName;

      const renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
      renameModal.show();
    });
  });
  </script>
  <script>
    // JavaScript to handle nested dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Handle submenu clicks
      document.querySelectorAll('.dropdown-submenu a.dropdown-toggle').forEach(function(element) {
        element.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          let submenu = this.nextElementSibling;
          if (submenu) {
            // Toggle the submenu
            if (submenu.style.display === 'block') {
              submenu.style.display = 'none';
            } else {
              // Hide other submenus
              document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
              });
              submenu.style.display = 'block';
            }
          }
        });
      });

      // Handle hover for better UX
      document.querySelectorAll('.dropdown-submenu').forEach(function(element) {
        element.addEventListener('mouseenter', function() {
          let submenu = this.querySelector('.dropdown-menu');
          if (submenu) {
            submenu.style.display = 'block';
          }
        });

        element.addEventListener('mouseleave', function() {
          let submenu = this.querySelector('.dropdown-menu');
          if (submenu) {
            submenu.style.display = 'none';
          }
        });
      });

      // Close submenus when main dropdown closes
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
          document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(menu) {
            menu.style.display = 'none';
          });
        }
      });
    });
    </script>
    <script>
      // Show/hide restriction options
      document.querySelectorAll('input[name="visibility"]').forEach(el => {
        el.addEventListener('change', () => {
          const restricted = document.getElementById('accessRestricted').checked;
          document.getElementById('restrictionOptions').classList.toggle('d-none', !restricted);
        });
      });

      // Show/hide department/country selectors
      document.querySelectorAll('.restrict-toggle').forEach(el => {
        el.addEventListener('change', () => {
          document.getElementById('restrictDepartmentDiv').classList.toggle('d-none', !document.getElementById('restrictByDept').checked);
          document.getElementById('restrictCountryDiv').classList.toggle('d-none', !document.getElementById('restrictByCountry').checked);
        });
      });

      // Drag and drop support
      function handleDrop(event) {
        event.preventDefault();
        const files = event.dataTransfer.files;
        if (files.length > 0) {
          document.getElementById('fileInput').files = files;
        }
      }
    </script>


</body>
</html>
