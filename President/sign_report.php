<?php
session_start();

// Check if user is logged in and has president role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'president') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$report_id = $_POST['report_id'] ?? null;
$comments = $_POST['comments'] ?? '';

if (!$report_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

// Prepare SQL to update report status
$sql = "UPDATE reports 
        SET status = 'signed', 
            signed_by = ?, 
            signed_date = NOW(), 
            comments = ?
        WHERE id = ? AND status = 'pending'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $_SESSION['user_id'], $comments, $report_id);

if ($stmt->execute()) {
    // Check if exactly one row was updated
    if ($stmt->affected_rows === 1) {
        echo json_encode(['success' => true, 'message' => 'Report signed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Report not found or already signed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>