<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has president role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'president') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = intval($_POST['id']);

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

// Delete the notification
// Note: In a production environment, you might consider just marking it as "dismissed" instead of deleting
$sql = "DELETE FROM notifications WHERE id = ? AND (user_id = ? OR user_id = 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to dismiss notification']);
}

$conn->close();
?>