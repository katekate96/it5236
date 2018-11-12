<?php
	
// Import the application classes
require_once('include/classes.php');

// Create an instance of the Application class
$app = new Application();
$app->setup();

// Declare an empty array of error messages
$errors = array();

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
	<p>
		Welcome to Up, Out, & About Travel! This is a discussion board to gain advice about popular travel destinations. You can <a href="login.php">create an account</a> or proceed directly to the 
		<a href="login.php">login page</a>.
	</p>
	<?php include 'include/footer.php'; ?>
	<script src="js/site.js"></script>
</body>
</html>
