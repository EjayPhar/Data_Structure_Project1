<?php
session_start();
include 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Insert logout activity
    $sql = "INSERT INTO user_logs (user_id, username, action, log_time)
            VALUES (?, ?, 'Logout', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $username);
    $stmt->execute();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: login.html");
exit();
?>
