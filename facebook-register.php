<?php
	//Start session
	session_start();
	
	//Include database connection details
	require_once('config.php');

	//Connect to mysql server
	$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
	if(!$link) {
		die('Failed to connect to server: ' . mysql_error());
	}
	
	//Select database
	$db = mysql_select_db(DB_DATABASE);
	if(!$db) {
		die("Unable to select database");
	}

	//Function to sanitize values received from the form. Prevents SQL injection
	function clean($str) {
		$str = @trim($str);
		if(get_magic_quotes_gpc()) {
			$str = stripslashes($str);
		}
		return mysql_real_escape_string($str);
	}
	
	//Sanitize the POST values
	$fname = '';
	$lname = '';
	$login = null;
	$password = null;
	$cpassword = null;

	$facebook_id = $_POST['facebook_id'];
		
	// //Check for duplicate facebook ID and login directly
	if($facebook_id != '') {
		$qry = "SELECT * FROM members WHERE facebook_id='$facebook_id'";
		$result = mysql_query($qry);
		if($result) {
			if(mysql_num_rows($result) > 0) {
				$_SESSION['FACEBOOKID'] = $facebook_id;
				header("location: facebook-login.php");
				exit();
			}
			//@mysql_free_result($result);
		}
		else {
			die("Query failed");
		}
	}
	
	//Create INSERT query
	$qry = "INSERT INTO members(firstname, lastname, login, passwd, facebook_id) VALUES('$fname','$lname','$login','$password','$facebook_id')";
	$result = @mysql_query($qry);
	
	//Check whether the query was successful or not
	if($result) {
		$_SESSION['FACEBOOKID'] = $facebook_id;
		header("location: facebook-login.php");
		exit();
	}
	else {
		die("Query failed");
	}


?>