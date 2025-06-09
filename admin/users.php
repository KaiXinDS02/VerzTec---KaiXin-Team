<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
// Upload File Script
$conn = new mysqli("db", "user", "password", "Verztec");

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
    <base href="../">
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
                        <a href="home.php">
                            <img src="images/logo.png" alt="">
                        </a>
                    </div>
                </div>
                <div class="col-xl-6 col-md-5 order-3 order-md-2 d-flex justify-content-center justify-content-md-start">
                    <div class="page-menu-wp">
                        <ul>
                            <li><a href="home.php">Home</a></li>
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




    <!-- Action Buttons Section -->
    <section class="py-4">
        <div class="container d-flex justify-content-end">
            <!-- Add User Button -->
            <button class="btn btn-dark rounded-2 px-4 py-2 me-2 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fa fa-user-plus me-2"></i> Add User
            </button>
            <!-- Add User Button -->

            <!-- Upload File Button -->
            <form method="POST" enctype="multipart/form-data">
                <label for="excel_file" class="btn btn-dark rounded-2 px-4 py-2 d-flex align-items-center" style="cursor: pointer;">
                    <i class="fa fa-upload me-2"></i> Upload File
                </label>
                <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx" onchange="this.form.submit()" style="display: none;">
            </form>
            <!-- Upload File Button -->
        </div>
    </section>
    <!-- Action Buttons Section -->
    

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <form id="addUserForm">
            <div class="modal-header">
            <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" class="form-control" id="add-username" name="username" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" class="form-control" id="add-password" name="password" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" class="form-control" id="add-email" name="email" required>
            </div>
            <div class="mb-3">
                <label>Department</label>
                <input type="text" class="form-control" id="add-department" name="department" required>
            </div>
            <div class="mb-3">
                <label>Role</label>
                <select class="form-select" id="add-role" name="role" required>
                <option value="ADMIN">ADMIN</option>
                <option value="MANAGER">MANAGER</option>
                <option value="USER" selected>USER</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Country</label>
                <input type="text" class="form-control" id="add-country" name="country" required>
            </div>
            </div>
            <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Add User</button>
            </div>
        </form>
        </div>
    </div>
    </div>
    <!-- Add User Modal -->





    <!-- Display User Data Section -->
        <section class="py-4">
        <div class="container">
            <h4 class="mb-3">Users</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <!-- Table Headers -->
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Country</th>
                            <th> </th> 
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



    <!-- Edit User Pop-Up -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <form id="editUserForm">
            <div class="modal-header">
            <h5 class="modal-title">Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <input type="hidden" id="edit-user-id" name="user_id">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" class="form-control" id="edit-username" name="username" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="edit-password" name="password" disabled readonly>
                    <button type="button" class="btn btn-outline-secondary" id="reset-password-btn">Reset</button>
                </div>
                <small class="form-text text-muted">Click reset to change password.</small>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" class="form-control" id="edit-email" name="email" required>
            </div>
            <div class="mb-3">
                <label>Department</label>
                <input type="text" class="form-control" id="edit-department" name="department" required>
            </div>
            <div class="mb-3">
                <label>Role</label>
                <select class="form-control" id="edit-role" name="role" required>
                    <option value="ADMIN">ADMIN</option>
                    <option value="MANAGER">MANAGER</option>
                    <option value="USER">USER</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Country</label>
                <input type="text" class="form-control" id="edit-country" name="country" required>
            </div>
            </div>
            <div class="modal-footer">
            <button type="submit" class="btn btn-dark">Save Changes</button>
            </div>
        </form>
        </div>
    </div>
    </div>
    <!-- Edit User Pop-Up -->





    <!-- Scripts -->
    <script src="js/jquery-3.4.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    
    <!-- Add User Pop-Up -->
    <script>
    document.getElementById('addUserForm').addEventListener('submit', function (e) {
        e.preventDefault(); // Stop form from submitting normally

        const requiredFields = ['username', 'password', 'email', 'department', 'role', 'country'];
        let isValid = true;

        // Validate fields
        requiredFields.forEach(field => {
            const input = document.getElementById(`add-${field}`);
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!isValid) return;

        // Send AJAX request
        const formData = new FormData(this);
        fetch('admin/add_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === "success") {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                modal.hide();

                // Optional: Reset form
                this.reset();
                document.getElementById('add-role').value = "USER";

                // Refresh page to show new user in table
                location.reload();
            } else {
                alert("Error: " + data); // Fallback alert in case something fails
            }
        })
        .catch(error => {
            alert("Request failed: " + error.message);
        });
    });
    </script>


    <!-- Load users via AJAX -->
    <script>
        $(document).ready(function () {
            // Load user data initially
            $("#user-data-body").load("admin/fetch_users.php", function () {
                attachDeleteHandlers(); // Attach delete buttons after loading
            });

            // Function to attach delete button event listeners
            function attachDeleteHandlers() {
                $(".delete-user").off("click").on("click", function (e) {
                    e.preventDefault();

                    const userId = $(this).data("userid");

                    // Show one confirmation prompt
                    if (confirm("Are you sure you want to delete this user?")) {
                        $.ajax({
                            url: "admin/delete_users.php",
                            type: "POST",
                            data: { user_id: userId },
                            success: function (response) {
                                if (response.trim() === "success") {
                                    alert("User deleted successfully.");
                                    $("#user-data-body").load("admin/fetch_users.php", function () {
                                        attachDeleteHandlers(); // Reattach after reload
                                    });
                                } else {
                                    alert("Failed to delete user: " + response);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.log(xhr.responseText);
                                alert("Error while sending delete request.");
                            }
                        });
                    }
                });
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            // Open modal and fill form
            $(document).on("click", ".edit-user", function () {
                const user = $(this).data();
                $("#edit-user-id").val(user.userid);
                $("#edit-username").val(user.username);
                $("#edit-password").val("•••••••••").prop("disabled", true).prop("readonly", true);
                $("#edit-email").val(user.email);
                $("#edit-department").val(user.department);
                $("#edit-role").val(user.role);
                $("#edit-country").val(user.country);
                $("#editUserModal").modal("show");
            });

            // Handle password reset button
            $(document).on("click", "#reset-password-btn", function () {
                const $pwd = $("#edit-password");
                $pwd.prop("disabled", false).prop("readonly", false).val("").focus();
            });

            // Handle edit form submit
            $("#editUserForm").on("submit", function (e) {
                e.preventDefault();

                // Disable password input before submission if unchanged
                const $pwd = $("#edit-password");
                if ($pwd.prop("disabled")) {
                    $pwd.prop("disabled", false); // Temporarily enable so it's included
                    $pwd.val(""); // Clear to indicate no change
                }

                const formData = $(this).serialize();

                $.ajax({
                    url: "admin/update_user.php",
                    method: "POST",
                    data: formData,
                    success: function (response) {
                        response = response.trim();
                        if (response === "success") {
                            alert("User updated successfully.");
                            $("#editUserModal").modal("hide");
                            $("#user-data-body").load("admin/fetch_users.php");
                        } else {
                            alert("Update failed: " + response);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("AJAX error: " + error);
                    }
                });
            });
        });
    </script>




</body>
</html>
