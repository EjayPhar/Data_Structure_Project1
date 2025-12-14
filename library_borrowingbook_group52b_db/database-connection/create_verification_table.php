<?php
include 'login/db_connect.php';

// Create verification_codes table
$verification_sql = "CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    purpose ENUM('password_reset', 'email_verification') DEFAULT 'password_reset',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at)
)";

if ($conn->query($verification_sql) === TRUE) {
    echo "Verification codes table created successfully.<br>";
} else {
    echo "Error creating verification codes table: " . $conn->error . "<br>";
}

$conn->close();
echo "Verification table setup completed.";
?>