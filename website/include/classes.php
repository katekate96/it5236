<?php

if (file_exists(getcwd() . "/include/credentials.php")) {
    require('credentials.php');
} else {
    echo "Application has not been configured. Copy and edit the credentials-sample.php file to credentials.php.";
    exit();
}

class Application {
    
    public $debugMessages = [];
    
    public function setup() {
        
        // Check to see if the client has a cookie called "debug" with a value of "true"
        // If it does, turn on error reporting
        if ($_COOKIE['debug'] == "true") {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
    }
    
    // Writes a message to the debug message array for printing in the footer.
    public function debug($message) {
        $this->debugMessages[] = $message;
    }
    
    // Creates a database connection
    protected function getConnection() {
        
        // Import the database credentials
        $credentials = new Credentials();
        
        // Create the connection
        try {
            $dbh = new PDO("mysql:host=$credentials->servername;dbname=$credentials->serverdb", $credentials->serverusername, $credentials->serverpassword);
        } catch (PDOException $e) {
            print "Error connecting to the database.";
            die();
        }
        
        // Return the newly created connection
        return $dbh;
    }
    
    public function auditlog($context, $message, $priority = 0, $userid = NULL){
        
        // Declare an errors array
        $errors = [];
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        // If a user is logged in, get their userid
        if ($userid == NULL) {
            
            $user = $this->getSessionUser($errors, TRUE);
            if ($user != NULL) {
                $userid = $user["userid"];
            }
            
        }
        
        $ipaddress = $_SERVER["REMOTE_ADDR"];
        
        if (is_array($message)){
            $message = implode( ",", $message);
        }
        
        // Construct a SQL statement to perform the insert operation
        $sql = "INSERT INTO auditlog (context, message, logdate, ipaddress, userid) " .
            "VALUES (:context, :message, NOW(), :ipaddress, :userid)";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":context", $context);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":ipaddress", $ipaddress);
        $stmt->bindParam(":userid", $userid);
        $stmt->execute();
        $dbh = NULL;
        
    }
    
    protected function validateUsername($username, &$errors) {
        if (empty($username)) {
            $errors[] = "Missing username";
        } else if (strlen(trim($username)) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } else if (strpos($username, "@")) {
            $errors[] = "Username may not contain an '@' sign";
        }
    }
    
    protected function validatePassword($password, &$errors) {
        if (empty($password)) {
            $errors[] = "Missing password";
        } else if (strlen(trim($password)) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
    }
    
    protected function validateEmail($email, &$errors) {
        if (empty($email)) {
            $errors[] = "Missing email";
        } 
    }
    
    
    // Registers a new user
    public function register($username, $password, $email, $registrationcode, &$errors) {
        
        $this->auditlog("register", "attempt: $username, $email, $registrationcode");
        
        // Validate the user input
        $this->validateUsername($username, $errors);
        $this->validatePassword($password, $errors);
        $this->validateEmail($email, $errors);
        if (empty($registrationcode)) {
            $errors[] = "Missing registration code";
        }
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
            // Hash the user's password
            $passwordhash = password_hash($password, PASSWORD_DEFAULT);
            
            // Create a new user ID
            $userid = bin2hex(random_bytes(16));

			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/registeruser";
			$data = array(
				'userid'=>$userid,
				'username'=>$username,
				'passwordHash'=>$passwordhash,
				'email'=>$email,
				'registrationcode'=>$registrationcode
			);
			$data_json = json_encode($data);
			$apiKey = 'wyKMqDIluT5ehCL3SIiqP82NGQBXX5ZO8wqNHvg0';
			$headers = array('Authorization: '.$apiKey);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json), $headers));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {

					 //$this->sendValidationEmail($userid, $email, $errors);

				}

			}
			
			curl_close($ch);

        } else {
            $this->auditlog("register validation error", $errors);
        }
        
        // Return TRUE if there are no errors, otherwise return FALSE
        if (sizeof($errors) == 0){
            return TRUE;
        } else {
            return FALSE;
        }
    }
	
	/*
	protected function sendValidationEmail($userid, $email, &$errors) {

      $url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/sendValidationEmail";
      $data = array(
        'emailvalidationid'=>$emailvalidationid,
        'userid'=>$userid,
        'email'=>$email,
        'emailsent'=>$emailsent
      );
      $data_json = json_encode($data);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('x-api-key  : ...'));
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response  = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($response === FALSE) {
        $errors[] = "An unexpected failure occurred contacting the web service.";
      } else {

        if($httpCode == 400) {

          // JSON was double-encoded, so it needs to be double decoded
          $errorsList = json_decode(json_decode($response))->errors;
          foreach ($errorsList as $err) {
            $errors[] = $err;
          }
          if (sizeof($errors) == 0) {
            $errors[] = "Bad input";
          }

        } else if($httpCode == 500) {

          $errorsList = json_decode(json_decode($response))->errors;
          foreach ($errorsList as $err) {
            $errors[] = $err;
          }
          if (sizeof($errors) == 0) {
            $errors[] = "Server error";
          }

        } else if($httpCode == 200) {

          // $this->sendValidationEmail($userid, $email, $errors);


            $this->auditlog("sendValidationEmail", "Sending message to $email");

            // Send reset email
            $pageLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $pageLink = str_replace("register.php", "login.php", $pageLink);
            $to      = $email;
            $subject = 'Confirm your email address';
            $message = "A request has been made to create an account at https://upoutandabouttravel.com for this email address. ".
                "If you did not make this request, please ignore this message. No other action is necessary. ".
                "To confirm this address, please click the following link: $pageLink?id=$validationid";
            $headers = 'From: webmaster@upoutandabouttravel.com' . "\r\n" .
                'Reply-To: webmaster@upoutandabouttravel.com' . "\r\n";

            mail($to, $subject, $message, $headers);

            $this->auditlog("sendValidationEmail", "Message sent to $email");

        }

        curl_close($ch);
    }
  }
	*/
	
    // Send an email to validate the address
    protected function sendValidationEmail($userid, $email, &$errors) {
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        $this->auditlog("sendValidationEmail", "Sending message to $email");
        
        $validationid = bin2hex(random_bytes(16));
        
        // Construct a SQL statement to perform the insert operation
        $sql = "INSERT INTO emailvalidation (emailvalidationid, userid, email, emailsent) " .
            "VALUES (:emailvalidationid, :userid, :email, NOW())";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":emailvalidationid", $validationid);
        $stmt->bindParam(":userid", $userid);
        $stmt->bindParam(":email", $email);
        $result = $stmt->execute();
        if ($result === FALSE) {
            $errors[] = "An unexpected error occurred sending the validation email";
            $this->debug($stmt->errorInfo());
            $this->auditlog("register error", $stmt->errorInfo());
        } else {
            
            $this->auditlog("sendValidationEmail", "Sending message to $email");
            
            // Send reset email
            $pageLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $pageLink = str_replace("register.php", "login.php", $pageLink);
            $to      = $email;
            $subject = 'Confirm your email address';
            $message = "A request has been made to create an account at https://upoutandabouttravel.comfor this email address. ".
                "If you did not make this request, please ignore this message. No other action is necessary. ".
                "To confirm this address, please click the following link: $pageLink?id=$validationid";
            $headers = 'From: webmaster@upoutandabouttravel.com' . "\r\n" .
                'Reply-To: webmaster@upoutandabouttravel.com' . "\r\n";
            
            mail($to, $subject, $message, $headers);
            
            $this->auditlog("sendValidationEmail", "Message sent to $email");
            
        }
        
        // Close the connection
        $dbh = NULL;
        
    }
	
	// NEW FOR OTP
    protected function sendOTP($userid, $email, &$errors) {
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        $this->auditlog("sendValidationEmail", "Sending message to $email");
        
        $validationid = bin2hex(random_bytes(3));
        
        // Construct a SQL statement to perform the insert operation
        $sql = "INSERT INTO emailvalidation (emailvalidationid, userid, email, emailsent) " .
            "VALUES (:emailvalidationid, :userid, :email, NOW())";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":emailvalidationid", $validationid);
        $stmt->bindParam(":userid", $userid);
        $stmt->bindParam(":email", $email);
        $result = $stmt->execute();
        if ($result === FALSE) {
            $errors[] = "An unexpected error occurred sending the validation email";
            $this->debug($stmt->errorInfo());
            $this->auditlog("register error", $stmt->errorInfo());
        } else {
            
            $this->auditlog("sendValidationEmail", "Sending message to $email");
            
            // Send reset email
            $pageLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $pageLink = str_replace("login.php", "otp.php", $pageLink);
            $to      = $email;
            $subject = 'Confirm your email address';
            $message = "Your One Time Password is: $validationid";
            $headers = 'From: webmaster@uoatravel.com' . "\r\n" .
                'Reply-To: webmaster@uoatravel.com' . "\r\n";
            
            mail($to, $subject, $message, $headers);
            
            $this->auditlog("sendValidationEmail", "Message sent to $email");
            
        }
        
        // Close the connection
        $dbh = NULL;
        
    }
    
    // Send an email to validate the address
    public function processEmailValidation($validationid, &$errors) {
        
        $success = FALSE;
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        $this->auditlog("processEmailValidation", "Received: $validationid");
        
        // Construct a SQL statement to perform the insert operation
        $sql = "SELECT userid FROM emailvalidation WHERE emailvalidationid = :emailvalidationid";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":emailvalidationid", $validationid);
        $result = $stmt->execute();
        
        if ($result === FALSE) {
            
            $errors[] = "An unexpected error occurred processing your email validation request";
            $this->debug($stmt->errorInfo());
            $this->auditlog("processEmailValidation error", $stmt->errorInfo());
            
        } else {
            
            if ($stmt->rowCount() != 1) {
                
                $errors[] = "That does not appear to be a valid request";
                $this->debug($stmt->errorInfo());
                $this->auditlog("processEmailValidation", "Invalid request: $validationid");
                
                
            } else {
                
                $userid = $stmt->fetch(PDO::FETCH_ASSOC)['userid'];
                
                // Construct a SQL statement to perform the insert operation
                $sql = "DELETE FROM emailvalidation WHERE emailvalidationid = :emailvalidationid";
                
                // Run the SQL select and capture the result code
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(":emailvalidationid", $validationid);
                $result = $stmt->execute();
                
                if ($result === FALSE) {
                    
                    $errors[] = "An unexpected error occurred processing your email validation request";
                    $this->debug($stmt->errorInfo());
                    $this->auditlog("processEmailValidation error", $stmt->errorInfo());
                    
                } else if ($stmt->rowCount() == 1) {
                    
                    $this->auditlog("processEmailValidation", "Email address validated: $validationid");
                    
                    // Construct a SQL statement to perform the insert operation
                    $sql = "UPDATE users SET emailvalidated = 1 WHERE userid = :userid";
                    
                    // Run the SQL select and capture the result code
                    $stmt = $dbh->prepare($sql);
                    $stmt->bindParam(":userid", $userid);
                    $result = $stmt->execute();
                    
                    $success = TRUE;
                    
                } else {
                    
                    $errors[] = "That does not appear to be a valid request";
                    $this->debug($stmt->errorInfo());
                    $this->auditlog("processEmailValidation", "Invalid request: $validationid");
                    
                }
                
            }
            
        }
        
        
        // Close the connection
        $dbh = NULL;
        
        return $success;
        
    }
	
	// PROCESS OTP
    public function processOTP($validationid, &$errors) {
        
        $success = FALSE;
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        $this->auditlog("processEmailValidation", "Received: $validationid");
        
        // Construct a SQL statement to perform the insert operation
        $sql = "SELECT userid FROM emailvalidation WHERE emailvalidationid = :emailvalidationid";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":emailvalidationid", $validationid);
        $result = $stmt->execute();
        
        if ($result === FALSE) {
            
            $errors[] = "An unexpected error occurred processing your email validation request";
            $this->debug($stmt->errorInfo());
            $this->auditlog("processEmailValidation error", $stmt->errorInfo());
            
        } else {
            
            if ($stmt->rowCount() != 1) {
                
                $errors[] = "That does not appear to be a valid request";
                $this->debug($stmt->errorInfo());
                $this->auditlog("processEmailValidation", "Invalid request: $validationid");
                
                
            } else {
                
                $userid = $stmt->fetch(PDO::FETCH_ASSOC)['userid'];
                
                // Construct a SQL statement to perform the insert operation
                $sql = "DELETE FROM emailvalidation WHERE emailvalidationid = :emailvalidationid";
                
                // Run the SQL select and capture the result code
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(":emailvalidationid", $validationid);
                $result = $stmt->execute();
                
                if ($result === FALSE) {
                    
                    $errors[] = "An unexpected error occurred processing your email validation request";
                    $this->debug($stmt->errorInfo());
                    $this->auditlog("processEmailValidation error", $stmt->errorInfo());
                    
                } else if ($stmt->rowCount() == 1) {
                    
                    $this->auditlog("processEmailValidation", "Email address validated: $validationid");
					
					$this->newSession($userid);
                    
                    $success = TRUE;
                    
                } else {
                    
                    $errors[] = "That does not appear to be a valid request";
                    $this->debug($stmt->errorInfo());
                    $this->auditlog("processEmailValidation", "Invalid request: $validationid");
                    
                }
                
            }
            
        }
        
        
        // Close the connection
        $dbh = NULL;
        
        return $success;
        
    }
	/*
	public function newSession($userid, &$errors) {
        
        $this->auditlog("newSession", "attempt: $usersessionid, $userid");
        
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
           // Create a new session ID
            $sessionid = bin2hex(random_bytes(25));
			
			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/newSession";
			$data = array(
				'usersessionid'=>$usersessionid,
				'userid'=>$userid,
				'expires'=>$expires,
				'registrationcode'=>$registrationcode
			);
			$data_json = json_encode($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->debug($response);
			$this->auditlog("addThing", "response = : $response");

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {
					// Store the session ID as a cookie in the browser
					setcookie('sessionid', $sessionid, time()+60*60*24*30);
					$this->auditlog("session", "new session id: $sessionid for user = $userid");

				}

			}
			
			curl_close($ch);
			return $usersessionid;

        } 
    }
	*/
    
    // Creates a new session in the database for the specified user
    public function newSession($userid, &$errors, $registrationcode = NULL) {
        
        // Check for a valid userid
        if (empty($userid)) {
            $errors[] = "Missing userid";
            $this->auditlog("session", "missing userid");
        }
        
        // Only try to query the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
            if ($registrationcode == NULL) {
                $regs = $this->getUserRegistrations($userid, $errors);
                $reg = $regs[0];
                $this->auditlog("session", "logging in user with first reg code $reg");
                $registrationcode = $regs[0];
            }
            
            // Create a new session ID
            $sessionid = bin2hex(random_bytes(25));
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Construct a SQL statement to perform the insert operation
            $sql = "INSERT INTO usersessions (usersessionid, userid, expires, registrationcode) " .
                "VALUES (:sessionid, :userid, DATE_ADD(NOW(), INTERVAL 7 DAY), :registrationcode)";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":sessionid", $sessionid);
            $stmt->bindParam(":userid", $userid);
            $stmt->bindParam(":registrationcode", $registrationcode);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                
                $errors[] = "An unexpected error occurred";
                $this->debug($stmt->errorInfo());
                $this->auditlog("new session error", $stmt->errorInfo());
                return NULL;
                
            } else {
                
                // Store the session ID as a cookie in the browser
                setcookie('sessionid', $sessionid, time()+60*60*24*30);
                $this->auditlog("session", "new session id: $sessionid for user = $userid");
                
                // Return the session ID
                return $sessionid;
                
            }
            
        }
        
    }
    
	
	public function getUserRegistrations($userid, &$errors) {
        
        // Assume an empty list of regs
        $regs = array();
        
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/userregistrations?userid=".$userid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("userregistrations", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getUserRegistrations", "web service response => " . $response);
				$regs = json_decode($response)->userregistrations;
		        $this->auditlog("getUserRegistrations", "success");
			}
		}
		
		curl_close($ch);
        // Return the list of users
        return $regs;
    }

	
    // Updates a single user in the database and will return the $errors array listing any errors encountered
    public function updateUserPassword($userid, $password, &$errors) {
        
        // Validate the user input
        if (empty($userid)) {
            $errors[] = "Missing userid";
        }
        $this->validatePassword($password, $errors);
        
        if(sizeof($errors) == 0) {
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Hash the user's password
            $passwordhash = password_hash($password, PASSWORD_DEFAULT);
            
            // Construct a SQL statement to perform the select operation
            $sql = "UPDATE users SET passwordhash=:passwordhash " .
                "WHERE userid = :userid";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":passwordhash", $passwordhash);
            $stmt->bindParam(":userid", $userid);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                $errors[] = "An unexpected error occurred supdating the password.";
                $this->debug($stmt->errorInfo());
                $this->auditlog("updateUserPassword error", $stmt->errorInfo());
            } else {
                $this->auditlog("updateUserPassword", "success");
            }
            
            // Close the connection
            $dbh = NULL;
            
        } else {
            
            $this->auditlog("updateUserPassword validation error", $errors);
            
        }
        
        // Return TRUE if there are no errors, otherwise return FALSE
        if (sizeof($errors) == 0){
            return TRUE;
        } else {
            return FALSE;
        }
    }
	
    public function clearPasswordResetRecords() {
		
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/clearPasswordResetRecords?passwordresetid=" . $passwordresetid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//$this->debug($response);
		$this->auditlog("delete passwordresetid", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
                $this->auditlog("delete passwordresetid", "successful: $passwordresetid");
			}
		}
		
		curl_close($ch);
    }
	
    // Retrieves an existing session from the database for the specified user
    public function getSessionUser(&$errors, $suppressLog=FALSE) {
        
        // Get the session id cookie from the browser
        $sessionid = NULL;
        $user = NULL;
        
        // Check for a valid session ID
        if (isset($_COOKIE['sessionid'])) {
            
            $sessionid = $_COOKIE['sessionid'];
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Construct a SQL statement to perform the insert operation
            $sql = "SELECT usersessionid, usersessions.userid, email, username, usersessions.registrationcode, isadmin " .
                "FROM usersessions " .
                "LEFT JOIN users on usersessions.userid = users.userid " .
                "WHERE usersessionid = :sessionid AND expires > now()";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":sessionid", $sessionid);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                
                $errors[] = "An unexpected error occurred";
                $this->debug($stmt->errorInfo());
                
                // In order to prevent recursive calling of audit log function
                if (!$suppressLog){
                    $this->auditlog("session error", $stmt->errorInfo());
                }
                
            } else {
                
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            }
            
            // Close the connection
            $dbh = NULL;
            
        }
        
        return $user;
        
    }
    /*
	public function isAdmin($userid, &$errors) {
       
		
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/isAdmin?userid=".$userid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("isadmin", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("isAdmin", "web service response => " . $response);
				$isadmin = json_decode($response)->isadmin;
		        $this->auditlog("isAdmin", "success");
				return $isadmin;
			}
		}
		
		curl_close($ch);
    }
	*/
    // Retrieves an existing session from the database for the specified user
    public function isAdmin(&$errors, $userid) {
        
        // Check for a valid user ID
        if (empty($userid)) {
            $errors[] = "Missing userid";
            return FALSE;
        }
        
        // Connect to the database
        $dbh = $this->getConnection();
        
        // Construct a SQL statement to perform the insert operation
        $sql = "SELECT isadmin FROM users WHERE userid = :userid";
        
        // Run the SQL select and capture the result code
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(":userid", $userid);
        $result = $stmt->execute();
        
        // If the query did not run successfully, add an error message to the list
        if ($result === FALSE) {
            
            $errors[] = "An unexpected error occurred";
            $this->debug($stmt->errorInfo());
            $this->auditlog("isadmin error", $stmt->errorInfo());
            
            return FALSE;
            
        } else {
            
            $row = $stmt->fetch();
            $isadmin = $row['isadmin'];
            
            // Return the isAdmin flag
            return $isadmin == 1;
            
        }
    }
    
    // Logs in an existing user and will return the $errors array listing any errors encountered
    public function login($username, $password, &$errors) {
        
        $this->debug("Login attempted");
        $this->auditlog("login", "attempt: $username, password length = ".strlen($password));
        
        // Validate the user input
        if (empty($username)) {
            $errors[] = "Missing username";
        }
        if (empty($password)) {
            $errors[] = "Missing password";
        }
        
        // Only try to query the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Construct a SQL statement to perform the insert operation
            $sql = "SELECT userid, passwordhash, emailvalidated, email FROM users " .
                "WHERE username = :username";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":username", $username);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                
                $errors[] = "An unexpected error occurred";
                $this->debug($stmt->errorInfo());
                $this->auditlog("login error", $stmt->errorInfo());
                
                
                // If the query did not return any rows, add an error message for bad username/password
            } else if ($stmt->rowCount() == 0) {
                
                $errors[] = "Bad username/password combination";
                $this->auditlog("login", "bad username: $username");
                
                
                // If the query ran successfully and we got back a row, then the login succeeded
            } else {
                
                // Get the row from the result
                $row = $stmt->fetch();
                
                // Check the password
                if (!password_verify($password, $row['passwordhash'])) {
                    
                    $errors[] = "Bad username/password combination";
                    $this->auditlog("login", "bad password: password length = ".strlen($password));
                    
                } else if ($row['emailvalidated'] == 0) {
                    
                    $errors[] = "Login error. Email not validated. Please check your inbox and/or spam folder.";
                    
                } else {
                    
                    // Create a new session for this user ID in the database
                    $userid = $row['userid'];
					$email = $row['email'];
					$this->sendOTP($userid, $email);
                    $this->auditlog("login", "success: $username, $userid");
                    
                }
                
            }
            
            // Close the connection
            $dbh = NULL;
            
        } else {
            $this->auditlog("login validation error", $errors);
        }
        
        
        // Return TRUE if there are no errors, otherwise return FALSE
        if (sizeof($errors) == 0){
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
	public function logout() {
		
		$sessionid = $_COOKIE['sessionid'];
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/logout?usersessionid=" . $sessionid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//$this->debug($response);
		$this->auditlog("logout", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
				// Clear the session ID cookie
                setcookie('sessionid', '', time()-3600);
                $this->auditlog("logout", "successful: $sessionid");
			}
		}
		
		curl_close($ch);
    }
	
    // Checks for logged in user and redirects to login if not found with "page=protected" indicator in URL.
    public function protectPage(&$errors, $isAdmin = FALSE) {
        
        // Get the user ID from the session record
        $user = $this->getSessionUser($errors);
        
        if ($user == NULL) {
            // Redirect the user to the login page
            $this->auditlog("protect page", "no user");
            header("Location: login.php?page=protected");
            exit();
        }
        
        // Get the user's ID
        $userid = $user["userid"];
        
        // If there is no user ID in the session, then the user is not logged in
        if(empty($userid)) {
            
            // Redirect the user to the login page
            $this->auditlog("protect page error", $user);
            header("Location: login.php?page=protected");
            exit();
            
        } else if ($isAdmin)  {
            
            // Get the isAdmin flag from the database
            $isAdminDB = $this->isAdmin($errors, $userid);
            
            if (!$isAdminDB) {
                
                // Redirect the user to the home page
                $this->auditlog("protect page", "not admin");
                header("Location: index.php?page=protectedAdmin");
                exit();
                
            }
            
        }
        
    }
	
	public function getThings(&$errors) {
        
		
		$user = $this->getSessionUser($errors);
        $registrationcode = $user["registrationcode"];
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getThings?registrationcode=".$registrationcode;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getThings", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getThings", "web service response => " . $response);
				$thing_object = json_decode($response);
				if (!empty($thing_object)){
					$things = array();
					foreach($thing_object as $thing){	
						$things[] = array(
							"thingid"=>$thing->thingid,
							"thingname"=>$thing->thingname,
							"thingcreated"=>$thing->thingcreated,
							"thingusername"=>$thing->thingusername,
							"thingattachmentid"=>$thing->thingattachmentid,
							"thingregistrationcode"=>$thing->thingregistrationcode
							
						);
					}
				}
		        $this->auditlog("getThings", "success");
			}
		}
		
		curl_close($ch);
        return $things;
    }
	
	
	public function getThing($thingid, &$errors) {
        
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getThing?thingid=".$thingid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getThing", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getThing", "web service response => " . $response);
				$thing_object = json_decode($response);
				$thing = array(
					"thingid"=>$thing_object[0]->thingid,
					"thingname"=>$thing_object[0]->thingname,
					"thingcreated"=>$thing_object[0]->thingcreated,
					"thinguserid"=>$thing_object[0]->thinguserid,
					"thingattachmentid"=>$thing_object[0]->thingattachmentid,
					"thingregistrationcode"=>$thing_object[0]->thingregistrationcode
					
				);
		        $this->auditlog("getThing", "success");
			}
		}
		
		curl_close($ch);
        return $thing;
    }
   
	
	public function getComments($thingid, &$errors) {
        
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getComments?thingid=".$thingid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getComments", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getComments", "web service response => " . $response);
				$comment_object = json_decode($response);
				if (!empty($comment_object)){
					$comments = array();
					foreach($comment_object as $comment){	
						$comments[] = array(
							"commentid"=>$comment->commentid,
							"commenttext"=>$comment->commenttext,
							"username"=>$comment->username,
							"attachmentid"=>$comment->attachmentid,
							"filename"=>$comment->filename
						);
					}
				}
		        $this->auditlog("getComments", "success");
			}
		}
		
		curl_close($ch);
        return $comments;
    }
	
	
	/*
	public function saveAttachment($attachmentid, &$errors) {
        
        $this->auditlog("saveAttachment", "attempt: $attachmentid, $attachment");
        
		$attachmentid = NULL;
        
        // Check for an attachment
        if (isset($attachment) && isset($attachment['name']) && !empty($attachment['name'])) {
            
            // Get the list of valid attachment types and file extensions
            $attachmenttypes = $this->getAttachmentTypes($errors);
            
            // Construct an array containing only the 'extension' keys
            $extensions = array_column($attachmenttypes, 'extension');
            
            // Get the uploaded filename
            $filename = $attachment['name'];
            
            // Extract the uploaded file's extension
            $dot = strrpos($filename, ".");
            
            // Make sure the file has an extension and the last character of the name is not a "."
            if ($dot !== FALSE && $dot != strlen($filename)) {
                
                // Check to see if the uploaded file has an allowed file extension
                $extension = strtolower(substr($filename, $dot + 1));
                if (!in_array($extension, $extensions)) {
                    
                    // Not a valid file extension
                    $errors[] = "File does not have a valid file extension";
                    $this->auditlog("saveAttachment", "invalid file extension: $filename");
                    
                }
                
            } else {
                
                // No file extension -- Disallow
                $errors[] = "File does not have a valid file extension";
                $this->auditlog("saveAttachment", "no file extension: $filename");
                
            }
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
			
			// Create a new ID
            $attachmentid = bin2hex(random_bytes(16));
			
			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/saveAttachment";
			$data = array(
				'attachmentid'=>$attachmentid,
				'filename'=>$filename
			);
			$data_json = json_encode($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->debug($response);
			$this->auditlog("saveAttachment", "response = : $response");

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {
					 // Move the file from temp folder to html attachments folder
                    move_uploaded_file($attachment['tmp_name'], getcwd() . '/attachments/' . $attachmentid . '-' . $attachment['name']);
                    $attachmentname = $attachment["name"];
                    $this->auditlog("saveAttachment", "success: $attachmentname");

				}

			}
			
			curl_close($ch);
			return $attachmentid;

        } 
    }
	*/
	
    // Handles the saving of uploaded attachments and the creation of a corresponding record in the attachments table.
    public function saveAttachment($dbh, $attachment, &$errors) {
        
        $attachmentid = NULL;
        
        // Check for an attachment
        if (isset($attachment) && isset($attachment['name']) && !empty($attachment['name'])) {
            
            // Get the list of valid attachment types and file extensions
            $attachmenttypes = $this->getAttachmentTypes($errors);
            
            // Construct an array containing only the 'extension' keys
            $extensions = array_column($attachmenttypes, 'extension');
            
            // Get the uploaded filename
            $filename = $attachment['name'];
            
            // Extract the uploaded file's extension
            $dot = strrpos($filename, ".");
            
            // Make sure the file has an extension and the last character of the name is not a "."
            if ($dot !== FALSE && $dot != strlen($filename)) {
                
                // Check to see if the uploaded file has an allowed file extension
                $extension = strtolower(substr($filename, $dot + 1));
                if (!in_array($extension, $extensions)) {
                    
                    // Not a valid file extension
                    $errors[] = "File does not have a valid file extension";
                    $this->auditlog("saveAttachment", "invalid file extension: $filename");
                    
                }
                
            } else {
                
                // No file extension -- Disallow
                $errors[] = "File does not have a valid file extension";
                $this->auditlog("saveAttachment", "no file extension: $filename");
                
            }
            
            // Only attempt to add the attachment to the database if the file extension was good
            if (sizeof($errors) == 0) {
                
                // Create a new ID
                $attachmentid = bin2hex(random_bytes(16));
                
                // Construct a SQL statement to perform the insert operation
                $sql = "INSERT INTO attachments (attachmentid, filename) VALUES (:attachmentid, :filename)";
                
                // Run the SQL insert and capture the result code
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(":attachmentid", $attachmentid);
                $stmt->bindParam(":filename", $filename);
                $result = $stmt->execute();
                
                // If the query did not run successfully, add an error message to the list
                if ($result === FALSE) {
                    
                    $errors[] = "An unexpected error occurred storing the attachment.";
                    $this->debug($stmt->errorInfo());
                    $this->auditlog("saveAttachment error", $stmt->errorInfo());
                    
                } else {
                    
                    // Move the file from temp folder to html attachments folder
                    move_uploaded_file($attachment['tmp_name'], getcwd() . '/attachments/' . $attachmentid . '-' . $attachment['name']);
                    $attachmentname = $attachment["name"];
                    $this->auditlog("saveAttachment", "success: $attachmentname");
                    
                }
                
            }
            
        }
        
        return $attachmentid;
        
    }
    
	public function addThing($thingname, $attachment, &$errors) {
        
        $this->auditlog("addThing", "attempt: $thingname, $attachment");
        
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
            $user = $this->getSessionUser($errors);
			$userid = $user["userid"];
			$registrationcode = $user["registrationcode"];
			$thingid = bin2hex(random_bytes(16));
			
			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/addThing";
			$data = array(
				'thingid'=>$thingid,
				'thingname'=>$thingname,
				'userid'=>$userid,
				'attachmentid'=>$attachmentid,
				'registrationcode'=>$registrationcode
			);
			$data_json = json_encode($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->debug($response);
			$this->auditlog("addThing", "response = : $response");

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {
					 $this->auditlog("addthing", "success: $name, id = $thingid");

				}

			}
			
			curl_close($ch);
			return $thingid;

        } 
    }
	
    public function addComment($commenttext, $thingid, $attachment, &$errors) {
        
        $this->auditlog("addComment", "attempt: $commenttext, $attachment");
        
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
              // Get the user id from the session
			$user = $this->getSessionUser($errors);
			$userid = $user["userid"];
			$commentid = bin2hex(random_bytes(16));
			
			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/addComment";
			$data = array(
				'commentid'=>$commentid,
				'commenttext'=>$commenttext,
				'userid'=>$userid,
				'thingid'=>$thingid,
				'attachmentid'=>$attachmentid
			);
			$data_json = json_encode($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->debug($response);
			$this->auditlog("addComment", "response = : $response");

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {
					 $this->auditlog("addcomment", "success: $commenttext, id = $commentid");

				}

			}
			
			curl_close($ch);
			return $commentid;

        } 
    }
	
	public function getUsers(&$errors) {
        
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getUsers";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getUsers", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getUsers", "web service response => " . $response);
				$user_object = json_decode($response);
				if (!empty($user_object)){
					$users = array();
					foreach($user_object as $user){	
						$users[] = array(
							"userid"=>$user->userid,
							"username"=>$user->username,
							"email"=>$user->email,
							"isadmin"=>$user->isadmin
							
						);
					}
				}
		        $this->auditlog("getUsers", "success");
			}
		}
		
		curl_close($ch);
        return $users;
    }

	public function getUser($userid, &$errors) {
        
        $user = $this->getSessionUser($errors);
        $loggedinuserid = $user["userid"];
        
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getUser?userid=".$loggedinuserid;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getUser", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getUser", "web service response => " . $response);
				$user_object = json_decode($response);
				$user = array(
					"userid"=>$user_object->userid,
					"username"=>$user_object->username,
					"email"=>$user_object->email,
					"isadmin"=>$user_object->isadmin
					
				);
		        $this->auditlog("getUser", "success");
			}
		}
		
		curl_close($ch);
        return $user;
    }
	
    
    // Updates a single user in the database and will return the $errors array listing any errors encountered
    public function updateUser($userid, $username, $email, $password, $isadminDB, &$errors) {
        
        // Assume no user exists for this user id
        $user = NULL;
        
        // Validate the user input
        if (empty($userid)) {
            
            $errors[] = "Missing userid";
            
        }
        
        if(sizeof($errors) == 0) {
            
            // Get the user id from the session
            $user = $this->getSessionUser($errors);
            $loggedinuserid = $user["userid"];
            $isadmin = FALSE;
            
            // Check to see if the user really is logged in and really is an admin
            if ($loggedinuserid != NULL) {
                $isadmin = $this->isAdmin($errors, $loggedinuserid);
            }
            
            // Stop people from editing someone else's profile
            if (!$isadmin && $loggedinuserid != $userid) {
                
                $errors[] = "Cannot edit other user";
                $this->auditlog("getuser", "attempt to update other user: $loggedinuserid");
                
            } else {
                
                // Validate the user input
                if (empty($userid)) {
                    $errors[] = "Missing userid";
                }
                if (empty($username)) {
                    $errors[] = "Missing username";
                }
                if (empty($email)) {
                    $errors[] = "Missing email;";
                }
                
                // Only try to update the data into the database if there are no validation errors
                if (sizeof($errors) == 0) {
                    
                    // Connect to the database
                    $dbh = $this->getConnection();
                    
                    // Hash the user's password
                    $passwordhash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Construct a SQL statement to perform the select operation
                    $sql = 	"UPDATE users SET username=:username, email=:email " .
                        ($loggedinuserid != $userid ? ", isadmin=:isAdmin " : "") .
                        (!empty($password) ? ", passwordhash=:passwordhash" : "") .
                        " WHERE userid = :userid";
                        
                        // Run the SQL select and capture the result code
                        $stmt = $dbh->prepare($sql);
                        $stmt->bindParam(":username", $username);
                        $stmt->bindParam(":email", $email);
                        $adminFlag = ($isadminDB ? "1" : "0");
                        if ($loggedinuserid != $userid) {
                            $stmt->bindParam(":isAdmin", $adminFlag);
                        }
                        if (!empty($password)) {
                            $stmt->bindParam(":passwordhash", $passwordhash);
                        }
                        $stmt->bindParam(":userid", $userid);
                        $result = $stmt->execute();
                        
                        // If the query did not run successfully, add an error message to the list
                        if ($result === FALSE) {
                            $errors[] = "An unexpected error occurred saving the user profile. ";
                            $this->debug($stmt->errorInfo());
                            $this->auditlog("updateUser error", $stmt->errorInfo());
                        } else {
                            $this->auditlog("updateUser", "success");
                        }
                        
                        // Close the connection
                        $dbh = NULL;
                } else {
                    $this->auditlog("updateUser validation error", $errors);
                }
            }
        } else {
            $this->auditlog("updateUser validation error", $errors);
        }
        
        // Return TRUE if there are no errors, otherwise return FALSE
        if (sizeof($errors) == 0){
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    // Validates a provided username or email address and sends a password reset email
    public function passwordReset($usernameOrEmail, &$errors) {
        
        // Check for a valid username/email
        if (empty($usernameOrEmail)) {
            $errors[] = "Missing username/email";
            $this->auditlog("session", "missing username");
        }
        
        // Only proceed if there are no validation errors
        if (sizeof($errors) == 0) {
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Construct a SQL statement to perform the insert operation
            $sql = "SELECT email, userid FROM users WHERE username = :username OR email = :email";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":username", $usernameOrEmail);
            $stmt->bindParam(":email", $usernameOrEmail);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                
                $this->auditlog("passwordReset error", $stmt->errorInfo());
                $errors[] = "An unexpected error occurred saving your request to the database.";
                $this->debug($stmt->errorInfo());
                
            } else {
                
                if ($stmt->rowCount() == 1) {
                    
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $passwordresetid = bin2hex(random_bytes(16));
                    $userid = $row['userid'];
                    $email = $row['email'];
                    
                    // Construct a SQL statement to perform the insert operation
                    $sql = "INSERT INTO passwordreset (passwordresetid, userid, email, expires) " .
                        "VALUES (:passwordresetid, :userid, :email, DATE_ADD(NOW(), INTERVAL 1 HOUR))";
                    
                    // Run the SQL select and capture the result code
                    $stmt = $dbh->prepare($sql);
                    $stmt->bindParam(":passwordresetid", $passwordresetid);
                    $stmt->bindParam(":userid", $userid);
                    $stmt->bindParam(":email", $email);
                    $result = $stmt->execute();
                    
                    $this->auditlog("passwordReset", "Sending message to $email");
                    
                    // Send reset email
                    $pageLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    $pageLink = str_replace("reset.php", "password.php", $pageLink);
                    $to      = $email;
                    $subject = 'Password reset';
                    $message = "A password reset request for this account has been submitted at https://russellthackston.me. ".
                        "If you did not make this request, please ignore this message. No other action is necessary. ".
                        "To reset your password, please click the following link: $pageLink?id=$passwordresetid";
                    $headers = 'From: webmaster@russellthackston.me' . "\r\n" .
                        'Reply-To: webmaster@russellthackston.me' . "\r\n";
                    
                    mail($to, $subject, $message, $headers);
                    
                    $this->auditlog("passwordReset", "Message sent to $email");
                    
                    
                } else {
                    
                    $this->auditlog("passwordReset", "Bad request for $usernameOrEmail");
                    
                }
                
            }
            
            // Close the connection
            $dbh = NULL;
            
        }
        
    }
    
    // Validates a provided username or email address and sends a password reset email
    public function updatePassword($password, $passwordresetid, &$errors) {
        
        // Check for a valid username/email
        $this->validatePassword($password, $errors);
        if (empty($passwordresetid)) {
            $errors[] = "Missing passwordrequestid";
        }
        
        // Only proceed if there are no validation errors
        if (sizeof($errors) == 0) {
            
            // Connect to the database
            $dbh = $this->getConnection();
            
            // Construct a SQL statement to perform the insert operation
            $sql = "SELECT userid FROM passwordreset WHERE passwordresetid = :passwordresetid AND expires > NOW()";
            
            // Run the SQL select and capture the result code
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(":passwordresetid", $passwordresetid);
            $result = $stmt->execute();
            
            // If the query did not run successfully, add an error message to the list
            if ($result === FALSE) {
                
                $errors[] = "An unexpected error occurred updating your password.";
                $this->auditlog("updatePassword", $stmt->errorInfo());
                $this->debug($stmt->errorInfo());
                
            } else if ($stmt->rowCount() == 1) {
                
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $userid = $row['userid'];
                $this->updateUserPassword($userid, $password, $errors);
                $this->clearPasswordResetRecords($passwordresetid);
                
            } else {
                
                $this->auditlog("updatePassword", "Bad request id: $passwordresetid");
                
            }
            
        }
        
    }
    
    function getFile($name){
        return file_get_contents($name);
    }
    
	public function getAttachmentTypes(&$errors) {
        
		
		$user = $this->getSessionUser($errors);
        $registrationcode = $user["registrationcode"];
		$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/getAttachmentTypes";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->debug($response);
		$this->auditlog("getAtts", "response = : $response");
		
		if ($response === FALSE) {
			$errors[] = "An unexpected failure occurred contacting the web service.";
		} else {
			if($httpCode == 400) {
				
				// JSON was double-encoded, so it needs to be double decoded
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Bad input";
				}
			} else if($httpCode == 500) {
				$errorsList = json_decode(json_decode($response))->errors;
				foreach ($errorsList as $err) {
					$errors[] = $err;
				}
				if (sizeof($errors) == 0) {
					$errors[] = "Server error";
				}
			} else if($httpCode == 200) {
	            $this->auditlog("getAtts", "web service response => " . $response);
				$att_object = json_decode($response);
				if (!empty($att_object)){
					$atts = array();
					foreach($att_object as $att){	
						$atts[] = array(
							"attachmenttypeid"=>$att->attachmenttypeid,
							"name"=>$att->name,
							"extension"=>$att->extension
						);
					}
				}
		        $this->auditlog("getAtts", "success");
			}
		}
		
		curl_close($ch);
        return $atts;
    }
	
	
    
	public function newAttachmentType($name, $extension, &$errors) {
        
        $this->auditlog("newAttachmentType", "attempt: $name, $extension");
        
        
        // Only try to insert the data into the database if there are no validation errors
        if (sizeof($errors) == 0) {
            
            // Create a new user ID
            $attachmenttypeid = bin2hex(random_bytes(25));

			$url = "https://eaiqac5v8c.execute-api.us-east-1.amazonaws.com/default/newAttachmentType";
			$data = array(
				'attachmenttypeid'=>$attachmenttypeid,
				'name'=>$name,
				'extension'=>$extension
			);
			$data_json = json_encode($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response  = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($response === FALSE) {
				$errors[] = "An unexpected failure occurred contacting the web service.";
			} else {

				if($httpCode == 400) {
					
					// JSON was double-encoded, so it needs to be double decoded
					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Bad input";
					}

				} else if($httpCode == 500) {

					$errorsList = json_decode(json_decode($response))->errors;
					foreach ($errorsList as $err) {
						$errors[] = $err;
					}
					if (sizeof($errors) == 0) {
						$errors[] = "Server error";
					}

				} else if($httpCode == 200) {


				}

			}
			
			curl_close($ch);
			return $attachmenttypeid;

        } 
    }
   
}


?>