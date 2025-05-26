<!-- Upload File Script -->
<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$conn = new mysqli("localhost", "root", "", "login");

$message = ""; // To hold success or error message

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file']['tmp_name'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            $name = $conn->real_escape_string($row[0]);
            $password = password_hash($row[1], PASSWORD_DEFAULT);
            $email = $conn->real_escape_string($row[2]);
            $department = $conn->real_escape_string($row[3]);
            $role = $conn->real_escape_string($row[4]);
            $country = $conn->real_escape_string($row[5]);

            $sql = "INSERT INTO users (username, password, email, department, role, country)
                    VALUES ('$name', '$password', '$email', '$department', '$role', '$country')";
            $conn->query($sql);
        }

        $message = "✅ Import completed.";
    } catch (Exception $e) {
        $message = "❌ Import failed: " . $e->getMessage();
    }
}
?>
<!-- Upload File Script -->







<!DOCTYPE html>
<html lang="en-US">
<head>
    <!-- Meta setup -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="keywords" content="">
    <meta name="decription" content="">
    <!-- Title -->
    <title>Verztec</title>
    <!-- Fav Icon -->
    <link rel="icon" href="images/favicon.ico">	
    <!-- Include Bootstrap -->
    <link rel="stylesheet" href="css/bootstrap.css">
    <!-- link font awesome -->
    <link rel="stylesheet" href="css/font-awesome.css">
    <!-- Main StyleSheet -->
    <link rel="stylesheet" href="style.css">	
    <!-- Responsive CSS -->
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        /* Add padding to the body below the header */
        body {
            padding-top: 110px; /* Adjust based on your header height */
            background-color: #f2f3fa;
        }
    </style>
</head>
<body>

    <!-- page header area -->
    <header class="header-area" style="position: fixed; top: 0; left: 0; width: 100%; z-index: 999; background: white;">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-xl-3 col-md-4 col-6">
                    <div class="page-logo-wp">
                        <a href="user home.html">
                            <img src="images/logo.png" alt="">
                        </a>
                    </div>
                </div>
                <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
                    <div class="page-menu-wp">
                        <ul>
                            <li><a href="user home.html">Home</a></li>
                            <li><a href="chatbot.html">Chatbot</a></li>
                            <li><a href="files.html">Files</a></li>
                            <li class = "active"><a href="#">Admin</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3 col-6 d-flex justify-content-end order-2 order-md-3">
                    <div class="page-user-icon profile">
                        <button>
                            <img src="images/Profile-Icon.svg" alt="">
                        </button>
                        <div class="menu">
                            <ul>
                                <li><a href="#"><i class="fa-regular fa-user"></i><span>Profile</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-message-smile"></i><span>Inbox</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-gear"></i><span>Settings</span></a></li>
                                <li><a href="#"><i class="fa-regular fa-square-question"></i><span>Help</span></a></li>
                                <li><a href="login.php"><i class="fa-regular fa-right-from-bracket"></i><span>Sign Out</span></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- page header area -->

    <!-- Upload File Button Section -->
    <section class="py-4">
        <div class="container d-flex justify-content-end">
            <form method="POST" enctype="multipart/form-data">
                <label for="excel_file" class="btn btn-primary rounded-pill px-4 py-2 d-flex align-items-center" style="cursor: pointer;">
                    <i class="fa fa-upload me-2"></i> Upload File
                </label>
                <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx" onchange="this.form.submit()" style="display: none;">
            </form>
        </div>
    </section>

    <!-- Upload File Button Section -->
        <section class="py-4">
        <div class="container">
            <h4 class="mb-3">Users</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <!-- Table Headers -->
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Country</th>
                        </tr>
                    </thead>
                    <!-- Empty tbody to be filled by AJAX -->
                    <tbody id="user-data-body">
                        <!-- User rows will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <!-- End Display User Data Section -->



    <!-- Alert Box -->
    <?php if (!empty($message)) : ?>
        <script>
            window.onload = function() {
                alert("<?php echo $message; ?>");
            };
        </script>
    <?php endif; ?>


    <!-- Scripts -->
    <script src="js/jquery-3.4.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>

    <!-- Load users via AJAX -->
    <script>
        $(document).ready(function() {
            $("#user-data-body").load("fetch_users.php");
        });
 
    </script>
</body>
</html>
