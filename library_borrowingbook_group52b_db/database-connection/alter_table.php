<?php
include 'login/db_connect.php';
$sql = "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student'";
if ($conn->query($sql) === TRUE) {
    echo "Role column added successfully.";
} else {
    echo "Error adding role column: " . $conn->error;
}
$conn->close();
?>
