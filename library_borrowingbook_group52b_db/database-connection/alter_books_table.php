<?php
include 'login/db_connect.php';

function books_column_exists(mysqli $conn, string $column): bool {
    // Use INFORMATION_SCHEMA to check for column existence in current database
    $column_esc = $conn->real_escape_string($column);
    $sql = "SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'books'
              AND COLUMN_NAME = '$column_esc'";
    $res = $conn->query($sql);
    if (!$res) {
        echo 'Error checking column existence: ' . $conn->error . '<br>';
        return false;
    }
    $row = $res->fetch_assoc();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

$messages = [];

// Ensure deleted_at column exists
if (!books_column_exists($conn, 'deleted_at')) {
    if ($conn->query("ALTER TABLE books ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL")) {
        $messages[] = 'Deleted at column added successfully.';
    } else {
        $messages[] = 'Error adding deleted at column: ' . $conn->error;
    }
} else {
    $messages[] = 'Deleted at column already exists.';
}

// Ensure deleted_by column exists
if (!books_column_exists($conn, 'deleted_by')) {
    if ($conn->query("ALTER TABLE books ADD COLUMN deleted_by VARCHAR(255) NULL DEFAULT NULL")) {
        $messages[] = 'Deleted by column added successfully.';
    } else {
        $messages[] = 'Error adding deleted by column: ' . $conn->error;
    }
} else {
    $messages[] = 'Deleted by column already exists.';
}

foreach ($messages as $m) {
    echo $m . '<br>';
}

echo 'Books table alteration completed.';
$conn->close();
?>
