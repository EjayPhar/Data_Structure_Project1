<?php
session_start();
require '../login/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.html");
    exit();
}

if (!isset($_POST['borrowing_id'])) {
    header("Location: borrowing.php");
    exit();
}

$borrowing_id = (int) $_POST['borrowing_id'];
$user_id = $_SESSION['user_id'];

/*
 Update borrowing:
 - status → returned
 - return_date → today
*/
$update = $conn->prepare("
    UPDATE borrowings
    SET status = 'returned',
        return_date = CURDATE()
    WHERE id = ? AND user_id = ? AND status = 'borrowed'
");

$update->bind_param("ii", $borrowing_id, $user_id);
$update->execute();

if ($update->affected_rows > 0) {
    $_SESSION['success_message'] = "Book returned successfully.";
}

$update->close();
$conn->close();

header("Location: borrowing.php");
exit();
