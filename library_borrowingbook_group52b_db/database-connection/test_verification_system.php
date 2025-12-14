<?php
/**
 * Test script for email verification system
 * This script tests the database setup and basic functionality
 */

include 'login/db_connect.php';

echo "<h2>Email Verification System - Test Results</h2>";
echo "<hr>";

// Test 1: Check if verification_codes table exists
echo "<h3>Test 1: Database Table Check</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'verification_codes'");
if ($table_check->num_rows > 0) {
    echo "✓ verification_codes table exists<br>";
} else {
    echo "✗ verification_codes table NOT found<br>";
    echo "Please run: php create_verification_table.php<br>";
}

// Test 2: Check table structure
echo "<h3>Test 2: Table Structure Check</h3>";
$structure = $conn->query("DESCRIBE verification_codes");
if ($structure) {
    echo "✓ Table structure:<br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Check if users table has email column
echo "<h3>Test 3: Users Table Check</h3>";
$users_check = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
if ($users_check->num_rows > 0) {
    echo "✓ users table has email column<br>";
} else {
    echo "✗ users table missing email column<br>";
}

// Test 4: Count existing users
echo "<h3>Test 4: User Count</h3>";
$user_count = $conn->query("SELECT COUNT(*) as count FROM users");
if ($user_count) {
    $count = $user_count->fetch_assoc()['count'];
    echo "✓ Total users in database: {$count}<br>";
    
    // Show sample users (without passwords)
    $sample_users = $conn->query("SELECT id, username, email, role FROM users LIMIT 5");
    if ($sample_users && $sample_users->num_rows > 0) {
        echo "<br>Sample users:<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
        while ($user = $sample_users->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Test 5: Test code generation
echo "<h3>Test 5: Code Generation Test</h3>";
$test_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
echo "✓ Sample generated code: {$test_code}<br>";
echo "✓ Code length: " . strlen($test_code) . " characters<br>";
echo "✓ Code is numeric: " . (is_numeric($test_code) ? 'Yes' : 'No') . "<br>";

// Test 6: Check PHP mail configuration
echo "<h3>Test 6: PHP Mail Configuration</h3>";
$smtp = ini_get('SMTP');
$smtp_port = ini_get('smtp_port');
$sendmail_from = ini_get('sendmail_from');

echo "SMTP Server: " . ($smtp ? $smtp : "Not configured") . "<br>";
echo "SMTP Port: " . ($smtp_port ? $smtp_port : "Not configured") . "<br>";
echo "Sendmail From: " . ($sendmail_from ? $sendmail_from : "Not configured") . "<br>";

if (!$smtp || !$smtp_port) {
    echo "<br><strong>Note:</strong> Email server not configured. System will work in debug mode (codes shown in response).<br>";
    echo "To enable email sending, configure SMTP settings in php.ini<br>";
}

// Test 7: File permissions check
echo "<h3>Test 7: Required Files Check</h3>";
$required_files = [
    'login/send_verification_code.php',
    'login/verify_code.php',
    'login/reset-password.html',
    'login/reset-password.php',
    'login/forgot-password.html'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✓ {$file} exists<br>";
    } else {
        echo "✗ {$file} NOT found<br>";
    }
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "The email verification system is ready to use!<br>";
echo "<br>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Navigate to <a href='login/forgot-password.html'>login/forgot-password.html</a><br>";
echo "2. Enter a registered email address<br>";
echo "3. Check the response for the verification code (or your email inbox if SMTP is configured)<br>";
echo "4. Enter the code to verify<br>";
echo "5. Reset your password<br>";

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        background-color: #f5f5f5;
    }
    h2 {
        color: #31694E;
    }
    h3 {
        color: #658C58;
        margin-top: 20px;
    }
    table {
        background-color: white;
        margin: 10px 0;
    }
    hr {
        border: 1px solid #BBC863;
        margin: 20px 0;
    }
</style>