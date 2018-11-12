<?php

	// Assume the user is not logged in and not an admin
	$isadmin = FALSE;
	$loggedin = FALSE;
	
	// If we have a session ID cookie, we might have a session
	if (isset($_COOKIE['sessionid'])) {
		
		$user = $app->getSessionUser($errors); 
		$loggedinuserid = $user["userid"];

		// Check to see if the user really is logged in and really is an admin
		if ($loggedinuserid != NULL) {
			$loggedin = TRUE;
			$isadmin = $app->isAdmin($errors, $loggedinuserid);
		}

	} else {
		
		$loggedinuserid = NULL;

	}


?>

<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #e3f2fd;">
	<a class="navbar-brand" href="index.php"><img src="images/travel2.png" alt="Travel Board Logo" height="190px"></a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav">
      <li class="nav-item active">
        <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
      </li>
	  <?php if (!$loggedin) { ?>
		  <li class="nav-item">
			<a class="nav-link" href="login.php">Login</a>
		  </li>
		  <li class="nav-item">
			<a class="nav-link" href="register.php">Register</a>
		  </li>
	  <?php } ?>
	  <?php if ($loggedin) { ?>
		  <li class="nav-item">
			<a class="nav-link disabled" href="list.php">Destinations</a>
		  </li>
		  <li class="nav-item">
			<a class="nav-link disabled" href="editprofile.php">Profile</a>
		  </li>
		  <?php if ($isadmin) { ?>
			<li class="nav-item">
				<a class="nav-link disabled" href="admin.php">Admin</a>
			</li>
		  <?php } ?>
		  <li class="nav-item">
			<a class="nav-link disabled" href="fileviewer.php?file=include/help.txt">Help</a>
		  </li>
		  <li class="nav-item">
			<a class="nav-link disabled" href="logout.php">Logout</a>
		  </li>
	  <?php } ?>
    </ul>
  </div>
</nav>



