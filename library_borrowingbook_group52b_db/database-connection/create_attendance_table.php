<?php
include 'login/db_connect.php';

// Create attendance table
$attendance_sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_in_time TIME NOT NULL,
    status ENUM('visited', 'absent') DEFAULT 'visited',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($attendance_sql) === TRUE) {
    echo "Attendance table created successfully.<br>";
} else {
    echo "Error creating attendance table: " . $conn->error . "<br>";
}

// Insert sample attendance data
$sample_attendance = [
    ['user_id' => 1, 'check_in_date' => '2023-08-01', 'check_in_time' => '09:15:00', 'status' => 'visited'],
    ['user_id' => 2, 'check_in_date' => '2023-08-01', 'check_in_time' => '10:30:00', 'status' => 'visited'],
    ['user_id' => 3, 'check_in_date' => '2023-08-01', 'check_in_time' => '11:20:00', 'status' => 'visited'],
    ['user_id' => 4, 'check_in_date' => '2023-08-01', 'check_in_time' => '14:00:00', 'status' => 'visited'],
];

foreach ($sample_attendance as $record) {
    $insert_sql = "INSERT INTO attendance (user_id, check_in_date, check_in_time, status) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isss", $record['user_id'], $record['check_in_date'], $record['check_in_time'], $record['status']);

    if ($insert_stmt->execute()) {
        echo "Attendance record inserted successfully.<br>";
    } else {
        echo "Error inserting attendance record: " . $insert_stmt->error . "<br>";
    }
    $insert_stmt->close();
}

$conn->close();
echo "Attendance table setup completed.";
?>
