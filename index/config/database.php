<?php

define('DB_HOST', 'db'); // use IP instead of localhost
define('DB_USER', 'user');
define('DB_PASS', 'password');
define('DB_NAME', 'exam_system');
define('DB_PORT', 3306); // 👈 add this

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");
?>
