<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed!']));
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in!']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['notification_id'])) {
    echo json_encode(['error' => 'Notification ID is required!']);
    exit;
}

$notification_id = $_POST['notification_id'];
$mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] === 'true';

if ($mark_all) {
    $sql = "UPDATE notifications SET `read` = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    $sql = "UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to mark notification as read.']);
}

$conn->close();
?>