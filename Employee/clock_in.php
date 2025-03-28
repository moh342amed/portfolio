<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');

// Check if user already clocked in today
$check_sql = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("is", $user_id, $current_date);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $attendance = $result->fetch_assoc();
    
    if (!empty($attendance['clock_in'])) {
        echo json_encode(['error' => 'Already clocked in today']);
        exit;
    }
    
    // Update existing record
    $update_sql = "UPDATE attendance SET clock_in = ?, status = ? WHERE id = ?";
    $status = (date('H:i:s') > '09:15:00') ? 'Late' : 'Present';
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $current_time, $status, $attendance['id']);
    
    if ($update_stmt->execute()) {
        // Update user table
        $update_user_sql = "UPDATE users SET clock_in = ?, today_status = ? WHERE id = ?";
        $today_status = "Clocked In";
        $update_user_stmt = $conn->prepare($update_user_sql);
        $update_user_stmt->bind_param("ssi", $current_time, $today_status, $user_id);
        $update_user_stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update attendance']);
    }
} else {
    // Create new attendance record
    $insert_sql = "INSERT INTO attendance (user_id, date, clock_in, status) VALUES (?, ?, ?, ?)";
    $status = (date('H:i:s') > '09:15:00') ? 'Late' : 'Present';
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("isss", $user_id, $current_date, $current_time, $status);
    
    if ($insert_stmt->execute()) {
        // Update user table
        $update_user_sql = "UPDATE users SET clock_in = ?, today_status = ? WHERE id = ?";
        $today_status = "Clocked In";
        $update_user_stmt = $conn->prepare($update_user_sql);
        $update_user_stmt->bind_param("ssi", $current_time, $today_status, $user_id);
        $update_user_stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to create attendance record']);
    }
}

$conn->close();
?>