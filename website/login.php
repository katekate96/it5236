<?php

// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

// Declare a set of variables to hold the username and password for the user
$username = "";
$password = "";

// Declare an empty array of error messages
$errors = array();

// If someone has clicked their email validation link, then process the request
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

	if (isset($_GET['id'])) {
		
		$success = $app->processEmailValidation($_GET['id'], $errors);
		if ($success) {
			$message = "Email address validated. You may login.";
		}

	}

}

// If someone is attempting to login, process their request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	// Pull the username and password from the <form> POST
	$username = $_POST['username'];
	$password = $_POST['password'];

	// Attempt to login the user and capture the result flag
	$result = $app->login($username, $password, $errors);
	
	// Check to see if the login attempt succeeded
	if ($result == TRUE) {	
		
		// Redirect the user to the topics page on success
		header("Location: otp.php");
		exit();

	}

}

if (isset($_GET['register']) && $_GET['register']== 'success') {
	$message = "Registration successful. Please check your email. A message has been sent to validate your address.";
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

<!--1. Display Errors if any exists 
	2. Display Login form (sticky):  Username and Password -->

<body>
	<?php include 'include/header.php'; ?>

	<h2>Login</h2>

	<?php include('include/messages.php'); ?>
	
	<div>
		<form method="post" action="login.php">
			
			<input type="text" name="username" onclick="doPageLoad()" id="username" placeholder="Username" value="<?php echo $username; ?>" />
			<br/>

			<input type="password" name="password" id="password" placeholder="Password" value="<?php echo $password; ?>" />
			<br/>
			
			<input type="checkbox" onclick="doSubmit()" id="saveLocal">Remember Username</input>
			<br/>

			<input  type="submit" value="Login" name="login" />
		</form>
		
<script>
	function doSubmit() {
		var saveLocal = document.getElementById("saveLocal").checked;
		if (saveLocal) {
			console.log("Saving username to local storage");
			var username = document.getElementById("username").value;
			localStorage.setItem("username", username);
			sessionStorage.removeItem("username");
		}	
	}	
	
	function doPageLoad() {
		console.log("Reading username from local/session storage");
		var usernameLocal = localStorage.getItem("username");
		if (usernameLocal) {
			document.getElementById("saveLocal").checked = true;
			document.getElementById("username").value = usernameLocal;
		}	
	}	
	

</script>
		
	</div>
	<a href="register.php">Need to create an account?</a>
	<br/>
	<a href="reset.php">Forgot your password?</a>
	<?php include 'include/footer.php'; ?>
	<script src="js/site.js"></script>
</body>
</html>
