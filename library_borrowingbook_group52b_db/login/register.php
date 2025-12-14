<?php
include 'db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = $_POST['role'];
$sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $username, $email, $password, $role);
if ($stmt->execute()) {
echo "Registration successful! <a href='login.html'>Login here</a>";
} else {
echo "Error: " . $stmt->error;
}
}
?>