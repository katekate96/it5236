<?php

// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

$errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	// Grab or initialize the input values
	$usernameOrEmail = $_POST['usernameOrEmail'];

	// Request a password reset email message
	$app->passwordReset($usernameOrEmail, $errors);
	
	$message = "An email has been sent to the specified account, if it exists. Please check your spam folder.";

}

?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>upoutandabouttravel.com</title>
	<meta name="description" content="Up, Out, and About Travel Discussion Site">
	<meta name="author" content="Katelyn Greer">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
	<?php include 'include/header.php'; ?>
	<main id="wrapper">
		<h2>Reset Password</h2>
		<?php include('include/messages.php'); ?>
		<form method="post" action="reset.php">
			<input type="text" name="usernameOrEmail" id="usernameOrEmail" placeholder="Enter your username or email address" required="required" size="40" />
			<input type="submit" value="Submit" />
		</form>
		<a href="register.php">Need to create an account?</a>
	</main>
	<?php include 'include/footer.php'; ?>
	<script src="js/site.js"></script>
</body>
</html>
