<?php
// Start session first
session_start();

// Include database connection
include 'db_connect.php';

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['code']);
    
    // Check if email is in session, if not try to get it from POST
    $email = '';
    if (isset($_SESSION['reset_email']) && !empty($_SESSION['reset_email'])) {
        $email = $_SESSION['reset_email'];
    } elseif (isset($_POST['email']) && !empty($_POST['email'])) {
        $email = trim($_POST['email']);
    }
    
    // If still no email, check if we can find it from the code in database
    if (empty($email) && !empty($code)) {
        $find_email_sql = "SELECT email FROM verification_codes 
                          WHERE code = ? AND used = 0 AND expires_at > NOW() 
                          ORDER BY created_at DESC LIMIT 1";
        $find_stmt = $conn->prepare($find_email_sql);
        $find_stmt->bind_param("s", $code);
        $find_stmt->execute();
        $find_stmt->store_result();
        
        if ($find_stmt->num_rows > 0) {
            $find_stmt->bind_result($found_email);
            $find_stmt->fetch();
            $email = $found_email;
        }
        $find_stmt->close();
    }
    
    if (empty($email)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Session expired. Please start over from the forgot password page.'
        ]);
        exit;
    }
    
    // Validate code format
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Invalid code format']);
        exit;
    }
    
    // Check if code exists and is valid
    $verify_sql = "SELECT id FROM verification_codes 
                   WHERE email = ? AND code = ? AND used = 0 
                   AND expires_at > NOW() AND purpose = 'password_reset'
                   LIMIT 1";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ss", $email, $code);
    $verify_stmt->execute();
    $verify_stmt->store_result();
    
    if ($verify_stmt->num_rows > 0) {
        $verify_stmt->bind_result($code_id);
        $verify_stmt->fetch();
        $verify_stmt->close();
        
        // Mark code as used
        $update_sql = "UPDATE verification_codes SET used = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $code_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Store verification status in session
        $_SESSION['code_verified'] = true;
        $_SESSION['verified_email'] = $email;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Code verified successfully! Redirecting...',
            'redirect' => 'reset-password.html'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>