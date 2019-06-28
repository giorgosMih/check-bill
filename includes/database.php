<?php
// Create connection
$dbConn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

// Check connection
if ($dbConn->connect_error) {
	echo "DB_ERROR -> mysqli connection failed: " . $dbConn->connect_error;
    die();
}

$dbConn->set_charset("utf8");
?>