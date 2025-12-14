<?php
include 'login/db_connect.php';

$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active'";
if ($conn->query($sql) === TRUE) {
    echo 'Status column added successfully.<br>';
} else {
    echo 'Error adding status column: ' . $conn->error . '<br>';
}

$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS join_date DATE DEFAULT CURDATE()";
if ($conn->query($sql) === TRUE) {
    echo 'Join date column added successfully.<br>';
} else {
    echo 'Error adding join date column: ' . $conn->error . '<br>';
}

$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active DATE DEFAULT CURDATE()";
if ($conn->query($sql) === TRUE) {
    echo 'Last active column added successfully.<br>';
} else {
    echo 'Error adding last active column: ' . $conn->error . '<br>';
}

echo 'Users table alteration completed.';
$conn->close();
?>
