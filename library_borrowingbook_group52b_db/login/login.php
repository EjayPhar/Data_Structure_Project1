<?php
session_start();
include 'db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
$email = $_POST['email'];
$password = $_POST['password'];
$requestedRole = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : '';

$sql = "SELECT id, username, password, role, email FROM users WHERE email=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $username, $hashed_password, $role, $user_email);

if ($stmt->fetch() && password_verify($password, $hashed_password)) {
    // Normalize roles for comparison
    $normalizedDbRole = strtolower($role);
    $normalizedRequestedRole = $requestedRole;
    
    // Treat 'student' as an alias of 'user' for backward compatibility
    if ($normalizedRequestedRole === 'student') {
        $normalizedRequestedRole = 'user';
    }
    
    // Treat 'staff' and 'librarian' as equivalent
    if ($normalizedDbRole === 'staff') {
        $normalizedDbRole = 'librarian';
    }
    if ($normalizedRequestedRole === 'staff') {
        $normalizedRequestedRole = 'librarian';
    }
    
    // Check if roles match
    if ($normalizedDbRole === $normalizedRequestedRole) {
$_SESSION['user_id'] = $id;
$_SESSION['username'] = $username;
$_SESSION['email'] = $user_email;
$_SESSION['role'] = strtolower($role);

// Record attendance on student/user login (one record per day)
if (in_array(strtolower($role), ['student', 'user'], true)) {
    if ($attCheck = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND check_in_date = CURDATE() LIMIT 1")) {
        $attCheck->bind_param("i", $id);
        if ($attCheck->execute()) {
            $attCheck->store_result();
            if ($attCheck->num_rows === 0) {
                if ($attIns = $conn->prepare("INSERT INTO attendance (user_id, check_in_date, check_in_time, status) VALUES (?, CURDATE(), CURTIME(), 'visited')")) {
                    $attIns->bind_param("i", $id);
                    $attIns->execute();
                    $attIns->close();
                }
            } else {
                if ($attUpd = $conn->prepare("UPDATE attendance SET status = 'visited' WHERE user_id = ? AND check_in_date = CURDATE() AND status <> 'visited'")) {
                    $attUpd->bind_param("i", $id);
                    $attUpd->execute();
                    $attUpd->close();
                }
            }
        }
        $attCheck->close();
    }
}

// Redirect based on role
if (strtolower($role) === 'librarian' || strtolower($role) === 'staff') {
    header('Location: ../librarian/librarian-dashboard.php');
    exit;
} elseif (strtolower($role) === 'admin') {
    header('Location: ../admin-dashboard/admin.php');
    exit;
} else {
    header('Location: ../Student/student-dashboard.php');
    exit;
}
    } else {
        header('Location: login.html?error=1');
        exit;
    }
} else {
    header('Location: login.html?error=1');
    exit;
}
}
?>