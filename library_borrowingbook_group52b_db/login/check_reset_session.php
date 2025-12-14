<?php
session_start();
header('Content-Type: application/json');

// Check if user has verified their code
$verified = isset($_SESSION['code_verified']) && $_SESSION['code_verified'] === true;
$email = isset($_SESSION['verified_email']) ? $_SESSION['verified_email'] : '';

echo json_encode([
    'verified' => $verified,
    'email' => $email,
    'session_id' => session_id()
]);
?>