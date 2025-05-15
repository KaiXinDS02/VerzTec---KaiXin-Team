<!-- Database Connection -->
<?php
session_start();
include('connect.php'); 

$error = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$username = $_POST['username'];
	$password = $_POST['password'];

    // Prepare and execute the SQL query
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['username'] = $user['username'];
        header("Location: user.html");
        exit(); 
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}
?>
<!-- Database Connection -->




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
	</head>
	<body>

		
		
		<!-- login form area -->
		<main class="login-wrap bg-included">
			<div class="login-form">
				<form action="login.php" method="POST">
					<div class="login-logo px-4">
						<a href="#">
							<img src="images/logo.png" alt="">
						</a>
					</div>

					<?php if (!empty($error)): ?>
                		<p style="color:red; text-align:center;"><?php echo $error; ?></p>
            		<?php endif; ?>

					<div class="single-input pb-3 pb-md-4">
						<label for="a111">Username</label>
						<input id="a111" type="text" name="username" required>
					</div>
					<div class="single-input">
						<label for="a222">Password</label>
						<input id="a222" type="password" name="password" required>
					</div>
					<div class="forgot-password text-end pt-2">
						<a href="#">Forgot Password?</a>
					</div>
					<div class="submit-btn">
						<button type="submit">Login</button>
					</div>
				</form>
			</div>
		</main>
		<!-- login form area  -->



		
		
		<!-- Main jQuery -->
		<script src="js/jquery-3.4.1.min.js"></script>
		
		<!-- Bootstrap.bundle Script -->
		<script src="js/bootstrap.bundle.min.js"></script>
		
		<!-- Custom jQuery -->
		<script src="js/scripts.js"></script>
		
	</body>
</html>