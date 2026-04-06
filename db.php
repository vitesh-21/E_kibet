<?php
$servername = "sql302.infinityfree.com";
$username   = "if0_41413175";
$password   = "vtesh1234"; 
$dbname     = "if0_41413175_student"; // Make sure "student" matches the name you created

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>