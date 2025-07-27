<?php
session_start();
include('connect.php'); 
include 'admin/auto_log_function.php';
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

$message = "";
$directory = 'chatbot/data/pdfs';

// Determine user context
$user_id = $_SESSION['user_id'] ?? 1;
$role    = $_SESSION['role']    ?? '';
$user_dept = $_SESSION['department'] ?? 'Your Department';
$user_country = $_SESSION['country'] ?? 'Your Country';

// Manager-specific visibility logic
if ($role === 'MANAGER') {
    $visibility = 'restricted';
    $managerVisibility = $_POST['manager_visibility'] ?? 'department'; // 'department' or 'country'

    if ($managerVisibility === 'department') {
        // Only department visibility
        $departments = [$_POST['departments'][0] ?? '']; // manager's department from hidden input
        $countries = [];
    } else {
        // Whole country visibility
        $departments = [];
        $countries = [$_POST['countries'][0] ?? '']; // manager's country from hidden input
    }
}

// Fetch unique departments
$deptResult = $conn->query("
  SELECT DISTINCT department
  FROM users
  WHERE department IS NOT NULL AND department != ''
  ORDER BY department ASC
");
$departments = [];
while($r = $deptResult->fetch_assoc()){
  $departments[] = $r['department'];
}

// Fetch countries
$countryResult = $conn->query("SELECT country FROM countries ORDER BY country ASC");
$countries = [];
while($r = $countryResult->fetch_assoc()){
  $countries[] = $r['country'];
}

// Build files query based on new file_visibility table
if($role==='ADMIN'){
  $stmt = $conn->prepare("SELECT * FROM files ORDER BY uploaded_at DESC");
}
elseif($role==='MANAGER'){
  $stmt = $conn->prepare("
    SELECT DISTINCT f.*
    FROM file_visibility v
    JOIN files f ON f.id = v.file_id
    WHERE (v.country = 'ALL' AND v.department = 'ALL')
       OR (v.country = ? AND v.department = 'ALL')
       OR (v.country = ? AND v.department = ?)
    ORDER BY f.uploaded_at DESC
  ");
  $stmt->bind_param("sss", $user_country, $user_country, $user_dept);
}
else {
  $stmt = $conn->prepare("
    SELECT DISTINCT f.*
    FROM files f
    JOIN file_visibility v ON f.id = v.file_id
    WHERE (v.country = 'ALL' AND v.department = 'ALL')
      OR (v.country = ? AND v.department = 'ALL')
      OR (v.country = ? AND v.department = ?)
    ORDER BY f.uploaded_at DESC
  ");
  $stmt->bind_param("sss", $user_country, $user_country, $user_dept);
}
$stmt->execute();

$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Fetch unique departments for fallback (optional)
$deptResult = $conn->query("
  SELECT DISTINCT department
  FROM users
  WHERE department IS NOT NULL AND department != ''
  ORDER BY department ASC
");
$departments = [];
while($r = $deptResult->fetch_assoc()){
  $departments[] = $r['department'];
}

// Fetch distinct countries from users table and their departments
$countryDeptMap = [];
$res = $conn->query("SELECT DISTINCT country, department FROM users WHERE country IS NOT NULL AND country != '' AND department IS NOT NULL AND department != '' ORDER BY country, department");

while ($row = $res->fetch_assoc()) {
    $c = $row['country'];
    $d = $row['department'];
    if (!isset($countryDeptMap[$c])) {
        $countryDeptMap[$c] = [];
    }
    if (!in_array($d, $countryDeptMap[$c])) {
        $countryDeptMap[$c][] = $d;
    }
}
// Build visibility metadata for display (new schema)
$fileVisibilities = [];
if (!empty($files)) {
    $fileIds = array_column($files, 'id');
    $placeholders = str_repeat('?,', count($fileIds) - 1) . '?';
    $types = str_repeat('i', count($fileIds));
    $visQuery = $conn->prepare("SELECT file_id, country, department FROM file_visibility WHERE file_id IN ($placeholders)");
    $visQuery->bind_param($types, ...$fileIds);
    $visQuery->execute();
    $visResult = $visQuery->get_result();
    while ($row = $visResult->fetch_assoc()) {
        $fid = $row['file_id'];
        $country = $row['country'];
        $department = $row['department'];
        if (!isset($fileVisibilities[$fid])) {
            $fileVisibilities[$fid] = ['ALL' => false, 'COUNTRY' => [], 'DEPARTMENT' => []];
        }
        if ($country === 'ALL' && $department === 'ALL') {
            $fileVisibilities[$fid]['ALL'] = true;
        } elseif ($country !== 'ALL' && $department === 'ALL') {
            $fileVisibilities[$fid]['COUNTRY'][] = $country;
        } elseif ($country !== 'ALL' && $department !== 'ALL') {
            $fileVisibilities[$fid]['DEPARTMENT'][] = $department . ' (' . $country . ')';
        }
    }
    $visQuery->close();
}

// Fetch username 
$username='Unknown';
$usr = $conn->prepare("SELECT username FROM users WHERE user_id=?");
$usr->bind_param("i",$user_id);
$usr->execute();
$usr->bind_result($username);
$usr->fetch();
$usr->close();

// Friendly file-type mapper → icon + color
function getIconClass($type){
  switch($type){
    case 'pdf': return 'file-pdf';
    case 'msword': return 'file-word';
    case 'excel': return 'file-excel';
    case 'powerpoint': return 'file-powerpoint';
    case 'jpeg': case 'png': case 'gif': return 'file-image';
    case 'text': return 'file-alt';
    case 'zip': case 'rar': return 'file-archive';
    default: return 'file';
  }
}
function getIconColor($type){
  switch($type){
    case 'pdf': return '#d9534f';
    case 'msword': return '#337ab7';
    case 'excel': return '#5cb85c';
    case 'powerpoint': return '#f0ad4e';
    case 'file-image': return '#5bc0de';
    case 'text': return '#777';
    case 'zip': case 'rar': return '#999';
    default: return '#666';
  }
}


// Friendly file type mapper for friendly display in Table
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

  // Scan chatbot/data/pdfs/ and insert if not in DB
  $inserted = 0;
  foreach (scandir($directory) as $file) {
      if ($file === '.' || $file === '..') continue;

      $filePath = realpath("$directory/$file");
      if (!$filePath) continue;

      $mimeType = mime_content_type($filePath);
      $fileType = getFriendlyFileType($mimeType);
      $fileSize = round(filesize($filePath) / 1024);
      $relativePath = 'chatbot/data/pdfs/' . $file;

      // Check if already in DB
      $check = $conn->prepare("SELECT id FROM files WHERE file_path = ?");
      $check->bind_param("s", $relativePath);
      $check->execute();
      $check->store_result();

      if ($check->num_rows === 0) {
          // Insert file record
          $stmt = $conn->prepare("INSERT INTO files (user_id, filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("isssi", $user_id, $file, $relativePath, $fileType, $fileSize);

          if ($stmt->execute()) {
              $file_id = $stmt->insert_id;

              // Insert 'ALL' visibility
              $visStmt = $conn->prepare("INSERT INTO file_visibility (file_id, country, department) VALUES (?, ?, ?)");
              $all = 'ALL';
              $visStmt->bind_param("iss", $file_id, $all, $all);
              $visStmt->execute();
              $visStmt->close();

              $inserted++;
          }
          $stmt->close();
      }

      $check->close();
  }
?>



<!-- Front-End -->
<!DOCTYPE html>
<html lang="en-US">
<head>
  <base href="../">
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verztec – Files</title>
  <link rel="icon" href="images/favicon.ico">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <style>
    body {
      padding-top: 6rem;    /* more top padding */
      padding-left: 2rem;   /* side padding */
      padding-right: 2rem;
      font-family: "Segoe UI", sans-serif;
    }
    /* HEADER (unchanged) */
    .header-area {
      position: fixed; top:0; left:0; width:100%;
      z-index:999; background:#fff;
      box-shadow:0 2px 4px rgba(0,0,0,0.1);
    }
    /* CONTROLS ROW */
    .controls-row {
      display:flex; flex-wrap:wrap;
      justify-content:space-between;
      align-items:center;
      margin-bottom:1.5rem;
    }
    .search-box {
      position:relative; width:320px;
    }
    .search-box input {
      width:100%; padding:.6rem 1rem .6rem 2.5rem;
      border:1px solid #ccc; border-radius:6px;
      background:#fff;
    }
    .search-box i {
      position:absolute; left:1rem; top:50%;
      transform:translateY(-50%); color:#aaa;
    }
    .filter-dropdown .dropdown-toggle {
      background:#fff; color:#333;
      border:1px solid #ccc; border-radius:6px;
      padding:.5rem 1rem;
      transition:.2s;
    }
    .filter-dropdown .dropdown-toggle:hover {
      background:#000; color:#fff; border-color:#000;
    }
    .filter-dropdown .dropdown-menu {
      max-height:200px; overflow-y:auto; padding:.5rem;
    }
    .btn-upload {
      background:#000; color:#fff;
      border:none; border-radius:6px;
      padding:.6rem 1.2rem;
      transition:.2s;
    }
    .btn-upload:hover {
      background:#333;
    }
    /* TABLE CONTAINER */
    .table-container {
      background:#fff; border-radius:8px;
      overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);
      max-height:60vh; overflow-y:auto;
    }
    #file-table {
      border-collapse: separate !important;
      width:100%;
    }
    #file-table thead th {
      position:sticky; top:0;
      background:#000; color:#fff;
      padding:.75rem 1rem;
      z-index:2;
    }
    #file-table thead th:first-child { border-top-left-radius:8px }
    #file-table thead th:last-child  { border-top-right-radius:8px }
    #file-table tbody tr { border-bottom:1px solid #eee }
    #file-table td {
      padding:.75rem 1rem; vertical-align:middle;
      font-size:.9rem; color:#333;
    }
    .file-icon { font-size:1.2rem; margin-right:.5rem }
    
    /* FORCE MODAL Z-INDEX TO APPEAR ON TOP */
    .modal {
      z-index: 99999 !important;
    }
    .modal-backdrop {
      z-index: 99998 !important;
    }
    
    /* ENSURE MODAL HAS MINIMUM DIMENSIONS */
    .modal-dialog {
      min-width: 400px !important;
      min-height: 300px !important;
    }
    .modal-content {
      min-height: 250px !important;
    }
    .modal-body {
      min-height: 150px !important;
      padding: 20px !important;
    }
  </style>
</head>
<body>

  <!-- NAVIGATION (unchanged) -->
  <header class="header-area">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-xl-3 col-md-4 col-6">
          <a href="home.php" class="page-logo-wp"><img src="images/logo.png" alt="Verztec"></a>
        </div>
        <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
          <div class="page-menu-wp">
            <ul>
              <li><a href="home.php">Home</a></li>
              <li><a href="chatbot.php">Chatbot</a></li>
              <li class="active"><a href="files.php">Files</a></li>
              <?php if ($_SESSION['role'] !== 'USER'): ?>
                <li><a href="admin/users.php">Admin</a></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
        <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
          <div class="page-user-icon profile">
            <button><img src="images/Profile-Icon.svg" alt="Profile"></button>
            <div class="menu">
              <ul>
                <li><a href="#"><i class="fa-regular fa-user"></i> Profile</a></li>
                <li><a href="#"><i class="fa-regular fa-moon"></i> Theme</a></li>
                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i> Sign Out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="container-fluid" style="padding-top:2rem;">
    <div class="row">
      <div class="col-12">

        <!-- CONTROLS -->
        <div class="controls-row">
          <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="tableSearch" placeholder="Search files…">
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="dropdown filter-dropdown">
              <button class="dropdown-toggle" id="typeFilterBtn" data-bs-toggle="dropdown">
                File Type: All
              </button>
              <div class="dropdown-menu" id="typeFilterMenu" aria-labelledby="typeFilterBtn">
                <!-- JS will inject checkboxes -->
              </div>
            </div>
            <button class="btn-upload" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
              <i class="fa fa-upload me-1"></i> Upload File
            </button>
            <form method="POST" enctype="multipart/form-data" style="display:none;">
              <input type="file" name="upload_file" id="upload_file"
                     accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf,.csv,.txt"
                     onchange="this.form.submit()">
            </form>
          </div>
        </div>

        <!-- FILES TABLE -->
        <div class="table-container">
          <table id="file-table" class="table table-hover mb-0 w-100">
            <thead>
              <tr>
                <th>Filename</th>
                <th>Modified At (UTC +8)</th>
                <th>Visbility</th>  
                <th>Type</th>
                <th>Size (kb)</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($files as $file):
                // Convert timestamp to user's timezone
                $user_country = $_SESSION['country'] ?? 'Singapore';
                $formattedDate = TimezoneHelper::convertToUserTimezone($file['uploaded_at'], $user_country, 'M j, Y g:i A');
                
                $icon = getIconClass($file['file_type']);
                $color= getIconColor($file['file_type']);

                $fileId = $file['id'];
                // Build visibility string as country/department pairs
                $visibilityText = '-';
                if (!empty($fileVisibilities[$fileId])) {
                  if (!empty($fileVisibilities[$fileId]['ALL'])) {
                    $visibilityText = 'ALL';
                  } else {
                    $pairs = [];
                    // Collect all country/department pairs
                    $rawPairs = [];
                    $visQuery = $conn->prepare("SELECT country, department FROM file_visibility WHERE file_id = ?");
                    $visQuery->bind_param("i", $fileId);
                    $visQuery->execute();
                    $visResult = $visQuery->get_result();
                    while ($row = $visResult->fetch_assoc()) {
                      $country = $row['country'];
                      $department = $row['department'];
                      if ($country === 'ALL' && $department === 'ALL') {
                        $pairs = ['ALL'];
                        break;
                      }
                      $pairs[] = $country . '/' . $department;
                    }
                    $visQuery->close();
                    if (!empty($pairs)) {
                      $visibilityText = implode(', ', $pairs);
                    }
                  }
                }

                // Determine editing/deleting permission 
                $canManage = false;
                $fileVisibility = $fileVisibilities[$fileId] ?? ['ALL' => false, 'DEPARTMENT' => [], 'COUNTRY' => []];

                if ($role === 'ADMIN') {
                    $canManage = true;
                } elseif ($role === 'MANAGER') {
                    // Managers can edit files that have visibility specifically for their country
                    // They CANNOT edit global ALL/ALL files (only admins can)
                    
                    // Skip global files
                    if (!empty($fileVisibility['ALL'])) {
                        $canManage = false; // Global files - only admins can edit
                    } else {
                        // Check if file has any visibility setting for manager's country
                        $hasManagerCountry = false;
                        
                        // Check country-wide visibility (country/ALL)
                        foreach ($fileVisibility['COUNTRY'] as $country) {
                            if (strtoupper(trim($country)) === strtoupper(trim($user_country))) {
                                $hasManagerCountry = true;
                                break;
                            }
                        }
                        
                        // Check department-specific visibility (country/department)
                        if (!$hasManagerCountry) {
                            foreach ($fileVisibility['DEPARTMENT'] as $deptCountryPair) {
                                // deptCountryPair is like "HR (Singapore)"
                                if (preg_match('/\(([^)]+)\)$/', $deptCountryPair, $matches)) {
                                    if (strtoupper(trim($matches[1])) === strtoupper(trim($user_country))) {
                                        $hasManagerCountry = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $canManage = $hasManagerCountry;
                    }
                }
                
                // Debug output for managers
                if ($role === 'MANAGER') {
                    $debugCountries = implode(', ', $fileVisibility['COUNTRY']);
                    $debugDepts = implode(', ', $fileVisibility['DEPARTMENT']);
                    echo "<!-- DEBUG: File {$file['filename']} - canManage: " . ($canManage ? 'true' : 'false') . 
                         ", isGlobal: " . (!empty($fileVisibility['ALL']) ? 'true' : 'false') . 
                         ", Countries: [{$debugCountries}], Departments: [{$debugDepts}], UserCountry: {$user_country} -->";
                }
 
              ?>
                <tr>
                  <td>
                    <i class="fa fa-<?= $icon ?> file-icon" style="color:<?= $color ?>"></i>
                    <?= htmlspecialchars($file['filename']) ?>
                  </td>
                  <td><?= $formattedDate ?></td>
                  <td><?= htmlspecialchars($visibilityText) ?></td>
                  <td><?= htmlspecialchars($file['file_type']) ?></td>
                  <td><?= htmlspecialchars($file['file_size']) ?></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-light btn-sm dropdown-toggle"
                              type="button" data-bs-toggle="dropdown">
                        <i class="fa fa-ellipsis-v"></i>
                      </button>
                      <ul class="dropdown-menu">
                        <li>
                          <a class="dropdown-item preview-file"
                             href="file_preview.php?file_id=<?= $file['id'] ?>"
                             target="_blank">Preview</a>
                        </li>
                        <li>
                          <a class="dropdown-item"
                             href="/file_download.php?file_id=<?= $file['id'] ?>"
                             download>Download</a>
                        </li>
                        <?php if ($canManage==true): ?>
                          <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">Edit</a>
                            <ul class="dropdown-menu">
                              <li>
                                <a class="dropdown-item rename-file" href="#"
                                   data-id="<?= $file['id'] ?>"
                                   data-name="<?= htmlspecialchars($file['filename']) ?>">
                                  Rename
                                </a>
                              </li>
                              <?php if($file['file_type']!=='pdf'): ?>
                                <li>
                                  <a class="dropdown-item"
                                     href="admin/edit_file.php?file_id=<?= $file['id'] ?>"
                                     target="_blank">Edit Content</a>
                                </li>
                              <?php endif; ?>
                              <li>
                                <a class="dropdown-item edit-visibility" href="#"
                                   data-bs-toggle="modal"
                                   data-bs-target="#editVisibilityModal<?= $file['id'] ?>">
                                  Visibility
                                </a>
                              </li>
                            </ul>
                          </li>
                          <li>
                            <a class="dropdown-item text-danger delete-file"
                               href="#" data-fileid="<?= $file['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">Delete</a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

<!-- UPLOAD FILE MODAL -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="uploadFileModalLabel">Upload File</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form id="uploadFileForm" action="admin/upload_file.php" method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <!-- File Upload -->
        <div class="border border-dashed rounded p-4 text-center"
             style="border:2px dashed #ccc;"
             ondrop="handleDrop(event)" ondragover="event.preventDefault()">
          <p class="mb-2">Drag and drop your file(s) here or</p>
          <input type="file" id="fileInput" name="upload_files[]"
                 class="form-control d-inline-block" style="width:auto;" multiple required>
        </div>
        <hr class="my-4">

        <?php if ($role === 'ADMIN'): ?>
        <!-- Visibility Toggle -->
        <div class="mb-3">
          <label class="form-label fw-bold">Visibility</label><br>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility"
                  id="accessAll" value="all" checked>
            <label class="form-check-label" for="accessAll">All</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility"
                  id="accessRestricted" value="restricted">
            <label class="form-check-label" for="accessRestricted">Restricted</label>
          </div>
        </div>

        <!-- Restriction UI -->
        <div id="restrictionOptions" class="d-none">
          <label class="form-label fw-bold mb-2">Select Visibility Scope</label>
          <div id="countryDeptMatrixContainer" class="table-responsive">
            <table class="table table-bordered align-middle text-center" id="countryDeptMatrixTable">
              <thead class="table-dark">
                <tr>
                  <th style="min-width:150px;">Department</th>
                  <?php foreach (array_keys($countryDeptMap) as $country): ?>
                    <th>
                      <?= htmlspecialchars($country) ?><br>
                      <input type="checkbox" class="select-column" data-country="<?= htmlspecialchars($country) ?>">
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $allDepartments = [];
                foreach ($countryDeptMap as $depts) {
                  foreach ($depts as $dept) {
                    $allDepartments[$dept] = true;
                  }
                }
                $allDepartments = array_keys($allDepartments);
                sort($allDepartments);
                ?>
                <?php foreach ($allDepartments as $dept): ?>
                  <tr>
                    <td class="text-start ps-3"><?= htmlspecialchars($dept) ?></td>
                    <?php foreach (array_keys($countryDeptMap) as $country): ?>
                      <td>
                        <?php if (in_array($dept, $countryDeptMap[$country])): ?>
                          <input type="checkbox"
                            class="matrix-checkbox country-<?= htmlspecialchars($country) ?>"
                            name="matrix_selection[<?= htmlspecialchars($country) ?>][]"
                            value="<?= htmlspecialchars($dept) ?>">
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-dark">Upload</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
    <?php endif; ?>


    <?php if ($role === 'MANAGER'): ?>
        <!-- Visibility Toggle -->
        <div class="mb-3">
          <label class="form-label fw-bold">Visibility</label><br>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility"
                  id="accessAll" value="all" checked>
            <label class="form-check-label" for="accessAll">Within Department (<?= htmlspecialchars($user_dept) ?>)</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility"
                  id="accessRestricted" value="restricted">
            <label class="form-check-label" for="accessRestricted">Country-wide Access (<?= htmlspecialchars($user_country) ?>)</label>
          </div>
        </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-dark">Upload</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
    <?php endif; ?>
    
  </div></div>
</div>

<!-- Render all modals after the table -->
<?php foreach($files as $file): ?>
  <div class="modal fade" id="editVisibilityModal<?= $file['id'] ?>" tabindex="-1" aria-labelledby="editVisibilityModalLabel<?= $file['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST" action="admin/edit_visibility.php">
          <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="editVisibilityModalLabel<?= $file['id'] ?>">
              Edit Visibility – <?= htmlspecialchars($file['filename']) ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php $fv = $fileVisibilities[$file['id']] ?? ['ALL' => true]; ?>
            <?php if($role === 'ADMIN'): ?>
              <div class="mb-3">
                <label class="form-label fw-bold">Visibility</label><br>
                <div class="form-check form-check-inline">
                  <input class="form-check-input visibility-radio" type="radio" name="visibility" id="vAll<?= $file['id'] ?>" value="all" <?= !empty($fv['ALL']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="vAll<?= $file['id'] ?>">All</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input visibility-radio" type="radio" name="visibility" id="vRest<?= $file['id'] ?>" value="restricted" <?= empty($fv['ALL']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="vRest<?= $file['id'] ?>">Restricted</label>
                </div>
              </div>

              <div id="editRestrictionOptions<?= $file['id'] ?>" class="<?= !empty($fv['ALL']) ? 'd-none' : '' ?>">
                <label class="form-label fw-bold mb-2">Select Visibility Scope</label>
                <div id="editCountryDeptMatrixContainer<?= $file['id'] ?>" class="table-responsive">
                  <table class="table table-bordered align-middle text-center" id="editCountryDeptMatrixTable<?= $file['id'] ?>">
                    <thead class="table-dark">
                      <tr>
                        <th style="min-width:150px;">Department</th>
                        <?php foreach (array_keys($countryDeptMap) as $country): ?>
                          <th>
                            <?= htmlspecialchars($country) ?><br>
                            <input type="checkbox" class="select-column" data-country="<?= htmlspecialchars($country) ?>" id="editSelectAll<?= $file['id'] . htmlspecialchars($country) ?>">
                          </th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $allDepartments = [];
                      foreach ($countryDeptMap as $depts) {
                        foreach ($depts as $dept) {
                          $allDepartments[$dept] = true;
                        }
                      }
                      $allDepartments = array_keys($allDepartments);
                      sort($allDepartments);
                      ?>
                      <?php foreach ($allDepartments as $dept): ?>
                        <tr>
                          <td class="text-start ps-3"><?= htmlspecialchars($dept) ?></td>
                          <?php foreach (array_keys($countryDeptMap) as $country): ?>
                            <td>
                              <?php if (in_array($dept, $countryDeptMap[$country])): ?>
                                <?php
                                  // Pre-fill: check if this (country, dept) is in file visibility
                                  $checked = false;
                                  if (!empty($fv['DEPARTMENT'])) {
                                    foreach ($fv['DEPARTMENT'] as $vis) {
                                      // $vis format: "HR (Singapore)"
                                      if ($vis === $dept . ' (' . $country . ')') {
                                        $checked = true;
                                        break;
                                      }
                                    }
                                  }
                                ?>
                                <input type="checkbox"
                                  class="matrix-checkbox country-<?= htmlspecialchars($country) ?>"
                                  name="matrix_selection[<?= htmlspecialchars($country) ?>][]"
                                  value="<?= htmlspecialchars($dept) ?>"
                                  <?= $checked ? 'checked' : '' ?>
                                >
                              <?php endif; ?>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            <?php elseif($role === 'MANAGER'): ?>
              <input type="hidden" name="visibility" value="restricted">
              <div class="mb-3">
                <label class="form-label fw-bold">Visibility</label><br>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    name="manager_visibility"
                    id="onlyDept<?= $file['id'] ?>"
                    value="department"
                    checked>
                  <label class="form-check-label" for="onlyDept<?= $file['id'] ?>">
                    Only Department: <?= htmlspecialchars($user_dept) ?>
                  </label>
                </div>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    name="manager_visibility"
                    id="wholeCtry<?= $file['id'] ?>"
                    value="country">
                  <label class="form-check-label" for="wholeCtry<?= $file['id'] ?>">
                    Whole Country: <?= htmlspecialchars($user_country) ?>
                  </label>
                </div>
              </div>
              <!-- Hidden inputs always sent -->
              <input type="hidden" name="departments[]" value="<?= htmlspecialchars($user_dept) ?>">
              <input type="hidden" name="countries[]" value="<?= htmlspecialchars($user_country) ?>">
              <div class="alert alert-info">
                <strong>Manager Access:</strong> You can only edit visibility for your own country (<?= htmlspecialchars($user_country) ?>). Other countries will remain unchanged.
              </div>
            <?php endif; ?>

          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
      </div>
    </div></div>
  </div>

  <!-- RENAME FILE MODAL -->
  <div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="admin/rename_file.php" class="modal-content">
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
    </form></div>
  </div>

    <!-- NOTIFICATION AND MODAL -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none;">
          <div class="modal-header" style="background-color:#81869E; color:#fff; border-radius: 12px 12px 0 0;">
            <h5 class="modal-title" id="announcementModalLabel" style="
              font-family: 'Gudea', sans-serif;
              font-weight: normal;
              white-space: normal;
              word-wrap: break-word;
              overflow-wrap: break-word;
            ">Announcement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body" style="
            font-family: 'Open Sans', sans-serif;
            color:#000;
            font-size:1rem;
            padding: 1.5rem 1.75rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
          ">
            <h5 id="modalTitle" style="
              margin-bottom: 1rem;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></h5>

            <p id="modalContent" style="
              margin: 0;
              padding: 0;
              text-align: left;
              word-wrap: break-word;
              overflow-wrap: break-word;
              white-space: pre-wrap;
            "></p>

            <hr>
            <p><strong>Target Audience:</strong> <span id="modalAudience"></span></p>
            <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
            <p><strong>Posted:</strong> <span id="modalTimestamp"></span></p>
          </div>
        </div>
      </div>
    </div>


  <!-- LOADING MODAL -->
  <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background:transparent; box-shadow:none; border:none;">
        <div class="d-flex flex-column align-items-center justify-content-center p-5">
          <div class="spinner-border text-dark mb-3" style="width:3rem;height:3rem;"></div>
          <div class="fw-bold text-dark">Processing...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- SUCCESS MESSAGE MODAL -->
  <div class="modal fade" id="countryMessageModal" tabindex="-1" aria-labelledby="countryMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="countryMessageModalLabel">Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="countryMessageBody">
          <!-- Message inserted dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPTS -->
  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="js/bootstrap.bundle.min.js"></script>
  <script src="js/scripts.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="js\notification.js"></script> <!-- script for handling announcements -->
  <script>
    // DataTable init
    const table = $('#file-table').DataTable({ dom:'rt', paging:false, info:false, lengthChange:false });

    // SEARCH
    $('#tableSearch').on('input', function(){
      table.search(this.value).draw();
      });

      // DELETE CONFIRM MODAL (add if missing)
      if (!document.getElementById('deleteConfirmModal')) {
        $('body').append(`
          <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Delete</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">Are you sure you want to delete this file?</div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </div>
            </div>
          </div>
        `);
      }

    // FILTER
    const types = new Set();
    table.rows().every(function(){
      types.add(this.data()[3]);
    });
    let menuHtml='';
    [...types].sort().forEach(type=>{
      menuHtml+=`
        <div class="form-check">
          <input class="form-check-input type-checkbox" type="checkbox" value="${type}">
          <label class="form-check-label">${type}</label>
        </div>`;
    });
    $('#typeFilterMenu').html(menuHtml);
    $.fn.dataTable.ext.search.push((settings,row)=>{
      const sel = $('.type-checkbox:checked').map((_,e)=>e.value).get();
      return !sel.length||sel.includes(row[3]);
    });
    $('#typeFilterMenu').on('change','.type-checkbox',function(){
      table.draw();
      const chosen = $('.type-checkbox:checked').map((_,e)=>e.value).get();
      $('#typeFilterBtn').text('File Type: '+(chosen.length?chosen.join(', '):'All'));
    });


    // DELETE CONFIRM
    $(document).ready(function(){
      let toDelete=null;
      $('#file-table').on('click','.delete-file',function(e){
        e.preventDefault();
        toDelete=$(this).data('fileid');
        new bootstrap.Modal($('#deleteConfirmModal')).show();
      });
      $('#confirmDeleteBtn').on('click',function(){
        if(!toDelete) return;
        // Show loading modal
        var loadingModal = new bootstrap.Modal($('#loadingModal'));
        loadingModal.show();
        $.post('admin/delete_file.php',{file_id:toDelete},res=>{
          loadingModal.hide();
          if(res.trim()==='success') {
            // Show countryMessageModal with success message, then reload
            $('#countryMessageBody').text('File deleted successfully.');
            var countryModal = new bootstrap.Modal($('#countryMessageModal'));
            countryModal.show();
            setTimeout(()=>location.reload(), 1200);
          } else {
            $('#countryMessageBody').text('Delete failed: '+res);
            var countryModal = new bootstrap.Modal($('#countryMessageModal'));
            countryModal.show();
          }
        }).fail(()=>{
          loadingModal.hide();
          $('#countryMessageBody').text('Server error');
          var countryModal = new bootstrap.Modal($('#countryMessageModal'));
          countryModal.show();
        });
        bootstrap.Modal.getInstance($('#deleteConfirmModal')).hide();
      });

      // RENAME FILE: Show loading modal and success modal
      $('#renameModal form').on('submit', function(e){
        e.preventDefault();
        // Close the rename modal before showing loading
        var renameModalInstance = bootstrap.Modal.getInstance($('#renameModal'));
        if(renameModalInstance) renameModalInstance.hide();
        var loadingModal = new bootstrap.Modal($('#loadingModal'));
        loadingModal.show();
        var form = this;
        $.post($(form).attr('action'), $(form).serialize(), function(res){
          loadingModal.hide();
          $('#countryMessageBody').text('File renamed successfully.');
          var countryModal = new bootstrap.Modal($('#countryMessageModal'));
          countryModal.show();
          setTimeout(()=>location.reload(), 1200);
        }).fail(function(){
          loadingModal.hide();
          $('#countryMessageBody').text('Rename failed.');
          var countryModal = new bootstrap.Modal($('#countryMessageModal'));
          countryModal.show();
        });
      });

      // UPLOAD FILE: Show loading modal and success modal
      $('#uploadFileForm').on('submit', function(e){
        e.preventDefault();
        // Close the upload modal before showing loading
        var uploadModalInstance = bootstrap.Modal.getInstance($('#uploadFileModal'));
        if(uploadModalInstance) uploadModalInstance.hide();
        var loadingModal = new bootstrap.Modal($('#loadingModal'));
        loadingModal.show();
        var formData = new FormData(this);
        $.ajax({
          url: $(this).attr('action'),
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(res){
            loadingModal.hide();
            $('#countryMessageBody').text('File uploaded successfully.');
            var countryModal = new bootstrap.Modal($('#countryMessageModal'));
            countryModal.show();
            setTimeout(()=>location.reload(), 1200);
          },
          error: function(){
            loadingModal.hide();
            $('#countryMessageBody').text('Upload failed.');
            var countryModal = new bootstrap.Modal($('#countryMessageModal'));
            countryModal.show();
          }
        });
      });
    });

    // RENAME FILE
    document.querySelectorAll('.rename-file').forEach(link=>{
      link.addEventListener('click',function(e){
        e.preventDefault();
        const id=this.dataset.id, nm=this.dataset.name;
        $('#fileIdInput').val(id);
        $('#newFilename').val(nm);
        new bootstrap.Modal($('#renameModal')).show();
      });
    });

    // EDIT VISIBILITY - Role-based modal approach
    document.querySelectorAll('.edit-visibility').forEach(link=>{
      link.addEventListener('click',function(e){
        e.preventDefault();
        const target = this.getAttribute('data-bs-target');
        console.log('Edit Visibility clicked, target:', target);
        
        // Check user role - only use custom modal for managers
        const userRole = '<?= $role ?>';
        console.log('User role:', userRole);
        
        if (userRole === 'MANAGER') {
          // For managers, use custom modal bypass
          const fileId = target.replace('#editVisibilityModal', '');
          console.log('Manager detected, using custom modal for file ID:', fileId);
          
          // Find the modal element
          const modalElement = document.querySelector(target);
          
          if (modalElement) {
            console.log('Creating custom modal for manager...');
            
            // Create a simple custom modal overlay with centered positioning
            const customModal = document.createElement('div');
            customModal.id = 'customEditModal' + fileId;
            customModal.style.cssText = `
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0, 0, 0, 0.5);
              z-index: 99999;
              display: flex;
              align-items: center;
              justify-content: center;
            `;
            
            // Create modal content container to match Bootstrap modal-dialog exactly
            const modalContainer = document.createElement('div');
            modalContainer.className = 'modal-dialog modal-lg';
            modalContainer.style.cssText = `
              max-width: 800px;
              margin: 1.75rem auto;
            `;
            
            // Create modal content with Bootstrap classes and explicit styling
            const modalContent = document.createElement('div');
            modalContent.className = 'modal-content';
            modalContent.style.cssText = `
              background-color: #fff;
              border: 1px solid rgba(0,0,0,.2);
              border-radius: 0.5rem;
              box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
              position: relative;
              display: flex;
              flex-direction: column;
              width: 100%;
              color: #212529;
            `;
            
            // Copy content from the original modal
            const originalModalBody = modalElement.querySelector('.modal-body');
            const originalModalHeader = modalElement.querySelector('.modal-header');
            
            // Create modal header with proper Bootstrap structure and styling
            let headerHTML = `<div class="modal-header" style="
              display: flex;
              flex-shrink: 0;
              align-items: center;
              justify-content: space-between;
              padding: 1rem 1rem;
              border-bottom: 1px solid #dee2e6;
              border-top-left-radius: calc(0.5rem - 1px);
              border-top-right-radius: calc(0.5rem - 1px);
            ">`;
            
            if (originalModalHeader) {
              const title = originalModalHeader.querySelector('.modal-title');
              headerHTML += `<h5 class="modal-title" style="
                margin-bottom: 0;
                line-height: 1.5;
                font-size: 1.25rem;
                font-weight: bold;
              ">${title ? title.innerHTML : 'Edit Visibility'}</h5>`;
            } else {
              headerHTML += '<h5 class="modal-title" style="margin-bottom: 0; line-height: 1.5; font-size: 1.25rem; font-weight: bold;">Edit Visibility</h5>';
            }
            
            headerHTML += `<button type="button" class="btn-close" onclick="closeCustomModal('${fileId}')" aria-label="Close" style="
              box-sizing: content-box;
              width: 1em;
              height: 1em;
              padding: 0.25em 0.25em;
              color: #000;
              background: transparent url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'m.235,1.406l4.461,4.461L9.157.404c.023-.023.061-.023.084,0l4.461,4.461c.023.023.023.061,0,.084L9.241,9.41l4.461,4.461c.023.023.023.061,0,.084l-4.461,4.461c-.023.023-.061.023-.084,0L4.696,13.955.235,18.416c-.023.023-.061.023-.084,0L-4.31,13.955c-.023-.023-.023-.061,0-.084L.151,9.41-4.31,4.949c-.023-.023-.023-.061,0-.084L.151.404C.174.381.212.381.235.404Z\'/%3e%3c/svg%3e') center/1em auto no-repeat;
              border: 0;
              border-radius: 0.375rem;
              opacity: .5;
              cursor: pointer;
            "></button></div>`;
            
            // Create modal body with proper Bootstrap structure and styling
            let bodyHTML = `<div class="modal-body" style="
              position: relative;
              flex: 1 1 auto;
              padding: 1rem;
            ">`;
            
            if (originalModalBody) {
              bodyHTML += originalModalBody.innerHTML;
            } else {
              bodyHTML += '<p>Modal content not found.</p>';
            }
            
            bodyHTML += '</div>';
            
            // Create modal footer with proper Bootstrap structure and styling
            let footerHTML = `
              <div class="modal-footer" style="
                display: flex;
                flex-wrap: wrap;
                flex-shrink: 0;
                align-items: center;
                justify-content: flex-end;
                padding: 0.75rem;
                border-top: 1px solid #dee2e6;
                border-bottom-right-radius: calc(0.5rem - 1px);
                border-bottom-left-radius: calc(0.5rem - 1px);
              ">
                <button type="button" class="btn btn-secondary" onclick="closeCustomModal('${fileId}')" style="
                  margin-right: 0.5rem;
                  color: #fff;
                  background-color: #6c757d;
                  border-color: #6c757d;
                  display: inline-block;
                  font-weight: 400;
                  line-height: 1.5;
                  text-align: center;
                  text-decoration: none;
                  vertical-align: middle;
                  cursor: pointer;
                  user-select: none;
                  border: 1px solid transparent;
                  padding: 0.375rem 0.75rem;
                  font-size: 1rem;
                  border-radius: 0.375rem;
                  transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
                ">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitCustomModal('${fileId}')" style="
                  color: #fff;
                  background-color: #0d6efd;
                  border-color: #0d6efd;
                  display: inline-block;
                  font-weight: 400;
                  line-height: 1.5;
                  text-align: center;
                  text-decoration: none;
                  vertical-align: middle;
                  cursor: pointer;
                  user-select: none;
                  border: 1px solid transparent;
                  padding: 0.375rem 0.75rem;
                  font-size: 1rem;
                  border-radius: 0.375rem;
                  transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
                ">Save</button>
              </div>
            `;
            
            // Set the complete modal content
            modalContent.innerHTML = headerHTML + bodyHTML + footerHTML;
            modalContainer.appendChild(modalContent);
            customModal.appendChild(modalContainer);
            
            // Add to page
            document.body.appendChild(customModal);
            console.log('Custom modal created and displayed for manager');
            
            // Close on backdrop click
            customModal.addEventListener('click', function(e) {
              if (e.target === customModal) {
                document.body.removeChild(customModal);
              }
            });
            
          } else {
            console.error('Modal element not found:', target);
          }
        } else {
          // For admins and other roles, use standard Bootstrap modal
          console.log('Admin/other role detected, using Bootstrap modal');
          const modalElement = document.querySelector(target);
          
          if (modalElement) {
            try {
              const modal = new bootstrap.Modal(modalElement);
              modal.show();
              console.log('Bootstrap modal shown successfully');
            } catch (error) {
              console.error('Error showing Bootstrap modal:', error);
            }
          } else {
            console.error('Modal element not found:', target);
          }
        }
      });
    });

    // Helper functions for custom modal
    window.closeCustomModal = function(fileId) {
      const customModal = document.getElementById('customEditModal' + fileId);
      if (customModal) {
        document.body.removeChild(customModal);
      }
    };

    window.submitCustomModal = function(fileId) {
      console.log('Submitting custom modal for file ID:', fileId);
      
      // Find the original form in the Bootstrap modal to get form data
      const originalModal = document.querySelector('#editVisibilityModal' + fileId);
      const originalForm = originalModal ? originalModal.querySelector('form') : null;
      
      if (originalForm) {
        // Create FormData from the original form
        const formData = new FormData(originalForm);
        
        // Also get values from the custom modal (in case user changed them)
        const customModal = document.getElementById('customEditModal' + fileId);
        if (customModal) {
          // Get radio button values from custom modal
          const managerVisibilityRadios = customModal.querySelectorAll('input[name="manager_visibility"]');
          managerVisibilityRadios.forEach(radio => {
            if (radio.checked) {
              formData.set('manager_visibility', radio.value);
            }
          });
        }
        
        console.log('Form data prepared, submitting...');
        
        // Close custom modal
        closeCustomModal(fileId);
        
        // Show loading modal
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();
        
        // Submit via AJAX
        fetch('admin/edit_visibility.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.text())
        .then(result => {
          loadingModal.hide();
          document.getElementById('countryMessageBody').textContent = 'Visibility updated successfully.';
          const countryModal = new bootstrap.Modal(document.getElementById('countryMessageModal'));
          countryModal.show();
          setTimeout(() => location.reload(), 1200);
        })
        .catch(error => {
          console.error('Error:', error);
          loadingModal.hide();
          document.getElementById('countryMessageBody').textContent = 'Visibility update failed.';
          const countryModal = new bootstrap.Modal(document.getElementById('countryMessageModal'));
          countryModal.show();
        });
      } else {
        console.error('Original form not found for file ID:', fileId);
        closeCustomModal(fileId);
      }
    };

    // NESTED SUBMENU
    document.addEventListener('DOMContentLoaded',function(){
      document.querySelectorAll('.dropdown-submenu > .dropdown-toggle').forEach(el=>{
        el.addEventListener('click',function(e){
          e.preventDefault(); e.stopPropagation();
          let sm=this.nextElementSibling;
          document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(m=>{ if(m!==sm) m.style.display='none'; });
          sm.style.display = sm.style.display==='block'?'none':'block';
        });
      });
      document.addEventListener('click',e=>{
        if(!e.target.closest('.dropdown'))
          document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(m=>m.style.display='none');
      });
    });

    // SHOW/HIDE RESTRICTIONS (ADMIN only)
    if (document.getElementById('restrictionOptions')) {
      document.querySelectorAll('input[name="visibility"]').forEach(el=>{
        el.addEventListener('change',()=>{
          document.getElementById('restrictionOptions').classList.toggle('d-none', !document.getElementById('accessRestricted')?.checked);
        });
      });
    }
    document.querySelectorAll('.restrict-toggle').forEach(el=>{
      el.addEventListener('change',()=>{
        document.getElementById('restrictDepartmentDiv').classList.toggle('d-none', !document.getElementById('restrictByDept').checked);
        document.getElementById('restrictCountryDiv').classList.toggle('d-none', !document.getElementById('restrictByCountry').checked);
      });
    });

    // DRAG & DROP SUPPORT
    function handleDrop(event){
      event.preventDefault();
      const files = event.dataTransfer.files;
      if(files.length>0){
        document.getElementById('fileInput').files = files;
      }
    }
    

    // EDIT VISIBILITY
    document.addEventListener('DOMContentLoaded', () => {

    // Run whenever a visibility modal is opened
    $(document).on('shown.bs.modal', '.modal', function () {
      const $modal = $(this);
      if (!this.id.startsWith('editVisibilityModal')) return;         // skip other modals
      const fileId = this.id.replace('editVisibilityModal', '');

      const $vAll  = $modal.find(`#vAll${fileId}`);
      const $vRest = $modal.find(`#vRest${fileId}`);
      const $opt   = $modal.find(`#editRestrictionOptions${fileId}`);

      const toggleRestrictSection = () => {
        if ($vRest.length && $vRest.is(':checked')) $opt.removeClass('d-none');
        else                                        $opt.addClass('d-none');
      };
      // Initial state + radio listeners
      toggleRestrictSection();
      $vAll.on('change', toggleRestrictSection);
      $vRest.on('change', toggleRestrictSection);

      // Department / Country checkboxes
      $modal.find('.restrict-toggle').each(function () {
        const $toggle = $(this);
        const targetId = $toggle.val() === 'department'
          ? `#deptDiv${fileId}` : `#countryDiv${fileId}`;
        const $targetDiv = $modal.find(targetId);

        const showHide = () => {
          $targetDiv.toggleClass('d-none', !$toggle.is(':checked'));
        };
        showHide();                        // initial
        $toggle.on('change', showHide);    // listener
      });
    });

    // Front‑end validation: restricted but no scope selected, and handle loading/success modals
    $('form[action="admin/edit_visibility.php"]').on('submit', function (e) {
      const $form = $(this);
      const fileId = $form.find('input[name="file_id"]').val();
      const restricted = $form.find(`#vRest${fileId}`).is(':checked');
      if (restricted) {
        // For ADMIN, require at least one matrix checkbox selected
        const checkedCount = $form.find('.matrix-checkbox:checked').length;
        if (checkedCount === 0) {
          e.preventDefault();
          $('#countryMessageBody').text('Please choose at least one department or country for restricted access.');
          var countryModal = new bootstrap.Modal($('#countryMessageModal'));
          countryModal.show();
          return;
        }
      }
      // Intercept submit for AJAX, close modal, show loading, then show success modal
      e.preventDefault();
      // Close the edit visibility modal
      var editVisModal = bootstrap.Modal.getInstance(document.getElementById('editVisibilityModal'+fileId));
      if(editVisModal) editVisModal.hide();
      var loadingModal = new bootstrap.Modal($('#loadingModal'));
      loadingModal.show();
      $.post($form.attr('action'), $form.serialize(), function(res){
        loadingModal.hide();
        $('#countryMessageBody').text('Visibility updated successfully.');
        var countryModal = new bootstrap.Modal($('#countryMessageModal'));
        countryModal.show();
        setTimeout(()=>location.reload(), 1200);
      }).fail(function(){
        loadingModal.hide();
        $('#countryMessageBody').text('Visibility update failed.');
        var countryModal = new bootstrap.Modal($('#countryMessageModal'));
        countryModal.show();
      });
    });
  });
  </script>
  <script>
document.addEventListener('DOMContentLoaded', function () {

  // SAFELY escape any string for HTML ID or data attribute use
  function escapeId(str) {
    return str.replace(/\s+/g, '_').replace(/[^\w\-]/g, '');
  }

  // Receive PHP-generated countryDeptMap from server
  const countryDeptMap = <?= json_encode($countryDeptMap) ?>;

  // DOM Elements
  const accessAll = document.getElementById('accessAll');
  const accessRestricted = document.getElementById('accessRestricted');
  const restrictionOptions = document.getElementById('restrictionOptions');
  const dynamicContainer = document.getElementById('dynamicCountryDeptContainer');

  // Renders dropdowns by country and their departments
  function renderCountryDropdowns() {
    dynamicContainer.innerHTML = '';

    Object.entries(countryDeptMap).forEach(([country, departments]) => {
      const safeCountry = escapeId(country);
      const dropdownId = `dropdown-${safeCountry}`;

      let html = `
        <div class="mb-3">
          <div class="dropdown w-100">
            <button class="btn btn-outline-dark dropdown-toggle w-100 text-start" type="button"
                    id="${dropdownId}" data-bs-toggle="dropdown" aria-expanded="false">
              ${country}
            </button>
            <ul class="dropdown-menu w-100 p-2" aria-labelledby="${dropdownId}" style="max-height: 300px; overflow-y: auto;">
              <li>
                <div class="form-check ms-1">
                  <input class="form-check-input select-all-country" type="checkbox"
                         id="selectAll-${safeCountry}" data-country="${country}">
                  <label class="form-check-label fw-bold" for="selectAll-${safeCountry}">Select All</label>
                </div>
              </li>
              <li><hr class="dropdown-divider"></li>
      `;

      departments.forEach(dept => {
        const safeDept = escapeId(dept);
        const inputId = `chk-${safeCountry}-${safeDept}`;
        html += `
          <li>
            <div class="form-check ms-2">
              <input class="form-check-input dept-checkbox" type="checkbox"
                     name="departments[${country}][]" value="${dept}"
                     id="${inputId}" data-country="${country}">
              <label class="form-check-label" for="${inputId}">${dept}</label>
            </div>
          </li>
        `;
      });

      html += '</ul></div></div>';
      dynamicContainer.insertAdjacentHTML('beforeend', html);
    });

    // Add select-all logic
    document.querySelectorAll('.select-all-country').forEach(el => {
      el.addEventListener('change', function () {
        const country = this.dataset.country;
        const checked = this.checked;
        document.querySelectorAll(`.dept-checkbox[data-country="${country}"]`).forEach(cb => {
          cb.checked = checked;
        });
      });
    });

    // Update "select all" when individual checkboxes change
    document.querySelectorAll('.dept-checkbox').forEach(cb => {
      cb.addEventListener('change', function () {
        const country = this.dataset.country;
        const checkboxes = document.querySelectorAll(`.dept-checkbox[data-country="${country}"]`);
        const allChecked = Array.from(checkboxes).every(c => c.checked);
        const selectAll = document.querySelector(`.select-all-country[data-country="${country}"]`);
        if (selectAll) {
          selectAll.checked = allChecked;
        }
      });
    });
  }

  // Toggle restriction block
  function showRestrictionOptions(show) {
    if (restrictionOptions) {
      restrictionOptions.classList.toggle('d-none', !show);
      if (show) renderCountryDropdowns();
    }
  }

  // Event listeners for radio buttons
  if (accessAll) {
    accessAll.addEventListener('change', () => {
      if (accessAll.checked) showRestrictionOptions(false);
    });
  }
  if (accessRestricted) {
    accessRestricted.addEventListener('change', () => {
      if (accessRestricted.checked) showRestrictionOptions(true);
    });
  }

  // Initial state on page load
  showRestrictionOptions(accessRestricted && accessRestricted.checked);

  // Drag and drop support
  window.handleDrop = function(event){
    event.preventDefault();
    const files = event.dataTransfer.files;
    if (files.length > 0) {
      document.getElementById('fileInput').files = files;
    }
  };

  // File upload AJAX
  $('#uploadFileForm').on('submit', function(e){
    e.preventDefault();
    const uploadModalInstance = bootstrap.Modal.getInstance($('#uploadFileModal'));
    if(uploadModalInstance) uploadModalInstance.hide();

    const loadingModal = new bootstrap.Modal($('#loadingModal'));
    loadingModal.show();

    const formData = new FormData(this);
    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(res){
        loadingModal.hide();
        $('#countryMessageBody').text('File uploaded successfully.');
        const countryModal = new bootstrap.Modal($('#countryMessageModal'));
        countryModal.show();
        setTimeout(()=>location.reload(), 1200);
      },
      error: function(){
        loadingModal.hide();
        $('#countryMessageBody').text('Upload failed.');
        const countryModal = new bootstrap.Modal($('#countryMessageModal'));
        countryModal.show();
      }
    });
  });
    // Show/hide matrix UI based on radio selection
    function toggleRestrictionMatrix(show) {
      const matrixBlock = document.getElementById('restrictionOptions');
      if (matrixBlock) {
        matrixBlock.classList.toggle('d-none', !show);
      }
    }

    if (accessAll) {
      accessAll.addEventListener('change', () => {
        if (accessAll.checked) toggleRestrictionMatrix(false);
      });
    }
    if (accessRestricted) {
      accessRestricted.addEventListener('change', () => {
        if (accessRestricted.checked) toggleRestrictionMatrix(true);
      });
    }

    // On page load
    toggleRestrictionMatrix(accessRestricted && accessRestricted.checked);

    // Handle 'Select All' per country column
    document.querySelectorAll('.select-column').forEach(checkbox => {
      checkbox.addEventListener('change', function () {
        const country = this.dataset.country;
        const boxes = document.querySelectorAll(`.matrix-checkbox.country-${CSS.escape(country)}`);
        boxes.forEach(cb => cb.checked = this.checked);
    });
  });


});
</script>  <!-- Session Timeout -->
  <script src="js/inactivity.js"></script>
</body>
</html>
