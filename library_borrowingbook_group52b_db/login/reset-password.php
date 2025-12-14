<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if code was verified
    if (!isset($_SESSION['code_verified']) || !$_SESSION['code_verified']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please verify your code first.']);
        exit;
    }
    
    $email = $_SESSION['verified_email'];
    $new_password = $_POST['new_password'];
    
    // Validate password strength
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
        exit;
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter']);
        exit;
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit;
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $update_sql = "UPDATE users SET password = ? WHERE email = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $hashed_password, $email);
    
    if ($update_stmt->execute()) {
        // Clear session variables
        unset($_SESSION['reset_email']);
        unset($_SESSION['code_verified']);
        unset($_SESSION['verified_email']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset successfully. Redirecting to login...'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating password. Please try again.']);
    }
    
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>