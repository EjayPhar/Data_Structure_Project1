<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Check if email exists in database
    $check_sql = "SELECT id, username FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email address not found in our system']);
        $check_stmt->close();
        exit;
    }
    
    $check_stmt->bind_result($user_id, $username);
    $check_stmt->fetch();
    $check_stmt->close();
    
    // Generate 6-digit verification code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiration time (15 minutes from now)
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete old unused codes for this email
    $delete_sql = "DELETE FROM verification_codes WHERE email = ? AND used = 0";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Insert new verification code
    $insert_sql = "INSERT INTO verification_codes (email, code, purpose, expires_at) VALUES (?, ?, 'password_reset', ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sss", $email, $code, $expires_at);
    
    if ($insert_stmt->execute()) {
        // Send email
        $to = $email;
        $subject = "Password Reset Verification Code - OMSC Library";
        $message = "Hello $username,\n\n";
        $message .= "You have requested to reset your password for OMSC Library Borrowing Management System.\n\n";
        $message .= "Your verification code is: $code\n\n";
        $message .= "This code will expire in 15 minutes.\n\n";
        $message .= "If you did not request this, please ignore this email.\n\n";
        $message .= "Best regards,\n";
        $message .= "OMSC Library Team";
        
        $headers = "From: noreply@omsclibrary.com\r\n";
        $headers .= "Reply-To: noreply@omsclibrary.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Try to send email
        $email_sent = mail($to, $subject, $message, $headers);
        
        if ($email_sent) {
            // Store email in session for verification step
            $_SESSION['reset_email'] = $email;
            echo json_encode([
                'success' => true, 
                'message' => 'Verification code sent to your email address'
            ]);
        } else {
            // Email sending failed, but save code in database for testing
            $_SESSION['reset_email'] = $email;
            echo json_encode([
                'success' => true, 
                'message' => 'Verification code generated. (Email service unavailable - Code saved in database: ' . $code . ')',
                'debug_code' => $code // Remove this in production
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error generating verification code']);
    }
    
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>