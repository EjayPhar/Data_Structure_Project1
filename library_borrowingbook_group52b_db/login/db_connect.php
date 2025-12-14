<?php
$servername = "localhost";
$username = "root"; // Default user
$password = ""; // Default password is empty
$database = "library_borrowingbook_group52b_db";//Name of your database
// Create connection
$conn = new mysqli($servername, $username, $password, $database);
// Check connection
if ($conn->connect_error) {
die("Connection failed: " . $conn->connect_error);
}

?>